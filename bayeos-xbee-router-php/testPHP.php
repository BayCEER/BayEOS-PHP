<?php 
require 'phpXBee.php';

$xbee= new XBee();
$xbee->confDefaults();
$xbee->confBaudRate(38400);
$xbee->deviceSet("/dev/ttyUSB0");
$xbee->open();
//$xbee_panid=$xbee->getPANID();

$package="7E 00 04 08 01 4D 59 50";
//$package="7E 00 04 08 01 49 44 69";
$package=explode(" ",$package);
$frame='';
for($i=0;$i<count($package);$i++){
	$frame.=pack('C',hexdec($package[$i]));
}

//echo "Send:".array_pop(unpack('H*',$frame))."\n";
$xbee->sendMessage($frame);

echo $xbee->getPANID()."\n";

?>