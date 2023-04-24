<?php

require_once "../../common/global.php";

session_start();

class Login{
	
	public function __construct(){
		
		if (isset($_POST['submit'])){
			
			$username = htmlspecialchars($_POST['username']);
			$password = htmlspecialchars($_POST['password']);
			
			$conn = new mysqli($GLOBALS["sqlserver"], $GLOBALS["sqluser"], $GLOBALS["sqlpass"], $GLOBALS["sqlpre"]."web");

			if ($conn->connect_error) {
				die("Connection failed: " . $conn->connect_error);
			}
			$sql = 'SELECT * FROM Dynob_user WHERE username="'.$username.'"';
			
			$result = $conn->query($sql);
			
			
			if ($result->num_rows > 0) {
				
				$row = $result->fetch_array(MYSQLI_ASSOC);
				
				if (password_verify ($password, $row['password'])){
					setcookie("dynob_user", $_POST["username"], time()+86400, "/");
					$_SESSION["login_message"] = "success";
				}
				else{
					$_SESSION["login_message"] = "wrongusrpwd";
				}
				
				$result->free();

				$conn->close();
				header('Refresh: 0.1; URL = '.base64_decode($_GET['ref']));
			}
			
			else{
				
				$_SESSION["login_message"] = "wrongusrpwd";
				$conn->close();
				header('Refresh: 0.1; URL = ../login.php?ref='.$_GET['ref']);
				
			}

			//$conn->close();
			//header('Refresh: 0.1; URL = '.base64_decode($_GET['ref']));
			unset($_SESSION["login_redir"]);
			die();
			
			//$dynob_name = 
			//setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");
			
		}
	}
}

$lg = new Login();


?>