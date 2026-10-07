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


/* =========================================================
   FETCH STAFF INFORMATION AND PROFILE PHOTO
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


/* =========================================================
   STAFF NAME
========================================================= */

$fullname = $staff['fullname'];


/* =========================================================
   PROFILE PHOTO
========================================================= */

/*
 * Default photo
 */

$profile_photo = "uploads/staff/default.png";


/*
 * Check staff profile picture
 */

if (!empty($staff['profile_picture'])) {

    /*
     * Remove any directory information.
     * This prevents paths such as:
     * uploads/photo.jpg
     * from being duplicated.
     */

    $photo_name = basename($staff['profile_picture']);


    /*
     * NEW LOCATION
     */

    if (file_exists("uploads/staff/" . $photo_name)) {

        $profile_photo =
            "uploads/staff/" . $photo_name;

    }


    /*
     * OLD LOCATION
     */

    elseif (file_exists("uploads/" . $photo_name)) {

        $profile_photo =
            "uploads/" . $photo_name;

    }

}

/* Total Timesheets */
$query = mysqli_query($conn,"
SELECT COUNT(*) total
FROM timesheets
WHERE staff_id='$staff_id'
");
$total_timesheets = mysqli_fetch_assoc($query)['total'];

/* Pending Timesheets */
$query = mysqli_query($conn,"
SELECT COUNT(*) total
FROM timesheets
WHERE staff_id='$staff_id'
AND status='pending'
");
$pending = mysqli_fetch_assoc($query)['total'];

/* Approved Timesheets */
$query = mysqli_query($conn,"
SELECT COUNT(*) total
FROM timesheets
WHERE staff_id='$staff_id'
AND status='approved'
");
$approved = mysqli_fetch_assoc($query)['total'];

/* Days Present This Week */
$query = mysqli_query($conn,"
SELECT COUNT(*) total
FROM attendance
WHERE staff_id='$staff_id'
AND status='Present'
AND WEEK(date)=WEEK(CURDATE())
");
$days_present = mysqli_fetch_assoc($query)['total'];

/* Hours This Week - Calculated From Attendance */

$query = mysqli_query($conn,"
SELECT IFNULL(
    SUM(
        TIME_TO_SEC(
            TIMEDIFF(
                COALESCE(time_out, clock_out),
                COALESCE(time_in, clock_in)
            )
        )
    ) / 3600,
    0
) AS total
FROM attendance
WHERE staff_id='$staff_id'
AND YEAR(date)=YEAR(CURDATE())
AND WEEK(date,1)=WEEK(CURDATE(),1)
AND COALESCE(time_in, clock_in) IS NOT NULL
AND COALESCE(time_out, clock_out) IS NOT NULL
");

$hours_week = mysqli_fetch_assoc($query)['total'];
/* Today's Attendance */
$todayAttendance = mysqli_query($conn,"
SELECT *
FROM attendance
WHERE staff_id='$staff_id'
AND date=CURDATE()
");
$todayRecord = mysqli_fetch_assoc($todayAttendance);

/* Weekly Hours Breakdown - From Attendance */

$weeklyHours = [];

$result = mysqli_query($conn,"
SELECT
    DAYNAME(date) AS day_name,
    SUM(
        TIME_TO_SEC(
            TIMEDIFF(
                COALESCE(time_out, clock_out),
                COALESCE(time_in, clock_in)
            )
        )
    ) / 3600 AS hours
FROM attendance
WHERE staff_id='$staff_id'
AND YEAR(date)=YEAR(CURDATE())
AND WEEK(date,1)=WEEK(CURDATE(),1)
AND COALESCE(time_in, clock_in) IS NOT NULL
AND COALESCE(time_out, clock_out) IS NOT NULL
GROUP BY date, DAYNAME(date)
");

while($r = mysqli_fetch_assoc($result)){
    $weeklyHours[$r['day_name']] = $r['hours'];
}

/* Recent Submitted Tasks */
/* Recent Submitted Tasks */
$recent_tasks = mysqli_query($conn,"
SELECT
    id,
    staff_id,
    report_date,
    start_time,
    end_time,
    hours_worked,
    tasks_completed,
    status,
    created_at
FROM task_reports
WHERE staff_id='$staff_id'
ORDER BY created_at DESC
LIMIT 3
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Dashboard</title>
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
            src="<?php echo htmlspecialchars($profile_photo); ?>"
            class="profile-small"
            alt="Profile Photo"
            onerror="this.src='uploads/staff/default.png';"
        >

    </div>

    <h3>
        <?php echo htmlspecialchars($fullname); ?>
    </h3>

    <p>Staff</p>

</div>

        <ul>

            <li><a href="staff_dashboard.php">Dashboard</a></li>
           <li><a href="attendance.php">Clock In/Out</a></li>
             <li>
                <a href="staff_submit_task_report.php"> My Task </a>
            </li>
            <li>
                <a href="task_history.php"> Task Submission History </a>
            </li>
            
            <li><a href="staff_notifications.php">Notifications</a></li>

            <li><a href="work_schedule.php">My Schedule</a></li>

            <li><a href="staff_profile.php">Profile</a></li>
            
            <li>
                <a href="staff_logout.php"> Logout </a>
            </li>

        </ul>

    </div>

    <!-- Main Content -->
    <div class="main">

        <div class="topbar">

            <div>

                <h1>Staff Dashboard</h1>

                <p>Welcome back, <?php echo $fullname; ?></p>

            </div>

            <div id="clock"></div>

        </div>

        <div class="cards">

    <div class="card">
        <h4>HOURS THIS WEEK</h4>
        <h1><?php echo number_format($hours_week,1); ?>h</h1>
        <p>Hours worked this week</p>
    </div>

    <div class="card">
        <h4>DAYS PRESENT</h4>
        <h1><?php echo $days_present; ?></h1>
        <p>This week</p>
    </div>

    <div class="card">
        <h4>PENDING</h4>
        <h1><?php echo $pending; ?></h1>
        <p>Awaiting approval</p>
    </div>

    <div class="card">
        <h4>APPROVED</h4>
        <h1><?php echo $approved; ?></h1>
        <p>Approved submissions</p>
    </div>

    <div class="card">
        <h4>TODAY</h4>
        <h1>
            <?php echo $todayRecord ? "Present" : "Absent"; ?>
        </h1>
        <p>Attendance Status</p>
    </div>

</div>

       <!-- Bottom Section -->
<div class="bottom">

    <!-- Weekly Hours -->
    <div class="box">

        <h3>This Week at a Glance</h3>

        <?php

        $days = [
            "Monday",
            "Tuesday",
            "Wednesday",
            "Thursday",
            "Friday",
            "Saturday",
            "Sunday"
        ];

        foreach($days as $day){

            $hours = $weeklyHours[$day] ?? 0;

            $percentage = min(($hours / 8) * 100, 100);

        ?>

        <div class="progress-row">

            <span>
                <?php echo substr($day,0,3); ?>
            </span>

            <div class="progress">

                <div class="progress-bar" style="width:<?php echo $percentage; ?>%"></div>

            </div>

            <span><?php echo number_format($hours,1); ?>h </span>

        </div>

        <?php } ?>

    </div>

    <!-- Recent Submitted Tasks -->
<div class="box">

    <h3>Recent Submitted Tasks</h3>

    <?php

    if ($recent_tasks && mysqli_num_rows($recent_tasks) > 0) {

        while ($task = mysqli_fetch_assoc($recent_tasks)) {

    ?>

    <div class="timesheet">

        <div>

            <strong>
                <?php
                echo htmlspecialchars(
                    $task['tasks_completed']
                );
                ?>
            </strong>

            <p>
                <?php
                echo date(
                    "d M Y",
                    strtotime($task['report_date'])
                );
                ?>

                •

                <?php
                echo number_format(
                    (float)$task['hours_worked'],
                    1
                );
                ?>h
            </p>

        </div>

        <div class="timesheet-actions">

            <span class="status <?php
                echo strtolower(
                    $task['status'] ?? 'pending'
                );
            ?>">

                <?php
                echo ucfirst(
                    $task['status'] ?? 'Pending'
                );
                ?>

            </span>

            <a
    href="task_details.php?id=<?php echo urlencode($task['id']); ?>"
    class="view-btn"
>
    View
</a>

        </div>

    </div>

    <?php

        }

    } else {

        echo "<p>No tasks submitted yet.</p>";

    }

    ?>

</div>

</div>

</div>
</div>
<script>

function updateClock() {

    let now = new Date();

    document.getElementById("clock")
        .innerHTML =
        now.toLocaleTimeString();
}

setInterval(updateClock,1000);

updateClock();

</script>

</body>
</html>