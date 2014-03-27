#!/usr/bin/php
<?php 
/*
 * Command line script to read out Eurotherm 2704 via ModbusTCP
 * 
 * 
 */

require_once 'BayEOSGatewayClient.php';

/*
 * Read configuration file and set some defaults
 */
$config=parse_ini_file('/etc/bayeos-fifo.ini');
if(! isset($config['names'])){
	$names=array();
	for($i=0;$i<count($config['script']);$i++){
		$names[$i]='Fifo.'.$i; //-> storage path /tmp/qliRouter$i ...
	}
} else $names=$config['names'];
$config['writer_sleep_time']=0;

//Extend BayEOSGatewayClient Class
class BayEOSFifo extends BayEOSGatewayClient {
	private $data_fifo;
	private $error_fifo;
	private $data;
	private $error;
	private $fp_data;
	private $fp_error;
	private $pipes;
	private $delim;
	private $dec;
	private $datetime_format;
	private $tz;
	/*
	 * constructor
	*/
	function __construct($names,$options=array()){
		$defaults=array('data_type'=>0x41,
						'delim'=>' ',
						'dec'=>'.',
						'tz'=>date_default_timezone_get());
		while(list($key,$value)=each($defaults)){
			if(! isset($options[$key])){
				echo "Option '$key' not set using default: ".(is_array($value)?implode(', ',$value):$value)."\n";
				$options[$key]=$value;
			}
		}
		parent::__construct($names,$options);
	}
	
	protected function readData(){
		//echo "readData called\n";
		$timeout=$this->getOption('timeout',120);
		$name=$this->names[$this->i];
		while($timeout>0){
			while(($c=fgetc($this->fp_data))!==FALSE){
				$this->data.=$c;
			}
			while(($c=fgetc($this->fp_error))!==FALSE){
				$this->error.=$c;
			}

			if($pos=strpos($this->error,"\n")){
				$line=trim(substr($this->error,0,$pos));
				fwrite(STDERR,date('Y-m-d H:i:s')." $name: $line\n");
				$this->error=substr($this->error,$pos+1);
			}
			
			
			if($pos=strpos($this->data,"\n"))
				break;
			usleep(10000);
			$timeout-=.01;
		}
		if($timeout<=0) return FALSE;
		$line=trim(substr($this->data,0,$pos));
		$this->data=substr($this->data,$pos+1);
		return $this->parseData($line);
		
	}

	protected function parseData($line){
		$ts='';
		if($this->datetime_format){
			$pos=strpos($line,$this->delim);
			$ts=substr($line,0,$pos);
		//	echo $this->delim"\n";
			$line=substr($line,$pos+1);
			$ts_obj=DateTime::createFromFormat($this->datetime_format,$ts,new DateTimeZone($this->tz));
			if(! $ts_obj){
				fwrite(STDERR, date('Y-m-d H:i:s')." Timestamp parse error: $ts with ".$this->datetime_format."\n");
				return FALSE;
			}
			$ts=floatval($ts_obj->format("U.u"));
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
	
	
	protected function initWriter(){
		$name=$this->names[$this->i];

		$fifo=$this->options['tmp_dir'].'/'.str_replace(array('/','\\','"','\''),'_',$name);
		
		$this->data_fifo=$fifo.'.data.fifo';
		posix_mkfifo($this->data_fifo,$this->getOption('data_fifo_mode',0600));
		if(! is_writable($this->data_fifo))
			die("Failed to create $fifo.data_fifo\n");
		$this->error_fifo=$fifo.'.error.fifo';
		posix_mkfifo($this->error_fifo,$this->getOption('error_fifo_mode',0600));
		if(! is_writable($this->error_fifo))
			die("Failed to create $fifo.error_fifo\n");

		$script=$this->getOption('script');
		if(! is_executable($script))
			die("$script is not executable\n");
/*		
		$descriptorspec = array(
//				0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
				1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
				2 => array("pipe", "w") // stderr is a pipe that the child will write to
		);
		$process = proc_open($script." &", $descriptorspec, $this->pipes);

				*/
		//Start the script
		$pid=pcntl_fork();
		if ($pid == -1) {
			die('Could not fork reader process!');
		} else if ($pid) {
			// We are the parent
			$this->fp_data=fopen($this->data_fifo,'r');
			$this->fp_error=fopen($this->error_fifo,'r');
			stream_set_blocking($this->fp_data, false);
			stream_set_blocking($this->fp_error, false);
			echo date('Y-m-d H:i:s')." Started script $script for $name with pid $pid\n";
			$this->delim=$this->getOption('delim');
			$this->dec=$this->getOption('dec');
			$this->tz=$this->getOption('tz');
			$this->datetime_format=$this->getOption('datetime_format');
				
			
		} else {
			// We are child:
			exec("$script >".$this->data_fifo." 2>".$this->error_fifo);
			exit();
		}
		//open Fifos for read

	}

}




$my_client = new BayEOSFifo($names,$config);
$my_client->run();






?>