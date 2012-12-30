<?php
// Set Logger Def
$defFile = '../../misc/loggerdefs/' . $_POST['Definition'];
if(!file_exists($defFile)) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
	echo "Invalid File: {$_POST['Definition']}";
	exit;
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
	header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
	echo "No ECU Parameters in Definition File";
	exit;
}

// Send it on back
echo json_encode($ecuArray);