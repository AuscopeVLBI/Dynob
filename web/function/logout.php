<?php

if (isset($_COOKIE["dynob_user"])) {
    unset($_COOKIE["dynob_user"]);
    setcookie("dynob_user", '', time() - 3600, '/'); 
}

if($_GET['ref']!==""){
	header('Refresh: 0.1; URL = '.base64_decode($_GET['ref']));
}
else{
	if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
		$protocol = 'https://';
	}
	else {
		$protocol = 'http://';
	}
	header('Refresh: 0.1; URL = '.$protocol.$_SERVER['SERVER_NAME'].'/web/index.html');
}

?>