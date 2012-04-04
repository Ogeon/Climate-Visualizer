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

header('Content-type: application/octet-stream');

ob_start();
set_time_limit(0);

$file = fopen("temps.txt", "r");

$centerPoints = array();
$centerStatus = 0;

while(!feof($file) && $centerStatus < 2) {
	$parts = explode(" ", fgets($file));

	$label = trim($parts[0]);

	if($label == "CPregLon") {
		$centerStatus++;
		$centerPoints = loadIntoArray($centerPoints, "Lon", $parts);
	}

	if($label == "CPregLat") {
		$centerStatus++;
		$centerPoints = loadIntoArray($centerPoints, "Lat", $parts);
	}
	
}

$limitsLon = array(INF, -INF, 0);
$limitsLat = array(INF, -INF, 0);

foreach ($centerPoints as $key => $value) {
	//echo "<p>Point $key:<br/>";
	$limitsLon[0] = min($limitsLon[0], $value["Lon"]);
	$limitsLon[1] = max($limitsLon[1], $value["Lon"]);
	$limitsLon[2] += $value["Lon"];

	$limitsLat[0] = min($limitsLat[0], $value["Lat"]);
	$limitsLat[1] = max($limitsLat[1], $value["Lat"]);
	$limitsLat[2] += $value["Lat"];

	/*foreach ($value as $key => $value) {
		echo "&nbsp;&nbsp;&nbsp;$key => $value<br/>";
	}
	echo "</p>";*/
}

$limitsLon[2] /= sizeof($centerPoints);
$limitsLat[2] /= sizeof($centerPoints);

/*echo "<p>Min Lon: ".$limitsLon[0]."<br/>Max Lon: ".$limitsLon[1]."<br/>Mean Lon:".$limitsLon[2]."</p>";
echo "<p>Min Lat: ".$limitsLat[0]."<br/>Max Lat: ".$limitsLat[1]."<br/>Mean Lat:".$limitsLat[2]."</p>";

echo "<br />";*/
$meanZ = 0;
$maxZ = 0;
foreach ($centerPoints as $i => $value) {
	$lon = toRad($value["Lon"] - $limitsLon[2] - 90);
	$lat = toRad($value["Lat"] + 90);

	$x = cos($lon)*sin($lat);
	$y = sin($lon)*sin($lat);
	$z = cos($lat);

	$a = toRad(90 - $limitsLat[2]);
	$centerPoints[$i]["x"] = $x;
	$centerPoints[$i]["y"] = $y*cos($a) - $z*sin($a);
	$centerPoints[$i]["z"] = $y*sin($a) + $z*cos($a);

	//$meanZ += $centerPoints[$i]["z"];
	$maxZ = min($maxZ, $centerPoints[$i]["z"]);
}
//$meanZ /= sizeof($centerPoints);

$texture = imagecreatefromjpeg("earth.jpg");
$width = imagesx($texture);
$height = imagesy($texture);
foreach ($centerPoints as $value) {
	$texX = $width*$value["Lon"]/360 + $width/2;
	$texY = -$height*$value["Lat"]/180 + $height/2;
	$color = imagecolorat($texture, $texX, $texY);

	echo ($value["x"]).";";
	echo (-$value["y"]).";";
	echo ($value["z"]-$maxZ).";";
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