#!/usr/bin/php
<?php 
/*
 * Command line script to read STDOUT and STDERR of
 * an arbitrary shell script
 * 
 * tries to parse STDOUT of shell script into timestamp (optional), array of values
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
	private $pid_script;
	private $origin;
	
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
		$origin='';
		if($this->origin){
			$pos=strpos($line,$this->delim);
			$origin=substr($line,0,$pos);
			$line=substr($line,$pos+1);
		}
		if($this->datetime_format){
			$pos=strpos($line,$this->delim);
			$ts=substr($line,0,$pos);
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
		return array('values'=>$data,'ts'=>$ts,'origin'=>$origin);
	}
	
	protected function saveData($data){
		//save data with timestamp
		if($data['origin'])
			$this->writer->saveOriginFrame($data['origin'],
				BayEOS::createDataFrame($data['values'],$this->getOption('data_type'),0),
				$data['ts']);
		else 
			$this->writer->saveDataFrame($data['values'],$this->getOption('data_type'),0,$data['ts']);
	}
	
	private function termChilds($pid){
		exec("pgrep -P $pid",$res);
		if($res[0]) $this->termChilds($res[0]);
		posix_kill($pid,SIGTERM);
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
		$tmp=explode(" ",$script);
		if(! (is_executable($script) || is_executable($tmp[0])))
			die("$script is not executable\n");

		//Start the script
		$pid=pcntl_fork();
		if ($pid == -1) {
			die('Could not fork reader process!');
		} else if ($pid) {
			// We are the parent
			//Signalhanlder to stop the script on SIGTERM
			$this->pid_script=$pid;
			declare(ticks = 1);
			pcntl_signal(SIGTERM, function($signo) {
				switch ($signo) {
					case SIGTERM:
						// Aufgaben zum Beenden bearbeiten
						echo date('Y-m-d H:i:s')." Stopping script ".$this->getOption('script')." for ".$this->name[$this->i]." with pid ".$this->pid_script."\n";
						$this->termChilds($this->pid_script);
						exit();
					break;
				}
			
			});
				
			
			$this->fp_data=fopen($this->data_fifo,'r');
			$this->fp_error=fopen($this->error_fifo,'r');
			stream_set_blocking($this->fp_data, false);
			stream_set_blocking($this->fp_error, false);
			echo date('Y-m-d H:i:s')." Started script $script for $name with pid $pid\n";
			$this->delim=$this->getOption('delim');
			$this->dec=$this->getOption('dec');
			$this->tz=$this->getOption('tz');
			$this->datetime_format=$this->getOption('datetime_format');
			$this->origin=$this->getOption('origin');
				
			
		} else {
			// We are child:
			exec("$script >".$this->data_fifo." 2>".$this->error_fifo." </dev/null");
			exit();
		}

	}

}




$my_client = new BayEOSFifo($names,$config,
		array('data_type'=>0x41,
		'delim'=>' ',
		'dec'=>'.',
		'origin'=>FALSE,
		'tz'=>date_default_timezone_get()));
$my_client->run();






?>