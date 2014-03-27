<?php 
require 'BayEOSSerialPHP.php';

/**
 * QLI represents an QLI connection
 *
 */
define("QLI_BEGIN",pack("C",0x2));
define("QLI_END",pack("C",0x3));
define("XON",pack("C",0x11));
define("XOFF",pack("C",0x13));
class QLI extends phpSerial {
	private $stack;
	private $datetime_format;
	private $tz;
	private $indexmap;
	/**
	 * Constructor. Parent is phpSerial
	 *
	 * @return QLI
	 */
	function QLI($tz,$datetime_format,$indexmap=FALSE) {
		$this->stack=array();
		$this->tz=$tz;
		$this->datetime_format=$datetime_format;
		$this->indexmap=$indexmap;
		parent::phpSerial();
	}

	/**
	 * Sets up typical Connection 9600 8-N-1
	 *
	 * @param String $device is the path to the QLI, defaults to /dev/ttyUSB0
	 * @return void
	 */
	public function confDefaults($device = '/dev/ttyUSB0') {
		$this -> deviceSet($device);
		$this -> confBaudRate(9600);
		$this -> confParity('none');
		$this -> confCharacterLength(8);
		$this -> confStopBits(1);
		$this -> confFlowControl('none');
	}

	/**
	 * Opens this QLI connection.
	 *
	 * Note that you can send raw serial with sendMessage from phpSerial
	 * @return void
	 * @param $waitForOpened int amount to sleep after openeing in seconds. Defaults to 0.1
	 */
	public function open($waitForOpened=0.1) {
		$this -> deviceOpen();
		usleep((int) ($waitForOpened * 1000000));
	}

	/**
	 * Closes this QLI connection
	 * @return void
	 */
	public function close() {
		$this -> deviceClose();
	}


	public function read($count = 0){
		$data = $this -> readPort($count);
		//echo 'READ: '.array_pop(unpack('H*',$data))."\n";
		if(! $data) return count($this->stack);
		if(! isset($this->stack[0])){
			$delim_pos=strpos($data,QLI_BEGIN);
			if($delim_pos===FALSE) return 0;
			else $data=substr($data,$delim_pos+1);
		}
		 
		$data=explode(QLI_BEGIN,$data);
		$offset=count($this->stack)-1;
		if($offset==-1) $offset=0;
		for($i=0;$i<count($data);$i++){
			$index=$i+$offset;
			if(! isset($this->stack[$index]['frame']))
				$this->stack[$index]['frame']='';
			$this->stack[$index]['frame'].=$data[$i];
			//echo "Stack[$index]: ".array_pop(unpack('H*',$this->stack[$index]['frame']))."\n";
			$this->stack[$index]['ok']=$this->_parseFrame($index);
			if(! $this->stack[$index]['ok'] && ($i+1)<count($data)){
				//Invalid Frame!
				echo "Invalid Frame\n";
				unset($this->stack[$index]);
				$offset--;
			}
		}
		 
		$anz=count($this->stack);
		//print_r($this->stack);
		//echo "Stackcount: $anz\n";
		if(! $this->stack[$anz-1]['ok']) $anz--;
		//echo "read: $anz\n";
		return $anz;
	}

	private function _parseFrame($index){
		$frame=$this->stack[$index]['frame'];
		if(strpos($frame,QLI_END)===FALSE) return FALSE;
		$pos=strpos($frame," ");
		$length = substr($frame, 0, $pos);
		if(strlen($frame)>$length) return FALSE;
		$frame=substr($frame,$pos+1);
		$pos=strpos($frame,"\r");
		$ts=substr($frame, 0, $pos);
		$ts_obj=DateTime::createFromFormat($this->datetime_format,$ts,new DateTimeZone($this->tz));
		if(! $ts_obj) return FALSE;
		$this->stack[$index]['ts']=floatval($ts_obj->format("U"));
		
		$frame=substr($frame,$pos+1);
		$pos=strpos($frame,"\n");
		$pos2=strpos($frame,QLI_END);
		$frame=substr($frame,($pos+1),($pos2-$pos-1));
		$data=explode("\r\n",$frame);
		
		$this->stack[$index]['values']=array();
		for($i=0;$i<count($data);$i++){
			if(strpos($data[$i],"=")===FALSE) continue;
			$tmp=explode("=",$data[$i]);
			//echo "parseFrame: '$data[$i]'\n";
			if(is_numeric($tmp[1])){
				if(is_array($this->indexmap)){
					if(isset($this->indexmap[$tmp[0]]))
						$this->stack[$index]['values'][$this->indexmap[$tmp[0]]]=$tmp[1];
				}
				else 
					$this->stack[$index]['values'][$i]=$tmp[1];
			}
		}
		//print_r($this->stack[$index]);
		return TRUE;	
	}


/*
 * Will return an array with the following keys:
 * ts -> seconds since 1.1.1970
 * value -> array with values
 * frame -> unparsed data
 * 
 */


	public function getFrame($timeout=120){
		while($this->read()==0 && $timeout>0){
			$timeout-=0.01;
			usleep(10000);
		}
		if($timeout<0) return FALSE;
		return array_shift($this->stack);
	}


}




?>