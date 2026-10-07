<?php
session_start();
include "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();

}

if ($_SESSION['admin_role'] != "Super Admin") {

    die("Access Denied");

}

$admin_id = $_SESSION['admin_id'];
$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

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
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Reports</title>
<link rel="stylesheet" href="styles.css">

<style>

.report-grid {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 20px;
    margin-top: 25px;
}

.report-card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    text-decoration: none;
    color: #333;
    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}

.report-card:hover {
    transform: translateY(-4px);
    box-shadow:
        0 5px 15px
        rgba(0,0,0,0.12);
}

.report-icon {
    font-size: 35px;
    margin-bottom: 10px;
}

.report-card h3 {
    margin: 8px 0;
    color: #0056b3;
}

.report-card p {
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

@media(max-width:1000px) {

    .report-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media(max-width:650px) {

    .report-grid {
        grid-template-columns: 1fr;
    }
}
</style>

</head>

<body>

<div class="container">

<div class="sidebar">

<div class="logo">

<h2>
<?php
echo htmlspecialchars(
    $app['organization_name']
);
?>
</h2>

<p>Personnel Timesheet System</p>

</div>

<div class="profile">

<div class="avatar">
<img src="<?php echo htmlspecialchars($admin_profile_photo); ?>" class="profile-small" alt="Admin Profile Photo" onerror="this.src='uploads/admins/default.png';">
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
<li><a href="admin_dashboard.php">Dashboard</a></li>
<li><a href="manage_admins.php">Manage Admins</a></li>
<li><a href="manage_supervisors.php">Manage Supervisors</a></li>
<li><a href="manage_staff.php">Manage Staff</a></li>
<li class="active"><a href="admin_reports.php">Reports</a></li>
<li><a href="admin_logout.php">Logout</a></li>
</ul>

</div>

<div class="main">

<div class="topbar">

<div>
<h1>Admin Reports</h1>
<p>Generate and view organization-wide reports.</p>
</div>

<div id="clock"></div>

</div>

<div class="report-grid">

<a href="admin_staff_hours_report.php" class="report-card">
<div class="report-icon">📊
</div>

<h3>Staff Hours Report</h3>

<p>View daily, weekly or monthly hours worked by staff members.</p>

</a>

<a href="admin_attendance_report.php" class="report-card">
<div class="report-icon">✅

</div>

<h3>Staff Attendance Report</h3>

<p>View staff attendance, clock-in, clock-out and punctuality records.</p>

</a>

<a href="admin_monthly_report.php" class="report-card">
<div class="report-icon">📅
</div>

<h3>Monthly Staff Report</h3>

<p>Generate a complete monthly attendance and working-hours report.</p>

</a>

<a href="admin_department_report.php" class="report-card">
<div class="report-icon">🏢
</div>
<h3>Department Report</h3>
<p>View staff, attendance and working hours by department.</p>
</a>

<a href="admin_overtime_report.php" class="report-card">
<div class="report-icon">⏱️
</div>
<h3>Overtime Report</h3>
<p>Identify staff members who worked beyond their normal working hours.</p>
</a>

<a href="admin_task_report.php" class="report-card">
<div class="report-icon">📝
</div>
<h3>Staff Task Report</h3>
<p>View tasks performed by staff, including dates and hours spent.</p>
</a>

<a href="admin_supervisor_report.php" class="report-card">
<div class="report-icon">👨‍💼
</div>
<h3>Supervisor Report</h3>
<p>View supervisors, assigned staff and approval statistics.</p>
</a>

<a href="admin_activity_report.php" class="report-card">
<div class="report-icon">🛡️
</div>
<h3>System Activity Report</h3>
<p>Monitor important activities performed by administrators and users.</p>
</a>

<a href="admin_login_report.php" class="report-card">
<div class="report-icon">🔐
</div>
<h3>Login Activity Report</h3>
<p>Monitor administrator, supervisor and staff login activity.</p>
</a>

</div>

</div>

</div>

<script>
function updateClock() {
    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString(
            'en-GB',
            {hour12:false}
        );
}
updateClock();
setInterval(
    updateClock,
    1000
);
</script>

</body>
</html>