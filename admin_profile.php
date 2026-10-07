<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

$query = mysqli_query($conn,"
SELECT *
FROM admins
WHERE admin_id='$admin_id'
");

if(mysqli_num_rows($query)==0){
    die("Administrator not found.");
}

$admin = mysqli_fetch_assoc($query);
?>

<!DOCTYPE html>
<html>
<head>
<title>My Profile</title>
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
<img src="uploads/admins/<?php echo $admin['profile_photo']; ?>" class="profile-small" alt="Profile">
</div>

<h3><?php echo $fullname; ?></h3>
<p><?php echo $admin_role; ?></p>

</div>

<ul>
<li><a href="admin_dashboard.php">Dashboard</a></li>
<li class="active"><a href="admin_profile.php">My Profile</a></li>
<?php if($admin_role=="Super Admin"){ ?>
<li><a href="manage_admins.php">Manage Admins</a></li>
<?php } ?>
<li><a href="admin_logout.php">Logout</a></li>
</ul>

</div>

<div class="main">

<div class="topbar">

<div>
<h1>My Profile</h1>
<p>Administrator Information</p>
</div>

<div id="clock"></div>

</div>

<div class="profile-card">

    <!-- LEFT SIDE -->
    <div class="profile-left">
        <img src="uploads/admins/<?php echo $admin['profile_photo']; ?>" class="profile-image">
        <h2><?php echo $admin['fullname']; ?></h2>
        <span class="role-tag">👑 <?php echo $admin['role']; ?></span>
        <p class="position"><?php echo $admin['position']; ?>b</p>
        <span class="status-tag">● <?php echo $admin['status']; ?></span>
    </div>

    <!-- RIGHT SIDE -->

    <div class="profile-right">
        <h2>Personal Information</h2>
        <table class="profile-table">
            <tr>
                <th>🆔 Admin ID</th>
                <td><?php echo $admin['admin_id']; ?></td>
            </tr>

            <tr>
                <th>📧 Email</th>
                <td><?php echo $admin['email']; ?></td>
            </tr>

            <tr>
                <th>📞 Phone</th>
                <td><?php echo $admin['phone']; ?></td>
            </tr>

            <tr>
                <th>👤 Gender</th>
                <td><?php echo $admin['gender']; ?></td>
            </tr>

            <tr>
                <th>🎂 Date of Birth</th>
                <td><?php echo date("d M Y",strtotime($admin['date_of_birth'])); ?></td>
            </tr>

            <tr>
                <th>📍 Address</th>
                <td><?php echo $admin['address']; ?></td>
            </tr>

            <tr>
                <th>💼 Position</th>
                <td><?php echo $admin['position']; ?></td>
            </tr>

            <tr>
                <th>🛡 Role</th>
                <td><?php echo $admin['role']; ?></td>
            </tr>

        </table>

    </div>

</div>

<div class="profile-buttons">
    <a href="edit_my_profile.php" class="btn green">✏ Edit Profile</a>
    <a href="change_my_password.php" class="btn blue">🔑 Change Password</a>
<?php if($admin_role=="Super Admin"){ ?>
    <a href="manage_admins.php" class="btn red">👥 Manage Admins</a>
<?php } ?>
    <a href="admin_dashboard.php" class="btn dark">🏠 Dashboard</a>
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