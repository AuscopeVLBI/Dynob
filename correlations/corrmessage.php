<?php
//output msg
$currentJob = $_GET["scanpointer"]+1;
echo "Correlating scan: ".$_GET["cscan"]." (".$currentJob." of ".$_GET["max"].")";


//redirect
$nscanpointer = $_GET["scanpointer"]+1;
if($_GET["scanpointer"]<$_GET["max"]){
    echo "
    <script>
    //Timeout force redirect (skip current scan)
    //var myVar = setInterval(myTimer, 240000); // 4*60 seconds
    //function myTimer() {
    //    window.location.href = './correlatel.php?cscan=".$_GET["cscan"]."&max=".$_GET["max"]."&scanpointer=".$nscanpointer."&exp=".$_GET["exp"]."&skipped=true';
    //}

    //normal redirect
    setTimeout(function(){
        window.location.href = './correlatel.php?cscan=".$_GET["cscan"]."&max=".$_GET["max"]."&scanpointer=".$_GET["scanpointer"]."&exp=".$_GET["exp"]."';
    }, 500);
    </script>";
}
?>