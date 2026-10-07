<?php
session_start();
include "config.php";
include "notification_function.php";

if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$fullname = $_SESSION['fullname'];


/* ==========================================
   FETCH STAFF PROFILE PHOTO
========================================== */

$stmt = $conn->prepare("
    SELECT fullname, profile_picture
    FROM staff
    WHERE staff_id = ?
");

$stmt->bind_param("s", $staff_id);
$stmt->execute();

$staff_result = $stmt->get_result();
$staff_data = $staff_result->fetch_assoc();

$stmt->close();


/* Get actual staff name from database */

$fullname = $staff_data['fullname'] ?? $fullname;


/* ==========================================
   PROFILE PHOTO
========================================== */

$profile_photo = "uploads/staff/default.png";


if (!empty($staff_data['profile_picture'])) {

    $photo_name = basename($staff_data['profile_picture']);


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

/* Get Staff Supervisor */
$getStaff = mysqli_query($conn,"
SELECT supervisor_id
FROM staff
WHERE staff_id='$staff_id'
");

$staff = mysqli_fetch_assoc($getStaff);
$supervisor_id = $staff['supervisor_id'];

$message = "";
$message_class = "";

if(isset($_POST['submit'])){

    $report_date = mysqli_real_escape_string($conn,$_POST['report_date']);
    $start_time = mysqli_real_escape_string($conn,$_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn,$_POST['end_time']);
    $hours_worked = mysqli_real_escape_string($conn,$_POST['hours_worked']);
    $tasks_completed = mysqli_real_escape_string($conn,$_POST['tasks_completed']);
    $challenges = mysqli_real_escape_string($conn,$_POST['challenges']);
    $remarks = mysqli_real_escape_string($conn,$_POST['remarks']);

    $check = mysqli_query($conn,"
    SELECT id
    FROM task_reports
    WHERE staff_id='$staff_id'
    AND report_date='$report_date'
    ");

    if(mysqli_num_rows($check)>0){

        $message="You have already submitted a task report for this date.";
        $message_class="error";

    }else{

        $insert=mysqli_query($conn,"
        INSERT INTO task_reports(
        staff_id,
        supervisor_id,
        report_date,
        start_time,
        end_time,
        hours_worked,
        tasks_completed,
        challenges,
        remarks
        )

        VALUES(

        '$staff_id',
        '$supervisor_id',
        '$report_date',
        '$start_time',
        '$end_time',
        '$hours_worked',
        '$tasks_completed',
        '$challenges',
        '$remarks'

        )
        ");

        if($insert){

    notifySupervisor(
        $conn,
        $supervisor_id,
        "New task report submitted by $fullname for $report_date. Please review it."
    );

    $message = "Task report submitted successfully.";
    $message_class = "success";

}else{

    $message = "Failed to submit task report.";
    $message_class = "error";

}
        
    }

}
?>

<!DOCTYPE html>
<html>

<head>

<title>Submit Task Report</title>

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

    <img
        src="<?php echo htmlspecialchars($profile_photo); ?>"
        class="profile-small"
        alt="Profile Photo"
        onerror="this.onerror=null; this.src='uploads/staff/default.png';"
    >

</div>

    <h4>
        <?php echo $_SESSION['fullname']; ?>
    </h4>

    <p>Staff</p>

</div>

<ul>

<li>
<a href="staff_dashboard.php">Dashboard</a>
</li>

<li>
<a href="attendance.php">Clock In/Out</a>
</li>


<li class="active">
<a href="staff_submit_task_report.php">My Task</a>
</li>

<li><a href="staff_reports.php">Task Reports</a></li>

<li><a href="submit_timesheet.php">Submit Timesheet</a></li>

<li>
                <a href="task_history.php"> Task Submission History </a>
            </li>

<li>
<a href="staff_profile.php">Profile</a>
</li>

<li>
<a href="staff_logout.php">Logout</a>
</li>

</ul>

</div>

<div class="main">

<div class="topbar">

<h1>Submit Task Report</h1>

<div id="clock"></div>

</div>

<div class="timesheet-card">

<?php

if($message!=""){

echo "<div class='$message_class'>$message</div>";

}

?>

<form method="POST">

<div class="form-grid">

    <div class="form-group">
        <label>Report Date</label>
        <input
            type="date"
            name="report_date"
            max="<?php echo date('Y-m-d'); ?>"
            required>
    </div>

    <div class="form-group">
        <label>Start Time</label>
        <input
            type="time"
            id="start_time"
            name="start_time"
            required>
    </div>

    <div class="form-group">
        <label>End Time</label>
        <input
            type="time"
            id="end_time"
            name="end_time"
            required>
    </div>

    <div class="form-group">
        <label>Hours Worked</label>
        <input
            type="number"
            step="0.01"
            id="hours_worked"
            name="hours_worked"
            readonly
            required>
    </div>

</div>

<div class="form-group">

<label>Task(s) Completed</label>

<textarea
name="tasks_completed"
placeholder="Describe all tasks completed..."
required></textarea>

</div>

<div class="form-group">

<label>Challenges Encountered (Optional)</label>

<textarea
name="challenges"
placeholder="Enter any challenges encountered..."></textarea>

</div>

<div class="form-group">

<label>Remarks (Optional)</label>

<textarea
name="remarks"
placeholder="Additional remarks..."></textarea>

</div>

<div class="button-group">

<button
type="submit"
name="submit"
class="save-btn">

Submit Task Report

</button>

<a
href="staff_dashboard.php"
class="back-btn">

Back to Dashboard

</a>

</div>

</form>

</div>

</div>

</div>

<script>

// ==========================
// AUTOMATIC HOURS CALCULATION
// ==========================

function calculateHours(){

    let start = document.getElementById("start_time").value;
    let end = document.getElementById("end_time").value;

    if(start !== "" && end !== ""){

        let startArray = start.split(":");
        let endArray = end.split(":");

        let startDate = new Date();
        startDate.setHours(startArray[0], startArray[1], 0);

        let endDate = new Date();
        endDate.setHours(endArray[0], endArray[1], 0);

        let diff = (endDate - startDate) / (1000 * 60 * 60);

        // Handles overnight work
        if(diff < 0){
            diff += 24;
        }

        document.getElementById("hours_worked").value =
            diff.toFixed(2);

    }

}

document.getElementById("start_time")
.addEventListener("change", calculateHours);

document.getElementById("end_time")
.addEventListener("change", calculateHours);


// ==========================
// LIVE CLOCK
// ==========================

function updateClock(){

    let now = new Date();

    document.getElementById("clock").innerHTML =
        now.toLocaleTimeString();

}

setInterval(updateClock,1000);

updateClock();

</script>

</body>

</html>


