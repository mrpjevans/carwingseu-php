<?php

/*
 *
 * Simple command-line Carwings-EU Example
 * From the command-line run:
 * php example.php your-carwings-username your-carwings-password
 *
 */

// Include the class file
include('class.carwingseu.php');

// Populate these with your Carwings username and password
$username = $argv[1];
$password = $argv[2];

// Sanity
if(empty($username) || empty($password)){
	exit("Please specify your Carwings username and password\r\n");
}

// Defaults
$timeout = 60; // Divisions of 5 seconds

// Create object
$carwings = new carwingseu();

// Attempt login and output result
try {

	// Attempt Login
	echo("Logging into Carwings\r\n");
	$login = $carwings->login($username, $password);

	echo("Logged in, requesting update\r\n");
	$carwings->update();

	echo("Update requested\r\n");
	
	$c = 0;
	while($c < $timeout){

		echo("Waiting 5 seconds...\r\n");

		sleep(5);

		echo("Checking...\r\n");
		$info = $carwings->info();

		// The status changes to OK for one call then null (as a string) after that.
		if($info['battery']['operation'] == 'OK' || $info['battery']['operation'] == "null"){

			echo("Update received!\r\n");
			echo("Range with AC On: " . $info['battery']['rangeac_m'] . "m\r\n");
			echo("Range with AC Off: " . $info['battery']['range_m'] . "m\r\n");
			break;

		} else {

			echo("Status: ".$info['battery']['operation']."\r\n");

		}

		$c++;

		// Give up?
		if($c == $timeout){
			echo("No response yet from the car. Boo.\r\n");
		}

	}
	

} Catch (Exception $ex) {

	echo("EXCEPTION:" . $ex->getMessage() . ":" . $ex->getCode()."\r\n");

}

?>