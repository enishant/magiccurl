# Changelog

All notable changes to this project are documented in this file.

## [2.0.0] - PHP 8+ modernization

This release modernizes MagicCurl for PHP 8.0–8.4, fixes several longstanding
bugs, and improves reliability, code quality, and test coverage. The public
API (`MagicCurl::__construct()`, `get()`, `post()`, `request()`) is preserved,
but a few behaviors changed — see **Breaking changes** below.

### Added
- `put()`, `patch()`, and `delete()` convenience methods (the underlying
  `request()` method already supported arbitrary HTTP verbs; these simply
  mirror `get()`/`post()` for symmetry).
- GET requests now support query parameters: `get($url, $payload)` merges
  `$payload` into the URL's query string. Previously the `$payload` argument
  to `get()` was silently ignored, despite being documented in the README.
- New constructor options: `verify_ssl`, `fail_on_http_error`, `timeout`,
  `connect_timeout`, `retries`, `retry_delay_ms`.
- Automatic retry of transport-level failures (DNS/connect/timeout/send/
  receive errors) when `retries` is configured. HTTP-level errors (4xx/5xx)
  are never auto-retried, since the request already reached the server.
- Full PHPUnit test suite (41 tests / 70 assertions), including integration
  tests against a local PHP built-in server fixture, and a GitHub Actions
  workflow testing PHP 8.0–8.4.
- PHPDoc across all public methods and the options array shape.

### Fixed
- **POST payloads were silently dropped** when the payload was a non-empty
  string that wasn't valid JSON (e.g. an already URL-encoded body or plain
  text). The body is now sent as-is for any non-empty string payload.
- **Missing `Undefined array key` warnings** in the constructor when
  `create_log` (and other options) were omitted — PHP 8 promotes these to
  warnings and they are now avoided entirely.
- **Dynamic property creation** (`$this->app_name`, `$this->app_version`)
  removed; these were redundant with the existing `APP_NAME`/`APP_VERSION`
  constants and would trigger deprecation warnings on PHP 8.2+.
- **Resource leak on error**: the cURL handle is now always closed on every
  code path, including transport errors.
- A regex bug in URL validation (`(.[a-z0-9-]+)*` — an unescaped `.` matches
  *any* character, not a literal dot) was replaced with `filter_var(...,
  FILTER_VALIDATE_URL)` plus an explicit http/https scheme check.
- `curl_getinfo()` is now called once per request instead of four times.
- Errors caused by `CURLOPT_FAILONERROR` no longer discard the HTTP status
  code — it's now included in the error response when known.

### Changed
- **SSL verification is now enabled by default** (`CURLOPT_SSL_VERIFYHOST`
  and `CURLOPT_SSL_VERIFYPEER` were previously hardcoded to `false` for every
  request). This is a security-motivated default change. Set `verify_ssl =>
  false` to restore the previous (insecure) behavior, e.g. for local
  development against a self-signed certificate.
- Non-2xx HTTP responses are, by default, now returned as `status => success`
  with the real `status_code`, headers, and body — instead of being
  collapsed into a generic `status => error` with no body. Set
  `fail_on_http_error => true` to restore the previous behavior.
- JSON responses are now always decoded to associative arrays (via
  `json_decode($body, true)`) for consistency. Previously, top-level JSON
  *objects* decoded to `stdClass`, while top-level JSON *arrays* decoded to
  PHP arrays — an inconsistency in the original implementation.
- `CURLOPT_ENCODING` is now set to `''` (accept all encodings supported by
  the local libcurl build) instead of the hardcoded `'gzip,deflate'`.
- Connection timeout is now configurable and set explicitly
  (`CURLOPT_CONNECTTIMEOUT`), separate from the overall request timeout.
- Internal property and method names were normalized to camelCase
  (`$create_log` → `$createLog`, `is_json()` → `isJson()`, etc.) per PSR-1/
  PSR-12. These were `protected`/`private` and are not part of the public
  API, so this does not affect consumers.
- Log timestamps now use `Y-m-d H:i:s` instead of the non-standard
  `d m Y H:i:s` (day, month, year separated by spaces).
- Minimum supported PHP version is now `^8.0` (previously `7.*|^8.1`, which
  — due to the version constraint syntax — technically excluded PHP 8.0.x).

### Removed
- `is_url_exists()`, a private, unused method that made a live HTTP request
  via `get_headers()`. It was never called anywhere in the class.

### Breaking changes
These are the behavior changes most likely to affect existing integrations:

1. **SSL verification default**: requests to hosts with invalid/self-signed
   certificates will now fail unless `verify_ssl => false` is set.
2. **HTTP error status handling**: code that checked `$response['status'] ===
   'error'` to detect 4xx/5xx responses must now check `$response['status_code']`
   instead, or pass `fail_on_http_error => true` to restore the old behavior.
3. **JSON object responses**: code that accessed decoded JSON objects with
   `->property` syntax (`stdClass`) must switch to array syntax
   (`['property']`), since responses now always decode to arrays.

### PHP versions tested
- PHP 8.1.2 (CLI, with `ext-curl`) — full local test suite run (41/41 passing).
- PHP 8.0, 8.2, 8.3, 8.4 — verified via manual review against each version's
  changelog for removed/deprecated features, and covered by the included
  GitHub Actions matrix for continuous verification.
