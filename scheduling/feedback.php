<?php
require_once "sefd2cat.php";
require_once "flux2cat.php";

$fb = new Feedback;
$fb->feedback($_GET["exp"]);

Class Feedback{
	function feedback($exp){
		
		$S2C=new Sefd2cat;
		$F2C=new Flux2cat;

		//var_dump($S2C->createEquipCat($exp));
		//var_dump($F2C->createFluxCat($exp));

		if($S2C->createEquipCat($exp) == "OK"){
			echo "dynequip.cat has been created! Download it <a href='./dynflux/dynequip.cat'>here</a><br>";
		}
		else{
			echo "An error occured while creating the dynequip.cat<br>";
		}

		if($F2C->createFluxCat($exp) == "OK"){
			echo "dynflux.cat has been created! Download it <a href='./dynflux/dynflux.cat'>here</a>";
		}
		else{
			echo "An error occured while creating the dynflux.cat";
		}
	}
}

?>