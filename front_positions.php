<?php
// The Surface Frontal Position (CODSUS) information is only updated by NWS about every 3 hours.
// To prevent continuously hitting the NWS FTP server for stale information, we download this
// file every 30 minutes. This was previously achieved through a crontab job, but I've added the
// functionality to this script so it's an all-in-one script.
//
// FTP URL:               ftp://tgftp.nws.noaa.gov/data/raw/as/asus02.kwbc.cod.sus.txt
// CODSUS Documentation:  https://www.wpc.ncep.noaa.gov/basicwx/read_coded_fcst_bull.shtml


/*
 **************************************************************************************************
 *** Placefile Configuration Options                                                            ***
 **************************************************************************************************
 */

/*
 * $RefreshMinutes
 *   Interval between Placefile refresh in Client. Since this data is only updated by NOAA ever
 *   4 hours, we're only choosing to update this every 10 minutes as a default.
 */
$RefreshMinutes = 10;

/*
 * $IconSize
 *   Icon size for the High / Low indicator
 *     1 = Smallest
 *     ...
 *     6  = Largest
 *   * Note: This can be included in the URL as part of the Query String
 */
$IconSize = $_REQUEST['IconSize'] ?? 6;

/*
 * $IconSet
 *   Icon set to use for High / Low indicator
 *     1 = Red/Blue 
 *     2 = Red/Cyan (a little more contrast)
 *   * Note: This can be included in the URL as part of the Query String
 */
$IconSet = $_REQUEST['IconSet'] ?? 1;

/* 
 * $MaxCodsusAge
 *   Maximum minutes to cache the CODSUS data (
 *     0 = get fresh copy every time
 *     30 = Recommended!
 * 
 * 	 To prevent constantly hitting NOAA FTP server for this data, 30 minutes is the 
 *   recommended cache time.
 */
$MaxCodsusAge = 30;

/*
 * $DeleteOld
 *   True = Delete old CODSUS data files from cache
 *   False = Retain ALL CODSUS data files downloaded from NOAA
 */
$DeleteOld = true;

/*
 * $ShowFileData
 *   True = Include each of the lines from the CODSUS Data File that generated the placefile item
 *          (Useful if there is an error, or unexpected result)
 *   False = Default - Keeps the file smaller
 */
$ShowFileData = false;

/*
 **************************************************************************************************
 *** Placefile Configuration Options                                                            ***
 **************************************************************************************************
 */




// Get the latest CODSUS data from NOAA or local cache (if within the MaxCodusAge option)
$codsus_data = getCodsusData();
if (!$codsus_data) {
	die('Unable to obtain CODSUS data for processing');
}

// Load common methods used in placefile
require_once('placefile_common.php');
$text = '';

// Begin processing the CODSUS data to generate the placefile
preg_match("/(\\d{2})(\\d{2})(\\d{2})Z/", $codsus_data[1], $t);
$utcTime = gmmktime($t[3], 0, 0, $t[1], $t[2], date("Y"));
$localTime = strtotime(substr(date("O"),0,3) . " hours", $utcTime);

// Establish the placefile headers
$data['Refresh'] = $RefreshMinutes;
$data['Threshold'] = 999;
$data['Title'] = "Surface Frontal Positions - {$t[3]}:00Z (" . date("g:ia T", $utcTime) . ") " . date("D M d", $utcTime);
$data['IconFile'] = [[40,40,21,21,"img/hi_lo.png"],[40,40,21,21,"img/hi_lo_light.png"]];
$data['Font'] = [[11,0,"Courier New"]];
$text = generatePlacefileHeaders($data);

// Process all fronts in the CODSUS data file
// The values in the array indicate the line colors and tool tip text for the placefile
$fronts = [ "COLD"=>	["Cold Front", "0 0 255"], 
			"WARM"=>	["Warm Front", "255 0 0"], 
			"STNRY"=>	["Stationary Front", "0 255 0"], 
			"OCFNT"=>	["Occluded Front", "255 0 255"], 
			"TROF"=>	["Trof", "255 153 51"]];
foreach($fronts as $front=>$vals) {
	preg_match_all("/({$front} )(-?\\d{6,7}\\s)*/m", $codsus_data[1], $m);
	$fName = $vals[0];
	$color = $vals[1];
	
	$text .= "\n; {$fName}s\n";
	for ($x=0; $x<count($m[0]); $x++) {
		if ($ShowFileData) {
			$text .= "; " . $m[0][$x];
		}
		$parts = explode(" ", str_replace("\n", " ", $m[0][$x]));
		
		$text .= "Color: {$color}\n";
		$text .= "Line: 6,0,\"{$fName} (" . date("D M d, g:ia T", $utcTime) . ")\"\n";
		
 		for ($i=1; $i<count($parts)-1; $i++) {
			if (strlen($parts[$i]) == 8) {
				$lat = number_format(substr($parts[$i],0,4) / 10, 1);
				$lng = number_format("-" . substr($parts[$i],4) / 10, 1);
			} else {
				$z = substr($parts[$i],0,1) == "-" ? 4 : 3;
				$lat = number_format(substr($parts[$i],0,$z) / 10, 1);
				$lng = number_format("-" . substr($parts[$i],$z) / 10, 1);
			}
			$text .= " {$lat},{$lng}\n";
		}
		$text .= "End:\n\n";
	}
}

// Process the Low & High Pressure Markers in the CODSUS data file
$text .= "; High and Low Pressure Markers\n";
$groups = ["LOW"=>$IconSize, "HIGH"=>$IconSize + 6];
foreach($groups as $key=>$val) {
	preg_match("/({$key}S )(\\d|\\s)*/m", $codsus_data[1], $m);		// Add a * before the /m in the match string
	if ($ShowFileData) {
		$text .= "; " . str_replace("\n", "\n;  ", $m[0]) . "\n";
	}
	$parts = explode(" ", str_replace("\n", " ", $m[0]));
	for ($i=1; $i<count($parts)-1; $i+=2) {
		$lat = substr($parts[$i+1],0,2) . "." . substr($parts[$i+1],2,1);
		$lng = "-" . substr($parts[$i+1],3,3) . "." . substr($parts[$i+1],6);
		
		if ($key == 'LOW') {
			$text .= "Object: {$lat},{$lng}\nIcon:0,0,000,{$IconSet},{$val},\"{$key} PRESSURE ({$parts[$i]} mb)\\n(" . date("D M d, g:ia T", $utcTime) . ")\"\nEnd:\n\n";
		} else {
			$text .= "Object: {$lat},{$lng}\nIcon:0,0,000,{$IconSet},{$val},\"{$key} PRESSURE ({$parts[$i]} mb)\\n(" . date("D M d, g:ia T", $utcTime) . ")\"\nEnd:\n\n";
		}
	}
}

// Provide some details on the times for this product
$text .= '; Server Product Time:......' . date("m/d/Y H:i:s T", $utcTime) . "\n";
$text .= '; Last downloaded from NWS:.' . $codsus_data[0]->format("m/d/Y H:i:s T") . "\n";

// Print the placefile as generated
if (isset($_REQUEST['review'])) {
	// Prettify the script
	displayAsPrettyPage($text);
} else {
	header("Content-Type: text/plain");
	echo $text;
}

// Capture the CODSUS Data File, either from local cache or from the NOAA FTP server
function getCodsusData() {
	global $MaxCodsusAge, $DeleteOld;

	// Create the data directory if it does not already exist
	if (!file_exists('./data')) {
		mkdir('./data', 644, true);
	}

	// Iterate through each file to determine the newest copy of the CODSUS file in cache
	// Files are in codsus_yyyyMMddHHmmss.txt format
	$currentTime = new DateTime();
	$files = scandir('./data', SCANDIR_SORT_DESCENDING);
	foreach($files as $file) {
		if ($file == '.' || $file == '..') {
			continue;
		}
		if (!preg_match('/\d{14}/', $file, $timeMatch)) {
			continue;
		}
		$fileTime = DateTime::createFromFormat('YmdHis', $timeMatch[0]);
		if (!$fileTime) {
			continue;
		}

		$timediff = $currentTime->diff($fileTime);
		$minutes = ($timediff->days * 24 * 60) + ($timediff->h * 60) + $timediff->i;
		if ($minutes < $MaxCodsusAge) {
			$codsus_data = file_get_contents('./data/' . $file);
			return [$fileTime, $codsus_data];
		}
		
		if ($DeleteOld) {
			unlink('./data/' . $file);
		}
	}

	// We did not have a current copy of the CODSUS file, download a fresh copy
	$codsus_data = file_get_contents('ftp://tgftp.nws.noaa.gov/data/raw/as/asus02.kwbc.cod.sus.txt');
	file_put_contents('./data/codsus_' . $currentTime->format('YmdHis') . '.txt', $codsus_data);
	return [$currentTime, $codsus_data];
}
?>
