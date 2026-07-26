<?php

declare(strict_types=1);

namespace Enishant\MagicCurl\Tests;

use Enishant\MagicCurl\MagicCurl;
use Enishant\MagicCurl\Tests\Support\HttpServerTrait;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \Enishant\MagicCurl\MagicCurl
 */
final class MagicCurlTest extends TestCase
{
    use HttpServerTrait;

    public static function setUpBeforeClass(): void
    {
        self::startHttpServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopHttpServer();
    }

    private function property(MagicCurl $client, string $name): mixed
    {
        $property = (new ReflectionClass($client))->getProperty($name);
        $property->setAccessible(true);

        return $property->getValue($client);
    }

    private function invoke(MagicCurl $client, string $method, array $args = []): mixed
    {
        $reflectionMethod = (new ReflectionClass($client))->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($client, $args);
    }

    // -- Construction / option parsing ----------------------------------------------------

    public function testDefaultOptions(): void
    {
        $client = new MagicCurl();

        self::assertTrue($this->property($client, 'verifySsl'));
        self::assertFalse($this->property($client, 'failOnHttpError'));
        self::assertFalse($this->property($client, 'createLog'));
        self::assertSame('', $this->property($client, 'userAgent'));
        self::assertSame(10, $this->property($client, 'timeout'));
        self::assertSame(10, $this->property($client, 'connectTimeout'));
        self::assertSame(0, $this->property($client, 'retries'));
    }

    public function testOptionsAreApplied(): void
    {
        $client = new MagicCurl([
            'user_agent' => 'CustomAgent/1.0',
            'verify_ssl' => false,
            'fail_on_http_error' => true,
            'timeout' => 5,
            'connect_timeout' => 2,
            'retries' => 3,
            'retry_delay_ms' => 25,
        ]);

        self::assertSame('CustomAgent/1.0', $this->property($client, 'userAgent'));
        self::assertFalse($this->property($client, 'verifySsl'));
        self::assertTrue($this->property($client, 'failOnHttpError'));
        self::assertSame(5, $this->property($client, 'timeout'));
        self::assertSame(2, $this->property($client, 'connectTimeout'));
        self::assertSame(3, $this->property($client, 'retries'));
        self::assertSame(25, $this->property($client, 'retryDelayMs'));
    }

    public function testInvalidlyTypedOptionsAreIgnoredWithoutError(): void
    {
        $client = new MagicCurl([
            'timeout' => 'not-a-number',
            'retries' => -1,
            'user_agent' => 12345,
        ]);

        self::assertSame(10, $this->property($client, 'timeout'));
        self::assertSame(0, $this->property($client, 'retries'));
        self::assertSame('', $this->property($client, 'userAgent'));
    }

    // -- isValidUrl -------------------------------------------------------------------------

    /**
     * @dataProvider urlProvider
     */
    public function testIsValidUrl(string $url, bool $expected): void
    {
        $client = new MagicCurl();

        self::assertSame($expected, $this->invoke($client, 'isValidUrl', [$url]));
    }

    public static function urlProvider(): array
    {
        return [
            'https url' => ['https://example.com/path?x=1', true],
            'http url' => ['http://example.com', true],
            'ftp scheme rejected' => ['ftp://example.com/file', false],
            'no scheme' => ['example.com/path', false],
            'empty string' => ['', false],
            'javascript scheme rejected' => ['javascript:alert(1)', false],
        ];
    }

    // -- isJson -----------------------------------------------------------------------------

    /**
     * @dataProvider jsonProvider
     */
    public function testIsJson(string $value, bool $expected): void
    {
        $client = new MagicCurl();

        self::assertSame($expected, $this->invoke($client, 'isJson', [$value]));
    }

    public static function jsonProvider(): array
    {
        return [
            'object' => ['{"a":1}', true],
            'array' => ['[1,2,3]', true],
            'scalar true' => ['true', true],
            'scalar number' => ['42', true],
            'empty string' => ['', false],
            'plain text' => ['not json', false],
            'malformed' => ['{"a":', false],
        ];
    }

    // -- buildUrlWithQuery --------------------------------------------------------------------

    public function testBuildUrlWithQueryAppendsWithQuestionMark(): void
    {
        $client = new MagicCurl();

        $result = $this->invoke($client, 'buildUrlWithQuery', ['https://example.com/path', ['a' => '1']]);

        self::assertSame('https://example.com/path?a=1', $result);
    }

    public function testBuildUrlWithQueryAppendsWithAmpersandWhenQueryExists(): void
    {
        $client = new MagicCurl();

        $result = $this->invoke($client, 'buildUrlWithQuery', ['https://example.com/path?x=1', ['a' => '1']]);

        self::assertSame('https://example.com/path?x=1&a=1', $result);
    }

    public function testBuildUrlWithQueryReturnsUrlUnchangedWhenNoParams(): void
    {
        $client = new MagicCurl();

        $result = $this->invoke($client, 'buildUrlWithQuery', ['https://example.com/path', []]);

        self::assertSame('https://example.com/path', $result);
    }

    // -- resolveRequestBody -------------------------------------------------------------------

    public function testResolveRequestBodyEncodesArray(): void
    {
        $client = new MagicCurl();

        $result = $this->invoke($client, 'resolveRequestBody', [['a' => '1', 'b' => '2']]);

        self::assertSame('a=1&b=2', $result);
    }

    public function testResolveRequestBodyReturnsNullForEmptyArray(): void
    {
        $client = new MagicCurl();

        self::assertNull($this->invoke($client, 'resolveRequestBody', [[]]));
    }

    public function testResolveRequestBodyPassesStringThrough(): void
    {
        $client = new MagicCurl();

        $result = $this->invoke($client, 'resolveRequestBody', ['{"raw":"json"}']);

        self::assertSame('{"raw":"json"}', $result);
    }

    public function testResolveRequestBodyReturnsNullForEmptyString(): void
    {
        $client = new MagicCurl();

        self::assertNull($this->invoke($client, 'resolveRequestBody', ['']));
    }

    // -- Integration: GET -----------------------------------------------------------------------

    public function testGetMergesPayloadIntoQueryString(): void
    {
        $client = new MagicCurl();

        $response = $client->get(self::serverUrl('/echo'), ['foo' => 'bar', 'n' => '1']);

        self::assertSame('success', $response['status']);
        self::assertSame(200, $response['status_code']);
        self::assertSame('GET', $response['response']['method']);
        self::assertSame(['foo' => 'bar', 'n' => '1'], $response['response']['query']);
    }

    public function testGetSendsCustomHeaders(): void
    {
        $client = new MagicCurl();

        $response = $client->get(self::serverUrl('/echo'), [], ['X-Test-Header: hello']);

        self::assertSame('hello', $response['response']['headers']['X-Test-Header'] ?? null);
    }

    public function testDefaultUserAgentIsSent(): void
    {
        $client = new MagicCurl();

        $response = $client->get(self::serverUrl('/echo'));

        self::assertSame('MagicCurl/2.0.0', $response['response']['headers']['User-Agent'] ?? null);
    }

    public function testCustomUserAgentOverridesDefault(): void
    {
        $client = new MagicCurl(['user_agent' => 'MyApp/9.9']);

        $response = $client->get(self::serverUrl('/echo'));

        self::assertSame('MyApp/9.9', $response['response']['headers']['User-Agent'] ?? null);
    }

    public function testPlainTextResponseIsReturnedVerbatim(): void
    {
        $client = new MagicCurl();

        $response = $client->get(self::serverUrl('/plain'));

        self::assertSame('just some plain text', $response['response']);
    }

    public function testJsonScalarResponseIsDecoded(): void
    {
        $client = new MagicCurl();

        $response = $client->get(self::serverUrl('/json-scalar'));

        self::assertTrue($response['response']);
    }

    // -- Integration: POST/PUT/PATCH/DELETE ------------------------------------------------------

    public function testPostSendsFormEncodedArrayPayload(): void
    {
        $client = new MagicCurl();

        $response = $client->post(self::serverUrl('/echo'), ['a' => '1', 'b' => 'two']);

        self::assertSame('POST', $response['response']['method']);
        self::assertSame('a=1&b=two', $response['response']['body']);
    }

    public function testPostSendsRawStringPayloadAsIs(): void
    {
        $client = new MagicCurl();

        $json = json_encode(['a' => 1]);
        $response = $client->post(self::serverUrl('/echo'), $json, ['Content-Type: application/json']);

        self::assertSame($json, $response['response']['body']);
    }

    /**
     * @dataProvider bodyMethodProvider
     */
    public function testMethodsWithBodySendCorrectVerbAndPayload(string $verb): void
    {
        $client = new MagicCurl();

        $response = $client->{strtolower($verb)}(self::serverUrl('/echo'), ['k' => 'v']);

        self::assertSame($verb, $response['response']['method']);
        self::assertSame('k=v', $response['response']['body']);
    }

    public static function bodyMethodProvider(): array
    {
        return [
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    // -- HTTP error status handling ---------------------------------------------------------------

    public function testNonSuccessStatusIsReturnedAsSuccessByDefault(): void
    {
        $client = new MagicCurl();

        $response = $client->get(self::serverUrl('/status'), ['code' => '404']);

        self::assertSame('success', $response['status']);
        self::assertSame(404, $response['status_code']);
    }

    public function testNonSuccessStatusBecomesErrorWhenFailOnHttpErrorEnabled(): void
    {
        $client = new MagicCurl(['fail_on_http_error' => true]);

        $response = $client->get(self::serverUrl('/status'), ['code' => '500']);

        self::assertSame('error', $response['status']);
        self::assertSame(500, $response['status_code']);
    }

    // -- Invalid input --------------------------------------------------------------------------

    public function testInvalidUrlReturnsErrorWithoutMakingARequest(): void
    {
        $client = new MagicCurl();

        $response = $client->get('not-a-url');

        self::assertSame('error', $response['status']);
        self::assertNull($response['status_code']);
    }

    // -- Timeouts and retries ---------------------------------------------------------------------

    public function testTimeoutProducesErrorResponse(): void
    {
        $client = new MagicCurl(['timeout' => 1]);

        $response = $client->get(self::serverUrl('/slow'));

        self::assertSame('error', $response['status']);
    }

    public function testRetriesAreAttemptedOnConnectionFailure(): void
    {
        // Port 1 is a reserved/unassigned TCP port; connecting to it on loopback
        // fails immediately with "connection refused", which is retryable.
        $client = new MagicCurl(['retries' => 2, 'retry_delay_ms' => 20]);

        $start = microtime(true);
        $response = $client->get('http://127.0.0.1:1/');
        $elapsedMs = (microtime(true) - $start) * 1000;

        self::assertSame('error', $response['status']);
        self::assertGreaterThanOrEqual(40, $elapsedMs);
    }

    // -- Logging ------------------------------------------------------------------------------

    public function testLoggingWritesEntriesWhenEnabled(): void
    {
        $logPath = tempnam(sys_get_temp_dir(), 'magiccurl_log_');
        self::assertNotFalse($logPath);

        $client = new MagicCurl(['create_log' => true, 'log_path' => $logPath]);
        $client->get(self::serverUrl('/echo'), [], ['X-Test: 1']);

        $contents = file_get_contents($logPath);
        unlink($logPath);

        self::assertStringContainsString('MAGICCURL HEADER', $contents);
        self::assertStringContainsString('MAGICCURL RESPONSE', $contents);
    }

    public function testLoggingIsSkippedWhenDisabled(): void
    {
        $logPath = tempnam(sys_get_temp_dir(), 'magiccurl_log_');
        self::assertNotFalse($logPath);

        $client = new MagicCurl();
        $client->get(self::serverUrl('/echo'));

        self::assertSame('', file_get_contents($logPath));
        unlink($logPath);
    }
}
