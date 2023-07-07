<?php
//this script starts the fringe check after prefringe
require_once "../common/SSHcon.php";
require_once "../common/global.php";

$FSet=new FringeSet;
$FSet->setup("2021256220924");

Class FringeSet{
	
	function setup($fringeout){
		$exp="fringe";
		//read expinfo.txt
		//fringeout is the name of dir in scheduling/fringeout
		$expinfo = new SplFileObject($_SERVER['DOCUMENT_ROOT']."scheduling/fringeout/".$fringeout."/expinfo.txt");
		while (!$expinfo->eof()) {
			$line = $expinfo->fgets();
			if(stripos($line,'Stations')!==false){
				$stations = substr($line,10);
				$stations = explode(",",$stations);
				$stations = array_filter($stations);
			}
		}

		//initiate (proc=exp, setupsx, setupaum)
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
			$log = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap log=".$exp.$st);
			sleep(1);
			$proc = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap proc=".$exp.$st);
			sleep(1);
			$setupsx = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap exper_initi");
			sleep(3);
			$setupsx = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap setupsx");
			sleep(5);
			if (in_array($st,$GLOBALS["vgosstations"])){
				$proc = $conssh->exec($conpcfs,"/usr2/fs/bin/inject_snap setupaum");
				sleep(90);
			}

		}
	}
}

?>