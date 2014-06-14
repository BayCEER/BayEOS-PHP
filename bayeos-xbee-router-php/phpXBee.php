<?php
require 'BayEOSSerialPHP.php';
/**
* XBee represents an XBee connection
*
* Be sure to configure the serial connection first.
* If on linux run these commands at the shell first:
*
* Add apache user to dialout group:
* sudo adduser www-data dialout
*
* Restart Apache:
* sudo /etc/init.d/apache2 restart
*
* THIS PROGRAM COMES WITH ABSOLUTELY NO WARANTIES !
* USE IT AT YOUR OWN RISKS !
* @author Chris Barnes
* @thanks Rémy Sanchez, Aurélien Derouineau and Alec Avedisyan for the original serial class
* @copyright GPL 2 licence
* @package phpXBee
*/
define("XBEE_ESCAPE",pack("C",0x7d));
define("XBEE_DELIM",pack("C",0x7e));
define("XON",pack("C",0x11));
define("XOFF",pack("C",0x13));
class XBee extends phpSerial {
        private $stack;
		/**
         * Constructor. Parent is phpSerial
         *
         * @return Xbee
         */
        function XBee() {
        		$this->stack=array();
                parent::phpSerial();
        }

        /**
         * Sets up typical Connection 38400 8-N-1
         *
         * @param String $device is the path to the xbee, defaults to /dev/ttyUSB0
         * @return void
         */
        public function confDefaults($device = '/dev/ttyUSB0') {
                $this -> deviceSet($device);
                $this -> confBaudRate(38400);
                $this -> confParity('none');
                $this -> confCharacterLength(8);
                $this -> confStopBits(1);
                $this -> confFlowControl('none');
        }
        
        /**
         * Opens this XBee connection.
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
         * Closes this XBee connection
         * @return void
         */
        public function close() {
                $this -> deviceClose();
        }
        
        /**
         * Sends an XBee frame. $waitForReply is how long to wait on recieving
         *
         * @param $frame
         * @param int $waitForRply
         * @return void
         */
        public function send($frame , $waitForReply=0) {
        	    $frame=XBEE_DELIM.$this->_escape($frame);
                $this -> sendMessage($frame, $waitForReply);                
                //echo 'Send: '.array_pop(unpack('H*',$frame))."\n";        //debug
        }

        public function read($count = 0){
        	$data = $this -> readPort($count);
        	//echo 'READ: '.array_pop(unpack('H*',$data))."\n";
        	if(! $data) return count($this->stack);
        	if(! isset($this->stack[0])){
        		$delim_pos=strpos($data,XBEE_DELIM);
        		if($delim_pos===FALSE) return 0;
        		else $data=substr($data,$delim_pos+1);
        	}
        	
        	$data=explode(XBEE_DELIM,$data);
        	$offset=count($this->stack)-1;
        	if($offset==-1) $offset=0;
        	for($i=0;$i<count($data);$i++){
        		$index=$i+$offset;
        		if(! isset($this->stack[$index]['ts']))
        			$this->stack[$index]['ts']=microtime(TRUE);
        		if(! isset($this->stack[$index]['rawframe'])) 
        			$this->stack[$index]['rawframe']='';
        		$this->stack[$index]['rawframe'].=$data[$i];
        		$this->stack[$index]['frame']=$this->_unescape($this->stack[$index]['rawframe']);
        		$this->stack[$index]['ok']=$this->_parseFrame($this->stack[$index]['frame']);
				if(! $this->stack[$index]['ok'] && ($i+1)<count($data)){
					//Invalid Frame!
					unset($this->stack[$index]);
					$offset--; 
			       	fwrite(STDERR,date('Y-m-d H:i:s').' '.$this->_device." : Invalid frame\n");
				}
        	}
        	
        	$anz=count($this->stack);
			//echo "Stackcount: $anz\n";
        	if(! $this->stack[$anz-1]['ok']) $anz--;
        	return $anz;
        }
        
        public function getPANID(){
        	$this->send(pack('C7',0x00,0x04,0x08,0x01,0x49,0x44,0x69));
        	
        	while(($data=$this->getFrame())){
        		$f=$data['frame'];
        		//echo "Got:".array_pop(unpack('H*',$f))."\n";
        		if($f[2]==pack("C",0x88) && $f[4]=='I' && $f[5]=='D' && $f[6]==pack("C",0x0)){
        			return array_pop(unpack('n',substr($f,7,2)));
        		}  		
        	}
        	die("Could not get PANID\n");
        }
        
        private function _parseFrame($frame){
        	if(strlen($frame)<3) return FALSE;
        	$length = substr($frame, 0, 2);
        	$checksum = substr($frame, -1);
        	$cmdData = substr($frame, 2, -1);
        	$calculatedChecksum = $this -> _calcChecksum($cmdData);
        	$calculatedLength = pack('n',strlen($cmdData));
         	
        	//echo "parseFrame: ".array_pop(unpack('H*',$frame.$calculatedChecksum.$checksum.$calculatedLength.$length))."\n";
        	if ($checksum === $calculatedChecksum && $length === $calculatedLength) {
        		return TRUE;
        	} else {
        		return FALSE;
        	}
        	 
        }
        
        
        
        /**
         * Calculates checksum for cmdData. Leave off start byte, length and checksum
         *
         * @param String $data Should be a binary string
         * @return String $checksum Should be a binary string
         */
        protected function _calcChecksum($data) {
        	$checksum = 0;
        	for ($i = 0; $i < strlen($data); $i++) {
        		$checksum += ord($data[$i]);
        	}
        	$checksum = $checksum & 0xFF;
        	$checksum = 0xFF - $checksum;
        	$checksum = chr($checksum);
        	return $checksum;
        }
        
        
        public function getFrame($timeout=120){
        	while($this->read()==0 && $timeout>0){
        		$timeout-=0.01;
        		usleep(10000);
        	}
        	if($timeout<0) return FALSE;
			return array_shift($this->stack);
        }
        
		private function _escape($rawData){
			$res='';
			for($i=0;$i<strlen($rawData);$i++){
				if(in_array($rawData[$i],array(XBEE_ESCAPE,XBEE_DELIM,XOFF,XOFF)))
					$res.=XBEE_ESCAPE.(pack("C",0x20) ^ $rawData[$i]);	
				else $res.=$rawData[$i];
			}
			return $res;						
		}
        
        private function _unescape($rawData){
        	//echo "Unescape: ".array_pop(unpack('H*',$rawData))."\n";
        	$res='';
        	for($i=0;$i<strlen($rawData);$i++){
        		if($rawData[$i]===XBEE_ESCAPE){
        			//echo "got escape!!\n";
        			$i++;
        			if($i<strlen($rawData))
        				$res.=pack("C",0x20) ^ $rawData[$i];
        		} else $res.=$rawData[$i];
        	}
        	//echo "Unescape: ".array_pop(unpack('H*',$res))."\n";
        	return $res;
        }
                
}

?>