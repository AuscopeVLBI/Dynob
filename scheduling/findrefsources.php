<?php
//this script find reference sources dynamically based on the percentage of flux density change in the pass six months
//usage ?exp=aum040 --  this will check the source flux density variation in the pass 6 months from the date of the session, if no option, use latest mjd
require_once "../common/dbwrite.php";

$RFS = new RefSources;
$RFS->refsources(["x","s"],182); //options: band, window days

Class RefSources{
	function refsources($band,$window){

		$db = new DbWrite;
		$dbname = "dynob";

		$exper = $_GET["exp"];
		
		$rated = [];
		$combratings = [];
		foreach ($band as $bnd){
			if(isset($exper)){
				$mjdnow = $db->query($dbname,"SELECT mjd FROM Fluxmon".$bnd." where session='".$exper."'");
			}
			else{
				$mjdnow = $db->query($dbname,"SELECT mjd FROM Fluxmon".$bnd." ORDER BY mjd DESC LIMIT 1");
			}
			$mjdnow = $mjdnow[0]["mjd"];
			$mjdpass = $mjdnow - $window;

			$dbq = $db->query($dbname,"SELECT * FROM Fluxmon".$bnd." where mjd >= ".$mjdpass." AND mjd < ".$mjdnow);
			
			$sourcestd = [];
			$sourcenumobs = [];
			$numobstmp = [];

			foreach($dbq[0] as $source=>$val){
				if($source !== "id" && $source !== "mjd" && $source !== "session"){
					$numobstmp = [];
					//echo $dbq[0][$source]."<br>";
					for ($j=0;$j<count($dbq);$j++){
						//echo $dbq[$j][$source]."<br>";
						array_push($numobstmp,$dbq[$j][$source]);
					}

					$sourcenb = count(array_filter($numobstmp));
					if($sourcenb>1){ //at leat 2 (samples)
						$sourcenumobs[$source] = $sourcenb;
						$filobs = array_filter($numobstmp);
						$sourcestd[$source] = $this->std($filobs);

						//rate the sources
						$rated[$source][$bnd]=(0.75*$sourcestd[$source])/(0.25*$sourcenb);
					}
				}
			}
		}
		foreach($rated as $source=>$arr){
			$combratings[$source] = $this->addNumbers(...array_values($arr));
		}
		asort($combratings);
		print_r($combratings);
	}

	function std($arr){
        $num_of_elements = count($arr);
        $variance = 0.0;
		// calculating mean using array_sum() method
        $average = array_sum($arr)/$num_of_elements;
        foreach($arr as $i)
        {
            // sum of squares of differences between 
            // all numbers and means.
            $variance += pow(($i - $average), 2);
        }
        //return (float)sqrt($variance/$num_of_elements); //of population
		return (float)sqrt($variance/($num_of_elements-1)); //of samples
    }

	function addNumbers($number1, $number2) {
		return $number1 + $number2;
	}
}
?>