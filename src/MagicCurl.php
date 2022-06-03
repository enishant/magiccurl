<?php
namespace Enishant\MagicCurl;

class MagicCurl
{
	/**
	* Request Method.
	* @var string
	*/
	protected $method;

	/**
	* Request URL.
	* @var string
	*/
	protected $url;

	/**
	* Request Payload.
	* @var array
	*/
	protected $payload;

	/**
	* Request Headers.
	* @var array
	*/
	protected $headers;

	/**
	* Create Debug Log.
	* @var bool
	*/
	protected $create_log;

	/**
	* User Agent.
	* @var string
	*/
	protected $user_agent;

	/**
	* App Version.
	* @var string
	*/
	CONST APP_VERSION = '1.0.0';

	public function __construct($options = [])
	{
		$this->app_name    = str_replace('\\', '_', get_class($this));
		$this->app_version = self::APP_VERSION;
		$this->create_log  = false;
		$this->user_agent  = '';

		if(isset($options) && !empty($options) && is_array($options))
		{
			if(isset($options['debug']) && $options['debug'] === true)
			{
				ini_set('display_errors', 1);
				ini_set('display_startup_errors', 1);
				error_reporting(E_ALL);
			}

			if($options['create_log'] === true)
			{
				$this->create_log = $options['create_log'];
			}

			if(isset($options['user_agent']) && !empty($options['user_agent']) && is_string($options['user_agent']))
			{
				$this->user_agent = $options['user_agent'];
			}
		}
	}

	public function get($url = '', $headers = [], $payload = [])
	{
		$this->method  = 'GET';
		$this->url     = $url;
		$this->payload = $payload;
		$this->headers = $headers;

		return $this->request($this->method, $this->url, $this->payload, $this->headers);
	}

	public function post($url = '', $headers = [], $payload = [])
	{
		$this->method  = 'POST';
		$this->url     = $url;
		$this->payload = $payload;
		$this->headers = $headers;

		return $this->request($this->method, $this->url, $this->payload, $this->headers);
	}

	public function request($method = 'GET', $url = '', $headers = [], $payload = [])
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		if(!empty($this->user_agent))
		{
			$this->log($this->app_name . ' user_agent', $this->user_agent);

			curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		}
		else
		{
			curl_setopt($ch, CURLOPT_USERAGENT, $this->app_name . '/' . $this->app_version);	
		}

		if(!empty($headers) && is_array($headers))
		{
			$this->log($this->app_name . ' Header', $headers);

			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		if($method === 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			if(empty($payload) === false && is_array($payload))
			{
				$this->log($this->app_name . ' Request', $payload);

				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
			}
		}
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);

		$server_output = curl_exec($ch);

		if(curl_errno($ch))
		{
			$error_msg = curl_error($ch);
			if (isset($error_msg)) 
			{
				$response['status']  = 'error';
				$response['message'] = $error_msg;
				return $response;
			}
		}

		$content_type  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		$status_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		$primary_ip    = curl_getinfo($ch, CURLINFO_PRIMARY_IP);

		curl_close ($ch);

		$response['status']        = 'success';
		$response['status_code']   = $status_code;
		$response['content_type']  = $content_type;
		$response['effective_url'] = $effective_url;
		$response['primary_ip']    = $primary_ip;
		$response['response']      = $this->is_json($server_output) ? json_decode($server_output) : $server_output;

		$this->log($this->app_name . ' Response', $response);

		return $response;
	}

	private function log($context = '',$data = '') {
		if($this->create_log && !empty($data))
		{
			$fp = fopen(__DIR__ . '/magiccurl_' . $this->app_version . '.log', 'a');//opens file in append mode  
			fwrite($fp, date('d m Y H:i:s') . ' :: ' . strtoupper($context) . ' :: ');
			if(is_string($data))
			{
				fwrite($fp, $data);
			}
			else if(is_array($data))
			{
				fwrite($fp, json_encode($data));
			}
			fwrite($fp, "\n");
			fclose($fp); 
		}
	}

	private function is_valid_url($url = '')
	{
		if(!$url || !is_string($url) || ! preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $url))
		{
			return false;
		}
		return true;
	}

	private function is_url_exists($url = '')
	{
		$url_headers = @get_headers($url);

		if(!$url_headers || $url_headers[0] == 'HTTP/1.1 404 Not Found')
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	private function is_json($string)
	{
		return !empty($string) && is_string($string) && is_array(json_decode($string, true)) && json_last_error() == 0;
	}
}
?>
