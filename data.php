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

header('Content-type: application/octet-stream');

ob_start();
set_time_limit(0);

$file = fopen("data/temps.txt", "r");

$regions = array();
$readStatus = 0;

while(!feof($file) && $readStatus < 6) {
	$parts = explode(" ", fgets($file));

	$label = trim($parts[0]);

	if($label == "CPregLon") {
		$readStatus++;
		$regions = loadIntoArray($regions, "Nlon", $parts);
	}

	if($label == "CPregLat") {
		$readStatus++;
		$regions = loadIntoArray($regions, "Nlat", $parts);
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
	
}

$limitsLon = array(INF, -INF, 0);
$limitsLat = array(INF, -INF, 0);

foreach ($regions as $key => $value) {
	//echo "<p>Point $key:<br/>";
	$limitsLon[0] = min($limitsLon[0], $value["Nlon"]);
	$limitsLon[1] = max($limitsLon[1], $value["Nlon"]);
	$limitsLon[2] += $value["Nlon"];

	$limitsLat[0] = min($limitsLat[0], $value["Nlat"]);
	$limitsLat[1] = max($limitsLat[1], $value["Nlat"]);
	$limitsLat[2] += $value["Nlat"];

	/*foreach ($value as $key => $value) {
		echo "&nbsp;&nbsp;&nbsp;$key => $value<br/>";
	}
	echo "</p>";*/
}

$limitsLon[2] /= sizeof($regions);
$limitsLat[2] /= sizeof($regions);

/*echo "<p>Min Lon: ".$limitsLon[0]."<br/>Max Lon: ".$limitsLon[1]."<br/>Mean Lon:".$limitsLon[2]."</p>";
echo "<p>Min Lat: ".$limitsLat[0]."<br/>Max Lat: ".$limitsLat[1]."<br/>Mean Lat:".$limitsLat[2]."</p>";

echo "<br />";*/
$meanZ = 0;
$maxZ = 0;
foreach ($regions as $i => $value) {
	//Normal
	$lon = toRad($value["Nlon"] - $limitsLon[2] - 90);
	$lat = toRad($value["Nlat"] + 90);
	$regions[$i]["N"] = toCartesian($lat, $lon, $limitsLat[2]);

	//Upper left
	$lon = toRad($value["ULlon"] - $limitsLon[2] - 90);
	$lat = toRad($value["ULlat"] + 90);
	$regions[$i]["UL"] = toCartesian($lat, $lon, $limitsLat[2]);

	//Upper right
	$lon = toRad($value["URlon"] - $limitsLon[2] - 90);
	$lat = toRad($value["URlat"] + 90);
	$regions[$i]["UR"] = toCartesian($lat, $lon, $limitsLat[2]);

	//$meanZ += $centerPoints[$i]["z"];
	$maxZ = min($maxZ, $regions[$i]["N"]["z"]);
}
//$meanZ /= sizeof($centerPoints);

$texture = imagecreatefromjpeg("data/earth.jpg");
$width = imagesx($texture);
$height = imagesy($texture);
foreach ($regions as $value) {
	$texX = $width*$value["Nlon"]/360 + $width/2;
	$texY = -$height*$value["Nlat"]/180 + $height/2;
	$color = imagecolorat($texture, $texX, $texY);

	//normals
	echo ($value["N"]["x"]).";";
	echo (-$value["N"]["y"]).";";
	echo ($value["N"]["z"]).";";

	//upper left
	echo ($value["UL"]["x"]).";";
	echo (-$value["UL"]["y"]).";";
	echo ($value["UL"]["z"]-$maxZ).";";

	//upper right
	echo ($value["UR"]["x"]).";";
	echo (-$value["UR"]["y"]).";";
	echo ($value["UR"]["z"]-$maxZ).";";

	echo (($color >> 16) & 0xFF).";";
	echo (($color >> 8) & 0xFF).";";
	echo ($color & 0xFF).":";

	flush_buffers();
	usleep(1);
}

imagedestroy($texture);



fclose($file);
ob_end_flush();
?>