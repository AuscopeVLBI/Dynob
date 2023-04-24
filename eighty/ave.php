<?php
//average from IVS for baseline performance

$ave = new Ave();

//$dfile = scandir("afile");
//$dfile = array_diff($dfile, array('.', '..'));

//foreach($dfile as $afile){
//	$fc->analyse($afile);
//}
//$ave->analyse("r1928-IVS-analysis-report-20200120-1449.txt");


if( $_REQUEST["afile"] ) {

	$afile = $_REQUEST['afile'];
	$ave->analyse($afile);
}


class Ave {

	//catalog or skd
	private $analysingfile = "";
	

	//calculated results
	public $halfyeararr = [];

	//init
	function analyse($file2Analyse){

		$linearr = [];
	
		if (!$fid = fopen($_SERVER["DOCUMENT_ROOT"]."eighty/2022a/".$file2Analyse,"r")){
			echo "fail to open ".$file2Analyse;
		}
		$this->analysingfile = $file2Analyse;
		
		$record = 0;
		while (!feof($fid)) {
			$line = fgets($fid);
			if (stripos($line,"Baseline Performance")!==false){
				$record = 1;
				continue;
			}
			if($record >= 1){
				$record = $record + 1;
			}
			if ($record >= 8){
				if(stripos($line,"------")!==false){
					$record == 0;
					break;
				}
				sscanf($line, "%s %d %s %s %f", $bl, $sched, $recover, $used, $perc);

				if($perc > 0.0){
					array_push($linearr,$perc);
				}
			}
		}
		fclose($fid);

		$mean = array_sum($linearr) / count($linearr);
		

		$expname = explode('-',$this->analysingfile)[0];

		echo $expname." ".$mean;

	}
}
?>
