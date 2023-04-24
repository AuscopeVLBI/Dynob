<?php
require_once "../common/SSHcon.php";
require_once "../common/global.php";
require_once "../common/dbwrite.php";

if (session_status() == PHP_SESSION_NONE) {
		session_start();
}

$lv1db = new Lv1db;
if ($_GET["do"]=="checkdir"){
	$lv1db->globalvars();
	$lv1db->checkdir();
}
if ($_GET["do"]=="cp2mac"){
	$lv1db->globalvars();
	$lv1db->cp2mac();
}
if ($_GET["do"]=="data2mac"){
	$lv1db->globalvars();
	$lv1db->data2mac();
}
if ($_GET["do"]=="predb"){
	$lv1db->globalvars();
	$lv1db->predb();
}
if ($_GET["do"]=="createdb"){
	$lv1db->globalvars();
	$lv1db->createdb();
}
if ($_GET["do"]=="createdbold"){
	$lv1db->globalvars();
	$lv1db->createdbold();
}


Class Lv1db{

	public $aserver;
	public $ausername;
	public $apassword;
	public $a7server;
	public $a7username;
	public $a7password;
	public $a7logpath;
	public $cserver;
	public $cusername;
	public $cpassword;
	public $amacpath;
	public $exper;
	public $anadir;
	public $corrdir;

	function globalvars(){
		$this->aserver = $GLOBALS["dbmachine"];
		$this->ausername = $GLOBALS["dbmacuser"];
		$this->apassword = $GLOBALS["dbmacpass"];
		$this->amacpath = $GLOBALS["dbmacpath"];
		$this->a7server = $GLOBALS["anamachine"];
		$this->a7username = $GLOBALS["anamacuser"];
		$this->a7password = $GLOBALS["anamacpass"];
		$this->a7logpath = $GLOBALS["anamaclogpath"];
		$this->cserver = $GLOBALS["cserver"];
		$this->cusername = $GLOBALS["cusername"];
		$this->cpassword = $GLOBALS["cpassword"];
		$this->exper = $_GET["exper"];
		$this->anadir = $_SESSION["anadir"];
	}

	function checkdir(){
		//check if correlation directory exists
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->aserver,$this->ausername,$this->apassword))){
			$message = "Exit: Unable to reach ".$this->aserver.PHP_EOL;
			$_SESSION["ana_message"] = $_SESSION["ana_message"].$message;
			die();
		}

		$sftp = ssh2_sftp($con);
		$_str = file_exists('ssh2.sftp://'.$sftp.$this->amacpath.$this->exper);
		if(!$_str){
			//make exper dir
			ssh2_sftp_mkdir($sftp, $this->amacpath.$this->exper);
			ssh2_sftp_chmod($sftp, $this->amacpath.$this->exper, 0775);
		}
		$_str = file_exists('ssh2.sftp://'.$sftp.$this->amacpath.$this->exper."/SNR");
		if(!$_str){
			//make SNR dir
			$expcode = $this->getexpcode();
			ssh2_sftp_mkdir($sftp, $this->amacpath.$this->exper."/SNR");
			ssh2_sftp_chmod($sftp, $this->amacpath.$this->exper."/SNR", 0775);
		}
		$_str = file_exists('ssh2.sftp://'.$sftp.$this->amacpath.$this->exper."/control");
		if(!$_str){
			//make control dir
			ssh2_sftp_mkdir($sftp, $this->amacpath.$this->exper."/control");
			ssh2_sftp_chmod($sftp, $this->amacpath.$this->exper."/control", 0775);
			
		}
		$anadir = $this->anadir.$this->exper."/";
		$_SESSION["anadir"] = $anadir;
		ssh2_disconnect($con);
	}

	function cp2mac(){
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->aserver,$this->ausername,$this->apassword))){
			$message = "Exit: Unable to reach ".$this->aserver.PHP_EOL;
			$_SESSION["ana_message"] = $_SESSION["ana_message"].$message;
			die();
		}
		
		//copy skd file to $this->amacpath
		$sftp = ssh2_sftp($con);
		$_str = file_exists('ssh2.sftp://'.$sftp.$this->amacpath.$this->exper.".skd");
		if(!$_str){
			$skd = $_SERVER['DOCUMENT_ROOT']."scheduling/skd/".$this->exper.".skd";
			ssh2_scp_send($con,$skd, $this->amacpath.$this->exper.".skd", 0755);
		}
		//copy log files
		$logs = glob($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/*.log");
		foreach($logs as $lg){
			$st = substr(basename($lg),0,2);
			ssh2_scp_send($con,$lg, $this->amacpath.$this->exper."/".$this->exper.$st.".log", 0755);
		}

		//copy v2d
		//replace lower letter station names to upper
		$v2dfile = new SplFileObject($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/buffer.v2d","r");
		$v2dfilenew = new SplFileObject($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$this->exper.".v2d","w");
		while (!$v2dfile->eof()) {
			$line = $v2dfile->fgets();
			if(strpos($line,"antennas")!==false){
				$_subline = substr($line,strpos($line,"="));
				$patt = "/".$_subline."/";
				$line = preg_replace($patt, ucwords($_subline), $line);
			}
			if(strpos($line,"ANTENNA")!==false){
				$_subline = substr($line,strpos($line," "));
				$patt = "/".$_subline."/";
				$line = preg_replace($patt, ucwords($_subline), $line);
			}
			$v2dfilenew ->fwrite($line);
		}
		ssh2_scp_send($con,$_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$this->exper.".v2d", $this->amacpath.$this->exper."/".$this->exper.".v2d", 0755);

		//copy vex
		ssh2_scp_send($con,$_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/buffer.vex", $this->amacpath.$this->exper."/".$this->exper.".vex", 0755);

		//copy control file
		$expcode = $this->getexpcode();
		ssh2_scp_send($con,$_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/cf_".$this->exper, $this->amacpath.$this->exper."/control/cf_".$expcode, 0755);
	}

	//copy data from correlator to analysis machine
	function data2mac(){
		$conssh = new ConnectSSH;
		if(!($concorr = $conssh->connect($this->cserver,$this->cusername,$this->cpassword))){
			$message = "Exit: Unable to reach ".$this->cserver.PHP_EOL;
			$_SESSION["ana_message"] = $_SESSION["ana_message"].$message;
			die();
		}
		//copy all $expcode files
		//read scan list for data
		//echo $_SERVER['DOCUMENT_ROOT']."postcorr/scans/scans_".$this->exper.".txt";
		$thescans = file($_SERVER['DOCUMENT_ROOT']."postcorr/scans/scans_".$this->exper.".txt");
		$thescans = array_filter($thescans);
		$thescans = array_values($thescans);


		switch(substr($this->exper,0,2)){
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
			if(substr($this->exper,0,1) == "z"){
				$corrdir = $GLOBALS["corrdir"]."/si/";
				$explabel = "si";
			}
		}
		$this->corrdir = $corrdir;

		//send
		$expcode = $this->getexpcode();
		$conssh->exec($concorr,"cd ".$this->corrdir.$this->exper.";scp -r ".$expcode." ".$this->ausername."@".$this->aserver.".phys.utas.edu.au:".$this->amacpath.$this->exper."/".$expcode." &>/dev/null;");
	}

	//before creating lv1 vgosdb
	function predb(){
		$conssh = new ConnectSSH;
		$con = $conssh->connect($this->a7server,$this->a7username,$this->a7password);		
		if(!($con = $conssh->connect($this->a7server,$this->a7username,$this->a7password))){
			$message = "Exit: Unable to reach ".$this->a7server.PHP_EOL;
			$_SESSION["ana_message"] = $_SESSION["ana_message"].$message;
			die();
		}
		echo $conssh->exec($con,"~/Make_apriori.dyn.sh /mnt/".$this->aserver."/AUSTRAL/".$this->exper.".skd");

		//download apriori file
		$scpapsnr = ssh2_scp_recv($con, "/mnt/".$this->aserver."/AUSTRAL/".$this->exper.".skd_snr.apriori", $_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$this->exper.".skd_snr.apriori");
		if(!$scpapsnr){
			echo "fail to download SNR_apriori";
		}
		
		//extract only the table
		$apsnrfile = new SplFileObject($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$this->exper.".skd_snr.apriori","r");
		$apsnrfile2 = new SplFileObject($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$this->exper.".skd_snr.apriori2","w");
		$mark = 0;
		while (!$apsnrfile->eof()) {
			$line = $apsnrfile->fgets();
			if(strpos($line,"?  list")!==false){
				$mark = 1;
				continue;
			}
			if($mark == 1){
				if(strpos($line,"End of listing")!==false){
					$mark = 0;
					break;
				}
				$apsnrfile2->fwrite($line);
			}
		}
		ssh2_scp_send($con,$_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$this->exper.".skd_snr.apriori2", "/mnt/".$this->aserver."/AUSTRAL/".$this->exper.".skd_snr.apriori", 0755);
		unlink($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$this->exper.".skd_snr.apriori");
		rename($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$this->exper.".skd_snr.apriori2",$_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$this->exper.".skd_snr.apriori");

		//mk4_site_ID report_prep
		//echo -e "\n" | report_prep.dyn1.sh
		//prepare corr report
		if(!($conana = $conssh->connect($this->aserver,$this->ausername,$this->apassword))){
			$message = "Exit: Unable to reach ".$this->aserver.PHP_EOL;
			$_SESSION["ana_message"] = $_SESSION["ana_message"].$message;
			die();
		}

		//upload alist if available, else create one
		$expcode = $this->getexpcode();
		
		if (file_exists($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/alist.out")){
			ssh2_scp_send($conana,$_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/alist.out", $this->amacpath.$this->exper."/".$expcode."/alist.out", 0755);
		}
		else{
			echo $conssh->exec($conana,"cd ".$this->amacpath.$this->exper."/".$expcode."; /home/observer/HOPS/x86_64-3.23/bin/alist * &>/dev/null;");
		}
		echo $conssh->exec($conana,"cd ".$this->amacpath.$this->exper."/".$expcode."; ~/bin/nskd_ovex.pl ../../".$this->exper.".skd ".$expcode." > ".$expcode.".ovex");
		//download ovex for edit
		$scpovex = ssh2_scp_recv($conana, $this->amacpath.$this->exper."/".$expcode."/".$expcode.".ovex", $_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$expcode.".ovex");
		$ovexfile = new SplFileObject($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$expcode.".ovex","r");
		$ovexfile2 = new SplFileObject($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$expcode.".ovex2","w");

		while (!$ovexfile->eof()) {
			$line = $ovexfile->fgets();
			if(strpos($line," site_ID")!==false){
				$_siteid = trim(substr($line,strpos($line,"=")+1,2));
				$gsiteid = $GLOBALS["stcodes"][$_siteid];
			}
			if(strpos($line,"mk4_site_ID")!==false){
				$line = substr($line,0,strpos($line,"=")+1).$gsiteid.";".PHP_EOL;
			}
			$ovexfile2->fwrite($line);
		}
		//upload 
		unlink($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$expcode.".ovex");
		rename($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$expcode.".ovex2",$_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$expcode.".ovex");
		ssh2_scp_send($conana,$_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/".$expcode.".ovex", $this->amacpath.$this->exper."/".$expcode."/".$expcode.".ovex", 0755);

		//create tmp file for aedit
		$tmpaedit = new SplFileObject($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/tmpaedit.sh","w");
		$line = 'source ~/HOPS/x86_64-3.23/bin/hops.bash'.PHP_EOL.
		'cd '.$this->amacpath.$this->exper.'/'.$expcode.'/'.PHP_EOL.
		'export CORDATA='.$this->amacpath.$this->exper.'/'.$expcode.'/'.PHP_EOL.
		'export DATADIR='.$this->amacpath.$this->exper.'/'.$expcode.'/'.PHP_EOL.
		'echo -e "edi dup snr\npsfile '.$expcode.'.psfile_rf\n'.$this->amacpath.$this->exper.'/'.$expcode.'/'.$expcode.'.ovex\nexit\ny\n" | /home/observer/HOPS/x86_64-3.23/bin/aedit -f '.$this->amacpath.$this->exper.'/'.$expcode.'/'.'/alist.ed.out';
		$tmpaedit->fwrite($line);
		ssh2_scp_send($conana,$_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/tmpaedit.sh", $this->amacpath.$this->exper."/".$expcode."/tmpaedit.sh", 0755);

		//continue report_prep
		$_proc = ["sed -i 's/ 16383 / ".$expcode." /g' alist.out",
		"grep -v  '0.000000 0.000  -1  -1' alist.out > alist.ed.out",
		"rm -f ".$expcode.".psfile_rf",
		"./tmpaedit.sh"];
		$_combproc = implode("; ",$_proc);

		echo $conssh->exec($conana,"cd ".$this->amacpath.$this->exper."/".$expcode.";".$_combproc);

		//continue report_prep
		switch(substr($this->exper,0,2)){
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
			if(substr($this->exper,0,1) == "z"){
				$corrdir = $GLOBALS["corrdir"]."/si/";
				$explabel = "si";
			}
		}
		if($explabel == "si"){
			echo $conssh->exec($conana,"cd ".$this->amacpath.$this->exper."/".$expcode."; echo -e '\n\n' | ~/bin/report_prep.dynsi.sh");
		}
		else{
			echo $conssh->exec($conana,"cd ".$this->amacpath.$this->exper."/".$expcode."; echo -e '\n\n' | ~/bin/report_prep.dyn.sh");
		}

	}

	//create lv1 vgosdb
	function createdb(){ //after 2023
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->a7server,$this->a7username,$this->a7password))){
			$message = "Exit: Unable to reach ".$this->a7server.PHP_EOL;
			$_SESSION["ana_message"] = $_SESSION["ana_message"].$message;
			die();
		}
		$sessyear = $this->getsessyear();
		$expcode = $this->getexpcode();
		
		//try intensive first, then normal, if found in intensive, turn on skip normal
		$skipnorm = 0;
		//download master
		//intensives
		//if(!file_exists($_SERVER['DOCUMENT_ROOT']."createdb/master/master".$sessyear."-int.txt")){
			$usnobkg = "https://ivs.bkg.bund.de/data_dir/vlbi/ivscontrol/master".$sessyear."-int.txt";
			$file_name = $_SERVER['DOCUMENT_ROOT']."createdb/master/master".$sessyear."-int.txt";
			if(!file_put_contents($file_name,file_get_contents($usnobkg))){
				$message = "failed to download master".$sessyear."-int.txt from the bkg server".PHP_EOL;
				$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			}
		//}
		$_masterint = new SplFileObject($_SERVER['DOCUMENT_ROOT']."createdb/master/master".$sessyear."-int.txt","r");

		while (!$_masterint->eof()) {
			$line = $_masterint->fgets();
			if(substr($line,0,1)=="|"){
				$_expl = explode("|",$line);
				if(strtolower(trim($_expl[3])) == $this->exper){
					$vdbname = trim($_expl[2])."-".trim($_expl[3]);
					$skipnorm = 1;
				}
			}
		}

		if($skipnorm == 0){
			//if(!file_exists($_SERVER['DOCUMENT_ROOT']."createdb/master/master".$sessyear.".txt")){
				$usnobkg = "https://ivs.bkg.bund.de/data_dir/vlbi/ivscontrol/master".$sessyear.".txt";
				//normal
				$file_name = $_SERVER['DOCUMENT_ROOT']."createdb/master/master".$sessyear.".txt";
				if(!file_put_contents($file_name,file_get_contents($usnobkg))){
					$message = "failed to download master".$sessyear.".txt from the bkg server".PHP_EOL;
					$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
				}
			//}
			$_master = new SplFileObject($_SERVER['DOCUMENT_ROOT']."createdb/master/master".$sessyear.".txt","r");

			while (!$_master->eof()) {
				$line = $_master->fgets();
				if(substr($line,0,1)=="|"){
					$_expl = explode("|",$line);
					if(strtolower(trim($_expl[3])) == $this->exper){
						$vdbname = trim($_expl[2])."-".trim($_expl[3]);
					}
				}
			}
		}

		echo $conssh->exec($con,"vgosDbMake -d ".$vdbname." /mnt/".$this->aserver."/AUSTRAL/".$this->exper."/".$expcode."/");
		echo $conssh->exec($con,"cd /data/vlbi/vgosDb/".$sessyear."; tar -zcvf ".$vdbname.".tgz ".$vdbname);
	}

	function createdbold(){ //before 2023
		$conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->a7server,$this->a7username,$this->a7password))){
			$message = "Exit: Unable to reach ".$this->a7server.PHP_EOL;
			$_SESSION["ana_message"] = $_SESSION["ana_message"].$message;
			die();
		}
		
		$sessyear = $this->getsessyear();
		$expcode = $this->getexpcode();
		//try intensive first, then normal, if found in intensive, turn on skip normal
		$skipnorm = 0;
		//download master
		//intensives
		//if(!file_exists($_SERVER['DOCUMENT_ROOT']."createdb/master/master".substr($sessyear,2,2)."-int.txt")){
			$usnobkg = "https://ivs.bkg.bund.de/data_dir/vlbi/ivscontrol/master".substr($sessyear,2,2)."-int.txt";
			$file_name = $_SERVER['DOCUMENT_ROOT']."createdb/master/master".substr($sessyear,2,2)."-int.txt";
			if(!file_put_contents($file_name,file_get_contents($usnobkg))){
				$message = "failed to download master".substr($sessyear,2,2)."-int.txt from the bkg server".PHP_EOL;
				$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			}
		//}
		$_masterint = new SplFileObject($_SERVER['DOCUMENT_ROOT']."createdb/master/master".substr($sessyear,2,2)."-int.txt","r");

		while (!$_masterint->eof()) {
			$line = $_masterint->fgets();
			if(substr($line,0,1)=="|"){
				$_expl = explode("|",$line);
				if(strtolower(trim($_expl[2])) == $this->exper){
					$vdbname = substr($sessyear,2,2).strtoupper(trim($_expl[3])).strtoupper(trim($_expl[12]));
					$skipnorm = 1;
				}
			}
		}

		if($skipnorm == 0){
			//if(!file_exists($_SERVER['DOCUMENT_ROOT']."createdb/master/master".substr($sessyear,2,2).".txt")){
				$usnobkg = "https://ivs.bkg.bund.de/data_dir/vlbi/ivscontrol/master".substr($sessyear,2,2).".txt";
				//normal
				$file_name = $_SERVER['DOCUMENT_ROOT']."createdb/master/master".substr($sessyear,2,2).".txt";
				if(!file_put_contents($file_name,file_get_contents($usnobkg))){
					$message = "failed to download master".substr($sessyear,2,2).".txt from the bkg server".PHP_EOL;
					$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
				}
			//}
			$_master = new SplFileObject($_SERVER['DOCUMENT_ROOT']."createdb/master/master".substr($sessyear,2,2).".txt","r");

			while (!$_master->eof()) {
				$line = $_master->fgets();
				if(substr($line,0,1)=="|"){
					$_expl = explode("|",$line);
					if(strtolower(trim($_expl[2])) == $this->exper){
						$vdbname = substr($sessyear,2,2).strtoupper(trim($_expl[3])).strtoupper(trim($_expl[12]));
					}
				}
			}
		}

		echo $conssh->exec($con,"vgosDbMake -d ".$vdbname." /mnt/".$this->aserver."/AUSTRAL/".$this->exper."/".$expcode."/");
		$sftp = ssh2_sftp($con);
		$_str = file_exists('ssh2.sftp://'.$sftp.$this->a7logpath.$sessyear);
		if(!$_str){
			ssh2_sftp_mkdir($sftp, $this->a7logpath.$sessyear);
			ssh2_sftp_chmod($sftp, $this->a7logpath.$sessyear, 0775);
		}
		$_str = file_exists('ssh2.sftp://'.$sftp.$this->a7logpath.$sessyear.'/'.$this->exper);
		if(!$_str){
			ssh2_sftp_mkdir($sftp, $this->a7logpath.$sessyear.'/'.$this->exper);
			ssh2_sftp_chmod($sftp, $this->a7logpath.$sessyear.'/'.$this->exper, 0775);
		}
		echo $conssh->exec($con,"cp /mnt/oaf/AUSTRAL/".$this->exper."/*.log ".$this->a7logpath."sessions/".$sessyear."/".$this->exper);
		echo $conssh->exec($con,"cp /mnt/oaf/AUSTRAL/".$this->exper."/".$this->exper.".skd ".$this->a7logpath."sessions/".$sessyear."/".$this->exper);
		echo $conssh->exec($con,"vgosDbCalc ".$vdbname);
		echo $conssh->exec($con,"vgosDbProcLogs ".$vdbname);
		echo $conssh->exec($con,"cd /data/vlbi/vgosDb/".$sessyear."; tar -zcvf ".$vdbname.".tgz ".$vdbname);

	}

	function getexpcode(){
		$db = new DbWrite;
		$dbq = $db->query("dynob","SELECT expcode FROM Correlation WHERE session='".$this->exper."'");
		$expcode = $dbq[0]["expcode"];
		return $expcode;
	}

	function getsessyear(){
		//get year of the session from vex file
		$vexfile = new SplFileObject($_SERVER['DOCUMENT_ROOT']."/tmp/".$this->exper."/buffer.vex","r");
		while (!$vexfile->eof()) {
			$line = $vexfile->fgets();
			if(strpos($line,"start")!==false){
				$year = trim(substr($line,strpos($line,"=")+1,5));
				if(str_contains($year, 'y')){
					$year = substr($year,0,4);
				}
				break;
			}
		}
		return $year;
	}


}




?>
