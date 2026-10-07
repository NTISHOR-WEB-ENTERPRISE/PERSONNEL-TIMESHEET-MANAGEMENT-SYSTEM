<?php
session_start();
include "config.php";
include "notification_function.php";

if(!isset($_SESSION['supervisor_id'])){
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$fullname = $_SESSION['fullname'];

/* ==================================
   SUPERVISOR PROFILE PHOTO
================================== */

$stmt = $conn->prepare("
    SELECT profile_photo
    FROM supervisors
    WHERE supervisor_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$result = $stmt->get_result();

$supervisor_data = $result->fetch_assoc();

$stmt->close();


$profile_photo = "uploads/supervisors/default.png";


if (
    $supervisor_data &&
    !empty($supervisor_data['profile_photo'])
) {

    $photo = basename(
        $supervisor_data['profile_photo']
    );

    $photo_path =
        "uploads/supervisors/" . $photo;

    if (file_exists($photo_path)) {

        $profile_photo = $photo_path;

    }

}

/* Statistics */

$total = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM task_reports
WHERE supervisor_id='$supervisor_id'
"))['total'];

$pending = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM task_reports
WHERE supervisor_id='$supervisor_id'
AND status='Pending'
"))['total'];

$approved = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM task_reports
WHERE supervisor_id='$supervisor_id'
AND status='Approved'
"))['total'];

$rejected = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM task_reports
WHERE supervisor_id='$supervisor_id'
AND status='Rejected'
"))['total'];


/* Filter */

$status = "";

if(isset($_GET['status'])){
    $status = $_GET['status'];
}

$sql = "
SELECT
tr.*,
s.fullname,
s.department
FROM task_reports tr
INNER JOIN staff s
ON tr.staff_id=s.staff_id
WHERE tr.supervisor_id='$supervisor_id'
";

if($status!=""){
    $sql .= " AND tr.status='$status'";
}

$sql .= "
ORDER BY tr.report_date DESC
";

$reports = mysqli_query($conn,$sql);
?>

<!DOCTYPE html>
<html>
<head>

<title>Task Report Approvals</title>

<link rel="stylesheet" href="styles.css">

</head>

<body>

<div class="container">

<!-- Sidebar -->

<div class="sidebar">

<div class="logo">

<h2><?php echo $app['organization_name']; ?></h2>


<p>Timesheet System</p>

</div>

<div class="profile">

<div class="avatar">


<img
    src="<?php echo htmlspecialchars($profile_photo); ?>"
    class="profile-small"
    alt="Profile"
    onerror="this.src='uploads/supervisors/default.png';"
>

</div>

<h3><?php echo $fullname; ?></h3>

<p>Supervisor</p>

</div>

<ul>

<li>
<a href="supervisor_dashboard.php">
Dashboard
</a>
</li>

<li>
<a href="approvals.php">
Approvals
</a>
</li>

<li class="active">
<a href="task_report_approvals.php">
Task Reports
</a>
</li>

<li>
<a href="reports.php">
Reports
</a>
</li>

<li>
<a href="supervisor_logout.php">
Logout
</a>
</li>

</ul>

</div>


<!-- Main -->

<div class="main">

<div class="topbar">

<h1>Task Report Approvals</h1>

<div id="clock"></div>

</div>


<!-- Summary -->

<div class="cards">

<div class="card">

<h4>Total Reports</h4>

<h1><?php echo $total; ?></h1>

<p>All submissions</p>

</div>

<div class="card">

<h4>Pending</h4>

<h1><?php echo $pending; ?></h1>

<p>Awaiting review</p>

</div>

<div class="card">

<h4>Approved</h4>

<h1><?php echo $approved; ?></h1>

<p>Approved reports</p>

</div>

<div class="card">

<h4>Rejected</h4>

<h1><?php echo $rejected; ?></h1>

<p>Rejected reports</p>

</div>

</div>


<!-- Filter -->

<div class="history-box">

<form method="GET" class="filter-form">

<label>Status</label>

<select name="status">

<option value="">All</option>

<option value="Pending"
<?php if($status=="Pending") echo "selected"; ?>>
Pending
</option>

<option value="Approved"
<?php if($status=="Approved") echo "selected"; ?>>
Approved
</option>

<option value="Rejected"
<?php if($status=="Rejected") echo "selected"; ?>>
Rejected
</option>

</select>

<button type="submit">

Filter

</button>

</form>


<table class="history-table">

<thead>
<tr>
    <th>#</th>
    <th>Staff</th>
    <th>Department</th>
    <th>Date</th>
    <th>Hours</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>

<tbody>

<?php
$count = 1;

if(mysqli_num_rows($reports)>0){

while($row=mysqli_fetch_assoc($reports)){
?>

<tr>

<td><?php echo $count++; ?></td>

<td>

<div class="staff-info">

<div class="staff-avatar">

<?php echo strtoupper(substr($row['fullname'],0,1)); ?>

</div>

<div>

<strong><?php echo $row['fullname']; ?></strong>

</div>

</div>

</td>

<td>

<?php
echo isset($row['department'])
? $row['department']
: "N/A";
?>

</td>

<td>

<div class="date-box">

<div>

<?php echo date("d M Y",strtotime($row['report_date'])); ?>

</div>

<small>

<?php echo date("h:i A",strtotime($row['start_time'])); ?>

</small>

</div>

</td>

<td>

<?php echo number_format($row['hours_worked'],1); ?>

hrs

</td>

<td>

<span class="status <?php echo strtolower($row['status']); ?>">

<?php echo $row['status']; ?>

</span>

</td>

<td>

<a
href="task_report_details.php?id=<?php echo $row['id']; ?>"
class="view-btn">

👁 View

</a>


<?php if($row['status']=="Pending"){ ?>


<a
href="update_task_report_status.php?id=<?php echo $row['id']; ?>&action=approve"
class="approve-btn">

✅ Approve

</a>


<a
href="update_task_report_status.php?id=<?php echo $row['id']; ?>&action=reject"
class="reject-btn">

❌ Reject

</a>


<?php } ?>

</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="7">

No Task Reports Found

</td>

</tr>

<?php } ?>

</tbody>

</table>

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