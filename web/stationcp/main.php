<?php
if(!defined('IN_STATIONCP')){
	exit("Access Denied");
}


//content
echo '<div class="main bg-danger">';

//check cookie
if(!isset($_COOKIE["dynob_user"])) {
	session_start();
	if (!$_SESSION["login_message"]){
		$_SESSION["login_message"] = $lang["PleaseLogIn"];
	}
	if($_SESSION["login_message"] == "wrongusrpwd"){
		$_SESSION["login_message"] = $lang["wrongusrpwd"];
	}
	
	include "template/login.htm";
} else {
	include_once "template/userbar.htm";
	echo '<div class="submain">';
	include_once "template/statcp_topNav.htm";
	//echo "Value is: " . $_COOKIE["dynob_user"];
	
	switch($_GET["task"]){
		case "sched":
			include_once "stationcp/sched.htm";
			break;
		case "obs":
			include_once "stationcp/obs.htm";
			break;
		case "trans":
			include_once "stationcp/trans.htm";
			break;
		case "corr":
			include_once "stationcp/corr.htm";
			break;
		case "ana":
			include_once "stationcp/ana.htm";
			break;
		default:
			include_once "stationcp/control.htm";
	}
	
	echo '</div>';
}

echo '</div>';


?>