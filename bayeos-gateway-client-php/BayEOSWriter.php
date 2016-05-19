<?php 
/*
 * Writer only
 */
require_once 'BayEOSGatewayClient.php';

$w = new BayEOSWriter('/tmp/bayeos-device1');
$count=0;
while(TRUE){
	echo "adding frame\n";
	$w->saveDataFrame(array(4,5)); //use simple sequential numeric channel numbers
/*
	$w->saveDataFrame(array(2=>4,4=>5),0x41); //use numeric channel numbers
	$w->saveDataFrame(array('C1'=>4,'C2'=>5),0x61); //use labelled channel type
	$w->saveMessage('test3'); //save a message
	
    $w->saveRoutedFrameRSSI(2,0,80,BayEOS::createDataFrame(array($count,1)));
	$w->saveFrame(BayEOS::createRoutedFrame(2,0,BayEOS::createDelayedFrame(50000,BayEOS::createDataFrame(array($count,1)))));
	$w->saveFrame(BayEOS::createRoutedFrame(2,0,BayEOS::createDataFrame(array($count,1))));
*/	
	sleep(5);
}

?>