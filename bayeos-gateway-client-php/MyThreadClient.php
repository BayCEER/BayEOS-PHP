#!/usr/bin/php
<?php 
/*
 * Run the Sender in the background as thread
 * 
 * To run this script you have to install pthreads manually
 * 
 * 
 */
require_once 'BayEOSGatewayClient.php';
$path='/tmp/bayeos-thread1';
$name="myClientThread";
$url="http://bayconf.bayceer.uni-bayreuth.de/gateway/frame/saveFlat";

//Create a BayEOSWriter
$w = new BayEOSWriter($path);
$w->saveMessage("MyClientThread.php started");

//Create a BayEOSSender and start it as thread
class SenderThread extends Thread {
	public function run() {
		$s = new BayEOSSender($GLOBALS['path'],$GLOBALS['name'],$GLOBALS['url']);
		$s->run();
	}
}
$s_thread=new SenderThread();
$s_thread->start();


//Add your data stuff here...
$count=0;
while(TRUE){
	echo "adding frame\n";
	$w->save(array($count++,300,1.0));
	sleep(5);
}

?>