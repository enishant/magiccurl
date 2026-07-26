# MagicCurl PHP Library

[![Stable](https://poser.pugx.org/enishant/magiccurl/v)](https://packagist.org/packages/enishant/magiccurl)
[![PHP Version Require](http://poser.pugx.org/enishant/magiccurl/require/php)](https://packagist.org/packages/enishant/magiccurl)
[![License](https://poser.pugx.org/enishant/magiccurl/license)](https://packagist.org/packages/enishant/magiccurl)
[![Downloads](https://poser.pugx.org/enishant/magiccurl/downloads)](https://packagist.org/packages/enishant/magiccurl)

A lightweight, dependency-free HTTP client built on the PHP cURL extension.
MagicCurl wraps the most common HTTP verbs behind a small, array-based API and
always returns a predictable response shape — no exceptions to catch for
ordinary request failures.

### Prerequisites
- PHP 8.0 or newer (tested through PHP 8.4)
- PHP `curl` extension
- PHP `json` extension

> Looking for the PHP 7 / 8.1-only release? See the `1.x` tags.

## Installation

- If your project uses Composer, run:

```bash
composer require enishant/magiccurl
```

- If you are not using Composer, download the latest release from
  [the releases section](https://github.com/enishant/magiccurl/releases)
  (the `magiccurl.zip` file), then `require` `src/MagicCurl.php` directly.

Note: This PHP library follows the following practices:

- Namespaced under `Enishant\MagicCurl`
- Options are passed as an array instead of multiple arguments wherever possible
- All requests and responses are communicated using the PHP cURL extension
- No exceptions are thrown for ordinary request failures (invalid URL,
  network error, timeout, non-2xx status by default); every call returns an
  array with a `status` key of `success` or `error`

## Basic Usage

Instantiate MagicCurl with or without options.

```php
use Enishant\MagicCurl\MagicCurl;

$client = new MagicCurl();
```

### Options

| Option                | Type   | Default | Description                                                              |
|------------------------|--------|---------|----------------------------------------------------------------------------|
| `create_log`           | bool   | `false` | Log user agent, headers, request and response.                            |
| `log_path`             | string | `''`    | Path to an existing, writable log file. Required when `create_log` is true.|
| `debug`                | bool   | `false` | Display all PHP errors for this process.                                  |
| `user_agent`           | string | `''`    | Custom [User-Agent](https://en.wikipedia.org/wiki/User_agent). Defaults to `MagicCurl/{version}`. |
| `verify_ssl`           | bool   | `true`  | Verify the remote SSL certificate and host. Only disable for trusted local/dev endpoints. |
| `fail_on_http_error`   | bool   | `false` | Treat non-2xx HTTP responses as `status => error`. When `false` (default), you get the full response (status code, body, etc.) for every HTTP status. |
| `timeout`              | int    | `10`    | Total request timeout, in seconds.                                        |
| `connect_timeout`      | int    | `10`    | Connection timeout, in seconds.                                           |
| `retries`              | int    | `0`     | Automatic retries for transport-level failures (DNS/connect/timeout/send/receive). HTTP error statuses are never auto-retried. |
| `retry_delay_ms`       | int    | `100`   | Delay between retry attempts, in milliseconds.                            |

#### Create a log file, and update ownership & permissions

```bash
sudo touch /path/to/magiccurl.log
sudo chown www-data:www-data /path/to/magiccurl.log
sudo chmod 644 /path/to/magiccurl.log
```

```php
use Enishant\MagicCurl\MagicCurl;

$options = [
    'create_log' => true,
    'log_path'   => '/path/to/magiccurl.log',
    'debug'      => true,
    'user_agent' => 'MagicCurl/1.0',
];

$client = new MagicCurl($options);
```

### Making requests

All the methods are invoked as `$client->method()` and always return an
associative array.

```php
// GET request
$client->get($url);
$client->get($url, $payload, $headers); // $payload is merged into the query string

// POST request
$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
];
$payload = ['data' => 'in array']; // sent as application/x-www-form-urlencoded
$client->post($url, $payload, $headers);

// Or send a pre-encoded body (e.g. JSON) as a string:
$client->post($url, json_encode(['data' => 'value']), $headers);

// PUT / PATCH / DELETE work the same way
$client->put($url, $payload, $headers);
$client->patch($url, $payload, $headers);
$client->delete($url, $payload, $headers);

// Any HTTP verb, including custom ones, via request()
$client->request('HEAD', $url);
```

### Reading the response

Every call returns an array shaped like one of these:

```php
// Success
[
    'status'        => 'success',
    'status_code'   => 200,
    'content_type'  => 'application/json',
    'effective_url' => 'https://example.com/',
    'primary_ip'    => '93.184.216.34',
    'response'      => [...], // JSON responses are decoded to arrays; otherwise the raw body string
]

// Error (invalid URL, network failure, or a non-2xx status when
// fail_on_http_error is true)
[
    'status'      => 'error',
    'message'     => 'Could not resolve host: example.invalid',
    'status_code' => null, // populated when a status code is known
]
```

## Upgrading from 1.x

See [CHANGELOG.md](CHANGELOG.md) for the full list of fixes and behavior
changes in 2.0. In short:

- SSL certificates are now verified by default. Pass `verify_ssl => false`
  to opt back into the old (insecure) behavior.
- Non-2xx responses are returned as `status => success` with the real
  `status_code`, unless you pass `fail_on_http_error => true`.
- JSON object responses now decode to arrays instead of `stdClass`.

## Development

```bash
composer install
composer run lint   # php -l across src/ and tests/
composer run test   # PHPUnit suite
```

## Contributing
All contributions for enhancement and fixes will be accepted through pull request, you can also contribute by reporting issues, [Click here](https://github.com/enishant/magiccurl/issues/new) to report an issue.

## License
The MagicCurl PHP Library is released under the MIT License. See [LICENSE](LICENSE) file for more details.
