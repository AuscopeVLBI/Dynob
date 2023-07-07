<?php

//Modivex::vex('z22332',[$_SERVER['DOCUMENT_ROOT']."tmp/z22332/z22332.vex"],'mixed',"si");
//Modivex::vexfreq($_SERVER['DOCUMENT_ROOT']."tmp/z22332/","mixed");

class Modivex{
	public static function vex($exper,$vexfile,$cmode,$ctag){

		for($i=0;$i<count($vexfile);$i++){
			$file[$i] = fopen($vexfile[$i], "r+");
		}
		if($cmode == "mixed"){
			if($ctag=="fringe"){
				$template = fopen("template/mixedmodefringe.vex", "r");
			}
			else{
				$template = fopen("template/mixedmode.vex", "r");
			}
		}
		elseif($cmode == "vgos"){
			$template = fopen("template/vgosmode.vex", "r");
		}
		$buffer = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/"."buffer.vex", "w+");
		chmod($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/"."buffer.vex",0777);
		$mark = ""; //switch on or off

		//define vex sections
		$vexsec = ['VEX_rev','$GLOBAL;','$EXPER;','$STATION;','$MODE;','$SCHED;','$SITE;','$ANTENNA;','$DAS;',
			'$SOURCE;','$BBC;','$IF;','$TRACKS;','$FREQ;','$PASS_ORDER;','$ROLL;','$PHASE_CAL_DETECT;'];

		//find section from template
		while(!feof($template)) {
			$line = fgets($template);
			//turn mark off if new section
			foreach ($vexsec as $vsec){
				if (strpos($line,$vsec)!==false){
					$mark = "";
				}
			}
			//Sections to change are MODE, BBC, IF, TRACKS, FREQ (need flexibility), PHASE_CAL_DETECT (for vgos)
			switch (true){
				case strpos($line,'$MODE;')!==false:
					$mark = "mode";
					$arrMODE = [];
				break;
				case strpos($line,'$BBC;')!==false:
					$mark = "bbc";
					$arrBBC = [];
				break;
				case strpos($line,'$IF;')!==false:
					$mark = "if";
					$arrIF = [];
				break;
				case strpos($line,'$TRACKS;')!==false:
					$mark = "tracks";
					$arrTRACKS = [];
				break;
				case strpos($line,'$FREQ;')!==false:
					$mark = "freq";
					$arrFREQ = [];
				break;
				case strpos($line,'$PHASE_CAL_DETECT;')!==false:
					$mark = "pcd";
					$arrPCD = [];
				break;
			}
			//store sections in arrays
			switch($mark){
				case "mode":
					//assume only one 'def'
					//if(stripos($line,'def')===false){
						array_push($arrMODE,$line);
					//}
				break;
				case "bbc":
					array_push($arrBBC,$line);
				break;
				case "if":
					array_push($arrIF,$line);
				break;
				case "tracks":
					array_push($arrTRACKS,$line);
				break;
				case "freq":
					array_push($arrFREQ,$line);
				break;
				case "pcd":
					array_push($arrPCD,$line);
				break;
			}
		}

		$unmodedef = 0;

		//find and combine SCHED and SOURCE from multiple vex
		$sourcemark = 0;
		if(count($vexfile)>1){
			for($i=0;$i<count($vexfile);$i++){
				while(!feof($file[$i])) {
					$line = fgets($file[$i]);
					//turn mark off if new section
					foreach ($vexsec as $vsec){
						if (strpos($line,$vsec)!==false){
							$mark = "";
						}
					}
					switch (true){
						case strpos($line,'$SCHED;')!==false:
							$mark = "sched";
							if(!$arrSCHED){
								$arrSCHED = [];
							}
						break;
						case strpos($line,'$SOURCE;')!==false:
							$mark = "source";
							if(!$arrSOURCE){
								$arrSOURCE = [];
							}
						break;
					}
					//store sections in arrays
					switch($mark){
						case "sched":
							if (strpos($line,"*")===false && strpos($line,"SCHED")==false){
								array_push($arrSCHED,$line);
							}
						break;
						case "source":
							if (strpos($line,"def ")!==false){
								$sourcemark = 1;
								$sou = substr($line,strpos($line,"def")+4,-1);
								foreach ($arrSOURCE as $asou){
									if (strpos($asou,$sou)!==false){
										$sourcemark = 0;
									}
								}
							}
							if (strpos($line,"enddef")!==false){
								if($sourcemark == 1){
									array_push($arrSOURCE,$line);
								}
								$sourcemark = 0;
							}
							if($sourcemark==1){
								array_push($arrSOURCE,$line);
							}
						break;
					}
				}
				fseek($file[$i],0);
			}
			//insert $SCHED and $SOURCE headers to arrays
			array_unshift($arrSCHED,'$SCHED;'.PHP_EOL);
			array_push($arrSCHED,'*================================================================='.PHP_EOL);
			array_unshift($arrSOURCE,'$SOURCE;'.PHP_EOL);
			array_push($arrSOURCE,'*================================================================='.PHP_EOL);
		}

		//set mode to mode
		while(!feof($file[0])) {
			$line = fgets($file[0]);
			if (strpos($line,'mode =')!==false || strpos($line,'mode=')!==false ){
				if ($unmodedef == 0){
					$_mmode = explode('=',$line);
					$_mmode = trim($_mmode[1]);
					for ($i=0;$i<count($arrMODE);$i++){
						if(stripos($arrMODE[$i],'def ')!==false){
							$arrMODE[$i] = "    def ".$_mmode.PHP_EOL;
							break;
						}
					}
					$unmodedef = 1;
					break;
				}
			}
		}
		fseek($file[0],0);

		//Write new vexfile based on 1st vex
		$mark = "";
		while(!feof($file[0])) {
			$line = fgets($file[0]);
			foreach ($vexsec as $vsec){
				if (strpos($line,$vsec)!==false){
					$mark = "";
				}
			}
			switch (true){
				case strpos($line,'$MODE;')!==false:
					$mark = "mode";
				break;
				case strpos($line,'$BBC;')!==false:
					$mark = "bbc";
				break;
				case strpos($line,'$IF;')!==false:
					$mark = "if";
 				break;
				case strpos($line,'$TRACKS;')!==false:
					$mark = "tracks";
				break;
				case strpos($line,'$FREQ;')!==false:
					$mark = "freq";
				break;
				case strpos($line,'$PHASE_CAL_DETECT;')!==false:
					$mark = "pcd";
				break;
				case strpos($line,'$SCHED;')!==false:
					if (count($vexfile)>1){
						$mark = "sched";
					}
				break;
				case strpos($line,'$SOURCE;')!==false:
					if (count($vexfile)>1){
						$mark = "source";
					}
				break;
			}
			switch($mark){
				case "mode":
					foreach($arrMODE as $_line){
						fwrite($buffer,$_line);
					}
					$mark = "skip";
				break;
				case "bbc":
					foreach($arrBBC as $_line){
						fwrite($buffer,$_line);
					}
					$mark = "skip";
				break;
				case "if":
					foreach($arrIF as $_line){
						fwrite($buffer,$_line);
					}
					$mark = "skip";
				break;
				case "tracks":
					foreach($arrTRACKS as $_line){
						fwrite($buffer,$_line);
					}
					$mark = "skip";
				break;
				case "freq":
					foreach($arrFREQ as $_line){
						fwrite($buffer,$_line);
					}
					$mark = "skip";
				break;
				case "pcd":
					foreach($arrPCD as $_line){
						fwrite($buffer,$_line);
					}
					$mark = "skip";
				break;
				case "skip":
					break;
				break;
				case "":
					fwrite($buffer,$line);
				break;
				case "sched":
					foreach($arrSCHED as $_line){
						fwrite($buffer,$_line);
					}
					$mark = "skip";
				break;
				case "source":
					foreach($arrSOURCE as $_line){
						fwrite($buffer,$_line);
					}
					$mark = "skip";
				break;
			}
		}

		//fclose
		for($i=0;$i<count($vexfile);$i++){
			fclose($file[$i]);
		}
		fclose($template);
		fclose($buffer);
	}

	public static function vexfreq($bufferdir,$cmode,$cstations,$vgosstations){
		//update the FREQ section after getting the log
		//$logs = shell_exec("ls ".$bufferdir."*.log");
		//$logs = array_filter(preg_split('/\s+/', $logs));

		$vexsec = ['$GLOBAL;','$EXPER;','$STATION;','$MODE;','$SCHED;','$SITE;','$ANTENNA;','$DAS;',
			'$SOURCE;','$BBC;','$IF;','$TRACKS;','$FREQ;','$PASS_ORDER;','$ROLL;','$PHASE_CAL_DETECT;'];

		//check the $mode for the $freq for each station
		$fmode = [];
		$vexfile = new SplFileObject($bufferdir."buffer.vex","r");
		$vexfile2 = new SplFileObject($bufferdir."updatedfreqs.vex","w");
		$mark = 0;
		$fmodemark = 0;
		while (!$vexfile->eof()) {
			$line = $vexfile->fgets();

			//turn mark off if new section
			foreach ($vexsec as $vsec){
				if (strpos($line,$vsec)!==false){
					$mark = 0;
					$fmodemark = 0;
				}
			}

			if (stripos($line,'ref $FREQ')!==false && strpos($line,'*')===false){
				$_line = preg_replace('/\s+/', ' ',$line);
				$_tmp = explode("=",$_line);
				$_subexp = array_filter(explode(":",trim($_tmp[1])));
				$fmode[$_subexp[0]] = array_slice($_subexp,1);
			}

			//the $FREQ
			if(stripos($line,'$FREQ;')!==false){
				$mark = 1;
				$vexfile2->fwrite($line);
				continue;
			}
			if($mark == 1){
				if($fmodemark == 0){
					$fullresfreq = []; //store freqs for all modes
					foreach($fmode as $_mode => $_starr){
						if(stripos($line,'def '.trim($_mode))!==false){
							$_n = 0; //start line replace
							$_l = 0; //for opposite sideband
							$fmodemark = 1;
							for ($i=0;$i<count($_starr);$i++){
								$log = $bufferdir.strtolower(substr(trim($_starr[$i]),0,2))."buffer.log";
								if(file_exists($log)){
									//---get bbc from log
									$bbcs = shell_exec("grep 'bbcsx' ".$log);
									preg_match_all('/bbc(\d*)=(.*),(.),(.*)/', $bbcs, $bbc_array);
									$numu = count(array_unique($bbc_array[1]));
									$ubbc = array_slice($bbc_array[2],0,$numu);
									$uif = array_slice($bbc_array[3],0,$numu);
									if(stripos($_mode,"HB")!==false && $cmode=="mixed"){
										$ifrenamed = array_map(function($rn) { $rn = preg_replace('/[e|g]/', 'c', $rn); $rn = preg_replace('/[f|h]/', 'd', $rn); return $rn;  }, $uif); //rename e,g and f,h to c and d, respectively
										$uif = $ifrenamed;
									}
									$ubw = array_slice($bbc_array[4],0,$numu);
									//---get lo from log
									$lo = shell_exec("grep 'lo=' ".$log);
									preg_match_all('/lo=lo(.),([0-9]*.[0-9]*),(.{3})/', $lo, $lo_array);
									$numu = count(array_unique($lo_array[1]));
									$ulo = array_slice($lo_array[2],0,$numu);
									$uiflo = array_slice($lo_array[1],0,$numu);
									$usideband = array_slice($lo_array[3],0,$numu);
									//mix bbc and lo
									$resfreq = [];
									$resside = [];
									$_res = 0;
									$_ind = 0;

									//----------find freq---------
									for($j=0;$j<count($uif);$j++){
										$_ind = array_keys($uiflo,$uif[$j])[0];
										if(stripos($usideband[$_ind],"usb")!==false){
											//add
											$_res = $ulo[$_ind] + $ubbc[$j];
											if($j>0 && $ubbc[$j]<$ubbc[$j-1] && $uif[$j]==$uif[$j-1]){
												$_res = $_res + 1000;
											}
											array_push($resside,"U");
										}
										else{
											//minus
											$_res = $ulo[$_ind] - $ubbc[$j];
											if($j>0 && $ubbc[$j]>$ubbc[$j-1] && $uif[$j]==$uif[$j-1]){
												$_res = $_res + 1000;
											}
											array_push($resside,"L");
										}
										array_push($resfreq,$_res);
									}
									array_push($fullresfreq,$resfreq);
									break;
									//-------------------------------
								}
							}
						}
					}
				}
				else{
					if(stripos($line,'enddef;')!==false){
						$fmodemark = 0;
						$vexfile2->fwrite($line);
						continue;
					}
					if(stripos($line,'chan_def')!==false){
						$_expline = explode(":",$line);
						if($cmode == "mixed"){ //upper side band + 2 low
							if(stripos($_expline[2],$resside[$_n])!==false){
								$_expline[1] = " ".trim(preg_replace('/([0-9]*.[0-9]*) MHz/', number_format((float)$resfreq[$_n], 2, '.', ''), $_expline[1]))." MHz ";
								$_expline[3] = " ".trim(preg_replace('/([0-9]*.[0-9]*) MHz/', number_format((float)$ubw[$_n], 2, '.', ''), $_expline[3]))." MHz ";
								$_nline = implode(":",$_expline);
								$_n = $_n + 1;
							}
							else{
								$_expline[1] = " ".trim(preg_replace('/([0-9]*.[0-9]*) MHz/', number_format((float)$resfreq[$_l], 2, '.', ''), $_expline[1]))." MHz ";
								$_expline[3] = " ".trim(preg_replace('/([0-9]*.[0-9]*) MHz/', number_format((float)$ubw[$_l], 2, '.', ''), $_expline[3]))." MHz ";
								$_nline = implode(":",$_expline);
								$_l = 7; //assume 7th freq is the lowest
							}
							
							
						}
						else{
							$_expline[1] = " ".trim(preg_replace('/([0-9]*.[0-9]*) MHz/', number_format((float)$resfreq[$_n], 2, '.', ''), $_expline[1]))." MHz ";
							//$_expline[2] = " ".$resside[$_n]." ";
							$_expline[3] = " ".trim(preg_replace('/([0-9]*.[0-9]*) MHz/', number_format((float)$ubw[$_n], 2, '.', ''), $_expline[3]))." MHz ";
							$_nline = implode(":",$_expline);
							$_n = $_n + 1;
						}
						
						$line = $_nline;
					}
				}
			}

			$vexfile2->fwrite($line);
		}

		chmod($bufferdir."updatedfreqs.vex",0777);
	}

}
?>
