<?php

class Core{
	
	public static function langSelect(){
		$langMat = explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
		if(!empty($langMat)){
			
			switch (true){
				//english
				case (strpos($langMat[0],'en')!== false):
					return "en";
				break;
				//may add other languages here (code must exist as directory name under the language directory), e.g.
				//case (strpos($langMat[0],'zh')!== false):
				//	return "zh";
				//break;
				default:
					return "en";
			}
		}
		else{
			return "en";
		}
	}
	
	public static function styleSelect(){
		if (CURSCRIPT == 'stationcp'){
			$styleSelect = 'stationCP';
		}
		
		if ($styleSelect == 'stationCP'){
			define("inputClass","loginInput");
			define("btnClass","btn-dark btnc");
			define("inputRClass","registerInput");
			
		}
	}
	
}

class C extends Core{}

?>