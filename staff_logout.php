<?php
session_start();
include "config.php";
include "activity_logger.php";
include "notification_function.php";

if(isset($_SESSION['staff_id'])){
    logActivity($conn,$_SESSION['staff_id'],"Staff","Logout","Staff logged out");
}

session_destroy();

header("Location: staff_login.php");
exit();
?>