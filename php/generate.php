<?php
// Save file to user's computer
header("Content-Type: text/plain; charset=utf-8");
header("Content-Disposition: attachment; filename=logcfg.txt");

$ecu = $_POST['ECUID'];
$ssmType = $_POST['Type'] == 'ssmcan' ? 'ssmcan' : 'ssmk';
$Profile = simplexml_load_file($_FILES['Profile']['tmp_name']);
$defFile = '../misc/loggerdefs/' . $_POST['Definition'];
if(file_exists($defFile)) {
	report_error('alert-error', 'Logger Definition could not be found');
}
$LoggerDef = simplexml_load_file($defFile);

// store selected parameters
$SelectedParams = $Profile->xpath("/profile/parameters/parameter[@livedata='selected']");
$selectedArray = array();
foreach($SelectedParams as $Parameter) {
	$selectedArray[(string)$Parameter['id']] = (string)$Parameter['units'];
}

/*
 * Making a HUGE assumption that all calculated parameters have higher IDs than
 * the parameters that are used in the equation.  In order to make the process
 * more simple, we will sort the selected parameters by their IDs.
 */
uksort($selectedArray, function($a, $b) {

	// 1st char determines if it is a regular or ECU Param (reg params come first)
	if($a[0] == 'P' && $b[0] == 'E')
		return -1;
	else if ($a[0] == 'E' && $b[0] == 'P')
		return 1;
	else {
		return strnatcmp($a, $b);
	}

});

// Parse Values into arrays
$defArray = array();
$hiddenDefArray = array();
$calculatedDefArray = array();

foreach($selectedArray as $id => $units) {

	// Lookup Definition
	$LoggerParam = $LoggerDef->xpath("//*[@id='{$id}']");
	$LoggerParam = current($LoggerParam);

	$LoggerConversion = $LoggerParam->xpath("conversions/conversion[@units='{$units}']");
	$LoggerConversion = current($LoggerConversion);

	// Store Name
	$defArray[$id]['paramname'] = str_replace(' ', '_', (string)$LoggerParam['name']) . '(' . $units . ')';
	
	// Parameter Type Dependancies
	$dependancyArray = array();
	if($id[0] == 'P') {

		// Check if there is an address
		if($LoggerParam->address)
			$defArray[$id]['paramid'] = (string)$LoggerParam->address;

		// Check the length of the address
		if($LoggerParam->address['length'])
			$defArray[$id]['databits'] = $LoggerParam->address['length']*8;

		// Check for dependancies
		if($LoggerParam->depends) {

			foreach($LoggerParam->depends->ref as $Reference) {
				
				// The dependant parameter
				$refID = (string)$Reference['parameter'];

				// The expression to be used for the calculated parameter
				$expression = (string)$LoggerConversion['expr'];
					
				// Try and find our units measurment in the expression
				preg_match('/\['.$refID.':([A-Za-z0-9]*)\]/', $expression, $matches);
				$refUnits = (isset($matches[1]) ? $matches[1] : null);

				// Check if the parameter is already included
				$dependantSelected = isset($selectedArray[$refID]);

				// If we don't have a matching selected parameter or our selected parameter's units do not match the equation
				if(!$dependantSelected || ($refUnits && $selectedArray[$refID] != $refUnits)) {

					// Lookup Definition for the Reference Parameter
					$ReferenceParam = $LoggerDef->xpath("//*[@id='{$refID}']");
					$ReferenceParam = current($ReferenceParam);

					if($refUnits) {
						$ReferenceConversion = $ReferenceParam->xpath("conversions/conversion[@units='{$refUnits}']");
						$ReferenceConversion = current($ReferenceConversion);
					} else {
						$ReferenceConversion = $ReferenceParam->conversions->conversion[0];
					}

					// Store Name
					$hiddenDefArray[$refID]['paramname'] = str_replace(' ', '_', (string)$ReferenceParam['name']) . '(' . $ReferenceConversion['units'] . ')';

					// Check if there is an address
					if($ReferenceParam->address)
						$hiddenDefArray[$refID]['paramid'] = (string)$ReferenceParam->address;

					// Check the length of the address
					if($ReferenceParam->address['length'])
						$hiddenDefArray[$refID]['databits'] = $ReferenceParam->address['length']*8;

					$hiddenDefArray[$refID]['scalingrpn'] = infix_to_postfix((string)$ReferenceConversion['expr']);

					$dependancyArray[$refID] = $hiddenDefArray[$refID]['paramname'];

				} else {

					// Save dependancy name
					$dependancyArray[$refID] = $defArray[$refID]['paramname'];

				}

			}
		}

	}

	if($id[0] == 'E') {

		$EcuAddress = $LoggerParam->xpath("ecu[@id='{$ecu}']");
		$EcuAddress = current($EcuAddress);

		// Check if there is an address
		if($EcuAddress->address)
			$defArray[$id]['paramid'] = (string)$EcuAddress->address;

		// Check the length of the address
		if((string)$LoggerConversion['storagetype'] == 'float')
			$defArray[$id]['isfloat'] = 1;
		else if($EcuAddress->address['length'])
			$defArray[$id]['databits'] = $EcuAddress->address['length']*8;

	}

	// Set our Scaling in Reverse Polish Notation (with commas seperating data and operators)
	$defArray[$id]['scalingrpn'] = infix_to_postfix((string)$LoggerConversion['expr']);

	// Check to see if we have to replace variables
	if(!empty($dependancyArray)) {
		
		// Loop through replacements and make them
		foreach($dependancyArray as $refID => $refName) {
			$defArray[$id]['scalingrpn'] = preg_replace('/(\[){0,1}' . $refID . '(:[A-Za-z0-9]+\]){0,1}/', $refName, $defArray[$id]['scalingrpn']);
		}

		// Move it to the calculated definitions array
		$calculatedDefArray[$id] = $defArray[$id];
		unset($defArray[$id]);
		
	}

}

// Begin Output
echo <<<EOT
;------------------------------ABOUT------------------------------;
; This config file was generated by comparing a RomRaider Logger  ;
; Profile XML against the RomRaider Logger Definition XML.        ;
; Depending on the parameters selected, it will enable logging    ;
; via either CAN or K-Line.                                       ;
;                                                                 ;
; @author Jason Hunter                                            ;
; @since  December 22, 2012                                       ;
; @see    http://subaru.hunterjm.com/                             ;
;-----------------------------------------------------------------;

EOT;

echo 'type = ' . $ssmType . "\n\n";

echo <<<EOT
;--------------------LOGGED PARAMETERS--------------------;
; These parameters were selected in the RomRaider Logging ;
; profile.  They will appear in your CSV log outputs on   ;
; the SD card in your Tactrix cable.  Keep in mind that   ;
; unlike RomRaider Logger, the headings will have an      ;
; underscore (_) instead of a space ( ).                  ;
;---------------------------------------------------------;

EOT;
foreach($defArray as $id => $data) {

	// Display a comment about the param
	echo ';' . $id . ' - ' . str_replace('_', ' ', $data['paramname']) . "\n";

	// Print out Logger Values
	foreach($data as $key => $value) {

		echo "{$key} = {$value}\n";

	}

	echo "\n";

}

echo <<<EOT
;----------------HIDDEN/TRIGGER PARAMETERS----------------;
; These parameters will not show up in your logs.  They   ;
; are required in order to create the calculated params   ;
; you have included below or to be used in triggers.      ;
;---------------------------------------------------------;

EOT;
foreach($hiddenDefArray as $id => $data) {

	// Display a comment about the param
	echo ';' . $id . ' - ' . str_replace('_', ' ', $data['paramname']) . "\n";

	// Print out Logger Values
	foreach($data as $key => $value) {

		echo "{$key} = {$value}\n";

	}

	echo "isvisible = 0\n\n";

}

echo <<<EOT
; Defogger Switch Trigger
paramname = defogger_trigger
paramid = 0x64
databits = 1
offsetbits = 5
isvisible = 0

;------------------CALCULATED PARAMETERS------------------;
; These parameters are calculated from the parameters     ;
; you have included above.  They will appear in your CSV  ;
; log outputs on the SD card in your Tactrix cable.  Keep ;
; in mind that unlike RomRaider Logger, the headings will ;
; have an underscore (_) instead of a space ( ).          ;
;---------------------------------------------------------;

EOT;

// Don't change the type unless we actually have calculated parameters
if(!empty($calculatedDefArray))
	echo "type = calc\n\n";

foreach($calculatedDefArray as $id => $data) {

	// Display a comment about the param
	echo ';' . $id . ' - ' . str_replace('_', ' ', $data['paramname']) . "\n";

	// Print out Logger Values
	foreach($data as $key => $value) {

		echo "{$key} = {$value}\n";

	}

	echo "\n";

}

// Start/Stop Logging
echo <<<EOT
;-------------------------TRIGGER-------------------------;
; Triggers allow us to start/stop and resume logging to   ;
; a file.  A default configuration has been setup for     ;
; you to start with.  This will start logging when the    ;
; engine starts (RPM > 0).                                ;
;                                                         ;
; A second configuration is also available.  It will log  ;
; when you press the defogger switch and will stop the    ;
; log when you turn the defogger off.  In order to use    ;
; that configuration, remove the ";" at the beginning of  ;
; the "Defogger Switch" lines and add a ";" to the others ;
;---------------------------------------------------------;

; CONFIG 1

conditionrpn = Engine_Speed(rpm),0,>	
action = start

conditionrpn = Engine_Speed(rpm),0,==
action = stop

; CONFIG 2

; Start log when defogger_trigger == 1
;conditionrpn = defogger_trigger,1,==
;action = start

; Stop log when defogger_trigger == 0
;conditionrpn = defogger_trigger,0,==
;action = stop
EOT;

function is_operand($who) {
	return((!is_operator($who) && ($who!="(") && ($who!=")"))? true : false);
}

function is_operator($who) {
	return(($who=="+" || $who=="-" || $who=="*" || $who=="/" || $who=="^")? true : false);
}

/* Check for Precedence */
function prcd($who) {
	if($who=="^")
		return(5);
	if(($who=="*")||($who=="/"))
		return(4);
	if(($who=="+")||($who=="-"))
		return(3);
	if($who=="(")
		return(2);
	if($who==")")
		return(1);
}

function infix_to_postfix($infixStr) {

	$postfixStr = array();
	$stackArr = array();
	
	for($i = 0; $i < strlen($infixStr); $i++) {
		if(is_operand($infixStr[$i])) {
			$postfixStr[]=$infixStr[$i];
		}
		if(is_operator($infixStr[$i])) {
			$postfixStr[]=',';
			if($infixStr[$i]!="^") {
				while((!empty($stackArr)) && (prcd($infixStr[$i])<=prcd($stackArr[count($stackArr) - 1]))) {
					$postfixStr[]=array_pop($stackArr);
					$postfixStr[]=',';
				}
			}
			else {
				while((!empty($stackArr)) && (prcd($infixStr[$i])<prcd($stackArr[count($stackArr) - 1]))) {
					$postfixStr[]=array_pop($stackArr);
					$postfixStr[]=',';
				}
			}
			array_push($stackArr,$infixStr[$i]);
		}
		if($infixStr[$i]=="(") {
			array_push($stackArr,$infixStr[$i]);
		}
		if($infixStr[$i]==")") {
			while($stackArr[count($stackArr) - 1]!="(") {
				$postfixStr[]=',';
				$postfixStr[]=array_pop($stackArr);
			}
			array_pop($stackArr);
		}
	}

	while(!empty($stackArr)) {
		if($stackArr[count($stackArr) - 1]=="(") {
			array_pop($stackArr);
		}
		else {
			$postfixStr[]=',';
			$postfixStr[]=array_pop($stackArr);
		}
	}

	return implode('', $postfixStr);

}

function report_error($type, $msg) {
	$errorRef = '/?alert[type]=' . urlencode($type) . '&alert[msg]=' . urlencode($msg));
	header('Location: ' . $errorRef);
}