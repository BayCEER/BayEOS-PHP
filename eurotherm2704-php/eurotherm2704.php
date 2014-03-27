#!/usr/bin/php
<?php 
/*
 * Command line script to read out Eurotherm 2704 via ModbusTCP
 * 
 * 
 */

require_once 'BayEOSGatewayClient.php';

$config=parse_ini_file('/etc/eurotherm2704.ini');
/*
 * Read configuration file and set some defaults
*/
if(! isset($config['names'])){
	$names=array();
	for($i=0;$i<count($config['host']);$i++){
		$names[$i]='IP.'.$config['host'][$i];
	}
} else $names=$config['names'];

if(! isset($config['sender'])) $config['sender']=$names;

/*
 * Check hosts
 */
for($i=0;$i<count($config['host']);$i++){
	if(! preg_match('/^[0-9]+\\.[0-9]+\\.[0-9]+\\.[0-9]+$/',$config['host'][$i]))
		die($config['host'][$i]." is not a valid IP address\n");
}


//Extend BayEOSGatewayClient Class
class Eurotherm2704 extends BayEOSGatewayClient {
	protected function readData(){
		$host=$this->getOption('host');
		$name=$this->names[$this->i];
		$addr=array(1,5,1025,1029,2049,2053,
				12197,12198,12199,12200,12201,12202,
				12204,12205);
		$namen=array('Istwert Temperatur Prüfraum','Sollwert Temperatur Prüfraum',
				'Istwert Feuchte Prüfraum','Sollwert Feuchte Prüfraum',
				'Istwert Temperatur Lampenraum','Sollwert Temperatur Lampenraum',
				'Lampengruppe 1','Lampengruppe 2','Lampengruppe 3','Lampengruppe 4','Lampengruppe 5','Lampengruppe 6',
				'Störung','Störung Kaltsolesatz');

		$fp=fsockopen($host,502, $errno, $errstr, 10);
		if(! $fp){
			fwrite(STDERR, date('Y-m-d H:i:s')." $name: No Socket to $host: $errstr ($errno)\n");
			return FALSE;
		}
		$values=array();
		$modbus_error=0;
		for($i=0;$i<count($addr);$i++){
			$req=$this->readMultipleRegistersPacketBuilder(255, $addr[$i], 1);
			//		echo bin2hex($req)."\n";
			fputs($fp,$req);
			$packet=fread($fp,11);
			//		echo bin2hex($packet)."\n";
			if((ord($packet[7]) & 0x80) > 0) $modbus_error=1;
			if($modbus_error){
				fwrite(STDERR, date('Y-m-d H:i:s')." $name: Modbus Error $host\n");
				return FALSE;
			}

			$int=BayEOSType::unpackINT16($packet[9].$packet[10],1);
			if($i<6) $int/=10;
			$values[]=$int;
		}
		fclose($fp);
		return $values;

	}

	private	function readMultipleRegistersPacketBuilder($unitId, $reference, $quantity){
		$dataLen = 0;
		// build data section
		$buffer1 = "";
		// build body
		$buffer2 = "";
		$buffer2 .= BayEOSType::BYTE(3);             // FC 3 = 3(0x03)
		// build body - read section
		$buffer2 .= BayEOSType::UINT16($reference,1);  // refnumber = 12288
		$buffer2 .= BayEOSType::UINT16($quantity,1);       // quantity
		$dataLen += 5;
		// build header
		$buffer3 = '';
		$buffer3 .= BayEOSType::UINT16(rand(0,65000),1);   // transaction ID
		$buffer3 .= BayEOSType::UINT16(0,1);               // protocol ID
		$buffer3 .= BayEOSType::UINT16($dataLen + 1,1);    // lenght
		$buffer3 .= BayEOSType::BYTE($unitId);        //unit ID
		// return packet string
		return $buffer3. $buffer2. $buffer1;
	}


}




$my_client = new Eurotherm2704($names,$config);
$my_client->run();






?>