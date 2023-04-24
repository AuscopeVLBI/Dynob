<?php
//output msg
$expname = $_GET["exp"];
$curind = $_GET["curind"];

$scanlist = 'scans_'.$expname.'.txt';
$thescans = file($scanlist);
$thescans = array_filter($thescans);
$thescans = array_values($thescans);

$numscans = count($thescans);
$scannm = $thescans[$curind-1];

echo "fourfiting scan: ".$scannm." (".$curind." of ".$numscans.")";

//redirect
//if($currentline<$_GET["max"]){
	
    echo "
    <script>
    //Timeout force redirect (skip current scan)
    //var myVar = setInterval(myTimer, 240000); // 4*60 seconds
    //function myTimer() {
    //    window.location.href = './fringefit.php?exp=&curind=';
    //}

    //normal redirect
    setTimeout(function(){
        window.location.href = './fringefit.php?exp=".$expname."&curind=".$curind."';
    }, 500);
    </script>";
//}
?>