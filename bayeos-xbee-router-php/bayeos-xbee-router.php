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
	private $device;
	
	//Init Writer
	protected function initWriter(){
		$this->xbee= new XBee();
		$this->openDevice();
		$this->xbee_panid=$this->xbee->getPANID();
		$read_error_count=0;
	}
	
	//Open Device
	//Will create a lockfile in /var/lock/
	private function openDevice(){
		$this->device=$this->getOption('device');
		if($this->device=='auto'){
			$this->device=$this->findDevice();
		}
		if(! $this->device)
			die("No device available");
		$this->xbee->confDefaults($this->device);
		$this->xbee->confBaudRate($this->getOption('baud',38400));
		if($this->xbee->open()===FALSE)
			die("Could not open baySerial device");
		$fp=fopen(str_replace('/dev/','/var/lock/',$this->device),'w');
		fwrite($fp,''.getmypid());
		fclose($fp);
		echo date('Y-m-d H:i:s').': '.$this->name.': '.$this->device.' opened'."\n";
	}
	
	//Find Device
	private function findDevice(){
		$devices=glob($this->getOption('device_search','/dev/ttyUSB*'));
		for($i=0;$i<count($devices);$i++){
			$lockfile=str_replace('/dev/','/var/lock/',$devices[$i]);
			if(! file_exists($lockfile)) return $devices[$i];
			//Lockfile exists
			$fp=fopen($lockfile,'r');
			$pid=fgets($fp,10);
			fclose($fp);
			$lines_out = array();
			exec('ps '.(int)$pid, $lines_out);
			if(count($lines_out) < 2) {
				// Process is not running
				fwrite(STDERR,date('Y-m-d H:i:s').': '.$this->name.': Old lockfile '.$lockfile.' detected. Deleting '."\n");
				unlink($lockfile);
				return $devices[$i];
			}
	
		}
		return false;	
	}
	
	//close device and remove lock file
	private function closeDevice(){
		$this->xbee->close();
		unlink(str_replace('/dev/','/var/lock/',$this->device));
	}
	
	//Generate Data
	protected function readData(){
		if($data=$this->xbee->getFrame($this->getOption('read_timeout',120))){
			$this->read_error_count=0;
			return $this->_parseRX16($data['frame']);
		}
		$this->read_error_count++;
		if($this->read_error_count>$this->getOption('maxerror_before_reopen',2)){
			$this->closeDevice();
			$this->openDevice();
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