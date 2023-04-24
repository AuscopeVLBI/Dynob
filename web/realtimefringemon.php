<?php
//this script monitor mfdynf. Output: SNR per baseline, and antenna SEFD


require_once "../common/SSHcon.php";
require_once "../common/global.php";
require_once "../common/dbwrite.php";


$RTM = new RTFmon;
$RTM->monSNR();
$RTM->monSEFD();

Class RTFmon {

	function monSNR(){

		$baseline = [];

		function rpstc($matches){
			$_rpstc = ["H"=>"Ho","L"=>"Hb","i"=>"Ke","e"=>"Yg","W"=>"Ww","g"=>"Ht"];
			//$_rpstc = ["H"=>"Ho","L"=>"Hb","i"=>"Ke","e"=>"Yg","W"=>"Ww"];
			return $_rpstc[$matches[1]]."-".$_rpstc[$matches[2]];
		}

		//check if correlation directory exists
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
			$message = "Exit: Unable to reach ".$GLOBALS["cserver"].PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}
		$corrdir = $GLOBALS["corrdir"]."/mf/mfdynf/";

		$_str = $conssh->exec($con,"cd ".$corrdir.";ls 1234/*");

		$strexpl = explode(PHP_EOL,$_str);
		array_shift($strexpl);

		foreach($strexpl as $sexpl){
			$_s1 = substr(trim($sexpl),0,1);
			$_s2 = substr(trim($sexpl),1,1);
			
			if($_s2 !== "." && !in_array($_s1.$_s2,$baseline) && ($_s1 !== $_s2) ){
				array_push($baseline,$_s1.$_s2);
			}
		}
		$baseline = array_filter($baseline);

		foreach($baseline as $bl){
			$_snr = $conssh->exec($con,"cd ".$corrdir.";fourfit -m1 -t -c cf_mfdynf -b".$bl." 1234/* 2>&1 | tee bltmp | grep 'SNR' | tr -s ' ' | cut -d ' ' -f 3");
			$_pol = $conssh->exec($con,"cd ".$corrdir.";cat tee bltmp | grep 'subgroup' | tr -s ' ' | cut -d ' ' -f 5");
			$psnr = explode(PHP_EOL,$_snr);
			$psnr = array_filter($psnr);
			$ppol = explode(PHP_EOL,$_pol);
			$ppol = array_filter($ppol);

			for($i=0;$i<count($psnr);$i++){
				$_bl = preg_replace_callback('/(.)(.)/', 'rpstc', $bl);
				echo $_bl." ".$ppol[$i]."-band: ".$psnr[$i]."<br>";
			}
		}

		$_snr = $conssh->exec($con,"cd ".$corrdir.";rm tee bltmp");
		ssh2_disconnect($con);

		unlink("../scheduling/skd/mfdynf.skd");

	}

	
	function monSEFD(){
		$db = new DbWrite;
		$dbq = $db->query("mfringe","SELECT * FROM SEFDmon where session='mfdynf'");

		$dbq[0]["id"] = $dbq[0]["mjd"] = $dbq[0]["session"] = $dbq[0]["expcode"] = $dbq[0]["source"] = "";

		$arf = array_filter($dbq[0]);

		foreach ($arf as $key => $val){
			echo $key.": ".$val."<br>";
		}
	}
	
}


?>
