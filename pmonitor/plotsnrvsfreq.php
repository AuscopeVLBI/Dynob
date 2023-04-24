<?php
//this script reads from fringe file and plots flux vs freq

require_once "../common/SSHcon.php";
require_once "../common/global.php";

class PlotFluxvsChan {

    public $cserver;
	public $cusername;
	public $cpassword;

    function readfringefile($exper){

        $this->cserver = $GLOBALS["cserver"];
		$this->cusername = $GLOBALS["cusername"];
		$this->cpassword = $GLOBALS["cpassword"];

        $conssh = new ConnectSSH;
		if(!($con = $conssh->connect($this->cserver,$this->cusername,$this->cpassword))){
			//$message = "Exit: Unable to reach ".$this->cserver.PHP_EOL;
			//$_SESSION["precor_message"] = $_SESSION["precor_message"].$message;
            echo "Unable to reach ".$this->cserver.PHP_EOL;
			die();
		}

        //detect the channels (read from cf_exp)
        


        $_str = $conssh->exec($con,"cd ".$this->corrdir.";ls");
		if (!stripos($_str,$this->exper)){
			$sftp = ssh2_sftp($con);
			ssh2_sftp_mkdir($sftp, $this->corrdir.$this->exper);
			//ssh2_sftp_chmod($sftp, $corrdir.$exper, 0777);
		}
		$corrdir = $this->corrdir.$this->exper."/";
		$_SESSION["corrdir"] = $corrdir;
		ssh2_disconnect($con);


    }

    function plotfluxchan(){

    }
}



?>