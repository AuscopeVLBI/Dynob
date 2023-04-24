<?php
Class Stats{
	public static function remove_outliers($dataset, $magnitude = 1) {
		$filtered = array_filter($dataset, function ($x) { return $x < 50000; }); //remove greater than 50000Jy
		//print_r($filtered);
		$dataset = $filtered;
		$count = count($dataset);
		if($count <= 0){
			$outlier = [0];
			$nonoutlier = [0];
		}
		else{
			$mean = array_sum($dataset) / $count; // Calculate the mean
			$deviation = sqrt(array_sum(array_map('self::sd_square', $dataset, array_fill(0, $count, $mean))) / $count) * $magnitude; // Calculate standard deviation and times by magnitude
			
			$outlier = array_filter($dataset, function($x) use ($mean, $deviation) { return ($x > $mean + $deviation || $x < $mean - $deviation); });
			$nonoutlier = array_filter($dataset, function($x) use ($mean, $deviation) { return ($x <= $mean + $deviation && $x >= $mean - $deviation); });
		}
		
		return [$nonoutlier,$outlier];
		
		//return array_filter($dataset, function($x) use ($mean, $deviation) { return ($x <= $mean + $deviation && $x >= $mean - $deviation); }); // Return filtered array of values that lie within $mean +- $deviation.
	}
	
	private static function sd_square($x, $mean) {
		return pow($x - $mean, 2);
	}

	public static function mean($a){
		$sum = 0;
		$n = sizeof($a);
		for ($i = 0; $i < $n; $i++) 
			$sum += $a[$i];
		return (double)$sum/(double)$n;
	}
 
	public static function median($a){
		sort($a);
		$n = sizeof($a);
		// check for even case
		if ($n % 2 != 0){
			return (double)$a[$n / 2];
		}
		return (double)($a[($n - 1) / 2] + $a[$n / 2]) / 2.0;
	}
}

?>
