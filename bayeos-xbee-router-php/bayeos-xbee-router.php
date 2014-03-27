#!/usr/bin/php
<?php 
require 'phpXBee.php';
require 'BayEOSGatewayClient.php';

/*
 * Read configuration file and set some defaults
 */
$config=parse_ini_file('/etc/bayeos-xbee-router.ini');
$config['writer_sleep_time']=0;
if(! isset($config['sender'])) $config['sender']=gethostname();
if(! isset($config['names'])){
	$names=array();
	for($i=0;$i<count($config['device']);$i++){
		$names[$i]='XBeeRouter.'.$i; //-> storage path /tmp/XBeeRouter$i ...
	}
} else $names=$config['names'];


class PHPXBeeRouter extends BayEOSGatewayClient{
	private $xbee;
	private $xbee_panid;
	private $read_error_count;
	
	//Init Writer
	protected function initWriter(){
		$this->xbee= new XBee();
		$this->xbee->confDefaults($this->getOption('device'));
		$this->xbee->confBaudRate($this->getOption('baud',38400));
		if($this->xbee->open()===FALSE)
			die("Could not open XBee device");
		$this->xbee_panid=$this->xbee->getPANID();
		$read_error_count=0;
	}
	
	//Generate Data
	protected function readData(){
		if($data=$this->xbee->getFrame()){
			$this->read_error_count=0;
			return $this->_parseRX16($data['frame']);
		}
		$this->read_error_count++;
		if($this->read_error_count>2){
			$this->xbee->close();
			$this->xbee->open();
			$this->read_error_count=0;
		}
		return FALSE;
	}

	//Save Data as Routed Frame RSSI
	protected function saveData($data){
		$this->writer->saveRoutedFrameRSSI($data['myid'],$this->xbee_panid,$data['rssi'],$data['payload']);
	}

	private function _parseRX16($frame){
		 
		$api=substr($frame,2,1);
		if($api!=pack("C",0x81)) return FALSE;
		return array('myid' => array_pop(unpack('n',substr($frame,3,2))),
				'rssi' => array_pop(unpack('C',substr($frame,5,1))),
				'payload' => substr($frame,7,-1));
	
	}
	
}


$my_client = new PHPXBeeRouter($names,$config);
$my_client->run();

?>