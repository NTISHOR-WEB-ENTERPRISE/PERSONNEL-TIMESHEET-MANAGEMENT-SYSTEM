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


$admin_id = $_SESSION['admin_id'];
$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];


/* ==============================
   GET SUPERVISOR ID
============================== */

if (
    !isset($_GET['supervisor_id']) ||
    empty($_GET['supervisor_id'])
) {

    die("Supervisor ID not provided.");

}


$supervisor_id = mysqli_real_escape_string(
    $conn,
    trim($_GET['supervisor_id'])
);


/* ==============================
   CHECK SUPERVISOR EXISTS
============================== */

$check = mysqli_query(
    $conn,
    "
    SELECT
        supervisor_id,
        fullname,
        status

    FROM supervisors

    WHERE supervisor_id='$supervisor_id'
    "
);


if (!$check) {

    die(
        "Database error: " .
        mysqli_error($conn)
    );

}


if (mysqli_num_rows($check) == 0) {

    die("Supervisor not found.");

}


$supervisor = mysqli_fetch_assoc($check);


/* ==============================
   DEACTIVATE SUPERVISOR
============================== */

$update = mysqli_query(
    $conn,
    "
    UPDATE supervisors

    SET status='Inactive'

    WHERE supervisor_id='$supervisor_id'
    "
);


if (!$update) {

    die(
        "Unable to deactivate supervisor: " .
        mysqli_error($conn)
    );

}


/* ==============================
   ACTIVITY LOG
============================== */

logActivity(
    $conn,
    $admin_id,
    "Admin",
    "Deactivate Supervisor",
    "Deactivated supervisor: " .
    $supervisor['fullname']
);


/* ==============================
   REDIRECT
============================== */

header(
    "Location: manage_supervisors.php?success=deactivated"
);

exit();

?>