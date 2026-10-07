<?php
session_start();
include "config.php";
include "activity_logger.php";

/* ============================== ADMIN AUTHENTICATION ============================== */

if (!isset($_SESSION['admin_id'])) {

    header("Location: admin_login.php");
    exit();

}

if ($_SESSION['admin_role'] != "Super Admin") {

    die("Access Denied");

}

$admin_id = $_SESSION['admin_id'];

/* ============================== GET SUPERVISOR ID ============================== */

if (!isset($_GET['supervisor_id']) || empty($_GET['supervisor_id'])) {

    die("Supervisor ID not provided.");

}

$supervisor_id = mysqli_real_escape_string(
    $conn,
    trim($_GET['supervisor_id'])
);

/* ============================== CHECK SUPERVISOR ============================== */

$check = mysqli_query(
    $conn,
    "SELECT id, fullname, status
     FROM supervisors
     WHERE supervisor_id='$supervisor_id'"
);

if (!$check) {

    die("Database error: " . mysqli_error($conn));

}

if (mysqli_num_rows($check) == 0) {

    die("Supervisor not found.");

}

$supervisor = mysqli_fetch_assoc($check);

/* ============================== ACTIVATE ============================== */

$update = mysqli_query(
    $conn,
    "UPDATE supervisors
     SET status='Active'
     WHERE supervisor_id='$supervisor_id'"
);

if ($update) {

    logActivity(
        $conn,
        $admin_id,
        "Admin",
        "Activate Supervisor",
        "Activated supervisor: " . $supervisor['fullname']
    );

    header("Location: manage_supervisors.php?success=activated");
    exit();

}

die(
    "Unable to activate supervisor: "
    . mysqli_error($conn)
);

?>