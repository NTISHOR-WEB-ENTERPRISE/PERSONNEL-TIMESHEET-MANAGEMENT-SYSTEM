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
$current_admin = $_SESSION['admin_id'];

if(!isset($_GET['id'])){
    header("Location: manage_admins.php");
    exit();
}

$id = (int)$_GET['id'];

$query = mysqli_query($conn,"
SELECT *
FROM admins
WHERE id='$id'
");

if(mysqli_num_rows($query)==0){
    die("Administrator not found.");
}

$admin = mysqli_fetch_assoc($query);

$message = "";
$error = "";

if(isset($_POST['change_password'])){

$password = trim($_POST['password']);
$confirm = trim($_POST['confirm_password']);

if(empty($password) || empty($confirm)){

$error = "Please complete all fields.";

}
elseif($password != $confirm){

$error = "Passwords do not match.";

}
elseif(strlen($password) < 8){

$error = "Password must be at least 8 characters.";

}
else{

$hash = password_hash($password, PASSWORD_DEFAULT);

mysqli_query($conn,"
UPDATE admins
SET password='$hash'
WHERE id='$id'
");

logActivity(
$conn,
$current_admin,
"Admin",
"Reset Password",
"Reset password for ".$admin['fullname']
);

header("Location: view_admin.php?id=".$id."&success=password");
exit();

}

}
?>

<!DOCTYPE html>
<html>

<head>

<title>Change Administrator Password</title>

<link rel="stylesheet" href="styles.css">

</head>

<body>

<div class="container">

<div class="sidebar">

<div class="logo">

<h2><?php echo $app['organization_name']; ?></h2>

<p>Personnel Timesheet System</p>

</div>

<div class="profile">

<div class="avatar">

<?php echo strtoupper(substr($fullname,0,1)); ?>

</div>

<h3><?php echo $fullname; ?></h3>

<p>Super Administrator</p>

</div>

<ul>

<li>

<a href="admin_dashboard.php">

Dashboard

</a>

</li>

<li class="active">

<a href="manage_admins.php">

Manage Admins

</a>

</li>

<li>

<a href="admin_logout.php">

Logout

</a>

</li>

</ul>

</div>

<div class="main">

<div class="topbar">

<div>

<h1>Change Password</h1>

<p><?php echo $admin['fullname']; ?></p>

</div>

<div id="clock"></div>

</div>

<?php if($message!=""){ ?>

<div class="success-box">

<?php echo $message; ?>

</div>

<?php } ?>

<?php if($error!=""){ ?>

<div class="error-box">

<?php echo $error; ?>

</div>

<?php } ?>

<div class="form-card">

<form method="POST">

<div class="form-group">

<label>Administrator</label>

<input
type="text"
value="<?php echo $admin['fullname']; ?>"
readonly>

</div>

<div class="form-group">

<label>New Password</label>

<input
type="password"
name="password"
required>

</div>

<div class="form-group">

<label>Confirm Password</label>

<input
type="password"
name="confirm_password"
required>

</div>

<div class="button-group">

<button
type="submit"
name="change_password"
class="approve-btn">

🔑 Change Password

</button>

<a
href="view_admin.php?id=<?php echo $admin['id']; ?>"
class="print-btn">

Cancel

</a>

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