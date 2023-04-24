<?php
require_once "../common/SSHcon.php";
require_once "../common/global.php";
require_once "modivex.php";
require_once "modiv2d.php";
require_once "modilist.php";
require_once "getinfo.php";
require_once "../common/dbwrite.php";

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

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

//$C = new Precorrelate;
//$C->copyvex();

class Precorrelate {

	public $cserver;
	public $cusername;
	public $cpassword;
	public $cmode;
	public $exper;
	public $dynexp;
	public $explabel;
	public $corrdir;
	public $expcode;
	public $cstations;
	public $ginfoout;

	//run setup() first
	function globalvars(){
		$this->cserver = $GLOBALS["cserver"];
		$this->cusername = $GLOBALS["cusername"];
		$this->cpassword = $GLOBALS["cpassword"];
		$this->cmode = $_SESSION["cmode"];
		$this->exper = $_SESSION["exper"];
		$this->dynexp = $_SESSION["dynexp"];
		$this->explabel = $_SESSION["explabel"];
		$this->corrdir = $_SESSION["corrdir"];
		$this->expcode = $_SESSION["expcode"];
		$this->cstations = $_SESSION["cstations"];
		$this->ginfoout = $_SESSION["ginfoout"];
	}

	//-----------------------------------------------------------------------------------------------

	function setup(){
		//$_SESSION["precor_message"] = "Pre-correlation started".PHP_EOL;
		$_SESSION["precor_message"] = "";

		//save to sessions
		if ($_GET["exper"]){ //if not input, assume session isset from previous
			$_SESSION["exper"] = $_GET["exper"];
		}
		$_pgexp = $_SESSION["exper"];
		if(!isset($_SESSION["exper"])){
			$message = "Exit: no experiment specified".PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}

		if(count(explode(",",$_pgexp))>1){
			$dynexp = explode(",",$_pgexp);
			$exper = "dyn".date("YmdHi");
		}
		else{
			$exper = $_pgexp;
			$dynexp = [$exper]; //for other experiments to work
		}
		//dynexp is the array of multiple short sessions
		//$exper is the common name for all combined short sessions
		$_SESSION["exper"] = $exper;
		$_SESSION["dynexp"] = $dynexp;

		//setup correlation directory
		switch(substr($exper,0,2)){
			case "mf":
				$corrdir = $GLOBALS["corrdir"]."/mf/";
				$explabel = "mf";
			break;
			case "dy":
				$corrdir = $GLOBALS["corrdir"]."/dyn/";
				$explabel = "dyn";
			break;
			case "si":
				$corrdir = $GLOBALS["corrdir"]."/si/";
				$explabel = "si";
			break;
			default:
				$corrdir = $GLOBALS["corrdir"]."/au/";
				$explabel = "default";
			break;
		}
		if($explabel == "default"){ //further check for SI
			if(substr($exper,0,1) == "z"){
				$corrdir = $GLOBALS["corrdir"]."/si/";
				$explabel = "si";
			}
		}
		$_SESSION["corrdir"] = $corrdir;
		$_SESSION["explabel"] = $explabel;
		//create tmp folder on webserver for tmp/buffer files
		if ( !is_dir( '../tmp/'.$exper )) {
			mkdir('../tmp/'.$exper);
			chmod('../tmp/'.$exper,0777);      
		}

		if ($_GET["cmode"]){
			$_SESSION["cmode"] = $_GET["cmode"];
			$cmode = $_SESSION["cmode"];
		}
		else{
			if(isset($_SESSION["cmode"])){
				$cmode = $_SESSION["cmode"];
			}
			else{
				if($explabel == "mf"){
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
			}
		}
		$_SESSION["cmode"] = $cmode;

		//assign expcode
		$db = new DbWrite;
		if($explabel!=="mf"){
			$exppre = substr($exper,0,4);
			$expprereal = "";
			foreach($GLOBALS["expcodepre"] as $_expkey=>$_exppre){
				if (stripos($exppre,$_expkey)!==false){
					$expprereal = $_expkey;
					$dbq = $db->query("dynob",'SELECT expcode FROM Correlation WHERE prefix="'.$expprereal.'" ORDER BY id DESC LIMIT 1');
					if($dbq=="null"){
						$expcode = $GLOBALS["expcodepre"][$expprereal]."01";
					}
					else{
						$expcode = $dbq[0]["expcode"]+1;
					}
					break;
				}
			}
			if($expprereal == ""){
				$expcode = "1234";
				$expprereal = substr($exper,0,3);
				if($explabel == "si"){
					$expcode = substr($exper,-4);
				}
			}
			
			/*
			if(array_key_exists($exppre,$GLOBALS["expcodepre"])){
				$dbq = $db->query("dynob",'SELECT expcode FROM Correlation WHERE prefix="'.$exppre.'" ORDER BY id DESC LIMIT 1');
				if($dbq=="null"){
					$expcode = $GLOBALS["expcodepre"][$exppre]."01";
				}
				else{
					$expcode = $dbq[0]["expcode"]+1;
				}
			}
			else{
				$expcode = "1234";
			}
			*/


			$dbq = $db->query("dynob",'SELECT expcode FROM Correlation WHERE session="'.$exper.'"');
			if($dbq=="null"){
				$db->insert("dynob","Correlation",["session","prefix","expcode"],[$exper,$expprereal,$expcode]);
			}
		}
		else{
			$expcode = "1234";
		}
		$_SESSION["expcode"] = $expcode;

		//download usno from bkg
		//https://ivs.bkg.bund.de/data_dir/vlbi/gsfc/ancillary/solve_apriori/usno_finals.erp
		
		//exec("curl --ssl-reqd -u anonymous:anonymous -O ftp://ivs.bkg.bund.de/pub/vlbi/gsfc/ancillary/solve_apriori/usno_finals.erp");
		//$file_name = $_SERVER['DOCUMENT_ROOT']."tmp/usno_finals.erp";
		//if(!rename("usno_finals.erp",$file_name)){
		//	$message = "failed to download usno_finals.erp from the bkg server".PHP_EOL;
		//	$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
		//}
		
		$usnobkg = "https://ivs.bkg.bund.de/data_dir/vlbi/gsfc/ancillary/solve_apriori/usno_finals.erp";
		//$usnobkg = "ftp://ivs.bkg.bund.de/pub/vlbi/gsfc/ancillary/solve_apriori/usno_finals.erp";
		$file_name = $_SERVER['DOCUMENT_ROOT']."tmp/usno_finals.erp";
		if(!file_put_contents($file_name,file_get_contents($usnobkg))){
			$message = "failed to download usno_finals.erp from the bkg server".PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
		}
	}

	function checkdir(){
		//check if correlation directory exists
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->cserver,$this->cusername,$this->cpassword))){
			$message = "Exit: Unable to reach ".$this->cserver.PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}
		$sftp = ssh2_sftp($con);
		$_str = file_exists('ssh2.sftp://'.$sftp.$this->corrdir.$this->exper);

		if(!$_str){
			ssh2_sftp_mkdir($sftp, $this->corrdir.$this->exper);
			ssh2_sftp_chmod($sftp, $corrdir.$exper, 0777);
		}
		//$_str = $conssh->exec($con,"cd ".$this->corrdir.";ls");
		//if (!stripos($_str,$this->exper)){
		//	$sftp = ssh2_sftp($con);
		//	ssh2_sftp_mkdir($sftp, $this->corrdir.$this->exper);
		//	ssh2_sftp_chmod($sftp, $corrdir.$exper, 0777);
		//}
		$corrdir = $this->corrdir.$this->exper."/";
		$_SESSION["corrdir"] = $corrdir;
		ssh2_disconnect($con);
	}

	function getdataloc(){
		//run Getinfo::locate once and store in vars (ustations,uraid,ufull)
		$ginfoout = Getinfo::locate($this->dynexp,$this->corrdir,$this->explabel);
		$cstations = $ginfoout["ustations"];
		$_SESSION["ginfoout"] = $ginfoout;
		if($_GET["cstations"]){
			$_csp = explode(",",$_GET["cstations"]);
			$_cs = [];
			foreach ($_csp as $csp){
				if(in_array($csp,$cstations)){
					array_push($_cs,$csp);
				}
			}
			$cstations = $_cs;
		}
		$_SESSION["cstations"] = $cstations;

		//$message = "Exit: here".$this->dynexp." ".$this->corrdir." ".$this->explabel."Hi".PHP_EOL;
		//$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
		//die();

		if (count($cstations)<2){
			$message = "Exit: No baseline to correlate".PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}
	}

	function copyvex(){
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->cserver,$this->cusername,$this->cpassword))){
			$message = "Exit: Unable to reach ".$this->cserver.PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}
		//----copy threads from template
		$_dir = $_SERVER['DOCUMENT_ROOT']."correlations/template/";
		ssh2_scp_send($con,$_dir."threads", $this->corrdir."threads", 0755);

		//ctag for mixed-mode fringe test (Yg is flexbuff or mk5)
		if ($this->cmode == "mixed"){
			if ($this->explabel == "mf"){
				$ctag = "fringe";
				if($this->exper == "mfdynf"){
					$ctag = "dynfringe";
				}
				else{
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
					
					//detect recorder
					$recdir = new SplFileObject($_SERVER['DOCUMENT_ROOT']."scheduling/fringeout/".$realtimedb['fringe_scheddir']."/expinfo.txt");
					$recdir->seek(0);
					while (!$recdir->eof()) {
						$line = $recdir->fgets();
						if(strlen($line)>0){
							if (stripos($line,"mk5")!==false){
								$ctag = "mixfringe";
							}
						}
					}
				}
			}
		}
		

		//----get, modify, send, remove each vex (get skd as well)-----
		$vexfile = [];
		$tagvex = "";
		$tagskd = "";

		for ($i=0;$i<count($this->cstations);$i++){
			if(in_array($this->cstations[$i],$GLOBALS["localstations"])){
				if($this->cstations[$i] =="ho"){
					$conpcfs = $conssh->connect("hobart",$GLOBALS["pcfsuser"],$this->cpassword);
				}
				else{
					$conpcfs = $conssh->connect("pcfs".$this->cstations[$i],$GLOBALS["pcfsuser"],$this->cpassword);
				}
				if(!($conpcfs)){
					continue;
				}
				else{
					$tagvex = "pcfs";
					$tagskd = "pcfs";
					break;
				}
			}
		}
		if ($tagvex ==""){
			$tagvex = "ivs";
		}
		if ($tagskd ==""){
			$tagskd = "ivs";
		}

		//skd first
		foreach($this->dynexp as $_dynexper){
			if(!file_exists($_SERVER['DOCUMENT_ROOT']."scheduling/skd/".$_dynexper.'.skd')){
				if ($tagskd == "pcfs"){
					$scpboolskd = ssh2_scp_recv($conpcfs, $GLOBALS["skdlocation"].$_dynexper.'.skd', $_SERVER['DOCUMENT_ROOT']."scheduling/skd/".$_dynexper.'.skd');
					if(!$scpboolskd){
						$tagskd = "ivs";
						break;
					}
				}
				elseif($tagskd == "ivs"){
					$guessdt = 2020;
					//$_SESSION["progress"] = "downloading vex from IVS";
					while ($guessdt<2040){
						$urlfrombkg = "https://ivs.bkg.bund.de/data_dir/vlbi/ivsdata/aux/".$guessdt."/".$_dynexper."/".$_dynexper.".skd";
						//$urlfrombkg = "ftp://ivs.bkg.bund.de/pub/vlbi/ivsdata/aux/".$guessdt."/".$_dynexper."/".$_dynexper.".skd";
						$file_name = $_SERVER['DOCUMENT_ROOT']."scheduling/skd/".$_dynexper.'.skd';
						if (!file_exists($file_name)){
							$handle = @fopen($urlfrombkg, 'r');
							if(!$handle){
								$guessdt = $guessdt + 1;
							}
							else{
								if(file_put_contents($file_name,file_get_contents($urlfrombkg))){
									break;
								}
								else{
									$message = "Exit: ".$urlfrombkg.PHP_EOL."
												No access to pcfs or no skd file found".PHP_EOL;
									$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
									die();
								}
							}
						}
					}
				}
			}
		}

		if ($tagvex == "pcfs"){
			foreach($this->dynexp as $_dynexper){
				$scpbool = ssh2_scp_recv($conpcfs, $GLOBALS["skdlocation"].$_dynexper.'.vex', $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.'.vex');
				if(!$scpbool){
					$tagvex = "ivs";
					break;
				}
				else{
					array_push($vexfile,$_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.'.vex');
				}
			}
		}

		if ($tagvex == "ivs"){
			foreach($this->dynexp as $_dynexper){
				$guessdt = 2022;
				//$_SESSION["progress"] = "downloading vex from IVS";
				while ($guessdt<2040){
					$urlfrombkg = "https://ivs.bkg.bund.de/data_dir/vlbi/ivsdata/aux/".$guessdt."/".$_dynexper."/".$_dynexper.".vex";
					//$urlfrombkg = "ftp://ivs.bkg.bund.de/pub/vlbi/ivsdata/aux/".$guessdt."/".$_dynexper."/".$_dynexper.".vex";
					$file_name = $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.'.vex';
					if (!file_exists($file_name)){
						$handle = @fopen($urlfrombkg, 'r');
						if(!$handle){
							$guessdt = $guessdt + 1;
						}
						else{
							if(file_put_contents($file_name,file_get_contents($urlfrombkg))){
								ssh2_scp_send($con, $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.'.vex', $this->corrdir.$_dynexper.'.vex', 0755);
								array_push($vexfile,$_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.'.vex');
								break;
							}
							else{
								$message = "Exit: ".$urlfrombkg.PHP_EOL."
											No access to pcfs or no vex file found".PHP_EOL;
								$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
								die();
							}
						}
					}
					else{
						ssh2_scp_send($con, $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.'.vex', $this->corrdir.$_dynexper.'.vex', 0755);
						array_push($vexfile,$_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.'.vex');
					}
				}
			}
		}
		
		if(count($vexfile)>0){
			//modify vex
            Modivex::vex($this->exper,$vexfile,$this->cmode,$ctag);
            //send vex
            //ssh2_scp_send($con, $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".'buffer.vex', $this->corrdir.$this->exper.'.vex', 0755);
		}

		ssh2_disconnect($conpcfs);
		ssh2_disconnect($con);

	}

	function getlog(){
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->cserver,$this->cusername,$this->cpassword))){
			$message = "Exit: Unable to reach ".$this->cserver.PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}
		//get log files
		$epstart = Getinfo::vexstart();
		$eptmp = date_parse_from_format("Y.z.H.i.s", $epstart);
		//$eptmp["month"], $eptmp["day"], $eptmp["year"]

		foreach($this->cstations as $cs){
			$logfile = [];
			//get from IVS server if not from local stations
			if(!in_array($cs,$GLOBALS["localstations"])){
				if($this->exper=="mfdynf"){
					//assume /disk2/ht on flexbuf only 
					if($conrec = $conssh->connect("flexbuf",$this->cusername,$this->cpassword)){
						foreach($this->dynexp as $_dynexper){
							$scpbool = ssh2_scp_recv($conrec, '/disk2/ht/mfdynf'.$cs.'.log', $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.$cs.'.log');
							if(!$scpbool){
								$message = $_dynexper.$cs." log file does not exist".PHP_EOL;
								$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
								echo $message;
							}
							else{
								array_push($logfile,$_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.$cs.'.log');
							}
						}
					}
				}
				else{
					foreach($this->dynexp as $_dynexper){
						//new
						/*
						exec("curl --ssl-reqd -u anonymous:anonymous -O ftp://ivs.bkg.bund.de/pub/vlbi/ivsdata/aux/".$eptmp['year']."/".$_dynexper."/".$_dynexper.$cs.".log");
						$file_name = $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.".".$cs.'.log';
						if (!file_exists($file_name)){
							if(!rename($_dynexper.$cs.".log",$file_name)){
								$message = "fail to download $_dynexper.$cs.log".PHP_EOL;
								$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
								echo $message;
							}
							else{
								array_push($logfile,$file_name);
							}
						}
						*/
						//old
						
						$urlfrombkg = "https://ivs.bkg.bund.de/data_dir/vlbi/ivsdata/aux/".$eptmp['year']."/".$_dynexper."/".$_dynexper.$cs.".log";
						//$urlfrombkg = "ftp://ivs.bkg.bund.de/pub/vlbi/ivsdata/aux/".$eptmp['year']."/".$_dynexper."/".$_dynexper.$cs.".log";
						$file_name = $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.".".$cs.'.log';
						if (!file_exists($file_name)){
							if(file_put_contents($file_name,file_get_contents($urlfrombkg))){
								array_push($logfile,$file_name);
							}
							else{
								$message = "fail to download $_dynexper.$cs.log".PHP_EOL;
								$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
								echo $message;
							}
						}
						
					}
				}

			}
			else{ //get log from pcfs if local
				if($cs == "ho"){
					$conpcfs = $conssh->connect("hobart",$GLOBALS["pcfsuser"],$this->cpassword);
				}
				else{
					$conpcfs = $conssh->connect("pcfs".$cs,$GLOBALS["pcfsuser"],$this->cpassword);
				}
				if(!($conpcfs)){
					foreach($this->dynexp as $_dynexper){
						//curl method
						/*
						exec("curl --ssl-reqd -u anonymous:anonymous -O ftp://ivs.bkg.bund.de/pub/vlbi/ivsdata/aux/".$eptmp['year']."/".$_dynexper."/".$_dynexper.$cs.".log");
						$file_name = $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.".".$cs.'.log';
						if (!file_exists($file_name)){
							if(!rename($_dynexper.$cs.".log",$file_name)){
								$message = "fail to download $_dynexper.$cs.log".PHP_EOL;
								$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
								echo $message;
							}
							else{
								array_push($logfile,$file_name);
							}
						}
						*/
						$urlfrombkg = "https://ivs.bkg.bund.de/data_dir/vlbi/ivsdata/aux/".$eptmp['year']."/".$_dynexper."/".$_dynexper.$cs.".log";
						//$urlfrombkg = "ftp://ivs.bkg.bund.de/pub/vlbi/ivsdata/aux/".$eptmp['year']."/".$_dynexper."/".$_dynexper.$cs.".log";
						$file_name = $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.".".$cs.'.log';
						if (!file_exists($file_name)){
							if(file_put_contents($file_name,file_get_contents($urlfrombkg))){
								array_push($logfile,$file_name);
							}
							else{
								$message = "fail to download $_dynexper.$cs.log".PHP_EOL;
								$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
								echo $message;
							}
						}
						
					}
				}
				else{
					foreach($this->dynexp as $_dynexper){
						$scpbool = ssh2_scp_recv($conpcfs, $GLOBALS["loglocation"].$_dynexper.$cs.'.log', $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.$cs.'.log');
						if(!$scpbool){
							$message = $_dynexper.$cs." log file does not exist".PHP_EOL;
							$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
							echo $message;
						}
						else{
							array_push($logfile,$_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$_dynexper.$cs.'.log');
						}
					}
				}
			}
			if(count($logfile)>0){
				//modify log
				exec("cat ".implode(" ",$logfile)." > ".$_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$cs."buffer.log");
				//send vex
				ssh2_scp_send($con, $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".$cs.'buffer.log', $this->corrdir.$this->exper.$cs.'.log', 0755);
				//remove log
				foreach($logfile as $lg){
					unlink($lg);
				}
			}
			ssh2_disconnect($conpcfs);
			
		}

		//modivex freq part
		Modivex::vexfreq($_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/",$this->cmode);
        //send vex
        ssh2_scp_send($con, $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".'updatedfreqs.vex', $this->corrdir.$this->exper.'.vex', 0755);
		unlink($_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".'buffer.vex');
		copy($_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".'updatedfreqs.vex',$_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".'buffer.vex');

		ssh2_disconnect($con);
	}

	function getv2d(){
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->cserver,$this->cusername,$this->cpassword))){
			$message = "Exit: Unable to reach ".$this->cserver.PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}
		//----Create v2d----
		Modiv2d::create($this->exper,$this->cmode,$this->cstations,$this->ginfoout["ufull"]);
		//send v2d
		ssh2_scp_send($con, $_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/".'buffer.v2d', $this->corrdir.$this->exper.'.v2d', 0755);

		//write correlation params
		//$_SESSION["progress"] = "creating correlation parameters";
		$corparam = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/"."corrparam.txt", "a+");
		foreach ($this->ginfoout["ufull"] as $_line){
			fwrite($corparam, $_line."\n");
		}
		fclose($corparam);

		ssh2_disconnect($con);
	}

	function genfilelist(){
		//create filelist
		Modilist::createfilelist($this->exper,$this->explabel,$this->cmode,$this->corrdir,$this->cstations,$this->ginfoout["uraid"],$this->ginfoout["ufull"]);
	}

	function dxcalc(){
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->cserver,$this->cusername,$this->cpassword))){
			$message = "Exit: Unable to reach ".$this->cserver.PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}
		//vex2difx
		ob_start();
		$echosilent = $conssh->exec($con,"cd ".$this->corrdir.";vex2difx ".$this->exper.".v2d >/dev/null");
		ob_end_clean();
		if($this->explabel == "mf"){
			sleep(5);
		}
		else{
			sleep(1);
		}

		ob_start();
		//echo $conssh->exec($con,"cd ".$this->corrdir.';for i in *.calc; do difxcalc $i;done;');
		echo $conssh->exec($con,"cd ".$this->corrdir.';difxcalc *.calc;');
		ob_end_clean();

		sleep(1);
		//create correlation list and add to /tmp/dynlist
		echo $conssh->exec($con,"ls ".$this->corrdir."*im | cut -d '.' -f 1 > /tmp/".$this->exper."joblist");

		if ($this->explabel == "mf" || $this->explabel == "si" ){
			$tstline = $conssh->exec($con,"head -1 /tmp/dynlist");
			if($tstline == ""){
				echo $conssh->exec($con,"echo /tmp/".$this->exper."joblist >> /tmp/dynlist"); //add to last line
			}
			else{
				echo $conssh->exec($con,"sed  -i '1i /tmp/".$this->exper."joblist' /tmp/dynlist"); //add to first line
			}
		}
		else{
			echo $conssh->exec($con,"echo /tmp/".$this->exper."joblist >> /tmp/dynlist"); //add to last line
		}
		
		/*
		if ($this->explabel == "mf"){
			echo $conssh->exec($con,"cd ".$this->corrdir.';for i in *.calc; do difxcalc $i;done;');
			sleep(1);
			//create correlation list and add to /tmp/dynlist
			echo $conssh->exec($con,"ls ".$this->corrdir."*im | cut -d '.' -f 1 > /tmp/".$this->exper."joblist");
			//echo $conssh->exec($con,"echo /tmp/".$this->exper."joblist >> /tmp/dynlist");
		}
		else{
			//usual session may be too large to process with PHP
		}
	*/
		ssh2_disconnect($con);
	}

	function dxmpi(){
		//check $this->exper to see how many scans left
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->cserver,$this->cusername,$this->cpassword))){
			$message = "Exit: Unable to reach ".$this->cserver.PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}

		//create file to record the number of scans
		$scannum = trim($conssh->exec($con,"wc -l < /tmp/".$this->exper."joblist"));
		if (!file_exists($_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/numscans")){
			$file = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/numscans", "w");
			fwrite($file,$scannum);
			fclose($file);
			$totscans = $scannum;
		}
		else{
			//read number of scans from /tmp/$this->exper
			$file = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$this->exper."/numscans","r");
			$totscans = trim(fgets($file));
			fclose($file);
		}

		if($scannum<=0){
			$message = "Correlation completed".PHP_EOL;
			$_SESSION["precor_message"] = $message;
			echo $message;
		}
		else{
			$cornow = $totscans - $scannum + 1;
			$_SESSION["precor_message"] = "scan ".$cornow." of ".$totscans.PHP_EOL;
			echo $message;
		}
		
	}

	/*
		//unmount /mnt/vbsDYN (hb only)
		print_r($cstations);
        if (in_array("hb",$cstations) && $explabel!=="mf"){
		$conflhb = $conssh->connect("flexbuffhb",$cusername,$cpassword);
			for($i=0;$i<count($dynexp);$i++){
				//ssh2_exec($conflhb,"fusermount -u /mnt/vbsDYN/".$dynexp[$i]."hb");
				//ssh2_exec($conflhb,"rm -rf /mnt/vbsDYN/".$dynexp[$i]."hb");
			}
		} 
	*/
	function postmpi(){
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->cserver,$this->cusername,$this->cpassword))){
			$message = "Exit: Unable to reach ".$this->cserver.PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}
		//get expcode
		$db = new DbWrite;
		if($explabel!=="mf"){
			$dbq = $db->query("dynob",'SELECT expcode FROM Correlation WHERE session="'.$this->exper.'"');
			if($dbq=="null"){
				$expcode = "1234";
			}
			else{
				$expcode = $dbq[0]["expcode"];
			}
		}
		else{
			$expcode = "1234";
		}
		$_SESSION["expcode"] = $expcode;

		//difx2mark4
		$list =$conssh->exec($con,"ls ".$this->corrdir."*input | cut -d '.' -f 1");
		$listarr = preg_split('/[\s]+/', $list);
		sort($listarr);
		$listarr = array_filter($listarr);
		$listarr = array_values($listarr);
		$dixfb = "";
		if(stripos($this->cmode,"vgos") !== false){
			$dixfb = '-b X 1 14000 ';
		}
		echo $conssh->exec($con,"cd ".$this->corrdir.";for i in {".substr($listarr[0],stripos($listarr[0],"_")+1)."..".substr($listarr[count($listarr)-1],stripos($listarr[count($listarr)-1],"_")+1)."}; do difx2mark4 ".$dixfb."-e ".$expcode." ".$this->exper.'_${i} &>/dev/null;done;');
		
		ssh2_disconnect($con);
		
	}

	function checkdynlist($exper){
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
			die();
		}
		
		//create file to record the number of scans
		$iscorrelating = $conssh->exec($con,"cat /tmp/dynlist | grep ".$exper." | wc -l");
		if ($iscorrelating >= 1){
			echo "true";
		}
		else{
			echo "false";
		}
	}
}

$precorr = new Precorrelate();
if ($_GET["do"]=="init"){
	$precorr->setup();
}
if ($_GET["do"]=="checkdir"){
	$precorr -> globalvars();
	$precorr->checkdir();
}
if ($_GET["do"]=="getdataloc"){
	$precorr -> globalvars();
	$precorr->getdataloc();
}
if ($_GET["do"]=="copyvex"){
	$precorr -> globalvars();
	$precorr->copyvex();
}
if ($_GET["do"]=="getlog"){
	$precorr -> globalvars();
	$precorr->getlog();
}
if ($_GET["do"]=="getv2d"){
	$precorr -> globalvars();
	$precorr->getv2d();
}
if ($_GET["do"]=="genfilelist"){
	$precorr -> globalvars();
	$precorr->genfilelist();
}
if ($_GET["do"]=="dxcalc"){
	$precorr -> globalvars();
	$precorr->dxcalc();
}
if ($_GET["do"]=="dxmpi"){
	$precorr -> globalvars();
	$precorr->dxmpi();
}
if ($_GET["do"]=="postmpi"){
	$precorr -> globalvars();
	$precorr->postmpi();
}
if ($_GET["do"]=="checkdynlist"){
	$precorr->checkdynlist($_GET["exper"]);
}

?>
