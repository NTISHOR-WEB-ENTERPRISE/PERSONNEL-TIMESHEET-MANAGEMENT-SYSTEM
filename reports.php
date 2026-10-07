<?php

session_start();

include "config.php";


/* ==================================
   CHECK SUPERVISOR LOGIN
================================== */

if (!isset($_SESSION['supervisor_id'])) {

    header("Location: supervisor_login.php");
    exit();

}


$supervisor_id = $_SESSION['supervisor_id'];

$fullname = $_SESSION['fullname'] ?? 'Supervisor';


/* ==================================
   GET SUPERVISOR PROFILE PHOTO
================================== */

$stmt = $conn->prepare("
    SELECT
        fullname,
        profile_photo
    FROM supervisors
    WHERE supervisor_id = ?
    LIMIT 1
");


if (!$stmt) {

    die("Database error: " . $conn->error);

}


$stmt->bind_param(
    "s",
    $supervisor_id
);


$stmt->execute();


$result = $stmt->get_result();


$supervisor_data = $result->fetch_assoc();


$stmt->close();


/* ==================================
   USE DATABASE NAME
================================== */

if ($supervisor_data) {

    $fullname =
        $supervisor_data['fullname'];

}


/* ==================================
   DEFAULT PROFILE PHOTO
================================== */

$profile_photo =
    "uploads/supervisors/default.png";


/* ==================================
   CHECK ACTUAL PROFILE PHOTO
================================== */

if (
    $supervisor_data &&
    !empty($supervisor_data['profile_photo'])
) {

    $photo =
        basename(
            $supervisor_data['profile_photo']
        );


    $photo_path =
        "uploads/supervisors/" . $photo;


    if (
        file_exists($photo_path)
    ) {

        $profile_photo =
            $photo_path;

    }

}

?>

<!DOCTYPE html>
<html>
<head>
<title>Reports</title>
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
        alt="Profile Photo"
        onerror="this.src='uploads/supervisors/default.png';"
    >

</div>

            <h4><?php echo $fullname; ?></h4>
            <p>Supervisor</p>
        </div>

        <ul>
            <li>
                <a href="supervisor_dashboard.php">Dashboard</a>
            </li>

            <li>
                <a href="approvals.php">Approvals</a>
            </li>

            <li class="active">
                <a href="reports.php">Reports</a>
            </li>

            <li>
                <a href="supervisor_logout.php">Logout</a>
            </li>
        </ul>

    </div>

    <!-- Main -->

    <div class="main">

        <div class="page-header">

            <div>
                <h2>Reports</h2>
            </div>

            <div id="clock"></div>

        </div>

        <div class="report-grid">

            <a href="hours_summary.php" class="report-card">
    <div class="report-icon">📊</div>
    <h3>Hours Summary</h3>
    <p>Total hours by employee for any date range</p>
</a>

           <a href="staff_monthly_report.php" class="report-card">
    <div class="report-icon">📅</div>
    <h3>Staff Monthly Report</h3>
    <p>View daily attendance, hours worked, overtime, and monthly summary</p>
</a>

            <a href="staff_overtime_report.php" class="report-card">
                <div class="report-icon">⏱️</div>
                <h3>Overtime Report</h3>
                <p>Identify employees with overtime hours</p>
            </a>

            <a href="staff_monthly_attendance_report.php" class="report-card">
                <div class="report-icon">🏢</div>
                <h3>Staff Monthly Attendance Report</h3>
                <p>Calculation of Hours worked for the month</p>
            </a>

            <a href="staff_task_report.php" class="report-card">
                <div class="report-icon">📝</div>
                <h3>Staff Task Report</h3>
                <p>View all tasks performed by a selected employee within a selected date range.</p>
            </a>

           <a href="supervisor_timesheet_report.php" class="report-card">
    <div class="report-icon">📄</div>
    <h3>Staff Timesheet Report</h3>
    <p>View employee's weekly timesheet, tasks performed, and total hours worked.</p>
</a>
            </div>
       
</div>


<script>
function updateClock(){
    let now = new Date();

    document.getElementById('clock').innerHTML =
    '● ' +
    now.toLocaleTimeString('en-GB',{hour12:false});
}

updateClock();
setInterval(updateClock,1000);
</script>

</body>
</html>