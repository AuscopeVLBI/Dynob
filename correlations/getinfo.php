<?php
require_once "../common/SSHcon.php";
require_once "../common/global.php";

class Getinfo{

	public static function vexstart(){
		$fvex = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$_SESSION["exper"]."/".'buffer.vex','r');
		while (!feof($fvex)) {
			$line = fgets($fvex);
			if(stripos($line,"exper_nominal_start")!==false){
				break;
			}
		}
		fclose($fvex);
		$epoch = substr($line,-20,-2);
		return $epoch;
	}

	public static function vexstop(){
		$fvex = fopen($_SERVER['DOCUMENT_ROOT']."tmp/".$_SESSION["exper"]."/".'buffer.vex','r');
 		while (!feof($fvex)) {
		$line = fgets($fvex);
				if(stripos($line,"exper_nominal_stop")!==false){
					break;
				}
		}
		fclose($fvex);
		$epoch = substr($line,-20,-2);
 		return $epoch;
	}

	public static function locate($dynexp,$vcCorrDir,$explabel){
		//check raid
		$raidmac = [];
		$raidpath = [];
		//outputs
		$expstations = [];
		$uraidpath = [];
		$umac = [];
		$ufull = [];
		$output = [];

		if($explabel == "mf"){
			array_push($raidmac,$GLOBALS["cserver"]);
			$raidpath[$GLOBALS["cserver"]] = [$vcCorrDir."data/"];
		}
		else{
			$consql = new mysqli($GLOBALS["sqlserver"],$GLOBALS["sqluser"],$GLOBALS["sqlpass"],"dynob");
			if ($consql->connect_error){
				$message = "Exit: MySQL Connection failed:". $consql->connect_error."<br>";
				$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
				die();
			}
			$sql = "SELECT id, machine, path FROM RaidList";
			$result = $consql->query($sql);
			if ($result->num_rows > 0) {
				while($row = $result->fetch_assoc()) {
					if (!in_array($row["machine"],$raidmac)){
						array_push($raidmac, $row["machine"]);
						$raidpath[$row["machine"]] = [$row["path"]];
					}
					else{
						array_push($raidpath[$row["machine"]],$row["path"]);
					}
					//edited for data under data
					array_push($raidmac,$GLOBALS["cserver"]);
					$raidpath[$GLOBALS["cserver"]] = [$vcCorrDir."data/"];
				}
			}
			$consql->close();
		}

		$uniraid = array_unique($raidmac);
		foreach($uniraid as $mac){
			$uraidpath[$mac] = [];
			$conssh = new ConnectSSH;
			//$mmm = $mac;
			//$message = "Exit: $GLOBALS['cusername'],$GLOBALS['cpassword'])".$mmm.PHP_EOL;
			//$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			//die();
			$conraid = "";
			if(!$conraid = $conssh->connect($mac,$GLOBALS["cusername"],$GLOBALS["cpassword"])){
				$message = "Machine not reachable: ".$mac."<br>";
				$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
				echo $message;
				continue;
			}
			$rpath = $raidpath[$mac];

			foreach($rpath as $tmprp){
				$list =$conssh->exec($conraid,"ls $tmprp");
				$listarr = preg_split('/[\s]+/', $list);
				$listarr = array_filter($listarr);
				$_a = array_values($listarr);

				foreach($_a as $a){
					foreach ($dynexp as $exper){
						if(stripos($a,$exper)!==false){
							if ($explabel!=="mf"){
								if(substr($a,strlen($exper),1)!=="_"){ //normal session
									if ($mac!==$GLOBALS["cserver"]){
										$_station = substr($a,strlen($exper),2);
										if(!in_array($_station,$expstations)){
											array_push($expstations, $_station);
										}
									}
								}
								else{ //si where all ht sessions are all in one folder
									$_station = substr($a,strlen($exper)+1,2);
									if(!in_array($_station,$expstations)){
										array_push($expstations, $_station);
									}
								}
							}
							elseif($explabel=="mf"){
								$_station = substr($a,strlen($exper)+1,2);
								if(!in_array($_station,$expstations)){
									array_push($expstations, $_station);
								}
							}
							if(!in_array($mac,$umac)){
								array_push($umac,$mac);
							}
							$_str = $exper." ".$_station." ".$mac." ".$tmprp." void";
							if(!in_array($_str, $ufull)){
								array_push($ufull, $_str);
							}
						}
					}
				}
			}
		}

		sort($expstations);
		sort($uraidpath);
		sort($ufull);

		$output["ustations"] = $expstations;
		//$output["uraid"] = $uraidpath;
		$output["uraid"] = $umac;
		$output["ufull"] = $ufull;
		return $output;
	}

}

?>
