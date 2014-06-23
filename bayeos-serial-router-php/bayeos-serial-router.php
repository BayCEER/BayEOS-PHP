#!/usr/bin/php
<?php 
require 'bayeosSerial.php';
require 'BayEOSGatewayClient.php';

/*
 * Read configuration file and set some defaults
 */
$config=parse_ini_file('/etc/bayeos-serial-router.ini');
$config['writer_sleep_time']=0;
if(! isset($config['names'])){
	$names=array();
	for($i=0;$i<count($config['device']);$i++){
		$names[$i]='Serial.'.$i; //-> storage path /tmp/Serial.$i ...
	}
} else $names=$config['names'];


class PHPSerialRouter extends BayEOSGatewayClient{
	private $baySerial;
	private $read_error_count;
	private $device;
	
	//Init Writer
	protected function initWriter(){
		$this->baySerial= new baySerial();
		//
		$this->openDevice();
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
		$this->baySerial->confDefaults($this->device);
		$this->baySerial->confBaudRate($this->getOption('baud',38400));
		if($this->baySerial->open()===FALSE)
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
		$this->baySerial->close();
		unlink(str_replace('/dev/','/var/lock/',$this->device));
	}
	
	//Generate Data
	protected function readData(){
		if($data=$this->baySerial->getFrame($this->getOption('read_timeout',120))){
			$this->read_error_count=0;
			return $data['frame'];
		}
		$this->read_error_count++;
		if($this->read_error_count>$this->getOption('maxerror_before_reopen',2)){
			$this->closeDevice();
			$this->openDevice();
			$this->read_error_count=0;
		}
		return FALSE;
	}

	//Save Data 
	protected function saveData($data){
		//echo "saveData: ".array_pop(unpack('H*',$data))."\n";
		$this->writer->saveFrame($data);
	}

	
}


$my_client = new PHPSerialRouter($names,$config);
$my_client->run();

?>