<?php
//this script writes to realtime.db from browser
$wrdbstr = $_REQUEST["wrdbstr"];
$wrdbmode = $_REQUEST["wrdbmode"];
if($wrdbmode==""){
	$wrdbmode="a+";
}

$wrdb = new WriteRtDb;
$wrdb->write($wrdbstr,$wrdbmode);

Class WriteRtDb{
	function write($str,$mode){
		$dbfile = fopen($_SERVER['DOCUMENT_ROOT']."web/fringe/realtime.db", $mode) or die("Unable to open realtime.db!");
		fwrite($dbfile, $str.PHP_EOL);
		fclose($dbfile);
	}
}

?>