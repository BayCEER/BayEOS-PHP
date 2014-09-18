#!/usr/bin/php
<?php 
require 'BayEOSGatewayClient.php';

/*
 * Read configuration file and set some defaults
 */
$config=parse_ini_file('/etc/bayeos-socket.ini');
$config['writer_sleep_time']=0;
if(! isset($config['names'])){
	$names=array();
	for($i=0;$i<count($config['socket']);$i++){
		$names[$i]='Socket.'.$i; //-> storage path /tmp/Serial.$i ...
	}
} else $names=$config['names'];



class PHPSocketRouter extends BayEOSGatewayClient{
	private $socket;
	private $delim;
	private $dec;
	private $datetime_format;
	private $tz;
	private $fp;
	
	//Init Writer
	protected function initWriter(){
		$this->socket=$this->getOption('socket');
		$this->delim=$this->getOption('delim');
		$this->dec=$this->getOption('dec');
		$this->tz=$this->getOption('tz');
		$this->datetime_format=$this->getOption('datetime_format');
	}
	
	private function connect(){
		if($this->fp) return;
		while(! $this->fp = stream_socket_client($this->socket,$error,$errstr,30)){
			sleep(10);
		}
		stream_set_blocking($this->fp, 1);
		
	}
	
	//Generate Data
	protected function readData(){
		$this->connect();
		$line=stream_get_line($this->fp,1024,"\n");
		$line=trim($line);
		if(! $line) {
			fclose($this->fp);
			$this->fp=0;
			sleep(1);
			return FALSE;
		}
		//echo "readData: $line\n";
		return $this->parseData($line);
	}

	private function parseData($line){
		$ts='';
		if($this->datetime_format){
			$pos=strpos($line,$this->delim);
			$ts=substr($line,0,$pos);
			//	echo $this->delim"\n";
			$line=substr($line,$pos+1);
			$ts=floatval(DateTime::createFromFormat($this->datetime_format,$ts,new DateTimeZone($this->tz))->format("U.u"));
		}
	
		if($this->dec!='.') $line=str_replace($this->dec,'.',$line);
		$data=explode($this->delim,trim($line));
		for($i=0;$i<count($data);$i++){
			if(! is_numeric($data[$i])) unset($data[$i]);
		}
		return array('values'=>$data,'ts'=>$ts);
	}
	
	protected function saveData($data){
		//save data with timestamp
		$this->writer->saveDataFrame($data['values'],$this->getOption('data_type'),0,$data['ts']);
	}
	
	
}


$my_client = new PHPSocketRouter($names,$config,
		array('data_type'=>0x41,
						'delim'=>' ',
						'dec'=>'.',
						'tz'=>date_default_timezone_get());
$my_client->run();

?>