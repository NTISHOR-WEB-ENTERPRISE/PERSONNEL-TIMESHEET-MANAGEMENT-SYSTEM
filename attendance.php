<?php

session_start();

include "config.php";
include "activity_logger.php";
include "notification_function.php";


/* =========================================================
   CHECK LOGIN
========================================================= */

if (!isset($_SESSION['staff_id'])) {

    header("Location: staff_login.php");
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

    $photo_name = basename(
        $staff['profile_picture']
    );


    /* NEW STAFF PHOTO LOCATION */

    if (
        file_exists(
            "uploads/staff/" . $photo_name
        )
    ) {

        $profile_photo =
            "uploads/staff/" . $photo_name;

    }


    /* OLD PHOTO LOCATION */

    elseif (
        file_exists(
            "uploads/" . $photo_name
        )
    ) {

        $profile_photo =
            "uploads/" . $photo_name;

    }

}


/* =========================================================
   TODAY
========================================================= */

$today = date("Y-m-d");


/* =========================================================
   CLOCK IN
========================================================= */

if (isset($_POST['clock_in'])) {

    $check = mysqli_query(
        $conn,
        "SELECT *
         FROM attendance
         WHERE staff_id='$staff_id'
         AND date='$today'"
    );


    if (mysqli_num_rows($check) == 0) {

        $time = date("H:i:s");


        mysqli_query(
            $conn,
            "INSERT INTO attendance
            (
                staff_id,
                date,
                clock_in,
                status
            )
            VALUES
            (
                '$staff_id',
                '$today',
                '$time',
                'Present'
            )"
        );


        logActivity(
            $conn,
            $staff_id,
            "Staff",
            "Clock In",
            "Staff clocked in"
        );

    }

}


/* =========================================================
   CLOCK OUT
========================================================= */

if (isset($_POST['clock_out'])) {

    $check = mysqli_query(
        $conn,
        "SELECT *
         FROM attendance
         WHERE staff_id='$staff_id'
         AND date='$today'"
    );


    if (mysqli_num_rows($check) > 0) {

        $time = date("H:i:s");


        mysqli_query(
            $conn,
            "UPDATE attendance

             SET clock_out='$time'

             WHERE staff_id='$staff_id'

             AND date='$today'"
        );


        logActivity(
            $conn,
            $staff_id,
            "Staff",
            "Clock Out",
            "Staff clocked out"
        );

    }

}


/* =========================================================
   GET TODAY'S ATTENDANCE
========================================================= */

$attendance = mysqli_query(
    $conn,

    "SELECT *
     FROM attendance

     WHERE staff_id='$staff_id'

     AND date='$today'"
);


$row = mysqli_fetch_assoc($attendance);

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
Attendance
</title>

<link
    rel="stylesheet"
    href="styles.css"
>


<style>

/* =========================================================
   SIDEBAR PROFILE PHOTO
========================================================= */

.sidebar .profile .avatar {

    width:60px;

    height:60px;

    border-radius:50%;

    overflow:hidden;

    margin:0 auto 10px;

    background:#eee;

    display:flex;

    align-items:center;

    justify-content:center;

}


.sidebar .profile .sidebar-profile-photo {

    width:100%;

    height:100%;

    object-fit:cover;

    display:block;

    border-radius:50%;

}


/* =========================================================
   ATTENDANCE PAGE
========================================================= */

.clock-card {

    background:#fff;

    padding:40px;

    border-radius:12px;

    text-align:center;

    margin-bottom:25px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.08);

}


.status-badge {

    display:inline-block;

    padding:8px 15px;

    border-radius:20px;

    background:#f1f1f1;

    margin-bottom:15px;

    font-weight:bold;

}



.big-clock {

    font-size:50px;

    margin:10px 0;

}


.clock-date {

    color:#666;

}


.clock-buttons {

    display:flex;

    justify-content:center;

    gap:15px;

    margin-top:25px;

}


.clockin-btn,
.clockout-btn {

    padding:12px 25px;

    border:none;

    border-radius:6px;

    color:#fff;

    cursor:pointer;

    font-weight:bold;

}


.clockin-btn {

    background:#198754;

}


.clockout-btn {

    background:#dc3545;

}


.clockin-btn:disabled,
.clockout-btn:disabled {

    background:#aaa;

    cursor:not-allowed;

}


.attendance-card {

    background:#fff;

    border-radius:12px;

    padding:25px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.07);

}


.card-header {

    margin-bottom:20px;

}


.attendance-table {

    width:100%;

    border-collapse:collapse;

}


.attendance-table th,
.attendance-table td {

    padding:14px;

    border-bottom:1px solid #eee;

    text-align:left;

}


.attendance-table th {

    background:#f5f6f8;

}


.approved {

    background:#d1e7dd;

    color:#0f5132;

    padding:6px 10px;

    border-radius:20px;

    font-size:12px;

    font-weight:bold;

}


@media(max-width:600px) {

    .clock-buttons {

        flex-direction:column;

    }

    .big-clock {

        font-size:38px;

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


<!-- STAFF PROFILE -->

<div class="profile">


<div class="avatar">

<img

    src="<?php
        echo htmlspecialchars(
            $profile_photo
        );
    ?>"

    alt="Profile Photo"

    class="sidebar-profile-photo"

    onerror="this.src='uploads/staff/default.png';"

>

</div>


<h4>

<?php

echo htmlspecialchars(
    $staff['fullname']
);

?>

</h4>


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

<a href="attendance.php">

Clock In/Out

</a>

</li>


<li>

<a href="staff_submit_task_report.php">

My Task

</a>

</li>


<li>

<a href="staff_attendance_history.php">

My Attendance History

</a>

</li>

<li><a href="submit_timesheet.php">Submit Timesheet</a></li>

<li>
                <a href="task_history.php"> Task Submission History </a>
            </li>


<li>

<a href="staff_profile.php">

Profile

</a>

</li>


<li>

<a href="staff_notifications.php">

Notifications

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
     MAIN CONTENT
========================================================= -->

<div class="main">


<!-- TOPBAR -->

<div class="topbar">


<div>

<h1>
Clock In / Out
</h1>

</div>


<div id="clock"></div>


</div>


<!-- =========================================================
     CLOCK CARD
========================================================= -->

<div class="clock-card">


<span class="status-badge">


<?php

if (!$row) {

    echo "⚫ Not Clocked In";

}

elseif (
    !empty($row['clock_in']) &&
    empty($row['clock_out'])
) {

    echo "🟢 Clocked In";

}

else {

    echo "🔴 Clocked Out";

}

?>


</span>


<h1
    class="big-clock"
    id="bigClock"
></h1>


<p class="clock-date">

<?php

echo date(
    "l, F d, Y"
);

?>

</p>


<form
    method="POST"
    class="clock-buttons"
>


<button

    type="submit"

    name="clock_in"

    class="clockin-btn"

    <?php

    echo ($row)
        ? 'disabled'
        : '';

    ?>

>

Clock In

</button>


<button

    type="submit"

    name="clock_out"

    class="clockout-btn"

    <?php

    echo (
        !$row ||
        !empty($row['clock_out'])
    )
        ? 'disabled'
        : '';

    ?>

>

Clock Out

</button>


</form>


</div>


<!-- =========================================================
     ATTENDANCE LOG
========================================================= -->

<div class="attendance-card">


<div class="card-header">

<h3>
Today's Time Log
</h3>

</div>


<table class="attendance-table">


<thead>

<tr>

<th>
Date
</th>

<th>
Clock In
</th>

<th>
Clock Out
</th>

<th>
Status
</th>

</tr>

</thead>


<tbody>


<?php

if ($row) {

?>


<tr>


<td>

<?php

echo htmlspecialchars(
    $today
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['clock_in']
);

?>

</td>


<td>

<?php

echo !empty($row['clock_out'])
    ? htmlspecialchars(
        $row['clock_out']
    )
    : 'Not Yet';

?>

</td>


<td>

<span class="approved">

Present

</span>

</td>


</tr>


<?php

} else {

?>


<tr>

<td
    colspan="4"
    style="text-align:center;"
>

No attendance record today

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

/* =========================================================
   CLOCK
========================================================= */

function updateClock() {

    let now = new Date();

    let time =
        now.toLocaleTimeString(
            'en-GB',
            {
                hour12:false
            }
        );


    document.getElementById(
        'clock'
    ).innerHTML = time;


    document.getElementById(
        'bigClock'
    ).innerHTML = time;

}


updateClock();

setInterval(
    updateClock,
    1000
);

</script>


</body>

</html>