<?php

session_start();

include "config.php";


/* =========================================================
   CHECK LOGIN
========================================================= */

if (!isset($_SESSION['staff_id'])) {

    header("Location: stafflogin.php");
    exit();

}


$staff_id = $_SESSION['staff_id'];


/* =========================================================
   FETCH STAFF INFORMATION
========================================================= */

$stmt = $conn->prepare("

    SELECT
        staff.*,
        supervisors.fullname AS supervisor_name

    FROM staff

    LEFT JOIN supervisors
        ON staff.supervisor_id = supervisors.supervisor_id

    WHERE staff.staff_id = ?

");


if (!$stmt) {

    die("Prepare failed: " . $conn->error);

}


$stmt->bind_param("s", $staff_id);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows == 0) {

    die("Staff member not found.");

}


$staff = $result->fetch_assoc();

$stmt->close();


$fullname = $staff['fullname'];


/* =========================================================
   PROFILE PHOTO
========================================================= */

$profile_photo = "uploads/staff/default.png";

if (!empty($staff['profile_picture'])) {

    $photo_name = basename($staff['profile_picture']);

    /* New staff photo location */
    if (file_exists("uploads/staff/" . $photo_name)) {

        $profile_photo =
            "uploads/staff/" . $photo_name;

    }

    /* Older photo location */
    elseif (file_exists("uploads/" . $photo_name)) {

        $profile_photo =
            "uploads/" . $photo_name;

    }

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
My Profile
</title>

<link
rel="stylesheet"
href="styles.css"
>


<style>

/* =========================================================
   STAFF PROFILE CARD
========================================================= */

.staff-profile-card {

    background:#fff;

    border-radius:12px;

    padding:30px;

    display:flex;

    gap:40px;

    margin-bottom:25px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.08);

}


/* =========================================================
   LEFT PROFILE
========================================================= */

.profile-left {

    width:280px;

    text-align:center;

    border-right:1px solid #eee;

    padding-right:30px;

}


.profile-image {

    width:150px;

    height:150px;

    border-radius:50%;

    object-fit:cover;

    border:5px solid #eee;

    margin-bottom:15px;

}


.profile-left h2 {

    margin:5px 0 10px;

}


.role-tag {

    display:inline-block;

    background:#e8f0ff;

    color:#2457c5;

    padding:7px 15px;

    border-radius:20px;

    font-size:13px;

    font-weight:bold;

}


.position {

    color:#666;

    margin:12px 0;

}


.status-tag {

    display:inline-block;

    background:#d1e7dd;

    color:#0f5132;

    padding:7px 15px;

    border-radius:20px;

    font-size:13px;

    font-weight:bold;

}


/* =========================================================
   RIGHT INFORMATION
========================================================= */

.profile-right {

    flex:1;

}


.profile-right h2 {

    margin-top:0;

    margin-bottom:20px;

}


.profile-table {

    width:100%;

    border-collapse:collapse;

}


.profile-table tr {

    border-bottom:1px solid #eee;

}


.profile-table th,
.profile-table td {

    padding:15px;

    text-align:left;

}


.profile-table th {

    width:220px;

    background:#f8f9fa;

    color:#555;

}


.profile-table td {

    color:#333;

}


/* =========================================================
   BUTTONS
========================================================= */

.profile-buttons {

    display:flex;

    flex-wrap:wrap;

    gap:12px;

    margin-bottom:25px;

}


.profile-buttons .btn {

    text-decoration:none;

    padding:12px 18px;

    border-radius:6px;

    color:white;

    display:inline-block;

    font-weight:bold;

}


.btn.blue {

    background:#0d6efd;

}


.btn.orange {

    background:#fd7e14;

}


.btn.dark {

    background:#343a40;

}


/* =========================================================
   PROFILE INFORMATION NOTICE
========================================================= */

.profile-notice {

    background:#e8f0ff;

    border-left:5px solid #0d6efd;

    padding:15px 18px;

    border-radius:6px;

    margin-bottom:25px;

    color:#333;

}


.profile-notice strong {

    display:block;

    margin-bottom:5px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

    .staff-profile-card {

        flex-direction:column;

    }


    .profile-left {

        width:auto;

        border-right:none;

        border-bottom:1px solid #eee;

        padding-right:0;

        padding-bottom:25px;

    }

}


@media(max-width:600px) {

    .profile-table th {

        width:auto;

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

echo $app['organization_name'];

?>

</h2>

<p>
Personnel Timesheet System
</p>

</div>


<div class="profile">


<div class="avatar">

<img
    src="<?php echo htmlspecialchars($profile_photo); ?>"
    alt="Profile Photo"
    class="sidebar-profile-photo"
    onerror="this.src='uploads/staff/default.png';"
>

</div>


<h3>

<?php

echo htmlspecialchars(
    $staff['fullname']
);

?>

</h3>


<p>
Staff
</p>


</div>


<ul>


<li>

<a href="staff_dashboard.php">

Dashboard

</a>

</li>


<li class="active">

<a href="staff_profile.php">

Profile

</a>

</li>


<li>

<a href="work_schedule.php">

My Schedule

</a>

</li>


<li>

<a href="staff_logout.php">

Logout

</a>

</li>


</ul>


</div>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">


<!-- TOP BAR -->

<div class="topbar">


<div>

<h1>
My Profile
</h1>

<p>
Staff Information
</p>

</div>


<div id="clock"></div>


</div>


<!-- =========================================================
     NOTICE
========================================================= -->

<div class="profile-notice">

<strong>
🔒 Profile Information
</strong>

Your personal information is managed by the administrator.
You can view your information here, but you cannot edit it.

</div>


<!-- =========================================================
     PROFILE CARD
========================================================= -->

<div class="staff-profile-card">


<!-- LEFT SIDE -->

<div class="profile-left">


<img

src="<?php echo htmlspecialchars($profile_photo); ?>"

class="profile-image"

onerror="this.src='uploads/staff/default.png';"

alt="Profile Photo"

>


<h2>

<?php

echo htmlspecialchars(
    $staff['fullname']
);

?>

</h2>


<span class="role-tag">

👤 Staff

</span>


<p class="position">

<?php

echo htmlspecialchars(
    $staff['position'] ?? 'Staff'
);

?>

</p>


<span class="status-tag">

●

<?php

echo htmlspecialchars(
    $staff['status']
);

?>

</span>


</div>


<!-- RIGHT SIDE -->

<div class="profile-right">


<h2>
Personal Information
</h2>


<table class="profile-table">


<tr>

<th>
🆔 Staff ID
</th>

<td>

<?php

echo htmlspecialchars(
    $staff['staff_id']
);

?>

</td>

</tr>


<tr>

<th>
📧 Email
</th>

<td>

<?php

echo htmlspecialchars(
    $staff['email'] ?? 'Not provided'
);

?>

</td>

</tr>


<tr>

<th>
📞 Phone
</th>

<td>

<?php

echo htmlspecialchars(
    $staff['phone'] ?? 'Not provided'
);

?>

</td>

</tr>


<tr>

<th>
🏢 Department
</th>

<td>

<?php

echo htmlspecialchars(
    $staff['department'] ?? 'Not assigned'
);

?>

</td>

</tr>


<tr>

<th>
💼 Position
</th>

<td>

<?php

echo htmlspecialchars(
    $staff['position'] ?? 'Not provided'
);

?>

</td>

</tr>


<tr>

<th>
👨‍💼 Supervisor
</th>

<td>

<?php

if (!empty($staff['supervisor_name'])) {

    echo htmlspecialchars(
        $staff['supervisor_name']
    );

} else {

    echo "Not Assigned";

}

?>

</td>

</tr>


<tr>

<th>
📅 Date Joined
</th>

<td>

<?php

if (!empty($staff['date_joined'])) {

    echo date(
        "d M Y",
        strtotime($staff['date_joined'])
    );

} else {

    echo "Not provided";

}

?>

</td>

</tr>


<tr>

<th>
📌 Status
</th>

<td>

<?php

echo htmlspecialchars(
    $staff['status']
);

?>

</td>

</tr>


</table>


</div>


</div>


<!-- =========================================================
     BUTTONS
========================================================= -->

<div class="profile-buttons">


<a

href="change_staff_password.php"

class="btn blue"

>

🔑 Change Password

</a>


<a

href="work_schedule.php"

class="btn orange"

>

📅 My Schedule

</a>


</div>


</div>


</div>


<script>

/* =========================================================
   CLOCK
========================================================= */

function updateClock(){

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();

}


updateClock();

setInterval(updateClock,1000);

</script>


</body>

</html>