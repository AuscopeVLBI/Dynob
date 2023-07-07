<?php
require_once "../config/config.php";

class AddUser extends Config{
	
	private $status = false;
	
	public function __construct(){
		
		if (isset($_POST['submit'])){
			
			$username = htmlspecialchars($_POST['username']);
			$password = htmlspecialchars($_POST['password']);
			//$invitecode = htmlspecialchars($_POST['invitecode']);
			//$passwordconf = htmlspecialchars($_POST['passwordconf']);
			$email = htmlspecialchars($_POST['email']);
			
			$conn = new mysqli(parent::$servername, parent::$username, parent::$password, parent::$db_pre."web");

			if ($conn->connect_error) {
				die("Connection failed: " . $conn->connect_error);
			}

			//if ($invitecode==="vlbiDynob"){
				
			$password = password_hash($password, PASSWORD_BCRYPT);
				
			$sql = "INSERT INTO Dynob_user (username, password, email, language, isadmin) VALUES ('$username', '$password', '$email','en','1')";

			if ($conn->query($sql) === TRUE) {
				echo "New record created successfully";
				$this->$status = true;
				
			} else {
				echo "Error: " . $sql . "<br>" . $conn->error;
			}
				
			//}
			//else{
			//	echo "Registration opens for invited stations only";
			//}
			
			$conn->close();
			
			//$dynob_name = 
			//setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");
			echo parent::$servername." and ". parent::$username." and ". parent::$password." and ". parent::$db_pre."web";
			//die();
			
		}
	}
	
	public function getStatus(){
		return $this->$status;
	}
}

?>