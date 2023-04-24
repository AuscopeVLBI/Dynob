<?php
if(!isset($_COOKIE["dynob_user"])) {
	if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
		$protocol = "https://";
	}
	else {
		$protocol = "http://";
	}
	$CurPageURL = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	header("Location: ".$protocol.$_SERVER['HTTP_HOST']."/web/login.php?ref=".base64_encode($CurPageURL));
	die();
}

if (!file_exists("fringe/realtime.db")){
	$newdbfile = fopen("fringe/realtime.db", "w");
	fclose($newdbfile);
	chmod("fringe/realtime.db",0777);
}

$realtimedb = [];
$dbfile = new SplFileObject("fringe/realtime.db");
while (!$dbfile->eof()) {
	$line = $dbfile->fgets();
	if(strlen($line)>0){
		$lineexp = explode('=',$line);
		$realtimedb[trim($lineexp[0])]=trim($lineexp[1]);
	}
}
?>
<!Doctype html>
<head>
<link rel="stylesheet" href="fringe/fringe.css">
<script src="js/jquery-3.6.0.min.js"></script>
<script>

function makeRequest(toPHP, callback) {
	var xmlhttp;
	if (window.XMLHttpRequest){// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp=new XMLHttpRequest();
	}
	else{// code for IE6, IE5
		xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange=function(){
		if (xmlhttp.readyState==4 && xmlhttp.status==200){
			callback(xmlhttp.response);
		}
	}
	xmlhttp.open("GET",toPHP,true);
	xmlhttp.send();
}

$(document).ready(function(){
	//default home
	$( ".recSelect" ).change(function() {
		var $input = $( this );
		if($input.is( ":checked" )){
			//$("#yg12recorder").show();
			$input.nextAll( ".recorder" ).first().show();
		}
		else{
			//$("#hb12recorder").hide();
			$input.nextAll( ".recorder" ).first().hide();
		}

		//refresh visible
		findvis();
	});

	//obstrace
	if($('.pgcontent').attr('id')=="start"){
		//lock active
		makeRequest("fringe/writerealtimedb.php?wrdbstr=fringe_stage=lockactive", function(response) {
			makeRequest("../observing/fringestart.php?dd=<?php echo $realtimedb["fringe_scheddir"]; ?>", function(response) {
				$( "#obstext" ).append(response);
			});
		});
		logintval();
	}

	if($('.pgcontent').attr('id')=="lockactive"){
		$( "#notice" ).html("Someone else is fringe checking!");
		$( "#obstext" ).append("\nClick the button to reset and start the fringe test if you are VERY sure no one else is fringe checking.");
		$( "#btnstartover" ).show();
		$( "#btnstartover" ).click(toStartover);
		//logintval();
	}
	
	function logintval() {
		makeRequest("../observing/getlog.php?dd=<?php echo $realtimedb["fringe_scheddir"]; ?>", function(response) {
			$( "#obstext" ).html(response);
			if (response.toLowerCase().indexOf("all stations finished observing") < 0){
				setTimeout(function(){
					logintval();
				}, 60000); //check status every minute
			}
			else{
				if($('.pgcontent').attr('id')!="lockactive"){
					//schedule ended, process with data transfer and stow
					//makeRequest("fringe/writerealtimedb.php?wrdbstr=fringe_stage=preptransfer", function(response2) { //enable if using session scheddir
						//start preptransfer
						makeRequest("../observing/fringepostobs.php?dd=<?php echo $realtimedb["fringe_scheddir"]; ?>&do=prep", function(response3) {
							$( "#obstext" ).append(response3);
							//tranxstat();
							$( "#obstext" ).append("\nClick the 'Continue' button once data transfer is completed");
							$( "#btncontinue" ).show();
							$( "#btncontinue" ).click(toCorrelate);
							$( "#btnstartover" ).show();
							$( "#btnstartover" ).click(toStartover);
						});
					//});
				}
			}
		});
	}
	//hash
	/*
	if(window.location.hash) {
		var hash = window.location.hash.substring(1); //Puts hash in variable, and removes the # character
		$( "#pgcontent" ).html("response");
	}
	*/

	//find visible function
	function findvis(){
		//time
		var _dateti = $( "#startTime" ).val();
		if(_dateti.length === 0){
			var d = new Date();
			var dmonth = d.getUTCMonth() + 1;
			var dday = d.getUTCDate();
			var dyear = d.getUTCFullYear();
			var dhour = d.getUTCHours();
			var dmin = d.getUTCMinutes();
			var dsec = d.getUTCSeconds();
		}
		else{
			var d = new Date(_dateti);
			var dmonth = d.getMonth() + 1;
			var dday = d.getDate();
			var dyear = d.getFullYear();
			var dhour = d.getHours();
			var dmin = d.getMinutes();
			var dsec = d.getSeconds();
		}
		var dateti = dmonth+","+dday+","+dyear+","+dhour+","+dmin+","+dsec;
		//alert(dateti);

		//checked stations
		var sts = [];
		$('.recSelect:checked').each(function() {
			sts.push($(this).attr('name'));
		});

		makeRequest("fringe/findvisible.php?dt="+dateti+"&sts="+sts, function(response) {
			//alert(response);
			$("#vistable").html(response);
			$(".tdsourcename").click(function(){
				var selectedsource = $( this ).text();
				$("input[name=useSource]").val(selectedsource);
			});
		});

		
	}



});

function toStartover(){
	$( "#btnstartover" ).hide();
	//remove the realtimedb
	makeRequest("./fringe/removerealtimedb.php", function(response) {
		//header("Location: fringe.php");
		//header("Refresh:0.1; url=fringe.php");
		window.location.href = window.location.origin + window.location.pathname;
	});
}

function toCorrelate(){
	$( "#btncontinue" ).hide();
	$( "#btnstartover" ).hide();
	var lines = $('#obstext').val().split('\n');
	for(var i = 0;i < lines.length;i++){
		if(lines[i].indexOf("Experiment: ") >= 0){
			var session = lines[i].substring(12).trim();
		}
	}

	var precorr = ["init","checkdir","getdataloc","copyvex","getlog","getv2d","genfilelist","dxcalc","dxmpi","postmpi","corrcomplete"];
	var ind = 0;
	precorrprogress(precorr[ind]);

	function precorrprogress(value) {
		makeRequest("../correlations/getprogress.php?progress="+value, function(response) {
			$( "#obstext" ).html(response);
			if(value!="next"){
				//only dxmpi is different because it checks status from vc0
				if(value=="dxmpi"){
					if (response.indexOf("Correlation completed") < 0){
						makeRequest("../correlations/precorrelate2.php?exper="+session+"&do="+value, function(response2) {
							setTimeout(function(){ 
								precorrprogress(precorr[ind]);
							},15000); //every 15 seconds
						});
					}
					else{
						var str = $("#obstext").val();
						if (str.indexOf("Exit:") < 0){
							if (ind<precorr.length){
								ind = ind + 1;
								precorrprogress(precorr[ind]);
							}
						}
						else{
							precorrprogress("next");
						}
					}
				}
				//correlation done
				else if(value=="corrcomplete"){ //just in case
					postCorrelate(session);
				}
				else{
					precorrexec(value);
				}
			}
		});
	}

	function precorrexec(value) {
		makeRequest("../correlations/precorrelate2.php?exper="+session+"&do="+value, function(response) {
			setTimeout(function(){ 
				var str = $("#obstext").val();
				if (str.indexOf("Exit:") < 0){
					if (ind<precorr.length){
						ind = ind + 1;
						precorrprogress(precorr[ind]);
					}
					else{
						precorrprogress("next");
					}
				}
			},1000);
		});
	}
}

function postCorrelate(session){
	var postcorr = ["initff","fringecheck","monitor","done"];
	var completemsg = ["done","done","monitored","done"];
	var ind = 0;
	postcorrprogress(postcorr[ind]);

	function postcorrprogress(value) {
		if(value=="initff"){
			$("#tracearea").html("<p>Creating control file</p><iframe id='corframe' width='500' height='300' src='../postcorr/initfringefit.php?exp="+session+"'></iframe>");
		}
		else if(value=="fringecheck"){
			$("#tracearea").html("<p>Fringe fitting</p><iframe id='corframe' width='500' height='300' src='../postcorr/fringefit.php?exp="+session+"'></iframe>");
		}
		else if(value=="monitor"){
			$("#tracearea").html("<p>Calculating antenna SEFD</p><iframe id='corframe' width='500' height='300' src='../pmonitor/monitor.php?exp="+session+"'></iframe>");
		}
		else if (value=="done"){
			$("#tracearea").html('<textarea id="obstext" rows="10" cols="50" readonly>All done! (show output)</textarea>');
			makeRequest("./fringe/removerealtimedb.php", function(response) {
				//header("Refresh:0.1; url=fringe/fringeshowout.php?exp="+session);
				//window.location.href = window.location.origin + window.location.pathname + "fringe/fringeshowout.php?exp="+session;
				window.location.href = window.location.origin + window.location.pathname.slice(0, window.location.pathname.lastIndexOf('/')) + "/fringe/fringeshowout.php?exp="+session;
			});

		}

		if(value !== "done"){
			var loopprogress = setInterval(function(){
				if($("#corframe").contents().text().indexOf(completemsg[ind]) > 0){
					if (ind<postcorr.length){
						ind = ind + 1;
						postcorrprogress(postcorr[ind]);
					}
					else{
						postcorrprogress("next");
					}
				}
			},5000); //check status every 5 seconds
		}
		
	}
	
}

function inxmin(x) {
	var date = new Date();
	var newdate = new Date(date.getTime() + x*60000);
	var Y = newdate.getUTCFullYear();
	var m = newdate.getUTCMonth();
	var d = newdate.getUTCDate();
	var H = newdate.getUTCHours();
	var i = newdate.getUTCMinutes();
	var s = newdate.getUTCSeconds();
	m=m+1;
	if(H<10){
		H="0"+H;
	}
	if(i<10){
		i="0"+i;
	}
	if(s<10){
		s="0"+s;
	}
	document.getElementById("startTime").value = Y+"-"+m+"-"+d+" "+H+":"+i+":"+s;
}


</script>
</head>
<body>
<h2>
One click fringe check
</h2>
<div style='color:Red' id="notice"></div>

<?php 
switch($realtimedb["fringe_stage"]){
	case "start":
		$pgcontentid = "start";
	break;
	case "lockactive":
		$pgcontentid = "lockactive";
	break;
	default:
		$pgcontentid = "default";
}
echo '<div class="pgcontent" id='.$pgcontentid.'>';

if($realtimedb["fringe_preset"]==""){
	if (false !== strpos($_SERVER['REQUEST_URI'], '?')) {
		if($_GET["error"]==1){
			echo "<div style='color:Red'>skd file not generated, please use reasonable parameters</div>";
		}
		else{
			header("Location: fringe.php");
		}
	}
	include 'fringe/schedform.html';
	include 'fringe/schednote.html';
}
else{
	if($realtimedb["fringe_stage"]==""){
		header("Location: fringe.php");
	}
	else{
		if($realtimedb["fringe_stage"]=="start"){
			include 'fringe/obstrace.html';
			ob_start();
			require_once '../observing/fringestart.php';
			$FStart=new FringeStart;
			$FStart->start($realtimedb["fringe_scheddir"]);
			ob_end_clean();
		}
		elseif($realtimedb["fringe_stage"]=="lockactive"){ 
			//when others try to initiate fringe check while lock is active
			include 'fringe/obstrace.html';
		}
	}
}
?>

</div>
<button id='btncontinue' type="button" style="display:none" >Continue</button>
<button id='btnstartover' type="button" style="display:none" >Start over</button>

</body>
</html>