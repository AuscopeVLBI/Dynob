<?php
//this script is to transfer the fringe check data to hobart
require_once "../common/SSHcon.php";
require_once "../common/global.php";

$fpo = new fringePostObs;
$fpo->fringepo();

Class fringePostObs{

	function fringepo(){
		if ($_REQUEST["do"]=="prep"){
			$this->transferprep($this->checkrecorder($_REQUEST["dd"]));
		}
		elseif($_REQUEST["do"]=="status"){
			$this->tranferstatus($this->checkrecorder($_REQUEST["dd"]));
		}

		//print_r($this->checkrecorder($_REQUEST["dd"]));
	}

	function checkrecorder($ddir){
		//$recorder = ["flexbuffhb","flexbuffke"];
		$expinfo = new SplFileObject($_SERVER['DOCUMENT_ROOT']."scheduling/fringeout/".$ddir."/expinfo.txt");
		while (!$expinfo->eof()) {
			$line = $expinfo->fgets();
			//get recorder
			if(stripos($line,'Recorders: ')!==false){
				$recorder = substr($line,11);
				$recorder = explode(",",$recorder);
				$recorder = array_filter($recorder);
			}
			//get expname
			if(stripos($line,'Exp name')!==false){
				$exp = trim(substr($line,10));
			}
		}
		return [$recorder,$exp];
	}
	
	function transferprep($recarray){
		$recorder = $recarray[0];
		$expname = $recarray[1];
		$validrec = [];

		//mkdir at cserver
		$conssh = new ConnectSSH;
        if(!$con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"])){
            die("Unable to reach ".$GLOBALS["cserver"]);
        }
		echo $conssh->exec($con,"mkdir ".$GLOBALS['corrdir']."/mf/".$expname.";mkdir ".$GLOBALS['corrdir']."/mf/".$expname."/data/");
		ssh2_disconnect($con);

		//prep files at recorder
		foreach($recorder as $rcd){
			if(stripos(trim($rcd),"flex")!==false){
				if(!($conrcd = $conssh->connect(trim($rcd),$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
					//die("Unable to reach ".$rcd );
					echo "Unable to reach ".$rcd;
				}
				$datacount = $conssh->exec($conrcd,'vbs_ls '.$expname.'* | wc -l');
				$flist = $conssh->exec($conrcd,'vbs_ls '.$expname.'*');
				echo $conssh->exec($conrcd,'fusermount -u /mnt/mf/;');

				if($datacount>0 && strlen($flist) > 1){
					//ob_start();
					echo $conssh->exec($conrcd,'vbs_fs -n 4 -I '.$expname.'* /mnt/mf/');
					//ob_end_clean();
					array_push($validrec,$rcd);
				}
				else{
					echo "no data recorded to ".$rcd.PHP_EOL;
				}
				ssh2_disconnect($conrcd);
			}
			else{ //assume mk5
				$st = substr(trim($rcd),-2);
				if(!($conpcfs = $conssh->connect("pcfs".$st,$GLOBALS["pcfsuser"],$GLOBALS["cpassword"]))){
					//die("Unable to reach ".$rcd );
					echo "Unable to reach ".$rcd;
				}
				$datacount = $conssh->exec($conpcfs,'echo "DirList | grep '.$expname.' | wc -l" | ssh oper@mk5'.$st);
				$_dirlist = $conssh->exec($conpcfs,'echo "DirList | grep '.$expname.' | tail -1" | ssh oper@mk5'.$st);
				$dirList = array_values(array_filter(preg_split('/\s+/', trim($_dirlist))));
				$startBit = $dirList[2];
				$stopBit = $dirList[3];

				if($datacount>0){
					//clear /mnt/mf
					$_str = $conssh->exec($conpcfs,"echo 'rm /mnt/mf/*' | ssh oper@mk5".$st);
					//disk2file needs to complete witin ~20 seconds, which is ~12s scan
					$d2f = $conssh->exec($conpcfs,'echo "echo \'disk2file=/mnt/mf/'.$dirList[1].':'.$startBit.':'.$stopBit.':w\' | nc -w 1 localhost 2620" | ssh oper@mk5'.$st);
					array_push($validrec,$rcd);
				}
				else{
					echo "no data recorded to ".$rcd.PHP_EOL;
				}
			}
			
		}

		//echo "transferring data, please wait\n\n";
		echo "Experiment: ".$expname.PHP_EOL;
		echo "Recorded on: ".implode(", ", $validrec).PHP_EOL;
		//echo "Transfer command: scp /mnt/mf/* ".$GLOBALS['cserver'].":".$GLOBALS['corrdir']."/mf/".$expname."/data/".PHP_EOL;
		//$this->tranferstatus($validrec,$exp);
	}

	function tranferstatus($recarray){
		//check if /mnt/mf/* is empty
		$recorder = $recarray[0];
		$expname = $recarray[1];

		$transfering = [];
		$completed = [];

		foreach($recorder as $rcd){
			if(!($conrcd = $conssh->connect(trim($rcd),$GLOBALS["cusername"],$GLOBALS["cpassword"]))){
				die("Unable to reach ".$rcd );
			}
			$flist =  $conssh->exec($conrcd,'ls /mnt/mf/ | wc -l;');
			
			if($flist > 0){
				array_push($transfering,$rcd);
			}
			else{
				array_push($completed,$rcd);
			}
			ssh2_disconnect($conrcd);
		}

		if(count($transfering)>0){
			echo "transferring from: ".implode(", ",$transfering).PHP_EOL;
			echo "completed: ".implode(", ",$completed).PHP_EOL;
		}
		else{
			echo "transfer finished";
		}
		
	}
	
}

?>