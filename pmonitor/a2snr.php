<?php
//new programme that read flux.cat with date it change, not from skd file.
//also uses restimated SEFD, not from skd file
//writes only when >3 observations exist for a scan 

require_once "sefdrees.php";
require_once "../common/global.php";
//require_once "lsqsefdrees.php";

$fc = new fluxCalc();

//$dfile = scandir("afile");
//$dfile = array_diff($dfile, array('.', '..'));

//foreach($dfile as $afile){
//	$fc->analyse($afile);
//}
//$fc->analyse("aua097.a.s");

if( $_REQUEST["afile"] ) {

	$afile = $_REQUEST['afile'];
	$fc->analyse($afile);
}



class fluxCalc{

	//catalog or skd
	private $sourcesUsed = [];
	private $sourcesAlias = [];
	private $sourceCat = [];
	private $fluxXCat = [];
	private $fluxSCat = [];
	private $positionCat = [];
	private $equipCat = [];
	private $Bw = "";
	private $stcode = []; //station code 
	private $SEFD1 = [];
	private $SEFD2 = [];

	//params
	public $mjd = [];
	public $baseline = [];
	private $band = [];
	public $sources = [];

	public $NumChannels = [];
	public $Scanlength = [];
	public $pol = [];
	public $model = [];

	public $obsSNR = [];
	public $elv1 = [];
	public $elv2 = [];
	public $fobs = [];
	public $bitnum = [];
	public $gdelsigps = [];
	public $gdelsigmm = [];
	public $sideband = [];

	//set
	private $vgosstations = ["Hb","Ke"];
	private $analysingfile;

	//calculated results
	public $flux = [];
	public $apsnr = [];
	public $sefdsefd = [];
	public $pbase = [];
	public $SampleRate = [];
	public $reessefd1 = [];
	public $reessefd2 = [];


	//init
	function analyse($file2Analyse){
	
		if (!$fid = fopen($_SERVER["DOCUMENT_ROOT"]."pmonitor/afile/".$file2Analyse,"r")){
			echo "fail to open ".$file2Analyse;
		}
		$this->analysingfile = $file2Analyse;
		
		function rpstc($matches){
			$_rpstc = ["H"=>"Ho","L"=>"Hb","i"=>"Ke","e"=>"Yg","W"=>"Ww","g"=>"Ht"];
			//$_rpstc = ["H"=>"Ho","L"=>"Hb","i"=>"Ke","e"=>"Yg","W"=>"Ww"];
			return $_rpstc[$matches[1]]."-".$_rpstc[$matches[2]];
		}
		
		while (!feof($fid)) {
			$line = fgets($fid);
			if  ($line[0] !== "#"){
				if(!empty($line)){
					//last three var valid if from vgosDB (Matlab)
					sscanf($line,"%s %s %s %d %s %s %s %f %f %f %d %f %f %s",$_year,$_time,$_sou,$_dur,$_chan,$_bl,$_pol,$_snr,$_elv1,$_elv2,$_bitnum,$_goupdelsigps,$_goupdelsigmm,$_sideband);

					//date
					$date = "20".$_year.".".$_time;
					$mjd = $this->getmjd($date);
					array_push($this->mjd,$mjd);
					//baseline
					
					$bl = preg_replace_callback('/(.)(.)/', 'rpstc', $_bl);
					array_push($this->baseline,$bl);

					//num channels
					$nc = (int)substr($_chan,1,2);
					array_push($this->NumChannels,$nc);
					//current band
					array_push($this->band,substr($_chan,0,1));
					//scan length
					array_push($this->Scanlength,$_dur);
					//obsSNR
					array_push($this->obsSNR,$_snr);
					//elev
					array_push($this->elv1,$_elv1);
					array_push($this->elv2,$_elv2);
					//pol
					array_push($this->pol,$_pol);

					//sources
					array_push($this->sources,$_sou);
					//sourcesUsed
					if(!in_array($_sou,$this->sourcesUsed)){
						array_push($this->sourcesUsed,$_sou);
					}
					//bitnum
					array_push($this->bitnum,$_bitnum);
					
					if (isset($_goupdelsigps)){ //if from Matlab (vgosDb)
						//groupdelsig
						array_push($this->gdelsigps,$_goupdelsigps);
						array_push($this->gdelsigmm,$_goupdelsigmm);
						//sideband
						array_push($this->sideband,$_sideband);
					}
				}
			}
		}
		fclose($fid);
		
		$expname = substr($this->analysingfile,0,-4);
		if (file_exists("../scheduling/skd/".$expname.".skd")){
			echo $expname." read skd <br>";
			$this->readskd();
			//$this->readcatExtra($date);
		}
		else{
			$urlfromivs = "https://cddis.gsfc.nasa.gov/archive/vlbi/ivsdata/aux/20".$_year."/".$expname."/".$expname.".skd";
			$urlfrombkg = "ftp://ivs.bkg.bund.de/pub/vlbi/ivsdata/aux/20".$_year."/".$expname."/".$expname.".skd";
			$file_name = "../scheduling/skd/".$expname.".skd";
			if(file_put_contents($file_name,file_get_contents($urlfrombkg))){
				echo $expname." downloaded skd <br>";
				$this->readskd();
				//$this->readcatExtra($date);
			}
			elseif(file_put_contents($file_name,file_get_contents($urlfromivs))){
				echo $expname." downloaded skd <br>";
				$this->readskd();
				//$this->readcatExtra($date);
			}
			else{
				echo $expname." read catalogues <br>";
				unlink($file_name);
				$this->readCat();
				//$this->readcatExtra($date);
			}
		}
		$this->calcFlux();
		
	}

	//readSkd
	function readskd(){
		$marker = 0;
		$stcode = [];
		$sfid = fopen("../scheduling/skd/".substr($this->analysingfile,0,-4).".skd", "r");
		while(!feof($sfid)){
			$line = fgets($sfid);
			if($line[0]=='$'){
				if($marker !== ""){
					$marker = "";
				}
			}
			if(stripos($line,'$SOURCES')!==false){
				$marker = "source";
				continue;
			}
			if(stripos($line,'$FLUX')!==false){
				$marker = "flux";
				continue;
			}
			if(stripos($line,'$STATIONS')!==false){
				$marker = "station";
				continue;
			}
			if(stripos($line,'$CODES')!==false){
				$marker = "codes";
				continue;
			}
			if(stripos($line,'$SRCWT')!==false){
				$marker = "srcwt"; //for sources without flux information
				continue;
			}
			if ($marker == "source"){
				sscanf($line, "%s %s %s %s %s %s %s %s", $_1,$_2,$_3,$_4,$_5,$_6,$_7,$_8);
				if($_2==="$"){
					$this->sourceCat[$_1] = $line;
				}
				else{
					$this->sourceCat[$_2] = $line;
					$this->sourcesAlias[$_2] = $_1;
				}
			}
			if ($marker == "flux"){
				sscanf($line, "%s %s %s %s %s", $_1,$_2,$_3,$_4,$_5);
				if  (stripos($line, " X ")!==false){
					$this->fluxXCat[$_1] = $line;
				}
				if  (stripos($line, " S ")!==false){
					$this->fluxSCat[$_1] = $line;
				}
			}
			if ($marker == "srcwt"){
				sscanf($line, "%s %f", $_1,$_2);
				//create a 1-step pseudo B-model
				$this->fluxXCat[$_1] = $_1." X B 0.0  0.10 13000.0";
				$this->fluxSCat[$_1] = $_1." S B 0.0  0.10 13000.0";
			}
			
			//stations
			if ($marker == "station"){
				if($line[0]=="T"){
					sscanf($line, "%s %s %s %s %s %s %s %s %s %s %s %s %s %s %s %s %s %s %s", $st1,$st2,$st3,$st4,$st5,$st6,$st7,$st8,$st9,$st10,$st11,$st12,$st13,$st14,$st15,$st16,$st17,$st18,$st19);
					if(in_array($st2,$GLOBALS["equipalias"])){
						$st2 = array_search($st2, $GLOBALS["equipalias"]);
					}
					$this->equipCat[$st2] = "$st3 $st2 $st3 $st4 $st5 $st6 $st7 $st8 $st9 $st10 $st11 $st12 $st13 $st14 $st15 $st16 $st17 $st18 $st19";
				}
				if($line[0]=="P"){
					sscanf($line, "%s %s %s %s %s %s %s %s %s", $sp1,$sp2,$sp3,$sp4,$sp5,$sp6,$sp7,$sp8,$sp9);
					$this->positionCat[$sp2] = "$sp2 $sp3 $sp4 $sp5 $sp6 $sp7 $sp8 $sp9";
					$stcodep[$sp3] = $sp2;
				}
				if($line[0]=="A"){
					sscanf($line, "%s %s %s %s %s %s %s %s %s %s %s %s %s %s %s", $sa1,$sa2,$sa3,$sa4,$sa5,$sa6,$sa7,$sa8,$sa9,$sa10,$sa11,$sa12,$sa13,$sa14,$sa15);
					$stcodea[$sa3] = $sa2;
				}
			}
			
			//codes
			if ($marker == "codes"){
				if($line[0]=="C"){
					//C SX X 8212.99 10000.0  1 Mk341:1 16.00 1(-1,15,0,16)
					sscanf($line, "%s %s %s %s %s %s %s %s %s", $_1,$_2,$_3,$_4,$_5,$_6,$_7,$_8,$_9);
					$this->Bw = $_8 * 1000000;
				}
			}
		}
		fclose($sfid);
		$stcode = [];
		foreach(array_keys($stcodep) as $stk){
			$stcode[$stcodea[$stk]] = $stcodep[$stk];
		}
		$this->stcode = $stcode;
	}

	//read catalog
	function readCat(){
		//$sourceCat = [];
		$sourceNum = count($this->sourcesUsed);
		$sourcesUsed = $this->sourcesUsed;
		$sfid = fopen($_SERVER["DOCUMENT_ROOT"]."catalogs/source.cat.geodetic.good", "r");
		$ffid = fopen($_SERVER["DOCUMENT_ROOT"]."catalogs/flux.cat", "r");
		$pfid = fopen($_SERVER["DOCUMENT_ROOT"]."catalogs/position.cat", "r");
		$efid = fopen($_SERVER["DOCUMENT_ROOT"]."catalogs/equip.cat", "r");
		for($i=0;$i<$sourceNum;$i++){
			//source.cat
			while (!feof($sfid)) {
				$line = fgets($sfid);
				if  ($line[0] !== "*"){
					if  (strpos($line, $sourcesUsed[$i])!==false){
						$this->sourceCat[$sourcesUsed[$i]] = $line;
					}
				}
			}
			fseek($sfid,0);

			//flux.cat
			while (!feof($ffid)) {
				$line = fgets($ffid);
				if  ($line[0] !== "*"){
					if  (strpos($line, $sourcesUsed[$i])!==false && stripos($line, " X ")!==false){
						$this->fluxXCat[$sourcesUsed[$i]] = $line;
					}
					if  (strpos($line, $sourcesUsed[$i])!==false && stripos($line, " S ")!==false){
						$this->fluxSCat[$sourcesUsed[$i]] = $line;
					}
					
				}
			}
			fseek($ffid,0);
		}
		fclose($sfid);
		//fclose($ffid);

		//stations
		$stations = [];
		for($i=0;$i<count($this->baseline);$i++){
			$_stations = explode("-",$this->baseline[$i]);
			if(!in_array($_stations[0],$stations)){
				array_push($stations,$_stations[0]);
			}
			if(!in_array($_stations[1],$stations)){
				array_push($stations,$_stations[1]);
			}
		}
		for($i=0;$i<count($stations);$i++){
			//position.cat
			while (!feof($pfid)) {
				$line = fgets($pfid);
				if  ($line[0] !== "*"){
					if  (strpos($line, $stations[$i]." ")!==false){
						$this->positionCat[$stations[$i]] = $line;
					}
				}
			}
			fseek($pfid,0);

			//eqiup.cat
			while (!feof($efid)) {
				$line = fgets($efid);
				if  ($line[0] !== "*"){
					if  (strpos($line, $stations[$i]." ")!==false){
						$this->equipCat[$stations[$i]] = $line;
					}
				}
			}
			fseek($efid,0);
		}
		fclose($pfid);
		fclose($efid);
	}

	function readcatExtra($date){
		//read the flux with date compare with mjd
		//use SEFD n for 2014-2017
		//use SEFD x for 2018-2019
		$fluxdt = date_parse_from_format("Y.z-Hi", $date);
		$fluxjd=gregoriantojd($fluxdt["month"],$fluxdt["day"],$fluxdt["year"]);
		$fluxmon = jdmonthname($fluxjd,0);
		$fluxcdate = substr($fluxdt["year"],2).$fluxmon;

		$sourceNum = count($this->sourcesUsed);
		$sourcesUsed = $this->sourcesUsed;
		$ffid = fopen($_SERVER["DOCUMENT_ROOT"]."catalogs/flux.cat", "r");
		for($i=0;$i<$sourceNum;$i++){
			//flux.cat
			while (!feof($ffid)) {
				$line = fgets($ffid);
				//check date
				if(strpos($line, $sourcesUsed[$i])!==false && stripos($line, " X ")!==false && stripos($line,$fluxcdate)!==false){
					if($line!==$this->fluxXCat[$sourcesUsed[$i]] ){
						$this->fluxXCat[$sourcesUsed[$i]] = substr($line,2);
					}
				}
				if(strpos($line, $sourcesUsed[$i])!==false && stripos($line, " S ")!==false && stripos($line,$fluxcdate)!==false){
					if($line!==$this->fluxSCat[$sourcesUsed[$i]] ){
						$this->fluxSCat[$sourcesUsed[$i]] = substr($line,2);
					}
				}
			}
			fseek($ffid,0);
		}
		fclose($ffid);
	}

	function getmjd($date){
		$decref = 1/1440; // 1/24/60/ (precise to minute) 
		$eptmp = date_parse_from_format("Y.z-Hi", $date);
		$epjd = gregoriantojd($eptmp["month"], $eptmp["day"], $eptmp["year"]);
		$epjd =  $epjd - 2;
		$dec = ($decref*$eptmp["hour"]*60)+($decref*$eptmp["minute"]);
		return $epjd+$dec+0.5-2400000; //apparently jd starts from noon, hence +0.5
	}

	//-----------define tgmst-----------------------
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

	function lst($jd,$EastLong){
		$TJD = floor($jd - 0.5) + 0.5;
		$DayFrac = $jd - $TJD;
	
		$T = ($TJD - 51545.0)/36525.0;
	
		$GMST0UT = 24110.54841 + 8640184.812866*$T + 0.093104*$T*$T - 6.2e-6*$T*$T*$T;
	
		// convert to fraction of day in range [0 1)
		$GMST0UT = $GMST0UT/86400.0;
	
		$GMST0UT = $GMST0UT - floor($GMST0UT);
		$LST = $GMST0UT + 1.0027379093*$DayFrac + $EastLong/(2*M_PI);
		$LST = $LST - floor($LST);
	
		return $LST;
	}

	function calcFlux(){
		$sourcesAlias = $this->sourcesAlias;
		$mjd = $this->mjd;
		$baseline = $this->baseline;
		//$SampleRate = $this->SampleRate; //2 * Bw
		$NumChannels = $this->NumChannels;
		$Scanlength = $this->Scanlength;
		$obsSNR = $this->obsSNR;
		$band = $this->band;
		$sources = $this->sources;
		$equip = $this->equipCat;
		$elev1 = $this->elv1;
		$elev2 = $this->elv2;
		$sefdelvfunc = ["Hb"=>"S 1.0 0.975 0.025  X 1.0 0.956 0.044",
						"Ke"=>"S 1.0 0.970 0.030  X 1.0 0.958 0.042",
						"Yg"=>"S 1.0 0.978 0.022  X 1.0 0.971 0.029"];

		$restr = [];
		$restrsnr = [];
		$restrsefd = [];
		$onescansnr = [];
		$onescansnrold = [];
		$stSEFD = [];
		$ree = new Sefdrees();
		$oscountarr = [];
		$vgosstations = $this->vgosstations;
		$bitnum = $this->bitnum;
		$gdelsigps = $this->gdelsigps;
		$gdelsigmm = $this->gdelsigmm;
		$sideband = $this->sideband;

		//wavelength=c/f
		$wavelength = ["L" => 0.3, "S" => 0.131, "C" => 0.06,
		//$wavelength = ["L" => 0.3, "S" => 3.8, "C" => 0.06,
		"X" => 0.0349, "Ku" => 0.0231, "K" => 0.0134,
		"Ka" => 0.01000, "E" => 0.005, "W" => 0.00375];

		for($i=0;$i<count($mjd);$i++){
			$_station1 = explode("-",$baseline[$i])[0];
			$_station2 = explode("-",$baseline[$i])[1];
			$csource = $this->sourceCat[$sources[$i]];

			if($band[$i]=="X"){
				if(!empty($this->fluxXCat[trim($sources[$i])])){
					$cflux = $this->fluxXCat[$sources[$i]];
				}
				else{
					$cflux = $this->fluxXCat[$sourcesAlias[$sources[$i]]];
				}
				//$cflux = $this->fluxXCat[$sources[$i]];
			}
			elseif($band[$i]=="S"){
				if(!empty($this->fluxSCat[$sources[$i]])){
					$cflux = $this->fluxSCat[$sources[$i]];
				}
				else{
					$cflux = $this->fluxSCat[$sourcesAlias[$sources[$i]]];
				}
				//$cflux = $this->fluxSCat[$sources[$i]];
			}
			$cpos = [$this->positionCat[$_station1],$this->positionCat[$_station2]];
			//---------------ra, de-----------------------
			sscanf($csource,"%s %s %d %d %f %d %d %f %f %f %s %s",$name,$commonname, $rah,$ram,$ras,$ded,$dem,$des,$epc,$z,$so,$dfgl);
			$ra = ($rah + $ram/60 + $ras/3600) * 15 * M_PI / 180;
			if ($ded<0){
				$sign = -1;
			}
			else{
				$sign = 1;
			}
			$de = $sign * (abs($ded) + $dem/60 + $des/3600) * M_PI /180;
			
			//-------------baseline components--------------------
			sscanf($cpos[0],"%s %s %f %f %f %d %f %f",$pcode1, $pname1, $px1, $py1, $pz1, $pocc1, $plon1, $plat1);
			sscanf($cpos[1],"%s %s %f %f %f %d %f %f",$pcode2, $pname2, $px2, $py2, $pz2, $pocc2, $plon2, $plat2);
			$blx = $px1-$px2;
			$bly = $py1-$py2;
			$blz = $pz1-$pz2;
			
			$y1 = $y2 = $c01 = $c02 = $c11 = $c12 = 0;
			$esb1 = $esy1 = $esc01 = $esc11 = $exb1 = $exy1 = $exc01 = $exc11 = 0;
			$esb2 = $esy2 = $esc02 = $esc12 = $exb2 = $exy2 = $exc02 = $exc12 = 0;
			
			sscanf($equip[$_station1],"%s %s %s %s %s %s %f %s %f %s %f %f %f %s %f %f %f",$ename1, $pcode1, $edat1, $eheads1, $etape1, $ex1, $esefdx1, $es1, $esefds1, $esb1, $esy1, $esc01, $esc11, $exb1, $exy1, $exc01, $exc11);
			sscanf($equip[$_station2],"%s %s %s %s %s %s %f %s %f %s %f %f %f %s %f %f %f",$ename2, $pcode2, $edat2, $eheads2, $etape2, $ex2, $esefdx2, $es2, $esefds2, $esb2, $esy2, $esc02, $esc12, $exb2, $exy2, $exc02, $exc12);
			
			sscanf($sefdelvfunc[$_station1],"%s %f %f %f %s %f %f %f",$esb1, $esy1, $esc01, $esc11, $exb1, $exy1, $exc01, $exc11); //if no elevation applied
			sscanf($sefdelvfunc[$_station2],"%s %f %f %f %s %f %f %f",$esb2, $esy2, $esc02, $esc12, $exb2, $exy2, $exc02, $exc12);
			
			if($band[$i] == "X"){
				$SEFD1 = $esefdx1;
				$SEFD2 = $esefdx2;
				$y1 = $exy1;
				$y2 = $exy2;
				$c01 = $exc01;
				$c02 = $exc02;
				$c11 = $exc11;
				$c12 = $exc12;
				$elvdep1 = $exb1;
				$elvdep2 = $exb2;
			}
			elseif($band[$i] == "S"){
				$SEFD1 = $esefds1;
				$SEFD2 = $esefds2;
				$y1 = $esy1;
				$y2 = $esy2;
				$c01 = $esc01;
				$c02 = $esc02;
				$c11 = $esc11;
				$c12 = $esc12;
				$elvdep1 = $esb1;
				$elvdep2 = $esb2;
			}
/*
			//SEFD overwrite as new identified
			if($band[$i] == "X"){
				$c0byst = ["Hb"=>0.697,"Ke"=>0.772,"Ww"=>0.924,"Yg"=>0.970];
				$c1byst = ["Hb"=>0.034,"Ke"=>0.040,"Ww"=>0.037,"Yg"=>0.031];
				
				if ($mjd[$i]<57022){ // 31 Dec 2014
					$SEFDbystX = ["Hb"=>4170,"Ke"=>4000,"Ww"=>4470,"Yg"=>3970];
				}
				if ($mjd[$i]>57022 && $mjd[$i]<57387){ // 31 Dec 2015
					$SEFDbystX = ["Hb"=>4240,"Ke"=>4220,"Ww"=>4220,"Yg"=>5210];
				}
				if ($mjd[$i]>57387 && $mjd[$i]<57753){ // 31 Dec 2016
					$SEFDbystX = ["Hb"=>3600,"Ke"=>4180,"Ww"=>4450,"Yg"=>3980];
				}
				if ($mjd[$i]>57753 && $mjd[$i]<58118){ // 31 Dec 2017
					$SEFDbystX = ["Hb"=>4080,"Ke"=>4220,"Ww"=>6240,"Yg"=>4280];
				}
				if ($mjd[$i]>58118 && $mjd[$i]<58483){ // 31 Dec 2018
					$SEFDbystX = ["Hb"=>16340,"Ke"=>4710,"Ww"=>4450,"Yg"=>4370];
				}
				if ($mjd[$i]>58483 && $mjd[$i]<58848){ // 31 Dec 2019
					$SEFDbystX = ["Hb"=>24970,"Ke"=>11520,"Ww"=>4470,"Yg"=>13920];
				}
				if ($mjd[$i]>58848){ // greater than Jan 2020
					$SEFDbystX = ["Hb"=>13150,"Ke"=>13520,"Ww"=>1840,"Yg"=>12190];
				}
				$SEFD1 = $SEFDbystX[$_station1];
				$SEFD2 = $SEFDbystX[$_station2];
				$c01 = $c0byst[$_station1];
				$c02 = $c0byst[$_station2];
				$c11 = $c1byst[$_station1];
				$c12 = $c1byst[$_station2];
			}
			elseif($band[$i] == "S"){
				$c0byst = ["Hb"=>0.974,"Ke"=>0.974,"Ww"=>0.988,"Yg"=>0.975];
				$c1byst = ["Hb"=>0.026,"Ke"=>0.028,"Ww"=>0.013,"Yg"=>0.026];
				
				if ($mjd[$i]<57022){ // 31 Dec 2014
					$SEFDbystS = ["Hb"=>5500,"Ke"=>4920,"Ww"=>4310,"Yg"=>3970];
				}
				if ($mjd[$i]>57022 && $mjd[$i]<57387){ // 31 Dec 2015
					$SEFDbystS = ["Hb"=>5730,"Ke"=>5210,"Ww"=>3980,"Yg"=>4660];
				}
				if ($mjd[$i]>57387 && $mjd[$i]<57753){ // 31 Dec 2016
					$SEFDbystS = ["Hb"=>4450,"Ke"=>5190,"Ww"=>5570,"Yg"=>5010];
				}
				if ($mjd[$i]>57753 && $mjd[$i]<58118){ // 31 Dec 2017
					$SEFDbystS = ["Hb"=>5010,"Ke"=>5230,"Ww"=>4920,"Yg"=>4790];
				}
				if ($mjd[$i]>58118 && $mjd[$i]<58483){ // 31 Dec 2018
					$SEFDbystS = ["Hb"=>14470,"Ke"=>5000,"Ww"=>6560,"Yg"=>4990];
				}
				if ($mjd[$i]>58483 && $mjd[$i]<58848){ // 31 Dec 2019
					$SEFDbystS = ["Hb"=>17650,"Ke"=>6460,"Ww"=>4310,"Yg"=>6418];
				}
				if ($mjd[$i]>58848){ // greater than Jan 2020
					$SEFDbystS = ["Hb"=>5250,"Ke"=>5260,"Ww"=>4700,"Yg"=>6400];
				}
				$SEFD1 = $SEFDbystS[$_station1];
				$SEFD2 = $SEFDbystS[$_station2];
				$c01 = $c0byst[$_station1];
				$c02 = $c0byst[$_station2];
				$c11 = $c1byst[$_station1];
				$c12 = $c1byst[$_station2];
			}
*/
			//SEFD with elevation dependecy
			if($elvdep1=="S" || $elvdep1=="X"){
				$tmpelv1 = pow(sin(deg2rad($elev1[$i])),$y1);
				$tmpelv1_2 = $c01 + ($c11/$tmpelv1);
				if($tmpelv1_2>1){
					$SEFD1 = $SEFD1*$tmpelv1_2;
					$SEFD1 = number_format($SEFD1,2,'.','');
				}
			}
			if($elvdep2=="S" || $elvdep2=="X"){
				$tmpelv2 = pow(sin(deg2rad($elev2[$i])),$y2);
				$tmpelv2_2 = $c02 + ($c12/$tmpelv2);
				if($tmpelv2_2>1){
					$SEFD2 = $SEFD2*$tmpelv2_2;
					$SEFD2 = number_format($SEFD2,2,'.','');
				}
			}
//*/			
			array_push($this->SEFD1,$SEFD1);
			array_push($this->SEFD2,$SEFD2);

			//identify model type
			$flux = preg_replace('/\s+/', ' ', $cflux);
			$flux = explode(" ", $flux);
			$type = $flux[2];
			if($type==""){
				$type = "N";
			}

			$flcon1 = (M_PI * M_PI)/(4 * log(2));
			$flcon2 = M_PI / (3600.0 * 180.0 * 1000.0); //convert from milliarc second to radian
			$gmst = $this->tgmst($mjd[$i]);
			$gha = $gmst-$ra;

			$u = $blx * sin($gha) + $bly * cos($gha);
			$v = $blz * cos($de) + sin($de) * (-$blx * cos($gha) + $bly * sin($gha));

			//local hour angle = gha-longitude
			$ha = $gha-deg2rad($plon1);
			$elevation1 = rad2deg(asin(sin($de)*sin(deg2rad($plat1))+cos($de)*cos(deg2rad($plat1))*cos($ha)));
			$ha = $gha-deg2rad($plon2);
			$elevation2 = rad2deg(asin(sin($de)*sin(deg2rad($plat2))+cos($de)*cos(deg2rad($plat2))*cos($ha)));

			if($elev1[$i] <= 0){
				//echo $elevation1;
				$elev1[$i] = $elevation1;
				$this->elv1[$i] = round($elevation1,1);
			}
			if($elev2[$i] <= 0){
				$elev2[$i] = $elevation2;
				$this->elv2[$i] = round($elevation2,1);
			}

			array_push($this->model,$type);

			//type B
			$pbase = sqrt($u*$u + $v*$v) / 1000.0; //projected baseline
			array_push($this->pbase,$pbase);
			if ($type == "B"){
				//$pbase = sqrt($u*$u + $v*$v) / 1000.0; //projected baseline
				for ($j=5;$j<count($flux);$j+=2){
					if($pbase <=$flux[$j]){
						$Fobs = $flux[$j-1];
						break;
					}
				}
				//"Projected Baseline: ".$pbase."<br>";
			}

			//type M
			//VieSched++
			if ($type == "M"){
				if($band[$i]=="X"){
					$u = $u/$wavelength["X"];
					$v = $v/$wavelength["X"];
				}
				elseif($band[$i]=="S"){
					$u = $u/$wavelength["S"];
					$v = $v/$wavelength["S"];
				}

				$majax = $flux[4]*$flcon2;
				$ratio = $flux[5];
				$pa = $flux[6] * M_PI/180;

				$ucospa = $u * cos($pa);
				$usinpa = $u * sin($pa);
				$vcospa = $v * cos($pa);
				$vsinpa = $v * sin($pa);
				$arg1 = ($vcospa + $usinpa) * ($vcospa + $usinpa);
				$arg2 = ($ucospa - $vsinpa) * ($ucospa - $vsinpa);
				$l = sqrt($arg1 + ($ratio*$ratio) * $arg2);

				$up = (M_PI * $majax * $l) * (M_PI * $majax * $l);
				$Fobs = $flux[3] * exp(-($up)/(4*log(2)));
			}
			array_push($this->flux,$flux);
			array_push($this->fobs,$Fobs);

			//---------------expected SNR calculation------------
			//$n = 0.6366; //2-bit 0.881
			//$n = 0.5715; //1-bit 0.637
			//$coreff = 0.97; //DiFX
			//$coreff = 0.8995;
			
			//$eta = $n*$coreff;
			if($bitnum[$i]==1){
				$eta=0.6366*0.97;
			}
			else{
				$eta=0.625*0.97; //values from VieSched++
			}
			$usenumchan = 0;
			$bandeff = 0;
			if($band[$i]=="X"){
				//vgos baselines
				if( (in_array($_station1,$vgosstations)) && (in_array($_station2,$vgosstations)) ){ //vgos baselines
					$usenumchan = $NumChannels[$i]*2; //two polarisation
					$bandeff = 1;//bandpass effect
				}
				//mixed baselines
				if( (in_array($_station1,$vgosstations) && !in_array($_station2,$vgosstations)) || (in_array($_station2,$vgosstations) && !in_array($_station1,$vgosstations))){ 
					$usenumchan = $NumChannels[$i];
					//$bandeff = 0.75;//bandpass effect ~12/16 MHz
					$bandeff = 0.785;//bandpass effect ~12.56/16 MHz (updated)
				}
				//legacy baselines
				if( (!in_array($_station1,$vgosstations) && !in_array($_station2,$vgosstations))){ 
					if(!empty($sideband)){ //if from VgosDb
						$usenumchan = substr_count($sideband[$i], 'u') + substr_count($sideband[$i], 'l');
					}
					else{
						//assume 0 lower side band if numchan < 7, 1 when numchan = 7, 2 when numchan >= 8
						if ($NumChannels[$i]>=8){
							$usenumchan = $NumChannels[$i]+2;
						}
						elseif ($NumChannels[$i]==7){
							$usenumchan = $NumChannels[$i]+1;
						}
						else{
							$usenumchan = $NumChannels[$i];
						}
					}
					//$bandeff = 0.75;//bandpass effect ~12/16 MHz
					$bandeff = 0.785;//bandpass effect ~12.56/16 MHz (updated)
				}
			}
			elseif($band[$i]=="S"){
				//vgos baselines
				if( (in_array($_station1,$vgosstations)) && (in_array($_station2,$vgosstations)) ){ //vgos baselines
					$usenumchan = $NumChannels[$i]*2; //two polarisation
					//$bandeff = 0.875;//bandpass effect ~14/16 MHz
					$bandeff = 0.821;//bandpass effect ~13.13/16 MHz (updated)
				}
				//mixed baselines
				if( (in_array($_station1,$vgosstations) && !in_array($_station2,$vgosstations)) || (in_array($_station2,$vgosstations) && !in_array($_station1,$vgosstations))){ 
					$usenumchan = $NumChannels[$i];
					$bandeff = 0.732;//bandpass effect ~11.72/16 MHz (updated)
				}
				//legacy baselines
				if( (!in_array($_station1,$vgosstations) && !in_array($_station2,$vgosstations))){ 
				//else{ //mixed and legacy baselines
					$usenumchan = $NumChannels[$i];
					//$bandeff = 0.75;//bandpass effect ~12/16 MHz
					$bandeff = 0.785;//bandpass effect ~12.56/16 MHz (updated)
				}
				//$SampleRate = 2 * $this->Bw * $bitnum[$i] * $NumChannels[$i]; //Nyquist * Bw * bit * NumChannels
			}

			$SampleRate = 2 * $this->Bw * $bitnum[$i] * $usenumchan * $bandeff; //Nyquist * Bw * bit * NumChannels * bandeff
			$rho = $Fobs/sqrt($SEFD2*$SEFD1);
			$numSamp = sqrt($SampleRate * $Scanlength[$i]); //SampleRate * Scanlength
			$SNR = $eta*$rho*$numSamp;
			//if( (in_array($_station1,$vgosstations)) && (in_array($_station2,$vgosstations)) ){ //vgos baselines
			//	$SNR = sqrt(2) * $SNR;
			//}

			array_push($this->apsnr,$SNR);
			array_push($this->SampleRate,$SampleRate);

			//re-estimate SEFD
			//store scan flux and snr of onescan
			
			if($mjd[$i]===$mjd[$i+1] && $sources[$i]===$sources[$i+1]){
				if($_station1!=="" && $_station2!==""){ //store
					//old method (if only 3 or less stations)
					$onescansnrold[$_station1.$_station2] = [$Fobs,$obsSNR[$i],$numSamp];
					//lsq method
					if(!isset($stSEFD[$_station1])){
						$stSEFD[$_station1] = $SEFD1;
					}
					if(!isset($stSEFD[$_station2])){
						$stSEFD[$_station2] = $SEFD2;
					}
					$onescansnr[$_station1.$_station2] = $obsSNR[$i]/$SNR;
					array_push($oscountarr,$_station1.$_station2);

				}
				else{ //skip, add to countline
					array_push($oscountarr,"0000"); //set null stations
				}
				
			}
			else{
				if($_station1!=="" && $_station2!==""){ //re-estimate with current

					//old method (if only 3 or less stations)
					$onescansnrold[$_station1.$_station2] = [$Fobs,$obsSNR[$i],$numSamp];
					
					//lsq method
					if(!isset($stSEFD[$_station1])){
						$stSEFD[$_station1] = $SEFD1;
					}
					if(!isset($stSEFD[$_station2])){
						$stSEFD[$_station2] = $SEFD2;
					}
					
					$onescansnr[$_station1.$_station2] = $obsSNR[$i]/$SNR;

					array_push($oscountarr,$_station1.$_station2);
					
					
				}
				else{ //re-estimate without current
					array_push($oscountarr,"0000"); //set null stations
				}
				//re-estimate
				if(count($stSEFD)<=3){
					//old method (if only 3 or less stations)
					foreach($onescansnrold as $oskey => $ossnr){
						//$rhore = $ossnr[1]/($eta*$numSamp);
						$oskeyst1 = substr($oskey,0,2);
						$oskeyst2 = substr($oskey,2,2);
						$rhore = $eta * ($ossnr[0]/$ossnr[1]) * $ossnr[2];

						//if( (in_array($oskeyst1,$vgosstations)) && (in_array($oskeyst2,$vgosstations)) ){ //vgos baselines
						//	$realsnr = $ossnr[0]*sqrt(2);
						//}
						//else{
						//	$realsnr = $ossnr[0];
						//}
						//$obsefdsefd = ($ossnr[0]/$rhore)*($ossnr[0]/$rhore);
						//$obsefdsefd = ($realsnr/$rhore)*($realsnr/$rhore); //old

						$obsefdsefd = $rhore*$rhore; //corrected
						array_push($restr,$oskey.":".$obsefdsefd);

					}
					$osout = $ree->rees(implode(",",$restr));
				}
				else{
					//new lsq method
					foreach($stSEFD as $oskey => $ossefd){
						array_push($restrsefd,$oskey.":".$ossefd);
					}
					foreach($onescansnr as $oskey => $ossnr){
						array_push($restrsnr,$oskey.":".$ossnr);
					}
					$osout = $ree->lsqrees(implode(",",$restrsefd),implode(",",$restrsnr));
				}

				
				//add to line
				for($j=0;$j<count($oscountarr);$j++){
					$_osst1 = substr($oscountarr[$j],0,2);
					$_osst2 = substr($oscountarr[$j],2,2);
					$count1 = 0;
					$count2 = 0;
					foreach($osout as $osokey => $ososefd){
						if($osokey==$_osst1){
							array_push($this->reessefd1,$ososefd);
							$count1 = 1;
						}
						elseif($osokey==$_osst2){
							array_push($this->reessefd2,$ososefd);
							$count2 = 1;
						}
					}
					if($count1==0){
						array_push($this->reessefd1,0);
					}
					if($count2==0){
						array_push($this->reessefd2,0);
					}
				}
				//reset tmp var
				$onescansnr = [];
				$onescansnrold = [];
				$restr = [];
				$restrsefd = [];
				$restrsnr = [];
				$oscountarr = [];
				$stSEFD = [];
			}
		}
		
		$wid = fopen("snrr/".$this->analysingfile.".snrr","w+");
		$scancount = 0;
		$writearr = [];

		fwrite($wid,"#mjd         source   dur bl    Pbaseline pol SampleRate elv1   elv2   flux    Mod obsnr  apsnr    snrr    SEFD1   SEFD2   reSEFD1 reSEFD2".PHP_EOL);
		
		for($i=0;$i<count($this->apsnr);$i++){
			//write only when at least 3 obs available
			/*
			$scancount = 0;
			if ($this->sources[$i] == $this->sources[$i+1]){
				$scancount = $scancount + 1;
			}
			if ($this->sources[$i] == $this->sources[$i+2]){
				$scancount = $scancount + 1;
			}
			if ($this->sources[$i] == $this->sources[$i-1]){
				$scancount = $scancount + 1;
			}
			if ($this->sources[$i] == $this->sources[$i-2]){
				$scancount = $scancount + 1;
			}
			*/
			array_push($writearr,number_format($this->mjd[$i],6,'.','')." ".str_pad($this->sources[$i],8," ",STR_PAD_LEFT)." ".str_pad($this->Scanlength[$i],3," ",STR_PAD_LEFT)." ".str_pad($this->baseline[$i],5," ",STR_PAD_LEFT)." ".str_pad(number_format($this->pbase[$i],2,'.',''),9," ",STR_PAD_LEFT)." ".$this->pol[$i]." ".str_pad($this->SampleRate[$i],10," ",STR_PAD_LEFT)." ".str_pad($this->elv1[$i],6," ",STR_PAD_LEFT)." ".str_pad($this->elv2[$i],6," ",STR_PAD_LEFT)." ".str_pad(number_format($this->fobs[$i],4,'.',''),8," ",STR_PAD_LEFT)." ".$this->model[$i]." ".str_pad(number_format($this->obsSNR[$i],2,'.',''),6," ",STR_PAD_LEFT)." ".str_pad(number_format($this->apsnr[$i],4,'.',''),8," ",STR_PAD_LEFT)." ".number_format($this->obsSNR[$i]/$this->apsnr[$i],4,'.','')." ".str_pad($this->SEFD1[$i],8," ",STR_PAD_LEFT)." ".str_pad($this->SEFD2[$i],8," ",STR_PAD_LEFT)." ".str_pad(number_format($this->reessefd1[$i],2,'.',''),8," ",STR_PAD_LEFT)." ".str_pad(number_format($this->reessefd2[$i],2,'.',''),8," ",STR_PAD_LEFT).PHP_EOL);

			//if($scancount>=2){
				//fwrite($wid,number_format($this->mjd[$i],6,'.','')." ".str_pad($this->sources[$i],8," ",STR_PAD_LEFT)." ".str_pad($this->Scanlength[$i],3," ",STR_PAD_LEFT)." ".str_pad($this->baseline[$i],5," ",STR_PAD_LEFT)." ".str_pad(number_format($this->pbase[$i],2,'.',''),9," ",STR_PAD_LEFT)." ".$this->pol[$i]." ".str_pad($this->SampleRate[$i],10," ",STR_PAD_LEFT)." ".str_pad($this->elv1[$i],6," ",STR_PAD_LEFT)." ".str_pad($this->elv2[$i],6," ",STR_PAD_LEFT)." ".str_pad(number_format($this->fobs[$i],4,'.',''),8," ",STR_PAD_LEFT)." ".$this->model[$i]." ".str_pad(number_format($this->obsSNR[$i],2,'.',''),6," ",STR_PAD_LEFT)." ".str_pad(number_format($this->apsnr[$i],4,'.',''),8," ",STR_PAD_LEFT)." ".number_format($this->obsSNR[$i]/$this->apsnr[$i],4,'.','')." ".str_pad($this->SEFD1[$i],8," ",STR_PAD_LEFT)." ".str_pad($this->SEFD2[$i],8," ",STR_PAD_LEFT)." ".str_pad(number_format($this->reessefd1[$i],2,'.',''),8," ",STR_PAD_LEFT)." ".str_pad(number_format($this->reessefd2[$i],2,'.',''),8," ",STR_PAD_LEFT).PHP_EOL);
			//}
		}

		//remove duplicates
		$uniqwritearr=array_unique($writearr);
		fwrite($wid,implode($uniqwritearr));
		fclose($wid);
		chmod($this->analysingfile.".snrr",0777);
	}


}
?>
