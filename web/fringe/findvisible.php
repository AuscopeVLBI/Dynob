<?php


$dt = explode(",",$_GET["dt"]);
$sts = $_GET["sts"];

$C = new Findvisible;
$C->findVisible($dt,$sts);

Class Findvisible{
	
	
	function findVisible($dt,$sts){

		
		$jd=gregoriantojd($dt[0],$dt[1],$dt[2]);
		$jd = $jd - 0.5 + $dt[3]/24 + $dt[4]/1440 + $dt[5]/86400;
		//---the date in mjd---------
		$jd1 = $jd-2400000;
		//next 30 minutes
		$jd2 = $jd-2400000+0.02083;
		//--------------------------

		//---get station pos--------
		$starr = explode(",",$sts);
		$stused = [];
		$poslist = new SplFileObject($_SERVER["DOCUMENT_ROOT"]."catalogs/position.cat");
		

		foreach($starr as $_stname){
			$st = strtolower(substr($_stname,0,2));
			while (!$poslist->eof()) {
				$line = $poslist->fgets();
				if( strtolower(substr($line,0,2)) == $st){
					array_push($stused,$line);
					$poslist->seek(1);
					break;
				}
			}
		}
		//--------------------------

		//---get source from catalog---------
		$vissources = [];
		$slist = new SplFileObject($_SERVER["DOCUMENT_ROOT"]."catalogs/source.cat.geodetic.good");
		while (!$slist->eof()) {
			$line = $slist->fgets();
			if(substr($line,0,1)!=="*"){
				sscanf($line,"%s %s %d %d %f %d %d %f",$sname,$sname2,$rah,$ram,$ras,$ded,$dem,$des);
				$ra = ($rah + $ram/60 + $ras/3600); //in hour angle

				if ($ded<0){
					$sign = -1;
				}
				else{
					$sign = 1;
				}
				$delta = $sign * (abs($ded) + $dem/60 + $des/3600); // in angle * M_PI/180;

				$visible = 0;
				foreach ($stused as $stpos){
					sscanf($stpos,"%s %s %f %f %f %d %f %f",$stcode,$stname,$stx,$sty,$stz,$stocc,$stlon,$stlat);
					$ObsLONG = $this->xyz2ell([$stx,$sty,$stz])[0];
					$ObsLAT = $this->xyz2ell([$stx,$sty,$stz])[1];

					//hour angle
					$LST1=24*$this->lst(($jd1), $ObsLONG*M_PI/180);
					$HA1=$LST1-$ra;
					//hour angle next 30 min
					$LST2=24*$this->lst(($jd2), $ObsLONG*M_PI/180);
					$HA2=$LST2-$ra;

					//check elevation for visibility
					$elv1 = asin(sin($ObsLAT*M_PI/180)*sin($delta*M_PI/180)+cos($ObsLAT*M_PI/180)*cos($delta*M_PI/180)*cos(15*$HA1*M_PI/180)) * 180/M_PI;
					$elv2 = asin(sin($ObsLAT*M_PI/180)*sin($delta*M_PI/180)+cos($ObsLAT*M_PI/180)*cos($delta*M_PI/180)*cos(15*$HA2*M_PI/180)) * 180/M_PI; //next 30 min

					$az1=360-(acos( (sin($delta*M_PI/180)-sin($elv1*M_PI/180)*sin($ObsLAT*M_PI/180))/(cos($elv1*M_PI/180)*cos($ObsLAT*M_PI/180))) * 180/M_PI);
					$az2=360-(acos( (sin($delta*M_PI/180)-sin($elv2*M_PI/180)*sin($ObsLAT*M_PI/180))/(cos($elv2*M_PI/180)*cos($ObsLAT*M_PI/180))) * 180/M_PI);
					//see flexbuffhb:~/ElCalc.m for more on azimuth

					if($elv1>5 && $elv2>5 && $az1 >5 && $az2 >5 ){
						$visible = 1;
					}
					else{
						$visible = 0;
						break;
					}
				}
				if($visible == 1){
					array_push($vissources,$sname);
				}
			}
		}
		//----------------------------------

		//---get flux from catalog---------
		if(file_exists($_SERVER["DOCUMENT_ROOT"]."catalogs/flux.nocomm.cat")){
			$ftstamp = filemtime($_SERVER["DOCUMENT_ROOT"]."catalogs/flux.nocomm.cat");
		}
		else{
			$ftstamp = 0;
		}
		$tstamp = gmmktime($dt[3], $dt[4], $dt[5], $dt[0], $dt[1], $dt[2]);
		$diffstamp = $tstamp - $ftstamp;

		//create flux without comments
		if(!file_exists($_SERVER["DOCUMENT_ROOT"]."catalogs/flux.nocomm.cat") || ($diffstamp > (30 * 24 * 60 * 60)) ){ //30 days
			unlink($_SERVER["DOCUMENT_ROOT"]."catalogs/flux.nocomm.cat");
			$fnc = new SplFileObject($_SERVER["DOCUMENT_ROOT"]."catalogs/flux.nocomm.cat","w");
			$fc = new SplFileObject($_SERVER["DOCUMENT_ROOT"]."catalogs/flux.cat","r");
			while (!$fc->eof()) {
				$line = $fc->fgets();
				if( substr($line,0,1)!=="*" && trim($line) !== '' ){
					$fnc->fwrite($line);
				}
			}
		}

		$fncr = new SplFileObject($_SERVER["DOCUMENT_ROOT"]."catalogs/flux.nocomm.cat","r");
		$sfluxes =[];
		$allbands = [];

		while (!$fncr->eof()) {
			$line = $fncr->fgets();
			$expline = preg_split('/\s+/', trim($line));
			//foreach($vissources as $source){
			for ($i=0;$i<count($vissources);$i++){
				if($expline[0] == $vissources[$i]){
					if($expline[2] == "B"){
						$_flux = $expline[4];
					}
					else{
						$_flux = $expline[3];
					}
					if($_flux > 2.5){
						$sfluxes[$vissources[$i]][$expline[1]] = $_flux;
					}
					if(!in_array($expline[1],$allbands)){
						array_push($allbands,$expline[1]);
					}
				}
			}
		}
		//----------------------------------

		foreach ($sfluxes as $source => $bandval){
			if(count($bandval)<count($allbands)){
				unset($sfluxes[$source]);
			}
		}

		//output table html 
		echo "<table id='vistab'>
	<tr>
		<th>Source</th>".PHP_EOL;
			//foreach ($sfluxes as $band => $sourceval){
			for ($i=0;$i<count($allbands);$i++){
				echo "		<th>Flux ".$allbands[$i]."-band</th>".PHP_EOL;
			}
		echo "	</tr>".PHP_EOL;
		foreach ($sfluxes as $source => $bandval){
			echo "	<tr>".PHP_EOL;
			echo "		<td class='tdsourcename'>".$source."</td>".PHP_EOL;
			foreach($bandval as $band => $flux){
				echo "		<td>".$flux."</td>".PHP_EOL;
			}
			echo "	</tr>".PHP_EOL;
		}
		echo "</table>";

	}

	
	function lst($jd,$EastLong){
		$TJD = floor($jd - 0.5) + 0.5;
		$DayFrac = $jd - $TJD;
	
		$T = ($TJD - 2451545.0)/36525.0;
	
		$GMST0UT = 24110.54841 + 8640184.812866*$T + 0.093104*$T*$T - 6.2e-6*$T*$T*$T;
	
		// convert to fraction of day in range [0 1)
		$GMST0UT = $GMST0UT/86400.0;
	
		$GMST0UT = $GMST0UT - floor($GMST0UT);
		$LST = $GMST0UT + 1.0027379093*$DayFrac + $EastLong/(2*M_PI);
		$LST = $LST - floor($LST);
	
		return $LST;
	}
	
	function xyz2ell($pos){
		$X = $pos[0];
		$Y = $pos[1];
		$Z = $pos[2];
	
		$a=6378136.6; //Equatorial radius of the Earth
		//$f=1/298.25642;  //from ElCalc
		$f = 1/298.257223563; // Ellipsoid flattening;
		$b = $a*(1-$f); // Define Semi-minor axis;
		// Estimate auxiliary values;
		$P = sqrt($X*$X + $Y*$Y);
		$Theta = atan($Z*$a/$P*$b);
		$e = sqrt((($a*$a) - ($b*$b))/($a*$a)); // First eccentricity of The Earth;
	
		// Initial value of Latitude;
		$ppi = atan2($Z,($P*(1-($e*$e))));
		// Iteration loop for estimate Latitude(ppi);
		for ($i = 0; $i<6; $i++){
			$N = $a / sqrt(1-($e*$e)*(sin($ppi)*sin($ppi))); // Prime vertical;
			$h = ($P/cos($ppi)) - $N; // Height;
			$ppi = atan2($Z,($P*(1-($e*$e)*($N/($N+$h))))); // Latitude
		}
		$lamda = (atan2($Y,$X))*180/M_PI;
		$ppi = $ppi*180/M_PI;
		$lonlat = [$lamda,$ppi];
		return $lonlat;
	}
}

?>