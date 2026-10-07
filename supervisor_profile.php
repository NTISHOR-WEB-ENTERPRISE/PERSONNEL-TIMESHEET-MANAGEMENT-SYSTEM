<?php

session_start();

include "config.php";


/* =========================================================
   CHECK LOGIN
========================================================= */

if (!isset($_SESSION['supervisor_id'])) {

    header("Location: supervisor_login.php");
    exit();

}


$supervisor_id = $_SESSION['supervisor_id'];


/* =========================================================
   FETCH SUPERVISOR INFORMATION
========================================================= */

$stmt = $conn->prepare("

    SELECT *

    FROM supervisors

    WHERE supervisor_id = ?

");


if (!$stmt) {

    die("Prepare failed: " . $conn->error);

}


$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows == 0) {

    die("Supervisor not found.");

}


$supervisor = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   SUPERVISOR NAME
========================================================= */

$fullname = $supervisor['fullname'];


/* =========================================================
   PROFILE PHOTO
========================================================= */

/*
   Supervisor photos are stored in:

   uploads/supervisors/
*/

if (
    !empty($supervisor['profile_photo']) &&
    file_exists(
        "uploads/supervisors/" .
        $supervisor['profile_photo']
    )
) {

    $profile_photo =
        "uploads/supervisors/" .
        $supervisor['profile_photo'];

} else {

    $profile_photo =
        "uploads/supervisors/default.png";

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
   PROFILE CARD
========================================================= */

.supervisor-profile-card {

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
   INFORMATION NOTICE
========================================================= */

.profile-notice {

    background:#e8f0ff;

    border-left:4px solid #0d6efd;

    padding:14px 16px;

    border-radius:6px;

    margin-bottom:20px;

    color:#333;

}


.profile-notice strong {

    display:block;

    margin-bottom:5px;

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
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

    .supervisor-profile-card {

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


    .profile-table th,
    .profile-table td {

        padding:10px;

        font-size:13px;

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

/*
   Show supervisor's actual photo
   in the sidebar when available.
*/

?>

<img

src="<?php echo htmlspecialchars($profile_photo); ?>"

class="profile-small"

alt="Profile Photo"

onerror="this.src='uploads/supervisors/default.png';"

>

</div>


<h3>

<?php

echo htmlspecialchars(
    $supervisor['fullname']
);

?>

</h3>


<p>
Supervisor
</p>


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


<li>

<a href="task_report_approvals.php">

Task Reports

</a>

</li>


<li>

<a href="reports.php">

Reports

</a>

</li>


<li class="active">

<a href="supervisor_profile.php">

My Profile

</a>

</li>


<li>

<a href="supervisor_logout.php">

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
Supervisor Information
</p>

</div>


<div id="clock"></div>


</div>


<!-- =========================================================
     INFORMATION NOTICE
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

<div class="supervisor-profile-card">


<!-- =====================================================
     LEFT SIDE
===================================================== -->

<div class="profile-left">


<img

src="<?php echo htmlspecialchars($profile_photo); ?>"

class="profile-image"

alt="Profile Photo"

onerror="this.src='uploads/supervisors/default.png';"

>


<h2>

<?php

echo htmlspecialchars(
    $supervisor['fullname']
);

?>

</h2>


<span class="role-tag">

👤 Supervisor

</span>


<?php if (!empty($supervisor['position'])) { ?>

<p class="position">

<?php

echo htmlspecialchars(
    $supervisor['position']
);

?>

</p>

<?php } ?>


<?php if (!empty($supervisor['status'])) { ?>

<span class="status-tag">

●

<?php

echo htmlspecialchars(
    $supervisor['status']
);

?>

</span>

<?php } ?>


</div>


<!-- =====================================================
     RIGHT SIDE
===================================================== -->

<div class="profile-right">


<h2>
Personal Information
</h2>


<table class="profile-table">


<tr>

<th>
🆔 Supervisor ID
</th>

<td>

<?php

echo htmlspecialchars(
    $supervisor['supervisor_id']
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
    $supervisor['email'] ?? 'Not provided'
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
    $supervisor['phone'] ?? 'Not provided'
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
    $supervisor['department'] ?? 'Not assigned'
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
    $supervisor['position'] ?? 'Supervisor'
);

?>

</td>

</tr>


<tr>

<th>
📅 Date Joined
</th>

<td>

<?php

if (!empty($supervisor['created_at'])) {

    echo date(
        "d M Y",
        strtotime(
            $supervisor['created_at']
        )
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
    $supervisor['status'] ?? 'Active'
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

<a href="change_supervisor_password.php" class="btn blue">🔑 Change Password</a>

<a href="supervisor_dashboard.php" class="btn dark">🏠 Dashboard</a>

</div>

</div>


</div>


<!-- =========================================================
     CLOCK
========================================================= -->

<script>

function updateClock(){

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