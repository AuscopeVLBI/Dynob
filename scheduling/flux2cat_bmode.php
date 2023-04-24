<?php
//this script converts flux from database to flux catalog 

require_once "../common/dbwrite.php";
require_once "../common/global.php";

$C=new Flux2cat;
$C->createFluxCat($_GET["exp"]);

Class Flux2cat{

	function createFluxCat($expname){

		//first download latest SKED flux catalogue to make up for sources not in database
		$skedfluxloc = $GLOBALS["skedfluxloc"];
		$file_name = $_SERVER['DOCUMENT_ROOT']."scheduling/dynflux/flux.cat";
		unlink($file_name);
		if(!file_put_contents($file_name,file_get_contents($skedfluxloc))){
			echo "Error downloading the SKED flux catalogue";
		}

		$db = new DbWrite;

		//X-band
		if (isset($expname)){
			$dbq = $db->query("dynob",'SELECT * FROM Fluxmonx WHERE session="'.$expname.'"');
		}
		else{
			$dbq = $db->query("dynob",'SELECT * FROM Fluxmonx ORDER BY mjd DESC LIMIT 2');
			$items0 = array_slice($dbq[0],4); //latest (remove id, session, mjd from array)
			$items1 = array_slice($dbq[1],4); //2nd latest
			foreach($items0 as $source => $flux){
				if($flux =="" || $flux ==NULL){
					if($items1[$source]!=="" && $items1[$source]!==NULL){
						//replacing the empty flux with the second latest
						$dbq[0][$source] = $items1[$source];
					}
				}
			}
		}
		$refluxmodelx = [];

		foreach ($dbq[0] as $sou => $reflux){
			if($sou=="id" || $sou=="mjd" || $sou=="session" || $reflux == NULL || $sou=="UTC"){
				continue;
			}
			else{
				$sou = preg_replace('/m/', "-", $sou);
				$sou = preg_replace('/p/', "+", $sou);
				//echo $sou ." is ".$reflux.PHP_EOL;
				$refluxmodelx[$sou] = $reflux;
			}
		}

		//S-band
		if (isset($expname)){
			$dbq = $db->query("dynob",'SELECT * FROM Fluxmons WHERE session="'.$expname.'"');
		}
		else{
			$dbq = $db->query("dynob",'SELECT * FROM Fluxmons ORDER BY mjd DESC LIMIT 2');
			$items0 = array_slice($dbq[0],4); //latest (remove id, session, mjd from array)
			$items1 = array_slice($dbq[1],4); //2nd latest
			foreach($items0 as $source => $flux){
				if($flux =="" || $flux ==NULL){
					if($items1[$source]!=="" && $items1[$source]!==NULL){
						//replacing the empty flux with the second latest
						$dbq[0][$source] = $items1[$source];
					}
				}
			}
		}
		$refluxmodels = [];

		foreach ($dbq[0] as $sou => $reflux){
			if($sou=="id" || $sou=="mjd" || $sou=="session" || $reflux == NULL){
				continue;
			}
			else{
				$sou = preg_replace('/m/', "-", $sou);
				$sou = preg_replace('/p/', "+", $sou);
				//echo $sou ." is ".$reflux.PHP_EOL;
				$refluxmodels[$sou] = $reflux;
			}
		}

		$file = new SplFileObject($_SERVER['DOCUMENT_ROOT']."scheduling/dynflux/flux.cat");
		$newfluxcat = new SplFileObject($_SERVER['DOCUMENT_ROOT']."scheduling/dynflux/dynflux_bm.cat", "w");

		$skedsources = []; //list of sources in sked catalogue
		while ( ! $file->eof()) {
			$line = $file->fgets();
			if ($line[0]==="*" || $line[0]==" "){
				continue;
			}
			else{
				sscanf($line,"%s %s %s %s",$_source, $_band, $_type, $_ext);
				array_push($skedsources,$_source);

				if($_band == "X"){
					$refluxmodel = $refluxmodelx;
				}
				elseif($_band == "S"){
					$refluxmodel = $refluxmodels;
				}

				if(array_key_exists($_source,$refluxmodel)){
					if($_type == "M"){
						//sscanf($line,"%s %s %s %f %s %s %s %s %s",$_source, $_band, $_type, $_flux, $_majax, $_ratio, $_pa, $_off1, $_off2 );
						//$refluxcatline = str_pad(trim($_source),10," ").str_pad(trim($_band),5," ").str_pad(trim($_type),4," ").str_pad($refluxmodel[$_source],6," ").str_pad($_majax,6," ").str_pad($_ratio,6," ").str_pad($_pa,5," ").str_pad($_off1,5," ").$_off2;
						$refluxcatline = str_pad(trim($_source),9," ").str_pad(trim($_band),2," ").str_pad("B",2," ").str_pad("0.0",4," ").str_pad($refluxmodel[$_source],5," ",STR_PAD_LEFT)." 13000.0";
					}
					elseif($_type == "B"){
						$refluxcatline = str_pad(trim($_source),9," ").str_pad(trim($_band),2," ").str_pad(trim($_type),2," ").str_pad("0.0",4," ").str_pad($refluxmodel[$_source],5," ",STR_PAD_LEFT)." 13000.0";
						//$refluxcatline = str_pad($_source,10," ").str_pad($_band,3," ").str_pad($_type,2," ").str_pad(".00",5," ").str_pad($refluxmodel[$_source],5," ")."13000.0";
					}
					$newfluxcat->fwrite($refluxcatline.PHP_EOL);
				}
				else{
					$newfluxcat->fwrite($line);
				}
				
			}
		}

		//add entries to dynflux if source not in sked flux catalogue
		$skedsources = array_unique($skedsources);
		$refluxsources = array_keys($refluxmodelx);

		$diffarr = array_diff($refluxsources,$skedsources);
		$newfluxmodel = [];

		foreach($diffarr as $source){
			//create a B-model flux
			array_push($newfluxmodel,str_pad(trim($source),9," ").str_pad("X",2," ").str_pad("B",2," ").str_pad("0.0",5," ").str_pad($refluxmodelx[$source],5," ")."13000.0");
			array_push($newfluxmodel,str_pad(trim($source),9," ").str_pad("S",2," ").str_pad("B",2," ").str_pad("0.0",5," ").str_pad($refluxmodels[$source],5," ")."13000.0");
		}
		$appnewflucat = implode(PHP_EOL,$newfluxmodel);
		$newfluxcat->fwrite($appnewflucat);

		return "OK";
	
	}

}
?>