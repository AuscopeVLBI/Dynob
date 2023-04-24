<?php
//this script is to display the output for the fringe check sessions

require_once "./fringegetout.php";

$session = $_GET["exp"];

$C = new Fringeoutput;
$frout = $C->fringeout($session);

$SNR=$frout[0];
$sefdtsys=$frout[1]; //st=>[[IF][SEFD,pol,lowfreq,sideband]],[unkown bbcs]
$sefdrees=$frout[2];
$sefdsched=$frout[3];
$source=$frout[4];
$stcodes = $GLOBALS["stcodes"];

unlink($_SERVER['DOCUMENT_ROOT']."web/fringe/realtime.db");


?>
<!DOCTYPE html>
<html>
<head>
<title>Fringe test output</title>
<style>
table, th, td {
  border: 1px solid;
}
</style>
</head>
<body>

<div>

	<fieldset>
		<legend>General:</legend>
		<p>
			Session: <?php echo $session;?>
		</p>
		<p>
			Source: <?php echo $source;?>
		</p>
		<br>
		<?php

			foreach($sefdtsys as $st => $_arr){
				echo "Station <b>".$st."</b>:<br>";
				echo "<table>".PHP_EOL."	<tr>".PHP_EOL."		<th>IF</th><th>frequency</th><th>sideband</th><th>polarisation</th><th>SEFD(tsys)</th>".PHP_EOL."	</tr>";
				
				foreach($_arr[0] as $_if=>$_detailval){
					//determine
					echo "	<tr>".PHP_EOL."		<td>".$_if."</td><td>".$_detailval[2]."</td><td>".$_detailval[3]."</td><td>".$_detailval[1]."</td><td>".ceil($_detailval[0])."</td>".PHP_EOL."</tr>";
				}

				echo "</table><br>";
				if(count($_arr[1])>0){
					echo "<span style='color:red'>Warning: </span>The following BBCs do not match that of Tsys, please check the .prc file if the correct BBCs are in used.: <br>";
					echo implode(", ",$_arr[1])."<br><br>";
				}
			}
			echo "<table>";
			echo "<tr><th></th><th>SEFD (scheduled)</th><th>SEFD (re-estimated)</th><th>Notes</th></tr>";
			foreach($sefdsched as $st => $_arr){
				foreach($_arr as $band => $val){

					$thenote = "<span style='color:green'>OK</span>";
					if($sefdrees[$st][$band] >= 10000 && $sefdrees[$st][$band]>$val){
						$thenote = "<span style='color:orange'>The antenna has low sensitivity, either the flux density for the selected source is much worse than predicted or something is wrong with the system.</span>";
					}
					if($sefdrees[$st][$band] >= 15000 && $sefdrees[$st][$band]>$val){
						$thenote = "<span style='color:red'>The re-estimated SEFD is not sensible, please check if the telescope is properly set up!</span>";
					}
					if($sefdrees[$st][$band] <= 2000 && $sefdrees[$st][$band]<$val){
						$thenote = "<span style='color:orange'>The re-estimated sensitivity appears too good to be true, either the flux density for the selected source is much better than predicted or there is some unusual issue.</span>";
					}
					if($sefdrees[$st][$band] <= 1000 && $sefdrees[$st][$band]<$val){
						$thenote = "<span style='color:red'>The re-estimated SEFD is not sensible, check if the SNR looks OK.</span>";
					}
					if(count($sefdsched)<3){
						$thenote = "<span style='color:green'>Need at least 3 stations to re-estimate the SEFD. Check if the SNRs below are sensible instead.</span>";
					}

					echo "<tr><td>".$st." (".strtoupper($band)."-band)</td><td>".ceil($val)."</td><td>".ceil($sefdrees[$st][$band])."</td><td>".$thenote."</td></tr>";
				}
			}
			echo "</table>"

		?>
	</fieldset>
	<br>
	<fieldset id="stpolsnr">
		<legend>SNR (advanced):</legend>
		<?php
		
			foreach($SNR as $st => $bandpol){
				$st1 =array_keys($stcodes,substr($st,0,1))[0];
				$st2 =array_keys($stcodes,substr($st,1,1))[0];
				foreach($bandpol as $band=>$pol){
					foreach($pol as $pl=>$val){
						echo $st1."-".$st2." ".strtoupper($band)."-band (pol ".$pl."): ".$val."<br>";
						echo "<div style='float:left;margin-top:15px;background-image: url(\"./fringeoutplot/".$st.$pl.strtoupper($band).".jpg\");background-repeat: no-repeat;background-position: -20px -220px;width:180px;height:88px;'></div>";
						echo "<div style='float:left;margin-left:20px;background-image: url(\"./fringeoutplot/".$st.$pl.strtoupper($band).".jpg\");background-repeat: no-repeat;background-position: -20px -323px;width:460px;height:88px;'></div>";
						echo "<div style='clear:both;'></div><br><br>";
					}
				}
			}
		?>
	</fieldset>

	<br>
	<button onclick="location.href='../fringe.php'" type="button">Start a new fringe check</button>
</body>
</html>
