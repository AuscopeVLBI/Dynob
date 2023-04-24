<?php
//this script links all class files under pmonitor
//usage: monitor.php?exp=[expname]

require_once "alist2a.php";
require_once "../common/dbwrite.php";
require_once "a2snr.php";
require_once "../common/global.php";
require_once "stats.php";
require_once "tsyssefd.php";


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

$db = new DbWrite;

$tsyssefd = new Tsyssefd;

$expname = $_GET["exp"];
$exptype = substr($expname,0,2);

if($exptype == "mf"){
	$expcode = "1234";
	$dbname = "mfringe";
}
else{
	$dbq = $db->query("dynob",'SELECT expcode FROM Correlation WHERE session="'.$expname.'"');
	$expcode = $dbq[0]["expcode"];
	$dbname = "dynob";
}

if(!$_GET["snrr"]){
	$al = new Alist2a;
	$al->createAlist("/home/observer/correlations/$exptype/$expname/",$expcode,$expname);
	$al->tranxa("/home/observer/correlations/$exptype/$expname/",$expcode,$expname);
	include("runa2snr.php");
}
else{
	if($GLOBALS["cmode"]!=="vgos"){
		$band = ["x","s"];
	}
	else{
		$band = ["x"];
	}

	$goodsources = $GLOBALS["goodsources"];
	

	$file = "snrr/$expname.a.".$band[0].".snrr";
	$spl = new SplFileObject($file);
	$spl->seek(1); //seek second line of file
	$line = $spl->current();
	sscanf($line, "%f %s %f %s %f %s %d %f %f %f %s %f %f %f %f %f %f %f", $_mjd,$_sou,$_dur,$_bl,$_pbase,$_pol,$_samprate,$_elv1,$_elv2,$_flux,$_mod,$_obsnr,$_apsnr,$_snrr,$_sefd1,$_sefd2,$_rees1,$_rees2);
	
	//mjd to UTC
	$_umjd = ($_mjd - 0.5);
	if(strpos($_umjd,".")!==false){
		$_utime = $_umjd - substr($_umjd,0,strpos($_umjd,"."));
		$_uh = $_utime * 24;
		$uh = floor($_uh);
		if(strpos($_uh,".")!==false){
			$_um = $_uh - substr($_uh,0,strpos($_uh,"."));
			$_um = $_um * 60;
			$um = floor($_um);
			if($um>=60){
				$uh = $uh + 1;
				$um = $um - 60;
			}
			if(strpos($_um,".")!==false){
				$_us = $_um - substr($_um,0,strpos($_um,"."));
				$_us = $_us * 60;
				$us = round($_us);
				if($us>=60){
					$um = $um + 1;
					$us = $us - 60;
				}
			}
			else{
				$us = 0;
			}
		}
		else{
			$um = 0;
		}
	}
	else{
		$_uh = 0;
	}
	$utime = str_pad($uh,2,"0",STR_PAD_LEFT).":".str_pad($um,2,"0",STR_PAD_LEFT).":".str_pad($us,2,"0",STR_PAD_LEFT);
	
	//cols and values to wrote to db
	if($exptype=="mf"){
		$dbcol = ["UTC","mjd","session","expcode","source"];
		$dbval = [jdtogregorian(2400000+$_mjd)." ".$utime,$_mjd,$expname,$expcode,$_sou];
	}
	else{
		$dbcol = ["UTC","mjd","session","expcode"];
		$dbval = [jdtogregorian(2400000+$_mjd)." ".$utime,$_mjd,$expname,$expcode];
	}

	foreach($band as $bnd){
		$rees = [];
		$snrrfile = fopen("snrr/$expname.a.".$bnd.".snrr", "r");
		while (!feof($snrrfile)) {
			$line = fgets($snrrfile);
			if($line[0] !== "#"){
				
				sscanf($line, "%f %s %f %s %f %s %d %f %f %f %s %f %f %f %f %f %f %f", $_mjd,$_sou,$_dur,$_bl,$_pbase,$_pol,$_samprate,$_elv1,$_elv2,$_flux,$_mod,$_obsnr,$_apsnr,$_snrr,$_sefd1,$_sefd2,$_rees1,$_rees2);
				$st1 = substr($_bl,0,2);
				$st2 = substr($_bl,3,2);

				if($_bl!==false){
					if(!isset($rees[$st1])){
						$rees[$st1] = [];
					}
					if(!isset($rees[$st2])){
						$rees[$st2] = [];
					}
					if($exptype !== "mf"){
						if (!in_array($_sou,$goodsources)){
							continue;
						}
					}
					if($_elv1 >= 0){
						array_push($rees[$st1],$_rees1);
					}
					if($_elv2 >= 0){
						array_push($rees[$st2],$_rees2);
					}
					//array_push($rees[$st1],$_rees1);
					//array_push($rees[$st2],$_rees2);
				}
				//$_bl = "";
			}
		}
		fclose($snrrfile);
		
		foreach(array_keys($rees) as $ak){			
			$rees[$ak] = array_filter($rees[$ak]);
			$rees[$ak] = array_unique($rees[$ak]);
			$_rmout = Stats::remove_outliers($rees[$ak]); //val,outliers
			$rees[$ak] = $_rmout[0];
			//$rees[$ak] = Stats::remove_outliers($rees[$ak]);
			$rees[$ak] = array_values($rees[$ak]);
			$median = Stats::median($rees[$ak]);
			
			if(count($_rmout[1])>=10){ // if at least 10 scans are outliers
				//outliers of outliers
				$_rmoutout = Stats::remove_outliers($_rmout[1]); //val,outliers
				//get median of outliers
				$_rmoutout[0] = array_values($_rmoutout[0]);
				$outmedian = Stats::median($_rmoutout[0]);

				//check tsys sefd for the station, is $median or $outmedian closer to tsys sefd?
				$tsyscres = $tsyssefd->calfromtsys($ak,$expname);

				if(is_array($tsyscres)){
					//rough average of all IF sefd
					$_tsyssefd = [];
					foreach($tsyscres[0] as $_if=>$_arr){
						array_push($_tsyssefd,$_arr[0]);
					}
					$avtsyssefd = Stats::mean($_tsyssefd);

					if(abs($outmedian-$avtsyssefd) < abs($median-$avtsyssefd)){
						//replace $median with $outmedian if $outmedian is closer to tsyssefd
						$median = $outmedian;
					}
				}
			}
			
			array_push($dbval,$median);
			array_push($dbcol,$ak.strtoupper($bnd));
			$dbq = $db->query($dbname,"SELECT * FROM SEFDmon where id='1'");
			if(!array_key_exists($ak.strtoupper($bnd),$dbq[0])){
				//create columns
				$db->addColtoTab($dbname,"SEFDmon",$ak.strtoupper($bnd),"float");
			}
		}
		
		//print_r($dbval);
		//die();
		
	}
	
	//add to DB
	
	$dbq = $db->query($dbname,"SELECT * FROM SEFDmon where session='".$expname."'");
	if($dbq=="null"){
		$db->insert($dbname,"SEFDmon",$dbcol,$dbval);
	}
	else{
		$db->update($dbname,"SEFDmon",$dbcol,$dbval,$expname);
	}
	

	//unlink afile
	unlink("afile/".$expname.".a.x");
	unlink("afile/".$expname.".a.s");

	echo "<p>monitored</p>";
}


?>
