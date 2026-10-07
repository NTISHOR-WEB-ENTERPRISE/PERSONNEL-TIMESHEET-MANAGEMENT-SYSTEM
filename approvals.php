<?php
session_start();
include "config.php";
include "notification_function.php";

if(!isset($_SESSION['supervisor_id'])){
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];

if(!isset($_GET['action']) || !isset($_GET['id'])){
    header("Location: supervisor_dashboard.php");
    exit();
}

$id = $_GET['id'];
$action = $_GET['action'];

/* Verify timesheet belongs to this supervisor */

$check = mysqli_query($conn,"
SELECT t.staff_id
FROM timesheets t
INNER JOIN staff s
ON t.staff_id = s.staff_id
WHERE t.id = '$id'
AND s.supervisor_id = '$supervisor_id'
");

if(mysqli_num_rows($check) == 0){
    die("Access Denied");
}

$row = mysqli_fetch_assoc($check);
$staff_id = $row['staff_id'];

/* APPROVE */

if($action == "approve"){

    mysqli_query($conn,"
    UPDATE timesheets
    SET status='approved'
    WHERE id='$id'
    ");

    mysqli_query($conn,"
    INSERT INTO notifications
    (staff_id,message,is_read)
    VALUES
    ('$staff_id','Your timesheet was approved',0)
    ");
}

/* REJECT */

if($action == "reject"){

    mysqli_query($conn,"
    UPDATE timesheets
    SET status='rejected'
    WHERE id='$id'
    ");

    mysqli_query($conn,"
    INSERT INTO notifications
    (staff_id,message,is_read)
    VALUES
    ('$staff_id','Your timesheet was rejected',0)
    ");
}

header("Location: supervisor_dashboard.php");
exit();
?>