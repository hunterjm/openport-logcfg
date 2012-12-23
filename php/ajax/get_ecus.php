<?php
// Set Logger Def
$defFile = '../misc/loggerdefs/' . $_POST['Definition'];
if(!file_exists($defFile)) {
	report_error('alert-error', 'Logger Definition could not be found');
}
$LoggerDef = simplexml_load_file($defFile);

// Find our ECU IDs
$ecuArray = $LoggerDef->xpath('//ecuparam/ecu/@id');
array_walk($ecuArray, function(&$value, $key) {
	$value = (string) $value;
});

// Get sorted, unique IDs
$ecuArray = array_unique($ecuArray);
sort($ecuArray);

// 500 on empty array
if(empty($ecuArray)) {
	header($_SERVER['SERVER_PROTOCOL'] . ' No ECUs found', true, 500);
	exit;
}

// Send it on back
echo json_encode($ecuArray);