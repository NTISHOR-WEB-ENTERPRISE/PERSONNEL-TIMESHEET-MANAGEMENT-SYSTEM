<?php
session_start();
include "config.php";

/* ===================================================== ADMIN AUTHENTICATION ===================================================== */

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SESSION['admin_role'] != "Super Admin") {
    die("Access Denied");

}

$fullname   = $_SESSION['fullname'] ?? '';
$admin_role = $_SESSION['admin_role'] ?? '';

/* ===================================================== FILTERS ===================================================== */

$month = isset($_GET['month']) && $_GET['month'] != ''
    ? $_GET['month']
    : date('Y-m');

$department = isset($_GET['department'])
    ? trim($_GET['department'])
    : '';

/* ===================================================== MONTH DATE RANGE ===================================================== */
$start_date = $month . '-01';

$end_date = date(
    'Y-m-t',
    strtotime($start_date)
);

/* ===================================================== OFFICIAL WORKING TIMES ===================================================== */
$official_start = '08:00:00';
$official_end   = '17:00:00';

/* ===================================================== GET DEPARTMENTS ===================================================== */
$departments = [];

$sql = "
    SELECT DISTINCT department
    FROM staff
    WHERE department IS NOT NULL
    AND department != ''
    ORDER BY department ASC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $departments[] = $row['department'];

    }

}

/* ===================================================== SAFE DEPARTMENT ===================================================== */
$safe_department = '';

if ($department != '') {

    $safe_department = mysqli_real_escape_string(
        $conn,
        $department
    );
}

/* ===================================================== STAFF CONDITION ===================================================== */
$staff_condition = '';

if ($department != '') {

    $staff_condition = "
        AND s.department = '$safe_department'
    ";

}

/* ===================================================== STAFF COUNT ===================================================== */
$staff_count = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM staff s
    WHERE 1=1
    $staff_condition
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $staff_count = (int)$row['total'];

}

/* ===================================================== INITIALIZE REPORT TOTALS ===================================================== */
$total_present  = 0;
$total_absent   = 0;
$total_hours    = 0;
$total_overtime = 0;

$staff_data = [];

/* ===================================================== GET STAFF ===================================================== */

$sql = "
    SELECT
        s.staff_id,
        s.fullname,
        s.department

    FROM staff s

    WHERE 1=1

    $staff_condition

    ORDER BY s.fullname ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {

    die(
        "Staff Query Error: " .
        mysqli_error($conn)
    );

}

/* ===================================================== PROCESS EACH STAFF MEMBER ===================================================== */
while ($staff = mysqli_fetch_assoc($result)) {

    $staff_id = $staff['staff_id'];

    $days_present  = 0;
    $days_absent   = 0;
    $staff_hours   = 0;
    $staff_overtime = 0;

    $worked_dates = [];

    /* ================================================= GET ATTENDANCE RECORDS ================================================= */
    $attendance_sql = "

        SELECT
            date,
            time_in,
            time_out,
            clock_in,
            clock_out,
            status

        FROM attendance

        WHERE staff_id = ?

        AND date BETWEEN ?
        AND ?

        ORDER BY date ASC

    ";

    $stmt = mysqli_prepare(
        $conn,
        $attendance_sql
    );

    if (!$stmt) {

        die(
            "Attendance Query Error: " .
            mysqli_error($conn)
        );

    }


    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $staff_id,
        $start_date,
        $end_date
    );

    mysqli_stmt_execute($stmt);

    $attendance_result =
        mysqli_stmt_get_result($stmt);

    /* ================================================= PROCESS ATTENDANCE RECORDS ================================================= */

    while (
        $attendance =
        mysqli_fetch_assoc($attendance_result)
    ) {

        $attendance_date =
            $attendance['date'];

        /* ============================================= MARK DATE AS HAVING AN ATTENDANCE RECORD ============================================= */

        $worked_dates[$attendance_date] = true;

        /* =============================================
           DAY OF WEEK
           
           1 = Monday
           2 = Tuesday
           3 = Wednesday
           4 = Thursday
           5 = Friday
           6 = Saturday
           7 = Sunday
        ============================================= */

        $day_number = (int)date(
            'N',
            strtotime($attendance_date)
        );

        /* ============================================= STATUS ============================================= */

        $status = strtolower(
            trim($attendance['status'] ?? '')
        );

        /* =============================================
           DAYS PRESENT
           
           Only Monday-Friday count as
           normal attendance.
        ============================================= */

        if (
            $day_number <= 5 &&
            $status == 'present'
        ) {

            $days_present++;

        }

        /* =============================================
           DETERMINE TIME IN
           Prefer time_in.
           If empty, use clock_in.
        ============================================= */

        $time_in = '';

        if (
            !empty($attendance['time_in'])
        ) {

            $time_in =
                $attendance['time_in'];

        }

        elseif (
            !empty($attendance['clock_in'])
        ) {

            $time_in =
                $attendance['clock_in'];

        }

        /* =============================================
           DETERMINE TIME OUT
           
           Prefer time_out.
           If empty, use clock_out.
        ============================================= */

        $time_out = '';

        if (
            !empty($attendance['time_out'])
        ) {

            $time_out =
                $attendance['time_out'];

        }

        elseif (
            !empty($attendance['clock_out'])
        ) {

            $time_out =
                $attendance['clock_out'];

        }

        /* ============================================= CALCULATE HOURS WORKED ============================================= */

        $hours_worked = 0;

        if (
            $time_in != '' &&
            $time_out != ''
        ) {

            $in_seconds =
                strtotime($time_in);

            $out_seconds =
                strtotime($time_out);

            /*
             * Make sure time-out is after
             * time-in.
             */

            if ($out_seconds > $in_seconds) {

                $seconds =
                    $out_seconds -
                    $in_seconds;

                $hours_worked =
                    $seconds / 3600;

            }

        }

        /* ============================================= ADD TO TOTAL HOURS ============================================= */

        $staff_hours += $hours_worked;

        /* =============================================
           CALCULATE OVERTIME
           
           WEEKEND:
           100% of hours worked = overtime.
           
           WEEKDAY:
           Anything after 17:00 = overtime.
        ============================================= */

        $overtime_hours = 0;

        /* =============================================
           SATURDAY / SUNDAY
        ============================================= */

        if ($day_number >= 6) {

            /*
             * All weekend hours are overtime.
             */

            $overtime_hours =
                $hours_worked;

        }

        else {

            if (
                $time_out != '' &&
                strtotime($time_out) >
                strtotime($official_end)
            ) {

                $overtime_seconds =
                    strtotime($time_out) -
                    strtotime($official_end);

                $overtime_hours =
                    $overtime_seconds / 3600;

            }

        }

        /* ============================================= ADD OVERTIME ============================================= */

        $staff_overtime +=
            $overtime_hours;

    }

    mysqli_stmt_close($stmt);

    /* =================================================
       CALCULATE ABSENT DAYS
       
       Monday-Friday only.
    ================================================= */

    $current_date =
        new DateTime($start_date);

    $last_date =
        new DateTime($end_date);


    while (
        $current_date <= $last_date
    ) {

        $date_string =
            $current_date->format('Y-m-d');


        $day_number =
            (int)$current_date->format('N');

        /*
         * Monday-Friday are working days.
         */

        if ($day_number <= 5) {

            /*
             * No attendance record =
             * absent.
             */

            if (
                !isset(
                    $worked_dates[$date_string]
                )
            ) {

                $days_absent++;

            }

        }

        $current_date->modify('+1 day');

    }

    /* ================================================= ADD TO OVERALL TOTALS ================================================= */

    $total_present +=
        $days_present;

    $total_absent +=
        $days_absent;

    $total_hours +=
        $staff_hours;

    $total_overtime +=
        $staff_overtime;

    /* ================================================= SAVE STAFF DATA ================================================= */

    $staff_data[] = [

        'staff_id' =>
            $staff['staff_id'],

        'fullname' =>
            $staff['fullname'],

        'department' =>
            $staff['department'],

        'days_present' =>
            $days_present,

        'days_absent' =>
            $days_absent,

        'total_hours' =>
            $staff_hours,

        'total_overtime' =>
            $staff_overtime

    ];

}


/* ===================================================== ATTENDANCE PERCENTAGE ===================================================== */

$total_attendance_days =
    $total_present +
    $total_absent;

$attendance_percentage = 0;

if ($total_attendance_days > 0) {

    $attendance_percentage =
        (
            $total_present /
            $total_attendance_days
        ) * 100;

}

/* ===================================================== DEPARTMENT NAME ===================================================== */

$department_name =
    $department != ''
    ? $department
    : 'All Departments';

/* ===================================================== MONTH NAME ===================================================== */

$month_name = date(
    'F Y',
    strtotime($start_date)
);

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Report</title>
<link rel="stylesheet" href="styles.css">
<style>

.report-container {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);
}

.filter-box {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.filter-box label {
    font-weight: bold;
}

.filter-box input,
.filter-box select {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.btn {
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    background: #0056b3;
    color: white;
    cursor: pointer;
}

.btn:hover {
    background: #003d80;
}

.stats {
    display: grid;
    grid-template-columns:
        repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: #f5f7fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.stat-card h3 {
    margin: 0;
    font-size: 24px;
    color: #0056b3;
}

.stat-card p {
    margin: 5px 0 0;
    color: #666;
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 12px;
    border: 1px solid #ddd;
    text-align: left;
}

th {
    background: #0056b3;
    color: white;
}

tr:nth-child(even) {
    background: #f8f9fa;
}

.print-btn {
    float: right;
    margin-bottom: 15px;
}

@media(max-width:1100px) {
    .stats {
        grid-template-columns:
            repeat(3, 1fr);
    }
}

@media(max-width:700px) {
    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media(max-width:500px) {
    .stats {
        grid-template-columns: 1fr;
    }
}

@media print {
    .sidebar,
    .topbar,
    .filter-box,
    .print-btn {
        display: none !important;
    }

    .main {
        margin: 0;
        width: 100%;
    }

    .report-container {
        box-shadow: none;
    }
}
</style>
</head>

<body>

<div class="container">

<!-- ============================== SIDEBAR ============================== -->

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

<?php

echo strtoupper(
    substr($fullname, 0, 1)
);

?>

</div>

<h3><?php echo htmlspecialchars($fullname); ?> </h3>
<p> <?php echo htmlspecialchars($admin_role); ?> </p>

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

<!-- ============================== MAIN ============================== -->

<div class="main">

<div class="topbar">

<div>
<h1>Department Report</h1>
<p>Attendance and working-hours report by department</p>
</div>

<div id="clock"></div>

</div>

<div class="report-container">

<!-- ============================== FILTERS ============================== -->

<form method="GET" class="filter-box">

<div>
<label>Month:</label>
<br>
<input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>" required >
</div>

<div>
<label>Department:</label>
<br>
<select name="department">
<option value="">All Departments</option>

<?php foreach ($departments as $dept): ?>

<option value="<?php echo htmlspecialchars($dept); ?>"

<?php

if ($department == $dept) {

    echo 'selected';

}

?>

>

<?php
echo htmlspecialchars($dept);
?>

</option>

<?php endforeach; ?>

</select>

</div>

<div style="align-self:end;">
<button type="submit" class="btn">Generate Report</button>
</div>

</form>

<!-- ============================== REPORT HEADING ============================== -->

<h2>
<?php
echo htmlspecialchars(
    $department_name
);
?>
Department Report</h2>

<p>Reporting Period:
    <strong>
<?php
echo htmlspecialchars(
    $month_name
);
?>
</strong>
</p>

<!-- ============================== STATISTICS ============================== -->

<div class="stats">

<div class="stat-card">

<h3>
<?php
echo $staff_count;
?>
</h3>

<p>Total Staff</p>
</div>

<div class="stat-card">

<h3>
<?php
echo $total_present;
?>
</h3>

<p>Days Present</p>
</div>

<div class="stat-card">

<h3>
<?php
echo $total_absent;
?>
</h3>

<p>Days Absent</p>
</div>

<div class="stat-card">
<h3>
<?php
echo number_format(
    $total_hours,
    2
);
?>
</h3>

<p>Total Hours</p>

</div>

<div class="stat-card">
<h3>
<?php
echo number_format(
    $total_overtime,
    2
);
?>
</h3>

<p>Overtime Hours</p>

</div>

</div>

<div style="margin-bottom:20px;">

<strong>Attendance Percentage:</strong>

<?php
echo number_format(
    $attendance_percentage,
    2
);
?>%
</div>

<!-- PRINT -->
<button onclick="window.print()" class="btn print-btn">🖨 Print Report</button>

<div style="clear:both;"></div>

<!-- ============================== STAFF BREAKDOWN ============================== -->

<h3>Staff Breakdown</h3>

<div class="table-container">

<table>

<thead>

<tr>
<th>Staff ID</th>
<th>Staff Name</th>
<th>Department</th>
<th>Days Present</th>
<th>Days Absent</th>
<th>Total Hours</th>
<th>Overtime</th>
</tr>

</thead>

<tbody>

<?php if (count($staff_data) > 0): ?>

<?php foreach ($staff_data as $row): ?>

<tr>

<td>

<?php

echo htmlspecialchars(
    $row['staff_id']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['fullname']
);

?>

</td>

<td>

<?php
echo htmlspecialchars(
    $row['department']
);
?>

</td>

<td>
<?php
echo (int)$row['days_present'];
?>
</td>

<td>
<?php
echo (int)$row['days_absent'];
?>
</td>

<td>

<?php

echo number_format(
    (float)$row['total_hours'],
    2
);
?>

</td>

<td>
<?php
echo number_format(
    (float)$row['total_overtime'],
    2
);
?>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="7" style="text-align:center;"> No staff records found.</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>
</div>
</div>
</div>

<script>
function updateClock() {
    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString(
            'en-GB',
            {
                hour12: false
            }
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