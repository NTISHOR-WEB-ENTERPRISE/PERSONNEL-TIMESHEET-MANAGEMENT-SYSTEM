<?php
session_start();
include "config.php";
include "activity_logger.php";

if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}

if($_SESSION['admin_role']!="Super Admin"){
    die("Access Denied");
}

$fullname = $_SESSION['fullname'];
$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'];

/* ===================================================== GET ADMIN PROFILE PHOTO ===================================================== */

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

$message = "";
$error = "";

/* ============================== GET SUPERVISORS ============================== */

$supervisors = mysqli_query($conn,"
    SELECT supervisor_id, fullname, department
    FROM supervisors
    ORDER BY fullname ASC
");

/* ============================== ADD STAFF ============================== */

if(isset($_POST['save'])){

    $staff_id = mysqli_real_escape_string(
        $conn,
        trim($_POST['staff_id'])
    );

    $staff_name = mysqli_real_escape_string(
        $conn,
        trim($_POST['fullname'])
    );

    $email = mysqli_real_escape_string(
        $conn,
        trim($_POST['email'])
    );

    $phone = mysqli_real_escape_string(
        $conn,
        trim($_POST['phone'])
    );

    $department = mysqli_real_escape_string(
        $conn,
        trim($_POST['department'])
    );

    $position = mysqli_real_escape_string(
        $conn,
        trim($_POST['position'])
    );

    $status = mysqli_real_escape_string(
        $conn,
        $_POST['status']
    );

    $supervisor_id = mysqli_real_escape_string(
        $conn,
        $_POST['supervisor_id']
    );

    $password = $_POST['password'];

    /* ============================== CHECK STAFF ID ============================== */

    $check_id = mysqli_query($conn,"

        SELECT id
        FROM staff
        WHERE staff_id='$staff_id'

    ");

    if(mysqli_num_rows($check_id) > 0){

        $error = "Staff ID already exists.";

    }

    /* ============================== CHECK EMAIL ============================== */

    elseif($email != ""){

        $check_email = mysqli_query($conn,"

            SELECT id
            FROM staff
            WHERE email='$email'

        ");

        if(mysqli_num_rows($check_email) > 0){

            $error = "Email address already exists.";

        }

    }

    /* ============================== CHECK PHONE ============================== */

    if($error == "" && $phone != ""){

        $check_phone = mysqli_query($conn,"

            SELECT id
            FROM staff
            WHERE phone='$phone'

        ");

        if(mysqli_num_rows($check_phone) > 0){

            $error = "Phone number already exists.";

        }

    }

    /* ============================== PASSWORD ============================== */

    if($error == "" && empty($password)){

        $error = "Password is required.";

    }

    /* ============================== PROFILE PHOTO ============================== */

    $photo = "default.png";

    if($error == "" && !empty($_FILES['profile_picture']['name'])){

        $extension = strtolower(
            pathinfo(
                $_FILES['profile_picture']['name'],
                PATHINFO_EXTENSION
            )
        );

        $allowed = array(
            "jpg",
            "jpeg",
            "png"
        );


        if(!in_array($extension,$allowed)){

            $error = "Only JPG, JPEG and PNG images are allowed.";

        }else{

            $upload_directory = "uploads/staff/";


            if(!is_dir($upload_directory)){

                mkdir(
                    $upload_directory,
                    0777,
                    true
                );

            }


            $photo =
                time()
                . "_"
                . basename($_FILES['profile_picture']['name']);


            if(!move_uploaded_file(
                $_FILES['profile_picture']['tmp_name'],
                $upload_directory.$photo
            )){

                $error = "Unable to upload profile picture.";

            }
        }
    }

    /* ============================== INSERT STAFF ============================== */

    if($error == ""){

        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $supervisor_value =
            ($supervisor_id == "")
            ? "NULL"
            : "'$supervisor_id'";


        $insert = mysqli_query($conn,"

            INSERT INTO staff(

                staff_id,
                fullname,
                email,
                password,
                department,
                position,
                status,
                profile_picture,
                phone,
                supervisor_id

            )

            VALUES(

                '$staff_id',
                '$staff_name',
                '$email',
                '$hashed_password',
                '$department',
                '$position',
                '$status',
                '$photo',
                '$phone',
                $supervisor_value

            )

        ");


        if($insert){

            logActivity(

                $conn,

                $admin_id,

                "Admin",

                "Add Staff",

                "Created staff member: ".$staff_name

            );


            /*
             * Notify supervisor when staff is
             * assigned during creation.
             *
             * This only runs if a supervisor
             * was selected.
             */

            if($supervisor_id != ""){

                /*
                 * We will connect this to your
                 * existing notification function
                 * after confirming its exact name.
                 */

            }


            $message =
                "Staff member added successfully.";


            /*
             * Clear form values after success.
             */

            $_POST = array();

        }else{

            $error =
                "Unable to add staff member. "
                . mysqli_error($conn);

        }

    }

}

?>

<!DOCTYPE html>
<html>
<head>
<title>Add Staff</title>
<link rel="stylesheet" href="styles.css">
</head>

<body>

<div class="container">

<!-- ============================== SIDEBAR ============================== -->
<div class="sidebar">

<div class="logo">
<h2><?php echo $app['organization_name']; ?></h2>
<p>Personnel Timesheet System</p>
</div>

<div class="profile">
    
<div class="avatar">
    <img src="<?php echo htmlspecialchars($admin_profile_photo); ?>" class="profile-small" alt="Administrator Profile Photo" onerror="this.onerror=null; this.src='uploads/admins/default.png';" >
</div>

<h3><?php echo htmlspecialchars($fullname); ?></h3>
<p><?php echo htmlspecialchars($admin_role); ?></p>

</div>

<ul>
<li><a href="admin_dashboard.php">Dashboard</a></li>
<li><a href="manage_admins.php">Manage Admins</a></li>
<li><a href="manage_supervisors.php">Manage Supervisors</a></li>
<li class="active"><a href="manage_staff.php">Manage Staff</a></li>
<li><a href="reports.php">Reports</a></li>
<li><a href="admin_logout.php">Logout</a></li>
</ul>

</div>

<!-- ============================== MAIN ============================== -->
<div class="main">

<div class="topbar">

<div>
<h1>Add New Staff</h1>
<p>Create a new staff account.</p>
</div>

<div id="clock"></div>
</div>

<!-- SUCCESS -->
<?php

if($message!=""){

?>

<div class="success-box">
<?php
echo htmlspecialchars($message);
?>
</div>

<?php

}

?>

<!-- ERROR -->
<?php
if($error!=""){
?>

<div class="error-box">
<?php
echo htmlspecialchars($error);
?>
</div>

<?php
}
?>

<!-- ============================== FORM ============================== -->

<div class="form-card">

<form method="POST" enctype="multipart/form-data" >

<div class="form-grid">

<!-- STAFF ID -->
<div class="form-group">

<label>Staff ID</label>

<input type="text" name="staff_id" value="<?php
echo isset($_POST['staff_id'])
    ? htmlspecialchars($_POST['staff_id'])
    : '';
?>" required>

</div>

<!-- FULL NAME -->
<div class="form-group">

<label>Full Name</label>

<input type="text" name="fullname" value="<?php
echo isset($_POST['fullname'])
    ? htmlspecialchars($_POST['fullname'])
    : '';
?>"
required>

</div>

<!-- EMAIL -->
<div class="form-group">

<label>Email Address</label>

<input type="email" name="email" value="<?php
echo isset($_POST['email'])
    ? htmlspecialchars($_POST['email'])
    : '';
?>"
>

</div>

<!-- PHONE -->
<div class="form-group">

<label>Phone Number</label>

<input type="text" name="phone" value="<?php
echo isset($_POST['phone'])
    ? htmlspecialchars($_POST['phone'])
    : '';
?>"

>

</div>

<!-- DEPARTMENT -->
<div class="form-group">

<label>Department</label>

<input type="text" name="department" value="<?php
echo isset($_POST['department'])
    ? htmlspecialchars($_POST['department'])
    : '';
?>"
>

</div>

<!-- POSITION -->
<div class="form-group">

<label>Position</label>

<input type="text" name="position" value="<?php
echo isset($_POST['position'])
    ? htmlspecialchars($_POST['position'])
    : '';
?>"

>

</div>

<!-- SUPERVISOR -->
<div class="form-group">

<label>Supervisor</label>
<select name="supervisor_id">
<option value="">Not Assigned</option>

<?php

if(mysqli_num_rows($supervisors) > 0){

    while(
        $supervisor =
        mysqli_fetch_assoc($supervisors)
    ){

?>

<option
value="<?php
echo htmlspecialchars(
    $supervisor['supervisor_id']
);
?>"

<?php

if(
    isset($_POST['supervisor_id'])
    &&
    $_POST['supervisor_id']
    ==
    $supervisor['supervisor_id']
){

    echo "selected";

}

?>

>

<?php

echo htmlspecialchars(
    $supervisor['fullname']
);

if(
    !empty($supervisor['department'])
){

    echo
        " - "
        .
        htmlspecialchars(
            $supervisor['department']
        );

}

?>

</option>


<?php

    }

}else{

?>

<option value="">No supervisors available</option>

<?php

}

?>

</select>

</div>

<!-- STATUS -->
<div class="form-group">

<label>Status</label>
<select name="status">
<option value="Active">Active</option>
<option value="Inactive">Inactive</option>
</select>

</div>

<!-- PASSWORD -->
<div class="form-group">
<label>Password</label>
<input type="password" name="password" required></div>

<!-- PROFILE PHOTO -->
<div class="form-group">
<label>Profile Picture</label>
<input type="file" name="profile_picture" accept=".jpg,.jpeg,.png">
</div>

</div>

<div class="button-group">
<button type="submit"name="save" class="approve-btn">💾 Save Staff</button>
<button type="reset" class="excel-btn">Reset</button>
<a href="manage_staff.php" class="print-btn">← Back</a>
</div>

</form>

</div>

</div>

</div>

<script>
function updateClock(){
    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();
}
updateClock();
setInterval(updateClock,1000);
</script>

</body>
</html>