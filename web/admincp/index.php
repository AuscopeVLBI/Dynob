<?php

if(!isset($_COOKIE["dynob_user"])) {
	if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
		$protocol = 'https://';
	}
	else {
		$protocol = 'http://';
	}
	$currentUrl = $protocol . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
	header('Refresh: 0.1; URL = '.$protocol.$_SERVER['SERVER_NAME'].'/web/login.php?ref='.base64_encode($currentUrl));
	die();
}
else {
	if($_COOKIE["dynob_user"] == "admin" ){
		header('Refresh: 0.1; URL = ./main.php');
	}
	else{
		exit("You do not have access to the admin control panel");
	}
}



?>