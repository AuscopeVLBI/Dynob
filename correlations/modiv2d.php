<?php
//assumption: all datastreams located on the same machine
require_once "../common/global.php";
require_once "../common/SSHcon.php";
require_once "../pmonitor/stats.php";
require_once "getinfo.php";

//Modiv2d::create('mf0129','mixed',['hb','ke'],["Hb hb vc0","Ke ke vc0"]);

class Modiv2d{
	public static function create($exper,$cmode,$cstations,$ufull){
		$file = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/"."buffer.v2d", "w+");
		$exptype = substr($exper,0,2);
		//get machine
		$machine = [];
		foreach($ufull as $tmpu){
			$tmpuexp = explode(" ",$tmpu);
			$machine[$tmpuexp[1]] = $tmpuexp[2];
		}

		if($cmode=="mixed"){
			$tempfile = fopen('template/mixedmode.v2d',"r");
		}
		elseif($cmode=="vgos"){
			$tempfile = fopen('template/vgosmode.v2d',"r");
		}

		//select S/X station as ref station
		$sxstation = "";
		foreach($cstations as $cs){
			if(!in_array($cs,$GLOBALS["vgosstations"])){
				$sxstation = $cs;
				break;
			}
		}
		if($sxstation !== ""){
			$reflog = $_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$sxstation."buffer.log";
			//$reflog = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$sxstation."buffer.log","r");
		}
		if (!isset($reflog)){
			foreach($cstations as $cs){
				if (!file_exists($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs."buffer.log")){
					continue;
				}
				else{
					$reflog = $_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs."buffer.log";
					//$reflog = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs."buffer.log","r");
					break;
				}
			}
		}

		//copy all and modify SETUP
		$vdifmode = strtoupper(trim(shell_exec('grep -i "vdif_8000" '.$reflog.' | tail -1 | cut -d " " -f 4')));
		$expvdif = explode("-",$vdifmode);
		if(substr($expvdif[3],0,1) == "2"){ //2bit
			$dynsetup = "specRes = 0.125\n\ttInt = 1.0\n\tguardNS = 26000\n\t#freqId = 12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27";
		}
		else{ //1 bit
			$dynsetup = "FFTSpecRes = 0.03125\n\tspecRes = 0.125\n\ttInt = 2.0\n\t#freqId = 12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27";
		}
		
		while (!feof($tempfile)){
			$line = fgets($tempfile);
			$line = preg_replace('/{dynsetup}/',$dynsetup,$line);
			$line = preg_replace('/{expname}/',$exper,$line);
			$line = preg_replace('/{antennas}/',implode(", ",array_filter($cstations)),$line);
			fwrite($file,$line);
		}
		fclose($tempfile);

		
		if($cmode == "mixed"){

			//-------if mixed mode, add zoom band----------
			$line = "ZOOM zoom1 {".PHP_EOL;
			fwrite($file,$line);

			//freqs
			$bbcsx = shell_exec('grep "bbcsx" '.$reflog.' | cut -d "=" -f 2 | tail -64');
			$bbcsx = explode(PHP_EOL,$bbcsx);
			$freqs = [];
			$bitswidth = [];
			$los = [];
			foreach ($bbcsx as $_bbcsx){
				if(strpos($_bbcsx,",")!==false){
					$_expbbcsx = explode(",",$_bbcsx);
					array_push($freqs,$_expbbcsx[0]);
					array_push($los,$_expbbcsx[1]);
					array_push($bitswidth,$_expbbcsx[2]);
				}
			}
			$freqs = array_unique($freqs);
			$los = array_intersect_key($los, $freqs);
			$bitswidth = $bitswidth[0];

			$lofreq = shell_exec('grep "lo=lo" '.$reflog.' | cut -d "=" -f 2 | tail -8');
			$lofreq = array_filter(explode(PHP_EOL,$lofreq));
			$loarr = [];
			foreach($lofreq as $_lofreq){
				$_explo = explode(",",$_lofreq);
				if(!array_key_exists($_explo[0],$loarr)){
					$loarr[$_explo[0]] = $_explo[1];
				}
			}

			$freqs = array_values($freqs);
			$los = array_values($los);
			
			if($bitswidth >= 16 ){
				$zoomwidth = 16;
				$margin = ($bitswidth-16)/2;
			}
			else{
				$zoomwidth = $bitswidth;
				$margin = 0;
			}

			$zoomfreq = [];
			for($u=0; $u<count($freqs); $u++){
				$_lofreq = $loarr["lo".$los[$u]];
				if( ($freqs[$u]+$_lofreq) > 8000){ //x-band
					array_push($zoomfreq,$freqs[$u]+$_lofreq+$margin);
				}
				else{
					if($bitswidth >= 16){
						$_i = $bitswidth/16;
						for($i=0;$i<$_i;$i++){
							array_push($zoomfreq,$freqs[$u]+$_lofreq+($i*16));
						}
					}
					else{
						array_push($zoomfreq,$freqs[$u]+$_lofreq);
					}
				}
			}

			$zoomfreq = array_unique($zoomfreq);
			$zoomfreq = array_slice($zoomfreq, 0, 14);

			foreach($zoomfreq as $_zf){
				fwrite($file,"\taddZoomFreq = freq@".$_zf."/bw@".number_format($bitswidth, 2, '.', '').PHP_EOL);
			}
			fwrite($file,"}");
			//-----------------------------

			//---other setups----------
			$datastream = $GLOBALS["dtsmixeds"];
			$format = "VDIF/8032/".substr($expvdif[3],0,1);
			$zoom = "zoom1";
			$vgosstations = $GLOBALS["vgosstations"];
			$toneguard = "\ttoneGuard = 3".PHP_EOL;
			$toneSelection = "\ttoneSelection = most".PHP_EOL;
			$nbandx = [];
			$nbands = [];
			$csbitswidth = [];
			foreach($cstations as $cs){
				$nbandx[$cs] = "8";
				$_reflog = $_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs."buffer.log";
				$bbcsx = shell_exec('grep "bbcsx" '.$_reflog.' | cut -d "=" -f 2 | tail -5');
				$bbcsx = explode(PHP_EOL,$bbcsx);
				foreach ($bbcsx as $_bbcsx){
					if(strpos($_bbcsx,",")!==false){
						$_expbbcsx = explode(",",$_bbcsx);
						$csbitswidth[$cs] = $_expbbcsx[2];
						break;
					}
				}
				if($csbitswidth[$cs] >= 16){
					$_i = $csbitswidth[$cs]/16;
					$nbands[$cs] = 8/$_i;
				}
				else{
					$nbands[$cs] = 8;
				}
			}
		}
		elseif($cmode=="vgos"){
			//no zoom bands
			$datastream = $GLOBALS["dtsvgos"];
			$format = "VDIF/8032/".substr($expvdif[3],0,1);
			$nbandx = [];
			foreach($cstations as $cs){
				$nbandx[$cs] = "8";
			}
			$vgosstations = $GLOBALS["vgosstations"];
			$toneguard = "";
			$toneSelection = "\ttoneSelection = all".PHP_EOL;
		}

		//Antenna
		$time = Getinfo::vexstart();
		//$time = preg_replace('/(.{4})y(.{3})d(.{2})h(.{2})m(.{2})s/', '$1.$2.$3', $time);
		foreach($cstations as $cs){
			//open log file, read clock offsets
			//if (!$fid = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs.'buffer.log',"r")){
			//	$message = "fail to open ".$cs." log <br>";
			//	$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			//	echo $message;
			//}
			if (!file_exists($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs."buffer.log")){
				continue;
			}
			
			$fmout = shell_exec("egrep  '/fmout-gps/|/gps-fmout/' ../tmp/".$exper."/".$cs."buffer.log | sed 's/\/gps-fmout\// /g' |sed 's/\#fmout\#/ /g'  | sed 's/\/fmout-gps\// /g' | sed 's/\#popen\#popen\/\#\/gps-fmout\#/ /g' | sed 's/\#gps-f\#/ /g' |  tr ':' ' '  | sed 's/\./ /1' | sed 's/\./ /1'");
			//$fmout = shell_exec("egrep  $6 $1 | grep -v '&'| sed 's/\/maser2gps\// /g' | sed 's/\#maser\#/ /g' | sed 's/\#gps-f\#/ /g' | sed 's/\/gps-fmout\// /g' |sed 's/\#fmout\#/ /g'  | sed 's/\/fmout-gps\// /g' | sed 's/\#popen\#popen\/\#\/gps-fmout\#/ /g' |  tr ":" " "  | sed 's/\./ /1' | sed 's/\./ /1'");
			$fmfile = fopen("../tmp/fmout.txt","w+");

			//fix Hb issue
			$fmoutarr = explode(PHP_EOL,$fmout);

			$checkoutlierarr = [];
			foreach($fmoutarr as $_fmoutarr){
				if(strlen($_fmoutarr) > 26){
					$_tmpfmout = preg_split('/\s+/', $_fmoutarr);
					array_push($checkoutlierarr,$_tmpfmout[5]);
				}
			}
			$theout = Stats::remove_outliers($checkoutlierarr,3);

			foreach($fmoutarr as $_fmoutarr){
				if(strlen($_fmoutarr) > 26){
					$_tmpfmout = explode(' ',$_fmoutarr);
					if(!in_array($_tmpfmout[5],$theout[1])){
						fwrite($fmfile,$_fmoutarr.PHP_EOL);
					}
				}
			}
			
			fclose($fmfile);
			$fmfile2 = fopen("../tmp/fmoutepoch.txt","w+");
			$mintodec = substr($time,12,2)/60 + substr($time,15,2)/60;
			fwrite($fmfile2,substr($time,0,4)." ".substr($time,5,3)." ".substr($time,9,2).".".substr($mintodec,2)." ".$cs." 0");
			fclose($fmfile2);
			fwrite($file,"\nANTENNA ".$cs."\n{\n");

			chmod("../tmp/fmoutepoch.txt",0777);
			chmod("../tmp/fmout.txt",0777);

			//$fmout2 = shell_exec("octave Log2Clock.m");
			$fmout2 = shell_exec("matlab -nodisplay -nosplash -nodesktop -r \"run('Log2Clock.m'); exit; exit\" | tail -n 4");
			if($exptype!=="mf"){
				fwrite($file,$fmout2);
			}
			else{
				//get first reading directly from log only
				$fmoutmodmf = preg_split('/\s+/', trim($fmout));
				if($cs=="ww"){
					$fmoutmfval = $fmoutmodmf[count($fmoutmodmf)-1] * 1E6;
				}
				else{
					$fmoutmfval = $fmoutmodmf[count($fmoutmodmf)-1] * -1E6;
				}

				$fmout2mf = explode(PHP_EOL,trim($fmout2));
				fwrite($file,"\tclockOffset = ".$fmoutmfval.PHP_EOL.$fmout2mf[2].PHP_EOL);
			//	fwrite($file,$fmout2mf[0].PHP_EOL.$fmout2mf[2].PHP_EOL);
			}
			//unlink("../tmp/fmout.txt");
			//unlink("../tmp/fmoutepoch.txt");
			
			if (in_array($cs,$vgosstations)){
				$csdts = preg_filter('/^/', $cs, $datastream[$cs]);
				fwrite($file,"\tdatastreams = ".implode(",",$csdts)."\n");
				//if ($cmode=="mixed" && $cs=="hb"){
				if ($cmode=="mixed"){
					fwrite($file,"\tzoom = ".$zoom."\n");
				}
				unset($csdts);
				if($cmode=="vgos" || !in_array($cs,$vgosstations)){
					fwrite($file,"\tphaseCalInt = 10\n".$toneguard.$toneSelection);
				}
			}
			else{
				fwrite($file,"\tfilelist = ".$cs.".filelist\n");
				fwrite($file,"\tmachine = ".$machine[$cs]."\n");
			}
			if(!($cmode=="vgos" && $cs=="ke")){
				fwrite($file,"\tdeltaClock = ".$GLOBALS["poffs"][$cs]."\n");
			}
			
			fwrite($file,"}\n");
		}

		//Datastream for Vgos Stations
		foreach($cstations as $cs){
			if (!file_exists($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs."buffer.log")){
				continue;
			}
			if (in_array($cs,$vgosstations)){
				foreach ($datastream[$cs] as $dts){
					fwrite($file,"\nDATASTREAM ".$cs.$dts."\n{\n");
					//-----for the four datastreams stations----
					if ($cmode=="mixed"){
						if($dts[0] == "s"){
							fwrite($file,"\tformat =  ".$format."\n\tnBand = ".$nbands[$cs]."\n\tfilelist = ".$cs.$dts.".filelist\n\tmachine = ".$machine[$cs]."\n}\n");
						}
						else{
							fwrite($file,"\tformat =  ".$format."\n\tnBand = ".$nbandx[$cs]."\n\tfilelist = ".$cs.$dts.".filelist\n\tmachine = ".$machine[$cs]."\n}\n");
						}

						/*
						if (count($datastream[$cs])>3){
							if($dts[0] == "s"){

								if($cs == "hb"){
									fwrite($file,"\tformat =  ".$format."\n\tnBand = 4\n\tfilelist = ".$cs.$dts.".filelist\n\tmachine = ".$machine[$cs]."\n}\n");
								}
								else{
									fwrite($file,"\tformat =  ".$format."\n\tnBand = ".$nband."\n\tfilelist = ".$cs.$dts.".filelist\n\tmachine = ".$machine[$cs]."\n}\n");
								}
							}
							else{
								fwrite($file,"\tformat =  ".$format."\n\tnBand = ".$nband."\n\tfilelist = ".$cs.$dts.".filelist\n\tmachine = ".$machine[$cs]."\n}\n");
							}
						}
						else{
							fwrite($file,"\tformat =  ".$format."\n\tnBand = ".$nband."\n\tfilelist = ".$cs.$dts.".filelist\n\tmachine = ".$machine[$cs]."\n}\n");
						}*/
					}
					elseif ($cmode=="vgos"){
						fwrite($file,"\tformat =  ".$format."\n\tnBand = ".$nbandx[$cs]."\n\tfilelist = ".$cs.$dts.".filelist\n\tmachine = ".$machine[$cs]."\n}\n");
					}
				}
			}
		}
		//EOP
		//get Julian date
		$epstart = Getinfo::vexstart();
		//echo $epstart;
		$eptmp = date_parse_from_format("Y.z.H.i.s", $epstart);
		$epjd = gregoriantojd($eptmp["month"], $eptmp["day"], $eptmp["year"]);
		$epjd =  $epjd - 3; //php doy starts from 0 = -1, Julian date = .5 = -1, EOP one day earlier = -1
		$epjdarr = [];
		//get latest erp
		//$erp = file_get_contents('ftp://cddis.gsfc.nasa.gov/pub/vlbi/gsfc/ancillary/solve_apriori/usno_finals.erp');
		//$erp = fopen('ftp://cddis.gsfc.nasa.gov/pub/vlbi/gsfc/ancillary/solve_apriori/usno_finals.erp',"r");
		$erp = fopen($_SERVER['DOCUMENT_ROOT'].'tmp/usno_finals.erp',"r");

		//get 5 days eop including a day before experiment
		for ($i=0;$i<6;$i++){
			array_push($epjdarr, $epjd);
			$epjd =  $epjd + 1;
		}
		while(!feof($erp)){
			$line = fgets($erp);
			foreach ($epjdarr as $epar){
				if (stripos($line,"$epar")===0){
					sscanf($line,"%f %f %f %f",$_ejd,$_expole,$_eypole,$_etaiutc);
					$ejd = $_ejd - 2400000.5;
					$expole = number_format($_expole/10, 6, '.', '');
					$eypole = number_format($_eypole/10, 6, '.', '');
					$tai = substr($_etaiutc,1,2);
					$ut1utc = number_format(($_etaiutc/1000000) + $tai, 6, '.', '');
					fwrite($file,"\nEOP $ejd { xPole=$expole yPole=$eypole tai_utc=$tai ut1_utc=$ut1utc }");
				}
			}
		}

		fclose($file);
		chmod($_SERVER['DOCUMENT_ROOT']."tmp/$exper/"."buffer.v2d", 0777);

	}
		
}
?>
