<?php 
/*
 * BaySerial Reader
 * 
 *  may read e.g. Arduino sending via BaySerial-USB
 */

require 'bayeosSerial.php';
//require 'BayEOSGatewayClient.php';
require '../bayeos-gateway-client-php/BayEOSGatewayClient.php';

$BaySerial= new BaySerial();
$BaySerial->confDefaults();
$BaySerial->confBaudRate(38400);
$BaySerial->deviceSet("/dev/ttyUSB0");
$BaySerial->open();
while(1){
	$frame=$BaySerial->getFrame();
	if($frame!==FALSE){
		echo "got frame: ".array_pop(unpack('H*',$frame['frame']))."\n";
		print_r(BayEOS::parseFrame($frame['frame']));
	}
	usleep(50000);
}


?>