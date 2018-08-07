#!/usr/bin/php
<?php 
require_once 'BayEOSGatewayClient.php';



$options=array();

function die_usage(){
	die("Usage: dumpParser.php [-o bin|text|csv|summary] [-c frames] [-a t|f] [-s YYYY-MM-DD] [-e YYYY-MM-DD] [-k 110] [-f ''] dumpfile\n");
}

if(count($argv)<2)
	die_usage();

for($i=1;$i<count($argv);$i++){
	if(preg_match('/-[tsoekhacf]/',$argv[$i])){
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
if(! isset($options['-a'])) $options['-a']="t";
if(! isset($options['-c'])) $options['-c']=0;
if(! isset($options['-f'])) $options['-f']="";

$from=DateTime::createFromFormat('Y-m-d H:i:s P',$options['-s'].'  00:00:00 +01:00');
$until=DateTime::createFromFormat('Y-m-d H:i:s P',$options['-e'].' 00:00:00 +01:00');
$min_date= DateTime::createFromFormat('Y-m-d H:i:s P','2000-01-01 00:00:00 +00:00');
$ref_date= DateTime::createFromFormat('Y-m-d H:i:s P','2000-01-01 00:00:00 +00:00');
if($options['-a']=='f'){
	$ref_ts=$ref_date->format('U');
	$ref_date= $from; //DateTime::createFromFormat('U',$from->format('U'));
	$last_ts=0;
}
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
$s_min_date='2100-01-01 00:00:00';
$s_max_date='1900-01-01 00:00:00';
$pos=0;
$count=0;
$found=0;
$last_date='-';
$summary=array();
$summary_m=array();
$summary_y=array();
while(! feof($fp)){
	//read timestamp, length and bayeosframe
	$ts_bin=fread($fp,4);
	if(strlen($ts_bin)<4) break;
	$ts=BayEOSType::unpackUINT32($ts_bin);
	if($options['-a']=='f'){//relative time millis()
		$ts/=1000;
		if($ts<$last_ts){
			$ref_date= DateTime::createFromFormat('U',round($ref_date->format('U')+$last_ts));
			//print("found $ts - $last_ts ".date('Y-m-d h:i:s',$ref_date->format('U')));
		}
		$ts_bin=BayEOSType::UINT32($ts+$ref_date->format('U')-$ref_ts);
		$last_ts=$ts;
	}
	$last_date=date('Y-m-d H:i:s',$ts+$ref_date->format('U'));
	$length_bin=fread($fp,1);
	$length=BayEOSType::unpackUINT8($length_bin);
	if($length) $bayeosframe=fread($fp,$length);
	else $bayeosframe='';
	$pos+=5+$length;
	if($options['-f']){
	    $res=BayEOS::parseFrame($bayeosframe,$ts+$ref_date->format('U'));
	    $filter=$res['origin']==$options['-f'];
	} else
	    $filter=1;
	if($ts>$min && $ts<$max && $bayeosframe && $count>=$options['-c'] && $filter){
		if($options['-o']=='text'){
			$res=BayEOS::parseFrame($bayeosframe,$ts+$ref_date->format('U'));
			fwrite(STDOUT,"found frame: ".date('Y-m-d H:i:s',$ts+$ref_date->format('U'))." - ".($ts/3600)."\n");
			print_r($res);
		} elseif($options['-o']=='csv') {
		    $res=BayEOS::parseFrame($bayeosframe,$ts+$ref_date->format('U'));
		    fwrite(STDOUT,$res['ts_f'].",".$res['origin'].",".implode(',',$res['value'])."\n");
		} elseif($options['-o']=='bin') {
		    fwrite(STDOUT,$ts_bin.$length_bin.$bayeosframe);
		} elseif($options['-o']=='hex') {
			fwrite(STDOUT,"found frame: ".date('Y-m-d H:i:s',$ts+$ref_date->format('U'))." - ".($ts/3600)."\n");
			fwrite(STDOUT,strlen($bayeosframe)."\n");
			fwrite(STDOUT,bin2hex($bayeosframe)."\n");			
		} elseif($options['-o']=='summary'){
			if($last_date<$s_min_date) $s_min_date=$last_date;
			if($last_date>$s_max_date) $s_max_date=$last_date;
			$day_key=date('Y-m-d',$ts+$ref_date->format('U'));
			$month_key=date('Y-m',$ts+$ref_date->format('U'));
			$year_key=date('Y',$ts+$ref_date->format('U'));
			if(! isset($summary[$day_key])) $summary[$day_key]=1;
			else $summary[$day_key]++;
			if(! isset($summary_m[$month_key])) $summary_m[$month_key]=1;
			else $summary_m[$month_key]++;
			if(! isset($summary_y[$year_key])) $summary_y[$year_key]=1;
			else $summary_y[$year_key]++;
		}
		$found++;
	}
	$count++;
	fwrite(STDERR,"done: ".round($pos/$fsize*100,2)."% - found: $found ".strlen($bayeosframe)." $last_date\r");
}
if($options['-o']=='summary'){
	echo "\n\nSummary:
Frames: $count
First Frame: $s_min_date
Last Frame: $s_max_date

Year  Frames
";
	while(list($key,$value)=each($summary_y)){
		echo "$key: $value\n";
	}
	
	echo "
Month    Frames
";
	
	while(list($key,$value)=each($summary_m)){
		echo "$key: $value\n";
	}
	echo "
Date       Frames
";
	while(list($key,$value)=each($summary)){
		echo "$key: $value\n";	
	}
		
}


?>
