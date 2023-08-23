# MagicCurl PHP Library

[![Stable](https://poser.pugx.org/enishant/magiccurl/v)](https://packagist.org/packages/enishant/magiccurl)
[![PHP Version Require](http://poser.pugx.org/enishant/magiccurl/require/php)](https://packagist.org/packages/enishant/magiccurl)
[![License](https://poser.pugx.org/enishant/magiccurl/license)](https://packagist.org/packages/enishant/magiccurl)
[![Downloads](https://poser.pugx.org/enishant/magiccurl/downloads)](https://packagist.org/packages/enishant/magiccurl)

### Prerequisites
- A minimum of PHP 7.x upto 8.1
- PHP cURL extension

## Installation

-   If your project using composer, run the below command

```php
composer require enishant/magiccurl:1.0
```

- If you are not using composer, download the latest release from [the releases section](https://github.com/enishant/magiccurl/releases).
    **You should download the `magiccurl.zip` file**.
    After that, include `MagicCurl.php` in your application and you can use the MagicCurl methods by creating it's instance.

Note: This PHP library follows the following practices:

- Namespaced under `Enishant\MagicCurl`
- Options are passed as an array instead of multiple arguments wherever possible
- All requests and responses are communicated using PHP CURL extension

## Basic Usage

Instantiate the MagicCurl php instance with/without providing an options.

```php
use Enishant\MagicCurl\MagicCurl;

$client = new MagicCurl;
```
### Options
- `create_log` - Creates log for user agent, header, request and response.
- `log_path` - Provide log file path.
- `debug` - Display all php errors.
- `user_agent` - Custom [User Agent](https://en.wikipedia.org/wiki/User_agent) name.

#### Create log file, update onwership & permissions
`sudo touch /path/to/magiccurl.log`

`sudo chown www-data:www-data /path/to/magiccurl.log`

`sudo chmod 644 /path/to/magiccurl.log`

```php
use Enishant\MagicCurl\MagicCurl;

$options = [
	'create_log' => true,
	'log_path'   => '/path/to/magiccurl.log',
	'debug'      => true,
	'user_agent' => 'MagicCurl/1.0'
];

$client = new MagicCurl( $options );
```

The resources can be accessed via the `$client` object. All the methods invocations follows the following pattern

```php
    // $client->function() to access the methods
    
    //Example - Request with GET method
    $client->get($url);
    $client->get($url, $payload, $headers);

    //Example - Request with POST method
    $headers = [
      'Accept: application/json',
      'Content-Type: application/json',
    ];
    $payload = ['data'=>'in array'];
    $client->post($url, $payload, $headers);
```
## Contributing
All contributions for enhancement and fixes will be accepted through pull request, you can also contribute by reporting issues, [Click here](https://github.com/enishant/magiccurl/issues/new) to report an issue.

## License
The MagicCurl PHP Library is released under the MIT License. See [LICENSE](LICENSE) file for more details.
