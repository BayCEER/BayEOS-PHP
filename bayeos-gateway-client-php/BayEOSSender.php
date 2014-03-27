<?php 
/*
 * Sender only
 */
require_once 'BayEOSGatewayClient.php';

$s = new BayEOSSender('/tmp/bayeos-device1',
		"PHP-Test-Device",
		"http://bayconf.bayceer.uni-bayreuth.de/gateway/frame/saveFlat",
		"xbee",
		"admin");

while(TRUE){
	$c = $s->send();
	if($c){
		echo "Successfully sent $c post requests\n";
	}
	sleep(5);
}

?>