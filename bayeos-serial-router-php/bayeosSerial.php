<?php
require 'BayEOSSerialPHP.php';

/**
*
*/
define("XBEE_ESCAPE",pack("C",0x7d));
define("XBEE_DELIM",pack("C",0x7e));
define("XON",pack("C",0x11));
define("XOFF",pack("C",0x13));

define("API_DATA",pack("C",0x1));
define("API_ACK",pack("C",0x2));

define("TX_OK",pack("C",0x1));
define("TX_CHECKSUM_FAILED",pack("C",0x2));
define("TX_BREAK",pack("C",0x3));

class BaySerial extends phpSerial {
        private $stack;
		/**
         * Constructor. Parent is phpSerial
         *
         * @return BaySerial
         */
        function BaySerial() {
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
         * Opens this BaySerial connection.
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
         * Closes this BaySerial connection
         * @return void
         */
        public function close() {
                $this -> deviceClose();
        }
        
        /**
         * Sends an BaySerial frame. $waitForReply is how long to wait on recieving
         *
         * @param $frame
         * @param int $waitForRply
         * @return void
         */
        public function send($frame , $api=API_DATA, $timeout=120) {
       	 	$frame=pack("C",strlen($frame)).$api.$frame.$this->_calcChecksum($api.$frame);
       	    $frame=XBEE_DELIM.$this->_escape($frame);
       	    //echo "SEND: ".array_pop(unpack('H*',$frame))."\n";
            $this -> sendMessage($frame, 0);
            
            if($api!=API_DATA) return 0;
            
            if($res=$this->getFrame($timeout)===FALSE) return 2;                
                //echo 'Send: '.array_pop(unpack('H*',$frame))."\n";        //debug
            if($res['api']!=API_ACK){
            	$this->sendTXBreak();
            	$this -> sendMessage($frame, 0);
            }
            if($res=$this->getFrame($timeout)===FALSE) return 2;
            
            if($res['api']===API_ACK){
            	if($res['frame']===TX_OK) return 0;
            	if($res['frame']===TX_CHECKSUM_FAILED) return 1;
            	if($res['frame']===TX_BREAK) return 3;
            }
            return 2;
                
        }

        public function read($count = 0){
        	$data = $this -> readPort($count);
        	//if($data) echo 'READ: '.array_pop(unpack('H*',$data))."\n";
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
        		if(! isset($this->stack[$index]['frame'])) 
        			$this->stack[$index]['frame']='';
        		$this->stack[$index]['frame'].=$this->_unescape($data[$i]);
        		//echo "Stack[$index]: ".array_pop(unpack('H*',$this->stack[$index]['frame']))."\n";
        		$this->stack[$index]['ok']=$this->_parseFrame($this->stack[$index]['frame']);
				if(! $this->stack[$index]['ok'] && ($i+1)<count($data)){
					//Invalid Frame!
					echo "Invalid Frame\n";
					unset($this->stack[$index]);
					$offset--; 
				}
        	}
        	
        	$anz=count($this->stack);
			//echo "Stackcount: $anz\n";
        	if(! $this->stack[$anz-1]['ok']) $anz--;
        	return $anz;
        }
        
        
        private function _parseFrame($frame){
        	if(strlen($frame)<3) return FALSE;
        	//echo "PARSE: ".array_pop(unpack('H*',$frame))."\n";
        	$length = substr($frame, 0, 1);
         	$checksum = substr($frame, -1);
        	$cmdData = substr($frame, 1, -1);
        	$calculatedChecksum = $this -> _calcChecksum($cmdData);
        	$calculatedLength = pack('C',strlen($cmdData)-1);
         	
        	//echo "parseFrame: ".array_pop(unpack('H*',$frame.$calculatedChecksum.$checksum.$calculatedLength.$length))."\n";
        	if ($checksum === $calculatedChecksum && $length === $calculatedLength) {
        		//echo "Frame OK\n";
        		$this->_sendAck(TX_OK);
        		return TRUE;
        	} else if($length === $calculatedLength) {
        		echo "Checksum failure\n";
        		$this->_sendAck(TX_CHECKSUM_FAILED);
        		return FALSE;
        	} else {
        		return FALSE;
        	}
        	 
        }

        private function _sendAck($type){
        	$this->send($type,API_ACK);
        }
        
        public function sendTXBreak(){
        	$this->_sendAck(TX_BREAK);
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
        	
        	$frame=array_shift($this->stack);
        	$frame['api']=substr($frame['frame'],1,1);
            $frame['length']=substr($frame['frame'],0,1);
            $frame['frame']=substr($frame['frame'],2,-1);
        	
			return $frame;
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
        			$res.=pack("C",0x20) ^ $rawData[$i];
        		} else $res.=$rawData[$i];
        	}
        	//echo "Unescape: ".array_pop(unpack('H*',$res))."\n";
        	return $res;
        }
        
}

?>