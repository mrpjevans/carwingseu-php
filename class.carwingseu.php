<?php

/*
 * carwingseu
 * A PHP 5 Class for the European version of Nissan Carwings
 * Version 0.1
 * 
 * By PJ Evans
 * mrpjevans.com
 *
 * Licensed under the terms of the GNU GENERAL PUBLIC LICENSE Version 2, June 1991
 * See LICENSE.txt for details
 *
 * See README.txt for details of usage
 *
 */

class carwingseu {

	// Internal variables
	private $username;
	private $password;
	private $cookiejar;
	private $retries;
	private $vin;

	// Constants
	//private $agent = "User-Agent: Dalvik/1.6.0 (Linux; U; Android 4.3; GT-I9300 Build/JSS15J)";
	private $agent = "User-Agent: Dalvik/1.6.0 (Linux; U; Android 4.3; GT-I9300 Build/JSS15J; carwingseu)";
	private $endpoint = "https://mobileapps.prod.nissan.eu/android-carwings-backend-v2/2.0/carwingsServlet";

	// Properties
	public $lastResponse = "";

	// Load required parameters - cookiejar is a file (any file) that will be used to store session data
	public function __construct($vin = null, $cookiejar = null, $retries = 3){
		$this->vin = $vin;
		$this->cookiejar = $cookiejar;
		$this->retries = $retries;
		if($this->cookiejar == null){
			$this->cookiejar = sys_get_temp_dir() . '/carwingseu';
		}
	}

	//
	// Internal Support Functions
	//

	// Send a request to Carwings and parse the response
	private function _post($req){

		// cURL Setup
		$ch = curl_init($this->endpoint);
		//curl_setopt($ch, CURLOPT_PROXY, "localhost:8888");
		//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookiejar);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"User-Agent: ".$this->agent,
			"Content-Type: application/x-www-form-urlencoded"
			));

		// Carwings is flaky to say the least, so we'll attempt to get a valid response $this->retries times
		$attempts = 1;
		while($attempts <= $this->retries){

			// Send request to server and record the response for debugging purposes
			$response = curl_exec($ch);
			$this->lastResponse = $response;

			// Does it look like we have XML?
			if(strlen($response) > 2 && substr($response,0,2) == "<?"){
				
				// XML Returned - get on with it
				break;

			}

			// No, so add the attempt and try again
			$attempts++;

		}
		
		// Was there too many attempts?
		if($attempts > $this->retries){
			throw new Exception("InvalidXMLResponse");
		}

		// Convert to array
		$arr = $this->_xml2array($response);

		// Error?
		if(isset($arr['ErrorCode'])){
			throw new Exception("RemoteError", $arr['ErrorCode']);
		}

		return $arr;

	}

	// Convert XML String to Associative Array
	private function _xml2array($string){
  		$sxi = new SimpleXmlIterator($string, null, false);
  		return $this->_sxiToArray($sxi);
	}

	// Recursive XML walker for xml2array
	private function _sxiToArray($sxi){
		$a = array();
		for( $sxi->rewind(); $sxi->valid(); $sxi->next() ) {
	    	if($sxi->hasChildren()){
	    		if(!array_key_exists($sxi->key(), $a)){
	      			$a[$sxi->key()] = array();
	    		}
	      		$a[$sxi->key()][] = $this->_sxiToArray($sxi->current());
	    	} else {
	      		$a[$sxi->key()] = strval($sxi->current());
	    	}
	  	}
		return $a;
	}

	// Battery info
	private function _parseBattery($root){

		$out = array();
		$batt = $root['SmartphoneLatestBatteryStatusResponse'][0]['SmartphoneBatteryStatusResponseType'][0]['BatteryStatusRecords'][0];

		$out['operation'] = $batt['OperationResult'];
		$out['charging'] = (bool)$batt['BatteryStatus'][0]['BatteryChargingStatus'] = "false" ? false : true;
		$out['capacity'] = (int)$batt['BatteryStatus'][0]['BatteryCapacity'];
		$out['remaining'] = (int)$batt['BatteryStatus'][0]['BatteryRemainingAmount'];
		$out['plugin'] = $batt['PluginState'];
		$out['pluggedin'] = ($out['plugin'] == 'CONNECTED');

		$out['rangeac'] = (int)$batt['CruisingRangeAcOn'];
		$out['range'] = (int)$batt['CruisingRangeAcOff'];

		$out['rangeac_km'] = floor($out['rangeac'] / 1000);
		$out['range_km'] = floor($out['range'] / 1000);

		$out['rangeac_m'] = floor(($out['rangeac'] / 1000) * 0.62137);
		$out['range_m'] = floor(($out['range'] / 1000) * 0.62137);

		$out['timeevse'] = strtotime($batt['TimeRequiredToFull'][0]['HourRequiredToFull'].":".$batt['TimeRequiredToFull'][0]['MinutesRequiredToFull']);
		$out['time3k'] = strtotime($batt['TimeRequiredToFull200'][0]['HourRequiredToFull'].":".$batt['TimeRequiredToFull'][0]['MinutesRequiredToFull']);
		$out['time6k'] = strtotime($batt['TimeRequiredToFull200_6k'][0]['HourRequiredToFull'].":".$batt['TimeRequiredToFull'][0]['MinutesRequiredToFull']);
		$out['timestamp'] = strtotime($batt['NotificationDateAndTime']);

		$out['timeevse_nice'] = date("G:i",$out['timeevse']);
		$out['time3k_nice'] = date("G:i",$out['time3k']);
		$out['time6k_nice'] = date("G:i",$out['time6k']);
		$out['timestamp_nice'] = date("d/m/Y H:i",$out['timestamp']);

		return $out;

	}

	//
	// Public functions
	//

	// Log into the system
	public function login($username, $password, $parse = true){
		
		// Debug
		// return $this->_xml2array(file_get_contents("/Users/PJ/Desktop/xml.txt"));

		// Clear cookie session
		if(is_file($this->cookiejar)){
			unlink($this->cookiejar);
		}

		// Load XML template
		$req = file_get_contents('login.xml');

		// Replacements
		$req = str_replace('#username#', $username, $req);
		$req = str_replace('#password#', $password, $req);
		$req = str_replace('#time#', time(), $req);

		// Go
		$data = $this->_post($req);

		// Check this is valid
		if(!isset($data['SmartphoneUserInfoType'])){
			throw new Exception("UnrecognisedResp");
		}

		// Locate and store VIN
		if(!isset($data['SmartphoneUserInfoType'][0]['VehicleInfo'][0]['Vin'])){
			throw new Exception("NoVinReturned");	
		}
		$this->vin = $data['SmartphoneUserInfoType'][0]['VehicleInfo'][0]['Vin'];

		// Parsed data
		if($parse){

			$out = array();

			// Car information
			$out['vin'] = $data['SmartphoneUserInfoType'][0]['VehicleInfo'][0]['Vin'];
			$out['nickname'] = $data['SmartphoneUserInfoType'][0]['Nickname'];
			
			// Battery
			$out['battery'] = $this->_parseBattery($data);
					
			return $out;

		}

		return $data;


	}

	// Fetch information
	public function info($parse = true){

		// Load XML template
		$req = file_get_contents('info.xml');

		// Replacements
		$req = str_replace('#vin#', $this->vin, $req);

		// Go
		$data = $this->_post($req);

		// Check this is valid
		if(!isset($data['SmartphoneLatestBatteryStatusResponse'])){
			throw new Exception("UnrecognisedResp");
		}

		if($parse){

			$out = array();

			// Battery
			$out['battery'] = $this->_parseBattery($data);
			
			return $out;		

		}

		return $data;

	}

	// Request Update (void)
	public function update(){

		// Load XML template
		$req = file_get_contents('update.xml');

		// Replacements
		$req = str_replace('#vin#', $this->vin, $req);

		// Go
		$data = $this->_post($req);

		// Check response
		if(!isset($data['SmartphoneRemoteBatteryChargingRecordsResponse'][0]['SmartphoneRemoteBatteryChargingRecordsResponseType'][0]['RemoteBatteryChargingRecords'][0]['BatteryChargingStartDateAndTime'])){
			throw new Exception("UnrecognisedResp");
		}

	}


}

?>