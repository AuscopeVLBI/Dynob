<?php 
$address = "131.217.63.227";
$address = "131.217.63.251"; //dbbcho
//$address = "131.217.61.77"; //dbbcyg
$port = 4000;

//socket_bind($socket, $address, $port);
if (isset($port) and ($socket=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) and (socket_connect($socket, $address, $port))){
	$text="Connection successful on IP $address, port $port";
	echo $text;
	$msg = "version\0";
	$len = strlen($msg);
	$buf = "";
	//socket_sendto($socket, $msg, $len, MSG_DONTROUTE);
	socket_write($socket, $msg);
	//dbbccomms($socket,"dbbcifa");
	
	//if (false !== ($bytes = socket_recvfrom($socket, $buf, 1024, MSG_DONTWAIT))) {
	//	echo "Read $bytes bytes from socket_recv(). Closing socket...";
	//	echo $buf;
	//} else {
	//	echo "socket_recv() failed; reason: " . socket_strerror(socket_last_error($socket)) . "\n";
	//}
	echo socket_read($socket,1024);
	echo "done";
	socket_close($socket);
	socket_shutdown($socket,2);
}
else{
	die("Unable to connect: ".socket_strerror(socket_last_error()));
}


function dbbccomms($sock,$msg){
	$buf = "";
	$msg = (string)$msg;
	$len = strlen($msg);
	socket_send($sock, $msg."\0", $len, MSG_DONTROUTE);
	if (false !== ($bytes = socket_recv($sock, $buf, 1024, MSG_WAITALL))) {
		echo "Read $bytes bytes from socket_recv(). Closing socket...";
	} else {
		echo "socket_recv() failed; reason: " . socket_strerror(socket_last_error($sock)) . "\n";
	}
	echo $buf;
}



?>