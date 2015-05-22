<?php

include 'config.php';

// default values

$charId 		= isset($charId) ? $charId : null;
$allianceID 	= isset($allianceID) ? $allianceID : null;
$corporationID 	= isset($corporationID) ? $corporationID : null;

$trackedAlliance = isset($trackedAlliance) ? $trackedAlliance : null;


$mysqli = new mysqli($mysql_hostname, $mysql_username, $mysql_password, $mysql_database);

if (!defined('_FILE_URL_') && (basename(__FILE__) == 'test.php')) {
	define('_FILE_URL_', 'test.php');
} else if(!defined('_FILE_URL_')) {
	define('_FILE_URL_', '');
} 

define('CDN_JS', '//80.79.115.132/~bs1942/cdn'); // '//cdn.kassikas.net');
define('CDN_IMG', 'http://images.cdn1.eveonline.com/'); // '//80.79.115.132/~bs1942/cdn/eveimg'); // '//cdn.kassikas.net/eveimg');

/*

CREATE TABLE mapSolarSystems_beltinfo AS 
SELECT m.solarSystemID, m.solarSystemName, IFNULL(b.belts, 0) belts, IFNULL(i.is_ice, 0) is_ice
FROM mapSolarSystems m
LEFT JOIN ( SELECT `solarSystemID`, COUNT(`itemID`) belts
FROM `eve_mapDenormalize` 
WHERE `itemName` LIKE '%- Asteroid Belt%'
GROUP BY `solarSystemID` ) AS b ON b.solarSystemID = m.solarSystemID
LEFT JOIN ( SELECT DISTINCT `solarSystemID`, 1 as is_ice
FROM `eve_mapDenormalize` 
WHERE `itemName` LIKE '%- Ice Field%' ) AS i ON i.solarSystemID = m.solarSystemID

*/

if ((!isset($show_refinerys) || !$show_refinerys) && !defined('_NO_REF')) {
	define('_NO_REF', true);
}

// disable refinerys if _NO_REF
if (defined('_NO_REF') && !empty($_GET['ref'])) { unset($_GET['ref']); }

$proto = 'http';
if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) {
	// apache + variants specific way of checking for https
	$proto = 'https';
} elseif (isset($_SERVER['HTTP_HTTPS']) && $_SERVER['HTTP_HTTPS'] == 'on') {
	// delfi varnish
	$proto = 'https';
} elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') {
	// nginx way of checking for https
	$proto = 'https';
}


$PAGE_URL_BASE = trim($proto . '://' . $_SERVER['SERVER_NAME'], '/') . '/';

$r_uri_parts = parse_url($_SERVER['REQUEST_URI']);
if (isset($r_uri_parts['path'])) {
	$PAGE_URL_BASE .=  trim($r_uri_parts['path'], '/');
}


if (!empty($_GET['osdx'])) {
	
header('Content-Type: application/opensearchdescription+xml;');

echo '<?' . 'xml version="1.0"?' . '>'; ?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
<ShortName>NAH</ShortName>
<Description>NAH Search</Description>
<Image height="16" width="16" type="image/png">http://upload.kassikas.net/favicon_FAXVYU.png</Image>
<Url type="text/html" method="get" template="<?php echo $PAGE_URL_BASE; ?>?filter={searchTerms}"/>"/>
</OpenSearchDescription><?php
	
	exit;
}

function print_r2($data) {
	echo '<pre>';
	print_r($data);
	echo '</pre>';
}

if (!function_exists('fetch_url')) {
	function fetch_url($url) {
		if( function_exists('curl_init') ) {
			$ch = curl_init();
			$timeout = 5;
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:31.0) Gecko/20100101 Firefox/31.0');
			
			
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			
			$data = curl_exec($ch);
			curl_close($ch);

			if(empty($data)) {
				return file_get_contents($url);
			}
			
			return $data;
		 } else {
			return file_get_contents($url);
		 }
	
	}
}

// lets use stangins page api cache
function getXML( $url , $cacheTime_h = 2 , $output_xml = false, $getString = false ) {
#	$x = 'http://portal.cultofwar.org/standings/';	
	//$x = 'http://ee.kassikas.net/standings/';
	$x = 'http://80.79.115.132/~bs1942/ee/standings/';
	$x .= '?getapi='.rawurlencode( $url );
	$x .= '&cache='.$cacheTime_h;
	
#	print '<a href="'.$x.'">'.urldecode($x).'</a>';
	
	$xmlSource = fetch_url($x); //file_get_contents($x);
	
	if(  $output_xml ) {	
		header ("content-type: text/xml");
		print $xmlSource;
		exit;
	}

	if ($getString) {
		return $xmlSource;
	}
	
	return simplexml_load_string($xmlSource);
}

function cleanName($str, $lower = false, $html = true) {
	$str = str_replace(' ', '_', trim($str));	
	if ($lower) {
		$str = strtolower($str);
	}
	if ($html) {
		$str = htmlspecialchars($str);
	}
	return $str;
}

function roman2dec( $roman ) {
    $numbers = array(
        'I' => 1,
        'V' => 5,
        'X' => 10,
        'L' => 50,
        'C' => 100,
        'D' => 500,
        'M' => 1000,
    );

    $roman = strtoupper( $roman );
    $length = strlen( $roman );
    $counter = 0;
    $dec = 0;
    while ( $counter < $length ) {
        if ( ( $counter + 1 < $length ) && ( $numbers[$roman[$counter]] < $numbers[$roman[$counter + 1]] ) ) {
            $dec += $numbers[$roman[$counter + 1]] - $numbers[$roman[$counter]];
            $counter += 2;
        } else {
            $dec += $numbers[$roman[$counter]];
            $counter++;
        }
    }
    return $dec;
}


if (isset($_GET['update_google'])) {
	$pages = array(13,12,8,15,6,7,5,3,4,14,18,2,1,9);
	
	$url = 'https://docs.google.com/spreadsheet/ccc?key=0AhtRscfsT4TFdGxsUmZZMktDZWpGR0xPazkyaHlTelE&output=csv&gid=8';
	
#	echo $url . "<br>";
#	$str = fetch_url($url);
#	print_r2($str);
#	exit;
}


$EVE_MINERALS = array(
	'34' => array('s' => 'Trit',	'l' => 'Tritanium'),
	'35' => array('s' => 'Pye',		'l' => 'Pyerite'), 
	'36' => array('s' => 'Mex',		'l' => 'Mexallon'),
	'37' => array('s' => 'Iso',		'l' => 'Isogen'),
	'38' => array('s' => 'Nox', 	'l' => 'Noxcium'),
	'39' => array('s' => 'Zyd', 	'l' => 'Zydrine'),
	'40' => array('s' => 'Mega', 	'l' => 'Megacyte'),
	
	'11399' => array('s' => 'Morp', 	'l' => 'Morphite'),

	'17887' => array('s' => 'Oxy', 	'l' => 'Oxygen Isotopes'),	
	'17889' => array('s' => 'Hyd', 	'l' => 'Hydrogen Isotopes'),
	'16274' => array('s' => 'Hel', 	'l' => 'Helium Isotopes'),
	'17888' => array('s' => 'Nit', 	'l' => 'Nitrogen Isotopes'),
	
	'16272' => array('s' => 'HWater', 	'l' => 'Heavy Water'),
	'16273' => array('s' => 'Ozone', 	'l' => 'Liquid Ozone'),
	'16275' => array('s' => 'Stront', 	'l' => 'Strontium Clathrates'),	

	
);

$auth = '?keyID=' . $keyId . '&vCode=' . $vCode;

$allianceID 	= empty($allianceID) 	? false : $allianceID;
$corporationID 	= empty($corporationID) ? false : $corporationID;
if (!$allianceID || !$corporationID) {
	$corpInfo = getXML('/corp/CorporationSheet.xml.aspx' . $auth, 24*7);

	if (empty($corpInfo->result->allianceID)) {
		$corpInfo = getXML('/corp/CorporationSheet.xml.aspx' . $auth, 1);
	}
	
	$corporationID = (string)$corpInfo->result->corporationID;
	if (empty($corpInfo->result->allianceID)) {
		exit("ERROR: corp not in alliance [name: " . $corpInfo->result->corporationName . " | id:" . $corporationID . "]");
	}
	
	$allianceID = (string)$corpInfo->result->allianceID;
	
}

$charId = empty($charId) 	? false : $charId;
if (!$charId) {
	$apiInfo = getXML("/account/APIKeyInfo.xml.aspx" . $auth, 24 * 7);
	$rrow = (array)$apiInfo->result->key->rowset->row;
	
	$charId = (string)$rrow['@attributes']['characterID'];
}




$auth .= '&characterID=' . $charId;


#$auth_not = '?keyID=' . $keyId_not . '&vCode=' . $vCode_not . '&characterID=' . $charId;

$url_locations = '/corp/Locations.xml.aspx' . $auth;






$sov = getXML('/map/Sovereignty.xml.aspx', 7);
$assets = getXML('/corp/AssetList.xml.aspx' . $auth, 7);
$OutpostList = getXML('/corp/OutpostList.xml.aspx' . $auth, 7);
$ConquerableStationList = getXML('/eve/ConquerableStationList.xml.aspx', 7);

$allianceList = getXML('/eve/AllianceList.xml.aspx?version=1', 7);

foreach ( $allianceList->result->rowset->row as $ally ) {
	$ally = (array)$ally;
	$ally = $ally['@attributes'];
	
	if ($ally['allianceID'] == $allianceID) {
		$trackedAlliance = trim($ally['name']) . ' [' . trim($ally['shortName']) . ']';
	}
}

/*

$list_notices = getXML('/char/Notifications.xml.aspx' . $auth_not, 10);

$typeToTrack = array(
		45 => 'Alliance anchoring alert',
		46 => 'Alliance structure turns vulnerable',
		48 => 'Sovereignty disruptor anchored',
		80 => 'Station aggression message',
		88 => 'Infrastructure Hub under attack',
		78 => 'Station state change message',

);

$notificationIDs = array();
foreach ($list_notices->result->rowset->row as $row) {
	$row = (array)$row; 	$row = $row['@attributes'];
	$typeID = (int)$row['typeID'];
	
	if ($typeID == 10 || $typeID == 13) continue; // bill
	if ($typeID == 77) continue; // Station service aggression message
	
	
	if (isset($typeToTrack[$typeID])) {
		$notificationIDs[] = $row['notificationID'];
		continue;
	}
	
#	print_r2($row);
}

print_r2($list_notices);

$url_notic_texts = '/char/NotificationTexts.xml.aspx' . $auth_not . '&IDs=' . join(',', $notificationIDs); 

$url_notic_texts = '/char/NotificationTexts.xml.aspx' . $auth_not . '&IDs=' . '484537041'; // join(',', $notificationIDs); 

var_dump( $url_notic_texts );

$notice_texts =  getXML($url_notic_texts, 10);

print_r2( $notificationIDs );

print_r2( $notice_texts );

#print_r2( $url_notices );

*/

$system_indy_index = false;

if (isset($_GET['indy'])) {
	$system_indy_index = array();

	$str = fetch_url('http://public-crest.eveonline.com/industry/systems/');
	$indySystems = json_decode($str, true);
	
	foreach ($indySystems['items'] as $indySys) {
		#print_r2($indySys); exit;
	
		#$system_id = $indySys['solarSystem']['id'];
		$indexs = array();
		foreach ($indySys['systemCostIndices'] as $index) {
			$indexs[cleanName($index['activityName'], true)] = $index['costIndex'];
		}
		
	#	$system_indy_index[$indySys['solarSystem']['id']] = $indexs;
		$system_indy_index[$indySys['solarSystem']['name']] = $indexs;
	}
	
#	print_r2($system_indy_index);	exit;

	#foreach ($indySystems)

}


#print_r2( $assets ); exit;

$stations = array();
#$tnm = array();
foreach ($OutpostList->result->rowset->row as $stat) {
	$stat = (array)$stat;
	$stat = $stat['@attributes'];

	$stations[$stat['solarSystemID']] = $stat;
}

$station_to_system = array();
foreach ($ConquerableStationList->result->rowset->row as $conq) {
	$conq = (array)$conq;
	$conq = $conq['@attributes'];

	if (!isset($stations[$conq['solarSystemID']])) {
		$stations[$conq['solarSystemID']] = $conq;
	}
	
	$station_to_system[$conq['stationID']] = $conq['solarSystemID'];

}

$stationTypes = array(
    '12242' => 'Conqrb.1',
    '12294' => 'Conqrb.2',
    '12295' => 'Conqrb.3',
    '21646' => 'Minmatar',
    '21644' => 'Amarr',
    '21645' => 'Gallente',
    '21642' => 'Caldari',
);

$NA_systems = array();
$NAH_systems = array();

$NA_private_sov = array();
$SOV_system_to_corp = array();
foreach ($sov->result->rowset->row as $sys) {
	$sys = (array)$sys;
	$sys = $sys['@attributes'];
	
	$SOV_system_to_corp[$sys['solarSystemID']] = $sys['corporationID'];
	
	if ($sys['allianceID'] != $allianceID) continue;
	$NA_systems[$sys['solarSystemID']] = $sys['solarSystemID'];
	
	if ($sys['corporationID'] != $corporationID) {
		$NA_private_sov[$sys['corporationID']] = null;
		continue;
	}
	$NAH_systems[$sys['solarSystemID']] = $sys['solarSystemID'];
	
	#print_r2($sys);
}

#print_r2($NA_private_sov);

foreach($NA_private_sov as $corpId => $tmp) {
	$corpInfo = getXML('/corp/CorporationSheet.xml.aspx?corporationID=' . $corpId , rand(5000, 9999));
	
#	print_r2( $corpInfo );
	
#	var_dump( (string)$corpInfo->result->corporationName );
#	var_dump( (string)$corpInfo->result->ticker );
	
	if (!empty($corpInfo->result->corporationName) && !empty($corpInfo->result->ticker)) {
		$NA_private_sov[$corpId] = array(
			'name' => (string)$corpInfo->result->corporationName,
			'ticker'=>(string)$corpInfo->result->ticker,
		);
	}
	
#	exit;
}

#print_r2($NA_private_sov);
#exit;

$Ore_Prospecting = array(
	2040 => 1, // Ore Prospecting Array 1
	2041 => 2, // Ore Prospecting Array 2
	2042 => 3, // Ore Prospecting Array 3
	2043 => 4, // Ore Prospecting Array 4
	2044 => 5, // Ore Prospecting Array 5
);

$Survey_Networks = array(
	2053 => 1, // Survey Networks 1
	2054 => 2, // Survey Networks 2
	2055 => 3, // Survey Networks 3
	2056 => 4, // Survey Networks 4
	2057 => 5, // Survey Networks 5
);

$Pirate_Detection = array(
	2026 => 1, // Pirate Detection Array 1
	2027 => 2, // Pirate Detection Array 2
	2028 => 3, // Pirate Detection Array 3
	2029 => 4, // Pirate Detection Array 4
	2030 => 5, // Pirate Detection Array 5
);

$Entrapment = array(
	2031 => 1, // Entrapment Array 1
	2034 => 2, // Entrapment Array 2
	2035 => 3, // Entrapment Array 3
	2036 => 4, // Entrapment Array 4
	2037 => 5, // Entrapment Array 5
);

$Quantum_Flux  = array(
	2058 => 1, // Quantum Flux Generator 1
	2059 => 2, // Quantum Flux Generator 2
	2060 => 3, // Quantum Flux Generator 3
	2061 => 4, // Quantum Flux Generator 4
	2062 => 5, // Quantum Flux Generator 5
);



$ihubs = array();
$ihub_items = array();
$err_dobule = array();

$minerals = array();
$itemPrices = array();

#print_r2($assets);

foreach ($assets->result->rowset->row as $listId => $row) {
	$row = (array)$row;
	

	
// STATIONs
// MINERALs
	
	if (empty($row['@attributes']['typeID']) || empty($row['@attributes']['locationID']) || ($row['@attributes']['typeID'] != 32458)) {
	
		// its missing stuff that inbound (locked)
		// refinerys with no office
					
		if (isset($row['@attributes']['typeID']) && isset($row['@attributes']['locationID'])) {
			$itemID = $row['@attributes']['itemID'];
			$typeID = $row['@attributes']['typeID'];
			$locationID = $row['@attributes']['locationID'];

			if ($typeID == 27 && isset($row['rowset']->row) ) { // office
			
				$stationId = false;
				
				$start2 = substr((string)$locationID, 0, 2);

				if ($start2 == "66") {
					$stationId = ($locationID-6000001);
				}
				
				if ($start2 == "67") {
					$stationId = ($locationID-6000000);
				}
				
				if ($stationId && isset($station_to_system[$stationId])) {

					$systemId = $station_to_system[$stationId];

					foreach  ( $row['rowset']->row as $r2 ) {
						$r2 = (array)$r2;

						if (isset($r2['@attributes']['quantity'])) {
				
						
							$typeID2 = $r2['@attributes']['typeID'];
							$quantity2 = $r2['@attributes']['quantity'];
							
							if (!isset($minerals[$systemId])) {
								$minerals[$systemId] = array();
							}
					
							if (!isset($minerals[$systemId][$typeID2])) {
								$minerals[$systemId][$typeID2] = 0;
							}
							
							$minerals[$systemId][$typeID2] += $quantity2;
							
							$itemInfo[$typeID2] = null;
						}
					}
				}
			}
		}
	
		continue;
	}
	
	
	$locationID = $row['@attributes']['locationID'];
	$itemID = $row['@attributes']['itemID'];
	$typeID = $row['@attributes']['typeID'];
	
// IHUB
	
	if ($typeID && ($typeID == 32458)) {

		$ihub_items[$itemID] = $locationID;
		
		if (isset($ihubs[$locationID])) {
			$err_dobule[$locationID] = 1;
			continue;
		}
		
		$sys = array(
			'ore' => 0,
			'survery' => 0,
		
			'pirate' => 0,
			'entrapment' => 0,
			'quantumflux' => 0,
			
			'jb' => 0,
			'beacon' => 0,
			'jammer' => 0,
			'supercap' => 0,
			
			'itemID' => $itemID,
		);
		
		if (!empty($row['rowset'])) {
			$row2 = (array)$row['rowset'];
			
			if (!empty($row2['row'])) {
			
				// ihub has only single upgrade
				if (!is_array($row2['row']) && is_object($row2['row'])) {
					$row2['row'] = array($row2['row']);
				}
			
				foreach ($row2['row'] as $_upgrade) {
				
					$upgrade = (array)$_upgrade;
					$typeID = $upgrade['@attributes']['typeID'];

					
					if ($typeID == 32422) {
						$sys['jb'] = 1; 
						continue;
					}
					
					if ($typeID == 2008) {
						$sys['beacon'] = 1; 
						continue;
					}
					
					if ($typeID == 2001) {
						$sys['jammer'] = 1; 
						continue;
					}
					
					if ($typeID == 2009) {
						$sys['supercap'] = 1; 
						continue;
					}
					
					
					if (isset($Ore_Prospecting[$typeID])) {
						$sys['ore'] = max($sys['ore'], $Ore_Prospecting[$typeID]);
						continue;
					}
					
					if (isset($Survey_Networks[$typeID])) {
						$sys['survery'] = max($sys['survery'], $Survey_Networks[$typeID]);
						continue;
					}
					
					
					
					if (isset($Pirate_Detection[$typeID])) {
						$sys['pirate'] = max($sys['pirate'], $Pirate_Detection[$typeID]);
						continue;
					}
					
					if (isset($Entrapment[$typeID])) {
						$sys['entrapment'] = max($sys['entrapment'], $Entrapment[$typeID]);
						continue;
					}
					
					if (isset($Quantum_Flux[$typeID])) {
						$sys['quantumflux'] = max($sys['quantumflux'], $Quantum_Flux[$typeID]);
						continue;
					}
					
					#print_r2( $typeID  );
				}
			}
		}
		
		#print_r2($sys);
		
		$ihubs[(int)$locationID] = $sys;
		
	#	$upgrades = !empty($row['rowset']) && !empty($row['rowset']->row) ? $row['rowset']->row : array();

	#	print_r2( $upgrades );
		
		#exit;
	
	}
	
}

#print_r2( $minerals );

IF (!empty($itemInfo) && count($itemInfo) > 0) {

	$str = "SELECT e.typeID, e.buy, e.sell ".
			" , i.typeName  ".
		" FROM  evecentral e ".
		" LEFT JOIN crucible_invtypes i ON i.typeID = e.typeID ".
		" WHERE e.typeID IN ('" . implode("', '", array_keys($itemInfo)) . "') "; 
		
	#echo $str;
	
	$result = $mysqli->query($str);
	while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
		#print_r2($row); exit;
		$itemInfo[$row['typeID']] = $row;
	}
	
}


$systems = $regions = array();

$str = "SELECT s.solarSystemID, s.solarSystemName, s.security, s.factionID, s.sunTypeID ".
	" ,c.constellationName, r.regionName ".
	" , h.itemID as ihub_id, h.closest as ihub_loc ".
	" , belt.belts , belt.is_ice ".
	" FROM mapSolarSystems s ".
	" INNER JOIN mapConstellations c ON c.constellationID = s.constellationID ".
	" INNER JOIN mapRegions r on r.regionID = s.regionID ".
	" LEFT JOIN nah_ihubs h ON h.sys_id = s.solarSystemID ".
	" LEFT JOIN mapSolarSystems_beltinfo belt ON belt.solarSystemID = s.solarSystemID ".
	" WHERE s.solarSystemID IN ('" . implode("', '", array_keys($NA_systems)) . "') ".
	" ORDER BY r.regionName, c.constellationName, s.solarSystemName ";


	
$location_ret_cnt = $loc_cnt_run = 0; 
$result = $mysqli->query($str);
while ($system = $result->fetch_array(MYSQLI_ASSOC)) {
	if (!empty($system['ihub_loc']) && empty($ihub_items[$system['ihub_id']])) {
		$system['ihub_loc'] = $system['ihub_id'] = '';
	}

	$ihub = isset($ihubs[$system['solarSystemID']]) ? $ihubs[$system['solarSystemID']] : false;
	if (empty($system['ihub_loc']) && !empty($ihub) && (++$location_ret_cnt <= 5)) {
		
#		print_r2( $ihub );
#		print_r2( $system );
		
		if ($loc_cnt_run > 0) {
			// wait for 0.1 seconds
			usleep(100000);
			//sleep(1);
		}
		
		
		$loc = getXML($url_locations . '&IDs=' . $ihub['itemID'] , rand(48, 96));
	#	echo $url_locations . '&IDs=' . $ihub['itemID'];
#		print_r2($loc);
		if (isset($loc->result->rowset->row)) {
			$rowset = (array)$loc->result->rowset;
			if (!empty($rowset['row'])) {
				$row = (array)$rowset['row'];
				if (!empty($row['@attributes'])) {
					$attr = $row['@attributes'];
					
					if (!empty($attr['x']) && !empty($attr['y']) && !empty($attr['z'])) {
						
						$nearst = "https://www.fuzzwork.co.uk/api/nearestCelestial.php".
							"?x=" . $attr['x'] . "&y=" . $attr['y'] . "&z=" . $attr['z'] . "&solarsystemid=" . $system['solarSystemID'];
							
#						echo $nearst;
							
						$nearst_str = fetch_url($nearst);
						$json = json_decode( $nearst_str );
						if (!empty($json->itemName)) {
							$system['ihub_loc'] = $json->itemName;
							
							$str = "INSERT INTO `nah_ihubs` (`itemID`, `closest`, `x`, `y`, `z`, `sys_id`) VALUES ".
								" ('" . $mysqli->escape_string($ihub['itemID']) .  "', 
									'" . $mysqli->escape_string($json->itemName) .  "',
									'" . $mysqli->escape_string($attr['x']) .  "',
									'" . $mysqli->escape_string($attr['y']) .  "',
									'" . $mysqli->escape_string($attr['z']) .  "',
									'" . $mysqli->escape_string($system['solarSystemID']) .  "'
								) ON DUPLICATE KEY UPDATE `itemID` = '" . $mysqli->escape_string($ihub['itemID']) .  "', " .
								" `closest` = '" . $mysqli->escape_string($json->itemName) .  "', ".
								" `x` = '" . $mysqli->escape_string($attr['x']) .  "', ".
								" `y` = '" . $mysqli->escape_string($attr['y']) .  "', ".
								" `z` = '" . $mysqli->escape_string($attr['z']) .  "'; ";
								

#							echo ""$str;
							
							$mysqli->query($str);
							$loc_cnt_run++;
						}
						#var_dump( $nearst_str );
						#print_r2( $json );
					}
				}
			}
			
		}
		
#		exit;
	}

	$system['faction'] = '';
	if (in_array($system['regionName'], array('Detorid', 'Feythabolis', 'Immensea', 'Impass', 'Insmother', 'Omist', 'Scalding Pass', 'Tenerifis', 'Wicked Creek', 'Cache'))) {
		$system['faction'] = 'Angel';
	} else if (in_array($system['regionName'], array('Etherium Reach', 'Oasa', 'The Kalevala Expanse', 'The Spire', 'Cobalt Edge', 'Outer Passage', 'Perrigen Falls', 'Malpais'))) {
		$system['faction'] = 'Drones';
	}  else if (in_array($system['regionName'], array('Esoteria', 'Paragon Soul', 'Providence', 'Catch'))) {
		$system['faction'] = 'Sanshas';
	}  else if (in_array($system['regionName'], array('Period Basis', 'Querious', 'Delve'))) {
		$system['faction'] = 'Dark-Blood';
	}  else if (in_array($system['regionName'], array('Geminate'))) {
		$system['faction'] = 'Guristas';
	}
	
	$systems[$system['solarSystemID']] = $system;
	if (!isset($regions[$system['regionName']])) {
		$regions[$system['regionName']] = 0;
	}
	$regions[$system['regionName']]++;
	
}
$result->free();

#print_r2($systems);

$mysqli->close();
header('Content-Type: text/html; charset=utf-8');

$show_ref = !empty($_GET['ref']);
$show_renters = !empty($_GET['rent']);

?><!DOCTYPE html>
<html>
	<head>
		<title>NAH</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="icon" type="image/png" href="http://upload.kassikas.net/favicon_FAXVYU.png" />
		<link rel="search" type="application/opensearchdescription+xml" title="NAH Search" href="?osdx=/search.osdx">
		<link href="<?php echo htmlspecialchars(CDN_JS); ?>/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
		
		<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
		
		<style>

			table.tablesorter thead tr th, table.tablesorter tfoot tr th, table.tablesorter tfoot td {
				background-color: #e6EEEE;
			/*	border: 1px solid #FFF; */
				padding: 4px 18px 4px 4px;
			}

			table.tablesorter thead tr .header {
				background-image: url(<?php echo htmlspecialchars(CDN_JS); ?>/jquery.tablesorter/themes/blue/bg.gif);
				background-repeat: no-repeat;
				background-position: center right;
				cursor: pointer;
			}
			table.tablesorter tbody td,
			table.tablesorter tfoot td	{
				color: #3D3D3D;
				padding: 4px;
				background-color: #FFF;
				vertical-align: top;
			}
			table.tablesorter tfoot td {
				background-color: #BCF5A9;
			}
			table.tablesorter thead tr .headerSortUp {
				background-image: url(<?php echo htmlspecialchars(CDN_JS); ?>/jquery.tablesorter/themes/blue/asc.gif);
			}
			table.tablesorter thead tr .headerSortDown {
				background-image: url(<?php echo htmlspecialchars(CDN_JS); ?>/jquery.tablesorter/themes/blue/desc.gif);
			}

			table .number {
				text-align: right;
			}
			
			table .center {
				text-align: center;
			}
			
			table .void {
				border: none;
				background-color: #fff;
			}
			
			table tbody td {
				border: 1px solid #ddd;
			}
			
			table th.header i {
				font-weight: normal;
			}
			
			.title {
				font-size: 180%;
			}
			
			.err_ihub {
				color: red!important;
			}
			
			.private_sov {
	
			}
			
			.sysName {
				padding-left: 5px !important;
				font-weight: bold;
				text-align: left;
				min-width: 80px;
			}
			
			.sovcount, .count { 
				font-weight: bold;
				color: green;
			}
			
			#sovupkeep tr td {
				width: 260px;
			}
			
			#sovupkeep tr td.countsum {
				padding-right: 5px;
				text-align: right;
				width: 125px;
			}
			
			#sovupkeep .details-costcost {
				display: none;
			}
			
			.ihd , .ice {
				text-align: center;
			}
			
			.sun {
				padding: 0!important;
			}
			
			.sun img {
				padding: 0;
				margin: 0;

			}
			
			.stat {
				white-space: nowrap;
			}
			
			.stat > img {
				margin-bottom: -4px;
				margin-top: -4px;
				margin-left: -4px;
				margin-right: 8px;
			}
			
			.orc {
				text-align: right;
			}
			
			.tablesorter tbody tr td > img {
				height: 24px;
				width: 24px;
			}
			
			.tableTopFilter  {
				background-color: white!important;
				padding-bottom: 0;
			}

		</style>
		
		<script>
		
			function number_format(number, decimals, dec_point, thousands_sep) {
			  //  discuss at: http://phpjs.org/functions/number_format/
			  // original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
			  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
			  // improved by: davook
			  // improved by: Brett Zamir (http://brett-zamir.me)
			  // improved by: Brett Zamir (http://brett-zamir.me)
			  // improved by: Theriault
			  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
			  // bugfixed by: Michael White (http://getsprink.com)
			  // bugfixed by: Benjamin Lupton
			  // bugfixed by: Allan Jensen (http://www.winternet.no)
			  // bugfixed by: Howard Yeend
			  // bugfixed by: Diogo Resende
			  // bugfixed by: Rival
			  // bugfixed by: Brett Zamir (http://brett-zamir.me)
			  //  revised by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
			  //  revised by: Luke Smith (http://lucassmith.name)
			  //    input by: Kheang Hok Chin (http://www.distantia.ca/)
			  //    input by: Jay Klehr
			  //    input by: Amir Habibi (http://www.residence-mixte.com/)
			  //    input by: Amirouche
			  //   example 1: number_format(1234.56);
			  //   returns 1: '1,235'
			  //   example 2: number_format(1234.56, 2, ',', ' ');
			  //   returns 2: '1 234,56'
			  //   example 3: number_format(1234.5678, 2, '.', '');
			  //   returns 3: '1234.57'
			  //   example 4: number_format(67, 2, ',', '.');
			  //   returns 4: '67,00'
			  //   example 5: number_format(1000);
			  //   returns 5: '1,000'
			  //   example 6: number_format(67.311, 2);
			  //   returns 6: '67.31'
			  //   example 7: number_format(1000.55, 1);
			  //   returns 7: '1,000.6'
			  //   example 8: number_format(67000, 5, ',', '.');
			  //   returns 8: '67.000,00000'
			  //   example 9: number_format(0.9, 0);
			  //   returns 9: '1'
			  //  example 10: number_format('1.20', 2);
			  //  returns 10: '1.20'
			  //  example 11: number_format('1.20', 4);
			  //  returns 11: '1.2000'
			  //  example 12: number_format('1.2000', 3);
			  //  returns 12: '1.200'
			  //  example 13: number_format('1 000,50', 2, '.', ' ');
			  //  returns 13: '100 050.00'
			  //  example 14: number_format(1e-8, 8, '.', '');
			  //  returns 14: '0.00000001'

			  number = (number + '')
				.replace(/[^0-9+\-Ee.]/g, '');
			  var n = !isFinite(+number) ? 0 : +number,
				prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
				sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
				dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
				s = '',
				toFixedFix = function(n, prec) {
				  var k = Math.pow(10, prec);
				  return '' + (Math.round(n * k) / k)
					.toFixed(prec);
				};
			  // Fix for IE parseFloat(0.55).toFixed(0) = 0;
			  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
				.split('.');
			  if (s[0].length > 3) {
				s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
			  }
			  if ((s[1] || '')
				.length < prec) {
				s[1] = s[1] || '';
				s[1] += new Array(prec - s[1].length + 1)
				  .join('0');
			  }
			  return s.join(dec);
			}

		
		
		</script>
		
	</head>
	<body>
<center>

<div class="navbar navbar-inverse">
      <div class="navbar-inner">
        <div class="container">
          <div class="nav-collapse collapse">
            <ul class="nav">
              <li <?php if(!$show_ref && !$show_renters): ?>class="active"<?php endif; ?>>
                <a href="<?php echo _FILE_URL_; ?>?ihub=1">ihubs</a>
              </li>
	<?php if(!defined('_NO_REF')): ?>
              <li <?php if($show_ref && !$show_renters): ?>class="active"<?php endif; ?>>
                <a href="<?php echo _FILE_URL_; ?>?ref=1">refinerys</a>
              </li>
	<?php endif; ?>
<!--
			  <li <?php if(!$show_ref && $show_renters): ?>class="active"<?php endif; ?>>
                <a href="?rent=1">renters</a>
              </li>
-->
            </ul>
          </div>
        </div>
      </div>
    </div>
	
	

<div id="filters">

	<select id="regionff">
		<option <?php if(empty($_GET['region'])): ?> selected="selected" <?php endif; ?> value="all">all regions</option>
		<?php foreach($regions as $region => $cnt): ?>
			<option data-dotlandname="<?php echo cleanName($region, false); ?>" <?php if(!empty($_GET['region']) && (($_GET['region']) == cleanName($region, true))): ?> selected="selected" <?php endif; ?> value="<?php echo cleanName($region, true); ?>">
				<?php echo htmlspecialchars($region) . ( (!$show_ref) ? " ({$cnt})" : '' ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	
	<input maxlength="30" size="35" type="text" value="<?php echo (!empty($_GET['filter']) ? htmlspecialchars($_GET['filter']) : ''); ?>" id="textff" placeholder="text filter">
	
	[count: <span class="count"><?php echo count($systems); ?></span>]
	
	<br>
	
	<?php if($loc_cnt_run > 0): ?>
		<br><span class="success"><strong><?php echo $loc_cnt_run; ?></strong> new ihubs located (planet)!</span>
	<?php endif; ?>
</div>
	
	<table class="tablesorter" id="SYSTEM_LIST">
		<thead>
			<tr>
			<!--	<th class="header">S</th>-->
				<th class="header">system</th>
			<?php if($system_indy_index): ?>
				<th class="header">indy</th>
			<?php endif; ?>
				<th class="header">station</th>
			<?php if(!$show_ref): ?>			
				<th title="refinery efficiency" class="header">ref</th>
				<th title="refinery tax" class="header">tax</th>
				<th title="office rental cost (m)" class="header">ofi</th>	
			
				<th class="header">sec</th>
				<th class="header">belts</th>
				<th class="header">ice</th>
			<?php endif; ?>
				<th class="header">const</th>
				<th class="header">region</th>
				<th class="header">faction</th>
				
			<?php if($show_ref):			
				foreach($EVE_MINERALS as $typeID => $info): ?>
					<th title="<?php echo $info['l'] ?>" class="header">
						<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/<?php echo $typeID; ?>_32.png"> <?php echo $info['s'] ?>
					</th>				
				<?php endforeach; ?>
			
					<th title="sum" class="header">
						SUM
					</th>	
			
			<?php else: ?>
				
				<th class="header">ihub</th>
				<th title="Pirate Detection" class="header">
					<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/<?php echo key($Pirate_Detection); ?>_32.png">
				</th>
				<th title="Entrapment" class="header">
					<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/<?php echo key($Entrapment); ?>_32.png">
				</th>
				<th title="Ore Prospecting" class="header">
					<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/<?php echo key($Ore_Prospecting); ?>_32.png">
				</th>
				<th title="Survey Networks" class="header">
					<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/<?php echo key($Survey_Networks); ?>_32.png">
				</th>
				<th title="Quantum Flux" class="header">
					<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/<?php echo key($Quantum_Flux); ?>_32.png">
				</th>
				
			
				<th title="Advanced Logistics Network" class="header">
					<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/32422_32.png">
				</th>
				<th title="Cynosural Navigation" class="header">
					<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/2008_32.png">
				</th>
				<th title="Cynosural Suppression" class="header">
					<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/2001_32.png">
				</th>
				<th title="Supercapital Construction Facilities" class="header">
					<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/2009_32.png">
				</th>
				
			<?php endif; ?>
				
			</tr>
		</thead>
		
		<tbody>
			<?php 
				$sum_all = 0;
				$rowId = 0; 
				foreach($systems as $system): 
				$ihub = isset($ihubs[$system['solarSystemID']]) ? $ihubs[$system['solarSystemID']] : false;
				$nah_sov = isset($NAH_systems[$system['solarSystemID']]);
				
				$stat = isset($stations[$system['solarSystemID']]) ? $stations[$system['solarSystemID']] : false;
				
				if ($show_ref && !$stat) continue;
				
			?>
			
				<tr data-sysid="<?php echo $system['solarSystemID']; ?>" class="region_<?php echo cleanName($system['regionName'], true); 
				
				if(!$ihub && $nah_sov):
					echo ' noihub';
				endif;
				
				if(!$ihub && !$nah_sov):
					echo ' private_sov';
				endif;
				
				?>">
			<!--		<td class="sun" data-value="<?php echo ++$rowId; ?>">
						<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/<?php echo htmlspecialchars($system['sunTypeID']) ?>_32.png">
					</td>
			-->	
					<td class="sysName"><a target="_blank" href="//evemaps.dotlan.net/map/<?php echo cleanName($system['regionName']); ?>/<?php echo cleanName($system['solarSystemName']); ?>"><?php echo htmlspecialchars($system['solarSystemName']) ?></a></td>
					
				<?php if($system_indy_index): 
					$indy = $system_indy_index[$system['solarSystemName']];
				?>
					<td data-value="<?php echo $indy['manufacturing']; ?>"><?php echo number_format($indy['manufacturing'], 3, '.', '.'); ?></td>
				<?php endif; ?>
					
					<?php
						
					
					#print_r2($stat); exit;
					
						if ($stat): ?>
							<td 
title="reprocessing efficiency (<?php echo 100 * $stat['reprocessingEfficiency'] . '%'; ?>; tax: <?php echo number_format((100 * $stat['reprocessingStationTake']), 2); ?>%)"
							class="stat">
							
								<img src="<?php echo htmlspecialchars(CDN_IMG); ?>/InventoryType/<?php echo $stat['stationTypeID']; ?>_32.png">						
								<?php echo isset($stationTypes[$stat['stationTypeID']]) ? $stationTypes[$stat['stationTypeID']] : ' station '; ?>

							</td>
							
		<?php if(!$show_ref): ?>
							
					<td class="ref" title="<?php echo $stat['reprocessingEfficiency']; ?>" data-value="<?php echo !isset($stat['reprocessingEfficiency']) ? '-1' : $stat['reprocessingEfficiency']; ?>">
						<?php echo !isset($stat['reprocessingEfficiency']) ? '-' : 100 * @$stat['reprocessingEfficiency'] . '%'; ?>
					</td>
					
					<td class="ref"  data-value="<?php echo !isset($stat['reprocessingStationTake']) ? '-1' : $stat['reprocessingStationTake']; ?>">
						<?php echo !isset($stat['reprocessingStationTake']) ? '-' : 100 * @$stat['reprocessingStationTake'] . '%'; ?>
					</td>

					<td data="<?php echo isset($stat['officeRentalCost']) ? $stat['officeRentalCost'] : '-1'; ?>" title="office rental cost (<?php echo $stat['officeRentalCost']; ?>)" class="orc">
								<?php echo  isset($stat['officeRentalCost']) ? number_format( ($stat['officeRentalCost']/1000000), 0, ',' , '' ) . 'm' : '<i>xxx</i>'; ?>
							</td>
		<?php endif; ?>
							
						<? else: ?>
						<!--	<td>&nbsp;</td>	-->
							<td data-value="-1">&nbsp;</td>
							<td data-value="-1">&nbsp;</td>
							<td data-value="-1">&nbsp;</td>
							<td data-value="-1">&nbsp;</td>
						<? endif ; ?>
						
		<?php if(!$show_ref): ?>
					<td><?php echo number_format($system['security'], 3, '.', ',') ?></td>
					<td class="number"><?php echo number_format($system['belts'], 0, '.', ',') ?></td>
					<td class="ice"><?php echo $system['is_ice']  ? 'ice' : '&nbsp;' ?></td>
		<?php endif; ?>
					<td><?php echo htmlspecialchars($system['constellationName']) ?></td>
					<td><?php echo htmlspecialchars($system['regionName']) ?></td>
					<td><?php echo htmlspecialchars($system['faction']) ?></td>
					
			<?php if($show_ref): 
				$sys_stuff = isset($minerals[$system['solarSystemID']]) ? $minerals[$system['solarSystemID']] : false;
				
			#	if (!$sys_stuff && isset($minerals[$system['solarSystemID'] + 6000001])) {
			#		$sys_stuff = $minerals[$system['solarSystemID'] + 6000001];
			#	}
				
				#var_dump($system['solarSystemID']); print_r2( $sys_stuff );
				
			?>
				
				<?php foreach($EVE_MINERALS as $typeID => $info):
					$sum = 0;
					
					#print_r2($sys_stuff);
					if (!empty($sys_stuff) && is_array($sys_stuff) && count($sys_stuff)) {
						foreach ($sys_stuff as $typeId => $quan) {
							#var_dump($typeId);
							#print_r2($itemInfo); exit;
							$sum += ($itemInfo[$typeId]['buy'] * $quan);
						}
					}
					
					$sum_all += $sum;
				
				
				?>
					<td title="<?php echo $info['l'] ?>" class="number data_<?php echo $typeID; ?>" data-value="<?php echo isset( $sys_stuff[$typeID] ) ? $sys_stuff[$typeID] : 0; ?>">
						<?php  if( isset( $sys_stuff[$typeID] ) ): ?>
							<?php echo number_format($sys_stuff[$typeID], 0, ".", "."); ?>
						<?php else: ?>
							0
						<?php endif; ?>
					</td>				
				<?php endforeach; ?>
			
					<td title="sum" class="number data_sum" data-value="<?php echo $sum; ?>">
						<?php echo number_format($sum, 0, ".", "."); ?>
					</td>	
			
			<?php else: ?>
		
					<?php 
						$ihub = isset($ihubs[$system['solarSystemID']]) ? $ihubs[$system['solarSystemID']] : false;
						
						if($ihub): 
						
						$ihub_loc = $system['ihub_loc'];
						if (!empty($ihub_loc)) {
							$ihub_loc = trim(str_replace($system['solarSystemName'], '', $system['ihub_loc']));
							$ihub_loc1 = explode('-', $ihub_loc);
							if (!empty($ihub_loc1[0])) {
								$ihub_loc = trim($ihub_loc1[0]);
							}
						}
						
						?>
						
				
						
						<td class="ihd ihub_planet" title="ihub (<?php echo htmlspecialchars($ihub_loc); ?>)"><?php echo !empty($ihub_loc) ? 'p.'.roman2dec($ihub_loc) : '???' ?></td>
						
						<td class="ihd strg" title="Pirate<?php echo trim($ihub['pirate']) ?>"><?php echo $ihub['pirate'] ?></td>
						<td class="ihd strg" title="Entrapment<?php echo trim($ihub['entrapment']) ?>"><?php echo $ihub['entrapment'] ?></td>
						<td class="ihd strg" title="Ore<?php echo trim($ihub['ore']) ?>"><?php echo $ihub['ore'] ?></td>
						<td class="ihd strg" title="Survey<?php echo trim($ihub['survery']) ?>"><?php echo $ihub['survery'] ?></td>
						<td class="ihd strg" title="Quantum<?php echo trim($ihub['quantumflux']) ?>"><?php echo $ihub['quantumflux'] ?></td>
						
						<td class="ihd strg<?php echo $ihub['jb'] ? ' up-adv' : '' ?>" title="Advanced Logistics Network"><?php echo $ihub['jb'] ? 'x' : '&nbsp;' ?></td>
						<td class="ihd strg<?php echo $ihub['beacon'] ? ' up-navig' : '' ?>" title="Cynosural Navigation"><?php echo $ihub['beacon'] ? 'x' : '&nbsp;' ?></td>
						<td class="ihd strg<?php echo $ihub['jammer'] ? ' up-suppre' : '' ?>" title="Cynosural Suppression"><?php echo $ihub['jammer'] ? 'x' : '&nbsp;' ?></td>
						<td class="ihd strg<?php echo $ihub['supercap'] ? ' up-cap' : '' ?>" title="Supercapital Construction Facilities"><?php echo $ihub['supercap'] ? 'x' : '&nbsp;' ?></td>
						
				
					
					<?php elseif($nah_sov): ?>
					
						<td colspan="10" class="err_ihub">
							<i>no ihub!</i>
						</td>
						
					<?php else: ?>
					
						<td colspan="10" class="private_sov">
							private sov <?php
								$owner = !empty( $NA_private_sov[$SOV_system_to_corp[$system['solarSystemID']]] ) 
									? $NA_private_sov[$SOV_system_to_corp[$system['solarSystemID']]] 
									: false;
									
								if ($owner):
									echo '(<a href="//evemaps.dotlan.net/corp/' . htmlspecialchars(cleanName($owner['name'])) . '">' . htmlspecialchars($owner['name']) 
										. '</a> [' . htmlspecialchars($owner['ticker']) . '])';
								endif;
									
								#print_r2( $owner );
							?>
						</td>
					
					<?php endif; ?>
					
		<?php endif; ?>
					
				</tr>
				
			<?php endforeach; ?>
			
		<?php if($show_ref): ?>
			<tfoot>
				<tr>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					
					
				<?php foreach($EVE_MINERALS as $typeID => $info): ?>
					<td title="<?php echo $info['l'] ?>" class="number sumcolumn" data-typeId="<?php echo $typeID; ?>">
						0
					</td>				
				<?php endforeach; ?>
		
					<td title="sum" class="number sumcolumn" data-typeId="sum">
						0
					</td>	
		
				</tr>
			</tfoot>
		<?php endif; ?>
			
		</tbody>
		
	</table>
	
	<table id="sovupkeep" style="margin-bottom: 5px; margin-top: 15px;">
		<tr>
			<td colspan="2">
				30d sov cost for selected <span class="count">xx</span> systems
				<span id="SOVCOST_DETAILS" style="float:right;">[<a href="javascript:void(0);">show details</a>]</span>
			</td>
		</tr>
		<tr class="details-costcost">
			<td>sov upkeep (<span class="sovcount count-systems">xx</span> systems):</td>
			<td class="countsum sovcost-systems">xx</td>
		</tr>
		<tr class="details-costcost">
			<td>Cynosural Suppression (<span class="sovcount count-jammers">xx</span>):</td>
			<td class="countsum sovcost-jammers">xx</td>
		</tr>
		<tr class="details-costcost">
			<td>Advanced Logistics Network (<span class="sovcount count-jb">xx</span>):</td>
			<td class="countsum sovcost-jb">xx</td>
		</tr>
		<tr class="details-costcost">
			<td>Cynosural Navigation (<span class="sovcount count-cynogen">xx</span>):</td>
			<td class="countsum sovcost-cynogen">xx</td>
		</tr>
		<tr class="details-costcost">
			<td>Supercapital Construction Facilities (<span class="sovcount count-supercap">xx</span>):</td>
			<td class="countsum sovcost-supercap">xx</td>
		</tr>
		<tr>
			<td>total:</td>
			<td style="font-weight: bold;" class="countsum sovcost-total">xx</td>
		</tr>
	
	</table>
	
	<!-- sum-all: <?php echo number_format($sum_all, 0, ".", ".") ?> -->
	
	assets: <?php echo $assets->currentTime; ?> 
	| sov: <?php echo $sov->currentTime; ?>
	| stations: <?php echo $OutpostList->currentTime; ?> / <?php echo $ConquerableStationList->currentTime; ?>
	
</center>

<div style="width: 100%; text-align: center; border-top: 1px solid;">
	All feedback/suggestions<!--/donations--> to <a target="_blank" href="//gate.eveonline.com/Profile/Baron_Holbach">Baron Holbach</a>.
<br>
	 All <a href="//eve.kassikas.net/ore/?legal=show">Eve Related Materials</a> are property of CCP Games. See <a href="//eve.kassikas.net/ore/?legal=show">Legal Notice</a>. 
	
</div>

		<script src="<?php echo htmlspecialchars(CDN_JS); ?>/jquery/jquery.js"></script>
		<script src="<?php echo htmlspecialchars(CDN_JS); ?>/bootstrap/js/bootstrap.min.js"></script>
		<script src="<?php echo htmlspecialchars(CDN_JS); ?>/jquery.tablesorter/jquery.tablesorter.js"></script>
		<script src="<?php echo htmlspecialchars(CDN_JS); ?>/floatThead/jquery.floatThead.min.js"></script>
		
		
		<script>
		$(document).ready(function() {		

			$('.tablesorter').tablesorter({
				textExtraction: function(node) {
					try {
						var $node = $(node);
						if($node.data("value")) {
							return $node.data("value");
						}
						return $node.text()
					} catch(e) {
						return '';
					}				
				}
			});
		
			var $thead = $('.tablesorter thead');
			var cols = $thead.find('tr th').length;
			var $filters = $('#filters');
			
			$thead.prepend(
				$('<tr></tr>').prepend(
					$('<th></th>')
						.attr('colspan', cols)
						.addClass('tableTopFilter')
						.html($filters.html())
				)
			);
			
			$filters.remove();
			
			
			$('.tablesorter').floatThead();
			//$('*[title]').tooltip({placement: 'right'});

			$('#settings').click(function() {
				$('#dataform > center').toggle();
				return false;
			});
			
			
			$systems = $('table.tablesorter tbody > tr');
			var $count = $('.count');
			
			function updateFilter() {
				var region = $('#regionff').val();
				var text = $.trim($('#textff').val()).toLowerCase();

				var dif = '?';
				function getDif() {
					if (dif == '?') {
						dif = '&';
						return '?';
					}
					return dif;
				}
				var dotland_link = false;
				
				// http://80.79.115.132/~bs1942/eve/NAH/
		//		var newUrl = '<?php echo (($_SERVER['SERVER_NAME'] == $_SERVER['SERVER_ADDR'] && $_SERVER['SERVER_ADDR'] == '80.79.115.132') ? '/~bs1942/eve/NAH/' : "/NAH/") ?><?php echo (_FILE_URL_) . ( $show_ref ? "' + getDif() + 'ref=1" : '' ); ?>';
				var newUrl = <?php echo json_encode(parse_url($PAGE_URL_BASE, PHP_URL_PATH)); ?>;
				if (!newUrl) { newUrl = '/'; }
				
				if (region == 'all') {
					$systems.show();
				} else {
					$systems.hide();
					$systems.parent().find('.region_' + region).show();
					
					newUrl +=  getDif() +'region=' + region;
					
					dotland_link = document.location.protocol + '//evemaps.dotlan.net/map/' + $('#regionff option:selected').data('dotlandname') +'/';
				}
				
				if (!!text) {
				
					newUrl += getDif() +'filter=' + text;
				
					$systems.each(function(i, e){
						var show = false;
					
						var $e = $(e);
						var txt = $e.data('txt');
						if (!txt) {
							txt = $.trim($e.data('ff')); // ( $e.text() + $.trim($e.data('ff')) ).toLowerCase();
							$e.find('td').each(function(i2, e2) {
								var $e2 = $(e2);
								var txt2 = $.trim($e2.text());
								if (txt2 == '' || txt2 == '&nbsp;') {
									return;
								}
								txt += ' @ ' + txt2;
								if ($e2.is('.strg')) {
									var title2 = $e2.attr('title');
									txt += ' @ ' + title2;
								}
							});
							txt = $.trim(txt.toLowerCase());
							//console.log($e, txt);
							$e.data('txt', txt);
						}
					
						var text1 = text.replace('*', '|').replace('*', '|').replace('*', '|').replace('*', '|').replace('*', '|');
					
						$.each(text.split('|'), function(tmp, filter) {
							if (!!filter && txt.indexOf(filter) != -1) {
								//$e.hide();
								show = true;
							}
						});
						/*
						var joinsplit = text.split('*');
						if ((joinsplit.length > 1) && show) {
							console.log($e);
							$.each(joinsplit, function(tmp, filter) {
								if (!(!!filter && txt.indexOf(filter) != -1)) {
									show = false;
								}
							});
						}
						*/
						if (!show) {
							$e.hide();
						} else {
							if (dotland_link && $e.is(':visible')) {
								dotland_link += $e.find('.sysName').text() + ',';
							}
							
						}
						
					});
				}
				

				var show_count = $systems.parent().find('tr:visible').length;
				$count.html( show_count );
				
<?php if($show_ref): ?>
	
				$('.tablesorter tfoot tr td.sumcolumn').each(function() {
					var $t = $(this);
					var typeId = $t.data('typeid');
					if (!typeId) return;
					var sum = 0;
					
					
					$('.tablesorter tbody tr:visible td.data_' + typeId).each(function() {
						sum += parseInt($(this).data('value'));
					});
					
					
					$t.html( number_format(sum, 0, ',', '.') );
				});
<?php else: ?>
				
				
				$('#region_dotland_link').remove();
				
				if (dotland_link && !!text && (show_count > 0)) {
					dotland_link = $.trim(dotland_link);
					dotland_link = dotland_link.substr(0, (dotland_link.length - 1));
					
					$('.tablesorter .tableTopFilter:first').append(
						$('<div></div>')
							.attr('id', 'region_dotland_link')
							.append(
								$('<a></a>')
									.attr('href', dotland_link)
									.attr('target', '_blank')
									.html(dotland_link.length < 123 ? dotland_link : (dotland_link.substr(0, 120) + '...'))
							)
					);
				}
				

<?php endif; ?>
				
				updateSovCost();
				
				if (window && window.history && window.history.replaceState) {
					window.history.replaceState('Object', document.title, newUrl);
				}
				
				
			}
			
			function sovUpKeepNF (number) {
				return number_format( (number * 1000000), 0, ',' , '.' );
			}
			
			function updateSovCost() {
				var COST_SYSTEM = 180;
				var COST_JAMMER = 600;
				var COST_JB = 300;
				var COST_CYNOGEN = 60;
				var COST_SUPERCAP = 30;
			
				<?php if ($show_ref): ?>
					$('#sovupkeep').hide();
					return;
				<?php endif; ?>
				
				var $tb = $('#SYSTEM_LIST tbody > tr:visible');
				
				var count_systems = $tb.find('td.ihd.ihub_planet, td.err_ihub').length; // both systmes with ihub and without
				var count_jammer = $tb.find('td.ihd.up-suppre').length;
				var count_jb = $tb.find('td.ihd.up-adv').length;
				var count_cynogen = $tb.find('td.ihd.up-navig').length;
				var count_supercap = $tb.find('td.ihd.up-cap').length;
				
				var cost_systems 	= count_systems		*	COST_SYSTEM;
				var cost_jammer 	= count_jammer		*	COST_JAMMER;
				var cost_jb 		= count_jb			*	COST_JB;
				var cost_cynogen 	= count_cynogen		*	COST_CYNOGEN;
				var cost_supercap 	= count_supercap	*	COST_SUPERCAP;
				
				$('.sovcount.count-systems').html(count_systems);
				$('.sovcount.count-jammers').html(count_jammer);
				$('.sovcount.count-jb').html(count_jb);
				$('.sovcount.count-cynogen').html(count_cynogen);
				$('.sovcount.count-supercap').html(count_supercap);
				
				$('.countsum.sovcost-systems').html( sovUpKeepNF(cost_systems) );
				$('.countsum.sovcost-jammers').html( sovUpKeepNF(cost_jammer) );
				$('.countsum.sovcost-jb').html( sovUpKeepNF(cost_jb) );
				$('.countsum.sovcost-cynogen').html( sovUpKeepNF(cost_cynogen) );
				$('.countsum.sovcost-supercap').html( sovUpKeepNF(cost_supercap) );
				
				var cost_total = cost_systems + cost_jammer + cost_jb + cost_cynogen + cost_supercap;
				$('.countsum.sovcost-total').html( sovUpKeepNF(cost_total) );
			}
			
			$('#regionff').change(updateFilter);
			$('#textff').keyup(updateFilter);
			
			updateFilter();
			
			$('#SOVCOST_DETAILS a').click(function() {
				var $rows = $('#sovupkeep .details-costcost');
				var is_visible = $rows.is(':visible');
				
				if (is_visible) {
					$(this).html('show details');
					$rows.hide();
				} else {
					$(this).html('hide details');
					$rows.show();
				}
			
			});
					
		}); 
		</script>
		

<?php if(!empty($GA_TRACKER_ID)): ?>
		
		<script>
		  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

		  ga('create', <?php echo json_encode($GA_TRACKER_ID); ?>, 'auto');
		  ga('send', 'pageview');
		  
		  <?php if (!empty($_SERVER['PHP_AUTH_USER'])): ?>
			ga('send', 'event', <?php echo json_encode($_SERVER['PHP_AUTH_USER']); ?>, 'user');
		  <?php endif; ?><?php if($show_ref): ?>
			ga('send', 'event', <?php echo json_encode($_SERVER['PHP_AUTH_USER']); ?>, 'refinery');
		   <?php endif; ?><?php if (!empty($trackedAlliance)): ?>
			ga('send', 'event', <?php echo json_encode($trackedAlliance); ?>, 'alliance');
		  <?php endif; ?>
		   
<?php endif; ?>

		</script>
		

	</body>
</html>