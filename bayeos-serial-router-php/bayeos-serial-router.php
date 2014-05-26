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
	
	//Init Writer
	protected function initWriter(){
		$this->baySerial= new baySerial();
		$this->baySerial->confDefaults($this->getOption('device'));
		$this->baySerial->confBaudRate($this->getOption('baud',38400));
		if($this->baySerial->open()===FALSE)
			die("Could not open baySerial device");
	}
	
	//Generate Data
	protected function readData(){
		if($data=$this->baySerial->getFrame()){
			$this->read_error_count=0;
			return $data['frame'];
		}
		$this->read_error_count++;
		if($this->read_error_count>2){
			$this->baySerial->close();
			$this->baySerial->open();
			$this->read_error_count=0;
		}
		return FALSE;
	}

	//Save Data as Routed Frame RSSI
	protected function saveData($data){
		//echo "saveData: ".array_pop(unpack('H*',$data))."\n";
		$this->writer->saveFrame($data);
	}

	
}


$my_client = new PHPSerialRouter($names,$config);
$my_client->run();

?>