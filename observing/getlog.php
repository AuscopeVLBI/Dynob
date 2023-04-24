<?php
//this script get the status from pcfs logs
require_once "../common/SSHcon.php";
require_once "../common/global.php";

$FStart=new Getlog;
$FStart->get($_REQUEST["dd"]);
//$FStart->get("2021256220924",$exp);

Class Getlog{
	function get($ddir){
		//read expinfo.txt
		//fringeout is the name of dir in scheduling/fringeout
		$expinfo = new SplFileObject($_SERVER['DOCUMENT_ROOT']."scheduling/fringeout/".$ddir."/expinfo.txt");
		while (!$expinfo->eof()) {
			$line = $expinfo->fgets();
			//get stations
			if(stripos($line,'Stations')!==false){
				$stations = substr($line,10);
				$stations = explode(",",$stations);
				$stations = array_filter($stations);
			}
			//get expname
			if(stripos($line,'Exp name')!==false){
				$exp = trim(substr($line,10));
			}
		}

		$conssh = new ConnectSSH;
		$obstatus = [];
		$statusmsg = [];
		foreach($stations as $st){
			$st=trim($st);
			if($st=="ho"){
				if(!($conpcfs = $conssh->connect("hobart",$GLOBALS["pcfsuser"],$GLOBALS["cpassword"]))){
					die("Unable to reach hobart");
				}
			}
			else{
				if(!($conpcfs = $conssh->connect("pcfs".$st,$GLOBALS["pcfsuser"],$GLOBALS["cpassword"]))){
					die("Unable to reach pcfs".$st);
				}
			}

			//getlog
			$logtail = $conssh->exec($conpcfs,"tail -80 /usr2/log/".$exp.$st.".log");
			if(stripos($logtail,"end of schedule")!==false || stripos($logtail,"terminate")!==false){
				//observing ended
				$obstatus[$st] = 0; //1 is observing, 0 is not observing
				unset($obstatus[$st]);
			}
			else{
				$obstatus[$st] = 1; //1 is observing, 0 is not observing
				$statusmsg[$st] = date("Y.m.d H:i:s")." ".ucfirst($st)." is observing".PHP_EOL;
			}
			//echo $logtail;
		}
		if(array_sum($obstatus)===0){
			//observing finished
			echo date("Y.m.d H:i:s")." "."all stations finished observing, preparing files...".PHP_EOL;
		}
		else {
			echo implode("",$statusmsg);
		}


	}
}

?>