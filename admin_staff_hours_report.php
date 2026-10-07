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

$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

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

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$selected_staff = $_GET['staff_id'] ?? '';
$selected_department = $_GET['department'] ?? '';
$generate = isset($_GET['generate']);

$staff_list = mysqli_query($conn, "

    SELECT
        staff_id,
        fullname,
        department

    FROM staff

    ORDER BY fullname ASC

");

$department_list = mysqli_query($conn, "

    SELECT DISTINCT
        department

    FROM staff
    WHERE department IS NOT NULL
    AND department != ''
    ORDER BY department ASC
");

$report = [];
$total_hours = 0;
$total_overtime = 0;
$total_records = 0;

if ($generate) {
    $conditions = [];

    $date_from_safe = mysqli_real_escape_string(
        $conn,
        $date_from
    );

    $date_to_safe = mysqli_real_escape_string(
        $conn,
        $date_to
    );

    $conditions[] = "
        attendance.date BETWEEN
        '$date_from_safe' AND '$date_to_safe'
    ";

    if ($selected_staff != '') {

        $staff_safe = mysqli_real_escape_string(
            $conn,
            $selected_staff
        );

        $conditions[] = "
            attendance.staff_id = '$staff_safe'
        ";
    }

    if ($selected_department != '') {

        $department_safe = mysqli_real_escape_string(
            $conn,
            $selected_department
        );

        $conditions[] = "
            staff.department = '$department_safe'
        ";
    }

    $where_clause = implode(
        " AND ",
        $conditions
    );

    $report_query = mysqli_query(
        $conn,
        "
        SELECT

            attendance.id,
            attendance.staff_id,
            staff.fullname,
            staff.department,
            staff.supervisor_id,
            supervisors.fullname AS supervisor_name,
            attendance.date AS work_date,
            attendance.time_in,
            attendance.time_out,
            attendance.clock_in,
            attendance.clock_out,
            attendance.status

        FROM attendance

        INNER JOIN staff
            ON attendance.staff_id =
               staff.staff_id

        LEFT JOIN supervisors
            ON staff.supervisor_id =
               supervisors.supervisor_id

        WHERE $where_clause

        ORDER BY
            attendance.date DESC,
            staff.fullname ASC
        "
    );

    if (!$report_query) {

        die(
            "Report query failed: " .
            mysqli_error($conn)
        );
    }

    while (
        $row = mysqli_fetch_assoc(
            $report_query
        )
    ) {

        $clock_in = !empty($row['clock_in'])
            ? $row['clock_in']
            : $row['time_in'];

        $clock_out = !empty($row['clock_out'])
            ? $row['clock_out']
            : $row['time_out'];


        $calculated_hours = 0;

        $calculated_overtime = 0;


        if (
            !empty($clock_in) &&
            !empty($clock_out)
        ) {

            $clock_in_timestamp = strtotime(
                $clock_in
            );

            $clock_out_timestamp = strtotime(
                $clock_out
            );


            if (
                $clock_in_timestamp !== false &&
                $clock_out_timestamp !== false
            ) {

                if (
                    $clock_out_timestamp <
                    $clock_in_timestamp
                ) {

                    $clock_out_timestamp += 86400;
                }


                $elapsed_hours =
                    (
                        $clock_out_timestamp -
                        $clock_in_timestamp
                    ) / 3600;

                $day_of_week = (int) date(
                    'w',
                    strtotime(
                        $row['work_date']
                    )
                );

                if (
                    $day_of_week == 0 ||
                    $day_of_week == 6
                ) {


                    $calculated_hours =
                        $elapsed_hours;

                    $calculated_overtime =
                        $elapsed_hours;

                }

                else {

                    if ($elapsed_hours > 8) {

                        $calculated_hours =
                            $elapsed_hours - 1;

                    } else {

                        $calculated_hours =
                            $elapsed_hours;

                    }

                    if (
                        $calculated_hours > 8
                    ) {

                        $calculated_overtime =
                            $calculated_hours - 8;

                    } else {

                        $calculated_overtime = 0;
                    }
                }
            }
        }

        if ($calculated_hours < 0) {
            $calculated_hours = 0;
        }

        if ($calculated_overtime < 0) {
            $calculated_overtime = 0;
        }

        $row['clock_in'] =
            $clock_in;

        $row['clock_out'] =
            $clock_out;

        $row['calculated_hours'] =
            round(
                $calculated_hours,
                2
            );

        $row['calculated_overtime'] =
            round(
                $calculated_overtime,
                2
            );

        $report[] = $row;

        $total_hours +=
            $row['calculated_hours'];

        $total_overtime +=
            $row['calculated_overtime'];

    }

    $total_records =
        count($report);

}

$expected_hours =
    $total_records * 8;

$hours_difference =
    $total_hours - $expected_hours;
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Staff Hours Report</title>
<linkn rel="stylesheet" href="styles.css">

<style>

.report-header {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);
}

.report-header h2 {
    margin-top: 0;
}

.filter-grid {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 15px;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-weight: bold;
    margin-bottom: 6px;
}

.filter-group input,
.filter-group select {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.report-buttons {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.generate-btn {
    background: #0056b3;
    color: white;
    border: none;
    padding: 11px 18px;
    border-radius: 6px;
    cursor: pointer;
}

.print-btn {
    background: #198754;
    color: white;
    border: none;
    padding: 11px 18px;
    border-radius: 6px;
    cursor: pointer;
}

.reset-btn {
    background: #6c757d;
    color: white;
    padding: 11px 18px;
    border-radius: 6px;
    text-decoration: none;
}

.summary-grid {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);
}

.summary-card h4 {
    margin: 0 0 8px;
    color: #666;
}

.summary-card h2 {
    margin: 0;
    color: #0056b3;
}

.report-table-box {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);
    overflow-x: auto;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th,
.report-table td {
    padding: 10px;
    border-bottom:
        1px solid #eee;
    text-align: left;
    white-space: nowrap;
}

.report-table th {
    background: #0056b3;
    color: white;
}

.status {
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.status.pending {
    background: #fff3cd;
    color: #856404;
}

.status.approved {
    background: #d1e7dd;
    color: #0f5132;
}

.status.rejected {
    background: #f8d7da;
    color: #842029;
}

.late {
    color: #dc3545;
    font-weight: bold;
}

.early {
    color: #dc3545;
    font-weight: bold;
}

@media print {

    .sidebar,
    .topbar,
    .filter-section,
    .report-buttons {
        display: none !important;
    }

    .main {
        margin: 0;
        width: 100%;
    }

    .report-header,
    .summary-card,
    .report-table-box {
        box-shadow: none;
    }
}

@media(max-width:1000px) {

    .filter-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .summary-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media(max-width:600px) {

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #333;
}

.container {
    display: flex;
    min-height: 100vh;
    width: 100%;
}

.sidebar {
    width: 250px;
    min-width: 250px;
    background: ash;
    color: white;
    min-height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(0,0,0,0.12);
}

.sidebar .logo {
    padding: 22px 18px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.15);
}

.sidebar .logo h2 {
    margin: 0;
    font-size: 20px;
    line-height: 1.3;
    color: white;
}

.sidebar .logo p {
    margin: 7px 0 0;
    font-size: 12px;
    color: rgba(255,255,255,0.8);
}

.sidebar .profile {
    text-align: center;
    padding: 20px 15px;
    border-bottom: 1px solid rgba(255,255,255,0.15);
}

.sidebar .avatar {
    width: 75px;
    height: 75px;
    margin: 0 auto 10px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid rgba(255,255,255,0.8);
    background: #fff;
}

.sidebar .profile-small {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.sidebar .profile h3 {
    margin: 8px 0 4px;
    font-size: 16px;
    color: white;
}

.sidebar .profile p {
    margin: 0;
    font-size: 13px;
    color: rgba(255,255,255,0.75);
}

.sidebar ul {
    list-style: none;
    padding: 15px 10px;
    margin: 0;
}

.sidebar ul li {
    margin-bottom: 5px;
}

.sidebar ul li a {
    display: block;
    padding: 13px 15px;
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    border-radius: 7px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.sidebar ul li a:hover {
    background: rgba(255,255,255,0.12);
    color: white;
}

.sidebar ul li.active a {
    background: white;
    color: #0b3d91;
    font-weight: bold;
}

.main {
    margin-left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;
    padding: 0;
}

.topbar {
    min-height: 75px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 25px;
    border-bottom: 1px solid #e5e5e5;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.topbar h1 {
    margin: 0;
    font-size: 24px;
    color: #222;
}

.topbar p {
    margin: 5px 0 0;
    color: #777;
    font-size: 13px;
}

#clock {
    font-size: 16px;
    font-weight: bold;
    color: #0b3d91;
    background: #f1f5fb;
    padding: 9px 14px;
    border-radius: 6px;
}

.main > .report-header,
.main > .summary-grid,
.main > .report-table-box {
    margin-left: 25px;
    margin-right: 25px;
}

.main > .report-header {
    margin-top: 25px;
}

.main > .summary-grid {
    margin-top: 20px;
}

.main > .report-table-box {
    margin-top: 20px;
}

@media (max-width: 800px) {

    .sidebar {
        width: 210px;
        min-width: 210px;
    }

    .main {
        margin-left: 210px;
        width: calc(100% - 210px);
    }

    .topbar {
        padding: 15px;
    }

    .topbar h1 {
        font-size: 20px;
    }

    .main > .report-header,
    .main > .summary-grid,
    .main > .report-table-box {
        margin-left: 15px;
        margin-right: 15px;
    }

}

@media (max-width: 600px) {

    .sidebar {
        width: 190px;
        min-width: 190px;
    }

    .main {
        margin-left: 190px;
        width: calc(100% - 190px);
    }

    .sidebar .logo h2 {
        font-size: 16px;
    }

    .sidebar .profile h3 {
        font-size: 14px;
    }

    .sidebar ul li a {
        padding: 11px 10px;
        font-size: 13px;
    }

    .topbar {
        display: block;
    }

    #clock {
        display: inline-block;
        margin-top: 10px;
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
<img src="<?php echo htmlspecialchars($admin_profile_photo); ?>" class="profile-small" alt="Profile" onerror="this.onerror=null; this.src='uploads/admins/default.png';">
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
<h1>Staff Hours Report</h1>
<p>Organization-wide staff working hours</p>
</div>

<div id="clock"></div>

</div>

<div class="report-header filter-section">

<h2>Generate Staff Hours Report</h2>

<form method="GET">

<div class="filter-grid">

<div class="filter-group">
<label>Date From</label>
<input type="date" name="date_from" value="<?php
echo htmlspecialchars(
    $date_from
);
?>"
required>
</div>

<div class="filter-group">

<label>Date To</label>

<input type="date" name="date_to" value="<?php
echo htmlspecialchars(
    $date_to
);
?>"
required>

</div>

<div class="filter-group">

<label>Staff</label>
<select name="staff_id">
<option value="">All Staff</option>

<?php
while (
    $staff =
    mysqli_fetch_assoc(
        $staff_list
    )
) {
?>

<option value="<?php
echo htmlspecialchars(
    $staff['staff_id']
);
?>"

<?php
if (
    $selected_staff ==
    $staff['staff_id']
) {

    echo "selected";

}
?>
>

<?php
echo htmlspecialchars(
    $staff['fullname']
);
?>

(
<?php
echo htmlspecialchars(
    $staff['staff_id']
);
?>
)
</option>

<?php
}
?>

</select>

</div>

<div class="filter-group">

<label>Department</label>
<select name="department">
<option value="">All Departments</option>

<?php
while (
    $dept =
    mysqli_fetch_assoc(
        $department_list
    )
) {
?>

<option value="<?php
echo htmlspecialchars(
    $dept['department']
);
?>"

<?php
if (
    $selected_department ==
    $dept['department']
) {

    echo "selected";

}
?>
>

<?php
echo htmlspecialchars(
    $dept['department']
);
?>
</option>

<?php
}
?>
</select>

</div>

</div>

<div class="report-buttons">
<button type="submit" name="generate" value="1" class="generate-btn">📊 Generate Report</button>
<button type="button" onclick="window.print()" class="print-btn">🖨️ Print</button>
<a href="admin_staff_hours_report.php" class="reset-btn">Reset</a>
</div>

</form>

</div>

<?php if ($generate): ?>

<div class="summary-grid">

<div class="summary-card">

<h4>Report Records</h4>

<h2>
<?php
echo number_format(
    $total_records
);
?>
</h2>

</div>

<div class="summary-card">

<h4>Total Hours</h4>

<h2>
<?php
echo number_format(
    $total_hours,
    2
);
?>
 hrs
</h2>

</div>

<div class="summary-card">

<h4>Expected Hours</h4>

<h2>
<?php
echo number_format(
    $expected_hours,
    2
);
?>
 hrs
</h2>

</div>

<div class="summary-card">

<h4>Overtime</h4>

<h2>
<?php
echo number_format(
    $total_overtime,
    2
);
?>
 hrs
</h2>

</div>

</div>

<div class="report-table-box">

<div style=" display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">

<div>

<h2>Staff Hours Report</h2>

<p>
<?php
echo date(
    'd M Y',
    strtotime($date_from)
);
?>

-

<?php
echo date(
    'd M Y',
    strtotime($date_to)
);
?>

</p>

</div>

</div>

<?php if (count($report) > 0): ?>

<table class="report-table">

<thead>
<tr>
<th>Date</th>
<th>Staff ID</th>
<th>Staff Name</th>
<th>Department</th>
<th>Clock In</th>
<th>Clock Out</th>
<th>Hours</th>
<th>Overtime</th>
<th>Status</th>
</tr>
</thead>

<tbody>

<?php foreach ($report as $row): ?>

<tr>

<td>
<?php
echo date(
    'd M Y',
    strtotime(
        $row['work_date']
    )
);

?>

</td>

<td>
<?php
echo htmlspecialchars(
    $row['staff_id']
);
?>
</td>

<td>

<strong>
<?php
echo htmlspecialchars(
    $row['fullname']
);
?>
</strong>

</td>

<td>

<?php
echo htmlspecialchars(
    $row['department'] ??
    'N/A'
);
?>

</td>

<td>

<?php
echo $row['clock_in'] ??
    '--';
?>

</td>

<td>

<?php
echo $row['clock_out'] ??
    '--';
?>

</td>

<td>

<strong>

<?php

echo number_format(
    (float)(
        $row['calculated_hours']
        ?? 0
    ),
    2
);
?>

</strong>

</td>

<td>
<?php
echo number_format(
    (float)(
        $row['calculated_overtime']
        ?? 0
    ),
    2
);
?>
</td>

<td>
<span class="status
<?php
echo strtolower(
    $row['status'] ??
    'pending'
);
?>
">

<?php
echo htmlspecialchars(
    $row['status'] ??
    'Pending'
);
?>

</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php else: ?>

<div style=" text-align:center; padding:40px; color:#777; ">
<h3>No records found</h3>
<p>No timesheet records match the selected filters.</p>
</div>

<?php endif; ?>

</div>

<?php endif; ?>

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