<?php

// Database Connection
$host = "localhost";
$user = "root";
$password = "";
$database = "PTSMS";

$conn = mysqli_connect($host, $user, $password, $database);

date_default_timezone_set('Africa/Lagos');

$conn->query("SET time_zone = '+01:00'");

// Check Connection
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}


// Application Settings
$app = [
    "name" => "NWEAATSMS",
    "organization_name" => "NTISHOR WEB ENTERPRISE",
    "logo" => "IMAGES/LOGO1.png",
    "email" => "ntishor64@gmail.com",
    "address" => "99C OLD ODUKPANI ROAD, IKOT ANSA, CALABAR, CROSS RIVER STATE"
];

?>