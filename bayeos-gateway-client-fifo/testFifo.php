<?php
posix_mkfifo('/tmp/test',0600);
posix_mkfifo('/tmp/test.error',0600);

$fp=fopen('/tmp/test','r');
$ep=fopen('/tmp/test.error','r');
while(true){
	if($line=fread($fp,128)){
		echo $line;
		echo "got line\n";
	}
	if($line2=fread($ep,128)){
		echo $line2;
		echo "got line2\n";
	}
	sleep(1);
}