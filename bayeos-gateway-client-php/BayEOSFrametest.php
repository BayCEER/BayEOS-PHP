#!/usr/bin/php
<?php 
require_once 'BayEOSGatewayClient.php';
$df=BayEOS::createDataFrame(array(1,2,3.2));
$df_checksum=BayEOS::createChecksumFrame($df);
$df_checksum_origin=BayEOS::createOriginFrame('TestOrigin',$df_checksum);
$df_origin=BayEOS::createOriginFrame('TestOrigin',$df);
$ms=BayEOS::createMessage('Test-Message');
$ems=BayEOS::createMessage('Test-Error-Message',1);
$ms_xbee=BayEOS::createRoutedFrame(20,3003,$ms);
$ems_xbee_rssi=BayEOS::createRoutedFrameRSSI(20,3003,87,$ems);
$df_origin_checksum=BayEOS::createChecksumFrame($df_origin);

print_r(BayEOS::parseFrame($df));
print_r(BayEOS::parseFrame($df_checksum));
print_r(BayEOS::parseFrame($df_checksum_origin));
print_r(BayEOS::parseFrame($df_origin_checksum));
print_r(BayEOS::parseFrame($ms));
print_r(BayEOS::parseFrame($ems));
print_r(BayEOS::parseFrame($ms_xbee));
print_r(BayEOS::parseFrame($ems_xbee_rssi));


?>