<?
ini_set('display_errors','On');
error_reporting(E_ALL);

function flush_buffers(){
    while ( @ob_end_flush() ); // even if there is no nested output buffer
	flush();
    ob_start();
}

function loadIntoArray($array, $key, $data) {
	$a = $array;
	$index = 0;
	foreach ($data as $value) {
		while(sizeof($a) < $index+1)
			$a[] = array();
		$v = trim($value);
		if($v != "" && is_numeric($v)) {
			$a[$index][$key] = $v;
			$index++;
		}

	}

	return $a;
}

function toRad($deg) {
	return 2*pi()*$deg/360;
}

function toCartesian($lat, $lon, $latShift) {
	$x = cos($lon)*sin($lat);
	$y = sin($lon)*sin($lat);
	$z = cos($lat);

	$a = toRad(90 - $latShift);
	$vector = array();
	$vector["x"] = $x;
	$vector["y"] = $y*cos($a) - $z*sin($a);
	$vector["z"] = $y*sin($a) + $z*cos($a);
	return $vector;
}

function convertCoordinates($source, $dest, $name, $lonShift, $latShift) {
	$lon = toRad($source[$name."lon"] - $lonShift - 90);
	$lat = toRad($source[$name."lat"] + 90);
	$dest[$name] = toCartesian($lat, $lon, $latShift);
	return $dest;
}

header('Content-type: application/octet-stream');

ob_start();
set_time_limit(0); //Can be removed if not allowed

$file = fopen("data/temps.txt", "r");

$regions = array();
$readStatus = 0;

while(!feof($file) && $readStatus < 10) {
	$parts = explode(" ", fgets($file));

	$label = trim($parts[0]);

	if($label == "CPregLon") {
		$readStatus++;
		$regions = loadIntoArray($regions, "Clon", $parts);
	}

	if($label == "CPregLat") {
		$readStatus++;
		$regions = loadIntoArray($regions, "Clat", $parts);
	}

	if($label == "ULregLon") {
		$readStatus++;
		$regions = loadIntoArray($regions, "ULlon", $parts);
	}

	if($label == "ULregLat") {
		$readStatus++;
		$regions = loadIntoArray($regions, "ULlat", $parts);
	}

	if($label == "URregLon") {
		$readStatus++;
		$regions = loadIntoArray($regions, "URlon", $parts);
	}

	if($label == "URregLat") {
		$readStatus++;
		$regions = loadIntoArray($regions, "URlat", $parts);
	}

	if($label == "LRregLon") {
		$readStatus++;
		$regions = loadIntoArray($regions, "LRlon", $parts);
	}

	if($label == "LRregLat") {
		$readStatus++;
		$regions = loadIntoArray($regions, "LRlat", $parts);
	}

	if($label == "LLregLon") {
		$readStatus++;
		$regions = loadIntoArray($regions, "LLlon", $parts);
	}

	if($label == "LLregLat") {
		$readStatus++;
		$regions = loadIntoArray($regions, "LLlat", $parts);
	}
	
}

$limitsLon = array(INF, -INF, 0);
$limitsLat = array(INF, -INF, 0);

foreach ($regions as $key => $value) {
	//echo "<p>Point $key:<br/>";
	$limitsLon[0] = min($limitsLon[0], $value["Clon"]);
	$limitsLon[1] = max($limitsLon[1], $value["Clon"]);
	$limitsLon[2] += $value["Clon"];

	$limitsLat[0] = min($limitsLat[0], $value["Clat"]);
	$limitsLat[1] = max($limitsLat[1], $value["Clat"]);
	$limitsLat[2] += $value["Clat"];

	/*foreach ($value as $key => $value) {
		echo "&nbsp;&nbsp;&nbsp;$key => $value<br/>";
	}
	echo "</p>";*/
}

$limitsLon[2] /= sizeof($regions);
$limitsLat[2] /= sizeof($regions);

$meanZ = 0;
$maxZ = 0;
foreach ($regions as $i => $value) {
	//Normal
	$regions[$i] = convertCoordinates($value, $regions[$i], "C", $limitsLon[2], $limitsLat[2]);

	//Upper left
	$regions[$i] = convertCoordinates($value, $regions[$i], "UL", $limitsLon[2], $limitsLat[2]);

	//Upper right
	$regions[$i] = convertCoordinates($value, $regions[$i], "UR", $limitsLon[2], $limitsLat[2]);

	//Lower right
	$regions[$i] = convertCoordinates($value, $regions[$i], "LR", $limitsLon[2], $limitsLat[2]);

	//Lower left
	$regions[$i] = convertCoordinates($value, $regions[$i], "LL", $limitsLon[2], $limitsLat[2]);

	//$meanZ += $centerPoints[$i]["z"];
	$maxZ = min($maxZ, $regions[$i]["C"]["z"]);
}
//$meanZ /= sizeof($centerPoints);

$texture = imagecreatefromjpeg("data/earth.jpg");
$width = imagesx($texture);
$height = imagesy($texture);
foreach ($regions as $value) {
	$texX = $width*$value["Clon"]/360 + $width/2;
	$texY = -$height*$value["Clat"]/180 + $height/2;
	$color = imagecolorat($texture, $texX, $texY);

	//centers
	echo ($value["C"]["x"]).";";
	echo (-$value["C"]["y"]).";";
	echo ($value["C"]["z"]-$maxZ+.015).";";

	//upper left
	echo ($value["UL"]["x"]).";";
	echo (-$value["UL"]["y"]).";";
	echo ($value["UL"]["z"]-$maxZ+.015).";";

	//upper right
	echo ($value["UR"]["x"]).";";
	echo (-$value["UR"]["y"]).";";
	echo ($value["UR"]["z"]-$maxZ+.015).";";

	//lower right
	echo ($value["LR"]["x"]).";";
	echo (-$value["LR"]["y"]).";";
	echo ($value["LR"]["z"]-$maxZ+.015).";";

	//lower left
	echo ($value["LL"]["x"]).";";
	echo (-$value["LL"]["y"]).";";
	echo ($value["LL"]["z"]-$maxZ+.015).";";

	echo (($color >> 16) & 0xFF).";";
	echo (($color >> 8) & 0xFF).";";
	echo ($color & 0xFF).":";

	flush_buffers();
	usleep(1);
}

imagedestroy($texture);


echo "T:";

$counter = 0;

while(!feof($file)) {
	$parts = explode(" ", fgets($file));

	foreach ($parts as $key => $value) {
		$v = trim($value);
		if($v == "")
			continue;

		echo $v;

		if($key < sizeof($parts)-1)
			echo ";";
		else
			echo ":";
	}

	$counter ++;

	flush_buffers();
	usleep(1);
}

fclose($file);
ob_end_flush();
?>