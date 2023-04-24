<?php
//this script search through vc0 mfdynf for the baselines, run fourfit and convert ps to jpg

require_once "../../common/SSHcon.php";
require_once "../../common/global.php";

//remove previous plots
$files = glob($_SERVER['DOCUMENT_ROOT']."web/fringe/realtimeplot/*"); // get all file names
foreach($files as $file){ // iterate files
	if(is_file($file)) {
	  unlink($file); // delete file
	}
}
//

$vgosstations = $GLOBALS["vgosstations"];
$stcodes = $GLOBALS["stcodes"];

$conssh = new ConnectSSH;
if(!$con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"])){
	die("Unable to reach ".$GLOBALS["cserver"]);
}

$stsinvolve = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls ~/correlations/mf/mfdynf/1234/*")));
array_shift($stsinvolve);

$baselines = [];
$pols = [];
foreach($stsinvolve as $_stinv){
	$_st1 = substr($_stinv,0,1);
	$_st2 = substr($_stinv,1,1);
	if($_st2!=="." && !in_array($_st1.$_st2,$baselines) && $_st1!==$_st2){
		array_push($baselines,$_st1.$_st2);
	}
	$polinv = explode(".",$_stinv);
	if ($polinv[1]!=="" && !in_array($polinv[1],$pols) ){
		array_push($pols,$polinv[1]);
	}
}
$pollist = ["RR","RL","LL","LR"];


foreach($baselines as $_bl){
	foreach ($pollist as $_polli){
		foreach($pols as $_pols){
			$ff = $conssh->exec($con,"cd ~/correlations/mf/mfdynf/; fourfit -t -b".$_bl.":".$_pols." -P".$_polli." -c cf_mfdynf -d diskfile:".$_bl.$_polli.$_pols.".ps 1234/*");

			$fp = $conssh->exec($con,"cd ~/correlations/mf/mfdynf/; gs -sDEVICE=jpeg -dJPEGQ=50 -dNOPAUSE -dBATCH -dSAFER -r60 -sOutputFile=".$_bl.$_polli.$_pols.".jpg ".$_bl.$_polli.$_pols.".ps");
			$fs = ssh2_scp_recv($con, 'correlations/mf/mfdynf/'.$_bl.$_polli.$_pols.'.jpg', $_SERVER['DOCUMENT_ROOT']."web/fringe/realtimeplot/".$_bl.$_polli.$_pols.".jpg");
		}
		
	}
}

//show plots
$allFiles = scandir($_SERVER['DOCUMENT_ROOT']."web/fringe/realtimeplot/"); 
$files = array_diff($allFiles, array('.', '..'));
foreach ($files as $_pic){
	echo '<div style="width: 500px;height: 300px;overflow: hidden;"><img src="./realtimeplot/'.$_pic.'" alt="'.$_bl.$_polli.'"></div>';
}

?>
