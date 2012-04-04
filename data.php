<?
ini_set('display_errors','On');
error_reporting(E_ALL);

include_once("netCDF.php");

function flush_buffers(){
    while ( @ob_end_flush() ); // even if there is no nested output buffer
		flush();
    ob_start();
}

function stream($data, $var) {
	$vals = $data->getValues($var);
	foreach ($vals as $value) {
		if($data->isTime($var)) {
			$years = $value/12;
			$monts = 1 + $value%12;
			echo (1950 + floor($years))."-".$monts.";";
		} else {
			echo $value.";";
		}
		flush_buffers();
		usleep(1);
	}
}

function streamMatrix($data, $var) {
	$vals = $data->getValues($var, array(0, 0, 0, 0), array(0, 0, 1, 1));
	foreach ($vals as $value) {
		if($data->isTime($var)) {
			$years = $value/12;
			$monts = 1 + $value%12;
			echo (1950 + floor($years))."-".$monts.";";
		} else {
			echo $value.";"."<br />";
		}
		flush_buffers();
		usleep(1);
	}
}


ob_start();

$data = new netCDF("temps.nc");
$vars = $data->getVariables();

foreach ($vars as $value) {
	echo $value."<br />";
}

echo "<br />";

$attrs = $data->getDimensions("time_bnds");
foreach ($attrs as $value) {
	echo $value."<br />";
}

echo $data->getLength("rlon");

echo "<br />";
streamMatrix($data, "tas");
/*echo "T;";
stream($data, "time");
echo "X;";
stream($data, "rlon");
echo "Y;";
stream($data, "rlat");*/

ob_end_flush();

?>