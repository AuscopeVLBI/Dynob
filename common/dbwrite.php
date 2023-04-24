<?php
require_once "../common/global.php";

//usage insert("mfringe","SEFDmon",["HbX","HbS"],[4500,5000]);

//$tst = new DbWrite;
//$tst->insert("mfringe","SEFDmon",["session","HbX","HbS"],['mftest',4500,5000]);

Class DbWrite{
	function insert($dbname,$tab,$col,$val){
		$servername = $GLOBALS["sqlserver"];
		$username = $GLOBALS["sqluser"];
		$password = $GLOBALS["sqlpass"];
		$conn = new mysqli($servername, $username, $password, $dbname);
		if ($conn->connect_error) {
			die("Failed to connect to MySQL: " . $conn->connect_error);
		}
		for($i=0;$i<count($val);$i++){
			$val[$i] = $conn -> real_escape_string($val[$i]);
		}
		$sql = "INSERT INTO $tab (".implode(", ",$col).") VALUES ('".implode("', '",$val)."')";
		if ($conn->query($sql) === true) {
			echo "New record created successfully";
		}
		else{
			echo "Error: " . $sql . "<br>" . $conn->error;
		}
		$conn->close();
	}

	function query($dbname,$sql){
		$servername = $GLOBALS["sqlserver"];
		$username = $GLOBALS["sqluser"];
		$password = $GLOBALS["sqlpass"];
		$conn = new mysqli($servername, $username, $password, $dbname);
		if ($mysqli -> connect_errno) {
			echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
			exit();
		}
		//$sql = "SELECT $col FROM $tab $arg";
		$result = $conn->query($sql);
		if ($result->num_rows > 0){
 			// output data of each row
			$ans = [];
			while($row = $result->fetch_assoc()){
				array_push($ans,$row);
				//echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
			}
		}
		else{
			$ans = "null";
		}
		$result -> free_result();
		$conn->close();
		return $ans;
	}

	function addColtoTab($dbname,$tab,$col,$type){
		$servername = $GLOBALS["sqlserver"];
		$username = $GLOBALS["sqluser"];
		$password = $GLOBALS["sqlpass"];
		$conn = new mysqli($servername, $username, $password, $dbname);
		if ($conn->connect_error) {
			die("Failed to connect to MySQL: " . $conn->connect_error);
		}
		$sql = "ALTER TABLE $tab ADD $col $type";
		if ($conn->query($sql) === true) {
			echo "New column added successfully";
		}
		else{
			echo "Error: " . $sql . "<br>" . $conn->error;
		}
		$conn->close();
	}

	function update($dbname,$tab,$col,$val,$exp){
		$servername = $GLOBALS["sqlserver"];
		$username = $GLOBALS["sqluser"];
		$password = $GLOBALS["sqlpass"];
		$conn = new mysqli($servername, $username, $password, $dbname);
		if ($conn->connect_error) {
			die("Failed to connect to MySQL: " . $conn->connect_error);
		}
		$set = [];
		for($i=0;$i<count($val);$i++){
			$val[$i] = $conn -> real_escape_string($val[$i]);
			array_push($set,$col[$i]." = '".$val[$i]."'");
		}
		$sql = "UPDATE $tab SET ".implode(", ",$set)." WHERE session='".$exp."'";
		if ($conn->query($sql) === true) {
			echo "Record updated successfully";
		}
		else{
			echo "Error: " . $sql . "<br>" . $conn->error;
		}
		$conn->close();
	}
}

?>