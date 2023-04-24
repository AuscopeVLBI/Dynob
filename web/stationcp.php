<?php
define('IN_STATIONCP', TRUE);
define('HEAD_TITLE', "Station Control Panel");
define('CSS', "template/stationcp.css?".time());
define('CURSCRIPT', 'stationcp');

require_once "function/core.php";

$useLang = C::langSelect();
C::styleSelect();

require_once "language/$useLang/lang_common.php";
require_once "language/$useLang/lang_stationcp.php";

//header
require_once "./template/header.htm";

switch ($_GET["mod"]){
	case "register":
		if(!isset($_COOKIE['dynob_user'])){
			require_once "stationcp/register.php";
		}
		else{
			 header('location: stationcp.php');
		}
		
	break;
	default:
		require_once "stationcp/main.php";
}

//footer
require_once "./template/footer.htm";

?>