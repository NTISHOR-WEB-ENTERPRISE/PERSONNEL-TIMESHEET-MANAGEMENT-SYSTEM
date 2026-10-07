<?php

session_start();

include "config.php";
include "notification_function.php";

if(!isset($_SESSION['supervisor_id'])){
    die("Supervisor not logged in.");
}

$supervisor_id = $_SESSION['supervisor_id'];

$message = "This is a test notification from the PTSMS notification system.";

if(notifySupervisor($conn, $supervisor_id, $message)){

    echo "Notification sent successfully.";

}else{

    echo "Notification failed.";

}

?>