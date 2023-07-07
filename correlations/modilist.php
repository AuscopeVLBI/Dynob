<?php
//this script creates filelist and corrinput.txt
require_once "../common/SSHcon.php";
require_once "../common/global.php";
ignore_user_abort(false);

class Modilist{

	public $newlist = [];

	public static function createfilelist($exper,$explabel,$cmode,$corrdir,$cstations,$uraid,$ufull){
		$machine = [];
		foreach($ufull as $tmpu){
			$tmpuexp = explode(" ",$tmpu);
			if (!isset($machine[$tmpuexp[2]])){
				$machine[$tmpuexp[2]] = [];
			}
			array_push($machine[$tmpuexp[2]],[$tmpuexp[1],$tmpuexp[3],$tmpuexp[0]]);
		}

		//read log to see what recorder
		$recorder = [];
		$vdifmode = [];
		foreach($cstations as $cs){
			if (!file_exists($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs."buffer.log")){
				continue;
			}
			$logfile = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs."buffer.log", "r");
			while (!feof($logfile)){
				$line = fgets($logfile);
				if(stripos($line,"Recorder 1=")!==false){
					$pos1 = stripos($line,"Recorder 1=");
					$pos2 = stripos($line,"Recorder 2=");
					$recorder[$cs] = strtolower(trim(substr($line,$pos1+11,$pos2-$pos1-11)));
					break;
				}
				elseif(stripos($line,"Recorder=")!==false){
					$pos = stripos($line,"Recorder=");
					$recorder[$cs] = strtolower(trim(substr($line,$pos+9)));
				}
			}

			if(in_array($cs,$GLOBALS["vgosstations"]) && in_array($cs,$GLOBALS["localstations"]) ){
				//read log to check vdifmode
				$vdifmode[$cs] = strtoupper(trim(shell_exec('grep -i "vdif_8000" '.$_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs.'buffer.log | tail -1 | cut -d " " -f 4')));
				$globvdifmode = strtoupper(trim(shell_exec('grep -i "vdif_8000" '.$_SERVER['DOCUMENT_ROOT']."tmp/".$exper."/".$cs.'buffer.log | tail -1 | cut -d " " -f 4')));
			}
		}

		//set datastream
		if($cmode=="mixed"){
			$dts = $GLOBALS["dtsmixeds"];
		}
		elseif($cmode=="vgos"){
			$dts = $GLOBALS["dtsvgos"];
		}

		//create filelists
		/*
		$sum = ["flexbuff"=>"vsum","mark5b"=>"m5bsum"];
		foreach($uraid as $raid){
			$conssh = new ConnectSSH;
			if(!$con = $conssh->connect($raid,$GLOBALS["cusername"],$GLOBALS["cpassword"])){
				die("Unable to reach ".$raid);
			}

			foreach($machine[$raid] as $mac){
				$st = $mac[0];
				$path = $mac[1];
				$exp = $mac[2].$st;
				if(in_array($st,$GLOBALS["vgosstations"])){
					foreach ($dts[$st] as $_dts){
						echo $conssh->exec($con,"cd ".$corrdir.";".$sum[$recorder[$st]]." -s ".$path.$exp."/*_".$_dts."* >> ".$st.$_dts.".filelist");
					}
				}
				else{
					echo $conssh->exec($con,"cd ".$corrdir.";".$sum[$recorder[$st]]." -s ".$path.$exp."/* >> ".$st.".filelist");
				}
			}
		}
		*/

		//create filelist using vsum.gappy.py for vgos stations
		$sum = ["flexbuff"=>"vsum","mark5b"=>"m5bsum"];
		foreach($uraid as $raid){
			$conssh = new ConnectSSH;
			if(!$con = $conssh->connect($raid,$GLOBALS["cusername"],$GLOBALS["cpassword"])){
				$message = "Exit: Unable to reach ".$raid."<br>";
				$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
				die();
			}

			foreach($machine[$raid] as $mac){
				$st = $mac[0];
				$path = $mac[1];
				$exp = $mac[2].$st;				

				if(in_array($st,$GLOBALS["vgosstations"])){
					//first check if all datastreams of one observation exist
					$thelist = [];
					$thelistwithpath = [];
					foreach ($dts[$st] as $_dts){
						if($explabel=="mf" || $explabel=="si"){
							$thelisttmp = $conssh->exec($con, "ls ".$path.$mac[2]."_".$st."*_".$_dts."* | cut -d '_' -f 2-3");
							$thelisttmp2 = $conssh->exec($con, "ls ".$path.$mac[2]."_".$st."*_".$_dts."*");
						}
						else{
							$thelisttmp = $conssh->exec($con, "ls ".$path.$exp."/*_".$_dts."* | cut -d '_' -f 2-3");
							$thelisttmp2 = $conssh->exec($con, "ls ".$path.$exp."/*_".$_dts."*");
						}

						$listarr = preg_split('/[\s]+/', $thelisttmp);
						sort($listarr);
						$listarr = array_filter($listarr);
						array_push($thelist,$listarr);

						$listarr2 = preg_split('/[\s]+/', $thelisttmp2);
						sort($listarr2);
						$listarr2 = array_filter($listarr2);
						array_push($thelistwithpath,$listarr2);
					}

					//filter out observations with missing datastreams
					$thelist = array_intersect(...$thelist);
					foreach ($thelist as $therl){
						$tmpstrarr = [];
						for ($i=0;$i<count($dts[$st]);$i++){
							$matches  = preg_grep ('/(.*)'.$therl.'_(.*)/i', $thelistwithpath[$i]);
							$matches = array_values($matches);
							
							if(count($matches)>0){
								if(in_array($st,$GLOBALS["localstations"])){
									$tmpstr = $conssh->exec($con,"cd ".$corrdir."; ~/vsum.gappy.dyn.py ".$matches[0]." ".$vdifmode[$st]);
								}
								else{
									$tmpstr = $conssh->exec($con,"cd ".$corrdir."; ~/vsum.gappy.dyn.py ".$matches[0]." ".$globvdifmode);
								}
								//$tmpstr = trim($conssh->exec($con,"cd ".$corrdir."; cat tf.list"));
								if (strpos($tmpstr,"badscan")!==false){
									break;
								}
								else{
									$tmpstr = str_replace(PHP_EOL, '', $tmpstr);
									array_push($tmpstrarr,$tmpstr);
								}
							}
						}
						
						if (count($tmpstrarr) == count($dts[$st])){
							for ($i=0;$i<count($dts[$st]);$i++){
								echo $conssh->exec($con, "cd ".$corrdir."; echo ".$tmpstrarr[$i]." >> ".$st.$dts[$st][$i].".filelist");
							}
						}
					}
				}
				else{
					if($recorder[$st] == "" || !isset($recorder[$st])){
						$recorder[$st] = "flexbuff"; //assume flexbuff on default
					}

					if($explabel=="mf" || $explabel=="si"){
						//echo $conssh->exec($con,"cd ".$corrdir."; vsum -s ".$path.$mac[2]."_".$st."_* >> ".$st.".filelist");
						echo $conssh->exec($con,"cd ".$corrdir."; ".$sum[$recorder[$st]]." -s ".$path.$mac[2]."_".$st."_* >> ".$st.".filelist");
					}
					else{
						echo $conssh->exec($con,"cd ".$corrdir.";".$sum[$recorder[$st]]." -s ".$path.$exp."/* > ".$st.".filelist");
					}
				}
			}
		}
	}
}

?>
