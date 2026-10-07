<?php

session_start();

include "config.php";
include "activity_logger.php";


/* ==============================
   ADMIN AUTHENTICATION
============================== */

if(!isset($_SESSION['admin_id'])){

    header("Location: admin_login.php");
    exit();

}



$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

/* ==============================
   ADMIN PROFILE PHOTO
============================== */

$admin_id = $_SESSION['admin_id'];

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

$admin_profile_photo = "uploads/admins/default.png";

if (
    $admin_data &&
    !empty($admin_data['profile_photo'])
) {

    $photo = basename($admin_data['profile_photo']);

    $photo_path = "uploads/admins/" . $photo;

    if (file_exists($photo_path)) {

        $admin_profile_photo = $photo_path;

    }

}

/* ==============================
   GET STAFF ID
============================== */

if(!isset($_GET['staff_id']) || empty($_GET['staff_id'])){

    header("Location: manage_staff.php");
    exit();

}

$staff_id = mysqli_real_escape_string(
    $conn,
    $_GET['staff_id']
);


/* ==============================
   GET STAFF INFORMATION
============================== */

$staff_query = mysqli_query($conn, "

    SELECT
        staff.*,
        supervisors.fullname AS supervisor_name

    FROM staff

    LEFT JOIN supervisors
        ON staff.supervisor_id = supervisors.supervisor_id

    WHERE staff.staff_id = '$staff_id'

");


if(!$staff_query){

    die("Database Error: " . mysqli_error($conn));

}


if(mysqli_num_rows($staff_query) == 0){

    die("Staff member not found.");

}


$staff = mysqli_fetch_assoc($staff_query);


/* Keep the actual staff ID */
$staff_id = $staff['staff_id'];


/* ==============================
   GET ASSIGNED TASKS
============================== */

$tasks_query = mysqli_query($conn,"

    SELECT *

    FROM assigned_tasks

    WHERE staff_id='$staff_id'

    ORDER BY created_at DESC

");


/* ==============================
   TASK STATISTICS
============================== */

$total_tasks = mysqli_fetch_assoc(

    mysqli_query($conn,"

        SELECT COUNT(*) AS total

        FROM assigned_tasks

        WHERE staff_id='$staff_id'

    ")

)['total'];


$assigned_tasks = mysqli_fetch_assoc(

    mysqli_query($conn,"

        SELECT COUNT(*) AS total

        FROM assigned_tasks

        WHERE staff_id='$staff_id'

        AND status='Assigned'

    ")

)['total'];


$in_progress_tasks = mysqli_fetch_assoc(

    mysqli_query($conn,"

        SELECT COUNT(*) AS total

        FROM assigned_tasks

        WHERE staff_id='$staff_id'

        AND status='In Progress'

    ")

)['total'];


$completed_tasks = mysqli_fetch_assoc(

    mysqli_query($conn,"

        SELECT COUNT(*) AS total

        FROM assigned_tasks

        WHERE staff_id='$staff_id'

        AND status='Completed'

    ")

)['total'];


/* ==============================
   GET WEEKLY WORK SCHEDULE
============================== */

$schedule_query = mysqli_query($conn,"

    SELECT *

    FROM work_schedule

    WHERE staff_id='$staff_id'

    ORDER BY FIELD(
        day_name,
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    )

");

?>

<!DOCTYPE html>

<html>

<head>

<title>
Manage Staff - <?php echo htmlspecialchars($staff['fullname']); ?>
</title>

<link rel="stylesheet" href="styles.css">


<style>

/* ==============================
   STAFF PROFILE
============================== */

.staff-profile-card{

    background:#fff;

    border-radius:12px;

    padding:25px;

    margin-bottom:25px;

    box-shadow:0 4px 15px rgba(0,0,0,.08);

}


.staff-profile{

    display:flex;

    align-items:center;

    gap:25px;

}


.staff-profile img{

    width:100px;

    height:100px;

    border-radius:50%;

    object-fit:cover;

    border:4px solid #eee;

}


.staff-info h2{

    margin:0 0 5px;

}


.staff-info p{

    margin:5px 0;

    color:#666;

}


/* ==============================
   INFORMATION GRID
============================== */

.info-grid{

    display:grid;

    grid-template-columns:repeat(3,1fr);

    gap:20px;

    margin-top:25px;

}


.info-item{

    background:#f8f9fa;

    padding:15px;

    border-radius:8px;

}


.info-item strong{

    display:block;

    color:#555;

    margin-bottom:5px;

}


/* ==============================
   ACTION BUTTONS
============================== */

.staff-actions{

    display:flex;

    flex-wrap:wrap;

    gap:10px;

    margin-top:25px;

}


/* ==============================
   TASK STATISTICS
============================== */

.task-statistics{

    display:grid;

    grid-template-columns:repeat(4,1fr);

    gap:20px;

    margin-bottom:25px;

}


.task-stat{

    background:#fff;

    padding:20px;

    border-radius:10px;

    box-shadow:0 3px 12px rgba(0,0,0,.07);

}


.task-stat h4{

    margin:0 0 10px;

    color:#666;

}


.task-stat h2{

    margin:0;

}


/* ==============================
   SECTION BOX
============================== */

.section-box{

    background:#fff;

    border-radius:12px;

    padding:25px;

    margin-bottom:25px;

    box-shadow:0 3px 12px rgba(0,0,0,.07);

}


/* ==============================
   SECTION HEADER
============================== */

.section-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:20px;

    gap:15px;

}


.section-header h2{

    margin:0;

}


/* ==============================
   SCHEDULE TABLE
============================== */

.schedule-table{

    width:100%;

    border-collapse:collapse;

}


.schedule-table th,
.schedule-table td{

    padding:14px;

    border-bottom:1px solid #eee;

    text-align:left;

}


.schedule-table th{

    background:#f5f6f8;

}


.schedule-status{

    display:inline-block;

    padding:6px 10px;

    border-radius:20px;

    font-size:12px;

    font-weight:bold;

}


.schedule-working{

    background:#d1e7dd;

    color:#0f5132;

}


.schedule-leave{

    background:#f8d7da;

    color:#842029;

}


.schedule-off{

    background:#e2e3e5;

    color:#41464b;

}


/* ==============================
   TASK TABLE
============================== */

.task-table{

    width:100%;

    border-collapse:collapse;

}


.task-table th,
.task-table td{

    padding:14px;

    border-bottom:1px solid #eee;

    text-align:left;

}


.task-table th{

    background:#f5f6f8;

}


/* ==============================
   STATUS
============================== */

.task-status{

    padding:6px 10px;

    border-radius:20px;

    font-size:12px;

    font-weight:bold;

}


.task-assigned{

    background:#fff3cd;

    color:#856404;

}


.task-progress{

    background:#cfe2ff;

    color:#084298;

}


.task-completed{

    background:#d1e7dd;

    color:#0f5132;

}


/* ==============================
   RESPONSIVE
============================== */

@media(max-width:900px){

    .info-grid{

        grid-template-columns:1fr 1fr;

    }


    .task-statistics{

        grid-template-columns:1fr 1fr;

    }

}


@media(max-width:600px){

    .staff-profile{

        flex-direction:column;

        text-align:center;

    }


    .info-grid{

        grid-template-columns:1fr;

    }


    .task-statistics{

        grid-template-columns:1fr;

    }


    .section-header{

        flex-direction:column;

        align-items:flex-start;

    }


    .schedule-table{

        font-size:13px;

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
        src="<?php echo htmlspecialchars($admin_profile_photo); ?>"
        class="profile-small"
        alt="Profile"
        onerror="this.onerror=null; this.src='uploads/admins/default.png';"
    >

    </div>


<h3>

<?php

echo htmlspecialchars($fullname);

?>

</h3>


<p> <?php echo htmlspecialchars($admin_role); ?></p>

</div>

<ul>

<li> <a href="admin_dashboard.php">Dashboard</a></li>

<li><a href="manage_admins.php">Manage Admins</a></li>

<li><a href="manage_supervisors.php">Manage Supervisors</a></li>

<li class="active"><a href="manage_staff.php">Manage Staff</a></li>

<li><a href="admin_reports.php">Reports</a></li>

<li><a href="admin_logout.php">Logout</a></li>

</ul>

</div>

<!-- ==============================
     MAIN
============================== -->

<div class="main">


<div class="topbar">


<div>

<h1>

Manage Staff

</h1>

<p>

Staff details, tasks and work schedule

</p>

</div>


<div id="clock"></div>


</div>



<!-- ==============================
     STAFF PROFILE
============================== -->

<div class="staff-profile-card">


<div class="staff-profile">

<?php

/* ==============================
   STAFF PROFILE PHOTO
============================== */

$profile_photo = $staff['profile_picture'] ?? '';

if (!empty($profile_photo)) {

    if (
        strpos($profile_photo, 'uploads/staff/') === 0
    ) {

        $photo_path = $profile_photo;

    } else {

        $photo_path =
            'uploads/staff/' . $profile_photo;
    }

} else {

    $photo_path =
        'uploads/staff/default.png';
}

?>

<img
    src="<?php echo htmlspecialchars($photo_path); ?>"
    alt="Staff Profile Photo"
    onerror="this.onerror=null; this.src='uploads/staff/default.png';"
>


<div class="staff-info">


<h2>

<?php

echo htmlspecialchars(
    $staff['fullname']
);

?>

</h2>


<p>

Staff ID:

<strong>

<?php

echo htmlspecialchars(
    $staff['staff_id']
);

?>

</strong>

</p>


<p>

<?php

echo htmlspecialchars(
    $staff['position'] ?? 'Staff'
);

?>

</p>


</div>


</div>



<!-- ==============================
     STAFF INFORMATION
============================== -->

<div class="info-grid">


<div class="info-item">

<strong>
Email
</strong>

<?php

echo htmlspecialchars(
    $staff['email'] ?? 'Not provided'
);

?>

</div>


<div class="info-item">

<strong>
Phone
</strong>

<?php

echo htmlspecialchars(
    $staff['phone'] ?? 'Not provided'
);

?>

</div>


<div class="info-item">

<strong>
Department
</strong>

<?php

echo htmlspecialchars(
    $staff['department'] ?? 'Not assigned'
);

?>

</div>


<div class="info-item">

<strong>
Position
</strong>

<?php

echo htmlspecialchars(
    $staff['position'] ?? 'Not provided'
);

?>

</div>


<div class="info-item">

<strong>
Supervisor
</strong>


<?php

if(!empty($staff['supervisor_name'])){

    echo htmlspecialchars(
        $staff['supervisor_name']
    );

}else{

    echo "Not Assigned";

}

?>

</div>


<div class="info-item">

<strong>
Status
</strong>


<?php

echo htmlspecialchars(
    $staff['status']
);

?>

</div>


</div>



<!-- ==============================
     STAFF ACTIONS
============================== -->

<div class="staff-actions">


<a

href="edit_staff.php?id=<?php echo $staff['staff_id']; ?>"

class="approve-btn"

>

✏️ Edit Staff

</a>


<?php

if($staff['status']=="Active"){

?>

<a

href="deactivate_staff.php?id=<?php echo $staff['staff_id']; ?>"

class="reject-btn"

onclick="return confirm('Deactivate this staff member?')"

>

⏸ Deactivate

</a>

<?php

}else{

?>

<a

href="activate_staff.php?id=<?php echo $staff['staff_id']; ?>"

class="excel-btn"

>

▶ Activate

</a>

<?php

}

?>


<a

href="delete_staff.php?id=<?php echo $staff['staff_id']; ?>"

class="delete-btn"

onclick="return confirm('WARNING: This will permanently delete this staff member and may affect related records. Continue?')"

>

🗑 Delete

</a>


<a

href="manage_staff.php"

class="print-btn"

>

← Back to Staff

</a>



</div>


</div>



<!-- ==============================
     TASK STATISTICS
============================== -->

<div class="task-statistics">


<div class="task-stat">

<h4>
Total Tasks
</h4>

<h2>

<?php

echo $total_tasks;

?>

</h2>

</div>


<div class="task-stat">

<h4>
Assigned
</h4>

<h2>

<?php

echo $assigned_tasks;

?>

</h2>

</div>


<div class="task-stat">

<h4>
In Progress
</h4>

<h2>

<?php

echo $in_progress_tasks;

?>

</h2>

</div>


<div class="task-stat">

<h4>
Completed
</h4>

<h2>

<?php

echo $completed_tasks;

?>

</h2>

</div>


</div>



<!-- =====================================================
     WEEKLY WORK SCHEDULE
===================================================== -->

<div class="section-box">


<div class="section-header">


<h2>

📅 Weekly Work Schedule

</h2>


<a

href="assign_schedule.php?staff_id=<?php echo urlencode($staff_id); ?>"

class="btn btn-success"

>

+ Assign Weekly Schedule

</a>


</div>


<table class="schedule-table">


<thead>

<tr>

<th>
Day
</th>

<th>
Shift
</th>

<th>
Start Time
</th>

<th>
End Time
</th>

<th>
Status
</th>

</tr>

</thead>


<tbody>


<?php

if(mysqli_num_rows($schedule_query) > 0){

    while($schedule = mysqli_fetch_assoc($schedule_query)){

?>


<tr>


<td>

<strong>

<?php

echo htmlspecialchars(
    $schedule['day_name']
);

?>

</strong>

</td>


<td>

<?php

echo htmlspecialchars(
    $schedule['shift']
);

?>

</td>


<td>

<?php

echo !empty($schedule['start_time'])

    ? date(
        "h:i A",
        strtotime($schedule['start_time'])
      )

    : "--";

?>

</td>


<td>

<?php

echo !empty($schedule['end_time'])

    ? date(
        "h:i A",
        strtotime($schedule['end_time'])
      )

    : "--";

?>

</td>


<td>


<?php

$schedule_status = $schedule['status'];

if($schedule_status == "Working"){

    echo "<span class='schedule-status schedule-working'>Working</span>";

}elseif($schedule_status == "Leave"){

    echo "<span class='schedule-status schedule-leave'>Leave</span>";

}else{

    echo "<span class='schedule-status schedule-off'>Off Day</span>";

}

?>


</td>


</tr>


<?php

    }

}else{

?>


<tr>

<td

colspan="5"

style="text-align:center;padding:30px;"

>

No weekly work schedule has been assigned to this staff member yet.

<br><br>


<a

href="assign_schedule.php?staff_id=<?php echo urlencode($staff_id); ?>"

class="btn btn-success"

>

+ Assign Weekly Schedule

</a>


</td>

</tr>


<?php

}

?>


</tbody>

</table>


</div>



<!-- ==============================
     ASSIGNED TASKS
============================== -->

<div class="section-box">


<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">


<h2>

Assigned Tasks

</h2>


<a

href="assign_task.php?staff_id=<?php echo urlencode($staff_id); ?>"

class="btn btn-success"

>

+ Assign New Task

</a>


</div>



<table class="task-table">


<thead>


<tr>

<th>
Task
</th>

<th>
Priority
</th>

<th>
Assigned Date
</th>

<th>
Deadline
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


if(mysqli_num_rows($tasks_query)>0){


while($task=mysqli_fetch_assoc($tasks_query)){


?>


<tr>


<td>


<strong>

<?php

echo htmlspecialchars(
    $task['task_title']
);

?>

</strong>


<br>


<small>

<?php

$description =
    $task['task_description'];

if(strlen($description)>80){

    $description =
        substr($description,0,80)
        . "...";

}

echo htmlspecialchars(
    $description
);

?>

</small>


</td>


<td>

<?php

echo htmlspecialchars(
    $task['priority']
);

?>

</td>


<td>

<?php

echo date(

    "d M Y",

    strtotime(
        $task['assigned_date']
    )

);

?>

</td>


<td>

<?php

echo date(

    "d M Y",

    strtotime(
        $task['deadline']
    )

);

?>

</td>


<td>


<?php


$status_class = "task-assigned";


if($task['status']=="In Progress"){

    $status_class = "task-progress";

}


if($task['status']=="Completed"){

    $status_class = "task-completed";

}


?>


<span class="task-status <?php echo $status_class; ?>">


<?php

echo htmlspecialchars(
    $task['status']
);

?>


</span>


</td>


<td>


<a

href="assign_task.php?staff_id=<?php echo urlencode($staff['staff_id']); ?>"

class="btn"

>

Assign Task

</a>


</td>


</tr>


<?php


}


}else{


?>


<tr>


<td

colspan="6"

style="text-align:center;padding:30px;"

>


No tasks have been assigned to this staff member yet.


<br><br>


<a

href="assign_task.php?staff_id=<?php echo urlencode($staff['staff_id']); ?>"

class="btn"

>

Assign Task

</a>


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

    document.getElementById("clock").innerHTML =

        new Date().toLocaleTimeString();

}


updateClock();

setInterval(updateClock,1000);


</script>


</body>

</html>