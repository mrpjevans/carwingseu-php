<?php

/*
 * carwingseu-getcsv
 * A PHP 5 script to fetch the current month's CSV file from Carwings
 * Version 0.1
 * 
 * By PJ Evans
 * mrpjevans.com
 *
 * Licensed under the terms of the GNU GENERAL PUBLIC LICENSE Version 2, June 1991
 * See LICENSE.txt for details
 *
 * Usage: php getcsv.php <username> <password> <outputdir>
 *
 */


// Setup
$username = $argv[1];
$password = $argv[2];
$outputDir = $argv[3];
$jar = tempnam(sys_get_temp_dir(), 'carwings-csv');

// Sanity
if(empty($username) || empty($password) || !is_dir($outputDir)){
	exit("Usage: php getcsv.php username password output-directory\r\n");
}

logit("Getting Current Month's Log from Carwings");

// Login
logit("Logging in to Carwings");
$response = call("http://www.nissan.co.uk/GB/en/YouPlus.html/j_security_check",array(
	"j_validate" => true,
	"j_username" => $username,
	"j_password" => $password,
	"_charset_" => "utf8"
	));

// Post-login script
logit("Calling post-login script");
$response = call("http://www.nissan.co.uk/content/GB/en/YouPlus/private/home.processafterlogin.html");
		
// Set up cross-site login (redirects)
logit("Requesting redirect to zeroemissons site");
$response = call("http://www.nissan.co.uk/GB/en/YouPlus/private/carwings/flashdata.routeplannerredirect.html?portalType=P0001");

// Now should get sensible response from the page containing the CSV link
logit("Getting electric bill page");
$response = call("https://zeroemission.nissan-carwings.com/aqPortal/content/country/default/jsp/mycar/electricbillcalculator/electricbill_comp.iface");

// Is the link there?
if(($pos = strpos($response,".csv\"")) === false){

	// No and we're too stupid to do anything else
	logit("No CSV link found, stopping");
	unlink($jar);
	exit(1);

}

// Parse out the link
$response = substr($response,0,$pos + 4);
$pos2 = strrpos($response,"\"");
$response = substr($response,$pos2 + 1);

// Result
logit("CSV Link is: " . $response);

// Get the CSV file 
logit("Downloading CSV file");
$csv = call("https://zeroemission.nissan-carwings.com" . $response);
file_put_contents($outputDir . "/" . basename($response) , $csv);

// Tidy up
unlink($jar);

// Success
logit("CSV downloaded to " . $outputDir . "/" . basename($response));
logit("Success");
exit(0);

// Print stuff out
function logit($s){
	$s = date("Y-m-d H:i:s")." ".$s."\r\n";
	echo($s);
}

// Make a HTTP call in a manner pleasing to Carwings
function call($url,$fields = null){
global $jar;

	// Setup
	$ch = curl_init($url);

	// Is this a POST?	
	if(is_array($fields)){

		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/x-www-form-urlencoded"
			));

	}

	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		"User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.114 Safari/537.36"
		));

	// Various settings
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	
	// Use cookies
	curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);

	// Call
	$response = curl_exec($ch);

	// Debug
	if(curl_error($ch) != ""){
		logit(curl_error($ch));
		exit;
	}

	return $response;

}

?>