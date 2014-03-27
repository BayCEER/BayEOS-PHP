<?php 
$socket = stream_socket_server("tcp://0.0.0.0:8000", $errno, $errstr);
if (!$socket) {
	echo "$errstr ($errno)<br />\n";
} else {
	while (TRUE){
		if($conn = stream_socket_accept($socket)) {
			echo "Got connect\n";
			$count=0;
			while(fwrite($conn, "1 $count 4\n")){
				if(fflush($conn))
					echo "Data send\n";;
				$count++;
				
				sleep(10);
			}
			fclose($conn);	
		}
	}
fclose($socket);
}



?>