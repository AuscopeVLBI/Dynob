<?php
//This script re-estimate max flux per session (flux model may be derived with some modification of code)
//assuming the a.band.snrr file is generated
//assuming bit num = 2

require_once "stats.php";
require_once "../common/dbwrite.php";

if(!isset($_COOKIE["dynob_user"])) {
	if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
		$protocol = "https://";
	}
	else {
		$protocol = "http://";
	}
	$CurPageURL = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']."?".$_SERVER['QUERY_STRING'];
	header("Location: ".$protocol.$_SERVER['HTTP_HOST']."/web/login.php?ref=".base64_encode($CurPageURL));
	die();
}

$C = new Reflux;
$C->rees();

Class Reflux{
	public $medresefd = [];
	public $allflux = [];
	public $allgha = [];
	public $allbl = [];
	public $maxflux = []; //maximum of the source in session
	public $realflux = []; //real max after solving from Model
	public $medianrealflux = []; //new (store all resolved flux for M-models and raw flux for B-models, to find the median afterwards)
	
	public function rees(){
		$exper = $_GET['exp'];
		$tmpband = strtolower($_GET["band"]); //accept "sx", "x", etc. default is sx
		$band = [];
		$bitnum = 2;
		$usesefd = "reestimated"; //accept "apriori" or "reestimated"
		//$usesefd = "apriori";

		$db = new DbWrite;
		$dbname = "dynob";
		
		if ($tmpband==null){ //sx mode
			$band = ["x","s"];
		}
		else{
			for ($i=0;$i<strlen($tmpband);$i++){
				array_push($band,$tmpband[$i]);
			}
		}
		//load the skd file and store flux
		$fluxstart = 0;
		$positionstart = 0;
		$skdflux = [];
		$skdpos = [];
		$skdsource = [];
		$skdfile = fopen("../scheduling/skd/".$exper.".skd","r");
		while(! feof($skdfile)) {
			$line = fgets($skdfile);
			if(strpos($line[0],'$')!==false){
				$fluxstart = 0;
				$positionstart = 0;
				$sourcestart = 0;
				$srcwtstart = 0;
			}
			if(strpos($line,'$FLUX')!==false){
				$fluxstart = 1;
				continue;
			}
			elseif(strpos($line,'$STATIONS')!==false){
				$positionstart = 1;
				continue;
			}
			elseif(strpos($line,'$SOURCES')!==false){
				$sourcestart = 1;
				continue;
			}
			elseif(strpos($line,'$SRCWT')!==false){
				$srcwtstart = 1; //for sources without flux information
				continue;
			}
			
			if($fluxstart == 1){
				array_push($skdflux,$line);
			}
			if($srcwtstart == 1){
				sscanf($line, "%s %f", $_1,$_2);
				//create a 1-step pseudo B-model
				array_push($skdflux,$_1." X B 0.0  0.10 13000.0");
				array_push($skdflux,$_1." S B 0.0  0.10 13000.0");
			}
			if($positionstart == 1){
				if($line[0]=="P"){
					$cpos = preg_split('/\s+/', $line);
					$skdpos[strtolower($cpos[1])] = [$cpos[3],$cpos[4],$cpos[5]];
				}
			}
			if($sourcestart == 1){
				sscanf($line,"%s %s %d %d %f %d %d %f %f %f %s %s",$name,$commonname,$rah,$ram,$ras,$ded,$dem,$des,$epc,$z,$so,$dfgl);
				if(strpos($commonname,'$')===false){
					$skdsource[$commonname] = [$rah,$ram,$ras,$ded,$dem,$des];
				}
				$skdsource[$name] = [$rah,$ram,$ras,$ded,$dem,$des];
			}
		}
		
		//load the snrr file
		foreach($band as $bnd){
			$handle = fopen("./snrr/".$exper.".a.".$bnd.".snrr", "r");
			$allresefd = [];
			$allstations = [];
			$this->medresefd[$bnd] = [];
			
			$allmjd = [];
			$allbl = [];
			$alldur = [];
			$allsource = [];
			$allelv1 = [];
			$allelv2 = [];
			$allpbase = [];
			$allsamp = [];
			$allobsnr = [];
			$allsefd1 = [];
			$allsefd2 = [];
			$allresefd1 = [];
			$allresefd2 = [];
			
			while(! feof($handle)) {
				$line = fgets($handle);
				if ($line[0]!=="#"){
					sscanf($line,"%f %s %f %s %f %s %f %f %f %f %s %f %f %f %f %f %f %f",$mjd, $source, $dur, $bl, $pbase, $pol, $samprate, $elv1, $elv2, $flux, $mod, $obsnr, $apsnr, $snrr, $sefd1, $sefd2, $resefd1, $resefd2);
				}
				else{
					continue;
				}

				if( $obsnr < 7){
					continue;
				}
				$st1 = substr(strtolower($bl),0,2);
				$st2 = substr(strtolower($bl),3,2);
				
				//store the stations
				//if (!array_key_exists($st1, $allstations)) {
				if (!in_array($st1, $allstations)) {
					array_push($allstations,$st1);
				}
				if (!in_array($st2, $allstations)) {
					array_push($allstations,$st2);
				}
				
				//store the re-estimated SEFD
				if (!array_key_exists($st1, $allresefd)) {
					$allresefd[$st1] = [];
				}
				if (!array_key_exists($st2, $allresefd)) {
					$allresefd[$st2] = [];
				}
				if($resefd1>0 && $resefd2>0){
					array_push($allresefd[$st1],$resefd1);
					array_push($allresefd[$st2],$resefd2);
				}
				
				//store vars for flux re-estimate
				array_push($allmjd,$mjd);
				array_push($allbl,$bl);
				array_push($alldur,$dur);
				array_push($allsource,$source);
				array_push($allsamp,$samprate);
				array_push($allelv1,$elv1);
				array_push($allelv2,$elv2);
				array_push($allpbase,$pbase);
				array_push($allobsnr,$obsnr);
				array_push($allsefd1,$sefd1);
				array_push($allsefd2,$sefd2);
				array_push($allresefd1,$resefd1);
				array_push($allresefd2,$resefd2);
			}
			fclose($handle);
			
			//get the median of the re-estimated SEFD (old)
			//foreach($allstations as $station){
			//	$this->medresefd[$bnd][$station] = Stats::median($allresefd[$station]);
			//}
			//get the median of the re-estimated SEFD (new, from database)
			
			foreach($allstations as $station){
				$ucst = ucfirst($station);
				$upbnd = strtoupper($bnd);
				$dbq = $db->query($dbname,"SELECT ".$ucst.$upbnd." FROM SEFDmon where session='".$exper."'");
				$this->medresefd[$bnd][$station] = $dbq[0][$ucst.$upbnd];
			}
			
			//recalculate flux. Either use above median SEFD or apriori SEFD
			if($bitnum==1){
				$eta=0.6366*0.97;
			}
			else{
				$eta=0.625*0.97; //values from VieSched++
			}
			$wavelength = ["L" => 0.3, "S" => 0.131, "C" => 0.06,
				"X" => 0.0349, "Ku" => 0.0231, "K" => 0.0134,
				"Ka" => 0.01000, "E" => 0.005, "W" => 0.00375];
			
			$this->allflux[$bnd] = [];
			$this->allpbase[$bnd] = [];
			$this->allgha[$bnd] = [];
			$this->allbl[$bnd] = [];
			$this->realflux[$bnd] = [];
			$this->medianrealflux[$bnd] = [];
			
			for ($i=0;$i<count($allobsnr);$i++){
				$st1 = substr(strtolower($allbl[$i]),0,2);
				$st2 = substr(strtolower($allbl[$i]),3,2);
				if (!array_key_exists($allsource[$i], $this->allflux[$bnd])) {
					$this->allflux[$bnd][$allsource[$i]] = [];
					$this->allpbase[$bnd][$allsource[$i]] = [];
					$this->allgha[$bnd][$allsource[$i]] = [];
					$this->allbl[$bnd][$allsource[$i]] = [];
				}
				if (!array_key_exists($allsource[$i], $this->medianrealflux[$bnd])) {
					$this->medianrealflux[$bnd][$allsource[$i]] = [[],[]]; //for M, 0 is max, 1 is empty. for B, 0 is step 1, 1 is second step
				}
				
				$numSamp = sqrt($allsamp[$i] * $alldur[$i]); //SampleRate * Scanlength
				if($usesefd=="apriori"){
					$sefd1 = $allsefd1[$i];
					$sefd2 = $allsefd2[$i];
				}
				elseif($usesefd=="reestimated"){
					$basesefd1 = round($this->medresefd[$bnd][$st1]/100)*100; //round to nearest 100
					$basesefd2 = round($this->medresefd[$bnd][$st2]/100)*100;
					
					//with elevation
					$c0 = ["x"=>["hb"=>0.954,"ke"=>0.955,"yg"=>0.970],
							"s"=>["hb"=>0.974,"ke"=>0.974,"yg"=>0.975]];
					$c1 = ["x"=>["hb"=>0.045,"ke"=>0.046,"yg"=>0.031],
							"s"=>["hb"=>0.026,"ke"=>0.028,"yg"=>0.026]];
					if (array_key_exists($st1, $c0[$bnd])) {
						$sefd1 = $basesefd1*($c0[$bnd][$st1]+($c1[$bnd][$st1]/sin(deg2rad($allelv1[$i]))));
					}
					else{
						$sefd1 = $basesefd1;
					}
					if (array_key_exists($st2, $c0[$bnd])) {
						$sefd2 = $basesefd2*($c0[$bnd][$st2]+($c1[$bnd][$st2]/sin(deg2rad($allelv2[$i]))));
					}
					else{
						$sefd2 = $basesefd2;
					}
				}
				$newflux = ($allobsnr[$i]/($eta*$numSamp))*sqrt($sefd1*$sefd2);

				$gmst = $this->tgmst($allmjd[$i]);
				//$rah,$ram,$ras,$ded,$dem,$des
				$rah = $skdsource[$allsource[$i]][0];
				$ram = $skdsource[$allsource[$i]][1];
				$ras = $skdsource[$allsource[$i]][2];
				$ded = $skdsource[$allsource[$i]][3];
				$dem = $skdsource[$allsource[$i]][4];
				$des = $skdsource[$allsource[$i]][5];
				$ra = ($rah + $ram/60 + $ras/3600) * 15 * M_PI / 180;
				if ($ded<0){
					$sign = -1;
				}
				else{
					$sign = 1;
				}
				$de = $sign * (abs($ded) + $dem/60 + $des/3600) * M_PI /180;
				$gha = $gmst-$ra;

				//new version starts here
				//calculate uv for each obs for M-models, then get realflux directly.
				//B-model uses median of flux instead of max flux.
				$flcon1 = (M_PI * M_PI)/(4 * log(2));
				$flcon2 = M_PI / (3600.0 * 180.0 * 1000.0); //convert from milliarc second to radian
				//$gha = $this->allgha[$bnd][$sourcekey][$maxind];
				$blx = $skdpos[$st1][0]-$skdpos[$st2][0];
				$bly = $skdpos[$st1][1]-$skdpos[$st2][1];
				$blz = $skdpos[$st1][2]-$skdpos[$st2][2];
				$u = $blx * sin($gha) + $bly * cos($gha);
				$v = $blz * cos($de) + sin($de) * (-$blx * cos($gha) + $bly * sin($gha));

				for ($j=0;$j<count($skdflux);$j++){
					$skdfl = preg_split('/\s+/', $skdflux[$j]);
					if(stripos($skdfl[1],$bnd)!==false && stripos($skdfl[0],$allsource[$i])!==false){

						$_pbase = sqrt($u*$u + $v*$v);
						$u = $u/$wavelength[ucfirst($bnd)];
						$v = $v/$wavelength[ucfirst($bnd)];

						//for the M-models
						/*
						if(stripos($skdfl[2],"M")!==false){

							$majax = $skdfl[4]*$flcon2;
							$ratio = $skdfl[5];
							$pa = $skdfl[6] * M_PI/180;

							$ucospa = $u * cos($pa);
							$usinpa = $u * sin($pa);
							$vcospa = $v * cos($pa);
							$vsinpa = $v * sin($pa);
							$arg1 = ($vcospa + $usinpa) * ($vcospa + $usinpa);
							$arg2 = ($ucospa - $vsinpa) * ($ucospa - $vsinpa);
							$l = sqrt($arg1 + ($ratio*$ratio) * $arg2);

							$up = (M_PI * $majax * $l) * (M_PI * $majax * $l);

							$realflux = $newflux/(exp(-($up)/(4*log(2))));
							//$realflux = $themax/(exp(-($up)/(4*log(2))));
							//$this->realflux[$bnd][$allsource[$i]] = $realflux;
							array_push($this->medianrealflux[$bnd][$allsource[$i]][0],$realflux);

						}
						else{
						*/
						//for monitor everything as B-models
						if(strtolower($bnd) == "x"){
							//if(sqrt($u*$u + $v*$v)/1000000 >= 150){
							if($_pbase/1000 >= 5240){ //in km, good for develop into B-models
								array_push($this->medianrealflux[$bnd][$allsource[$i]][1],$newflux);
							}
							else{
								array_push($this->medianrealflux[$bnd][$allsource[$i]][0],$newflux);
							}
						}
						elseif(strtolower($bnd) == "s"){
							//if(sqrt($u*$u + $v*$v)/1000000 >= 40){
							if($_pbase/1000 >= 5240){
								array_push($this->medianrealflux[$bnd][$allsource[$i]][1],$newflux);
							}
							else{
								array_push($this->medianrealflux[$bnd][$allsource[$i]][0],$newflux);
							}
						}

						//}
					}
				}

				array_push($this->allflux[$bnd][$allsource[$i]],$newflux);
				array_push($this->allpbase[$bnd][$allsource[$i]],$allpbase[$i]);
				array_push($this->allbl[$bnd][$allsource[$i]],$allbl[$i]);
				array_push($this->allgha[$bnd][$allsource[$i]],$gha);
			}

			foreach($this->medianrealflux[$bnd] as $_source => $_fluxarr){
				//monitor the first array only, maybe in the future both?
				$this->realflux[$bnd][$_source] = Stats::median($_fluxarr[0]);
			}

			//mjd to UTC
			$_umjd = ($allmjd[0] - 0.5);
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

			//add to database
			$dbcol = ["UTC","mjd","session"];
			$dbval = [jdtogregorian(2400000+$allmjd[0])." ".$utime,$allmjd[0],$exper];
			if(stripos($bnd,"x")!==false || stripos($bnd,"s")!==false){
				$dbq = $db->query($dbname,"SELECT * FROM Fluxmon".$bnd." where id='1'");
				foreach($this->realflux[$bnd] as $sourcename=>$realfl){
					$sourcename = str_replace('-','m',$sourcename);
					$sourcename = str_replace('+','p',$sourcename);
					if(!array_key_exists($sourcename,$dbq[0])){
						//create columns
						$db->addColtoTab($dbname,"Fluxmon".$bnd,$sourcename,"float");
					}
					array_push($dbcol,$sourcename);
					array_push($dbval,round($realfl,2));
				}
				
				$dbq = $db->query($dbname,"SELECT * FROM Fluxmon".$bnd." where session='".$exper."'");
				if($dbq=="null"){
					$db->insert($dbname,"Fluxmon".$bnd,$dbcol,$dbval);
				}
				else{
					$db->update($dbname,"Fluxmon".$bnd,$dbcol,$dbval,$exper);
				}
			}

		}
	}
	
	function tgmst($mjd){
		$T = (floor($mjd) - 51544.5) / 36525;
		$hhs = ($mjd - floor($mjd)) * 24;
		// time seconds
		$p1 = 6*3600 + 41*60 + 50.54841;
		$p2 = 8640184.812866;
		$p3 = 0.093104;
		$theta = ($p1 + $p2*$T + pow($p3*$T,2))/ 3600;  // hours

		$theta = fmod($theta,24);

		$theta = $theta * 15 * M_PI / 180;         // Sidereal time Greenwich in radians at 0 UT
		$st = $hhs*366.2422/365.2422*15* M_PI /180;  // sidereal time since midnight in radians
		$theta1 = $theta + $st;                   // sidereal time Greenwich in radians at the epoch
		$gmst = fmod($theta1,(2*M_PI));

		return $gmst;
	}

	
}

?>