<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
Class CorMessage{
	/*
	function precorprogress(){
		$progress = $_SESSION['precor_progress'];
		echo $progress;
	}
	*/
	function precormsg(){
		$progress = $_SESSION['precor_progress'];
		$msg = $_SESSION['precor_message'];
		echo $progress.PHP_EOL.$msg;
	}

	function postcormsg(){
		$progress = $_SESSION['postcor_progress'];
		$msg = $_SESSION['postcor_progress'];
		echo $progress.PHP_EOL.$msg;
	}

	function setprogress($txt){
		switch ($txt){
			case "init":
				$_SESSION["precor_progress"] = "Initiating correlation";
				$_SESSION['precor_message'] = "";
			break;
			case "checkdir":
				$_SESSION["precor_progress"] = "Checking correlation directory on correlator";
			break;
			case "getdataloc":
				$_SESSION["precor_progress"] = "Getting data Location";
			break;
			case "copyvex":
				$_SESSION["precor_progress"] = "Copying threads and vex files to correlator";
			break;
			case "getlog":
				$_SESSION["precor_progress"] = "Downloading log files";
			break;
			case "getv2d":
				$_SESSION["precor_progress"] = "Writing v2d and corrparam";
			break;
			case "genfilelist":
				$_SESSION["precor_progress"] = "Generating filelist (this may take a while)";
			break;
			case "dxcalc":
				$_SESSION["precor_progress"] = "Preparing for correlation";
			break;
			case "dxmpi":
				$_SESSION["precor_progress"] = "Correlating...";
			break;
			case "corrcomplete":
				$_SESSION["precor_progress"] = "Correlation completed";
				$_SESSION['precor_message'] = "";
			break;
			//post corr
			case "postmpi":
				$_SESSION["postcor_progress"] = "Running difx2mark4";
			break;
			case "initff":
				$_SESSION["postcor_progress"] = "Creating control file";
			break;
			case "fringecheck":
				$_SESSION["postcor_progress"] = "Fringe fitting";
			break;
			case "done":
				$_SESSION["postcor_progress"] = "All done!";
			break;
			//default:
			//	$_SESSION["precor_progress"] = "processing";
		}
	}
}

$cormsg = new CorMessage();
$cormsg->setprogress($_GET["progress"]);
$cormsg->precormsg();

/*
switch ($_GET["show"]){
	case "progress":
		$cormsg->precorprogress();
	break;
	case "message":
		$cormsg->precormsg();
	break;
}*/

?>