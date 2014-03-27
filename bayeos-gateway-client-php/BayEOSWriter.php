<?php 
/*
 * Writer only
 */
require_once 'BayEOSGatewayClient.php';

$w = new BayEOSWriter('/tmp/bayeos-device1');
$count=0;
while(TRUE){
	echo "adding frame\n";
	$w->saveDataFrame(
			array($count++,
			1,
			1.0));
	sleep(5);
}

?>