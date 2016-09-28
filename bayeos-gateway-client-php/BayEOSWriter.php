#!/usr/bin/php
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
// use of more complicated save methods
	$w->saveDataFrame(array(2=>4,4=>5),0x41); //use numeric channel numbers
	$w->saveDataFrame(array('C1'=>4,'C2'=>5),0x61); //use labelled channel type
	$w->saveMessage('test3'); //save a message
	$w->saveRoutedFrameRSSI(2,0,80,BayEOS::createDataFrame(array($count,1)));
*/

/*
//	use of generic saveFrame method and 
//  frame creation using BayEOS-class
//	see BayEOSFrametest.php for more examples
	
	$df=BayEOS::createDataFrame(array(1,2,3.2));
	$df_checksum=BayEOS::createChecksumFrame($df);
	$w->saveFrame($df_checksum);
*/


	sleep(5);
}

?>