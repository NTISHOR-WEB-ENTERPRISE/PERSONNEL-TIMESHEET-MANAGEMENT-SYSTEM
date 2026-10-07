<?php

session_start();

include "config.php";
include "activity_logger.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SESSION['admin_role'] != "Super Admin") {
    die("Access Denied");
}

$admin_id   = $_SESSION['admin_id'];
$fullname   = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

/* =====================================================
   GET ADMIN PROFILE PHOTO
===================================================== */

$admin_profile_photo = "uploads/admins/default.png";

$stmt = $conn->prepare("
    SELECT fullname, profile_photo
    FROM admins
    WHERE admin_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $admin_id);
$stmt->execute();

$profile_result = $stmt->get_result();
$admin_data = $profile_result->fetch_assoc();

$stmt->close();

if ($admin_data) {

    // Use database fullname if available
    if (!empty($admin_data['fullname'])) {
        $fullname = $admin_data['fullname'];
    }

    if (!empty($admin_data['profile_photo'])) {

        $photo = basename(
            $admin_data['profile_photo']
        );

        $photo_path = "uploads/admins/" . $photo;

        if (file_exists($photo_path)) {

            $admin_profile_photo = $photo_path;

        }
    }
}

/* =========================================================
   GET SUPERVISOR ID
========================================================= */

if (
    !isset($_GET['supervisor_id']) ||
    empty($_GET['supervisor_id'])
) {
    die("Supervisor not specified.");
}

$supervisor_id = trim($_GET['supervisor_id']);


/* =========================================================
   FETCH SUPERVISOR
========================================================= */

$stmt = $conn->prepare("
    SELECT *
    FROM supervisors
    WHERE supervisor_id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$result = $stmt->get_result();

$supervisor = $result->fetch_assoc();

$stmt->close();


if (!$supervisor) {
    die("Supervisor not found.");
}


$message = "";
$message_class = "";


/* =========================================================
   HANDLE UPDATE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new_supervisor_id = trim($_POST['supervisor_id'] ?? '');
    $new_fullname      = trim($_POST['fullname'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $phone             = trim($_POST['phone'] ?? '');
    $department        = trim($_POST['department'] ?? '');
    $status            = $_POST['status'] ?? '';
    $password          = $_POST['password'] ?? '';

    /* Current profile photo */
    $current_photo = $supervisor['profile_photo'] ?? 'default.png';


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        empty($new_supervisor_id) ||
        empty($new_fullname)
    ) {

        $message =
            "Supervisor ID and Full Name are required.";

        $message_class = "error";

    } elseif (
        $status !== "Active" &&
        $status !== "Inactive"
    ) {

        $message =
            "Invalid supervisor status.";

        $message_class = "error";

    } else {


        /* =================================================
           CHECK DUPLICATE SUPERVISOR ID
        ================================================= */

        $check = $conn->prepare("
            SELECT id
            FROM supervisors
            WHERE supervisor_id = ?
            AND supervisor_id != ?
        ");

        if (!$check) {
            die("Prepare failed: " . $conn->error);
        }

        $check->bind_param(
            "ss",
            $new_supervisor_id,
            $supervisor_id
        );

        $check->execute();

        $duplicate = $check->get_result();

        $check->close();


        if ($duplicate->num_rows > 0) {

            $message =
                "That Supervisor ID is already being used.";

            $message_class = "error";

        } else {


            /* =================================================
               PROFILE PHOTO
            ================================================= */

            $new_photo = $current_photo;

            if (
                isset($_FILES['profile_photo']) &&
                $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if (
                    $_FILES['profile_photo']['error']
                    === UPLOAD_ERR_OK
                ) {

                    $upload_dir =
                        "uploads/supervisors/";

                    /*
                     * Create folder if it does not exist.
                     */

                    if (!is_dir($upload_dir)) {
                        mkdir(
                            $upload_dir,
                            0755,
                            true
                        );
                    }


                    $file_tmp =
                        $_FILES['profile_photo']['tmp_name'];

                    $file_name =
                        $_FILES['profile_photo']['name'];

                    $file_size =
                        $_FILES['profile_photo']['size'];


                    /* Get extension */

                    $extension =
                        strtolower(
                            pathinfo(
                                $file_name,
                                PATHINFO_EXTENSION
                            )
                        );


                    /* Allowed image types */

                    $allowed_extensions = [
                        'jpg',
                        'jpeg',
                        'png',
                        'gif',
                        'webp'
                    ];


                    if (
                        !in_array(
                            $extension,
                            $allowed_extensions
                        )
                    ) {

                        $message =
                            "Invalid profile photo format. " .
                            "Allowed formats: JPG, JPEG, PNG, GIF and WEBP.";

                        $message_class = "error";

                    } elseif ($file_size > 5 * 1024 * 1024) {

                        $message =
                            "Profile photo must not exceed 5MB.";

                        $message_class = "error";

                    } else {

                        /*
                         * Generate a unique filename.
                         */

                        $new_photo =
                            "supervisor_" .
                            preg_replace(
                                '/[^a-zA-Z0-9_-]/',
                                '_',
                                $new_supervisor_id
                            ) .
                            "_" .
                            time() .
                            "." .
                            $extension;


                        $destination =
                            $upload_dir .
                            $new_photo;


                        if (
                            !move_uploaded_file(
                                $file_tmp,
                                $destination
                            )
                        ) {

                            $message =
                                "Failed to upload the profile photo.";

                            $message_class = "error";

                            $new_photo =
                                $current_photo;

                        } else {

                            /*
                             * Delete old photo if it is not
                             * the default image.
                             */

                            if (
                                !empty($current_photo) &&
                                $current_photo !== "default.png"
                            ) {

                                $old_photo =
                                    $upload_dir .
                                    $current_photo;

                                if (
                                    file_exists(
                                        $old_photo
                                    )
                                ) {

                                    unlink(
                                        $old_photo
                                    );

                                }

                            }

                        }

                    }

                } else {

                    $message =
                        "There was an error uploading the profile photo.";

                    $message_class = "error";

                }

            }


            /* =================================================
               ONLY UPDATE DATABASE IF NO ERROR
            ================================================= */

            if ($message_class !== "error") {


                /* =================================================
                   UPDATE WITHOUT PASSWORD
                ================================================= */

                if (empty($password)) {

                    $update = $conn->prepare("
                        UPDATE supervisors
                        SET
                            supervisor_id = ?,
                            fullname = ?,
                            email = ?,
                            phone = ?,
                            department = ?,
                            status = ?,
                            profile_photo = ?
                        WHERE supervisor_id = ?
                    ");

                    if (!$update) {
                        die(
                            "Prepare failed: " .
                            $conn->error
                        );
                    }

                    $update->bind_param(
                        "ssssssss",
                        $new_supervisor_id,
                        $new_fullname,
                        $email,
                        $phone,
                        $department,
                        $status,
                        $new_photo,
                        $supervisor_id
                    );


                } else {


                    /* =================================================
                       UPDATE WITH PASSWORD
                    ================================================= */

                    $hashed_password =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                    $update = $conn->prepare("
                        UPDATE supervisors
                        SET
                            supervisor_id = ?,
                            fullname = ?,
                            email = ?,
                            phone = ?,
                            department = ?,
                            status = ?,
                            profile_photo = ?,
                            password = ?
                        WHERE supervisor_id = ?
                    ");

                    if (!$update) {
                        die(
                            "Prepare failed: " .
                            $conn->error
                        );
                    }

                    $update->bind_param(
                        "sssssssss",
                        $new_supervisor_id,
                        $new_fullname,
                        $email,
                        $phone,
                        $department,
                        $status,
                        $new_photo,
                        $hashed_password,
                        $supervisor_id
                    );

                }


                /* =================================================
                   EXECUTE UPDATE
                ================================================= */

                if ($update->execute()) {


                    /* =================================================
                       ACTIVITY LOG
                    ================================================= */

                   if (function_exists('logActivity')) {

    logActivity(
        $conn,
        $admin_id,
        "Admin",
        "Update Supervisor",
        "Updated supervisor: " .
        $new_fullname .
        " (" .
        $new_supervisor_id .
        ")"
    );

}


                    $update->close();


                    /*
                     * Redirect using the new Supervisor ID.
                     */

                    header(
                        "Location: edit_supervisor.php?supervisor_id=" .
                        urlencode($new_supervisor_id) .
                        "&success=1"
                    );

                    exit();

                } else {

                    $message =
                        "Failed to update supervisor: " .
                        $update->error;

                    $message_class = "error";

                    $update->close();

                }

            }

        }

    }

}


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

if (isset($_GET['success'])) {

    $message =
        "Supervisor information updated successfully.";

    $message_class = "success";

}


/* =========================================================
   REFRESH SUPERVISOR DATA
========================================================= */

$refresh_supervisor_id =
    isset($_GET['success'])
    ? $_GET['supervisor_id']
    : $supervisor_id;


$stmt = $conn->prepare("
    SELECT *
    FROM supervisors
    WHERE supervisor_id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "s",
    $refresh_supervisor_id
);

$stmt->execute();

$supervisor =
    $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$supervisor) {
    die("Supervisor not found.");
}


/* =========================================================
   PROFILE PHOTO FOR PREVIEW
========================================================= */

$profile_photo =
    !empty($supervisor['profile_photo'])
    ? $supervisor['profile_photo']
    : 'default.png';


$profile_photo_path =
    "uploads/supervisors/" .
    $profile_photo;


/*
 * Use default if the selected image does not exist.
 */

if (
    !file_exists($profile_photo_path)
) {

    $profile_photo_path =
        "uploads/supervisors/default.png";

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
Edit Supervisor
</title>

<link
rel="stylesheet"
href="styles.css"
>


<style>

/* =========================================================
   FORM CARD
========================================================= */

.form-card {

    background:white;

    padding:30px;

    border-radius:10px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);

    max-width:900px;

}


/* =========================================================
   FORM GRID
========================================================= */

.form-grid {

    display:grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap:20px;

}


/* =========================================================
   FORM GROUP
========================================================= */

.form-group {

    display:flex;

    flex-direction:column;

}


.form-group label {

    margin-bottom:7px;

    font-weight:bold;

}


.form-group input,
.form-group select {

    padding:11px;

    border:1px solid #ccc;

    border-radius:6px;

    font-size:14px;

}


.form-group input:focus,
.form-group select:focus {

    outline:none;

    border-color:#0d47a1;

}


/* =========================================================
   FULL WIDTH
========================================================= */

.form-group.full {

    grid-column:1 / -1;

}


/* =========================================================
   REQUIRED
========================================================= */

.required {

    color:red;

}


/* =========================================================
   MESSAGES
========================================================= */

.success {

    background:#d1e7dd;

    color:#0f5132;

    padding:12px;

    border-radius:6px;

    margin-bottom:20px;

}


.error {

    background:#f8d7da;

    color:#842029;

    padding:12px;

    border-radius:6px;

    margin-bottom:20px;

}


/* =========================================================
   PROFILE PHOTO SECTION
========================================================= */

.photo-section {

    grid-column:1 / -1;

    border:1px solid #e5e5e5;

    border-radius:10px;

    padding:20px;

    background:#fafafa;

}


.photo-content {

    display:flex;

    align-items:center;

    gap:25px;

}


.photo-preview {

    width:120px;

    height:120px;

    border-radius:50%;

    object-fit:cover;

    border:4px solid #e5e5e5;

    background:white;

}


.photo-info {

    flex:1;

}


.photo-info p {

    margin:5px 0 12px;

    color:#666;

    font-size:13px;

}


.photo-input {

    width:100%;

}


/* =========================================================
   PASSWORD NOTE
========================================================= */

.password-note {

    font-size:12px;

    color:#777;

    margin-top:5px;

}


/* =========================================================
   BUTTONS
========================================================= */

.form-actions {

    margin-top:25px;

    display:flex;

    gap:10px;

}


.btn-submit {

    background:#0d47a1;

    color:white;

    border:none;

    padding:11px 18px;

    border-radius:6px;

    cursor:pointer;

    font-weight:bold;

}


.btn-submit:hover {

    background:#083579;

}


.btn-cancel {

    background:#6c757d;

    color:white;

    padding:11px 18px;

    border-radius:6px;

    text-decoration:none;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:700px) {

    .form-grid {

        grid-template-columns:1fr;

    }


    .form-group.full,
    .photo-section {

        grid-column:auto;

    }


    .photo-content {

        flex-direction:column;

        text-align:center;

    }

}

</style>

</head>


<body>


<div class="container">


<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">


<div class="logo">

<h2>

<?php

echo htmlspecialchars(
    $app['organization_name']
);

?>

</h2>

<p>
Personnel Timesheet System
</p>

</div>


<div class="profile">

<div class="avatar">

    <img
        src="<?php echo htmlspecialchars($admin_profile_photo); ?>"
        class="profile-small"
        alt="Administrator Profile Photo"
        onerror="this.onerror=null; this.src='uploads/admins/default.png';"
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


<li>

<a href="manage_admins.php">
Manage Admins
</a>

</li>


<li class="active">

<a href="manage_supervisors.php">
Manage Supervisors
</a>

</li>


<li>

<a href="manage_staff.php">
Manage Staff
</a>

</li>


<li>

<a href="reports.php">
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


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">


<div class="topbar">

<div>

<h1>
Edit Supervisor
</h1>

<p>
Update all supervisor account information.
</p>

</div>


<div id="clock"></div>

</div>


<!-- =========================================================
     FORM
========================================================= -->

<div class="form-card">


<?php if ($message !== ""): ?>

<div class="<?php echo $message_class; ?>">

<?php

echo htmlspecialchars(
    $message
);

?>

</div>

<?php endif; ?>


<form
method="POST"
enctype="multipart/form-data"
>


<div class="form-grid">


<!-- =====================================================
     SUPERVISOR ID
===================================================== -->

<div class="form-group">

<label>

Supervisor ID
<span class="required">*</span>

</label>

<input

type="text"

name="supervisor_id"

value="<?php

echo htmlspecialchars(
    $supervisor['supervisor_id']
);

?>"

required

>

</div>


<!-- =====================================================
     FULL NAME
===================================================== -->

<div class="form-group">

<label>

Full Name
<span class="required">*</span>

</label>

<input

type="text"

name="fullname"

value="<?php

echo htmlspecialchars(
    $supervisor['fullname']
);

?>"

required

>

</div>


<!-- =====================================================
     EMAIL
===================================================== -->

<div class="form-group">

<label>
Email
</label>

<input

type="email"

name="email"

value="<?php

echo htmlspecialchars(
    $supervisor['email'] ?? ''
);

?>"

>

</div>


<!-- =====================================================
     PHONE
===================================================== -->

<div class="form-group">

<label>
Phone
</label>

<input

type="text"

name="phone"

value="<?php

echo htmlspecialchars(
    $supervisor['phone'] ?? ''
);

?>"

>

</div>


<!-- =====================================================
     DEPARTMENT
===================================================== -->

<div class="form-group">

<label>
Department
</label>

<input

type="text"

name="department"

value="<?php

echo htmlspecialchars(
    $supervisor['department'] ?? ''
);

?>"

>

</div>


<!-- =====================================================
     STATUS
===================================================== -->

<div class="form-group">

<label>
Status
</label>

<select name="status">


<option
value="Active"

<?php

if (
    ($supervisor['status'] ?? '')
    === 'Active'
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
    ($supervisor['status'] ?? '')
    === 'Inactive'
) {

    echo 'selected';

}

?>

>

Inactive

</option>


</select>

</div>


<!-- =====================================================
     PROFILE PHOTO
===================================================== -->

<div class="photo-section">


<label>

Profile Photo

</label>


<div class="photo-content">


<img

src="<?php

echo htmlspecialchars(
    $profile_photo_path
);

?>"

class="photo-preview"

id="photoPreview"

alt="Supervisor Profile Photo"

onerror="this.src='uploads/supervisors/default.png';"

>


<div class="photo-info">

<p>

Current profile photo. Select a new image
to replace it.

</p>


<input

type="file"

name="profile_photo"

class="photo-input"

id="profilePhoto"

accept="image/jpeg,image/png,image/gif,image/webp"

>


<p>

Maximum file size: 5MB.
Allowed: JPG, JPEG, PNG, GIF and WEBP.

</p>

</div>


</div>


</div>


<!-- =====================================================
     PASSWORD
===================================================== -->

<div class="form-group full">

<label>
New Password
</label>

<input

type="password"

name="password"

placeholder="Leave blank to keep current password"

>

<span class="password-note">

Only enter a password if you want to change
the supervisor's current password.

</span>

</div>


</div>


<!-- =====================================================
     ACTIONS
===================================================== -->

<div class="form-actions">


<button

type="submit"

class="btn-submit"

>

💾 Save Changes

</button>


<a

href="manage_supervisors.php"

class="btn-cancel"

>

Cancel

</a>


</div>


</form>


</div>


</div>


</div>


<!-- =========================================================
     CLOCK
========================================================= -->

<script>

function updateClock(){

    const clock =
        document.getElementById("clock");

    if (clock) {

        clock.innerHTML =
            new Date().toLocaleTimeString();

    }

}

updateClock();

setInterval(
    updateClock,
    1000
);


/* =========================================================
   PROFILE PHOTO PREVIEW
========================================================= */

const photoInput =
    document.getElementById("profilePhoto");

const photoPreview =
    document.getElementById("photoPreview");


if (photoInput) {

    photoInput.addEventListener(
        "change",
        function(event) {

            const file =
                event.target.files[0];

            if (file) {

                const reader =
                    new FileReader();

                reader.onload =
                    function(e) {

                        photoPreview.src =
                            e.target.result;

                    };

                reader.readAsDataURL(file);

            }

        }
    );

}

</script>


</body>

</html>