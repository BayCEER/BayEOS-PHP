#!/usr/bin/php
<?php 
require 'BayEOSGatewayClient.php';

/*
 * Read configuration file and set some defaults
 */
$config=parse_ini_file('/etc/bayeos-logger-importer.ini');
$names=$config['sender'];
$config['writer_sleep_time']=0;
$ref_date= DateTime::createFromFormat('Y-m-d H:i:s P','2000-01-01 00:00:00 +00:00')->format('U');


class PHPLoggerImporter extends BayEOSGatewayClient{
	private $queue;
	private $queue_fp;
	private $queue_file;
	
	//Init Writer
	protected function initWriter(){
		$this->queue=$this->getOption('tmp_dir').'/'.str_replace(array('/','\\','"','\''),'_',$this->name).'_queue';
		if(! is_dir($this->queue)){
			if(! mkdir($this->queue,0777,TRUE)){
				die("could not create ".$this->queue);
			}
		}
		exec('chmod 1777 '.$this->queue);
	}

	
	private function openQueueFile(){
		if($this->queue_fp) return 1;
		do {
			sleep(2);
			$files=glob($this->queue.'/*');
		} while(count($files)==0);
		$this->queue_file=$files[0];
		$this->queue_fp=fopen($files[0],'r');
	}
	
	//Generate Data
	protected function readData(){
		if($this->queue_fp && feof($this->queue_fp)){
			fclose($this->queue_fp);
			$this->queue_fp=0;
			unlink($this->queue_file);	
		}
		$this->openQueueFile();

		$ts=$this->getOption('timeshift')+$GLOBALS['ref_date']+BayEOSType::unpackUINT32(fread($this->queue_fp,4));
		$length=BayEOSType::unpackUINT8(fread($this->queue_fp,1));
		$bayeosframe=fread($this->queue_fp,$length);
		return array('frame'=>$bayeosframe,'ts'=>$ts);
	}

	protected function saveData($data){
		//save the original frame with the ts
		$this->writer->saveFrame($data['frame'],$data['ts']);
	}
	
	
}


$my_client = new PHPLoggerImporter($names,$config,array('timeshift'=>0));
$my_client->run();

?>