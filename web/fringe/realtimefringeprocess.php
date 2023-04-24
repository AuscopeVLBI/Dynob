<?php
require_once "../../common/SSHcon.php";
require_once "../../common/global.php";

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

$R = new RTFP;

if($_GET["action"]=="prepare"){
	$R->prepare();
}
elseif($_GET["action"]=="prepfiles"){
	$R->prepfiles($_SESSION["recorders"]);
}
elseif($_GET["action"]=="checksize"){
	$R->checksize($_SESSION["recorders"]);
}

Class RTFP{

	function prepare(){

		$conssh = new ConnectSSH;

		$stations = explode(",",htmlspecialchars($_REQUEST["stations"]));
		$session = strtolower(htmlspecialchars($_REQUEST["sessionName"]));
		$fcsource =  array_filter(explode(",",htmlspecialchars($_REQUEST["fcsource"])));//fringe check sources
		$recorders = array_filter(explode(",",htmlspecialchars($_REQUEST["recorders"])));

		$_SESSION["stations"] = $stations;
		$_SESSION["session"] = $session;
		$_SESSION["fcsource"] = $fcsource;
		$_SESSION["recorders"] = $recorders;
		
		//download get skd
		if(!file_exists("../fringe/realtimefringeskd/".$session.".skd")){
			if(file_exists("../../scheduling/skd/".$session.".skd")){
				copy("../../scheduling/skd/".$session.".skd","../fringe/realtimefringeskd/".$session.".skd");
			}
			else{
				for ($i=0;$i<count($stations);$i++){
					if(!($conpcfs = $conssh->connect("pcfs".$stations[$i],$GLOBALS["pcfsuser"],$GLOBALS["cpassword"]))){
						continue;
					}
					else{
						$scpboolskd = ssh2_scp_recv($conpcfs, $GLOBALS["skdlocation"].$session.'.skd', "../fringe/realtimefringeskd/".$session.".skd");
						if(!$scpboolskd){
							continue;
						}
						else{
							break;
						}
					}
				}
				ssh2_disconnect($conpcfs);
			}
		}
		
		if(!file_exists("../fringe/realtimefringeskd/".$session.".skd")){
			echo "not a valid session";
			die();
		}
		
		//read $SKED section from skd
		$skdreading = false;
		$file = new SplFileObject("../fringe/realtimefringeskd/".$session.".skd");
		
		//store vars in array
		$sources = [];
		$fringestarttime = [];
		$maxtime = [];
		
		while ( ! $file->eof()) {
			$line = $file->fgets();
			if ($skdreading){
				if($line[0]=='$'){
					$skdreading = false;
					break;
				}
				sscanf($line,"%s %d %s %s %s %d",$_source, $n, $_type, $_stage, $_recstart, $_maxtime);
				//store source
				array_push($sources,$_source);
		
				//calculate time to start fringe check and store
				$date = DateTime::createFromFormat( 'yzHis' , $_recstart);
				$contime = $date->format('Y-m-d H:i:s');
				$timestamp = strtotime($contime." -1 day +".($_maxtime+5)." seconds");
				$frstart = date("Y-m-d H:i:s",$timestamp);
				array_push($fringestarttime,$frstart);
				
				//store max time
				array_push($maxtime,$_maxtime);
				
			}
		
			if (stripos($line,'$SKED')!==false){
				$skdreading = true;
			}
		}
		
		if($_REQUEST["anysource"]==1){
			//use first scan, every 3 hours select another scan, less than 90 secs
			$fringedatetime = [];
			$ti = 0;
			for ($i=0;$i<count($fringestarttime);$i++){
				$timenow = gmdate("Y-m-d H:i:s");
				if(strtotime($fringestarttime[$i]) >= strtotime($timenow)){ //start whenever webpage started
					if($ti == 0){
						$checktime = $fringestarttime[$i];
						array_push($fringedatetime,$checktime);
						$ti = 1;
						continue;
					}
					else{
						if( strtotime($fringestarttime[$i]) >= strtotime($checktime)+10800 ){ //three hours different
							$checktime = $fringestarttime[$i];
							array_push($fringedatetime,$checktime);
						}
					}
				}
			}
		}
		else{
			//if no fcsource, use one of the source from global rtfcsources, if present in skd
			if(empty($fcsource)){
				$inter = array_intersect($sources, $GLOBALS["rtfcsources"]);
			}
			else{
				$inter = array_intersect($sources, $fcsource);
			}
		
			//from the inter scans select scans that are shorter than 90secs and at least 3 hours apart
			$fringedatetime = [];
			$ti = 0;
			foreach($inter as $_arrnum => $_source){
				if($maxtime[$_arrnum]<90 && (strtotime($fringestarttime[$_arrnum]) >= strtotime($timenow)) ){
					if($ti == 0){
						$checktime = $fringestarttime[$_arrnum];
						array_push($fringedatetime,$checktime);
						$ti = 1;
						continue;
					}
					else{
						if( strtotime($fringestarttime[$_arrnum]) >= strtotime($checktime)+10800 ){ //three hours different
							$checktime = $fringestarttime[$_arrnum];
							array_push($fringedatetime,$checktime);
						}
					}
				}
			}
		}
		echo implode(PHP_EOL,$fringedatetime); //output for realtimefringeprocess.html

		//create dir for fringecheck on vc0
		if(!($con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
			echo "No connection to ".$GLOBALS["cserver"];
			die();
		}
		else{
			$_str = $conssh->exec($con,"cd ".$GLOBALS["corrdir"]."/mf/;ls");
			//remove mfdynf
			$folrm = $conssh->exec($con,"rm -rf ".$GLOBALS["corrdir"]."/mf/mfdynf/");

			if (!stripos($_str,$session)){
				$connection = ssh2_connect($GLOBALS["cserver"], 22);
				ssh2_auth_password($connection, $GLOBALS["cusername"], $GLOBALS["cpassword"]);
				$sftp = ssh2_sftp($connection);
				ssh2_sftp_mkdir($sftp, $GLOBALS["corrdir"]."/mf/".$session);
				ssh2_sftp_mkdir($sftp, $GLOBALS["corrdir"]."/mf/".$session."/data");
			}
		}
		//ssh2_disconnect($con);
	}

	function prepfiles($rec){
		
		//$stations = explode(",",htmlspecialchars($_REQUEST["stations"]));
		//$session = strtolower(htmlspecialchars($_REQUEST["sessionName"]));
		//$fcsource =  array_filter(explode(",",htmlspecialchars($_REQUEST["fcsource"])));//fringe check sources
		//$recorders = array_filter(explode(",",htmlspecialchars($rec)));
		$stations = $_SESSION["stations"];
		$session = $_SESSION["session"];
		$fcsource = $_SESSION["fcsource"];
		$recorders= $_SESSION["recorders"];

		$thescans=[]; //for non-local stations

		$conssh = new ConnectSSH;

		//0. create dir for fringecheck on vc0
		if(!($con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
			echo "No connection to ".$GLOBALS["cserver"];
			die();
		}
		else{
			$_str = $conssh->exec($con,"cd ".$GLOBALS["corrdir"]."/mf/;ls");
			if (!stripos($_str,$session)){
				$connection = ssh2_connect($GLOBALS["cserver"], 22);
				ssh2_auth_password($connection, $GLOBALS["cusername"], $GLOBALS["cpassword"]);
				$sftp = ssh2_sftp($connection);
				ssh2_sftp_mkdir($sftp, $GLOBALS["corrdir"]."/mf/".$session);
				ssh2_sftp_mkdir($sftp, $GLOBALS["corrdir"]."/mf/".$session."/data");
			}
		}

		//1. Prepare the file into /mnt/mf/ for local recorders
		foreach ($recorders as $_rec){
			$st = substr($_rec,-2,2);
			if(in_array(strtolower($st),$GLOBALS["localstations"])){ //if local stations
				if(stripos($_rec,"flex")!==false){ //assume flexbuff
					if(!($conrec = $conssh->connect($_rec,$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
						echo "no connection to ".$_rec."<br>";
						continue;
					}
					else{
						//1. Prepare the file into /mnt/mf/ for recorders
						//get last recordered scan, vbs_fs to /mnt/mf/
						$_str = $conssh->exec($conrec,"vbs_ls ".$session."* | tail -1");
						$_scan = array_filter(explode("_",$_str));
						$scanname = $_scan[0]."_".$_scan[1]."_".$_scan[2];
						$_str = $conssh->exec($conrec,"fusermount -u /mnt/mf/");
						$_str = $conssh->exec($conrec,"vbs_fs -I ".$scanname."* /mnt/mf/");
						if(!in_array($_scan[2],$thescans)){
							array_push($thescans,$_scan[2]);
						}
					}
				}
				else { //assume mk5
					//$st = substr($_rec,-2,2);
					if(!($conrec = $conssh->connect('pcfs'.$st,$GLOBALS["pcfsuser"],$GLOBALS["cpassword"]))){
						echo "no connection to ".$_rec."<br>";
						continue;
					}
					else{
						//1. Prepare the file into /mnt/ folder of mk5
						//a. DirList | tail -1
						$_dirlist = $conssh->exec($conrec,'echo "DirList | tail -1" | ssh oper@mk5yg');
						$dirList = array_values(array_filter(preg_split('/\s+/', trim($_dirlist))));
						$startBit = $dirList[2];
						$stopBit = $dirList[3];
						if( ($startBit + 1200000000) < $stopBit ){
							$stopBit = $startBit + 1200000000; //only extract ~1.2GB data, ~12s
						}

						//a. dot? see if recording
						//$_dot = $conssh->exec($conrec,'echo "echo \'dot?\' | nc -w 1 localhost 2620" | ssh oper@mk5yg');
						//$dot = explode(":",$_dot);
						//$dotonoff = trim($dot[3]);
						//clear /mnt/mf
						$_str = $conssh->exec($conrec,"echo 'rm /mnt/mf/*' | ssh oper@mk5yg");
						//disk2file needs to complete witin ~20 seconds, which is ~12s scan
						$d2f = $conssh->exec($conrec,'echo "echo \'disk2file=/mnt/mf/'.$dirList[1].':'.$startBit.':'.$stopBit.':w\' | nc -w 1 localhost 2620" | ssh oper@mk5yg');
						//echo 'echo "echo \'disk2file=/mnt/mf/'.$dirList[1].':'.$startBit.':'.$stopBit.':w\' | nc -w 1 localhost 2620" | ssh oper@mk5yg';

						$_scan = array_filter(explode("_",$dirList[1]));
						$scanname = $_scan[0]."_".$_scan[1]."_".$_scan[2];
						if(!in_array($_scan[2],$thescans)){
							array_push($thescans,$_scan[2]);
						}
					}
				}
			}
		}
		ssh2_disconnect($conrec);

		$_SESSION["thescan"] = $thescans;
		
		//[The opened terminal detects and transfers data]
		
	}

	//2. check filesize on flexbuf/mk5b, check file size on vc0:~/correlations/mf/mfdynf
	//output is true (if it is okay to proceed)
	function checksize($rec){
		//$session = strtolower(htmlspecialchars($_REQUEST["sessionName"]));
		//$recorders = array_filter(explode(",",htmlspecialchars($rec)));
		//$stations = explode(",",htmlspecialchars($_REQUEST["stations"]));
		$stations = $_SESSION["stations"];
		$session = $_SESSION["session"];
		$fcsource = $_SESSION["fcsource"];
		$recorders= $_SESSION["recorders"];

		$conssh = new ConnectSSH;
		$numdata = [];
		$transcompleted = [];

		//$recnames = []; //name=>file names under rec
		$recnamesize = []; //name=>size array for rec

		foreach ($recorders as $_rec){
			$st = substr($_rec,-2,2);
			if(in_array(strtolower($st),$GLOBALS["localstations"])){ //if local stations
				if(stripos($_rec,"flex")!==false){ //assume flexbuff
					if(!($conrec = $conssh->connect($_rec,$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
						echo "no connection to ".$_rec."<br>";
						continue;
					}
					else{
						//count number of files under /mnt/mf
						$ffiles = $conssh->exec($conrec,"ls /mnt/mf/");
						$ffilesarr = array_filter(preg_split("/\r\n|\n|\r|\s+/", $ffiles));
						//$numdata = $numdata + count($ffilesarr);
						$numdata = array_merge($numdata, $ffilesarr);
		
						//get the size on recorder
						$sftp = ssh2_sftp($conrec);
						foreach($ffilesarr as $_data){
							$fileonrec = ssh2_sftp_stat($sftp, '/mnt/mf/'.$_data);
							$filesize = $fileonrec['size'];
							//array_push($recnames[$_rec],$_data);
							$recnamesize[$_data] = $filesize;
						}
					}
				}
				else { //assume mk5
					//$st = substr($_rec,-2,2);
					if(!($conrec = $conssh->connect('pcfs'.$st,$GLOBALS["pcfsuser"],$GLOBALS["cpassword"]))){
						echo "no connection to ".$_rec."<br>";
						continue;
					}
					else{
						//1. Prepare the file into /mnt/ folder of mk5
						//a. DirList | tail -1
						$ffiles = $conssh->exec($conrec,'echo "ls /mnt/mf/" | ssh oper@mk5yg');
						$ffilesarr = array_filter(preg_split("/\r\n|\n|\r|\s+/", $ffiles));
						//$numdata = $numdata + count($ffilesarr);
						$numdata = array_merge($numdata, $ffilesarr);
		
						//get the size on recorder
						$sftp = ssh2_sftp($conrec);
						foreach($ffilesarr as $_data){
							//$fileonrec = ssh2_sftp_stat($sftp, '/mnt/mf/'.$_data);
							//$filesize = $fileonrec['size'];
							$filesize = $conssh->exec($conrec,'echo "stat --printf=\"%s\" /mnt/mf/'.$_data.'" | ssh oper@mk5yg');
							//array_push($recnames[$_rec],$_data);
							$recnamesize[$_data] = $filesize;
						}
					}
				}
				//print_r($numdata);
			}
			else{ //not local stations, "recorder" is the destination raid
				//break the rec into machine=>path
				$_recnonlocal = explode(":",$_rec);
				$thescans = $_SESSION["thescan"];
				
				//check size on recorder, check size on vc0
				if(!($conrec = $conssh->connect(trim($_recnonlocal[0]),$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
					echo "no connection to ".$_rec."<br>";
					continue;
				}
				else{
					$ffiles = $conssh->exec($conrec,"ls ".trim($_recnonlocal[1]));
					$ffilesarr = array_filter(preg_split("/\r\n|\n|\r|\s+/", $ffiles));
					$scansentries = [];

					foreach ($thescans as $_scan){
						foreach ($ffilesarr as $scanentry){
							if(strpos($scanentry,$_scan)!==false){
								array_push($numdata, $scanentry);
								array_push($scansentries,$scanentry);
							}
						}
					}
	
					//get the size on recorder
					$sftp = ssh2_sftp($conrec);
					foreach($scansentries as $_data){
						$fileonrec = ssh2_sftp_stat($sftp, trim($_recnonlocal[1])."/".$_data);
						$filesize = $fileonrec['size'];
						//array_push($recnames[$_rec],$_data);
						$recnamesize[$_data] = $filesize;
					}

					//copy log to mfdynf[st].log
					//assume log file under same directory as data
					$cplog = $conssh->exec($conrec,"cd ".trim($_recnonlocal[1]).";cp ".$session.strtolower($st).".log mfdynf".strtolower($st).".log");
				}
			}
		}

		//look at files copied to correlator
		if(!($con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
			echo "no connection to ".$GLOBALS["cserver"]."<br>";
		}
		else{
			$sftp2 = ssh2_sftp($con);
			$numdataoncor = $conssh->exec($con,"ls ".$GLOBALS["corrdir"]."/mf/".$session."/data | wc -l");
			foreach($recnamesize as $_data => $_size){
				$fileoncor = ssh2_sftp_stat($sftp2, $GLOBALS["corrdir"]."/mf/".$session."/data/".$_data);
				$filesize = $fileoncor['size'];
				if($filesize >= $_size){
					//$transcompleted = $transcompleted + 1;
					array_push($transcompleted,$_data);
				}
			}
			//echo "<br>completed transfer: ".$transcompleted."/".$numdata."<br>";
			echo "<br>on list: ".implode(", ",$numdata)."<br>";
			echo "<br>completed transfer: ".implode(", ",$transcompleted)."<br>";

			if(count($transcompleted) == count($numdata) || count($recnamesize) <=0){

				//copying completed, proceed to renaming of files and folder
				$compieddata = $conssh->exec($con,"ls ".$GLOBALS["corrdir"]."/mf/".$session."/data");
				$ffilesarr = array_filter(preg_split("/\r\n|\n|\r|\s+/", $compieddata));
				foreach($ffilesarr as $_data){
					$_expdata = explode("_",$_data);
					$_expdata[0] = "mfdynf";
					$dataname = implode("_",$_expdata);
					$compieddata = $conssh->exec($con,"mv ".$GLOBALS["corrdir"]."/mf/".$session."/data/".$_data." ".$GLOBALS["corrdir"]."/mf/".$session."/data/".$dataname);
				}

				//check if original folder still exists
				$cls = $conssh->exec($con,"ls ".$GLOBALS["corrdir"]."/mf/".$session);
				if(stripos($cls,"No such")===false){
					$folname = $conssh->exec($con,"mv ".$GLOBALS["corrdir"]."/mf/".$session." ".$GLOBALS["corrdir"]."/mf/mfdynf");

					//copy log file and vex file into mfdynf.vex, mfdynfhb.log, etc
					foreach ($stations as $_st){
						$lowerst = strtolower($_st);
						if(!($conpcfs = $conssh->connect("pcfs".$lowerst,$GLOBALS["pcfsuser"],$GLOBALS["cpassword"]))){
							echo "no connection to ".$GLOBALS["cserver"]."<br>";
						}
						else{
							$dynvex = $conssh->exec($conpcfs,"cp ".$GLOBALS["skdlocation"].$session.".skd ".$GLOBALS["skdlocation"]."mfdynf.skd");
							$dynvex = $conssh->exec($conpcfs,"cp ".$GLOBALS["skdlocation"].$session.".vex ".$GLOBALS["skdlocation"]."mfdynf.vex");
							$dynlog = $conssh->exec($conpcfs,"cp ".$GLOBALS["loglocation"].$session.$lowerst.".log ".$GLOBALS["loglocation"]."mfdynf".$lowerst.".log");
						}
					}

				}
				//output true
				echo "true";
			}
		}
	}
}

?>
