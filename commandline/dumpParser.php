#!/usr/bin/php
<?php 
require_once 'BayEOSGatewayClient.php';



$options=array();

function die_usage(){
	die("Usage: dumpParserExtractor.php [-o bin|text] [-s YYYY-MM-DD] [-e YYYY-MM-DD] [-k 110] dumpfile\n");
}

if(count($argv)<2)
	die_usage();

for($i=1;$i<count($argv);$i++){
	if(preg_match('/-[tsoekh]/',$argv[$i])){
		if($argv[$i]=='-h') die_usage();
		$key=$argv[$i];
		$i++;
		$value=$argv[$i];
		$options[$key]=$value;
	} else 
		$file=$argv[$i];
}
if(! isset($options['-s'])) $options['-s']="2000-01-01";
if(! isset($options['-e'])) $options['-e']=date('Y-m-d',time()+24*3600);
if(! isset($options['-k'])) $options['-k']="110";
if(! isset($options['-o'])) $options['-o']="";

$from=DateTime::createFromFormat('Y-m-d H:i:s P',$options['-s'].' 00:00:00 +01:00');
$until=DateTime::createFromFormat('Y-m-d H:i:s P',$options['-e'].' 00:00:00 +01:00');
$min_date= DateTime::createFromFormat('Y-m-d H:i:s P','2000-01-01 00:00:00 +00:00');
$ref_date= DateTime::createFromFormat('Y-m-d H:i:s P','2000-01-01 00:00:00 +00:00');
$min=$from->format('U')-$ref_date->format('U');
$max=$until->format('U')-$ref_date->format('U');


if(! is_readable($file))
	die("File $file not readable\n");
$fp=fopen($file,"r");
$fsize=filesize($file);
if(isset($options['-t'])) $t_shift=1*$options['-t'];
else $t_shift=0;

$sig='';
for($i=0;$i<strlen($options['-s']);$i++){
	$sig.=pack("C",(1*substr($options['-s'],$i,1)));
}

$pos=0;
$found=0;
$last_date='-';
while(! feof($fp)){
	//read timestamp, length and bayeosframe
	$ts_bin=fread($fp,4);
	$ts=BayEOSType::unpackUINT32($ts_bin);
	$last_date=date('Y-m-d h:i:s',$ts+$ref_date->format('U'));
	$length_bin=fread($fp,1);
	$length=BayEOSType::unpackUINT8($length_bin);
	if($length) $bayeosframe=fread($fp,$length);
	else $bayeosframe='';
	$pos+=5+$length;
	if($ts>$min && $ts<$max && $bayeosframe){
		if($options['-o']=='text'){
			$res=BayEOS::parseFrame($bayeosframe,$ts+$ref_date->format('U'));
			fwrite(STDOUT,"found frame: ".date('Y-m-d h:i:s',$ts+$ref_date->format('U'))." - ".($ts/3600)."\n");
			print_r($res);
		} elseif($options['-o']=='bin') {
			fwrite(STOUT,$ts_bin.$length_bin.$bayeosframe);
		}
		$found++;
	}
	fwrite(STDERR,"done: ".round($pos/$fsize*100,2)."% - found: $found ".strlen($bayeosframe)." $last_date\n");
}



?>
