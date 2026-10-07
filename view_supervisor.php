<?php

session_start();

include "config.php";
include "activity_logger.php";


/* ==============================
   ADMIN AUTHENTICATION
============================== */

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SESSION['admin_role'] != "Super Admin") {
    die("Access Denied");
}

$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];


/* ==============================
   GET SUPERVISOR ID
============================== */

if (
    !isset($_GET['supervisor_id']) ||
    empty($_GET['supervisor_id'])
) {
    header("Location: manage_supervisors.php");
    exit();
}

$supervisor_id = mysqli_real_escape_string(
    $conn,
    trim($_GET['supervisor_id'])
);


/* ==============================
   GET SUPERVISOR
============================== */

$query = mysqli_query(
    $conn,
    "
    SELECT *
    FROM supervisors
    WHERE supervisor_id='$supervisor_id'
    "
);


if (!$query) {
    die(
        "Database error: " .
        mysqli_error($conn)
    );
}


if (mysqli_num_rows($query) == 0) {
    die("Supervisor not found.");
}


$supervisor = mysqli_fetch_assoc($query);


/* ==============================
   GET ASSIGNED STAFF
============================== */

$staff_query = mysqli_query(
    $conn,
    "
    SELECT
        staff_id,
        fullname,
        email,
        phone,
        department,
        position,
        status,
        profile_picture

    FROM staff

    WHERE supervisor_id='$supervisor_id'

    ORDER BY fullname ASC
    "
);


if (!$staff_query) {
    die(
        "Unable to load assigned staff: " .
        mysqli_error($conn)
    );
}


/* ==============================
   COUNT ASSIGNED STAFF
============================== */

$assigned_staff_count =
    mysqli_num_rows($staff_query);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
View Supervisor
</title>

<link
rel="stylesheet"
href="styles.css"
>


<style>

/* ==============================
   SUPERVISOR PROFILE
============================== */

.supervisor-profile {

    background: white;

    padding: 30px;

    border-radius: 10px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);

    margin-bottom: 25px;

}


.profile-header {

    display: flex;

    align-items: center;

    gap: 25px;

    padding-bottom: 25px;

    border-bottom: 1px solid #eee;

}


.supervisor-large-photo {

    width: 110px;

    height: 110px;

    border-radius: 50%;

    object-fit: cover;

    border: 4px solid #eee;

}


.profile-info h2 {

    margin: 0 0 8px;

    font-size: 25px;

}


.profile-info p {

    margin: 5px 0;

    color: #666;

}


.supervisor-status {

    display: inline-block;

    padding: 6px 14px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    margin-top: 8px;

}


.supervisor-status.active {

    background: #d1e7dd;

    color: #0f5132;

}


.supervisor-status.inactive {

    background: #f8d7da;

    color: #842029;

}


/* ==============================
   INFORMATION GRID
============================== */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

    margin-top: 25px;

}


.info-item {

    background: #f8f9fa;

    padding: 15px;

    border-radius: 8px;

}


.info-item label {

    display: block;

    font-size: 12px;

    color: #777;

    margin-bottom: 5px;

    font-weight: bold;

    text-transform: uppercase;

}


.info-item strong {

    font-size: 15px;

}


/* ==============================
   ASSIGNED STAFF
============================== */

.assigned-box {

    background: white;

    padding: 25px;

    border-radius: 10px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);

}


.section-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;

}


.section-header h2 {

    margin: 0;

}


.staff-count {

    background: #e7f1ff;

    color: #0056b3;

    padding: 7px 13px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;

}


.staff-table {

    width: 100%;

    border-collapse: collapse;

}


.staff-table th,
.staff-table td {

    padding: 12px;

    border-bottom: 1px solid #eee;

    text-align: left;

}


.staff-table th {

    background: #0056b3;

    color: white;

}


.staff-photo {

    width: 42px;

    height: 42px;

    border-radius: 50%;

    object-fit: cover;

}


.status {

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;

}


.status.active {

    background: #d1e7dd;

    color: #0f5132;

}


.status.inactive {

    background: #f8d7da;

    color: #842029;

}


.action-buttons {

    margin-top: 25px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

}


.edit-button {

    background: #ffc107;

    color: #212529;

    padding: 10px 16px;

    border-radius: 6px;

    text-decoration: none;

    font-weight: bold;

}


.back-button {

    background: #6c757d;

    color: white;

    padding: 10px 16px;

    border-radius: 6px;

    text-decoration: none;

    font-weight: bold;

}


.view-staff-button {

    background: #0d6efd;

    color: white;

    padding: 6px 10px;

    border-radius: 5px;

    text-decoration: none;

    font-size: 12px;

}


.empty-state {

    text-align: center;

    padding: 40px;

    color: #777;

}


@media(max-width:800px) {

    .info-grid {

        grid-template-columns: 1fr;

    }


    .profile-header {

        flex-direction: column;

        text-align: center;

    }

}

</style>

</head>


<body>


<div class="container">


<!-- ==============================
     SIDEBAR
============================== -->

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

<?php

echo strtoupper(
    substr($fullname, 0, 1)
);

?>

</div>


<h3>

<?php

echo htmlspecialchars($fullname);

?>

</h3>


<p>

<?php

echo htmlspecialchars($admin_role);

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


<!-- ==============================
     MAIN
============================== -->

<div class="main">


<div class="topbar">

<div>

<h1>
Supervisor Profile
</h1>

<p>
View supervisor information and assigned staff.
</p>

</div>


<div id="clock"></div>

</div>


<!-- ==============================
     SUPERVISOR PROFILE
============================== -->

<div class="supervisor-profile">


<div class="profile-header">


<?php

$photo =
    !empty($supervisor['profile_photo'])
    ? $supervisor['profile_photo']
    : 'default.png';

?>


<img

src="uploads/supervisors/<?php
echo htmlspecialchars($photo);
?>"

class="supervisor-large-photo"

onerror="
this.src='uploads/supervisors/default.png';
"

>


<div class="profile-info">


<h2>

<?php

echo htmlspecialchars(
    $supervisor['fullname']
);

?>

</h2>


<p>

Supervisor ID:

<strong>

<?php

echo htmlspecialchars(
    $supervisor['supervisor_id']
);

?>

</strong>

</p>


<span class="supervisor-status <?php
echo strtolower(
    $supervisor['status']
);
?>">

<?php

echo htmlspecialchars(
    $supervisor['status']
);

?>

</span>


</div>


</div>


<!-- INFORMATION -->


<div class="info-grid">


<div class="info-item">

<label>
Supervisor ID
</label>

<strong>

<?php

echo htmlspecialchars(
    $supervisor['supervisor_id']
);

?>

</strong>

</div>


<div class="info-item">

<label>
Full Name
</label>

<strong>

<?php

echo htmlspecialchars(
    $supervisor['fullname']
);

?>

</strong>

</div>


<div class="info-item">

<label>
Email
</label>

<strong>

<?php

echo !empty($supervisor['email'])
    ? htmlspecialchars($supervisor['email'])
    : 'Not provided';

?>

</strong>

</div>


<div class="info-item">

<label>
Phone
</label>

<strong>

<?php

echo !empty($supervisor['phone'])
    ? htmlspecialchars($supervisor['phone'])
    : 'Not provided';

?>

</strong>

</div>


<div class="info-item">

<label>
Department
</label>

<strong>

<?php

echo !empty($supervisor['department'])
    ? htmlspecialchars($supervisor['department'])
    : 'Not assigned';

?>

</strong>

</div>


<div class="info-item">

<label>
Assigned Staff
</label>

<strong>

<?php

echo $assigned_staff_count;

?>

Staff Member<?php
echo $assigned_staff_count == 1 ? '' : 's';
?>

</strong>

</div>


<div class="info-item">

<label>
Account Status
</label>

<strong>

<?php

echo htmlspecialchars(
    $supervisor['status']
);

?>

</strong>

</div>


<div class="info-item">

<label>
Date Created
</label>

<strong>

<?php

if (!empty($supervisor['created_at'])) {

    echo date(
        "d M Y, h:i A",
        strtotime(
            $supervisor['created_at']
        )
    );

} else {

    echo "Not available";

}

?>

</strong>

</div>


</div>


<!-- ACTIONS -->

<div class="action-buttons">


<a

href="edit_supervisor.php?supervisor_id=<?php
echo urlencode(
    $supervisor['supervisor_id']
);
?>"

class="edit-button"

>

✏️ Edit Supervisor

</a>


<a

href="manage_supervisors.php"

class="back-button"

>

← Back to Supervisors

</a>


</div>


</div>


<!-- ==============================
     ASSIGNED STAFF
============================== -->

<div class="assigned-box">


<div class="section-header">


<h2>
Assigned Staff
</h2>


<span class="staff-count">

<?php

echo $assigned_staff_count;

?>

Staff

</span>


</div>


<?php

if ($assigned_staff_count > 0) {

?>


<div style="overflow-x:auto;">


<table class="staff-table">


<thead>

<tr>

<th>
Photo
</th>

<th>
Staff ID
</th>

<th>
Name
</th>

<th>
Department
</th>

<th>
Position
</th>

<th>
Status
</th>

<th>
Action
</th>

</tr>

</thead>


<tbody>


<?php

while (
    $staff =
    mysqli_fetch_assoc(
        $staff_query
    )
) {

    $staff_photo =
        !empty($staff['profile_picture'])
        ? $staff['profile_picture']
        : 'default.png';

?>


<tr>


<td>

<img

src="uploads/staff/<?php

echo htmlspecialchars(
    $staff_photo
);

?>"

class="staff-photo"

onerror="
this.src='uploads/staff/default.png';
"

>

</td>


<td>

<?php

echo htmlspecialchars(
    $staff['staff_id']
);

?>

</td>


<td>

<strong>

<?php

echo htmlspecialchars(
    $staff['fullname']
);

?>

</strong>

</td>


<td>

<?php

echo !empty($staff['department'])
    ? htmlspecialchars(
        $staff['department']
    )
    : 'N/A';

?>

</td>


<td>

<?php

echo !empty($staff['position'])
    ? htmlspecialchars(
        $staff['position']
    )
    : 'N/A';

?>

</td>


<td>

<span class="status <?php
echo strtolower(
    $staff['status']
);
?>">

<?php

echo htmlspecialchars(
    $staff['status']
);

?>

</span>

</td>


<td>

<a

href="view_staff.php?staff_id=<?php
echo urlencode(
    $staff['staff_id']
);
?>"

class="view-staff-button"

>

View Staff

</a>

</td>


</tr>


<?php

}

?>


</tbody>

</table>

</div>


<?php

} else {

?>


<div class="empty-state">

<h3>
No Staff Assigned
</h3>

<p>
This supervisor currently has no staff members assigned to them.
</p>

</div>


<?php

}

?>


</div>


</div>


</div>


<script>

function updateClock() {

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();

}

updateClock();

setInterval(
    updateClock,
    1000
);

</script>


</body>

</html>