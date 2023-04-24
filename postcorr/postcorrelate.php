<?php

require_once "../common/SSHcon.php";
require_once "../common/global.php";
require_once "../common/dbwrite.php";

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

Class Postcorrelate{

    //run setup if not fringe check
    function setup(){
        
		$_SESSION["precor_message"] = "";

		//save to sessions
		if ($_GET["exper"]){ //if not input, assume session isset from previous
			$_SESSION["exper"] = $_GET["exper"];
		}
		$_pgexp = $_SESSION["exper"];
		if(!isset($_SESSION["exper"])){
			$message = "Exit: no experiment specified".PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}

		if(count(explode(",",$_pgexp))>1){
			$dynexp = explode(",",$_pgexp);
			$exper = "dyn".date("YmdHi");
		}
		else{
			$exper = $_pgexp;
			$dynexp = [$exper]; //for other experiments to work
		}
		//dynexp is the array of multiple short sessions
		//$exper is the common name for all combined short sessions
		$_SESSION["exper"] = $exper;
		$_SESSION["dynexp"] = $dynexp;

        //cmode
		if ($_GET["cmode"]){
			$_SESSION["cmode"] = $_GET["cmode"];
			$cmode = $_SESSION["cmode"];
		}
		elseif(isset($_SESSION["cmode"])){
			$cmode = $_SESSION["cmode"];
		}
		else{
			$cmode = $GLOBALS["cmode"];
		}
		$_SESSION["cmode"] = $cmode;

		//setup correlation directory
		switch(substr($exper,0,2)){
			case "mf":
				$corrdir = $GLOBALS["corrdir"]."/mf/";
				$explabel = "mf";
			break;
			case "dy":
				$corrdir = $GLOBALS["corrdir"]."/dyn/";
				$explabel = "dyn";
			break;
			default:
				$corrdir = $GLOBALS["corrdir"]."/au/";
				$explabel = "default";
			break;
		}
		$_SESSION["corrdir"] = $corrdir;
		$_SESSION["explabel"] = $explabel;

		//find expcode
		$db = new DbWrite;
		if($explabel!=="mf"){
			$dbq = $db->query("dynob",'SELECT expcode FROM Correlation WHERE session="'.$this->exper.'"');
			if($dbq=="null"){
				$expcode = "1234";
			}
			else{
				$expcode = $dbq[0]["expcode"];
			}
		}
		else{
			$expcode = "1234";
		}
		$_SESSION["expcode"] = $expcode;
	}

    function postmpi(){

        $cserver = $GLOBALS["cserver"];
		$cusername = $GLOBALS["cusername"];
		$cpassword = $GLOBALS["cpassword"];
		$cmode = $_SESSION["cmode"];
		$exper = $_SESSION["exper"];
		$dynexp = $_SESSION["dynexp"];
		$explabel = $_SESSION["explabel"];
		$corrdir = $_SESSION["corrdir"];
		$expcode = $_SESSION["expcode"];

        $conssh = new ConnectSSH;
		if(!($con = $conssh->connect($cserver,$cusername,$cpassword))){
			$message = "Exit: Unable to reach ".$cserver.PHP_EOL;
			$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
			die();
		}
        $list = $conssh->exec($con,"ls ".$corrdir.$exper."/*input | cut -d '.' -f 1");
		$listarr = preg_split('/[\s]+/', $list);
		sort($listarr);
		$listarr = array_filter($listarr);
		$listarr = array_values($listarr);

        //may take a while
        ob_start();
		echo $conssh->exec($con,"cd ".$corrdir.$exper.";for i in {".substr($listarr[0],stripos($listarr[0],"_")+1)."..".substr($listarr[count($listarr)-1],stripos($listarr[count($listarr)-1],"_")+1)."}; do difx2mark4 -w 16 -e ".$expcode." ".$exper.'_${i};done;');
		ob_end_clean();

		ssh2_disconnect($con);
    }

	function initff(){
		$exper = $_SESSION["exper"];
		require_once "../common/dbwrite.php";

		//
	}
    
}


$postcorr = new Postcorrelate();
print_r($_SESSION);
if(!isset($_SESSION["exper"])){
    $postcorr->setup();
}
if ($_GET["do"]=="postmpi"){
	$postcorr->postmpi();
}
if ($_GET["do"]=="initff"){
	$postcorr->initff();
}
if ($_GET["do"]=="fringecheck"){
	$postcorr->fringecheck();
}


?>