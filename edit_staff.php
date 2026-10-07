<?php

session_start();

require_once "config.php";

/* =====================================================
   ADMIN AUTHENTICATION
===================================================== */

if (!isset($_SESSION['admin_id'])) {

    header("Location: admin_login.php");
    exit();

}

$admin_id   = $_SESSION['admin_id'];
$fullname   = $_SESSION['fullname'] ?? '';
$admin_role = $_SESSION['admin_role'] ?? '';


/* =====================================================
   GET STAFF ID
===================================================== */

$staff_id = $_GET['staff_id'] ?? '';

$staff_id = trim($staff_id);

if ($staff_id === '') {

    die("Invalid staff ID.");

}


/* =====================================================
   GET STAFF INFORMATION
===================================================== */

$stmt = $conn->prepare("
    SELECT
        id,
        staff_id,
        fullname,
        email,
        password,
        department,
        position,
        status,
        created_at,
        profile_picture,
        phone,
        supervisor_id
    FROM staff
    WHERE staff_id = ?
    LIMIT 1
");

if (!$stmt) {

    die("Database error: " . $conn->error);

}

$stmt->bind_param(
    "s",
    $staff_id
);

$stmt->execute();

$result = $stmt->get_result();

$staff = $result->fetch_assoc();

$stmt->close();


if (!$staff) {

    die("Staff member not found.");

}


/* =====================================================
   GET SUPERVISORS
===================================================== */

$supervisors = [];

$supervisor_sql = "
    SELECT
        supervisor_id,
        fullname
    FROM supervisors
    ORDER BY fullname ASC
";

$supervisor_result =
    $conn->query($supervisor_sql);

if ($supervisor_result) {

    while (
        $supervisor =
        $supervisor_result->fetch_assoc()
    ) {

        $supervisors[] =
            $supervisor;

    }

}


/* =====================================================
   FORM VARIABLES
===================================================== */

$message = '';

$error = '';


/* =====================================================
   UPDATE STAFF
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =================================================
       GET FORM DATA
    ================================================= */

    $new_staff_id =
        trim($_POST['staff_id'] ?? '');

    $new_fullname =
        trim($_POST['fullname'] ?? '');

    $new_email =
        trim($_POST['email'] ?? '');

    $new_phone =
        trim($_POST['phone'] ?? '');

    $new_department =
        trim($_POST['department'] ?? '');

    $new_position =
        trim($_POST['position'] ?? '');

    $new_supervisor_id =
        trim($_POST['supervisor_id'] ?? '');

    $new_status =
        trim($_POST['status'] ?? '');

    $new_password =
        $_POST['password'] ?? '';

    $confirm_password =
        $_POST['confirm_password'] ?? '';


    /* =================================================
       BASIC VALIDATION
    ================================================= */

    if (
        $new_staff_id === '' ||
        $new_fullname === ''
    ) {

        $error =
            "Staff ID and Full Name are required.";

    }


    /* =================================================
       EMAIL VALIDATION
    ================================================= */

    elseif (
        $new_email !== '' &&
        !filter_var(
            $new_email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    }


    /* =================================================
       STATUS VALIDATION
    ================================================= */

    elseif (
        !in_array(
            $new_status,
            ['Active', 'Inactive']
        )
    ) {

        $error =
            "Invalid staff status.";

    }


    /* =================================================
       PASSWORD VALIDATION
    ================================================= */

    elseif (
        $new_password !== '' &&
        $new_password !== $confirm_password
    ) {

        $error =
            "Passwords do not match.";

    }


    /* =================================================
       CHECK STAFF ID
    ================================================= */

    if ($error === '') {

        $stmt = $conn->prepare("
            SELECT id
            FROM staff
            WHERE staff_id = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "si",
            $new_staff_id,
            $staff['id']
        );

        $stmt->execute();

        $duplicate =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if ($duplicate) {

            $error =
                "That Staff ID is already assigned to another staff member.";

        }

    }


    /* =================================================
       CHECK EMAIL
    ================================================= */

    if (
        $error === '' &&
        $new_email !== ''
    ) {

        $stmt = $conn->prepare("
            SELECT id
            FROM staff
            WHERE email = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "si",
            $new_email,
            $staff['id']
        );

        $stmt->execute();

        $duplicate =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if ($duplicate) {

            $error =
                "That email address is already assigned to another staff member.";

        }

    }


    /* =================================================
       CHECK PHONE
    ================================================= */

    if (
        $error === '' &&
        $new_phone !== ''
    ) {

        $stmt = $conn->prepare("
            SELECT id
            FROM staff
            WHERE phone = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "si",
            $new_phone,
            $staff['id']
        );

        $stmt->execute();

        $duplicate =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if ($duplicate) {

            $error =
                "That phone number is already assigned to another staff member.";

        }

    }


    /* =================================================
       PROFILE PICTURE
    ================================================= */

    $profile_picture =
        $staff['profile_picture'];


    if (
        $error === '' &&
        isset($_FILES['profile_picture']) &&
        $_FILES['profile_picture']['error']
        !== UPLOAD_ERR_NO_FILE
    ) {


        if (
            $_FILES['profile_picture']['error']
            !== UPLOAD_ERR_OK
        ) {

            $error =
                "There was an error uploading the profile picture.";

        }


        /* ---------------------------------------------
           FILE SIZE
        --------------------------------------------- */

        elseif (
            $_FILES['profile_picture']['size']
            > 5 * 1024 * 1024
        ) {

            $error =
                "Profile picture must not exceed 5MB.";

        }


        /* ---------------------------------------------
           FILE TYPE
        --------------------------------------------- */

        else {

            $allowed_types = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];


            $file_type =
                mime_content_type(
                    $_FILES['profile_picture']['tmp_name']
                );


            if (
                !isset(
                    $allowed_types[$file_type]
                )
            ) {

                $error =
                    "Only JPG, PNG, GIF and WEBP images are allowed.";

            }

            else {

                $extension =
                    $allowed_types[$file_type];


                $upload_directory =
                    "uploads/staff/";


                if (
                    !is_dir(
                        $upload_directory
                    )
                ) {

                    mkdir(
                        $upload_directory,
                        0755,
                        true
                    );

                }


                $new_filename =
                    $new_staff_id .
                    '_' .
                    time() .
                    '.' .
                    $extension;


                $destination =
                    $upload_directory .
                    $new_filename;


                if (
                    move_uploaded_file(
                        $_FILES['profile_picture']['tmp_name'],
                        $destination
                    )
                ) {

                    /*
                     * Save only the filename
                     * in the database.
                     */

                    $profile_picture =
                        $new_filename;


                    /*
                     * Delete old profile picture.
                     */

                    if (
                        !empty(
                            $staff['profile_picture']
                        )
                    ) {

                        $old_picture =
                            basename(
                                $staff['profile_picture']
                            );


                        $old_path =
                            $upload_directory .
                            $old_picture;


                        if (
                            file_exists(
                                $old_path
                            )
                        ) {

                            unlink(
                                $old_path
                            );

                        }

                    }

                }

                else {

                    $error =
                        "Unable to save the profile picture.";

                }

            }

        }

    }


    /* =================================================
       PERFORM DATABASE UPDATE
    ================================================= */

    if ($error === '') {


        /*
         * If password was supplied,
         * update the password too.
         */

        if ($new_password !== '') {


            $hashed_password =
                password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );


            $stmt = $conn->prepare("
                UPDATE staff
                SET
                    staff_id = ?,
                    fullname = ?,
                    email = NULLIF(?, ''),
                    password = ?,
                    department = NULLIF(?, ''),
                    position = NULLIF(?, ''),
                    status = ?,
                    profile_picture = NULLIF(?, ''),
                    phone = NULLIF(?, ''),
                    supervisor_id = NULLIF(?, '')
                WHERE id = ?
            ");


            $stmt->bind_param(
                "ssssssssssi",
                $new_staff_id,
                $new_fullname,
                $new_email,
                $hashed_password,
                $new_department,
                $new_position,
                $new_status,
                $profile_picture,
                $new_phone,
                $new_supervisor_id,
                $staff['id']
            );

        }


        /*
         * Password was not changed.
         */

        else {

            $stmt = $conn->prepare("
                UPDATE staff
                SET
                    staff_id = ?,
                    fullname = ?,
                    email = NULLIF(?, ''),
                    department = NULLIF(?, ''),
                    position = NULLIF(?, ''),
                    status = ?,
                    profile_picture = NULLIF(?, ''),
                    phone = NULLIF(?, ''),
                    supervisor_id = NULLIF(?, '')
                WHERE id = ?
            ");


            $stmt->bind_param(
                "sssssssssi",
                $new_staff_id,
                $new_fullname,
                $new_email,
                $new_department,
                $new_position,
                $new_status,
                $profile_picture,
                $new_phone,
                $new_supervisor_id,
                $staff['id']
            );

        }


        if ($stmt->execute()) {

            $stmt->close();


            /*
             * Redirect using the NEW Staff ID.
             */

            header(
                "Location: edit_staff.php?staff_id=" .
                urlencode($new_staff_id) .
                "&updated=1"
            );

            exit();

        }


        else {

            $error =
                "Unable to update staff information: "
                . $stmt->error;

            $stmt->close();

        }

    }

}


/* =====================================================
   SUCCESS MESSAGE
===================================================== */

if (
    isset($_GET['updated']) &&
    $_GET['updated'] == '1'
) {

    $message =
        "Staff information updated successfully.";

}


/* =====================================================
   RELOAD STAFF INFORMATION
===================================================== */

$stmt = $conn->prepare("
    SELECT
        id,
        staff_id,
        fullname,
        email,
        department,
        position,
        status,
        created_at,
        profile_picture,
        phone,
        supervisor_id
    FROM staff
    WHERE staff_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $staff_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$staff =
    $result->fetch_assoc();

$stmt->close();


if (!$staff) {

    die("Staff member no longer exists.");

}


/* =====================================================
   PROFILE IMAGE
===================================================== */

$profile_image =
    "uploads/staff/default.png";


if (
    !empty(
        $staff['profile_picture']
    )
) {

    $stored_picture =
        basename(
            $staff['profile_picture']
        );


    $possible_path =
        "uploads/staff/" .
        $stored_picture;


    if (
        file_exists(
            $possible_path
        )
    ) {

        $profile_image =
            $possible_path;

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Edit Staff - <?php echo htmlspecialchars($staff['fullname']); ?>
</title>

<style>

/* =====================================================
   GLOBAL
===================================================== */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #333;
}


/* =====================================================
   LAYOUT
===================================================== */

.container {
    display: flex;
    min-height: 100vh;
}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {
    width: 220px;
    min-width: 220px;
    background: #888;
    min-height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    overflow-y: auto;
    z-index: 1000;
}


/* =====================================================
   LOGO
===================================================== */

.logo {
    text-align: center;
    color: white;
    padding: 20px 10px 15px;
}

.logo h2 {
    margin: 0;
    font-size: 18px;
    line-height: 1.4;
}

.logo p {
    margin: 6px 0 0;
    font-size: 11px;
    color: #eee;
}


/* =====================================================
   PROFILE
===================================================== */

.profile {
    text-align: center;
    color: white;
    padding: 10px 8px 18px;
}

.avatar {
    width: 75px;
    height: 75px;
    margin: 0 auto 10px;
    border-radius: 50%;
    overflow: hidden;
    background: #ddd;
    border: 3px solid white;
}

.profile-small {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile h3 {
    margin: 6px 0 4px;
    font-size: 13px;
    text-transform: uppercase;
}

.profile p {
    margin: 0;
    font-size: 11px;
    color: #f1f1f1;
}


/* =====================================================
   MENU
===================================================== */

.sidebar ul {
    list-style: none;
    padding: 0;
    margin: 10px 15px;
}

.sidebar ul li {
    margin-bottom: 8px;
}

.sidebar ul li a {
    display: block;
    text-decoration: none;
    color: white;
    background: #0866d6;
    padding: 12px 12px;
    border-radius: 5px;
    font-size: 13px;
    font-weight: bold;
    transition: 0.2s;
}

.sidebar ul li a:hover {
    background: #0754b5;
}

.sidebar ul li.active a {
    background: #06418f;
}


/* =====================================================
   MAIN
===================================================== */

.main {
    margin-left: 220px;
    width: calc(100% - 220px);
    min-height: 100vh;
    padding: 25px;
}


/* =====================================================
   TOPBAR
===================================================== */

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.topbar h1 {
    margin: 0 0 5px;
    color: #0056b3;
    font-size: 26px;
}

.topbar p {
    margin: 0;
    color: #777;
    font-size: 14px;
}

#clock {
    color: #0056b3;
    font-size: 14px;
    font-weight: bold;
}


/* =====================================================
   CONTENT
===================================================== */

.edit-container {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    max-width: 1100px;
}


/* =====================================================
   ALERTS
===================================================== */

.alert {
    padding: 14px 16px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}


/* =====================================================
   SECTION
===================================================== */

.section {
    margin-bottom: 30px;
}

.section-title {
    font-size: 18px;
    color: #0056b3;
    border-bottom: 2px solid #e5e5e5;
    padding-bottom: 10px;
    margin-bottom: 20px;
}


/* =====================================================
   PROFILE HEADER
===================================================== */

.profile-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
    padding: 20px;
    background: #f5f7fa;
    border-radius: 10px;
}

.staff-photo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}

.profile-header h2 {
    margin: 0 0 5px;
    color: #333;
}

.profile-header p {
    margin: 3px 0;
    color: #777;
}


/* =====================================================
   FORM GRID
===================================================== */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 7px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 7px;
    font-size: 14px;
    outline: none;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,.1);
}

/* =====================================================
   INFORMATION SECTIONS - SIDE BY SIDE
===================================================== */

.info-sections {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    align-items: start;
}

.info-sections .section {
    background: #f8f9fa;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    padding: 18px;
    margin-bottom: 0;
}

.info-sections .section-title {
    font-size: 16px;
    margin-bottom: 18px;
}

.info-sections .form-grid {
    grid-template-columns: 1fr;
    gap: 15px;
}

/* Tablet */
@media(max-width: 1100px) {
    .info-sections {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Mobile */
@media(max-width: 700px) {
    .info-sections {
        grid-template-columns: 1fr;
    }
}

/* =====================================================
   PASSWORD NOTE
===================================================== */

.password-note {
    background: #fff3cd;
    color: #856404;
    padding: 12px;
    border-radius: 7px;
    font-size: 13px;
    margin-bottom: 18px;
}


/* =====================================================
   PHOTO UPLOAD
===================================================== */

.photo-upload {
    display: flex;
    align-items: center;
    gap: 20px;
}

.photo-upload img {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #ddd;
}


/* =====================================================
   BUTTONS
===================================================== */

.actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.btn {
    display: inline-block;
    border: none;
    padding: 12px 20px;
    border-radius: 7px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width: 800px) {

    .sidebar {
        width: 180px;
        min-width: 180px;
    }

    .main {
        margin-left: 180px;
        width: calc(100% - 180px);
        padding: 15px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

}

@media(max-width: 600px) {

    .sidebar {
        width: 150px;
        min-width: 150px;
    }

    .main {
        margin-left: 150px;
        width: calc(100% - 150px);
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .edit-container {
        padding: 18px;
    }

    .profile-header {
        flex-direction: column;
        text-align: center;
    }

}


/* =====================================================
   PRINT
===================================================== */

@media print {

    .sidebar,
    .topbar,
    .actions {
        display: none !important;
    }

    .main {
        margin: 0;
        width: 100%;
        padding: 0;
    }

    .edit-container {
        box-shadow: none;
    }

}

</style>

</head>

<body>

<div class="container">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">

    <div class="logo">

        <h2>
            <?php
            echo htmlspecialchars(
                $app['organization_name']
                ?? 'NTISHOR WEB ENTERPRISE'
            );
            ?>
        </h2>

        <p>
            Personnel Timesheet System
        </p>

    </div>


    <div class="profile">

        <div class="avatar">

            <?php

            $admin_photo =
                "uploads/admins/default.png";

            $admin_stmt =
                $conn->prepare("
                    SELECT profile_photo
                    FROM admins
                    WHERE admin_id = ?
                    LIMIT 1
                ");

            if ($admin_stmt) {

                $admin_stmt->bind_param(
                    "s",
                    $admin_id
                );

                $admin_stmt->execute();

                $admin_result =
                    $admin_stmt->get_result();

                $admin_row =
                    $admin_result->fetch_assoc();

                $admin_stmt->close();


                if (
                    $admin_row &&
                    !empty(
                        $admin_row['profile_photo']
                    )
                ) {

                    $admin_file =
                        basename(
                            $admin_row['profile_photo']
                        );

                    $admin_path =
                        "uploads/admins/" .
                        $admin_file;


                    if (
                        file_exists(
                            $admin_path
                        )
                    ) {

                        $admin_photo =
                            $admin_path;

                    }

                }

            }

            ?>

            <img
                src="<?php echo htmlspecialchars($admin_photo); ?>"
                class="profile-small"
                alt="Admin Profile"
                onerror="
                    this.onerror=null;
                    this.src='uploads/admins/default.png';
                "
            >

        </div>


        <h3>

            <?php

            echo htmlspecialchars(
                $fullname
            );

            ?>

        </h3>


        <p>

            <?php

            echo htmlspecialchars(
                $admin_role
            );

            ?>

        </p>

    </div>


    <ul>

        <li>

            <a href="admin_dashboard.php">
                Dashboard
            </a>

        </li>


        <?php if (
            $admin_role === 'Super Admin'
        ): ?>

            <li>

                <a href="manage_admins.php">
                    Manage Admins
                </a>

            </li>

        <?php endif; ?>


        <li>

            <a href="manage_supervisors.php">
                Manage Supervisors
            </a>

        </li>


        <li class="active">

            <a href="manage_staff.php">
                Manage Staff
            </a>

        </li>


        <li>

            <a href="admin_attendance_report.php">
                Attendance Report
            </a>

        </li>


        <li>

            <a href="admin_staff_hours_report.php">
                Staff Hours
            </a>

        </li>


        <li>

            <a href="admin_reports.php">
                Reports
            </a>

        </li>


        <li>

            <a href="admin_logout.php">
                Logout
            </a>

        </li>

    </ul>

</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


    <div class="topbar">

        <div>

            <h1>
                Edit Staff
            </h1>

            <p>
                Update complete staff information
            </p>

        </div>


        <div id="clock"></div>

    </div>


    <div class="edit-container">


        <!-- =================================================
             MESSAGES
        ================================================= -->

        <?php if ($message !== ''): ?>

            <div class="alert alert-success">

                <?php

                echo htmlspecialchars(
                    $message
                );

                ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="alert alert-error">

                <?php

                echo htmlspecialchars(
                    $error
                );

                ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             STAFF HEADER
        ================================================= -->

        <div class="profile-header">

            <img
                src="<?php echo htmlspecialchars($profile_image); ?>"
                class="staff-photo"
                alt="Staff Profile"
                onerror="
                    this.onerror=null;
                    this.src='uploads/staff/default.png';
                "
            >

            <div>

                <h2>

                    <?php

                    echo htmlspecialchars(
                        $staff['fullname']
                    );

                    ?>

                </h2>

                <p>

                    Staff ID:
                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $staff['staff_id']
                        );

                        ?>

                    </strong>

                </p>

                <p>

                    <?php

                    echo htmlspecialchars(
                        $staff['department']
                        ?: 'No Department'
                    );

                    ?>

                </p>

            </div>

        </div>


        <!-- =================================================
             FORM
        ================================================= -->

        <form
            method="POST"
            enctype="multipart/form-data"
        >

<div class="info-sections">
            <!-- =================================================
                 PERSONAL INFORMATION
            ================================================= -->

            <div class="section">

                <div class="section-title">
                    Personal Information
                </div>


                <div class="form-grid">


                    <!-- STAFF ID -->

                    <div class="form-group">

                        <label>
                            Staff ID *
                        </label>

                        <input
                            type="text"
                            name="staff_id"
                            value="<?php
                                echo htmlspecialchars(
                                    $staff['staff_id']
                                );
                            ?>"
                            required
                        >

                    </div>


                    <!-- FULL NAME -->

                    <div class="form-group">

                        <label>
                            Full Name *
                        </label>

                        <input
                            type="text"
                            name="fullname"
                            value="<?php
                                echo htmlspecialchars(
                                    $staff['fullname']
                                );
                            ?>"
                            required
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label>
                            Email Address
                        </label>

                        <input
                            type="email"
                            name="email"
                            value="<?php
                                echo htmlspecialchars(
                                    $staff['email'] ?? ''
                                );
                            ?>"
                        >

                    </div>


                    <!-- PHONE -->

                    <div class="form-group">

                        <label>
                            Phone / WhatsApp
                        </label>

                        <input
                            type="text"
                            name="phone"
                            value="<?php
                                echo htmlspecialchars(
                                    $staff['phone'] ?? ''
                                );
                            ?>"
                        >

                    </div>


                    <!-- PROFILE PICTURE -->

                    <div class="form-group full">

                        <label>
                            Profile Picture
                        </label>


                        <div class="photo-upload">

                            <img
                                src="<?php
                                    echo htmlspecialchars(
                                        $profile_image
                                    );
                                ?>"
                                id="preview"
                                alt="Profile Preview"
                            >


                            <input
                                type="file"
                                name="profile_picture"
                                accept="
                                    image/jpeg,
                                    image/png,
                                    image/gif,
                                    image/webp
                                "
                                onchange="previewImage(this)"
                            >

                        </div>

                    </div>


                </div>

            </div>


            <!-- =================================================
                 EMPLOYMENT INFORMATION
            ================================================= -->

            <div class="section">

                <div class="section-title">
                    Employment Information
                </div>


                <div class="form-grid">


                    <!-- DEPARTMENT -->

                    <div class="form-group">

                        <label>
                            Department
                        </label>

                        <input
                            type="text"
                            name="department"
                            value="<?php
                                echo htmlspecialchars(
                                    $staff['department'] ?? ''
                                );
                            ?>"
                        >

                    </div>


                    <!-- POSITION -->

                    <div class="form-group">

                        <label>
                            Position
                        </label>

                        <input
                            type="text"
                            name="position"
                            value="<?php
                                echo htmlspecialchars(
                                    $staff['position'] ?? ''
                                );
                            ?>"
                        >

                    </div>


                    <!-- SUPERVISOR -->

                    <div class="form-group">

                        <label>
                            Supervisor
                        </label>

                        <select
                            name="supervisor_id"
                        >

                            <option value="">
                                No Supervisor
                            </option>


                            <?php foreach (
                                $supervisors
                                as $supervisor
                            ): ?>

                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $supervisor[
                                                'supervisor_id'
                                            ]
                                        );
                                    ?>"
                                    <?php

                                    if (
                                        ($staff[
                                            'supervisor_id'
                                        ] ?? '')
                                        ===
                                        $supervisor[
                                            'supervisor_id'
                                        ]
                                    ) {

                                        echo 'selected';

                                    }

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $supervisor[
                                            'fullname'
                                        ]
                                    );

                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- STATUS -->

                    <div class="form-group">

                        <label>
                            Status
                        </label>

                        <select
                            name="status"
                        >

                            <option
                                value="Active"
                                <?php

                                if (
                                    strtolower(
                                        $staff['status']
                                    )
                                    ===
                                    'active'
                                ) {

                                    echo 'selected';

                                }

                                ?>
                            >
                                Active
                            </option>


                            <option
                                value="Inactive"
                                <?php

                                if (
                                    strtolower(
                                        $staff['status']
                                    )
                                    ===
                                    'inactive'
                                ) {

                                    echo 'selected';

                                }

                                ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </div>


                </div>

            </div>


            <!-- =================================================
                 ACCOUNT INFORMATION
            ================================================= -->

            <div class="section">

                <div class="section-title">
                    Account Information
                </div>


                <div class="password-note">

                    Leave the password fields empty if you
                    do not want to change the staff member's
                    password.

                </div>


                <div class="form-grid">


                    <!-- NEW PASSWORD -->

                    <div class="form-group">

                        <label>
                            New Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            placeholder="Leave blank to keep current password"
                        >

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label>
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            autocomplete="new-password"
                            placeholder="Confirm new password"
                        >

                    </div>


                </div>

            </div>


            <!-- =================================================
                 SYSTEM INFORMATION
            ================================================= -->

            <div class="section">

                <div class="section-title">
                    System Information
                </div>


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Database ID
                        </label>

                        <input
                            type="text"
                            value="<?php
                                echo htmlspecialchars(
                                    $staff['id']
                                );
                            ?>"
                            disabled
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Account Created
                        </label>

                        <input
                            type="text"
                            value="<?php

                                echo !empty(
                                    $staff['created_at']
                                )

                                ? date(
                                    'd M Y, h:i A',
                                    strtotime(
                                        $staff['created_at']
                                    )
                                )

                                : 'N/A';

                            ?>"
                            disabled
                        >

                    </div>


                </div>

            </div>

        </div>
            <!-- =================================================
                 ACTIONS
            ================================================= -->

            <div class="actions">

                <a
                    href="manage_staff.php"
                    class="btn btn-secondary"
                >
                    ← Back to Staff
                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Staff Information
                </button>

            </div>


        </form>


    </div>

</div>

</div>


<script>

/* =====================================================
   LIVE CLOCK
===================================================== */

function updateClock() {

    const clock =
        document.getElementById("clock");

    if (!clock) {
        return;
    }

    clock.innerHTML =
        new Date().toLocaleTimeString(
            'en-GB',
            {
                hour12: false
            }
        );

}

updateClock();

setInterval(
    updateClock,
    1000
);


/* =====================================================
   PROFILE IMAGE PREVIEW
===================================================== */

function previewImage(input) {

    const preview =
        document.getElementById("preview");

    if (
        input.files &&
        input.files[0]
    ) {

        const reader =
            new FileReader();

        reader.onload =
            function(event) {

                preview.src =
                    event.target.result;

            };

        reader.readAsDataURL(
            input.files[0]
        );

    }

}

</script>

</body>

</html>