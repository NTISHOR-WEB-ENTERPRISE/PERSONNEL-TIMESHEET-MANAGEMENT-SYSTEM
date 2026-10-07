<?php
session_start();
include "config.php";
include "activity_logger.php";

/* ============================== ADMIN AUTHENTICATION ============================== */

if(!isset($_SESSION['admin_id'])){

    header("Location: admin_login.php");
    exit();

}

if($_SESSION['admin_role'] != "Super Admin"){

    die("Access Denied");

}

$admin_id = $_SESSION['admin_id'];

/* ============================== GET STAFF ID ============================== */

if(!isset($_GET['staff_id']) || empty($_GET['staff_id'])){

    header("Location: manage_staff.php");
    exit();

}


$staff_id = mysqli_real_escape_string(
    $conn,
    trim($_GET['staff_id'])
);


/* ============================== GET STAFF ============================== */

$query = mysqli_query($conn,"

    SELECT
        id,
        staff_id,
        fullname,
        status

    FROM staff

    WHERE staff_id='$staff_id'

");


if(!$query){

    die(
        "Database error: "
        . mysqli_error($conn)
    );

}


if(mysqli_num_rows($query) == 0){

    die("Staff member not found.");

}

$staff = mysqli_fetch_assoc($query);

/* ============================== ACTIVATE STAFF ============================== */

$update = mysqli_query($conn,"
    UPDATE staff
    SET status='Active'
    WHERE staff_id='$staff_id'
");


if(!$update){

    die(
        "Unable to activate staff member: "
        . mysqli_error($conn)
    );

}

/* ============================== LOG ACTIVITY ============================== */

logActivity(

    $conn,
    $admin_id,
    "Admin",
    "Activate Staff",
    "Activated staff member: " . $staff['fullname']
);

/* ============================== RETURN TO MANAGE STAFF ============================== */

header("Location: manage_staff.php");

exit();
?>