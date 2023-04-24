<?php
//this script converts re-estimated SEFD from database to equip catalog 

require_once "../common/dbwrite.php";
require_once "../common/global.php";
require_once "../pmonitor/stats.php";

//$C=new Sefd2cat;
//$C->createEquipCat($_GET["exp"]);

Class Sefd2cat{

	function createEquipCat($expname){

		$db = new DbWrite;
		if (isset($expname)){
			$dbq = $db->query("dynob",'SELECT * FROM SEFDmon WHERE session="'.$expname.'"');
		}
		else{
            //median from 5 latest sessions
			$dbq = $db->query("dynob",'SELECT * FROM SEFDmon ORDER BY mjd DESC LIMIT 5');
		}

		$sefdall = []; //$sefdall[station][band] =[1 or 5 values];
		for ($i=0;$i<count($dbq);$i++){
			foreach ($dbq[$i] as $entry => $val){
				if($entry=="id" || $entry=="mjd" || $entry=="session" || $entry=="expcode" || $val == NULL || $val == ""){
					continue;
				}
				else{
					if(strlen($entry) == 3){
						$band = substr($entry,-1);
						$st = substr($entry,0,2);
						$sefdall[$st][$band] = [];
					}
				}
			}
		}

		for ($i=0;$i<count($dbq);$i++){
			foreach ($dbq[$i] as $entry => $val){
				if($entry=="id" || $entry=="mjd" || $entry=="session" || $entry=="expcode" || $val == NULL || $val == ""){
					continue;
				}
				else{
					if(strlen($entry) == 3){
						$band = substr($entry,-1);
						$st = substr($entry,0,2);
						array_push($sefdall[$st][$band],$val);
					}
				}
			}
		}

		$file = new SplFileObject($_SERVER['DOCUMENT_ROOT']."scheduling/dynflux/equip.cat");
		$newquipcat = new SplFileObject($_SERVER['DOCUMENT_ROOT']."scheduling/dynflux/dynequip.cat", "w");
		
		while ( ! $file->eof()) {
			$line = $file->fgets();
			if(substr(trim($line),0,1)!=="*" && substr(trim($line),0,1)!==" "){
				sscanf($line,"%s %s %s %s %s %s %d %s %d %s",$_antname, $_antcode, $_dat, $_heads, $_taplen, $_X, $_sefdx, $_S, $_sefds, $_rem);
				$remstr = substr($line,stripos($line,$_sefds." ".$_rem)+strlen($_sefds." ".$_rem));

				$_antcodeunified = "";
				if(in_array($_antcode,$GLOBALS["equipalias"])){
					$_antcodeunified = array_search($_antcode, $GLOBALS["equipalias"]);
				}
				else{
					$_antcodeunified = $_antcode;
				}

				if(array_key_exists(trim($_antcodeunified),$sefdall)){
					$sefdarr = [];
					foreach ($sefdall[$_antcodeunified] as $band => $arr){
						$medsefd = ceil(Stats::median($arr)/100) * 100;
						array_push($sefdarr,str_pad($band,3," ",STR_PAD_LEFT)." ".str_pad($medsefd,5," ",STR_PAD_LEFT)." ");
					}
					$line = " ".str_pad(trim($_antname),11," ",STR_PAD_RIGHT).str_pad(trim($_antcode),4," ",STR_PAD_RIGHT).str_pad(trim($_dat),9," ",STR_PAD_RIGHT).str_pad($_heads,7," ",STR_PAD_BOTH)." ".str_pad($_taplen,7," ",STR_PAD_BOTH).implode("",$sefdarr).$_rem.$remstr;
				}
			}
			$newquipcat->fwrite($line);
		}
		return "OK";
	}

}

?>