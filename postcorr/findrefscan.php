<?php
//usage: $C->getRef(["1921-293","0454-234"],["e","W","L","i","g"],8);
//i.e: $C->getRef(fringe cheking sources in array format, reference station code in fourfit,target min SNR);

require_once "../common/SSHcon.php";
require_once "../common/global.php";
require_once "../common/dbwrite.php";

Class FindRef{
	public $selstinscanarr = [];
	public $selsouinscanarr = [];
	public $selstinscanarract = [];
	public $selscansarr = [];
	public $tmpbl = [];

	function getRef($refSource,$refStCode,$targetminSNR){

		$db = new DbWrite;
		$expname = $_GET["exp"];
		$exptype = substr($expname,0,2);
		$exptype1 = substr($expname,0,1);
		$vexfile = fopen($_SERVER['DOCUMENT_ROOT'].'tmp/'.$expname.'/'.$expname.'.vex',"r");
		if($exptype!=="mf"){
			$dbq = $db->query("dynob",'SELECT expcode FROM Correlation WHERE session="'.$expname.'"');
			$expcode = $dbq[0]["expcode"];
		}
		else{
			$expcode = "1234";
		}

		if($exptype1 == "z"){//si
			$exptype = "si";
		}
		
		$conssh = new ConnectSSH;
		if(!$con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"])){
			die("Unable to reach ".$GLOBALS["cserver"]);
		}
		$allscans = explode(PHP_EOL,$conssh->exec($con,"ls correlations/".$exptype."/".$expname."/".$expcode));
		$allscans = array_filter($allscans);

		$stnum = 0;
		$stmark = 0;
		$frqsmark = 0;
		$frqxmark = 0;
		$returnarr = [];
		$refstused = "";
		$selstinscan = 0;
		$selinscan = 0;
		
		while (!feof($vexfile)){
			$line = fgets($vexfile);

			//find ref freq
			if (stripos($line,'chan_def = &X')!==false && $frqxmark==0 && (stripos($line,'32.000')!==false || stripos($line,'16.000')!==false || stripos($line,'8.000')!==false)){
				$frqxmark = 1;
				$refFreqX = substr(trim(explode(":",$line)[1]),0,7);
			}
			if (strpos($line,'chan_def = &S')!==false && $frqsmark==0 && (stripos($line,'32.000')!==false || strpos($line,'16.000')!==false || strpos($line,'8.000')!==false)){
				$frqsmark = 1;
				$refFreqS = substr(trim(explode(":",$line)[1]),0,7);
			}

			//find stations
			if (strpos($line,'$')!==false && strpos($line,'ref ')===false ){
				$stmark = 0;
			}
			if (stripos($line,'$STATION')!==false){
				$stmark = 1;
			}
			if($stmark >= 1){
				if(stripos($line,"def ")!==false){
					$stnum += 1;
				}
			}

			//find scan with max stations according to vex
			//selecting criteria: sources in refsource, num stations in vex, num stations actually observed
			//format: $selscansarr[name;name;...], $selstinscanarr[3,3,...]
			if(strpos($line,"scan ")!==false){
				$selinscan = 1;
				$scannm = substr($line,strpos($line,"scan ")+5,-1);
				$scannm = str_ireplace(';', '', $scannm); 

			}
			if($selinscan == 1 ){
				if(strpos($line,"station")!==false){
					$selstinscan = $selstinscan+1;
				}
				if(strpos($line,"source")!==false){
					$sourcenm = trim(substr($line,strpos($line,"=")+1,-1));
					$sourcenm = str_ireplace(';', '', $sourcenm);
				}
				if(strpos($line,"endscan")!==false){
					$selinscan = 2;
				}
			}
			if($selinscan == 2 ){
				if($exptype !== "mf"){
					if((in_array($sourcenm,$refSource)) && in_array($scannm,$allscans)){
						array_push($this->selscansarr,$scannm);
						array_push($this->selstinscanarr,$selstinscan);
						array_push($this->selsouinscanarr,$sourcenm);
					}
					
				}else{
					array_push($this->selscansarr,$scannm);
					array_push($this->selstinscanarr,$selstinscan);
				}
				$selstinscan = 0;
				$selinscan = 0;
			}
		}
		fseek($vexfile,0);
		fclose($vexfile);

		//find ref scan 
		if($exptype == "mf"){
			if($expname == "mfdynf"){
				//reslect scannm
				//$scannm = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls correlations/".$exptype."/".$expname.'/'.$expcode.'/')));
				//$scannm = $scannm[0];

				//use above if full datasize in the future, use dummy to recover full sensitivity for short scans
				$scannm = "dummy";

			}

			//use first scan as reference (old)
			//$stsinvolve = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls correlations/".$exptype."/".$expname.'/'.$expcode.'/'.$this->selscansarr[0])));
			//array_shift($stsinvolve);

			//use scan available in the correlation directory as the reference
			$stsinvolve = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls correlations/".$exptype."/".$expname.'/'.$expcode.'/*')));
			array_shift($stsinvolve);
			
			//find ref station according to priority
			$starr = [];
			foreach ($stsinvolve as $sts){
				$stc1 = substr($sts,0,1);
				$stc2 = substr($sts,1,1);
				if(($stc1 !== $stc2) && $stc2!=='.' ){
					if(!in_array($stc1,$starr)){
						array_push($starr,$stc1);
					}
					if(!in_array($stc2,$starr)){
						array_push($starr,$stc2);
					}
				}
			}
			foreach ($refStCode as $refst){
				if(in_array($refst,$starr)){
					$refstused = $refst;
					break;
				}
			}
			$returnarr = [$scannm,$starr,$refFreqX,$refFreqS,$refstused];
			return $returnarr;
		}
		else{
			//count from correlator and store 
			for ($i=0;$i<count($this->selscansarr);$i++){
				$stsinvolve = array_filter(explode(PHP_EOL,$conssh->exec($con,"ls correlations/".$exptype."/".$expname.'/'.$expcode.'/'.$this->selscansarr[$i])));
				array_shift($stsinvolve);
				$starr = [];
				$this->tmpbl[$i] = [];
				foreach ($stsinvolve as $sts){
					$stc1 = substr($sts,0,1);
					$stc2 = substr($sts,1,1);
					if(($stc1 !== $stc2) && $stc2!=='.' ){
						if(!in_array($stc1,$starr)){
							array_push($starr,$stc1);
						}
						if(!in_array($stc2,$starr)){
							array_push($starr,$stc2);
						}
						//push bl
						if (!in_array($stc1.$stc2,$this->tmpbl[$i])){
							array_push($this->tmpbl[$i],$stc1.$stc2);
						}
					}
				}
				array_push($this->selstinscanarract,$starr);
			}
			
			//compared actual num stations with stations in vex
			$actreldiff = [];
			$refstused = [];
			for ($i=0; $i<count($this->selstinscanarr);$i++){
				array_push($actreldiff,$this->selstinscanarr[$i]-count($this->selstinscanarract[$i]));
			}

			$_countst = [];
			foreach($this->selstinscanarract as $_selstarr){
				array_push($_countst,count($_selstarr));
			}

			if(max($_countst) == 2){ //if maximum number of station of the seesion is 2
				$minusnum = 1;
			}
			else{
				$minusnum = 2;
			}
			//select scans from selscansarr, prioritising scan with more stations according to vex
			$rating = [];
			for ($j=0;$j<$stnum-$minusnum;$j++){
				$scanind = [];
				for ($i=0;$i<count($this->selstinscanarr);$i++){
					if ($this->selstinscanarr[$i] == $stnum -$j ){
						array_push($scanind,$i);
					}
				}
				if(count($scanind)>0){
					for ($i=0;$i<count($scanind);$i++){
						$_rating = 0; //init

						//check if there's any loss station in actual observation
						$_rating = ($_rating + ($stnum -$j) - $actreldiff[$scanind[$i]])*1.5; //scale up for high weighting

						//rate by $refStation
						for ($k=0;$k<count($refStCode);$k++){
							if(in_array($refStCode[$k],$this->selstinscanarract[$scanind[$i]])){
								$_rating = $_rating + (count($refStCode)-$k)*1/count($refStCode);
								$refstused[$scanind[$i]] = $refStCode[$k];
								break;
							}
						}

						//rate by $refSource
						$souind = array_search($this->selsouinscanarr[$scanind[$i]],$refSource);
						$_rating = $_rating + (count($refSource)-$souind)*1/count($refSource);

						//add to array
						$rating[$scanind[$i]] = $_rating;
					}
				}
			}

			arsort($rating);

			//check minimum SNR
			$snrchecklimit = 5;

			if($targetminSNR>0){
				foreach($rating as $_ind=>$_val){
					$snrchecklimit = $snrchecklimit - 1;
					if($snrchecklimit<0){ //no perfect baseline, use first scan of highest score
						$refind = array_search(max($rating),$rating);
						$returnarr = [$this->selscansarr[$refind],$this->selstinscanarract[$refind],$refFreqX,$refFreqS,$refstused[$refind]];
						return $returnarr;
					}
					$goodblinscan = 0;
					foreach($this->tmpbl[$_ind] as $tbl ){
						$ffSNR = explode(PHP_EOL,$conssh->exec($con,"cd correlations/".$exptype."/".$expname."; fourfit -m1 -t -b".$tbl." ".$expcode."/".$this->selscansarr[$_ind]." pc_mode manual 2>&1| grep 'SNR'"));
						$ffSNR = array_filter($ffSNR);
						
						$goodpol = 0;
						foreach($ffSNR as $fsnr){
							$fsnr = trim(str_ireplace("fourfit: SNR ",'',$fsnr));
							if($fsnr >= $targetminSNR){
								$goodpol += 1;
							}
						}
						if ($goodpol == count($ffSNR)){
							$goodblinscan = $goodblinscan + 1;
						}
					}
					if($goodblinscan == count($this->tmpbl[$_ind])){
						$returnarr = [$this->selscansarr[$_ind],$this->selstinscanarract[$_ind],$refFreqX,$refFreqS,$refstused[$_ind]];
						return $returnarr;
					}
				}
			}
			else{ 	//do not check minimum SNR
				//use first scan of highest score
				$refind = array_search(max($rating),$rating);
				$returnarr = [$this->selscansarr[$refind],$this->selstinscanarract[$refind],$refFreqX,$refFreqS,$refstused[$refind]];
				return $returnarr;
			}
		}

	}
}


?>
