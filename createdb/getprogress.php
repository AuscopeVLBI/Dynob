<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
Class CDBMessage{
	/*
	function precorprogress(){
		$progress = $_SESSION['precor_progress'];
		echo $progress;
	}
	*/
	function cdbmsg(){
		$progress = $_SESSION['cdb_progress'];
		$msg = $_SESSION['cdb_message'];
		echo $progress.PHP_EOL.$msg;
	}

	function setprogress($txt){
		switch ($txt){
			case "checkdir":
				$_SESSION["cdb_progress"] = "Checking directory on analysis computer";
			break;
			case "cp2mac":
				$_SESSION["cdb_progress"] = "Preparing files...";
			break;
			case "data2mac":
				$_SESSION["cdb_progress"] = "Copying correlated data to analysis computer";
			break;
			case "predb":
				$_SESSION["cdb_progress"] = "Preparing to generate vgosDB (this can take a while)";
			break;
			case "createdb":
				$_SESSION["cdb_progress"] = "Creating vgosDB";
			break;
			case "dbcomplete":
				$_SESSION["cdb_progress"] = "vgosDB created";
				$_SESSION['cdb_message'] = "";
			break;
			//default:
			//	$_SESSION["precor_progress"] = "processing";
		}
	}
}

$dbmsg = new CDBMessage();
$dbmsg->setprogress($_GET["progress"]);
$dbmsg->cdbmsg();

?>