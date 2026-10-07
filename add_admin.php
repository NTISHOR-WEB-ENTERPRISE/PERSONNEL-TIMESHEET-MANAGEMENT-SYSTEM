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

if(isset($_POST['save'])){

    $new_admin_id = mysqli_real_escape_string($conn,$_POST['admin_id']);
    $new_fullname = mysqli_real_escape_string($conn,$_POST['fullname']);
    $email = mysqli_real_escape_string($conn,$_POST['email']);
    $phone = mysqli_real_escape_string($conn,$_POST['phone']);
    $address = mysqli_real_escape_string($conn,$_POST['address']);
    $gender = mysqli_real_escape_string($conn,$_POST['gender']);
    $dob = mysqli_real_escape_string($conn,$_POST['date_of_birth']);
    $position = mysqli_real_escape_string($conn,$_POST['position']);
    $role = mysqli_real_escape_string($conn,$_POST['role']);
    $status = mysqli_real_escape_string($conn,$_POST['status']);
    $password = password_hash($_POST['password'],PASSWORD_DEFAULT);

    /* Check Admin ID */

    $check = mysqli_query($conn,"
    SELECT *
    FROM admins
    WHERE admin_id='$new_admin_id'
    ");

    if(mysqli_num_rows($check)>0){

        $error="Admin ID already exists.";

    }else{

        /* Check Email */

        $check2 = mysqli_query($conn,"
        SELECT *
        FROM admins
        WHERE email='$email'
        ");

        if(mysqli_num_rows($check2)>0){

            $error="Email already exists.";

        }else{

            /* Upload Image */

            $photo="default.png";

            if(!empty($_FILES['profile_photo']['name'])){

                $ext = strtolower(pathinfo(
                    $_FILES['profile_photo']['name'],
                    PATHINFO_EXTENSION
                ));

                $allowed = array("jpg","jpeg","png");

                if(in_array($ext,$allowed)){

                    $photo = time()."_".$_FILES['profile_photo']['name'];

                    move_uploaded_file(

                        $_FILES['profile_photo']['tmp_name'],

                        "uploads/admins/".$photo

                    );

                }

            }

            mysqli_query($conn,"
            INSERT INTO admins(

                admin_id,
                fullname,
                email,
                phone,
                address,
                gender,
                date_of_birth,
                position,
                profile_photo,
                password,
                role,
                status,
                created_by

            )

            VALUES(

                '$new_admin_id',
                '$new_fullname',
                '$email',
                '$phone',
                '$address',
                '$gender',
                '$dob',
                '$position',
                '$photo',
                '$password',
                '$role',
                '$status',
                '$admin_id'

            )
            ");

            logActivity(

                $conn,

                $_SESSION['admin_id'],

                "Admin",

                "Add Admin",

                "Created administrator: ".$new_fullname

            );

            $message="Administrator added successfully.";

        }

    }

}
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Administrator</title>
<link rel="stylesheet" href="styles.css">
</head>

<body>

<div class="container">

<!-- Sidebar -->
<div class="sidebar">

<div class="logo">
<h2><?php echo $app['organization_name']; ?></h2>
<p>Personnel Timesheet System</p>
</div>

<div class="profile">

<div class="avatar">
    <img src="<?php echo htmlspecialchars($admin_profile_photo); ?>" class="profile-small" alt="Administrator Profile Photo" onerror="this.onerror=null; this.src='uploads/admins/default.png';">
</div>

<h3><?php echo $fullname; ?></h3>
<p>Super Administrator</p>

</div>

        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li class="active"><a href="manage_admins.php">Manage Admins</a></li>
            <li><a href="manage_supervisors.php">Supervisors</a></li>
            <li><a href="manage_staff.php">Staff</a></li>
            <li><a href="admin_reports.php">Reports</a></li>
            <li><a href="admin_logout.php">Logout</a></li>
        </ul>

            </div>

<!-- Main -->
<div class="main">

<div class="topbar">

        <div>
            <h1>Add New Admin</h1>
            <p>Create a new system Admin.</p>
        </div>

<div id="clock"></div>

</div>

<?php if($message!=""){ ?>

<div class="success-box"><?php echo $message; ?></div>

<?php } ?>

<?php if($error!=""){ ?>

<div class="error-box"><?php echo $error; ?></div>

<?php } ?>

<div class="form-card">

<form method="POST" enctype="multipart/form-data">

<div class="form-grid">

<div class="form-group">
<label>Admin ID</label>
<input type="text" name="admin_id" required>
</div>

<div class="form-group">
<label>Full Name</label>
<input type="text" name="fullname" required>
</div>

<div class="form-group">
<label>Email Address</label>
<input type="email" name="email" required>
</div>

<div class="form-group">
<label>Phone Number</label>
<input type="text" name="phone">
</div>

<div class="form-group">
<label>Gender</label>
<select name="gender">
<option value="">Select</option>
<option>Male</option>
<option>Female</option>
</select>
</div>

<div class="form-group">
<label>Date of Birth</label>

<input type="date" name="date_of_birth">
</div>

<div class="form-group">
<label>Position</label>
<input type="text" name="position" value="Administrator">
</div>

<div class="form-group">
<label>Role</label>
<select name="role">
<option value="Admin">Admin</option>
<option value="Super Admin">Super Admin</option>
</select>
</div>

<div class="form-group">
<label>Status</label>
<select name="status">
<option value="Active">Active</option>
<option value="Inactive">Inactive</option>
</select>
</div>

<div class="form-group">
<label>Password</label>
<input type="password" name="password" required>
</div>

<div class="form-group">
<label>Profile Photo</label>
<input type="file" name="profile_photo" accept=".jpg,.jpeg,.png">
</div>

</div>

<div class="form-group">
<label>Address</label>
<textarea name="address" rows="4" placeholder="Enter full address"></textarea>
</div>

<div class="button-group">
<button type="submit" name="save" class="approve-btn">💾 Save Administrator</button>
<button type="reset" class="excel-btn">Reset</button>

<a href="manage_admins.php" class="print-btn"> ← Back</a>

</div>

</form>

</div>
</div>
</div>

<script>
function updateClock(){
document.getElementById("clock").innerHTML=
new Date().toLocaleTimeString();
}
updateClock();
setInterval(updateClock,1000);
</script>

</body>
</html>