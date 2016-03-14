# Getting started with XRDL #
## Introduction ##
This page explains how to get started using XRDL for automatic XML-RPC client generation.
In the following you are assumed to
  * already have an XML-RPC web service written in php
  * want to expose only class methods and named class types via XML-RPC

## Generating the XRDL service definition ##
  * Clone a copy of the XRDL hg repository
  * Switch to the src/php/ directory
  * Have a look at the README.txt file
  * Edit exports.php to enumerate the classes and methods that you wish to export
  * Execute a command line similar to this
```
php -d include_path=~/path/to/exported/classes/ ./xrdl-generator.php --url "http://rpc.example.com" --ns "com.example.rpc" --name "Sample XML-RPC service"
```
  * If all goes well, this will result in output similar to this:
```
<?xml version="1.0"?>
<service name="Sample XML-RPC service" ns="com.example.rpc" url="rpc.example.com">
 <types>
 </types>
 <methods>
  <method name="logStatisticsEvent" result="boolean">
   <param type="string">arg0</param>
   <param type="string">arg1</param>
   <param type="string">arg2</param>
   <param type="string">arg3</param>
  </method>
 </methods>
</service>
```
  * Save the output to a file called service.xml. This is your XRDL service definition.

## Generating a php client ##
In the following, you are assumed to have an XRDL service definition in a filed called service.xml.
Please complete the following steps to generate a php client from your service definition.
  * Execute a command line similar to this:
```
php ./xml-rpc-client-generator.php --file /path/to/service.xml
```
  * This should result in output similar to this
```
<?php

require_once "xmlrpc.inc";
require_once "xmlrpcs.inc";
require_once "xmlrpc_wrappers.inc";

//namespace com\example\rpc;

// Remotely defined types


class Sample_XML_RPC_service_client {
	private $client;
	private $url;

	public function __construct() {
		$this->url = "rpc.example.com";
		$this->client = new xmlrpc_client("rpc.example.com");
	}

	// Remote methods
	public function logStatisticsEvent(string $arg0, string $arg1, string $arg2, string $arg3) {
                $msg = new xmlrpcmsg("setInfoObjectHeadline",
                        array(
				new xmlrpcval($arg0, "string"),
				new xmlrpcval($arg1, "string"),
				new xmlrpcval($arg2, "string"),
				new xmlrpcval($arg3, "string"),

                        )
                );
                $response = $this->client->send($msg, 15);
                if ($response->faultCode()) {
                        return false;
                } else {
                        $responseVal = $response->value();
			$cookies = $response->cookies();
			foreach ($cookies as $key => $value) {
				$this->client->setcookie($key, $value);
			}
                        return php_xmlrpc_decode();
                }
	
	}
}

?>
```
  * Save the output to an appropriately named .php file.
  * Download the phpxmlrpc library from http://phpxmlrpc.sourceforge.net/ and place the source files in your include\_path.
  * This is your client. If you instantiate it, you should immediately be able to communicate with your XML-RPC based web service.