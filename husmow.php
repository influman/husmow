<?php
	$xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>";      
	//***********************************************************************************************************************
	// V1.13 : Husqvarna Automower / copyright Influman 2019
	
	$action = getArg("action", true); 
	$server = getArg("server", true); // VAR1
	$mower = getArg("mower", false);
	$value = getArg("value", false);
	$periph_id = getArg('eedomus_controller_module_id'); 
	$tab_err = array("1" => "Hors zone", "2" => "Coupure fil périphérique", "10" => "A l'envers", "13" => "Robot bloqué", "15" => "Robot soulevé", "18" => "Problème bump arrière", "19" => "Problème bump avant", "25" => "Disque de coupe bloqué", "69" => "Arrêt manuel interrupteur", "74" => "Hors zone protection virtuelle");
		
	if ($action == '' ) {
		die();
	}
	$xml .= "<HUSQVARNA>";
	
	// SESSIONS // Ouverture de sessions
	if ($action != "polling") {
		$token_expire = loadVariable("HUSQVARNA_AUTOMOWER_EXPIRE");
		if (time() > $token_expire) {
			$tab_param = explode(",",$server);
			$login = $tab_param[0];
			$pass = utf8_decode($tab_param[1]);
			$credentials = '{"data":{"type":"token","attributes":{"username":"'.$login.'","password":"'.$pass.'"}}}';                     
			$headers_login = array(                                                                          
				'Content-Type:application/json',   
				'Accept: application/json',			
				'Content-Length: ' . strlen($credentials));  
			$url = "https://iam-api.dss.husqvarnagroup.net/api/v3/token";
			$response = httpQuery($url, 'POST', $credentials, NULL, $headers_login, true, false);
			$return_api = sdk_json_decode($response);
			if (array_key_exists("data",$return_api)) {
				
				$token = $return_api['data']['id'];
				$provider = $return_api['data']['attributes']['provider'];
				saveVariable("HUSQVARNA_AUTOMOWER_TOKEN", $token);
				saveVariable("HUSQVARNA_AUTOMOWER_PROVIDER", $provider);
				$token_expire = $return_api['data']['attributes']['expires_in'] + time();
				saveVariable("HUSQVARNA_AUTOMOWER_EXPIRE", $token_expire);
				$xml .= "<EXPIRE>".$token_expire."</EXPIRE>";
			} else {
				$xml .= "<STATUS>Unauthorized access, check login and password</STATUS>";
				$xml .= "</HUSQVARNA>";
				sdk_header('text/xml');
				echo $xml;
				die();
			}
		} else {
			$token = loadVariable("HUSQVARNA_AUTOMOWER_TOKEN");
			$provider = loadVariable("HUSQVARNA_AUTOMOWER_PROVIDER");
		}
		$headers = array(                                                                          
				'Content-type: application/json',
				'Accept: application/json',
					'Authorization: Bearer '.$token,
					'Authorization-Provider: '.$provider); 
	}
    
	// STATUS // Lecture des données Cloud Husqvarna
	if ($action == "getstatus") { 
		$status = "";
		$tab_mowers = array();
		$url = 	"https://amc-api.dss.husqvarnagroup.net/v1/mowers";
		$response = httpQuery($url, 'GET', NULL, NULL, $headers, true, false);
		$return_api = sdk_json_decode($response);
		$i = 0;
		$status = "No mower detected...";
		$xml .= "<MOWERS>";
		foreach($return_api as $mowers) {
			$i++;
			$xml .= "<MOWER_".$i.">";
			$mower_id = $mowers['id'];
			$mower_name = $mowers['name'];
			$mower_model = $mowers['model'];
			$mower_state = $mowers['status']['mowerStatus'];
			$mower_battery = $mowers['status']['batteryPercent'];
			$mower_mode = $mowers['status']['operatingMode'];
			$nextStartTimestamp = $mowers['status']['nextStartTimestamp'];
			$storedTimestamp = $mowers['status']['storedTimestamp'];
			$lastErrorCode = $mowers['status']['lastErrorCode'];
			$xml .= "<ID>".$mower_id."</ID>";
			$xml .= "<NAME>".$mower_name."</NAME>";
			$xml .= "<MODEL>".$mower_model."</MODEL>";
			$xml .= "<STATE>".$mower_state."</STATE>";
			$xml .= "<BATTERY>".$mower_battery."</BATTERY>";
			$xml .= "<MODE>".$mower_mode."</MODE>";
			$xml .= "<STARTTSMP>".$nextStartTimestamp."</STARTTSMP>";
			$xml .= "<STORETSMP>".$storedTimestamp."</STORETSMP>";
			$xml .= "<LASTERROR>".$lastErrorCode."</LASTERROR>";
			$tab_mowers[$i]['id'] = $mower_id;
			$tab_mowers[$i]['name'] = $mower_name;
			$tab_mowers[$i]['model'] = $mower_model;
			$tab_mowers[$i]['state'] = $mower_state;
			$tab_mowers[$i]['battery'] = $mower_battery;
			$tab_mowers[$i]['mode'] = $mower_mode;
			$tab_mowers[$i]['nextStartTimestamp'] = $nextStartTimestamp;
			$tab_mowers[$i]['storedTimestamp'] = $storedTimestamp;
			$tab_mowers[$i]['lastErrorCode'] = $lastErrorCode;
			$url = 	"https://amc-api.dss.husqvarnagroup.net/v1/mowers/".$mower_id."/geofence";
			$response = httpQuery($url, 'GET', NULL, NULL, $headers, true, false);
			$return_geofence = sdk_json_decode($response);
			$latitude = round($return_geofence['lastLocations'][0]['latitude'], 6);
			$longitude = round($return_geofence['lastLocations'][0]['longitude'], 6);
			$geofence = $latitude.",".$longitude;
			$xml .= "<GEOFENCE>".$geofence."</GEOFENCE>";
			$tab_mowers[$i]['geofence'] = $geofence;
			$xml .= "<LATITUDE>".$latitude."</LATITUDE>";
			$tab_mowers[$i]['latitude'] = $latitude;
			$xml .= "<LONGITUDE>".$longitude."</LONGITUDE>";
			$tab_mowers[$i]['longitude'] = $longitude;
			$xml .= "</MOWER_".$i.">";
			if ($i == $mower) {
				$status = $mower_name." ".$mower_model." (".$mower_mode.")";
				if ($mower_state == "ERROR") {
					//if (array_key_exists($lastErrorCode, $tab_err)) {
					//	$status .= " ERROR ".$tab_err[$lastErrorCode];
					//} else {
						$status .= " ERROR ";
					//}
				}
			}
		}
		$xml .= "</MOWERS>";
		saveVariable("HUSQVARNA_AUTOMOWER",$tab_mowers);
		$xml .= "<STATUS>".$status."</STATUS>";
		$xml .= "</HUSQVARNA>";
		sdk_header('text/xml');
		echo $xml;
		die();
	}
	
	// COMMAND // Actionner la tondeuse
	if ($action == "command") {
		$tab_mowers = loadVariable("HUSQVARNA_AUTOMOWER");
		if (array_key_exists($mower,$tab_mowers)) {
			$mower_id = $tab_mowers[$mower]['id'];
			if ($value == "start") {
				$command = '{"action":"START"}';   
			} else if ($value == "stop") {
				$command = '{"action":"STOP"}';
			} else if ($value == "park") {
				$command = '{"action":"PARK"}';
			} else {
				$command = '{"action":"'.$value.'"}';
			}
			$headers = array(                                                                          
				'Content-type: application/json',
				'Accept: application/json',
					'Authorization: Bearer '.$token,
					'Authorization-Provider: '.$provider,
					'Content-Length: ' . strlen($command)); 
			$url = 	"https://amc-api.dss.husqvarnagroup.net/v1/mowers/".$mower_id."/control";
			$response = httpQuery($url, 'POST', $command, NULL, $headers, true, false);
		}
		die();
	}
	
	// POLLING // Lecture des données par capteur
	if ($action == "polling") {
		$tab_mowers = loadVariable("HUSQVARNA_AUTOMOWER");
		if (array_key_exists($mower,$tab_mowers)) {
			$xml .= "<MOWER_STATUS>".$tab_mowers[$mower]['state']."</MOWER_STATUS>";
			$xml .= "<BATTERY>".$tab_mowers[$mower]['battery']."</BATTERY>";
			$xml .= "<LATITUDE>".$tab_mowers[$mower]['latitude']."</LATITUDE>";
			$xml .= "<LONGITUDE>".$tab_mowers[$mower]['longitude']."</LONGITUDE>";
			$xml .= "<ID>".$tab_mowers[$mower]['id']."</ID>";
			$nextstart = $tab_mowers[$mower]['nextStartTimestamp'];
			$xml .= "<STARTTSMP>".$tab_mowers[$mower]['nextStartTimestamp']."</STARTTSMP>";
			if ($nextstart == 0) {
				$nextstart_date = "---";
			} else {
				$nextstart_date = date("Y-m-d H:i:s",$nextstart);
			}
			$xml .= "<NEXTSTART>".$nextstart_date."</NEXTSTART>";
			$xml .= "<STORETSMP>".$tab_mowers[$mower]['storedTimestamp']."</STORETSMP>";
			$xml .= "<LASTERROR>".$tab_mowers[$mower]['lastErrorCode']."</LASTERROR>";
			$xml .= "<MODE>".$tab_mowers[$mower]['mode']."</MODE>";
			
		}
		$xml .= "</HUSQVARNA>";
		sdk_header('text/xml');
		echo $xml;
		die();
	}
?>
