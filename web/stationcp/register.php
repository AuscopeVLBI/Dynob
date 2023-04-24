<?php
if(!defined('IN_STATIONCP')){
	exit("Access Denied");
}

$rand = md5(mt_rand(0,999999999));
//content
echo '<div class="main bg-danger">';
include "template/nav.htm";
include "template/register.htm";
echo '</div>';


if (isset($_POST['submit'])){
	include_once "function/addUser.php";
	$au = new AddUser;
	if($au->getStatus()){
		setcookie("dynob_user", $_POST["username"], time()+86400, "/");
	}
	else{
		echo "false";
	}
}

$secKey = $rand;

?>