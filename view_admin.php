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

$fullname=$_SESSION['fullname'];

if(!isset($_GET['id'])){
    header("Location: manage_admins.php");
    exit();
}

$id=(int)$_GET['id'];

$query=mysqli_query($conn,"
SELECT *
FROM admins
WHERE id='$id'
");

if(mysqli_num_rows($query)==0){

die("Administrator not found.");

}

$admin=mysqli_fetch_assoc($query);

logActivity(

$conn,

$_SESSION['admin_id'],

"Admin",

"View Admin",

"Viewed administrator profile : ".$admin['fullname']

);
?>

<!DOCTYPE html>

<html>

<head>

<title>Administrator Profile</title>

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

<a href="manage_staff.php">

Manage Staff

</a>

</li>

<li>

<a href="manage_supervisors.php">

Manage Supervisors

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

<div class="main">

<div class="topbar">

<div>

<h1>Administrator Profile</h1>

<p>Complete administrator information</p>

</div>

<div id="clock"></div>

</div>

<div class="details-card">

<div class="staff-header">

<img

src="uploads/admins/<?php echo $admin['profile_photo'];?>"

class="profile-large">

<div>

<h2>

<?php echo $admin['fullname']; ?>

</h2>

<p>

<?php echo $admin['position']; ?>

</p>

<span class="status <?php echo strtolower($admin['status']); ?>">

<?php echo $admin['status']; ?>

</span>

</div>

</div>

<div class="info-grid">

<div class="info-card">

<span>Admin ID</span>

<h3>

<?php echo $admin['admin_id']; ?>

</h3>

</div>

<div class="info-card">

<span>Email</span>

<h3>

<?php echo $admin['email']; ?>

</h3>

</div>

<div class="info-card">

<span>Phone</span>

<h3>

<?php echo $admin['phone']; ?>

</h3>

</div>

<div class="info-card">

<span>Gender</span>

<h3>

<?php echo $admin['gender']; ?>

</h3>

</div>

<div class="info-card">

<span>Date of Birth</span>

<h3>

<?php echo date("d M Y",strtotime($admin['date_of_birth'])); ?>

</h3>

</div>

<div class="info-card">

<span>Role</span>

<h3>

<?php echo $admin['role']; ?>

</h3>

</div>

<div class="info-card">

<span>Last Login</span>

<h3>

<?php

echo $admin['last_login']

? date("d M Y h:i A",strtotime($admin['last_login']))

: "Never";

?>

</h3>

</div>

<div class="info-card">

<span>Created</span>

<h3>

<?php echo date("d M Y",strtotime($admin['created_at'])); ?>

</h3>

</div>

</div>

<div class="detail-section">

<h3>Address</h3>

<div class="content-box">

<?php

echo !empty($admin['address'])

? nl2br(htmlspecialchars($admin['address']))

: "<em>No address available.</em>";

?>

</div>

</div>

<div class="button-group">

<a

href="edit_admin.php?id=<?php echo $admin['id'];?>"

class="approve-btn">

✏ Edit

</a>

<a

href="change_admin_password.php?id=<?php echo $admin['id'];?>"

class="excel-btn">

🔑 Change Password

</a>

<a

href="manage_admins.php"

class="print-btn">

← Back

</a>

</div>

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