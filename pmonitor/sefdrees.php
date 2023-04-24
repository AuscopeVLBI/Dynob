<?php
//This script re-estimates the SEFD by inputing the observed SEFD per baseline.

//old method (when 3 stations or less)
//usage: $C->rees("HbKe:xxx.xxx,HbYg:yyy.yyy,KeYg:zzz.zzz");

//lsq method (when 4 stations or more)
//usage: $C->lsqrees(old,snrr)
//usage: $C->lsqrees("Hb:10000,Ke:12000,Yg:5000,..","HbKe:0.5,KeYg:0.6,HbYg:0.8,..");

//$T = new Sefdrees;
//$T->rees("HbKe:2000000,KeYg:6000000,HbYg:3000000,HbWw:4000000,KeWw:8000000,WwYg:12000000,HtHb:5000000");
//$T->rees("HtYg:15000000,KeYg:6000000");

require '../scripts/vendor/autoload.php';

use NumPHP\Core\NumArray;
use NumPHP\LinAlg\LinAlg;

Class Sefdrees{

	public $out = [];

	function rees($input){
		$arr = explode(",",$input);
		$blval = [];
		$stn = [];
		$tmpout = [];
		
		foreach($arr as $arinput){
			if(strpos($arinput,":")==4){
				$bl =  substr($arinput,0,strpos($arinput,":"));

				$twos1 = substr($bl,0,2);
				$twos2 = substr($bl,2,2);
				if(!in_array($twos1,$stn)){
					array_push($stn,$twos1);
				}
				if(!in_array($twos2,$stn)){
					array_push($stn,$twos2);
				}

				$blval[$twos1.$twos2] = substr($arinput,strpos($arinput,":")+1);
				$blval[$twos2.$twos1] = substr($arinput,strpos($arinput,":")+1); //just in case

			}
		}

		//four steps:
		//1. solve for those stations that have only 1 baseline
		$countbl = [];
		$st1b = [];
		foreach($stn as $st1){
			foreach($stn as $st2){
				if(strpos($input,$st1.$st2)!==false){
					$countbl[$st1] = $countbl[$st1] + 1;
					$countbl[$st2] = $countbl[$st2] + 1;
				}
			}
		}
		foreach ($countbl as $ckey => $cval){
			if($cval==1){
				array_push($st1b,$ckey);
			}
		}
		foreach($st1b as $st1){
			foreach($st1b as $st2){
				if(strpos($input,$st1.$st2)!==false){
					//$tmpout[$st1] = $tmpout[$st2] = sqrt($blval[$st1.$st2]); //take sqrt
					$tmpout[$st1] = $tmpout[$st2] = 0; //take 0 value
				}
			}
		}

		//2. solve by finding common stations - multi baselines
		$iscircular = [];
		foreach($arr as $_bl){
			//break stations
			$twos1 = substr($_bl,0,2);
			$twos2 = substr($_bl,2,2);
			foreach($stn as $st){
				//e.g. For HtWw, if HbHt and HbWw exist, can solve the equation
				if( (strpos($input,$twos1.$st)!==false || strpos($input,$st.$twos1)!==false) && (strpos($input,$twos2.$st)!==false || strpos($input,$st.$twos2)!==false) ){
					//derived: st^2 = (sefd1 self* sefd2 self)/sefd no self
					//e.g Hb^2 = (HbKe*HbYg)/KeYg
					$tmpout[$twos1] = sqrt($blval[$twos1.$st]*$blval[$twos1.$twos2]/$blval[$twos2.$st]);
					$tmpout[$twos2] = sqrt($blval[$twos2.$st]*$blval[$twos1.$twos2]/$blval[$twos1.$st]);
					$tmpout[$st] = sqrt($blval[$twos2.$st]*$blval[$twos1.$st]/$blval[$twos1.$twos2]);
					$iscircular[$twos1] = $iscircular[$twos2] = $iscircular[$st] = true;
				}
				else{ //non-circular
					if(!isset($iscircular[$twos1])){
						//$tmpout[$twos1] = sqrt($blval[$twos1.$twos2]); //take sqrt
						//$tmpout[$twos1] = 0; //take 0
						$iscircular[$twos1] = false;
					}
					if(!isset($iscircular[$twos2])){
						//$tmpout[$twos2] = sqrt($blval[$twos1.$twos2]); //take sqrt
						//$tmpout[$twos2] = 0; //take 0
						$iscircular[$twos2] = false;
					}
				}
			}
		}

		//3. solve for all unsolvable stations
		foreach($iscircular as $ikey1 => $ival1){
			foreach($iscircular as $ikey2 => $ival2){
				if(strpos($input,$ikey1.$ikey2)!==false && $ival1==false && $ival2 ==false ){
					//$tmpout[$ikey1] = $tmpout[$ikey2] = sqrt($blval[$st1.$st2]); //take sqrt
					$tmpout[$ikey1] = $tmpout[$ikey2] = 0; //take 0 value
				}
			}
		}

		//4. solve for those solvable without common stations using solved stations - multi baselines
		$tokeys = array_keys($tmpout);
		$unsolved = array_diff($stn,$tokeys);

		while(count($unsolved)>0){
			foreach($blval as $bkey => $bval){
				$twos1 = substr($bkey,0,2);
				$twos2 = substr($bkey,2,2);
						
				if(in_array($twos1,$unsolved)){
					if(!in_array($twos2,$unsolved)){
						$tmpout[$twos1] = $bval/$tmpout[$twos2];
						unset($unsolved[array_search($twos1,$unsolved)]);
						$unsolved = array_values($unsolved);
					}
				}
				elseif(in_array($twos2,$unsolved)){
					$tmpout[$twos2] = $bval/$tmpout[$twos1];
					unset($unsolved[array_search($twos2,$unsolved)]);
					$unsolved = array_values($unsolved);
				}
			}
		}
		$this->out = $tmpout;
		return $this->out;
	}

	function lsqrees($sefd,$snrr){

		$tmpout = [];

		//break down $sefd input
		$arr = explode(",",$sefd);
		$stval = [];
		$stn = [];

		foreach($arr as $arinput){
			if(strpos($arinput,":")==2){
				$st = substr($arinput,0,2);
				if(!in_array($st,$stn)){
					array_push($stn,$st);
				}
				$stval[$st] = trim(substr($arinput,strpos($arinput,":")+1));
			}
		}
		$x0 = array_values($stval);
		$x0mat = new NumArray([$x0]);

		//break down $snrr input
		$arr = explode(",",$snrr);
		$corrtab = [];
		$basnum = [];
		$stn = [];
		
		foreach($arr as $arinput){
			if(strpos($arinput,":")==4){
				$bl =  substr($arinput,0,4);

				$st1 = substr($bl,0,2);
				$st2 = substr($bl,2,2);
				
				//into $corrtab
				array_push($corrtab,trim(substr($arinput,strpos($arinput,":")+1)));

				//into $basnum (compare with)
				$stkey = array_keys($stval);
				$subbasnum = [array_keys($stkey, $st1)[0],array_keys($stkey, $st2)[0]];
				array_push($basnum,$subbasnum);
			}
		}

		$corrtabmat = new NumArray([$corrtab]);
		$basnummat = new NumArray($basnum);

		//$x0 = [10000,  12000,  5000, 6000, 8000];
		//$x0mat = new NumArray([$x0]);

		//$corrtab = [0.69,0.52,0.6, 0.8,0.52,0.6, 0.8,0.6,0.8, 1.1];
		//$corrtabmat = new NumArray([$corrtab]);

		//$basnum = [[0,1],[0,2],[0,3],[0,4],[1,2],[1,3],[1,4],[2,3],[2,4],[3,4]];
		//$basnummat = new NumArray($basnum);

		//Method begins
		$l_rat = [];
		$l0 = [];

		for ($ib=0;$ib < count($basnum);$ib++){
			$lratcal = sqrt($x0[$basnum[$ib][0]]*$x0[$basnum[$ib][1]])/$corrtab[$ib]; //sqrt(SEFD1*SEFD2)/SNRratio
			$l0cal = sqrt($x0[$basnum[$ib][0]]*$x0[$basnum[$ib][1]]); //sqrt(SEFD1*SEFD2)
			array_push($l_rat,$lratcal);
			array_push($l0,$l0cal);
		}

		$l_ratmat = new NumArray([$l_rat]);
		$l0mat = new NumArray([$l0]);
		$lmat = $l_ratmat;
		$lmat->sub($l0mat);
		$lmat->getTranspose();

		$a = \NumPHP\Core\NumPHP::zeros($lmat->getShape()[1],count($x0));

		for ($ib=0;$ib < count($basnum);$ib++){
			$a1cal = $x0[$basnum[$ib][1]]/(2*sqrt($x0[$basnum[$ib][0]] * $x0[$basnum[$ib][1]])); //first station : SEFD2/(2*sqrt(SEFD1*SEFD2))
			$a2cal = $x0[$basnum[$ib][0]]/(2*sqrt($x0[$basnum[$ib][0]] * $x0[$basnum[$ib][1]])); //second station : SEFD1/(2*sqrt(SEFD1*SEFD2))
			$a->set($ib, $basnum[$ib][0], $a1cal);
			$a->set($ib, $basnum[$ib][1], $a2cal);
		}

		$p = \NumPHP\Core\NumPHP::eye(count($basnum)); //for scan-wise, meaning: one scan only

		$at = $a->getTranspose();
		$atp = $at->dot($p);
		$atpa = $atp->dot($a);

		$invn = \NumPHP\LinAlg\LinAlg::inv($atpa);
		$at = $a->getTranspose();
		$inat = $invn->dot($at);
		$inatp = $inat->dot($p);


		$inatpl = $inatp->dot($lmat->getTranspose());

		

		$x = $x0mat->getTranspose()->add($inatpl);
		$x=$x->getData(); //final re-estimated values in array format

		for ($i=0;$i<count($stkey);$i++){
			$tmpout[$stkey[$i]] = $x[$i][0];
		}
		
		$this->out = $tmpout;
		return $tmpout;

	}

}
?>