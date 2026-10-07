<?php
session_start();
include "config.php";
include "activity_logger.php";

/* ============================== ADMIN AUTHENTICATION ============================== */

if (!isset($_SESSION['admin_id'])) {

    header("Location: admin_login.php");
    exit();

}

/* ============================== ONLY SUPER ADMIN ============================== */

if ($_SESSION['admin_role'] != "Super Admin") {

    die("Access Denied");

}

$current_admin_id = $_SESSION['admin_id'];

/* ============================== GET ADMIN ID ============================== */

if (!isset($_GET['id']) || empty($_GET['id'])) {

    die("Admin ID not provided.");

}

$id = (int) $_GET['id'];


/* ============================== GET ADMIN ============================== */

$query = mysqli_query(
    $conn,
    "
    SELECT id, admin_id, fullname, role, status
    FROM admins
    WHERE id='$id'
    "
);

if (!$query) {

    die(
        "Database error: "
        . mysqli_error($conn)
    );

}

if (mysqli_num_rows($query) == 0) {

    die("Administrator not found.");

}


$admin = mysqli_fetch_assoc($query);

/* ============================== CHECK CURRENT STATUS ============================== */

if ($admin['status'] == "Active") {

    header("Location: manage_admins.php");
    exit();

}

/* ============================== ACTIVATE ADMIN ============================== */

$update = mysqli_query(
    $conn,
    "
    UPDATE admins
    SET status='Active'
    WHERE id='$id'
    "
);

if (!$update) {

    die(
        "Unable to activate administrator: "
        . mysqli_error($conn)
    );

}

/* ============================== ACTIVITY LOG ============================== */

logActivity(
    $conn,
    $current_admin_id,
    "Admin",
    "Activate Admin",
    "Activated administrator: " . $admin['fullname']
);

/* ============================== RETURN ============================== */

header(
    "Location: manage_admins.php?success=activated"
);

exit();
?>