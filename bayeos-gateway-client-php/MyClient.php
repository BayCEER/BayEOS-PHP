#!/usr/bin/php
<?php 
$options=array('bayeosgateway_url'=>
		'http://bayconf.bayceer.uni-bayreuth.de/gateway/frame/saveFlat',
		'bayeosgateway_pw'=>'xbee',
		'bayeosgateway_user'=>'admin');
$names=array('PHP-TestDevice1','PHP-TestDevice2');

require_once 'BayEOSGatewayClient.php';
/*
 * extend the BayEOSGatewayClient-Class with a own 
 * implementation of readData($name)
 */
class PHPTestDevice extends BayEOSGatewayClient{
	//Generate Data:
	protected function readData(){
		if($this->names[$this->i]=='PHP-TestDevice1')
			return FALSE;
		else 
			return array(2,1.0,rand(-1,1));
				
	}
}

//Create a Instance
$client = new PHPTestDevice($names,$options);

//Run the client
$client->run();

?>