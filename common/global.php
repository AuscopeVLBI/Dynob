<?php
$corrdir = "/home/observer/correlations"; //correlation directory on primary correlation machine
$pcfsuser = "";
$skdlocation = "";
$loglocation = "";

//Mysql
$sqlserver = "localhost";
$sqluser = "";
$sqlpass = "";
$sqlpre = "Dynob_";

//Correlator infos
$cserver = "";
$cusername = "";
$cpassword = "";

//analysis data machine
$dbmachine = "";
$dbmacuser = "";
$dbmacpass = "";
$dbmacpath = "";

//analysis processing machine
$anamachine = "";
$anamacuser = "";
$anamacpass = "";
$anamaclogpath = "";
//$anamaclogpath = "/500/sessions/";

//local stations
$localstations = ["ho","hb","ke","yg"];

//sked flux.cat link
$skedfluxloc = 'https://raw.githubusercontent.com/nvi-inc/sked_catalogs/main/flux.cat';
$skedequiploc = 'https://raw.githubusercontent.com/nvi-inc/sked_catalogs/main/equip.cat';

//experiment(s)
$exper = "mftest"; //"mf0004,mf0005,mf0006..";
$cmode = "mixed"; //mixed or vgos

//datastreams
$vgosstations = ["hb","ke"];
$dtsvgos = array("hb"=>["a","b","c","d","e","f","g","h"],
		  "ke"=>["a","b","c","d","e","f","g","h"]);
$dtsmixeds = array("hb"=>["xx","xy","sx","sy"],
		   "ke"=>["xx","xy","sx","sy"]);

//peculiar offsets for dUT1
//$poffs = array("hb"=>-1.725,
//		"ke"=>-0.27,
//		"yg"=>-2.278,
//		"ww"=>-1.974,
//		"ht"=>-2.943);//,
$poffs = array("hb"=>-1.585,
		"ke"=>-1.85,
		"yg"=>-2.278,
		"ww"=>-1.974,
		"ht"=>-2.943,
		"ho"=>-1.0);//,
//		"yg"=>-2.15);

//stations code fourfit
$stcodes = ["Hb"=>"L","Ke"=>"i","Yg"=>"e","Ww"=>"W","Ht"=>"g","Ho"=>"H"];
$expcodepre = ["aum"=>"88","aua"=>"80"];

//antenna diameter
$antdias = ["Hb"=>12,"Ke"=>12,"Yg"=>12,"Ww"=>12,"Ht"=>15,"Ho"=>26];

//equip alias
$equipalias = ["Ho"=>"04"];

//manual phase cal
$manualpcalsources = ["1921-293","0454-234","0727-115","1057-797","0530-727"]; //order by priority
$refStationArr = ["e","W","g","L","i"]; //order by priority
$minSNR = 8; //0 is off/do not check minimum SNR (faster)

//define good sources
$goodsources = ["0454-234","0727-115","1334-127","OJ287","2255-282"];
//$goodsources = ["0454-234"];

//real time fringe check sources
$rtfcsources = ["0454-234","0727-115","1334-127","OJ287","2255-282","1921-293","1057-797"];
?>
