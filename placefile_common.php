<?php
/*
 * This common script is shared among numerous placefile scripts and provides
 * functions that are used in several, if not all, placefile scripts.
 */

 // This is bad! But, also don't want to kill the execution of a script based on a
 // silly mistake I made! 
error_reporting(0);

// If not being called from the shell, is not GR Product, and the REQUEST['override'] isn't set,
// tell the user to copy the link and paste into GR. This prevents unwanted excessive downloads.
if (!isGRProduct() && !isset($_REQUEST['override'])) {
	die('This product is intended to be viewed in <a href="http://www.grlevelx.com/">Gibson Ridge Software</a> '
		.'(GR2Analyst or GRLevel3) or <a href="https://supercellwx.net">SupercellWX</a>. Please copy the link '
        .'and paste into your Product\'s PlaceFile Manager:'
		.'<br /><br /><a href="' . getFullURL() . '">' . getFullURL() .'</a>.');
}


/*
 * Generate the first few lines of the placefile based on details in the $data array.
 */
function generatePlacefileHeaders($data) {
	$hdr = ['Refresh','Threshold','Title','IconFile','Font'];
	foreach($hdr as $h) {
		if (is_array($data[$h])) {
			for ($i=0; $i<count($data[$h]); $i++) {
				$row = $h . ": " . ($i+1) . ",";
				for ($x=0; $x<count($data[$h][$i]); $x++) {
					if (!is_numeric($data[$h][$i][$x])) {
						$row .= '"' . $data[$h][$i][$x] .'",';
					} else {
						$row .= $data[$h][$i][$x] .',';
					}
				}
				$text .= substr($row,0,-1) . "\n";
			}
			//foreach($data[$h] as $row) {
			//	$text .= $h . ": \n";
			//}
		} else {
			$text .= $h . ": " . $data[$h] . "\n";
		}
	}
	return $text . "\n";
}

/*
 * Log each request to see where this script is being accessed
 */
function log_access() {
    // If being run in the shell, presume it's a test and bypass the logging
    if (php_sapi_name() == 'cli') {
        return;
    }

    if (!empty($_REQUEST['lat']) && !empty($_REQUEST['lat'])) {
        $radar_site = getRadarSite($_REQUEST['lat'], $_REQUEST['lon']);
    } else {
        $radar_site = '';
    }

    $time_stamp = date('Y-m-d H:i:s');
    $log = $time_stamp . '|' 
        . $_SERVER['REMOTE_ADDR'] . '|'
        . $_SERVER['SCRIPT_FILENAME'] . '|'
        . get_pf_options() . '|'
        . $radar_site . '|'
        . $_SERVER['HTTP_USER_AGENT'] . "\r\n";
    file_put_contents('./data/access.log', $log, FILE_APPEND);
}

/*
 * Use the Lat/Lng provided by the client to determine which radar site is being viewed (for stats/curiosity)
 */
function getRadarSite($lat, $lng) {
    $radar_sites = file_get_contents('./data/radar_sites.csv');
    preg_match('/(-?\d+\.\d{2})/', $lat, $lat1);
    preg_match('/(-?\d+\.\d{2})/', $lng, $lng1);
    if (preg_match('/(\w{4}),' . str_replace('.', '\.', $lat1[0]) . '\d+,' . str_replace('.', '\.', $lng1[0]) . '\d+/', $radar_sites, $radar_site)) {
        return strtoupper($radar_site[1]);
    }
    return $lat . ',' . $lng;
}

/*
 * Many scripts can have options to create a unique viewing experience, capture those here (for logging)
 */
function get_pf_options() {
	$ignore = ['lat','lon','version','dpi'];
	foreach($_REQUEST as $key=>$val) {
		if (!in_array($key, $ignore)) {
			$ret .= $key . '=' . $val . '&';
		}
	}
	if (!empty($ret)) {
		return substr($ret, 0, -1);
	} else {
		return null;
	}
}

/*
 * Check if the requesting software is reporting either GRLevelX or SuperCellWX
 */
function isGRProduct() {
    // If being run in the shell, presume it's a test and bypass this check
    if (php_sapi_name() == 'cli') {
        return true;
    }
	$grProducts = ['grlevel3', 'gr2analyst', 'supercellwx'];
	foreach ($grProducts as $grProduct) {
		if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), $grProduct) !== false) {
			return true;
		}
	}
	return false;
}

/*
 * Generates the URL for this placefile to be displayed for the user to copy into
 * placefile manager.
 */
function getFullURL() {
	// Program to display URL of current page. 
	
    $link = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    return $link;
}

/*
 * For debugging, display this placefile with some styling to help identify errors
 */
function displayAsPrettyPage($text) {
	
	$text = preg_replace("/\"(.*)\"/m", '<span class="pfString">$0</span>', $text);
	$text = preg_replace("/;(.*)$/m", '<span class="pfComment">$0</span>', $text);
	$text = preg_replace("/^(\\S*):/m", '<span class="pfCommand">$0</span>', $text);
	$text = str_replace("\n", "<br />", $text);
	?>
	
	<html>
	<head><title>Review Placefile Product</title>
	<style>
	.pfString {
		color:green;
	}
	.pfComment {
		color:gray;
	}
	.pfCommand {
		color:blue;
	}
	</style>
	</head>
	<body>
	<div style="font-family:courier new;font-size:10pt;"><?php echo $text; ?></div>
	</body>
	</html>
	<?php
}

?>