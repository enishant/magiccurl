<?php

declare(strict_types=1);

namespace Enishant\MagicCurl;

/**
 * A lightweight, dependency-free HTTP client built on top of the PHP cURL extension.
 *
 * MagicCurl wraps the most common HTTP verbs (GET, POST, PUT, PATCH, DELETE) behind a
 * small, array-based API and normalizes every response into a predictable associative
 * array shape, whether the request succeeded or failed. No exceptions are thrown for
 * ordinary request failures (invalid URLs, network errors, timeouts); instead the
 * returned array's `status` key is set to `error` and a human-readable `message` is
 * included, so callers can rely on a single control-flow shape.
 *
 * @author Nishant Vaity <enishant@gmail.com>
 */
class MagicCurl
{
    /** Library name, used to build the default User-Agent string. */
    public const APP_NAME = 'MagicCurl';

    /** Library version, used to build the default User-Agent string. */
    public const APP_VERSION = '2.0.0';

    /** Default total request timeout, in seconds. */
    private const DEFAULT_TIMEOUT = 10;

    /** Default connection timeout, in seconds. */
    private const DEFAULT_CONNECT_TIMEOUT = 10;

    /** HTTP methods that may carry a request body. */
    private const METHODS_WITH_BODY = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * cURL error codes that indicate the request never reached the server
     * (as opposed to CURLE_HTTP_RETURNED_ERROR, where it did). Only these
     * are safe to retry automatically, regardless of HTTP method.
     */
    private const RETRYABLE_CURL_ERRORS = [
        CURLE_COULDNT_RESOLVE_PROXY,
        CURLE_COULDNT_RESOLVE_HOST,
        CURLE_COULDNT_CONNECT,
        CURLE_OPERATION_TIMEDOUT,
        CURLE_GOT_NOTHING,
        CURLE_SEND_ERROR,
        CURLE_RECV_ERROR,
    ];

    /** Whether request/response activity is written to {@see self::$logPath}. */
    protected bool $createLog = false;

    /** Destination file for the debug log, when {@see self::$createLog} is enabled. */
    protected string $logPath = '';

    /** Custom User-Agent header. Falls back to "MagicCurl/{version}" when empty. */
    protected string $userAgent = '';

    /** Whether PHP error display/reporting was elevated for this instance. */
    protected bool $debug = false;

    /**
     * Whether to verify the remote SSL certificate and host. Defaults to true.
     * Only disable this for trusted local/development endpoints; disabling
     * certificate verification in production is a security risk.
     */
    protected bool $verifySsl = true;

    /**
     * Whether a non-2xx HTTP response should be treated as a request error
     * (legacy behaviour). Defaults to false, so callers get the full
     * response (status code, body, etc.) for every HTTP status and decide
     * for themselves how to handle it.
     */
    protected bool $failOnHttpError = false;

    /** Maximum time, in seconds, allowed for the entire request. */
    protected int $timeout = self::DEFAULT_TIMEOUT;

    /** Maximum time, in seconds, allowed for the connection phase. */
    protected int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT;

    /** Number of automatic retries for transport-level failures (0 = disabled). */
    protected int $retries = 0;

    /** Delay between retry attempts, in milliseconds. */
    protected int $retryDelayMs = 100;

    /**
     * @param array{
     *     debug?: bool,
     *     create_log?: bool,
     *     log_path?: string,
     *     user_agent?: string,
     *     verify_ssl?: bool,
     *     fail_on_http_error?: bool,
     *     timeout?: int,
     *     connect_timeout?: int,
     *     retries?: int,
     *     retry_delay_ms?: int
     * } $options {
     *     @var bool   $debug               Display all PHP errors for this process. Default false.
     *     @var bool   $create_log          Log user agent, headers, request and response. Default false.
     *     @var string $log_path            Path to an existing, writable log file.
     *     @var string $user_agent          Custom User-Agent header.
     *     @var bool   $verify_ssl          Verify the remote SSL certificate/host. Default true.
     *     @var bool   $fail_on_http_error  Treat non-2xx responses as errors. Default false.
     *     @var int    $timeout             Total request timeout, in seconds. Default 10.
     *     @var int    $connect_timeout     Connection timeout, in seconds. Default 10.
     *     @var int    $retries             Automatic retries for transport failures. Default 0.
     *     @var int    $retry_delay_ms      Delay between retries, in milliseconds. Default 100.
     * }
     */
    public function __construct(array $options = [])
    {
        if ($options === []) {
            return;
        }

        if (($options['debug'] ?? false) === true) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
            $this->debug = true;
        }

        if (($options['create_log'] ?? false) === true) {
            $this->createLog = true;
        }

        if (is_string($options['user_agent'] ?? null) && $options['user_agent'] !== '') {
            $this->userAgent = $options['user_agent'];
        }

        if (is_string($options['log_path'] ?? null) && $options['log_path'] !== '') {
            $this->logPath = $options['log_path'];
        }

        if (array_key_exists('verify_ssl', $options)) {
            $this->verifySsl = (bool) $options['verify_ssl'];
        }

        if (array_key_exists('fail_on_http_error', $options)) {
            $this->failOnHttpError = (bool) $options['fail_on_http_error'];
        }

        if (isset($options['timeout']) && is_int($options['timeout']) && $options['timeout'] >= 0) {
            $this->timeout = $options['timeout'];
        }

        if (
            isset($options['connect_timeout']) && is_int($options['connect_timeout'])
            && $options['connect_timeout'] >= 0
        ) {
            $this->connectTimeout = $options['connect_timeout'];
        }

        if (isset($options['retries']) && is_int($options['retries']) && $options['retries'] >= 0) {
            $this->retries = $options['retries'];
        }

        if (
            isset($options['retry_delay_ms']) && is_int($options['retry_delay_ms'])
            && $options['retry_delay_ms'] >= 0
        ) {
            $this->retryDelayMs = $options['retry_delay_ms'];
        }
    }

    /**
     * Send a GET request.
     *
     * @param array<string,mixed> $payload Query parameters merged into the URL.
     * @param array<int,string>   $headers Raw HTTP headers, e.g. ['Accept: application/json'].
     *
     * @return array<string,mixed>
     */
    public function get(string $url = '', array $payload = [], array $headers = []): array
    {
        return $this->request('GET', $url, $payload, $headers);
    }

    /**
     * Send a POST request.
     *
     * @param array<string,mixed>|string $payload Array is sent as form-encoded;
     *                                             string is sent as-is (e.g. pre-encoded JSON).
     * @param array<int,string>          $headers Raw HTTP headers.
     *
     * @return array<string,mixed>
     */
    public function post(string $url = '', array|string $payload = [], array $headers = []): array
    {
        return $this->request('POST', $url, $payload, $headers);
    }

    /**
     * Send a PUT request.
     *
     * @param array<string,mixed>|string $payload Array is sent as form-encoded; string is sent as-is.
     * @param array<int,string>          $headers Raw HTTP headers.
     *
     * @return array<string,mixed>
     */
    public function put(string $url = '', array|string $payload = [], array $headers = []): array
    {
        return $this->request('PUT', $url, $payload, $headers);
    }

    /**
     * Send a PATCH request.
     *
     * @param array<string,mixed>|string $payload Array is sent as form-encoded; string is sent as-is.
     * @param array<int,string>          $headers Raw HTTP headers.
     *
     * @return array<string,mixed>
     */
    public function patch(string $url = '', array|string $payload = [], array $headers = []): array
    {
        return $this->request('PATCH', $url, $payload, $headers);
    }

    /**
     * Send a DELETE request.
     *
     * @param array<string,mixed>|string $payload Array is sent as form-encoded; string is sent as-is.
     * @param array<int,string>          $headers Raw HTTP headers.
     *
     * @return array<string,mixed>
     */
    public function delete(string $url = '', array|string $payload = [], array $headers = []): array
    {
        return $this->request('DELETE', $url, $payload, $headers);
    }

    /**
     * Send an HTTP request via cURL and return a normalized response array.
     *
     * The returned array always has a `status` key of `success` or `error`.
     * On success it also contains `status_code`, `content_type`,
     * `effective_url`, `primary_ip`, and `response` (the body, JSON-decoded
     * into an associative array when it looks like JSON). On error it
     * contains `message` and, when known, `status_code`.
     *
     * @param string                      $method  HTTP method, e.g. GET, POST, PUT, PATCH, DELETE.
     * @param string                      $url     Absolute request URL (http/https).
     * @param array<string,mixed>|string  $payload For GET, an array is merged into the query string.
     *                                             For methods with a body, an array is sent as
     *                                             `application/x-www-form-urlencoded`; a string is
     *                                             sent as the raw body (e.g. pre-encoded JSON).
     * @param array<int,string>           $headers Raw HTTP headers,
     *                                              e.g. ['Accept: application/json'].
     *
     * @return array<string,mixed>
     */
    public function request(
        string $method = 'GET',
        string $url = '',
        array|string $payload = [],
        array $headers = []
    ): array {
        $method = strtoupper($method);

        if (!$this->isValidUrl($url)) {
            return $this->errorResponse('A valid absolute URL (http/https) is required.');
        }

        if ($method === 'GET' && is_array($payload) && $payload !== []) {
            $url = $this->buildUrlWithQuery($url, $payload);
        }

        $ch = curl_init($url);

        if ($ch === false) {
            return $this->errorResponse('Unable to initialize a cURL handle.');
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            $this->userAgent !== '' ? $this->userAgent : self::APP_NAME . '/' . self::APP_VERSION
        );

        if ($headers !== []) {
            $this->log(self::APP_NAME . ' Header', $headers);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if (in_array($method, self::METHODS_WITH_BODY, true)) {
            $body = $this->resolveRequestBody($payload);

            if ($body !== null) {
                $this->log(self::APP_NAME . ' Request', $payload);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, $this->failOnHttpError);

        [$serverOutput, $errorNumber] = $this->executeWithRetries($ch);

        if ($errorNumber !== 0) {
            $errorMessage = curl_error($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
            curl_close($ch);

            return $this->errorResponse($errorMessage !== '' ? $errorMessage : 'Unknown cURL error.', $statusCode);
        }

        $info = curl_getinfo($ch);
        curl_close($ch);

        $serverOutput = is_string($serverOutput) ? $serverOutput : '';

        $response = [
            'status' => 'success',
            'status_code' => $info['http_code'] ?? null,
            'content_type' => $info['content_type'] ?? null,
            'effective_url' => $info['url'] ?? $url,
            'primary_ip' => $info['primary_ip'] ?? null,
            'response' => $this->isJson($serverOutput) ? json_decode($serverOutput, true) : $serverOutput,
        ];

        $this->log(self::APP_NAME . ' Response', $response);

        return $response;
    }

    /**
     * Execute the request, retrying transport-level failures up to
     * {@see self::$retries} times.
     *
     * @return array{0: string|bool, 1: int} The last curl_exec() result and curl_errno().
     */
    private function executeWithRetries(\CurlHandle $ch): array
    {
        $attempt = 0;

        do {
            $serverOutput = curl_exec($ch);
            $errorNumber = curl_errno($ch);

            $canRetry = $errorNumber !== 0
                && in_array($errorNumber, self::RETRYABLE_CURL_ERRORS, true)
                && $attempt < $this->retries;

            if (!$canRetry) {
                return [$serverOutput, $errorNumber];
            }

            $attempt++;
            usleep($this->retryDelayMs * 1000);
        } while (true);
    }

    /**
     * Build a normalized error response.
     *
     * @return array<string,mixed>
     */
    private function errorResponse(string $message, ?int $statusCode = null): array
    {
        return [
            'status' => 'error',
            'message' => $message,
            'status_code' => $statusCode,
        ];
    }

    /**
     * Normalize a request payload into a cURL-compatible request body.
     * Returns null when there is nothing to send.
     */
    private function resolveRequestBody(array|string $payload): ?string
    {
        if (is_array($payload)) {
            return $payload === [] ? null : http_build_query($payload);
        }

        return $payload === '' ? null : $payload;
    }

    /**
     * Merge query parameters into a URL, correctly handling URLs that already
     * contain a query string.
     *
     * @param array<string,mixed> $params
     */
    private function buildUrlWithQuery(string $url, array $params): string
    {
        if ($params === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($params);
    }

    /**
     * Write a single line to the debug log, when logging is enabled and the
     * configured log file exists and is writable. Failures to log never
     * interrupt the calling request.
     *
     * @param array<mixed,mixed>|string $data
     */
    private function log(string $context, array|string $data = ''): void
    {
        if (!$this->createLog || $this->logPath === '' || $data === [] || $data === '') {
            return;
        }

        if (!is_file($this->logPath) || !is_writable($this->logPath)) {
            if ($this->debug) {
                error_log(sprintf('[%s] Log file "%s" is missing or not writable.', self::APP_NAME, $this->logPath));
            }

            return;
        }

        $fp = fopen($this->logPath, 'a');

        if ($fp === false) {
            return;
        }

        try {
            $line = sprintf(
                '%s :: %s :: %s%s',
                date('Y-m-d H:i:s'),
                strtoupper($context),
                is_array($data) ? (string) json_encode($data) : $data,
                PHP_EOL
            );

            fwrite($fp, $line);
        } finally {
            fclose($fp);
        }
    }

    /**
     * Determine whether a URL is a well-formed, absolute http/https URL.
     */
    private function isValidUrl(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    /**
     * Determine whether a string is valid JSON.
     */
    private function isJson(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
