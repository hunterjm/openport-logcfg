<?php
// Set Logger Def
$defFile = '../misc/loggerdefs/' . $_POST['Definition'];
if(!file_exists($defFile)) {
	echo "Wrong File: {$_POST['Definition']}";
// 	header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
// 	exit;
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
	echo "Empty Array";
// 	header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
// 	exit;
}

// Send it on back
echo json_encode($ecuArray);