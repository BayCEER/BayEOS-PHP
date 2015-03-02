<?php 
/**
 * @mainpage BayEOS PHP Classes
 * 
 * Bugs and Feedback:
 * holzheu@bayceer.uni-bayreuth.de
 * 
 * Source Code is available on 
 * https://github.com/BayCEER/BayEOS-PHP
 * 
 * Binary Packages for Debian Wheezy could be installed by adding
 * 
 * deb	http://www.bayceer.uni-bayreuth.de/edv/debian wheezy/
 * 
 * to /etc/apt/sources.list
 * 
 * @section sec1 Introduction
 * This classes allowes the user to build up own BayEOS-Device implementations in PHP
 * 
 * **********************************************************************************
 * @subsection sec1_1 BayEOSWriter-Class
 * 
 * BayEOSWriter reads data either directly (sensor) or via different ways of communication (XBee, QLI, ...)
 * and stores data in files in a queue directory
 * 
 * Example: \ref BayEOSWriter.php
 * 
 * Storage structure:
 * 
 * BayEOSWriter only has one active file. Ending is ".act"
 * Finished files have the ending ".rd"
 * Per default exported files will get deleted. 
 * If "rm" is set to FALSE, files are kept as ".bak" files in the queue directory
 * 
 * File formate is binary. For each frame there is
 * [timestamp double][length short][frame]
 * 
 *  
 * **********************************************************************************
 * 
 * @subsection sec1_2 BayEOSSender-Class
 * 
 * BayEOSSender looks for finished data files in queue-directory and trys to send the data to the
 * configured BayEOS-Gateway
 * 
 * Example: \ref BayEOSSender.php
 *  
 * ***********************************************************************************
 * @subsection sec1_3 BayEOSGatewayClient
 * 
 * BayEOSGatewayClient is a wrapper class. Only works on systems with fork! 
 * It forks two processes for each device: one writer, one sender
 * 
 * To make use of the class
 * one has do derive a class and overwrite the BayEOSGatewayClient::readData()
 * 
 * To set up a routing devices (e.g. XBee) one has to additionaly overwrite
 * the BayEOSGatewayClient::saveData($data)
 * 
 * To init a writer process, one can overwrite BayEOSGatewayClient::initWriter()
 *
 * Example: \ref MyClient.php
 * 
 * ***********************************************************************************
 * @subsection sec1_4 BayEOSType-Class
 * 
 * BayEOSType is a helper Class to do binary transformations...
 * 
 * @section sec2 Implementations
 * BayEOSFifo
 * 
 * Eurotherm2704
 * 
 * PHPQLI
 * 
 * PHPSerialRouter
 * 
 * PHPSocketRouter
 * 
 * PHPXBeeRouter
 * 
 */
class BayEOSType {

    public static function BYTE($value) {
        return chr($value & 0xFF);
    }

    public static function UINT16($value,$endianness = 0) {
        return pack(($endianness?"n":"v"),$value);
    }

    public static function UINT32($value,$endianness = 0){
        return pack(($endianness?"N":"V"),$value);
    }
    
    public static function UINT64($value,$endianness = 0){
    	$a = gmp_init("$value");
    	$b=array();
    	for($i=0;$i<4;$i++){
    		$res = gmp_div_qr($a, "0x10000");
    		$a=$res[0];
    		$b[$i]=gmp_intval($res[1]);
    	}

    	if($endianness) return pack("nnnn",$b[3],$b[2],$b[1],$b[0]);
    	else return pack("vvvv",$b[0],$b[1],$b[2],$b[3]);
    }

    public static function FLOAT32($value,$endianness = 0){
    	$float = pack("f", $value);
    	// set 32-bit unsigned integer of the float
    	$w = unpack("L", $float);
    	return self::UINT32($w[1],$endianness);
    }

    public static function INT32($value,$endianness = 0){
    	$int = pack("l", $value);
    	// set 32-bit unsigned integer of the signed long
    	$w = unpack("L", $int);
    	return self::UINT32($w[1],$endianness);
    }

    public static function INT16($value,$endianness = 0){
    	$int = pack("s", $value);
    	// set 16-bit unsigned integer of the signed short
    	$w = unpack("S", $int);
    	return self::UINT16($w[1],$endianness);
    }

    public static function unpackUINT8($value) {
     	$res=unpack("C",$value);
    	return $res[1];
     }

    public static function unpackINT8($value) {
     	$res=unpack("c",$value);
    	return $res[1];
     }

     public static function unpackUINT16($value,$endianness = 0) {
     	$res=unpack(($endianness?"n":"v"),$value);
    	return $res[1];
   	 
    }

    public static function unpackUINT32($value,$endianness = 0) {
     	$res=unpack(($endianness?"N":"V"),$value);
    	return $res[1];
    }

    public static function unpackUINT64($value,$endianness = 0) {
     	$res=unpack(($endianness?"N2":"V2"),$value);
	if($endianness) return $res[1]<<32+$res[2];
    	else return $res[2]<<32+$res[1];
    }
    
    public static function unpackFLOAT32($value,$endianness = 0) {
     	$res=unpack(($endianness?"N":"V"),$value);  //INT32-Value
     	$int=pack("L",$res[1]); //Binary INT in Maschine Byte Order
     	$res=unpack("f",$int); //Float 
    	return $res[1];
    }

    public static function unpackINT32($value,$endianness = 0) {
     	$res=unpack(($endianness?"N":"V"),$value);  //INT32-Value
     	$int=pack("L",$res[1]); //Binary INT in Maschine Byte Order
     	$res=unpack("l",$int); //signed long
    	return $res[1];
    }

    public static function unpackINT16($value,$endianness = 0) {
     	$res=unpack(($endianness?"n":"v"),$value);  //INT16-Value
     	$int=pack("S",$res[1]); //Binary INT in Maschine Byte Order
     	$res=unpack("s",$int); //signed long
    	return $res[1];
    }
}


class BayEOS {
/**
 * create bayeos data frame
 * 
 * @param array $values
 * @param int $type
 * @param int $offset
 * @return string (binary)
 */
	public static function createDataFrame($values,$type=0x1,$offset=0){
		$bayeos_frame=pack("C2",0x1,intval($type,0));
		//Extract offset and data type
		$offset_type=(0xf0 & $type);
		$data_type=(0x0f & $type);
		if($offset_type==0x0) $bayeos_frame.=pack("C",$offset); //Simple offset Frame
		while(list($key,$value)=each($values)){
			if($offset_type==0x40) $bayeos_frame.=pack("C",$key); //Offset-Value-Frame
			switch ($data_type){
				case 0x1:
					$bayeos_frame.=BayEOSType::FLOAT32($value); //float32le
					break;
				case 0x2:
					$bayeos_frame.=BayEOSType::INT32($value); //int32le
					break;
				case 0x3:
					$bayeos_frame.=BayEOSType::INT16($value); //int16le
					break;
				case 0x4:
					$bayeos_frame.=pack("C",$value); //int8
					break;
				case 0x5:
					$bayeos_frame.=pack("d",$value); //double - Note: double may only work on little endian mashines..
					break;		
			}
		}
		//echo "BayEOS-Frame: ".array_pop(unpack('H*',$bayeos_frame))."\n";
		
		return $bayeos_frame;
	}

/**
 * Parse a binary bayeos frame into a PHP array
 * 
 * @param string $frame
 * @param float $ts
 * @param string $origin
 * @param int $rssi
 * @return array
 * 
 */
	public static function parseFrame($frame,$ts=FALSE,$origin='',$rssi=FALSE){
		if(! $ts) $ts=microtime(TRUE);
		$type=array_pop(unpack("C",substr($frame,0,1)));
		$res=array();
		switch($type){
			case 0x1:
				$res['value']=BayEOS::parseDataFrame($frame);
				$res['type']="DataFrame";
				break;
			case 0x2:
				$res['value']=substr($frame,2);
				$res['cmd']=array_pop(unpack('C',substr($frame,1,1)));
				$res['type']="Command";
				break;
			case 0x3:
				$res['value']=substr($frame,2);
				$res['cmd']=array_pop(unpack('C',substr($frame,1,1)));
				$res['type']="CommandResponse";
				break;
			case 0x4:
				$res['value']=substr($frame,1);
				$res['type']="Message";
				break;
			case 0x5:
				$res['value']=substr($frame,1);
				$res['type']="ErrorMessage";
				break;
			case 0x6:
				$origin.='/XBee'.BayEOSType::unpackUINT16(substr($frame,3,2)).':'.BayEOSType::unpackUINT16(substr($frame,1,2));
				return BayEOS::parseFrame(substr($frame,5),$ts,$origin,$rssi);
			case 0x7:
				$ts-=BayEOSType::unpackUINT32(substr($frame,1,4))/1000;
				return BayEOS::parseFrame(substr($frame,5),$ts,$origin,$rssi);
				break;
			case 0x8:
				$origin.='/XBee'.BayEOSType::unpackUINT16(substr($frame,3,2)).':'.BayEOSType::unpackUINT16(substr($frame,1,2));
				$rssi_neu=BayEOSType::unpackUINT8(substr($frame,5,1));
				if(! $rssi) $rssi=$rssi_neu;
				if($rssi_neu>$rssi) $rssi=$rssi_neu; 
				return BayEOS::parseFrame(substr($frame,6),$ts,$origin,$rssi);
			case 0x9:
				 $ts=DateTime::createFromFormat('Y-m-d H:i:s P','2000-01-01 00:00:00 +00:00')->format('U')+
				 BayEOSType::unpackUINT32(substr($frame,1,4));
				 return BayEOS::parseFrame(substr($frame,5),$ts,$origin,$rssi);
				break;
			case 0xa:
				$res['pos']=BayEOSType::unpackUINT32(substr($frame,1,4));
				$res['value']=substr($frame,5);
				$res['type']="Binary";
				break;
			case 0xb:
				$length=BayEOSType::unpackUINT8(substr($frame,1,1));
				$origin.='/'.substr($frame,2,$length);
				return BayEOS::parseFrame(substr($frame,$length+2),$ts,$origin,$rssi);
			case 0xc:
				$ts=array_pop(unpack('d',substr($frame,1,8)));
				return BayEOS::parseFrame(substr($frame,9),$ts,$origin,$rssi);
				break;
			default:
				error_log('ParseFrame: Unexpected type '.$type);
				$res['type']='Unknown';
				$res['value']=$frame;	
		}
		$res['ts']=$ts;
		$res['ts_f']=DateTime::createFromFormat('U',round($ts))->format('Y-m-d H:i:s P');
		$res['origin']=$origin;
		if($rssi) $res['rssi']=$rssi;
		return $res;		
	}
	

/**
 * Parse a bayeos data frame into a PHP array
 * 
 * @param string $frame
 * @return array
 */	
 public static function parseDataFrame($frame){
		if(substr($frame,0,1)!=pack("C",0x1)) return FALSE;
		$type=array_pop(unpack("C",substr($frame,1,1)));
		//Extract offset and data type
		$offset_type=(0xf0 & $type);
		$data_type=(0x0f & $type);
		$pos=2;
		$key=0;
		$res=array();
		if($offset_type==0x0){
			$key=array_pop(unpack("C",substr($frame,2,1)));
			$pos++;
		}
		while($pos<strlen($frame)){
			if($offset_type==0x40){
				$key=array_pop(unpack("C",substr($frame,$pos,1)));
				$pos++;
			} else $key++;
			switch ($data_type){
				case 0x1:
					$value=BayEOSType::unpackFLOAT32(substr($frame,$pos,4)); //float32le
					$pos+=4;
					break;
				case 0x2:
					$value=BayEOSType::unpackINT32(substr($frame,$pos,4)); 
					$pos+=4;
					break;
				case 0x3:
					$value=BayEOSType::unpackINT16(substr($frame,$pos,2)); 
					$pos+=2;
					break;
				case 0x4:
					$value=BayEOSType::unpackINT8(substr($frame,$pos,1));
					$pos++; 
					break;
				case 0x5:
					$value=array_pop(unpack("d",substr($frame,$pos,8))); //double - Note: double may only work on little endian mashines..
					break;
			}
			$res[$key]=$value;
		}
		return $res;
	}
	
	
	
}

class BayEOSWriter {
/**
 * Create a BayEOSWriter Instance
 *
 * @param string $path
 *  Path of queue directory
 * @param int $max_chunk
 *  Maximum file size when a new file is started
 * @param int $max_time
 *  Maximum time when a new file is started
*/
	function __construct($path,$max_chunk=5000,$max_time=60){
		$this->path=$path;
		$this->max_chunk=$max_chunk;
		$this->max_time=$max_time;
		if(! is_dir($this->path)){
			if(! mkdir($this->path,0700,TRUE)){
				die("could not create ".$this->dir);
			}
		}
		chdir($this->path);
		$files=glob('*');
		$last=end($files);
		if(strstr($last.'$','.act$')){
			//Found active file -- unexpected shutdown...
			rename($last,str_replace('.act','.rd',$last));
		}
		$this->start_new_file();
	}





/**
 * Write a dataFrame to the buffer
 * 
 * @param array $value
 *  in the form ('channel_number'=>'value',...)
 * @param int $type
 *  valid bayeos data frame type number
 * @param int offset
 *  offset parameter for bayeos data frames (not relevant for all types)
 * @param float $ts
 * Unix epoch timestamp. If zero write uses system time
*/
	function saveDataFrame($values,$type=0x1,$offset=0,$ts=0){
		$this->saveFrame(BayEOS::createDataFrame($values,$type,$offset),$ts);
	}

/**
 * Save Origin Frame
 * 
 * @param string $origin 
 *  is the name to appear in the gateway
 * @param string $frame 
 * must be a valid bayeosframe
 * @param float $ts
 * Unix epoch timestamp. If zero write uses system time
 * 
*/
	function saveOriginFrame($origin,$frame,$ts=0){
		$origin=substr($origin,0,255);
		$this->saveFrame(
			pack("C",0xb). //Starting Byte
			pack("C",strlen($origin)). //length of orginin string
			$origin. //Origin String
			$frame,$ts); 
	}

/**
 * Save Routed Frame RSSI
 * 
 * @param int $MyId
 * TX-XBee MyId
 * @param int $PanId
 * XBee PANID
 * @param int $rssi
 * RSSI
 * @param string $frame 
 * must be a valid bayeosframe
 * @param float $ts
 * Unix epoch timestamp. If zero write uses system time
 * 
*/
	function saveRoutedFrameRSSI($MyId,$PanId,$rssi,$frame,$ts=0){
		$this->saveFrame(
				pack("C",0x8). //Starting Byte
				BayEOSType::INT16($MyId). //MyID
				BayEOSType::INT16($PanId). //PANID
				pack("C",$rssi). //RSSI
				$frame,$ts);
	}
	
/**
 * Save Message
 * 
 * @param string $sting
 * Message to save
 * @param float $ts
 * Unix epoch timestamp. If zero write uses system time
 * 
 */
	function saveMessage($sting,$ts=0){
		$this->saveFrame(pack("C",0x4).$sting,$ts);
	}
	
/**
 * Save Error Message
 * 
 * @param string $sting
 * Message to save
 * @param float $ts
 * Unix epoch timestamp. If zero write uses system time
 * 
 */
	function saveErrorMessage($sting,$ts=0){
		$this->saveFrame(pack("C",0x5).$sting,$ts);
	}
	
/**
 * Save Frame
 * 
 * Base Function
 * 
 * @param string $frame 
 * must be a valid bayeosframe
 * @param float $ts
 * Unix epoch timestamp. If zero write uses system time
 * 
 */
	public function saveFrame($frame,$ts=0){
		if(! $ts) $ts=microtime(TRUE);
		fwrite($this->fp,
				pack('d',$ts).pack('s',strlen($frame)).$frame);
	
		if(ftell($this->fp)>$this->max_chunk || //max size reached or ...
				(mktime()-$this->current_ts)>$this->max_time){ //max time reached...
			//Close current file and start new one...
			fclose($this->fp);
			rename($this->current_name.'.act',$this->current_name.'.rd');
			$this->start_new_file();
		}
	
	}

	private function start_new_file(){
		$this->current_ts=mktime();
		$tmp=microtime();
		list($usec,$sec)=explode(' ',$tmp);
		$this->current_name=$sec.'-'.$usec;
		$this->fp=fopen($this->current_name.'.act','w');
	}
	
	
	private $path;
	private $max_chunk;
	private $max_time;
	private $fp;
	private $current_name;
	private $current_ts;

}

class BayEOSSender {
/**
 * Constructor for a BayEOS-Sender
 * 
 * @param string $path
 *  Path where BayEOSWriter puts files
 * @param string $name
 *  Sender name
 * @param string $url
 * 	GatewayURL e.g. http://<gateway>/gateway/frame/saveFlat
 * @param string $pw
 * 	Password on gateway
 * @param string $user
 *  User on gateway
 * @param bool $absolute_time
 *  if set to false, relative time is used (delay)
 * @param bool $rm
 *  If set to false files are kept as .bak file in the BayEOSWriter directory 
*/
	function __construct($path,$name,$url,$pw,$user='import',$absolute_time=TRUE,$rm=TRUE,$gateway_version='1.9'){
		if(! filter_var($url, FILTER_VALIDATE_URL))
			die("URL '$url' not valid\n");
		if(! $pw)
			die("No gateway password found\n");
		
		$this->path=$path;
		$this->name=$name;
		$this->url=$url;
		$this->pw=$pw;
		$this->user=$user;
		$this->absolute_time=$absolute_time;
		$this->rm=$rm;
		$this->gateway_version=$gateway_version;
	}

/**
 * Keeps sending as long as all files are send or an error occures
 * 
 * @return int 
 * number of post requests...
 */
	function send(){
		$count=0;
		while($post=$this->sendFile()){
			$count+=$post;
		}
		return $count;
		
	}
	
/**
 * Read one file from the queue and try to send it to the gateway.
 * On success file is deleted or rename the file to *.bak.
 * Takes allways the oldest file
*/
	private function sendFile(){
		chdir($this->path);
		$files=glob('*.rd');
		if(count($files)==0) return 0; //nothing to do

		//open oldest file
		//echo "opening $files[0]\n";
		$fp=fopen($files[0],'r');
		//build up post request		
		$data="sender=".urlencode($this->name)."&password=".urlencode($this->pw);
		$frames='';
		//reference time
		$ref= DateTime::createFromFormat('Y-m-d H:i:s P','2000-01-01 00:00:00 +00:00')->format('U');
		//size of data-types:
		$size_of_double=strlen(pack('d',1.0));
		$size_of_short=strlen(pack('s',1));
		
		//echo "$size_of_double - $size_of_short\n";
		$count=0;
		while(! feof($fp)){
			//read timestamp, length and bayeosframe
			$tmp=fread($fp,$size_of_double);
			if(strlen($tmp)==0) break;
			$tmp=unpack('d',$tmp);
			$ts=$tmp[1];
			$tmp=unpack('s',fread($fp,$size_of_short));
			$length=$tmp[1];
			$bayeos_frame=fread($fp,$length);
			if($bayeos_frame){
				$count++;
				if($this->absolute_time){
					if($this->gateway_version=='1.8') $bayeos_frame=pack("C",0x9).BayEOSType::UINT32(round($ts-$ref)).$bayeos_frame;
					else $bayeos_frame=pack("C",0xc).BayEOSType::UINT64(round($ts*1000)).$bayeos_frame;
				}else
					$bayeos_frame=pack("C",0x7).BayEOSType::UINT32(round((microtime(TRUE)-$ts)*1000)).$bayeos_frame;
				$frames.="&bayeosframes[]=".($this->gateway_version=='1.8'?
						base64_encode($bayeos_frame):urlencode(base64_encode($bayeos_frame)));
			}
		}
		fclose($fp);
		if($frames){
			//Frames to post...
			if($res=$this->post($data.$frames)){
				//Post successful
				if($res==1){
					if($this->rm) unlink($files[0]);
					else rename($files[0],str_replace('.rd','.bak',$files[0]));
				} elseif($res==2){
					fwrite(STDERR, date('Y-m-d H:i:s').' '.$this->name." Will keep failed file as ".
						str_replace('.rd','.bak',$files[0])."\n");
					rename($files[0],str_replace('.rd','.bak',$files[0]));
				}
				return $count;
			}
		} else {
			//Empty file...
			if(filesize($files[0])>0)
				rename($files[0],str_replace('.rd','.bak',$files[0]));
			else 
				unlink($files[0]);
			return 0;
		}
		return 0;		
		
	}
/**
 * 
 * @param $string $data
 * @return success
 * 
 */	
	private function post($data){
		//echo "POST:".$this->url.'-'.$this->pw.'-'.$this->user."\n$data\n";
		$ch=curl_init($this->url);
		curl_setopt($ch,CURLOPT_POST,1);
		curl_setopt($ch,CURLOPT_TIMEOUT,120);
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		curl_setopt($ch,CURLOPT_HEADER,1);
		curl_setopt($ch,CURLOPT_USERAGENT,'BayEOS-PHP/1.0.8');
		curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->pw);
		//curl_setopt($ch,CURLOPT_NOBODY,1);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$res=curl_exec($ch);
		//echo "CURL-res: $res\n";
		curl_close($ch);
		if($res===FALSE){
			fwrite(STDERR, date('Y-m-d H:i:s').' '.$this->name." curl_exec failed\n");
			return 0;
		}
		$res=explode("\n",$res);
		for($i=0;$i<count($res);$i++){
			if(preg_match('|^HTTP/1\\.[0-9] 200 OK|i',$res[$i])) return 1;
			elseif(preg_match('|^HTTP/1\\.[0-9] 500|i',$res[$i],$matches)){
				fwrite(STDERR, date('Y-m-d H:i:s').' '.$this->name." Post Error: $res[$i]\n");
				return 2;
			}
			elseif(preg_match('|^HTTP/1\\.[0-9] [45]|i',$res[$i],$matches)){
				fwrite(STDERR, date('Y-m-d H:i:s').' '.$this->name." Post Error: $res[$i]\n");
				return 0;
			}
		}
		return 0;
		
	}
	
	
	private $path;
	private $url;
	private $user;
	private $pw;
	private $name;
	private $absolute_time;
	private $rm;
	private $gateway_version;
	
}



class BayEOSGatewayClient{
/**
 * Create a instance of BayEOSGatewayClient
 * 
 * @param array $names
 * 	Name array: e.g. array('Fifo.0','Fifo.1'...)
 *  Name is used for the storage directory: e.g. /tmp/Fifo.0, ...
 * @param array $options
 * 	Array of options. Three forms are possible:
 *  
 *  1. $options['key']='value': Same value for all devices
 *  2. $options['key']=array('value1','value2',...): value1 is for device1, ...
 *  3. $options['key']=array('name1'=>'value1','name2'=>'value2',..) 
 *  
 *  It is not possilbe to mix forms for one key!!
 *  
 */	
function __construct($names,$options=array(),$defaults=array()){
		if(! is_array($names) && $names)
			$names=array($names);
		if(count(array_unique($names))<count($names))
			die("Duplicate names detected!");
		if(count($names)==0)
			die("No name given");
		
		$prefix='';
		if(! isset($options['sender'])){
			$prefix=gethostname().'/';
		}
		if(isset($options['sender']) && ! is_array($options['sender']) && count($names)>1){
			$prefix=$options['sender'].'/';
			unset($options['sender']);
		} 
		for($i=0;$i<count($names);$i++){
	 		$sender_defaults[$i]=$prefix.$names[$i];
		}
		
		$defaults=array_merge(array('writer_sleep_time'=>15,
					'max_chunk'=>5000,
					'max_time'=>60,
					'data_type'=>0x1,
					'sender_sleep_time'=>5,
					'sender'=>$sender_defaults,
					'bayeosgateway_user'=>'import',
					'bayeosgateway_version'=>'1.9',
					'absolute_time'=>TRUE,
					'rm'=>TRUE,
					'tmp_dir'=>sys_get_temp_dir()),$defaults);
		while(list($key,$value)=each($defaults)){
			if(! isset($options[$key])){
				echo "Option '$key' not set using default: ".(is_array($value)?implode(', ',$value):$value)."\n";
				$options[$key]=$value;
			}
		}
		$this->names=$names;
		$this->options=$options;
	}
	
	/**
	 * Helper function to get a option value
	 * 
	 * @param string $key
	 * @param string $default
	 * 
	 * @return string 
	 *  Value of the specified option key
	 */
	function getOption($key,$default=''){
		if(isset($this->options[$key])){
			if(is_array($this->options[$key])){
				if(isset($this->options[$key][$this->i])) 
					return $this->options[$key][$this->i];
				if(isset($this->options[$key][$this->name])) 
					return $this->options[$key][$this->name];	
			} else 
				return $this->options[$key];
		}
		return $default;
		
	}
/**
 * Runs the BayEOSGatewayClient
 * 
 * forks one BayEOSWrite and one BayEOSSender per name 
 */	
	function run(){
		for($i=0;$i<count($this->names);$i++){
			$this->i=$i;
			$this->name=$this->names[$i];
			$path=$this->getOption('tmp_dir').'/'.str_replace(array('/','\\','"','\''),'_',$this->name);
			$this->pid_w[$i] = pcntl_fork();
			if ($this->pid_w[$i] == -1) {
				die('Could not fork writer process!');
			} else if ($this->pid_w[$i]) {
				// We are the parent
				echo date('Y-m-d H:i:s')." Started writer for ".$this->name." with pid ".$this->pid_w[$i]."\n";
			} else {
				// We are child:
				//Start writer and run it...
				$this->initWriter(); 
				$this->writer = new BayEOSWriter($path,
						$this->getOption('max_chunk'),
						$this->getOption('max_time'));
				while(TRUE){
					//;
					$data=$this->readData();
					if($data!=FALSE) $this->saveData($data);
					else fwrite(STDERR,date('Y-m-d H:i:s')." ".$this->name.": readData failed\n");
					sleep($this->getOption('writer_sleep_time'));
				}
				exit();
		
		
			}
			$this->pid_r[$i] = pcntl_fork();
			if ($this->pid_r[$i] == -1) {
				die('Could not fork sender process');
			} else if ($this->pid_r[$i]) {
				// We are the parent
				echo date('Y-m-d H:i:s')." Started sender for ".$this->name." with pid ".$this->pid_r[$i]."\n";
			} else {
				// We are child:
				//Start sender and run it...
				$s = new BayEOSSender($path,
						$this->getOption('sender'),
						$this->getOption('bayeosgateway_url'),
						$this->getOption('bayeosgateway_pw'),
						$this->getOption('bayeosgateway_user'),
						$this->getOption('absolute_time'),
						$this->getOption('rm'),
						$this->getOption('bayeosgateway_version'));
		
				while(TRUE){
					$c = $s->send();
					if($c){
						echo date('Y-m-d H:i:s')." ".$this->name.": Successfully sent $c frames\n";
					}
					sleep($this->getOption('sender_sleep_time'));
				}
				exit();
		
			}
		
		
		
		}
		//This is only for the parent process...
		declare(ticks = 1);
		
		pcntl_signal(SIGTERM, function($signo) {
			switch ($signo) {
				case SIGTERM:
					// Aufgaben zum Beenden bearbeiten
					for($i=0;$i<count($this->pid_w);$i++){
						posix_kill($this->pid_w[$i],SIGTERM);
						$res=pcntl_waitpid($this->pid_w[$i],$status);
						echo date('Y-m-d H:i:s')." Stopping writer for ".$this->names[$i]." with pid ".$this->pid_w[$i].': '.
							($res>0?'ok':'failed')."\n";
						
					}
					for($i=0;$i<count($this->pid_r);$i++){
						posix_kill($this->pid_r[$i],SIGTERM);
						$res=pcntl_waitpid($this->pid_r[$i],$status);
						echo date('Y-m-d H:i:s')." Stopping sender for ".$this->names[$i]." with pid ".$this->pid_r[$i].": ".
							($res>0?'ok':'failed')."\n";
					}
					echo date('Y-m-d H:i:s')." Stopping main process\n";
								
					exit;
					break;
			}
		
		});
		
		while(TRUE){
			//Sleep until we get SIGTERM
			sleep(1);
		}
	}
	
	/**
	 * Method called by BayEOSGatewayClient::run()
	 * 
	 * can be overwritten by implementation
	 */
	protected function initWriter(){
	}

	/**
	 * Method called by BayEOSGatewayClient::run() 
	 * 
	 *  must be overwritten by implementation!
	 */
	protected function readData(){
		die("no readData() found!\n");
		return FALSE;
	}
	
	/**
	 * Method called by BayEOSGatewayClient::run() 
	 * 
	 * can be overwritten by Implementation (e.g. to store routed frames)
	 */
	protected function saveData($data){
		$this->writer->saveDataFrame($data,$this->getOption('data_type'));
	}
	
	protected $names;
	protected $options;
	protected $writer;
	protected $i; //The current number
}

?>
