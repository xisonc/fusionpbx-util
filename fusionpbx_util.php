<?php

class FusionPBX_Util
{
	// server & auth
	private $server;
	private $username;
	private $password;
	public $authed = false;

	// session data
	private $csrf = [];
	private $cookies = [];

	// pbx data
	public $domains = [];
	public $domain;
	public $domain_uuid;

	// misc
	public $useragent = 'Mozilla/5.0 (X11; Linux x86_64; rv:101.0) Gecko/20100101 Firefox/101.0';

	// constructor
	public function __construct( $server, $username=null, $password=null )
	{
		// set server var
		$this->server = rtrim($server, '/');

		// set auth vars
		$this->username = ($username ?? null);
		$this->password = ($password ?? null);

		// auto login if username and password are set
		if( $this->username && $this->password )
			$this->authenticate();
	}

	public function authenticate( $username=null, $password=null )
	{
		// send GET request to capture CSRF token and cookies
		$fetch = $this->GET('/login.php');

		// set auth vars
		$this->username = ($username ?? null);
		$this->password = ($password ?? null);

		// vars
		$vars = [];
		$vars['username'] = $this->username;
		$vars['password'] = $this->password;

		// send POST request to login
		$fetch = $this->POST('/core/dashboard/', $vars);

		// check login status by checking document body for id='domain_list'
		if( strstr($fetch['body'], 'id=\'domains_list\'') )
			$this->authed = true;

		// return true or false
		return $this->authed;
	}


	// helper util to get list of domains and domain uuid's from fusionpbx
	public function domain_list()
	{
		// check if authenticated
		if( !$this->authed )
			return false;

		// fetch dashboard page
		$fetch = $this->GET('/core/dashboard/index.php');

		// get links from page body
		// format: <a href='/core/domains/domains.php?domain_uuid={{uuid}}&domain_change=true'>{{domain}}</a>
		preg_match_all('!href=\'\/core\/domains\/domains.php\?domain_uuid\=([0-9abcdef-]+?)\&(.*?)\>(.*?)\<\/a\>!mi', $fetch['body'], $matches);

		// combine matches[3] as keys and matches[1] as values into a single array
		$this->domains = array_combine($matches[3], $matches[1]);

		// return domains
		return $this->domains;
	}


	// helper util to change domain in session
	public function change_domain( $domain, $domain_uuid=null )
	{
		// check if authenticated
		if( !$this->authed )
			return false;

		// check for domain in $this->domains
		if( !$domain_uuid && array_key_exists($domain, $this->domains) )
		{
			$domain_uuid = $this->domains[$domain];
		}

		// vars
		$vars = [];
		$vars['domain_uuid'] = $domain_uuid;
		$vars['domain_change'] = 'true';

		// submit request
		$this->GET('/core/domains/domains.php', $vars);

		// update current domain and domain_uuid
		$this->domain = $domain;
		$this->domain_uuid = $domain_uuid;
	}


	// GET request
	public function GET( $action, $vars=[] )
	{
		return $this->Request($action, $vars, 'GET');
	}

	// POST request
	public function POST( $action, $vars=[] )
	{
		return $this->Request($action, $vars, 'POST');
	}

	// fetch request
	private function Request( $action='', $vars=[], $method='POST' )
	{
		// build URL
		$url = $this->server . $action;

		// append vars to GET request URL
		if( $method == 'GET' && sizeof($vars) > 0 )
		{
			$url.= '?'.http_build_query($vars);
		}

		// set up curl object
		$curl =	curl_init();
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_TIMEOUT, 300);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($curl, CURLOPT_VERBOSE, true);
				curl_setopt($curl, CURLOPT_HEADER, true);
				curl_setopt($curl, CURLOPT_USERAGENT, $this->useragent);

		// inject cookies into request if available
		if( is_array($this->cookies) && sizeof($this->cookies) > 0 )
		{
			foreach( $this->cookies as $key=>$val )
			{
				curl_setopt($curl, CURLOPT_COOKIE, $key.'='.$val);
			}
		}

		// is POST
		if( $method == 'POST' )
		{
			// inject csrf into POST vars
			if( $this->csrf )
				$vars["{$this->csrf['v']}"] = $this->csrf['v'];

			// enable post
			curl_setopt($curl, CURLOPT_POST, true);

			// add vars
			curl_setopt($curl, CURLOPT_POSTFIELDS, $vars);
		}

		// exec curl request and get result
		$fetch	= curl_exec($curl);

		// get errors
		$errNo	= curl_errno($curl);
		$errMsg	= curl_error($curl);
		$status	= curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// separate headers from body
		$hlength	= curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$headers	= substr($fetch, 0, $hlength);
		$body		= substr($fetch, $hlength);

		// close connection
		curl_close($curl);

		// throw error
		if( $errNo )
		{
			error_log('CURL Error ('.$errNo.'): '.$errMsg);
			return false;
		}

		// capture cookies and store in $this->cookies
		if( preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches) )
		{
			foreach( $matches[1] as $item )
			{
				$split = explode('=', $item, 2);
				$this->cookies["{$split[0]}"] = $split[1];
			}
		}

		// capture csrf token and store in $this->csrf
		if( preg_match('!\<input type=\'hidden\' name=\'([0-9abcdef]{64})\' value=\'([0-9abcdef]{64})\'\>!i', $body, $matches) )
		{
			$this->csrf = ['k' => $matches[1], 'v' => $matches[2]];
		}

		// return output
		return	[
					'status'	=> $status,
					'headers'	=> $headers,
					'body'		=> $body
				];
	}

}
