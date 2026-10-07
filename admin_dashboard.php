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

$admin_query = mysqli_query($conn,"
SELECT profile_photo
FROM admins
WHERE admin_id='$admin_id'
");

$admin = mysqli_fetch_assoc($admin_query);

/* =========================== DASHBOARD STATISTICS =========================== */

$total_staff = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM staff
"))['total'];

$total_supervisors = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM supervisors
"))['total'];

$total_timesheets = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM timesheets
"))['total'];

$total_reports = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM task_reports
"))['total'];

$pending_reports = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM task_reports
WHERE status='Pending'
"))['total'];

$approved_reports = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM task_reports
WHERE status='Approved'
"))['total'];

$rejected_reports = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM task_reports
WHERE status='Rejected'
"))['total'];

$total_notifications = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM notifications
WHERE status='Unread'
"))['total'];

$total_logs = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM activity_logs
"))['total'];

/* =========================== RECENT ACTIVITIES =========================== */
$activities = mysqli_query($conn,"
SELECT *
FROM activity_logs
ORDER BY activity_time DESC
LIMIT 10
");

?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
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
        <img src="uploads/admins/<?php echo $admin['profile_photo']; ?>" class="profile-small" alt="Profile">
    </div>

    <h3><?php echo $fullname; ?></h3>
    <p><?php echo $admin_role; ?></p>

</div>

<ul>
    <li class="active"><a href="admin_dashboard.php">Dashboard</a></li>
    <li><a href="manage_staff.php">Manage Staff</a></li>
    <li><a href="manage_supervisors.php">Manage Supervisors</a></li>
    <?php if($admin_role=="Super Admin"){ ?>
    <li><a href="manage_admins.php">Manage Admins</a></li>
    <?php } ?>
    <li><a href="activity_logs.php">Activity Logs</a></li>
    <li><a href="notifications.php">Notifications</a></li>
    <li><a href="admin_reports.php">Reports</a></li>
    <li><a href="admin_profile.php">My Profile</a></li>
    <li><a href="admin_logout.php">Logout</a></li>
</ul>

</div>

    <!-- Main -->
    <div class="main">

        <div class="topbar">
            <div>
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?php echo $fullname; ?></p>
            </div>

            <div id="clock"></div>
        </div>

    <div class="cards">

<div class="card">
<h4>Total Staff</h4>
<h1><?php echo $total_staff; ?></h1>
</div>

<div class="card">
<h4>Supervisors</h4>
<h1><?php echo $total_supervisors; ?></h1>
</div>

<div class="card">
<h4>Timesheets</h4>
<h1><?php echo $total_timesheets; ?></h1>
</div>

<div class="card">
<h4>Task Reports</h4>
<h1><?php echo $total_reports; ?></h1>
</div>

<div class="card">
<h4>Pending</h4>
<h1><?php echo $pending_reports; ?></h1>
</div>

<div class="card">
<h4>Approved</h4>
<h1><?php echo $approved_reports; ?></h1>
</div>

<div class="card">
<h4>Rejected</h4>
<h1><?php echo $rejected_reports; ?></h1>
</div>

<div class="card">
<h4>Notifications</h4>
<h1><?php echo $total_notifications; ?></h1>
</div>

<div class="card">
<h4>Activity Logs</h4>
<h1><?php echo $total_logs; ?></h1>
</div>

<?php if($admin_role=="Super Admin"){ ?>

<div class="card">
<h4>System Role</h4>
<h1>👑</h1>
<p>Super Administrator</p>
</div>

<?php } ?>
</div>

<div class="history-box">
<h2>Recent Activities</h2>
<table class="history-table">

<thead>
<tr>

<th>Time</th>
<th>User</th>
<th>Activity</th>
<th>Description</th>

</tr>
</thead>

<tbody>

<?php

while($row=mysqli_fetch_assoc($activities)){

?>

<tr>
<td><?php echo date("d M Y h:i A",strtotime($row['activity_time'])); ?></td>
<td><?php echo $row['user_type']; ?></td>
<td><?php echo $row['activity_type']; ?></td>
<td><?php echo $row['activity_description']; ?></td>
</tr>

<?php } ?>
</tbody>
</table>
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