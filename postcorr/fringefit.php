<?php
//usage: fringefit.php?exp=[expname]&curind=1

require_once "../common/SSHcon.php";
require_once "../common/global.php";
require_once "../common/dbwrite.php";

if(!isset($_COOKIE["dynob_user"])) {
	if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
		$protocol = "https://";
	}
	else {
		$protocol = "http://";
	}
	$CurPageURL = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	header("Location: ".$protocol.$_SERVER['HTTP_HOST']."/web/login.php?ref=".base64_encode($CurPageURL));
	die();
}

$expname = $_GET["exp"];
$exptype = substr($expname,0,2);
$currentline = $_GET["curind"];
$exptype1 = substr($expname,0,1);
if($exptype1 == "z"){//si
	$exptype = "si";
}

//$scanlist = fopen('scans_'.$expname.'.txt',"r");

//fclose($cfexp);
$scanlist = 'scans_'.$expname.'.txt';
$thescans = file($scanlist);
$thescans = array_filter($thescans);
$thescans = array_values($thescans);

$numscans = count($thescans);
$scannm = $thescans[$currentline-1];
$vgosstations = $GLOBALS["vgosstations"];
$stcodes = $GLOBALS["stcodes"];
$db = new DbWrite;
if($exptype!=="mf"){
	$dbq = $db->query("dynob",'SELECT expcode FROM Correlation WHERE session="'.$expname.'"');
	$expcode = $dbq[0]["expcode"];
}

else{
	$expcode = "1234";
}

$conssh = new ConnectSSH;
if(!$con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"])){
	die("Unable to reach ".$GLOBALS["cserver"]);
}

$stsinvolve = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls ~/correlations/".$exptype."/".$expname.'/'.$expcode.'/'.$scannm)));
array_shift($stsinvolve);

//print_r($stsinvolve);
//die();

if(count(array_filter($stsinvolve))>0){
	foreach ($stsinvolve as $sts){
		$stc1 = substr($sts,0,1);
		$stc2 = substr($sts,1,1);
		$stc1key = strtolower(array_keys($stcodes,$stc1)[0]);
		$stc2key = strtolower(array_keys($stcodes,$stc2)[0]);
		$pol = "";
		
		if($stc2!=='.' && strpos($sts,'..')!==false ){
			if(in_array($stc1key,$vgosstations)){
				if(in_array($stc2key,$vgosstations)){
					//vgos baseline
					//echo $stc1key.$stc2key;
					//die();
					$pol = "I";
				}
				else{
					//mixed-mode baseline
					//first station is vgos:				
					$pol = "RR+LR";
				}
					
			}
			else{
				if(in_array($stc2key,$vgosstations)){
					//mixed-mode baseline
					//second station is vgos:
					$pol = "RR+RL";
				}
				else{
					//legacy baseline
					$pol = "RR";
				}
			}
			echo $conssh->exec($con,"cd correlations/".$exptype."/".$expname.'; parallel fourfit -b'.$stc1.$stc2.' -P'.$pol.' -c cf_'.$expname.' ::: '.$expcode.'/'.$scannm);
		}
	}
}

if($currentline<$numscans){
	$nextline = $currentline+1;
	echo "Preparing to fourfit the next scan";
	header("Refresh: 2; url=./fitmsg.php?exp=".$expname."&curind=".$nextline);
	exit();
}
else{
	//echo "mv ".$_SERVER['DOCUMENT_ROOT']."postcorr/".$scanlist." ".$_SERVER['DOCUMENT_ROOT']."postcorr/scans/".$scanlist;
	//shell_exec("mv ".$_SERVER['DOCUMENT_ROOT']."postcorr/".$scanlist." ".$_SERVER['DOCUMENT_ROOT']."postcorr/scans/".$scanlist);
	rename($_SERVER['DOCUMENT_ROOT']."postcorr/".$scanlist,$_SERVER['DOCUMENT_ROOT']."postcorr/scans/".$scanlist);//just in case

	echo "<p>fringefit done</p>";
}

?>
