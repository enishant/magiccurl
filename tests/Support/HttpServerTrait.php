<?php

declare(strict_types=1);

namespace Enishant\MagicCurl\Tests\Support;

/**
 * Boots PHP's built-in web server against tests/fixtures/router.php so
 * integration tests can exercise MagicCurl against a real HTTP server on
 * loopback, without any external network access.
 */
trait HttpServerTrait
{
    private static $serverProcess;
    private static string $serverBaseUrl;

    public static function startHttpServer(): void
    {
        $host = '127.0.0.1';
        $port = 8917 + (int) (getmypid() % 500);
        $router = __DIR__ . '/../fixtures/router.php';

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $command = sprintf(
            '%s -d display_errors=0 -S %s:%d %s',
            escapeshellarg(PHP_BINARY),
            $host,
            $port,
            escapeshellarg($router)
        );

        self::$serverProcess = proc_open($command, $descriptorSpec, $pipes);

        if (self::$serverProcess === false) {
            self::fail('Unable to start the built-in PHP server for integration tests.');
        }

        self::$serverBaseUrl = "http://{$host}:{$port}";

        // Wait for the server to accept connections (bounded to avoid hanging CI).
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 0.1);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50000);
        }

        self::fail('Timed out waiting for the built-in PHP server to start.');
    }

    public static function stopHttpServer(): void
    {
        if (self::$serverProcess !== null && is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    protected static function serverUrl(string $path = ''): string
    {
        return self::$serverBaseUrl . $path;
    }
}
