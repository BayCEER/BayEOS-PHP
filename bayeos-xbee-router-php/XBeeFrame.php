<?php

/**
 * XbeeFrameBase represents common functions for all types of frames
 *
 * @package XBeeFrameBase
 * @subpackage XBeeFrame
 * @subpackage XBeeResponse
 */

abstract class _XBeeFrameBase {
	const DEFAULT_START_BYTE = 0x7e, DEFAULT_FRAME_ID = 0x01,
	REMOTE_API_ID = 0x17, LOCAL_API_ID = 0x08,
	QUEUED_API_ID = 0x09, TX_API_ID = 0x10, TX_EXPLICIT_API_ID = 0x11;

	protected $frame, $frameId, $apiId, $cmdData, $startByte,
	$address16, $address64, $options, $cmd, $val;

	/**
	 * Contructor for abstract class XbeeFrameBase.
	 *
	 */
	protected function _XBeeFrameBase() {
		$this -> setStartByte(_XBeeFrameBase::DEFAULT_START_BYTE);
		$this -> setFrameId(_XBeeFrameBase::DEFAULT_FRAME_ID);
	}

	/**
	 * Assembles frame after all values are set
	 *
	 * @return void
	 */
	protected function _assembleFrame() {
		$this -> setFrame(
				$this -> getStartByte() .
				$this -> _getFramelength($this -> getCmdData()) .
				$this -> getCmdData() .
				$this -> _calcChecksum($this -> getCmdData())
		);
		//echo 'Assembled: ';print_r($this -> _unpackBytes($this -> getFrame()));        //debug
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

	/**
	 * Calculates lenth for cmdData. Leave off start byte, length and checksum
	 *
	 * @param String $data Should be a binary string
	 * @return String $length Should be a binary string
	 */
	protected function _getFramelength($data) {
		$length = strlen($data);
		$length = sprintf("%04x", $length);
		$length = $this -> _packBytes($length);
		return $length;
	}

	/**
	 * Transforms hex into a string
	 *
	 * @param String $hex
	 * @return String $string Should be a binary string
	 */
	protected function _hexstr($hex) {
		$string = '';
		for ($i=0; $i < strlen($hex); $i+=2) {
			$string .= chr(hexdec($hex[$i] . $hex[$i+1]));
		}
		return $string;
	}

	/**
	 * Transforms string into hex
	 *
	 * @param String $str Should be a binary string
	 * @return String $hex Sould be a hex string
	 */
	protected function _strhex($str) {
		$hex = '';
		for ($i=0; $i < strlen($str); $i+=2) {
			$hex .= dechex(ord($str[$i])) . dechex(ord($str[$i+1]));
		}
		return $hex;
	}

	/**
	 * Packs a string into binary for sending
	 *
	 * @param String $data
	 * @return String $data Should be a binary string
	 */
	protected function _packBytes($data) {
		return pack('H*', $data);
	}

	/**
	 * Unpacks bytes into an array
	 *
	 * @param String $data Should be a binary string
	 * @return Array $data
	 */
	protected function _unpackBytes($data) {
		return unpack('H*', $data);
	}

	/**
	 * Sets raw frame, including start byte etc
	 *
	 * @param String $frame
	 * @return void
	 */
	public function setFrame($frame) {
		$this -> frame = $frame;
	}

	/**
	 * Gets raw frame data
	 *
	 * @return String $FrameData
	 */
	public function getFrame() {
		return $this -> frame;
	}

	/**
	 * Sets FrameId according to XBee API
	 *
	 * @param String $frameId
	 * @return void
	 */
	public function setFrameId($frameId) {
		$this -> frameId = $frameId;
	}

	/**
	 * Gets frame ID according to XBee API
	 *
	 * @return String $frameId
	 */
	public function getFrameId() {
		return $this -> frameId;
	}

	/**
	 * Sets ApiId according to XBee API
	 *
	 * @param String $apiId
	 */
	public function setApiId($apiId) {
		$this -> apiId = $apiId;
	}

	/**
	 * Gets API ID
	 *
	 * @return String $apiId
	 */
	public function getApiId() {
		return $this -> apiId;
	}

	/**
	 * Sets raw command data, without start byte etc
	 *
	 * @param String $cmdData
	 * @return void
	 */
	public function setCmdData($cmdData) {
		$this -> cmdData = $this -> _packBytes($cmdData);
	}

	/**
	 * Gets raw command data, without start byte etc
	 *
	 * @return String $cmdData
	 */
	public function getCmdData() {
		return $this -> cmdData;
	}

	/**
	 * Sets Start Byte according to XBee API, defaults to 7E
	 *
	 * @param String $startByte
	 */
	public function setStartByte($startByte) {
		$this -> startByte = $this -> _packBytes($startByte);
	}

	/**
	 * Gets Start Byte according to XBee API, default is 7E
	 *
	 * @return String $startByte
	 */
	public function getStartByte() {
		return $this -> startByte;
	}

	/**
	 * Sets the 16 bit address
	 *
	 * @param String $address16
	 */
	public function setAddress16($address16) {
		$this->address16 = $address16;
	}

	/**
	 * Gets the 16 bit address
	 *
	 * @return String $address16
	 */
	public function getAddress16() {
		return $this->address16;
	}

	/**
	 * Sets the 64 bit address
	 *
	 * @param String $address64
	 */
	public function setAddress64($address64) {
		$this->address64 = $address64;
	}

	/**
	 * Gets the 64 bit address
	 *
	 * @param String $address64
	 */
	public function getAddress64() {
		return $this->address64;
	}

	/**
	 * Sets the options of the frame
	 *
	 * @param String $options
	 */
	public function setOptions($options) {
		$this->options = $options;
	}

	/**
	 * Gets the options of the frame
	 *
	 * @return String $options
	 */
	public function getOptions() {
		return $this->options;
	}

	/**
	 * Sets the command
	 *
	 * @param String $cmd
	 */
	public function setCmd($cmd) {
		$this -> cmd = $cmd;
	}

	/**
	 * Gets the command
	 *
	 * @return String $cmd
	 */
	public function getCmd() {
		return $this -> cmd;
	}

	/**
	 * Sets the value of a packet
	 *
	 * @param String $val
	 */
	public function setValue($val) {
		$this -> val = $val;
	}

	/**
	 * Gets value of value
	 *
	 * @return String $val
	 */
	public function getValue() {
		return $this -> val;
	}
}

/**
 * XbeeFrame represents a frame to be sent.
 *
 * @package XBeeFrame
 */
class XBeeFrame extends _XBeeFrameBase {
	public function XBeeFrame() {
		parent::_XBeeFrameBase();
	}

	/**
	 * Represesnts a remote AT Command according to XBee API.
	 * 64 bit address defaults to eight 00 bytes and $options defaults to 02 immediate
	 * Assembles frame for sending.
	 *
	 * @param $address16, $cmd, $val, $address64, $options
	 * @return void
	 */
	public function remoteAtCommand($address16, $cmd, $val, $address64 = '0000000000000000', $options = '02') {
		$this -> setApiId(_XBeeFrameBase::REMOTE_API_ID);
		$this -> setAddress16($address16);
		$this -> setAddress64($address64);
		$this -> setOptions($options);
		$this -> setCmd($this -> _strhex($cmd));
		$this -> setValue($val);

		$this -> setCmdData(
				$this -> getApiId() .
				$this -> getFrameId() .
				$this -> getAddress64() .
				$this ->getAddress16() .
				$this -> getOptions() .
				$this -> getCmd() .
				$this -> getValue()
		);
		$this -> _assembleFrame();
	}

	/**
	 * Represesnts a local AT Command according to XBee API.
	 * Takes command and value, value defaults to nothing
	 *
	 * @param String $cmd, String $val
	 * @return void
	 */
	public function localAtCommand($cmd, $val = '') {
		$this -> setApiId(_XBeeFrameBase::LOCAL_API_ID);
		$this -> setCmd($this ->_strhex($cmd));
		$this -> setCmdData(
				$this -> getApiId() .
				$this -> getFrameId() .
				$this -> getCmd() .
				$this -> getValue()
		);
		$this -> _assembleFrame();
	}

	/**
	 * Not Implemented, do not use
	 */
	public function queuedAtCommand() {
		$this -> setApiId(_XBeeFrameBase::QUEUED_API_ID);
		trigger_error('queued_at not implemented', E_USER_ERROR);
	}

	/**
	 * Not Implemented, do not use
	 */
	public function txCommand() {
		$this -> setApiId(_XBeeFrameBase::TX_API_ID);
		trigger_error('tx not implemented', E_USER_ERROR);
	}

	/**
	 * Not Implemented, do not use
	 */
	public function txExplicityCommand() {
		$this -> setApiId(_XBeeFrameBase::TX_EXPLICIT_API_ID);
		trigger_error('tx_explicit not implemented', E_USER_ERROR);
	}

}

/**
 * XBeeResponse represents a response to a frame that has been sent.
 *
 * @package XBeeResponse
 */
class XBeeResponse extends _XBeeFrameBase {
	const REMOTE_RESPONSE_ID = '97', LOCAL_RESPONSE_ID = '88' ; RX16 = '81';
	protected $address16, $address64, $status, $cmd, $nodeId, $signalStrength;
	protected $status_bytes = array();

	/**
	 * Constructor. Sets up an XBeeResponse
	 *
	 * @param String $response A single frame of response from an XBee
	 */
	public function XBeeResponse($response) {
		parent::_XBeeFrameBase();

		$this->status_byte = array('00' => 'OK','01' => 'Error','02'=> 'Invalid Command', '03' => 'Invalid Parameter', '04' => 'No Response' );
		$this -> _parse($response);

		if ($this -> getApiId() === XBeeResponse::REMOTE_RESPONSE_ID) {
			$this -> _parseRemoteAt();
		} else if ($this -> getApiId() === XBeeResponse::RX16) {
			$this -> _parseRX16();
		} else if ($this -> getApiId() === XBeeResponse::LOCAL_RESPONSE_ID) {
			$this -> _parseLocalAt();
		} else {
			trigger_error('Could not determine response type or response type is not implemented.', E_USER_WARNING);
		}
		/* debug
		 echo '</br>';echo 'Response:';print_r($response);echo '</br>';
		echo ' apiId:';print_r($this->getApiId());echo '</br>';echo ' frameId:';print_r($this->getFrameId());echo '</br>';
		echo ' add64:';print_r($this->getAddress64());echo '</br>';echo ' add16:';print_r($this->getAddress16());echo '</br>';
		echo ' DB:';print_r($this->getSignalStrength());echo '</br>';echo ' NI:';print_r($this->getNodeId());echo '</br>';
		echo ' CMD:';print_r($this->getCmd());echo '</br>';echo ' Status:';print_r($this->getStatus());echo '</br>';
		echo ' isOk:';print_r($this->isOk());echo '</br>';*/
	}

	/**
	 * Parses the command data from the length and checksum
	 *
	 * @param String $response A XBee frame response from an XBee
	 * @return void
	 */
	private function _parse($response) {
		$length = substr($response, 0, 2);
		$checksum = substr($response, -1);
		$cmdData = substr($response, 2, -1);
		$apiId = substr($cmdData, 0, 1);
		$frameId = substr($cmdData, 1, 1);
		$calculatedChecksum = $this -> _calcChecksum($cmdData);
		$calculatedLength = $this -> _getFramelength($cmdData);

		$packedChecksum = $checksum;        //pack for comparison
		$packedLength = $length;        //pack for comparison

		if ($packedChecksum === $calculatedChecksum && $packedLength === $calculatedLength) {
			$this -> setApiId($apiId);
			$cmdData = $this->_unpackBytes($cmdData);
			$cmdData=$cmdData[1];
			$this -> setCmdData($cmdData);
			$this -> setFrameId($frameId);
			$this -> setFrame($response);
		} else {
			trigger_error('Checksum or length check failed.', E_USER_WARNING);
		}
	}

	/**
	 * Parses remote At command
	 *
	 * @return void
	 */
	private function _parseRemoteAt() {
		//A valid remote frame looks like this:
		//<apiId1> <frameId1> <address64,8> <address16,8> <command,2> <status,2>

		$cmdData = $this->getCmdData();

		$cmd = substr($cmdData, 24, 4);
		$cmd = $this->_hexstr($cmd);

		$frameId = substr($cmdData, 2, 2);
		$status = substr($cmdData, 4, 2);
		$address64 = substr($cmdData, 4, 16);
		$address16 = substr($cmdData, 20, 4);
		$signalStrength = substr($cmdData, 30, 2);

		$this->_setSignalStrength($signalStrength);
		$this->setAddress16($address16);
		$this->setAddress64($address64);
		$this->_setCmd($cmd);
		$this->_setStatus($status);
		$this->setFrameId($frameId);
	}

	/**
	 * Parses a Local At Command response
	 *
	 * @return void
	 */
	private function _parseLocalAt() {
		//A valid local frame looks like this:
		//<api_id1> <frameId1> <command2> <status2> <add16> <add64> <DB> <NI> <NULL>
		$cmdData = $this->getCmdData();

		$cmd = substr($cmdData, 4, 6);
		$cmd = $this->_hexstr($cmd);
		$frameId = substr($cmdData, 2, 2);
		$status = substr($cmdData, 8, 2);
		$address64 = substr($cmdData, 14, 16);
		$address16 = substr($cmdData, 10, 4);
		$signalStrength = substr($cmdData, 30, 2);
		$nodeId = $this->_hexstr(substr($cmdData, 32, -2));

		$this -> _setNodeId($nodeId);
		$this->_setSignalStrength($signalStrength);
		$this->setAddress16($address16);
		$this->setAddress64($address64);
		$this->_setCmd($cmd);
		$this->_setStatus($status);
		$this->setFrameId($frameId);
	}

	/**
	 * Parses a Local At Command response
	 *
	 * @return void
	 */

	private function _parseRX16() {
		//A valid local frame looks like this:
		//<api_id1> <MYID 2b> <RSSI><Options><payload>
		$cmdData = $this->getCmdData();
		$myid = substr($cmdData,2,4);
		$rssi = substr($cmdData,6,2);
		$payload =substr($cmdData,8);


		$this->_setSignalStrength($rssi);
		$this->setAddress16($myid);
		$this->_setCmd($payload);
	}
	/**
	 * Gets signal strength in dB
	 *
	 * @return String $signalStrength
	 */
	public function getSignalStrength() {
		return $this -> signalStrength;
	}

	/**
	 * Sets signal strength
	 *
	 * @param String $strength
	 */
	private function _setSignalStrength($strength) {
		$this->signalStrength = $strength;
	}

	/**
	 * Gets Node ID aka NI
	 *
	 * @return String $nodeId
	 */
	public function getNodeId() {
		return $this->nodeId;
	}

	/**
	 * Sets Node ID aka NI
	 *
	 * @param String $nodeId
	 */
	private function _setNodeId($nodeId) {
		$this->nodeId = $nodeId;
	}

	/**
	 * Sets status
	 *
	 * @param int $status
	 */
	private function _setStatus($status) {
		$this->status = $status;
	}

	/**
	 * Returns status. If you want boolean use isOk
	 *
	 * 00 = OK
	 * 01 = Error
	 * 02 = Invalid Command
	 * 03 = Invalid Parameter
	 * 04 = No Response
	 *
	 * @return int $status
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * Checks if this resonse was positive
	 *
	 * @return boolean
	 */
	public function isOk() {
		if ($this->getStatus()=='00') {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Sets the command for this frame
	 *
	 * @return void
	 * @param String $cmd The Xbee Command
	 */
	private function _setCmd($cmd) {
		$this->cmd = $cmd;
	}

	/**
	 * Returns command.
	 *
	 * @return String $cmd
	 */
	public function getCmd() {
		return $this->cmd;
	}
}
?>