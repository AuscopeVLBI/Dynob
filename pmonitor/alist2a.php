<?php
require_once "../common/SSHcon.php";
require_once "../common/global.php";

//$al = new Alist2a;
//$al->createAlist("/home/observer/correlations/mf/mf0004/","1234","mf0004");
//$al->tranxa("/home/observer/correlations/mf/mf0004/","1234","mf0004");

//new version uses effective duration

Class Alist2a{

	function createAlist($corrdir,$exp4lcode,$expname){
		$conssh = new ConnectSSH;
		if(!$con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"])){
			die("Unable to reach ".$GLOBALS["cserver"]);
		}
		/*
		$scans = array_filter(explode(PHP_EOL,$conssh->exec($con,"cd ".$corrdir.$exp4lcode.";ls;")));
		$scdiv = count($scans)/500;
		for ($i=0;$i<ceil($scdiv);$i++){
			$combscans = implode(" ",array_slice($scans, $i*500, ($i*500)+500));
			$_ali = $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";echo 'y' | alist ".$combscans." &>/dev/null;");
			if($i>0){
				$_aliout = $conssh->exec($con,"cd ".$corrdir.$exp4lcode."; sed -e '1,3d' < alist.out >> alistdyn.out;");
			}
			else{
				$_aliout = $conssh->exec($con,"cd ".$corrdir.$exp4lcode."; cat alist.out >> alistdyn.out;");
			}
		}*/
		echo $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";alist * &>/dev/null;");
		echo $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";sed -i 's/ 16383 / 1234 /g' alist.out;grep -v  '0.000000 0.000  -1  -1' alist.out > alist.ed.out");
		$xvalsx = $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";cat alist.ed.out | grep ' X0'");
		$svalsx = $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";cat alist.ed.out | grep ' S0'");
		if(trim($xvalsx) == "" && trim($svalsx) == ""){
			echo $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";cat alist.ed.out | grep ' X3' | tr -s ' ' |  awk ' {if ($15!=\"LL\" && $15!=\"ii\" && $15!=\"ee\" && $15!=\"WW\") {if ($18!=\"LL\" && $18!=\"RR\" && $18!=\"LR\" && $18!=\"RL\") {print $11\" \"$9\" \"$14\"  \"$6\"  \"$17\" \"$15\"  CM  \"$21\"  \"$29\"  \"$30\" 2\"}else{print $11\" \"$9\" \"$14\"  \"$6\"  \"$17\" \"$15\"  \"$18\"  \"$21\"  \"$29\"  \"$30\" 2\"}}} BEGIN {print \"#time source dur chan baseline POL SNR Elev1 Elev2 bit\"}'  > ".$expname.".a.x");
		}
		else{
			echo $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";cat alist.ed.out | grep ' X0' | tr -s ' ' |  awk ' {if ($15!=\"LL\" && $15!=\"ii\" && $15!=\"ee\" && $15!=\"WW\") {if ($18!=\"LL\" && $18!=\"RR\" && $18!=\"LR\" && $18!=\"RL\") {print $11\" \"$9\" \"$14\"  \"$6\"  \"$17\" \"$15\"  CM  \"$21\"  \"$29\"  \"$30\" 2\"}else{print $11\" \"$9\" \"$14\"  \"$6\"  \"$17\" \"$15\"  \"$18\"  \"$21\"  \"$29\"  \"$30\" 2\"}}} BEGIN {print \"#time source dur chan baseline POL SNR Elev1 Elev2 bit\"}'  > ".$expname.".a.x");
			echo $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";cat alist.ed.out | grep ' S0' | tr -s ' ' |  awk ' {if ($15!=\"LL\" && $15!=\"ii\" && $15!=\"ee\" && $15!=\"WW\") {if ($18!=\"LL\" && $18!=\"RR\" && $18!=\"LR\" && $18!=\"RL\") {print $11\" \"$9\" \"$14\"  \"$6\"  \"$17\" \"$15\"  CM  \"$21\"  \"$29\"  \"$30\" 2\"}else{print $11\" \"$9\" \"$14\"  \"$6\"  \"$17\" \"$15\"  \"$18\"  \"$21\"  \"$29\"  \"$30\" 2\"}}} BEGIN {print \"#time source dur chan baseline POL SNR Elev1 Elev2 bit\"}'  > ".$expname.".a.s");	
		}
	}

	function tranxa($corrdir,$exp4lcode,$expname){
		$conssh = new ConnectSSH;
		if(!$con = $conssh->connect($GLOBALS["cserver"],$GLOBALS["cusername"],$GLOBALS["cpassword"])){
			die("Unable to reach ".$GLOBALS["cserver"]);
		}
		ssh2_scp_recv($con, $corrdir.$exp4lcode."/".$expname.".a.x", $_SERVER['DOCUMENT_ROOT'].'pmonitor/afile/'.$expname.".a.x");
		ssh2_scp_recv($con, $corrdir.$exp4lcode."/".$expname.".a.s", $_SERVER['DOCUMENT_ROOT'].'pmonitor/afile/'.$expname.".a.s");

		echo $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";rm $expname.a.*;");

		//download alist, then remove
		ssh2_scp_recv($con, $corrdir.$exp4lcode."/alist.out", $_SERVER['DOCUMENT_ROOT'].'tmp/'.$expname."/alist.out");
		echo $conssh->exec($con,"cd ".$corrdir.$exp4lcode.";rm alist*;");
	}

}
?>
