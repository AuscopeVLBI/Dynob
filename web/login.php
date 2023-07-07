<!DOCTYPE html>
<html>
<head>
<title>Dynob login</title>
</head>
<body>

<?php
if(isset($_COOKIE["dynob_user"])) {
	//echo 'You have logged in!';
	if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
		$protocol = 'https://';
	}
	else {
		$protocol = 'http://';
	}
	header('Refresh: 0.1; URL = '.$protocol.$_SERVER['SERVER_NAME'].'/web/index.html');
	die();
}
else{
	echo $_SESSION["login_redir"];
	echo '<h1>Dynob login</h1>
	<div class="loginBox">';
		if ($_SESSION["login_message"]){
			echo '<div class="loginTopMessage">
			<b>'.$_SESSION["login_message"].'</b></div><br>';
		}
		echo '<form action="./function/login.php?ref='.$_GET["ref"].'" method="post">
			
			<div class="formGroup">
				<label for="username"><b>Username:</b></label>
				<input type="text" class="" id="username" name="username">
			</div>
			<br>
			<div class="formGroup">
				<label for="pwd"><b>Password:</b></label>
				<input type="password" class="" id="pwd" name="password" required="required">
			</div>
			<br>
			<button name="submit" type="submit" class="">Submit</button>
		</form>
	</div>';
}

?>

</body>
</html>

