<?php
//this script prepares the reference file for recording and correlations

require_once "../common/SSHcon.php";
require_once "../common/global.php";
require_once "../common/dbwrite.php";

//check if lock is active
$realtimedb = [];
$dbfile = new SplFileObject("../web/fringe/realtime.db");
while (!$dbfile->eof()) {
	$line = $dbfile->fgets();
	if(strlen($line)>0){
		$lineexp = explode('=',$line);
		$realtimedb[trim($lineexp[0])]=trim($lineexp[1]);
	}
}
if($realtimedb["fringe_stage"]=="lockactive"){
	die("fringe check is in progress");
}

//make schedule dir, remove existing
if ($_POST["startTime"]){
	$tmpdate = date_create($_POST["startTime"]);
	$dirname = "fringeout/".date_format($tmpdate,"YzHis");
	//$dirname = "fringeout/".implode('_',explode(" ",$_POST["startTime"]));
	if (!file_exists($dirname)) {
		mkdir($dirname, 0777, true);
		chmod($dirname, 0777);
	} else {
		rrmdir($dirname);
		mkdir($dirname, 0777, true);
		chmod($dirname, 0777);
	}
}

function rrmdir($src) {
    $dir = opendir($src);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $full = $src . '/' . $file;
            if ( is_dir($full) ) {
                rrmdir($full);
            }
            else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
}

//check fringe name from SQL
$db = new DbWrite;
$dbq = $db->query("mfringe","SELECT session FROM SEFDmon ORDER BY id DESC LIMIT 1");
if($dbq=="null"){
	//no fringe record, use mf0001
	$expname = "mf0001";
}
else{
	$inum = trim(substr($dbq[0]["session"],2)) + 1;
	$expname = "mf".str_pad($inum,4,"0",STR_PAD_LEFT);
}

//write prefile
$prefile = fopen($dirname."/expinfo.txt", "w") or die("Unable to open file!");
$date = date_create($_POST["startTime"]);
$starttime = date_format($date,"Y.m.d H:i:s");
$totaldur = $_POST["duration"] + 25;
$date->add(new DateInterval('PT'.$totaldur.'S')); // adds secs
$endtime = date_format($date,"Y.m.d H:i:s");
$stations = [];
$recorder = [];
if($_POST["hb12"]){
	array_push($stations,"hb");
	array_push($recorder,$_POST["recorderhb"]);
}
if($_POST["ke12"]){
	array_push($stations,"ke");
	array_push($recorder,$_POST["recorderke"]);
}
if($_POST["yg12"]){
	array_push($stations,"yg");
	array_push($recorder,$_POST["recorderyg"]);
}
if($_POST["ho26"]){
	array_push($stations,"ho");
	array_push($recorder,$_POST["recorderho"]);
}

$txt = "Start time: ".$starttime.PHP_EOL.
	"End time: ".$endtime.PHP_EOL.
	"Stations: ".implode(",",$stations).PHP_EOL.
	"Recorders: ".implode(",",$recorder).PHP_EOL.
	"Min SNR: ".$_POST["minsnr"].PHP_EOL.
	"Exp name: ".$expname.PHP_EOL.
	"Mode: ".$_POST["emode"];
fwrite($prefile, $txt);
fclose($prefile);

//auto update catalogs/flux.cat to latest
unlink("../catalogs/flux.cat");
file_put_contents("../catalogs/flux.cat",file_get_contents($GLOBALS["skedfluxloc"]));

//write xml
$starr = [];
foreach($stations as $st){
	if($st=="hb"){
		array_push($starr,"			<station>HOBART12</station>");
	}
	if($st=="ho"){
		array_push($starr,"			<station>HOBART26</station>");
	}
	elseif($st=="ke"){
		array_push($starr,"			<station>KATH12M</station>");
	}
	elseif($st=="yg"){
		array_push($starr,"			<station>YARRA12M</station>");
	}
}

$onlysource = "";
if (trim($_POST["useSource"])!==""){
	$onlysource = '
		<onlyUseListedSources>
			<source>'.trim($_POST["useSource"]).'</source>
		</onlyUseListedSources>';
}

$xmltxt = '<?xml version="1.0" encoding="utf-8"?>
<VieSchedpp>
	<software>
		<name>VieSched++</name>
	</software>
	<created>
		<time>'.date("Y.m.d H:i:s").'</time>
		<name>unknown</name>
		<email>unknown</email>
	</created>
	<general>
		<experimentName>'.$expname.'</experimentName>
		<startTime>'.$starttime.'</startTime>
		<endTime>'.$endtime.'</endTime>
		<subnetting>false</subnetting>
		<idleToObservingTime>true</idleToObservingTime>
		<stations>
'.implode(PHP_EOL,$starr).'
		</stations>'.$onlysource.'
		<scanAlignment>start</scanAlignment>
		<logSeverityConsole>info</logSeverityConsole>
		<logSeverityFile>info</logSeverityFile>
	</general>
	<output>
		<experimentDescription>Dynamic fringe checking</experimentDescription>
		<scheduler>unknown</scheduler>
		<correlator>unknown</correlator>
		<initializer_log>true</initializer_log>
		<iteration_log>false</iteration_log>
		<createSummary>false</createSummary>
		<createNGS>true</createNGS>
		<createSKD>true</createSKD>
		<createVEX>true</createVEX>
		<createSnrTable>true</createSnrTable>
		<createOperationsNotes>true</createOperationsNotes>
		<createSourceGroupStatistics>false</createSourceGroupStatistics>
		<createSkyCoverage>false</createSkyCoverage>
	</output>
	<catalogs>
		<antenna>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/antenna.cat</antenna>
		<equip>'.$_SERVER["DOCUMENT_ROOT"].'scheduling/dynflux/dynequip.cat</equip>
		<flux>'.$_SERVER["DOCUMENT_ROOT"].'scheduling/dynflux/dynflux.cat</flux>
		<freq>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/freq.cat</freq>
		<hdpos>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/hdpos.cat</hdpos>
		<loif>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/loif.cat</loif>
		<mask>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/mask.cat</mask>
		<modes>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/modes.cat</modes>
		<position>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/position.cat</position>
		<rec>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/rec.cat</rec>
		<rx>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/rx.cat</rx>
		<source>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/source.cat.geodetic.good</source>
		<tracks>'.$_SERVER["DOCUMENT_ROOT"].'catalogs/tracks.cat</tracks>
	</catalogs>
	<station>
		<setup>
			<member>__all__</member>
			<parameter>default</parameter>
			<transition>hard</transition>
		</setup>
		<parameters>
			<parameter name="default">
				<available> 1</available>
				<availableForFillinmode> 1</availableForFillinmode>
				<weight> 1</weight>
				<minScan> '.$_POST["duration"].'</minScan>
				<maxScan> '.$_POST["duration"].'</maxScan>
				<minSlewtime> 0</minSlewtime>
				<maxSlewtime> 600</maxSlewtime>
				<maxSlewDistance> 175</maxSlewDistance>
				<minSlewDistance> 0</minSlewDistance>
				<maxWait> 600</maxWait>
				<minElevation> 15</minElevation>
				<maxNumberOfScans> 9999</maxNumberOfScans>
				<maxTotalObsTime> 999999</maxTotalObsTime>
				<preob> 10</preob>
				<midob> 3</midob>
				<systemDelay> 6</systemDelay>
			</parameter>
			<parameter name="down">
				<available> 0</available>
			</parameter>
		</parameters>
		<cableWrapBuffers>
			<cableWrapBuffer member="__all__">
				<axis1LowOffset>5</axis1LowOffset>
				<axis1UpOffset>5</axis1UpOffset>
				<axis2LowOffset>0</axis2LowOffset>
				<axis2UpOffset>0</axis2UpOffset>
			</cableWrapBuffer>
		</cableWrapBuffers>
	</station>
	<source>
		<setup>
			<member>__all__</member>
			<parameter>default</parameter>
			<transition>hard</transition>
		</setup>
		<parameters>
			<parameter name="default">
				<available> 1</available>
				<availableForFillinmode> 1</availableForFillinmode>
				<weight> 1</weight>
				<minElevation> 0</minElevation>
				<minSunDistance> 4</minSunDistance>
				<minScan> '.$_POST["duration"].'</minScan>
				<maxScan> '.$_POST["duration"].'</maxScan>
				<minNumberOfStations> '.count($stations).'</minNumberOfStations>
				<minRepeat> 30</minRepeat>
				<minFlux> 0.01</minFlux>
				<maxNumberOfScans> 999</maxNumberOfScans>
			</parameter>
		</parameters>
	</source>
	<baseline>
		<setup>
			<member>__all__</member>
			<parameter>default</parameter>
			<transition>hard</transition>
		</setup>
		<parameters>
			<parameter name="default">
				<ignore> 0</ignore>
				<minScan> 0</minScan>
				<maxScan> 9999</maxScan>
				<weight> 1</weight>
			</parameter>
		</parameters>
	</baseline>
	<skyCoverage>
		<influenceDistance>30</influenceDistance>
		<influenceInterval>3600</influenceInterval>
		<maxTwinTelecopeDistance>0</maxTwinTelecopeDistance>
		<interpolationDistance>cosine</interpolationDistance>
		<interpolationTime>cosine</interpolationTime>
	</skyCoverage>
	<weightFactor>
		<skyCoverage>1</skyCoverage>
		<numberOfObservations>1</numberOfObservations>
		<duration>1</duration>
		<idleTime>1</idleTime>
		<idleTimeInterval>300</idleTimeInterval>
	</weightFactor>
	<mode>
		<skdMode>1024-16(AUMOD)</skdMode>
		<bandPolicies>
			<bandPolicy name="X">
				<minSNR>'.$_POST["minsnr"].'</minSNR>
				<station>
					<tag>required</tag>
					<backup_value>0</backup_value>
				</station>
				<source>
					<tag>required</tag>
					<backup_internalModel/>
				</source>
			</bandPolicy>
			<bandPolicy name="S">
				<minSNR>'.$_POST["minsnr"].'</minSNR>
				<station>
					<tag>required</tag>
					<backup_value>0</backup_value>
				</station>
				<source>
					<tag>required</tag>
					<backup_internalModel/>
				</source>
			</bandPolicy>
		</bandPolicies>
	</mode>
</VieSchedpp>';

$xmlfile = fopen($dirname."/".$expname.".xml", "w") or die("Unable to open file!");
fwrite($xmlfile, $xmltxt);
fclose($xmlfile);

//create schedule
exec('VieSchedpp/bin/VieSchedpp '.$dirname.'/'.$expname.'.xml');


//check if skd file is generated
if (!file_exists($dirname."/".$expname.".skd")) {
	//rrmdir($dirname);
	header("Location: ../web/fringe.php?error=1");
	exit;
}
else{
	ob_start();
	//send to pcfs
	$conssh = new ConnectSSH;
	foreach($stations as $st){
		if($st=="ho"){
			if(!($conpcfs = $conssh->connect("hobart",$GLOBALS["pcfsuser"],$cpassword))){
				die("Unable to reach hobart");
			}
		}
		else{
			if(!($conpcfs = $conssh->connect("pcfs".$st,$GLOBALS["pcfsuser"],$cpassword))){
				die("Unable to reach pcfs".$st);
			}
		}
		ssh2_scp_send($conpcfs,$dirname."/".$expname.".skd", "/usr2/sched/".$expname.".skd", 0755);
		ssh2_scp_send($conpcfs,$dirname."/".$expname.".vex", "/usr2/sched/".$expname.".vex", 0755);

		//drudg
		echo $conssh->exec($conpcfs,'rm /usr2/sched/'.$expname.$st.'.snp');
		if ($st=="yg"){
			if ($_POST["recorderyg"]=="flexbuffyg"){
				echo $conssh->exec($conpcfs,'cd /usr2/sched/;echo -e "'.ucfirst($st).'\n11\n1 16 1 1\n3\n5\n0" | drudg '.$expname.'.skd');
				//echo $conssh->exec($conpcfs,'cp /usr2/proc/mf0070'.$st.'.prc /usr2/proc/'.$expname.$st.'.prc');
			}
			else{
				echo $conssh->exec($conpcfs,'cd /usr2/sched/;echo -e "'.ucfirst($st).'\n3\n5\n0" | drudg '.$expname.'.skd');
				//echo $conssh->exec($conpcfs,'mv /usr2/sched/'.$expname.$st.'.prc /usr2/proc/');
			}
		}
		elseif ($st=="ho"){
			if ($_POST["recorderho"]=="flexbuffhb"){
				echo $conssh->exec($conpcfs,'cd /usr2/sched/;echo -e "'.ucfirst($st).'\n11\n1 16 1 1\n3\n5\n0" | drudg '.$expname.'.skd');
				//echo $conssh->exec($conpcfs,'cp /usr2/proc/si.prc /usr2/proc/'.$expname.$st.'.prc');
			}
			else{
				echo $conssh->exec($conpcfs,'cd /usr2/sched/;echo -e "'.ucfirst($st).'\n3\n5\n0" | drudg '.$expname.'.skd');
				//echo $conssh->exec($conpcfs,'mv /usr2/sched/'.$expname.$st.'.prc /usr2/proc/');
			}
		}
		elseif ($st=="ke"){
			echo $conssh->exec($conpcfs,'cd /usr2/sched/;echo -e "'.ucfirst($st).'\n11\n1 16 1 1\n3\n5\n0" | /usr2/fs-9.13.0/bin/drudg '.$expname.'.skd');
			//echo $conssh->exec($conpcfs,'cp /usr2/proc/aum044'.$st.'.prc /usr2/proc/'.$expname.$st.'.prc');
		}
		elseif ($st=="hb"){
			echo $conssh->exec($conpcfs,'cd /usr2/sched/;echo -e "'.ucfirst($st).'\n11\n1 16 1 1\n3\n5\n0" | drudg '.$expname.'.skd');
			//echo $conssh->exec($conpcfs,'cp /usr2/proc/aum044'.$st.'.prc /usr2/proc/'.$expname.$st.'.prc');
		}

		//copy the current loaded prc file to new mf
		$prcnow = trim($conssh->exec($conpcfs,"echo 'exit' | pfmed  | grep 'active schedule procedure file' | cut -d ':' -f 2"));
		echo $conssh->exec($conpcfs,'cp /usr2/proc/'.$prcnow.'.prc /usr2/proc/'.$expname.$st.'.prc');

		ssh2_disconnect($conpcfs);
	}

	ob_end_clean();
	
	$dbfile = fopen("../web/fringe/realtime.db", "a+") or die("Unable to open file!");
	fwrite($dbfile, "fringe_preset=1".PHP_EOL);
	fwrite($dbfile, "fringe_stage=start".PHP_EOL);
	fwrite($dbfile, "fringe_scheddir=".date_format($tmpdate,"YzHis").PHP_EOL);
	fclose($dbfile);

	header("Refresh:0.2; url=../web/fringe.php");
	exit;
	
}
?>