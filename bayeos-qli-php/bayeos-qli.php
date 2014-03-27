#!/usr/bin/php
<?php 
require 'QLI.php';
require 'BayEOSGatewayClient.php';

/*
 * Read configuration file and set some defaults
 */
$config=parse_ini_file('/etc/bayeos-qli.ini');
$config['writer_sleep_time']=0;
if(! isset($config['names'])){
	$names=array();
	for($i=0;$i<count($config['device']);$i++){
		$names[$i]='QLI.'.$i; //-> storage path /tmp/qliRouter$i ...
	}
} else $names=$config['names'];


class PHPQLI extends BayEOSGatewayClient{
	private $qli;
	private $read_error_count;
	
	function __construct($names,$options){
		$defaults=array('data_type'=>0x41,
			'datetime_format'=>'d.m.Y H:M:S',
			'indexmap'=>FALSE,
			'baud'=>9600,
			'tz'=>date_default_timezone_get());
		while(list($key,$value)=each($defaults)){
			if(! isset($options[$key])){
				echo "Option '$key' not set using default: ".(is_array($value)?implode(', ',$value):$value)."\n";
				$options[$key]=$value;
			}
		}
		parent::__construct($names,$options);
	}
	
	//Init Writer
	protected function initWriter(){
		$this->qli= new QLI($this->getOption('tz'),$this->getOption('datetime_format'),$this->getOption('indexmap'));
		$this->qli->confDefaults($this->getOption('device'));
		$this->qli->confBaudRate($this->getOption('baud'));
		if($this->qli->open()===FALSE)
			die("Could not open qli device");
	}
	
	//Generate Data
	protected function readData(){
		if($data=$this->qli->getFrame()){
			$this->read_error_count=0;
			return $data;
		}
		$this->read_error_count++;
		if($this->read_error_count>2){
			$this->qli->close();
			$this->qli->open();
			$this->read_error_count=0;
		}
		return FALSE;
	}

	//Save Data as DataFrame with timestamp
	protected function saveData($data){
		//print_r($data);
		$this->writer->saveDataFrame($data['values'],$this->getOption('data_type'),0,$data['ts']);
	}

	
}


$my_client = new PHPQLI($names,$config);
$my_client->run();

?>