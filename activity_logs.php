<?php

session_start();

include "config.php";
include "notification_function.php";

if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}

/* ============================== ADMIN SESSION INFORMATION ============================== */

$admin_id = $_SESSION['admin_id'];

$fullname = $_SESSION['fullname'] ?? 'Administrator';

/* ============================== ADMIN PROFILE PHOTO ============================== */

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

/* ============================== ADMIN PROFILE PHOTO PATH ============================== */

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

/* ========================== Dashboard Statistics ========================== */

$total_logs = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM activity_logs
"))['total'];

$staff_logs = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM activity_logs
WHERE user_type='Staff'
"))['total'];

$supervisor_logs = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM activity_logs
WHERE user_type='Supervisor'
"))['total'];

$admin_logs = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM activity_logs
WHERE user_type='Admin'
"))['total'];

/* ========================== Filters ========================== */

$user_type = "";
$activity = "";
$date = "";

$where = "WHERE 1=1";

if(isset($_GET['user_type']) && $_GET['user_type']!=""){
    $user_type = $_GET['user_type'];
    $where .= " AND user_type='$user_type'";
}

if(isset($_GET['activity']) && $_GET['activity']!=""){
    $activity = $_GET['activity'];
    $where .= " AND activity_type='$activity'";
}

if(isset($_GET['date']) && $_GET['date']!=""){
    $date = $_GET['date'];
    $where .= " AND DATE(activity_time)='$date'";
}

$sql = "
SELECT *
FROM activity_logs
$where
ORDER BY activity_time DESC
";

$logs = mysqli_query($conn,$sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Activity Logs</title>
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
                <img src="<?php echo htmlspecialchars($admin_profile_photo); ?>" class="profile-small" alt="Admin Profile Photo" onerror="this.src='uploads/admins/default.png';">
            </div>

            <h3><?php echo $fullname; ?></h3>
            <p>Administrator</p>
        </div>

        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li class="active"><a href="activity_logs.php">Activity Logs</a></li>
            <li><a href="manage_staff.php">Staff</a></li>
            <li><a href="manage_supervisors.php">Supervisors</a></li>
            <li><a href="admin_reports.php">Reports</a></li>
            <li><a href="admin_logout.php">Logout</a></li>
        </ul>

    </div>

    <!-- Main -->
    <div class="main">

        <div class="topbar">
            <div>
                <h1>Activity Logs</h1>
                <p>Monitor all activities performed in the system</p>
            </div>

            <div id="clock"></div>
        </div>

        <!-- Statistics -->
        <div class="cards">

            <div class="card">
                <h4>Total Logs</h4>
                <h1><?php echo $total_logs; ?></h1>
            </div>

            <div class="card">
                <h4>Staff</h4>
                <h1><?php echo $staff_logs; ?></h1>
            </div>

            <div class="card">
                <h4>Supervisors</h4>
                <h1><?php echo $supervisor_logs; ?></h1>
            </div>

            <div class="card">
                <h4>Admins</h4>
                <h1><?php echo $admin_logs; ?></h1>
            </div>

        </div>

        <!-- Table -->
        <div class="history-box">

            <form method="GET" class="filter-form">
                <select name="user_type">
                    <option value="">All Users</option>
                    <option value="Admin" <?php if($user_type=="Admin") echo "selected"; ?>> Admin </option>
                    <option value="Supervisor" <?php if($user_type=="Supervisor") echo "selected"; ?>> Supervisor </option>
                    <option value="Staff" <?php if($user_type=="Staff") echo "selected"; ?>> Staff </option>
                </select>
                <input type="text" name="activity" value="<?php echo $activity; ?>" placeholder="Activity Type">
                <input type="date" name="date" value="<?php echo $date; ?>">
                <button type="submit"> Filter </button>
            </form>

            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>User ID</th>
                        <th>User Type</th>
                        <th>Activity</th>
                        <th>Description</th>
                    </tr>
                </thead>

                <tbody>

                <?php
                if(mysqli_num_rows($logs)>0){
                    while($row=mysqli_fetch_assoc($logs)){
                ?>

                    <tr>
                        <td> <?php echo date("d M Y h:i A",strtotime($row['activity_time'])); ?> </td>
                        <td> <?php echo $row['user_id']; ?></td>
                        <td><?php echo $row['user_type']; ?></td>
                        <td><span class="status"><?php echo $row['activity_type']; ?> </span> </td>
                        <td> <?php echo $row['activity_description']; ?></td>
                    </tr>

                <?php
                    }
                }else{
                ?>

                    <tr>No activity logs found.</td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

            <div class="button-group">
                <a href="activity_logs_pdf.php" class="pdf-btn"> 📄 Export PDF </a>
                <a href="activity_logs_excel.php" class="excel-btn">📊 Export Excel</a>
                <a href="activity_logs_print.php" class="print-btn">🖨 Print</a>
            </div>

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