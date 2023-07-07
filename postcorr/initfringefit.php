<?php
//for mixed-mode and S/X only

require_once "../common/SSHcon.php";
require_once "../common/global.php";
require_once "findrefscan.php";
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

$refStationArr = $GLOBALS["refStationArr"]; //order by priority
//$refsource = ["1921-293","0454-234","0727-115"]; //order by priority
$refsource = $GLOBALS["manualpcalsources"]; //order by priority
$minSNR = $GLOBALS["minSNR"]; //0 is off/do not check minimum SNR (faster)

$FR = new FindRef;
$refF = $FR->getRef($refsource,$refStationArr,$minSNR);

//$refF = $FR->getRef($refsource,$refStation,$minSNR);
//$refF = ["310-0506",["e","W","L","i"],"8212.99","2200.99","e"];
//$refF = ["308-1900",["e","g","W","L","i"],"8212.99","2200.99","e"];
$refscan = $refF[0];
$stations = $refF[1];
$expname = $_GET["exp"];
$exptype = substr($expname,0,2);
$exptype1 = substr($expname,0,1);
$rFreqX = $refF[2];
$rFreqS = $refF[3];
$refStation = trim($refF[4]);


if($exptype1 == "z"){//si
	$exptype = "si";
}

//check mode
if($exptype == "mf"){
	$realtimedb = [];
	$dbfile = new SplFileObject($_SERVER['DOCUMENT_ROOT']."web/fringe/realtime.db");
	$dbfile->seek(0);
	while (!$dbfile->eof()) {
		$line = $dbfile->fgets();
		if(strlen($line)>0){
			$lineexp = explode('=',$line);
			$realtimedb[trim($lineexp[0])]=trim($lineexp[1]);
		}
	}
	$recdir = new SplFileObject($_SERVER['DOCUMENT_ROOT']."scheduling/fringeout/".$realtimedb['fringe_scheddir']."/expinfo.txt");
	$recdir->seek(0);
	while (!$recdir->eof()) {
		$line = $recdir->fgets();
		if(strlen($line)>0){
			if (stripos($line,"Mode")!==false){
				$cmode = trim(explode(":", $line)[1]);
			}
		}
	}
}
else{
	$cmode = $GLOBALS["cmode"];
}
//$cmode = "mixed";

$fullfreqs = []; //full frequency used

$stcodes = $GLOBALS["stcodes"];
$db = new DbWrite;
if($exptype!=="mf"){
	$dbq = $db->query("dynob",'SELECT expcode FROM Correlation WHERE session="'.$expname.'"');
	$expcode = $dbq[0]["expcode"];
}
else{
	$expcode = "1234";
}

if($cmode == "mixed"){
	$cftemplate = "cf_template";
	$rpol = "r";
	$lpol = "l";
}
else{
	$cftemplate = "cf_template_vgos";
	$rpol = "x";
	$lpol = "y";
}

copy($cftemplate,"cf_".$expname);
$cffile = fopen($cftemplate,"r");

$arrline = [];
$freqs = [];

//get frequencies from v2d's zoom
if($cmode == "mixed"){
	$corfreqs = [];
	$v2dfile = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$expname."/buffer.v2d","r");
	while (!feof($v2dfile)){
		$line = fgets($v2dfile);
		if(strpos($line,"addZoomFreq")!==false && strpos($line,'#')===false){
			array_push($corfreqs,(float)(substr($line,strpos($line,'@')+1,strpos($line,'/')-strpos($line,'@')-1)));
		}
	}
	sort($corfreqs);
}

while (!feof($cffile)){
	$line = fgets($cffile);
	$line = preg_replace('/{refscan}/',$refscan,$line);
	$line = preg_replace('/{reffreqx}/',$rFreqX,$line);
	$line = preg_replace('/{reffreqs}/',$rFreqS,$line);
	$line = preg_replace('/{refstation}/',$refStation,$line);

	//array_push($arrline,$line);
	if($cmode == "mixed"){
		if(stripos($line,"if f_group ")!==false){
			preg_match('/if f_group (.)/', $line, $group);
		}
		if(stripos($line,"chan_ids")!==false){
			$line = "chan_ids abcdefghijklmn ".implode(" ",$corfreqs).PHP_EOL;
		}

	}
	else{
		$group = ["vgos","X"];
	}
	if(stripos($line,"freqs ")!==false){
		$freqs[$group[1]]  = trim(substr($line,stripos($line, "freqs")+6));
	}
	array_push($arrline,$line);
}
fclose($cffile);

$conssh = new ConnectSSH;
if(!$con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"])){
	die("Unable to reach ".$GLOBALS["cserver"]);
}

//needed for vgos
$stsinvolve = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls correlations/".$exptype."/".$expname.'/'.$expcode.'/*')));
array_shift($stsinvolve);
$blarr = [];
foreach ($stsinvolve as $sts){
	$stc1 = substr($sts,0,1);
	$stc2 = substr($sts,1,1);
	if(($stc1 !== $stc2) && $stc2!=='.' ){
		array_push($blarr,$stc1.$stc2);
	}
}

if($cmode=="mixed"){
	$band=["X","S"];
}
else{
	$band=["X"];
}

foreach ($stations as $rst){
	if ($rst !== $refStation){
		foreach($band as $bnd){
			//find the station character order
			$_listbl = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls correlations/".$exptype."/".$expname."/".$expcode."/".$refscan)));
			array_shift($_listbl);

			foreach($_listbl as $_fbl){
				$_fbl = trim($_fbl);
				if(strpos($_fbl,$rst.$refStation)!==false || strpos($_fbl,$refStation.$rst)!==false){
					$fbl = substr($_fbl,0,2);
				}
			}

			if($cmode=="mixed"){
				array_push($arrline,"if station $rst and f_group $bnd".PHP_EOL);
			}
			else{
				array_push($arrline,"if station $rst".PHP_EOL);
			}
			array_push($arrline,"  pc_mode manual".PHP_EOL);
			//set delay_offs
			$deloffr = [];
			$deloffl = [];
			$deloff = [];
			$freqtmp = explode(" ",$freqs[$bnd]);
			$usedfreqs = [];

			for($fi=0;$fi<count(array_filter($freqtmp));$fi++){

				$indioff = trim($conssh->exec($con,"cd ~/correlations/".$exptype."/".$expname."; fourfit -m1 -t -b".$fbl." $expcode/".$refscan." set pc_mode manual freqs ".$freqtmp[$fi]." 2>&1 | tee tmp.ff | grep 'at sbd' | tr -s ' ' | cut -d ' ' -f 8  | tr '\\n' ' ' "));
				$SNR = trim($conssh->exec($con,"cd ~/correlations/".$exptype."/".$expname."; cat tmp.ff | grep 'SNR' |  tr -s ' ' | cut -d ' ' -f 3 | tr '\\n' ' '"));
				$inexpl = explode(" ",$indioff);
				$inexsnr = explode(" ",$SNR);
				
				if (count($inexpl)<=1){					
					if( !in_array(trim($rst).trim($refStation),$blarr) ){ //meaning, refStation is at 'front', use negative values
						array_push($deloff,$inexpl[0]*-1000);
					}
					else{
						array_push($deloff,$inexpl[0]*1000);
					}
					array_push($usedfreqs,$freqtmp[$fi]);
				}
				else{
					if(!in_array(trim($rst).trim($refStation),$blarr) ){ //meaning, refStation is at 'front', use negative values
						array_push($deloffr,$inexpl[0]*-1000);
						array_push($deloffl,$inexpl[1]*-1000);
					}
					else{
						array_push($deloffr,$inexpl[0]*1000);
						array_push($deloffl,$inexpl[1]*1000);
					}
					array_push($usedfreqs,$freqtmp[$fi]);
				}
			}
			if(count($usedfreqs)>0){
				array_push($arrline,"  freqs ".implode(' ',$usedfreqs).PHP_EOL);
				if(count($deloff)>0){
					array_push($arrline,"  delay_offs ".implode('',$usedfreqs)." ".implode(' ',$deloff).PHP_EOL);
					array_push($arrline,"*{".$rst.$bnd."_pc_phases}".PHP_EOL);
				}
				else{
					array_push($arrline,"  delay_offs_".$rpol." ".implode('',$usedfreqs)." ".implode(' ',$deloffr).PHP_EOL);
					array_push($arrline,"  delay_offs_".$lpol." ".implode('',$usedfreqs)." ".implode(' ',$deloffl).PHP_EOL);
					array_push($arrline,"*{".$rst.$bnd."_pc_phases_".$rpol."}".PHP_EOL);
					array_push($arrline,"*{".$rst.$bnd."_pc_phases_".$lpol."}".PHP_EOL);
				}
				array_push($arrline,PHP_EOL);
				
			}
		}
	}
}

//echo $conssh->exec($con,"cd correlations/".$exptype."/".$expname."; rm tmp.ff"));
$cfexp = fopen('cf_'.$expname,"w");
fwrite($cfexp,implode("",$arrline));
fclose($cfexp);

//upload to correlator server
ssh2_scp_send($con,'cf_'.$expname, "correlations/".$exptype."/".$expname."/cf_".$expname, 0755);

foreach ($stations as $rst){
	if ($rst !== $refStation){
		foreach($band as $bnd){
			//find the station character order
			$_listbl = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls correlations/".$exptype."/".$expname."/".$expcode."/".$refscan)));
			array_shift($_listbl);
			foreach($_listbl as $_fbl){
				$_fbl = trim($_fbl);
				if(strpos($_fbl,$rst.$refStation)!==false || strpos($_fbl,$refStation.$rst)!==false){
					$fbl = substr($_fbl,0,2);
				}
			}

			//set pc_phases
			$indiphase = trim($conssh->exec($con,"cd correlations/".$exptype."/".$expname."; fourfit -m1 -t -c cf_$expname -b".$fbl.":".$bnd." $expcode/".$refscan." 2>&1  | grep 'pc_phases'"));
			$inexpl = explode(PHP_EOL,$indiphase);
			$pclinearr = [];
			foreach($inexpl as $_expline){
				$_line = preg_split('/[\s]+/',trim($_expline));
				$_valarr = array_slice($_line,2);
				$_frarr = array_slice($_line,0,2);
				if(!in_array(trim($rst).trim($refStation),$blarr) ){ //meaning, refStation is at 'front', use negative values){
					$_valarr = array_map(function($ev) { return $ev * -1; }, $_valarr);
				}
				$phasesline = implode(" ",$_frarr)." ".implode(" ",$_valarr);
				array_push($pclinearr,$phasesline);
			}

			if (count($inexpl)<=1){
				$key = array_search("*{".$rst.$bnd."_pc_phases}".PHP_EOL,$arrline);
				if($key!==false){
					$arrline[$key] = "  ".$pclinearr[0].PHP_EOL;
					//array_push($arrline,"  ".$inexpl[0].PHP_EOL);
				}
			}
			else{
				$pcphaser = preg_replace('/pc_phases/','pc_phases_'.$rpol.'',$pclinearr[0]);
				$pcphasel = preg_replace('/pc_phases/','pc_phases_'.$lpol.'',$pclinearr[1]);
				//array_push($arrline,"  ".$pcphaser.PHP_EOL);
				//array_push($arrline,"  ".$pcphasel.PHP_EOL);
				$key = array_search("*{".$rst.$bnd."_pc_phases_".$rpol."}".PHP_EOL,$arrline);
				if($key!==false){
					$arrline[$key] = "  ".$pcphaser.PHP_EOL;
				}
				$key = array_search("*{".$rst.$bnd."_pc_phases_".$lpol."}".PHP_EOL,$arrline);
				if($key!==false){
					$arrline[$key] = "  ".$pcphasel.PHP_EOL;
				}
			}
		}
	}
}

$cfexp = fopen('cf_'.$expname,"w");
fwrite($cfexp,implode("",$arrline));
fclose($cfexp);

//finally, uncomment the ionosphere for vgos
$arrline = [];
if($cmode == "vgos"){
	$cfexpvg = new SplFileObject('cf_'.$expname."b", "w");
	$cfexpvgr = new SplFileObject('cf_'.$expname, "r");
	while (!$cfexpvgr->eof()) {
		$line = $cfexpvgr->fgets();
		if(stripos($line,'ion_npts')!==false){
			$line = preg_replace('/\*ion_npts/','ion_npts',$line);
		}
		$cfexpvg->fwrite($line);
	}
}

//upload to correlator server
if($cmode == "vgos"){
	ssh2_scp_send($con,'cf_'.$expname."b", "correlations/".$exptype."/".$expname."/cf_".$expname, 0755);
	unlink('cf_'.$expname);
	rename('cf_'.$expname."b",'cf_'.$expname);
}
else{
	ssh2_scp_send($con,'cf_'.$expname, "correlations/".$exptype."/".$expname."/cf_".$expname, 0755);
}

/*
//-----check error code G after combining polarisations on refscan-----------
//get weak channel value
foreach($arrline as $value) {
	if(preg_match("/\bweak_channel\b/i", $value)){
		$weakchan = preg_split('/[\s]+/', $value);
		$weakchan = array_filter($weakchan);
		$weakchan = array_values($weakchan);
		$weakchan = $weakchan[1];
		break;
	}
}

if (is_numeric($weakchan)){
	$stsinvolve = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls ~/correlations/".$exptype."/".$expname.'/'.$expcode.'/'.$refscan)));
	array_shift($stsinvolve);

	foreach ($stsinvolve as $sts){
		$stc1 = substr($sts,0,1);
		$stc2 = substr($sts,1,1);
		$stc1key = strtolower(array_keys($stcodes,$stc1)[0]);
		$stc2key = strtolower(array_keys($stcodes,$stc2)[0]);
		$pol = "";

		if ( ($stc1 == $refStation || $stc2 == $refStation) && ($stc1!==$stc2) ){
			if ($stc1 == $refStation){
				$otherst = $stc2;
			}
			else{
				$otherst = $stc1;
			}

			if($stc2!=='.' && strpos($sts,'..')!==false ){
				if(in_array($stc1key,$vgosstations)){
					if(in_array($stc2key,$vgosstations)){
						//vgos baseline
						$pol = "I";
					}
					else{
						//mixed-mode baseline
						$pol = "RR+LR";
					}
				}
				else{
					if(in_array($stc2key,$vgosstations)){
						//mixed-mode baseline
						$pol = "RR+LR";
					}
					else{
						//legacy baseline
						$pol = "RR";
					}
				}
				
				foreach($band as $bnd){
					$fringeq = $conssh->exec($con,"cd ~/correlations/".$exptype."/".$expname.'; parallel fourfit -t -m0 -b'.$stc1.$stc2.':'.$bnd.' -P'.$pol.' -c cf_'.$expname.' ::: '.$expcode.'/'.$refscan." 2>&1 | grep 'fringe\[' | cut -d ' ' -f 3" );
					$fringeq = preg_split('/[\s]+/', $fringeq);
					$fringeq = array_filter($fringeq);
					$fringeq = array_values($fringeq);
					$blockstart = array_search("if station ".$otherst." and f_group ".$bnd.PHP_EOL,$arrline);

					//get freqs 
					$thefreqs = $arrline[$blockstart+2];
					$thefreqs = preg_split('/[\s]+/', $thefreqs);
					$thefreqs = array_filter($thefreqs);
					array_shift($thefreqs);
					$thefreqs = array_values($thefreqs);
					$fullfreqs = array_merge($fullfreqs,$thefreqs);

					$badchanid = [];
					$goodchan = [];
					foreach($fringeq as $fin=>$fq){
						if($fq < $weakchan * (array_sum($fringeq)/count($fringeq))){
							array_push($badchanid,$fin);
						}
						else{
							array_push($goodchan,$thefreqs[$fin]);
						}
					}
					if(count($badchanid) > 0){
						$arrline[$blockstart+2] = "  freqs ".implode(' ',$goodchan).PHP_EOL;
						for ($i=$blockstart+3;$i<$blockstart+7;$i++){
							$tmpline  = preg_split('/[\s]+/', $arrline[$i]);
							$tmpline  = array_filter($tmpline);
							$tmpline  = array_values($tmpline);

							$tmpline[1] = implode('',$goodchan);
							foreach($badchanid as $bc){
								unset($tmpline[$bc+2]);
							}
							$arrline[$i] = "  ".implode(" ",$tmpline).PHP_EOL;
						}
					}
				}
			}
		}
	}

	$cfexp = fopen('cf_'.$expname,"w");
	fwrite($cfexp,implode("",$arrline));
	fclose($cfexp);
	//upload to correlator server
	ssh2_scp_send($con,'cf_'.$expname, "correlations/".$exptype."/".$expname."/cf_".$expname, 0755);
}
*/

//create tmp file about the channel names


$stschan = fopen('../tmp/'.$expname.'/channels.txt',"w");
fwrite($stschan,implode(",",array_unique($fullfreqs)));
fclose($stschan);

//create scan list
$scan_list = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls correlations/".$exptype."/".$expname.'/'.$expcode)));
$scanlist = fopen('scans_'.$expname.'.txt',"w+");
fwrite($scanlist,implode(PHP_EOL,$scan_list));
fclose($scanlist);

//copy cf file to ../tmp/expname
rename('cf_'.$expname,'../tmp/'.$expname.'/'.'cf_'.$expname);

echo "<p>initff done</p>";


?>
