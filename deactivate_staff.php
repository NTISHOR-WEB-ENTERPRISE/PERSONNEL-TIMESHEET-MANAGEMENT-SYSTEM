<?php

session_start();

include "config.php";
include "activity_logger.php";

/* ==============================
   ADMIN AUTHENTICATION
============================== */

if (!isset($_SESSION['admin_id'])) {

    header("Location: admin_login.php");
    exit();

}

if ($_SESSION['admin_role'] != "Super Admin") {

    die("Access Denied");

}

$current_admin = $_SESSION['admin_id'];


/* ==============================
   GET STAFF ID
============================== */

if (!isset($_GET['staff_id']) || empty($_GET['staff_id'])) {

    header("Location: manage_staff.php");
    exit();

}

$staff_id = mysqli_real_escape_string(
    $conn,
    trim($_GET['staff_id'])
);


/* ==============================
   CHECK STAFF EXISTS
============================== */

$query = mysqli_query($conn, "

    SELECT id, staff_id, fullname, status

    FROM staff

    WHERE staff_id='$staff_id'

");


if (!$query) {

    die("Database error: " . mysqli_error($conn));

}


if (mysqli_num_rows($query) == 0) {

    die("Staff member not found.");

}


$staff = mysqli_fetch_assoc($query);

$id = $staff['id'];


/* ==============================
   DEACTIVATE STAFF
============================== */

$update = mysqli_query($conn, "

    UPDATE staff

    SET status='Inactive'

    WHERE id='$id'

");


if (!$update) {

    die(
        "Unable to deactivate staff member. "
        . mysqli_error($conn)
    );

}


/* ==============================
   LOG ACTIVITY
============================== */

logActivity(

    $conn,

    $current_admin,

    "Admin",

    "Deactivate Staff",

    "Deactivated staff member: " . $staff['fullname']
);


/* ==============================
   RETURN TO MANAGE STAFF
============================== */

header("Location: manage_staff.php?message=deactivated");

exit();

?>