<?php

Class Tsyssefd{

	public function calfromtsys($station,$session){
		$k = 1.380649e-23;//boltzman constant
		$antdias = $GLOBALS["antdias"]; //antenna diameter
		$area = 0.625 * M_PI * (($antdias[$station]/2)*($antdias[$station]/2)); //0.625 is the rough antenna efficiency
		$station = strtolower($station);

		//get tsys from log
		$tsyslines = shell_exec("grep '/tsys/' ".$_SERVER["DOCUMENT_ROOT"]."tmp/".$session."/".$station."buffer.log");
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
				$bbcline = shell_exec("grep -m 1 'bbc".str_pad(hexdec($_ifchan),2,"0",STR_PAD_LEFT)."=' ".$_SERVER["DOCUMENT_ROOT"]."tmp/".$session."/".$station."buffer.log | head -1");
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
				$bbcline = shell_exec("grep -m 1 'bbc".$_ifchan."=' ".$_SERVER["DOCUMENT_ROOT"]."tmp/".$session."/".$station."buffer.log | head -1");
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
			$loline = shell_exec("grep -m 1 'lo=lo".$_lo."' ".$_SERVER["DOCUMENT_ROOT"]."tmp/".$session."/".$station."buffer.log | head -1");
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

?>