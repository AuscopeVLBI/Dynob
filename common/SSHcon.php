<?php
class ConnectSSH {

	function connect($server, $user, $password){
		if(count(explode(".",$server))>=4){
			//$connection = ssh2_connect(trim($server), 22, [ 'hostkey' => 'ecdsa-sha2-nistp256,ssh-rsa']);
			$connection = ssh2_connect(trim($server), 22);
		}
		else{
			$connection = ssh2_connect($server.'.phys.utas.edu.au', 22);
		}
		if (!ssh2_auth_password($connection, $user, $password)) {
			print_r($connection);
			return false;
			//die('Authentication Failed for '.$server);
		}
		return $connection;
	}

	//exec
	function exec($connection, $command){
		$stream = ssh2_exec($connection, $command);
		//print output
		stream_set_blocking($stream, true);
		$stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
		$contents = stream_get_contents($stream_out);
		//var_dump($contents);
		return $contents;
	}

}

//Usage:
//$conssh = new ConnectSSH;
//new connect
//$con = $conssh->connect("131.217.62.225", "observer", "LunchB0x");
//exec
//echo $conssh->exec($con,'ls -la');
//$conssh->exec($con,'cd /usr2/sched;ls | grep dyn');
//send
//ssh2_scp_send($connection, '/local/filename', '/remote/filename', 0644);
//receive
//ssh2_scp_recv($connection, '/remote/filename', '/local/filename');
?>
