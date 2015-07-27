#!/usr/bin/php
<?php 
/*
 * This is an example for BayEOSSimpleClient
 * 
 * BayEOSSimpleClient uses fork to start a Sender process
 * 
 */


require_once 'BayEOSGatewayClient.php';

//Configuration
$path='/tmp/bayeos-simpleClient2';
$name="mySimpleClient";
$url="http://bayconf.bayceer.uni-bayreuth.de/gateway/frame/saveFlat";
$options=array('backup_path'=>'/var/bayeos/mySimpleClient2')

//Create a BayEOSSimpleClient
//Note: This already forks the sender process
$c = new BayEOSSimpleClient($path,$name,$url,$options);

//Setup signal handling for SIGTERM
declare(ticks = 1);
pcntl_signal(SIGTERM,  function($signo) {
	$GLOBALS['c']->stop();
});

//Do your data stuff here...
$count=0;
while(TRUE){
	echo "adding frame\n";
	$c->save(array($count++,300,1.0));
	sleep(5);
}
?>