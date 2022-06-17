# Info
FusionPBX_Util is a simple PHP class that allows you to perform GET/POST requests to a FusionPBX server that automatically handles Cookie & CSRF tokens for you.

I built this for my own purposes to make it easier to create extensions and destinations from an internal tool.

This is in no way a complete REST API, nor is it ever going to be. If you need a full programmable REST API, there is a commercial REST API available to FusionPBX Purple level Members, more info here: https://www.fusionpbx.com/members

The class includes a couple of helper functions, `domain_list()` to get a list of SIP domains on the server, and `change_domain()` to change the current domain context in the session.


# Usage
This is pretty simple, simply `include()` the file somewhere in your code.

Then you can use it like so:
```php
<?php

// include the class file
include('fusionpbx_util.php');

// set up connection to server
$pbx = new FusionPBX_Util('https://server.example.com', 'admin_username', 'admin_password');

// alternatively you can call authenticate() separaretly, though there's not really any benefit:
$pbx = new FusionPBX_Util('https://server.example.com');
$pbx->authenticate('admin_username', 'admin_password');

// this will fetch a list of domains with their associated UUIDs and return them,
// domains will also be stored in $pbx->domains to iterate through and use later
$pbx->domain_list();

// change domain accepts two arguments:
// the first is the domain name, the second is the domain uuid
// if you omit the second argument, it will try to look up the domain in $this->domains (if present)
$pbx->change_domain('sip.example.com', 'long-uuid-string-goes-here');
```

From here we can build GET and POST requests to scrape data from FusionPBX or update settings, etc.

Here's an example creating a new destination:

```php
// first we must perform a GET request to prime the CSRF token:
$pbx->get('/app/destinations/destination_edit.php');

// then we can create a POST request to create a new destination
$vars =	[
  'destination_type'			=> 'inbound',
  'destination_prefix'			=> '1',
  'destination_number'			=> '5550001111',
  'destination_caller_id_name'  	=> '',
  'destination_caller_id_number'	=> '',
  'destination_context'			=> 'public',
  'destination_action'			=> 'transfer:1000 XML '.$this->domain,
  'destination_alternate_action'	=> ':',
  'user_uuid'				=> '',
  'group_uuid'				=> '',
  'destination_cid_name_prefix'	        => '',
  'destination_record'			=> '',
  'destination_hold_music'		=> '',
  'destination_accountcode'		=> '',
  'domain_uuid'				=> $pbx->domain_uuid,
  'destination_order'			=> '100',
  'destination_enabled'			=> 'true',
  'destination_description'		=> ''
];
$create = $pbx->post('/app/destinations/destination_edit.php', $vars);

// dump the output returned from Fusion:
var_dump($create);
```

Both GET and POST requests will return an array on success, and false on an error.

The return array contains 3 parts, the `status`, `headers`, and `body`.

`status` is the HTTP status code (eg. 200, 404, etc)

`headers` is a string with the complete request headers

`body` is the message body (usually HTML).

I recommend using PHP's `DOMDocument` class to parse the `body` and scrape whatever data you need from it.


# License
Licensed under the MIT License, do with this as you please. It's not very great, but it works.
