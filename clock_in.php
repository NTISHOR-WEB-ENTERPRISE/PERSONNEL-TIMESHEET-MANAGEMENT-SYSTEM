<?php
session_start();
include "config.php";
include "notification_function.php";

$staff_id = $_SESSION['staff_id'];

mysqli_query($conn,
"UPDATE timesheets
SET clock_in = NOW()
WHERE staff_id = '$staff_id'
AND work_date = CURDATE()");
?>