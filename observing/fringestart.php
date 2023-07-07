<?php
//this script starts the fringe check after prefringe
require_once "../common/SSHcon.php";
require_once "../common/global.php";

//$FStart=new FringeStart;
//$FStart->start($_REQUEST["dd"]);

Class FringeStart{
	
	function start($ddir){
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

		//schedule=
		$conssh = new ConnectSSH;
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

			//$proc = $conssh->exec($conpcfs,"rm /usr2/log/".$exp.$st.".log");
			//sleep(2);
			$proc = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap log=".$exp.$st);
			sleep(2);
			$proc = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap clkoff");
			sleep(2);
			$proc = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap maserdelay");
			sleep(2);
			$proc = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap caltsys");
			sleep(2);
			$proc = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap schedule=".$exp.$st.",#1");
			sleep(1);
			//echo "here";

		}
	}
}
?>