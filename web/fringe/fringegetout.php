<?php
//this script is to genereate the output for the fringe check sessions

require_once "../../common/SSHcon.php";
require_once "../../common/global.php";

Class Fringeoutput{

	function fringeout($session){

		$bands = preg_split('/\s+/', trim(shell_exec('ls ../../pmonitor/snrr | grep '.$session.' | cut -d "." -f 3')));

		$sefdrees = []; //re-estimated
		$sefdtsys = []; //from tsys (log file)
		$sefdsched = []; // scheduled
		$stations = [];
		$source = "";

		for ($i=0;$i<count($bands);$i++){
			//get the .snrr file
			$snrrfile = "../../pmonitor/snrr/".$session.".a.".$bands[$i].".snrr";
			$spl = new SplFileObject($snrrfile);

			while (!$spl->eof()) {
				$line = $spl->fgets();
				if(substr($line,0,1)!=="#" && trim($line)!==""){
					sscanf($line, "%f %s %f %s %f %s %d %f %f %f %s %f %f %f %f %f %f %f", $_mjd,$_sou,$_dur,$_bl,$_pbase,$_pol,$_samprate,$_elv1,$_elv2,$_flux,$_mod,$_obsnr,$_apsnr,$_snrr,$_sefd1,$_sefd2,$_rees1,$_rees2);

					$st1 = explode("-",trim($_bl))[0];
					$st2 = explode("-",trim($_bl))[1];
					array_push($stations,$st1);
					array_push($stations,$st2);

					$sefdrees[$st1][$bands[$i]] = $_rees1;
					$sefdrees[$st2][$bands[$i]] = $_rees2;
					$sefdsched[$st1][$bands[$i]] = $_sefd1;
					$sefdsched[$st2][$bands[$i]] = $_sefd2;
					$source = $_sou;
					
				}
			}

			$stations = array_unique($stations);
		}

		//generate the fourfit plots
		$unln = shell_exec("rm fringeoutplot/*");

		$stcodes = $GLOBALS["stcodes"];
		$vgosstations = $GLOBALS["vgosstations"];
		$conssh = new ConnectSSH;
		if(!$con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"])){
			die("Unable to reach ".$GLOBALS["cserver"]);
		}
		$stsinvolve = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls ~/correlations/mf/".$session."/1234/*")));
		array_shift($stsinvolve);

		$SNR = []; //bl>band>pol
		foreach ($stsinvolve as $sts){
			$stc1 = substr($sts,0,1);
			$stc2 = substr($sts,1,1);
			$stc1key = strtolower(array_keys($stcodes,$stc1)[0]);
			$stc2key = strtolower(array_keys($stcodes,$stc2)[0]);
			$pol = [];
			
			if($stc2!=='.' && strpos($sts,'..')!==false && $stc1!==$stc2 ){
				if(in_array($stc1key,$vgosstations)){
					if(in_array($stc2key,$vgosstations)){
						$pol = ["RR","LL","RL","LR"];
					}
					else{
						//mixed-mode baseline
						//first station is vgos:				
						$pol = ["RR","LR"];
					}
						
				}
				else{
					if(in_array($stc2key,$vgosstations)){
						//mixed-mode baseline
						//second station is vgos:
						$pol = ["RR","RL"];
					}
					else{
						//legacy baseline
						$pol = ["RR"];
					}
				}
				foreach($pol as $pl){
					for ($i=0;$i<count($bands);$i++){
						$_SNR = $conssh->exec($con,"cd correlations/mf/".$session.'; parallel fourfit -m1 -t -d diskfile:'.$stc1.$stc2.$pl.strtoupper($bands[$i]).'.ps -b'.$stc1.$stc2.':'.strtoupper($bands[$i]).' -P'.$pl.' -c cf_'.$session.' ::: 1234/* 2>&1 | tr -s " " | grep "SNR" | cut -d " " -f 3');
						$SNR[$stc1.$stc2][$bands[$i]][$pl] = $_SNR;
						//rename ps file to jpeg
						$fp = $conssh->exec($con,"cd ~/correlations/mf/".$session."/; gs -sDEVICE=jpeg -dJPEGQ=50 -dNOPAUSE -dBATCH -dSAFER -r60 -sOutputFile=".$stc1.$stc2.$pl.strtoupper($bands[$i]).".jpg ".$stc1.$stc2.$pl.strtoupper($bands[$i]).".ps");
						$fs = ssh2_scp_recv($con, 'correlations/mf/'.$session.'/'.$stc1.$stc2.$pl.strtoupper($bands[$i]).'.jpg', $_SERVER['DOCUMENT_ROOT']."web/fringe/fringeoutplot/".$stc1.$stc2.$pl.strtoupper($bands[$i]).".jpg");
					}
				}
			}
		}

		//$stfintsys = [];
		
		//$stfintsys["Hb"] = $this->calfromtsys("Hb",$session);

		$stfintsys = [];
		foreach ($stations as $st){
			$stfintsys[$st] = $this->calfromtsys($st,$session);
		}
		$final = [$SNR,$stfintsys,$sefdrees,$sefdsched,$source];

		return $final;
		exit();

	}

	function calfromtsys($station,$session){
		$k = 1.380649e-23;//boltzman constant
		$antdias = $GLOBALS["antdias"]; //antenna diameter
		$area = 0.625 * M_PI * (($antdias[$station]/2)*($antdias[$station]/2)); //0.625 is the rough antenna efficiency
		$station = strtolower($station);

		//get tsys from log
		$tsyslines = shell_exec("grep '/tsys/' ../../tmp/".$session."/".$station."buffer.log");
		preg_match_all('/(\d*|[a-h])[ul],(\d*).(\d)/', $tsyslines, $tsysarr);
		$tsysarr = $tsysarr[0];
		$tsys = [];
		for ($i=0;$i<count($tsysarr);$i++){
			$_ifchan = explode(",",$tsysarr[$i]);
			if($_ifchan[1]>0){
				if(!isset($tsys[$_ifchan[0]])){
					$tsys[$_ifchan[0]] = [];
				}
				array_push($tsys[$_ifchan[0]],$_ifchan[1]);
			}
		}
		ksort($tsys);
		if(count($tsys)<=0){
			return "no tsys information on the log file!";
			exit();
		}
		$avtsys = [];
		foreach($tsys as $_ifchan=>$_valarr){
			if(stripos($_ifchan,"u")!==false){
				$avtsys["usb"][substr(trim($_ifchan),0,strlen(trim($_ifchan))-1)] = array_sum($_valarr)/count($_valarr);
			}
			else{
				$avtsys["lsb"][substr(trim($_ifchan),0,strlen(trim($_ifchan))-1)] = array_sum($_valarr)/count($_valarr);
			}
		}

		//calculate sefd, corresponding polarisation, frequency and sideband for the IF
		$spfs = []; //corresponding polarisation, frequency and sideband for the IF
		$unknownbbc = [];
		$loarr = [];
		$bbcfreqarr = [];

		if(count($avtsys["usb"])>=count($avtsys["lsb"])){
			//add the lsb value
			foreach($avtsys["lsb"] as $_ifchan => $_valarr){
				$newval = ($avtsys["usb"][$_ifchan] + $_valarr)/2;
				$avtsys["usb"][$_ifchan] = $newval;
			}
			$_recif = count($avtsys["usb"]);
		}
		else{
			//add the usb value
			foreach($avtsys["usb"] as $_ifchan => $_valarr){
				$newval = ($avtsys["lsb"][$_ifchan] + $_valarr)/2;
				$avtsys["lsb"][$_ifchan] = $newval;
			}
			$_recif = count($avtsys["lsb"]);
		}

		if($_recif<=16){ //assume S/X telescopes
			//group the tsys by LO
			foreach($avtsys["usb"] as $_ifchan => $_valarr){
				$bbcline = shell_exec("grep -m 1 'bbc".str_pad(hexdec($_ifchan),2,"0",STR_PAD_LEFT)."=' ../../tmp/".$session."/".$station."buffer.log | head -1");
				if(trim($bbcline)==""){ //if no correponding bbc found (could be S-band splitted)
					array_push($unknownbbc,'bbc'.$_ifchan);
					continue;
				}
				$_bbcfreq = trim(explode("=",$bbcline)[1]);
				$_bbcfreq = explode(",",$_bbcfreq);
				$bbcfreq = $_bbcfreq[0];
				$bbclo = $_bbcfreq[1];
				array_push($loarr,$bbclo);
				array_push($bbcfreqarr,$bbcfreq);
			}
			$loocc = array_count_values($loarr);
		}
		else{
			//group the tsys by LO
			foreach($avtsys["usb"] as $_ifchan => $_valarr){
				$bbcline = shell_exec("grep -m 1 'bbc".$_ifchan."=' ../../tmp/".$session."/".$station."buffer.log | head -1");
				if(trim($bbcline)==""){ //if no correponding bbc found (could be S-band splitted)
					array_push($unknownbbc,'bbc'.$_ifchan);
					continue;
				}
				$_bbcfreq = trim(explode("=",$bbcline)[1]);
				$_bbcfreq = explode(",",$_bbcfreq);
				$bbcfreq = $_bbcfreq[0];
				$bbclo = $_bbcfreq[1];
				array_push($loarr,$bbclo);
				array_push($bbcfreqarr,$bbcfreq);
			}
			$loocc = array_count_values($loarr);
		}

		//actions
		$cummu = 0;
		foreach($loocc as $_lo =>$_num){
			$loline = shell_exec("grep -m 1 'lo=lo".$_lo."' ../../tmp/".$session."/".$station."buffer.log | head -1");
			$_lolo = trim(explode("=",$loline)[1]);
			$explo = explode(",",$_lolo);
			$lolo = $explo[0];
			$lofreq = $explo[1];
			$losideband = $explo[2];
			$lopol = $explo[3];

			//change lopol display for vgos stations
			if(in_array($station,$GLOBALS["vgosstations"])){
				$lopol = preg_replace('/rcp/i', 'X', $lopol);
				$lopol = preg_replace('/lcp/i', 'Y', $lopol);
			}

			//calculate the tsys for each IF
			$_aband = array_slice($avtsys[$losideband], $cummu, $_num);
			$_aband = array_sum($_aband)/count($_aband);
			$_sefd = ((2*$k*$_aband)/$area)/1e-26;

			//corresponding sefd, polarisation, frequency (lowest) and sideband for the IF
			if(stripos($losideband,"usb")!==false){
				$freq = $lofreq + $bbcfreqarr[$cummu];
			}
			else{
				$freq = $lofreq - $bbcfreqarr[$cummu];
			}
			$spfs[$_lo]  = [$_sefd,$lopol,$freq,$losideband];

			//add cummulative
			$cummu = $cummu + $_num;
		}

		return [$spfs,$unknownbbc]; //sefd, corresponding polarisation, frequency and sideband for the IF

	}
}

$C = new Fringeoutput;
$frout = $C->fringeout($_GET["exp"]);

?>