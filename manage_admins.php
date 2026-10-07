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

$admin_id=$_SESSION['admin_id'];
$fullname=$_SESSION['fullname'];
$admin_role=$_SESSION['admin_role'];

/* ==============================
   ADMIN PROFILE PHOTO
============================== */

$stmt = $conn->prepare("
    SELECT
        fullname,
        profile_photo
    FROM admins
    WHERE admin_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $admin_id
);

$stmt->execute();

$result = $stmt->get_result();

$admin_data = $result->fetch_assoc();

$stmt->close();


/* ==============================
   ADMIN PROFILE PHOTO PATH
============================== */

$admin_profile_photo =
    "uploads/admins/default.png";


if (
    $admin_data &&
    !empty($admin_data['profile_photo'])
) {

    $photo =
        basename(
            $admin_data['profile_photo']
        );

    $photo_path =
        "uploads/admins/" . $photo;


    if (file_exists($photo_path)) {

        $admin_profile_photo =
            $photo_path;

    }

}

$total_admins=mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM admins
"))['total'];

$total_super=mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM admins
WHERE role='Super Admin'
"))['total'];

$total_active=mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM admins
WHERE status='Active'
"))['total'];

$total_inactive=mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM admins
WHERE status='Inactive'
"))['total'];

$search="";
$role="";
$status="";

$where="WHERE 1";

if(isset($_GET['search']) && $_GET['search']!=""){

$search=mysqli_real_escape_string($conn,$_GET['search']);

$where.=" AND (

fullname LIKE '%$search%'

OR

admin_id LIKE '%$search%'

)";
}

if(isset($_GET['role']) && $_GET['role']!=""){

$role=$_GET['role'];

$where.=" AND role='$role'";

}

if(isset($_GET['status']) && $_GET['status']!=""){

$status=$_GET['status'];

$where.=" AND status='$status'";

}

$admins=mysqli_query($conn,"

SELECT *

FROM admins

$where

ORDER BY created_at DESC

");
?>

<!DOCTYPE html>
<html>

<head>

<title>Manage Administrators</title>

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

<img
    src="<?php echo htmlspecialchars($admin_profile_photo); ?>"
    class="profile-small"
    alt="Admin Profile Photo"
    onerror="this.src='uploads/admins/default.png';"
>

</div>

<h3><?php echo $fullname; ?></h3>

<p><?php echo $admin_role; ?></p>

</div>

<ul>

<li>

<a href="admin_dashboard.php">

Dashboard

</a>

</li>

<li class="active"><a href="manage_admins.php">Manage Admins</a></li>

<li><a href="manage_supervisors.php">Manage Supervisors</a></li>

<li><a href="manage_staff.php">Manage Staff</a></li>

<li><a href="admin_reports.php">Reports</a></li>

<li>

<a href="admin_logout.php">

Logout

</a>

</li>

</ul>

</div>

<!-- Main -->

<div class="main">

<div class="topbar">

<div>

<h1>Manage Administrators</h1>

<p>Create, edit and manage administrators.</p>

</div>

<div id="clock"></div>

</div>

<div class="cards">

<div class="card">

<h4>Total Admins</h4>

<h1><?php echo $total_admins; ?></h1>

</div>

<div class="card">

<h4>Super Admins</h4>

<h1><?php echo $total_super; ?></h1>

</div>

<div class="card">

<h4>Active</h4>

<h1><?php echo $total_active; ?></h1>

</div>

<div class="card">

<h4>Inactive</h4>

<h1><?php echo $total_inactive; ?></h1>

</div>

</div>

<div class="history-box">

<form method="GET" class="filter-form">

<input
type="text"
name="search"
placeholder="Search Name or Admin ID"
value="<?php echo $search; ?>">

<select name="role">

<option value="">All Roles</option>

<option value="Super Admin"

<?php if($role=="Super Admin") echo "selected"; ?>>

Super Admin

</option>

<option value="Admin"

<?php if($role=="Admin") echo "selected"; ?>>

Admin

</option>

</select>

<select name="status">

<option value="">All Status</option>

<option value="Active"

<?php if($status=="Active") echo "selected"; ?>>

Active

</option>

<option value="Inactive"

<?php if($status=="Inactive") echo "selected"; ?>>

Inactive

</option>

</select>

<button type="submit">

Search

</button>

<a href="add_admin.php" class="approve-btn">

➕ Add Administrator

</a>

</form>

<table class="history-table">

<thead>

<tr>

<th>Photo</th>

<th>Admin ID</th>

<th>Name</th>

<th>Role</th>

<th>Status</th>

<th>Email</th>

<th>Phone</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php

if(mysqli_num_rows($admins)>0){

while($row=mysqli_fetch_assoc($admins)){

?>

<tr>

<td>

<img

src="uploads/admins/<?php echo $row['profile_photo']; ?>"

width="50"

height="50"

style="border-radius:50%;object-fit:cover;">

</td>

<td>

<?php echo $row['admin_id']; ?>

</td>

<td>

<?php echo $row['fullname']; ?>

</td>

<td>

<?php echo $row['role']; ?>

</td>

<td>

<span class="status <?php echo strtolower($row['status']); ?>">

<?php echo $row['status']; ?>

</span>

</td>

<td>

<?php echo $row['email']; ?>

</td>

<td>

<?php echo $row['phone']; ?>

</td>

<td>

<a

href="view_admin.php?id=<?php echo $row['id']; ?>"

class="view-btn">

View

</a>

<a

href="edit_admin.php?id=<?php echo $row['id']; ?>"

class="approve-btn">

Edit

</a>

<?php

if($row['status']=="Active"){

?>

<a

href="deactivate_admin.php?id=<?php echo $row['id']; ?>"

class="reject-btn">

Deactivate

</a>

<?php

}else{

?>

<a

href="activate_admin.php?id=<?php echo $row['id']; ?>"

class="excel-btn">

Activate

</a>

<?php

}

?>

</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="8">

No administrators found.

</td>

</tr>

<?php

}

?>

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