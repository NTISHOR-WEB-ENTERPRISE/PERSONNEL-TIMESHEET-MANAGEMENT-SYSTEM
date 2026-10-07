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

/* ===================================================== ADMIN PROFILE PHOTO ===================================================== */

$admin_id = $_SESSION['admin_id'];

$stmt = $conn->prepare("
    SELECT
        fullname,
        profile_photo
    FROM admins
    WHERE admin_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();
$stmt->close();

/* ===================================================== ADMIN PROFILE PHOTO PATH ===================================================== */
$admin_profile_photo = "uploads/admins/default.png";

if (
    $admin_data &&
    !empty($admin_data['profile_photo'])
) {

    $photo = basename(
        $admin_data['profile_photo']
    );

    $photo_path =
        "uploads/admins/" . $photo;

    if (file_exists($photo_path)) {

        $admin_profile_photo =
            $photo_path;
    }
}

/* ===================================================== FILTERS ===================================================== */

$month = isset($_GET['month']) &&
         $_GET['month'] != ''
    ? $_GET['month']
    : date('Y-m');

$staff_id = isset($_GET['staff_id'])
    ? trim($_GET['staff_id'])
    : '';

/* ===================================================== MONTH DATE RANGE ===================================================== */
$start_date = $month . "-01";

$end_date = date(
    "Y-m-t",
    strtotime($start_date)
);

/* ===================================================== GET STAFF ===================================================== */

$staff_sql = "
    SELECT
        staff_id,
        fullname
    FROM staff
    ORDER BY fullname ASC
";

$staff_result =
    $conn->query($staff_sql);

if (!$staff_result) {

    die(
        "Error loading staff: "
        . $conn->error
    );
}

/* ===================================================== ATTENDANCE QUERY =====================================================
   IMPORTANT:
   We are now using the ATTENDANCE table.
   The attendance table contains:
   staff_id
   date
   time_in
   time_out
   status
   clock_in
   clock_out
===================================================== */

$sql = "
    SELECT
        a.id,
        a.staff_id,
        s.fullname,
        s.department,
        a.date,
        a.time_in,
        a.time_out,
        a.clock_in,
        a.clock_out,
        a.status

    FROM attendance a

    INNER JOIN staff s
        ON a.staff_id = s.staff_id

    WHERE a.date BETWEEN ?
    AND ?

";

$params = [
    $start_date,
    $end_date
];

$types = "ss";

/* ===================================================== STAFF FILTER ===================================================== */

if ($staff_id != '') {

    $sql .= "
        AND a.staff_id = ?
    ";

    $params[] = $staff_id;
    $types .= "s";
}

/* ===================================================== ORDER ===================================================== */

$sql .= "
    ORDER BY
        a.date ASC,
        s.fullname ASC
";

/* ===================================================== PREPARE ===================================================== */

$stmt =
    $conn->prepare($sql);

if (!$stmt) {
    die(
        "SQL Error: "
        . $conn->error
    );
}

/* ===================================================== BIND PARAMETERS ===================================================== */

$stmt->bind_param(
    $types,
    ...$params
);

/* ===================================================== EXECUTE ===================================================== */

$stmt->execute();

$result =
    $stmt->get_result();

/* ===================================================== SUMMARY VARIABLES ===================================================== */
$total_overtime = 0;
$total_hours = 0;
$overtime_records = 0;
$records = [];

/* ===================================================== PROCESS ATTENDANCE RECORDS ===================================================== */

while (
    $row =
    $result->fetch_assoc()
) {

    /* ================================================= GET DATE================================================= */
    $work_date =
        $row['date'];

    /* ================================================= GET TIME IN =================================================
       Prefer time_in.
       If empty, use clock_in.
    ================================================= */
    $time_in =
        !empty($row['time_in'])
        ? $row['time_in']
        : $row['clock_in'];

    /* ================================================= GET TIME OUT =================================================
       Prefer time_out.
       If empty, use clock_out.
    ================================================= */
    $time_out =
        !empty($row['time_out'])
        ? $row['time_out']
        : $row['clock_out'];

    /* ================================================= INITIAL VALUES ================================================= */
    $hours_worked = 0;
    $overtime_hours = 0;

    /* ================================================= CALCULATE HOURS ================================================= */

    if (
        !empty($time_in) &&
        !empty($time_out)
    ) {

        $in_timestamp =
            strtotime(
                $work_date . ' ' . $time_in
            );

        $out_timestamp =
            strtotime(
                $work_date . ' ' . $time_out
            );

        /* ============================================= HANDLE OVERNIGHT SHIFT ============================================= */
        if (
            $out_timestamp < $in_timestamp
        ) {

            $out_timestamp += 86400;
        }

        /* ============================================= TOTAL HOURS WORKED ============================================= */
        $seconds_worked =
            $out_timestamp -
            $in_timestamp;

        $hours_worked =
            $seconds_worked / 3600;

        /* ============================================= DETERMINE DAY OF WEEK ============================================= */
        $day_number =
            (int)date(
                'N',
                strtotime($work_date)
            );

        /* ============================================= WEEKEND OVERTIME =============================================
           Saturday = 6
           Sunday   = 7
           100% of weekend hours are
           overtime.
        ============================================= */
        if ($day_number >= 6) {
            $overtime_hours =
                $hours_worked;
        }

        /* ============================================= WEEKDAY OVERTIME
        =============================================
           Official end time = 17:00
           Only hours after 17:00
           count as overtime.
        ============================================= */
        else {
            $official_end =
                strtotime(
                    $work_date . ' 17:00:00'
                );


            if (
                $out_timestamp >
                $official_end
            ) {

                $overtime_seconds =
                    $out_timestamp -
                    $official_end;

                $overtime_hours =
                    $overtime_seconds / 3600;
            }
        }
    }

    /* ================================================= ONLY INCLUDE RECORDS WITH OVERTIME ================================================= */
    if ($overtime_hours > 0) {
        $overtime_records++;
    }

    /* ================================================= ADD TO TOTALS ================================================= */

    $total_hours +=
        $hours_worked;

    $total_overtime +=
        $overtime_hours;

    /* ================================================= SAVE CALCULATED VALUES ================================================= */

    $row['hours_worked'] =
        $hours_worked;

    $row['overtime_hours'] =
        $overtime_hours;

    /* ================================================= SAVE RECORD ================================================= */
    $records[] = $row;
}

$stmt->close();

/* ===================================================== MONTH NAMEn===================================================== */

$month_name = date(
    "F Y",
    strtotime(
        $month . "-01"
    )
);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Overtime Report</title>
<link rel="stylesheet" href="styles.css">

<style>

.report-container {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow:
        0 2px 8px rgba(0,0,0,0.08);
    margin-top: 25px;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: end;
    margin-bottom: 25px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group label {
    font-weight: bold;
    font-size: 14px;
}

.filter-group input,
.filter-group select {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    min-width: 180px;
}

.btn {
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #0056b3;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.summary-grid {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.summary-card {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 5px solid #0056b3;
}

.summary-card h4 {
    margin: 0 0 8px;
    color: #555;
}

.summary-card strong {
    font-size: 25px;
    color: #0056b3;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th {
    background: #0056b3;
    color: white;
    padding: 12px;
    text-align: left;
}

table td {
    padding: 11px;
    border-bottom: 1px solid #ddd;
}

table tr:hover {
    background: #f5f5f5;
}

.overtime {
    font-weight: bold;
    color: #dc3545;
}

.status {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.status-approved {
    background: #d1e7dd;
    color: #0f5132;
}

.status-pending {
    background: #fff3cd;
    color: #664d03;
}

.status-rejected {
    background: #f8d7da;
    color: #842029;
}

.status-present {
    background: #d1e7dd;
    color: #0f5132;
}

@media print {

    .sidebar,
    .topbar,
    .filters,
    .no-print {
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

@media(max-width:800px) {

    .summary-grid {
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
<img src="<?php
        echo htmlspecialchars(
            $admin_profile_photo
        );
    ?>"
    class="profile-small"
    alt="Profile"
    onerror="
        this.onerror=null;
        this.src='uploads/admins/default.png';
    "
>

</div>

<h3>
<?php
echo htmlspecialchars(
    $fullname
);
?>

</h3>

<p>
<?php
echo htmlspecialchars(
    $admin_role
);
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
<h1>Overtime Report</h1>
<p>View staff overtime records for a selected month.</p>
</div>

<div id="clock"></div>

</div>

<div class="report-container">

<form method="GET" class="filters no-print">

<div class="filter-group">
<label>Month</label>
<input type="month" name="month" value="<?php
        echo htmlspecialchars(
            $month
        );
    ?>"
    required
>
</div>

<div class="filter-group">
<label>Staff</label>
<select name="staff_id">
<option value="">All Staff</option>
<?php
while (
    $staff =
    $staff_result->fetch_assoc()
):
?>

<option value="<?php
        echo htmlspecialchars(
            $staff['staff_id']
        );
    ?>"
    <?php

    if (
        $staff_id ==
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

</option>

<?php endwhile; ?>

</select>

</div>

<div class="filter-group">
<button type="submit" class="btn btn-primary">Generate Report</button>
</div>

<div class="filter-group">
<button type="button" onclick="window.print()" class="btn btn-secondary">Print</button>
</div>

</form>

<div style="margin-bottom:20px;">

<h2>Overtime Report -
<?php
echo htmlspecialchars(
    $month_name
);
?>
</h2>

<p>Showing staff overtime records for
<?php
echo htmlspecialchars(
    $month_name
);
?>.
</p>

</div>

<div class="summary-grid">

<div class="summary-card">

<h4>Overtime Records</h4>
<strong>
<?php
echo $overtime_records;
?>
</strong>

</div>

<div class="summary-card">

<h4>Total Hours Worked</h4>
<strong>
<?php
echo number_format(
    $total_hours,
    2
);
?>
hrs
</strong>
</div>

<div class="summary-card">

<h4>Total Overtime</h4>

<strong>
<?php
echo number_format(
    $total_overtime,
    2
);
?>
 hrs
</strong>

</div>

</div>

<div class="table-wrapper">

<table>

<thead>
<tr>
<th>#</th>
<th>Staff ID</th>
<th>Staff Name</th>
<th>Department</th>
<th>Date</th>
<th>Time In</th>
<th>Time Out</th>
<th>Hours Worked</th>
<th>Overtime Hours</th>
<th>Status</th>
</tr>
</thead>

<tbody>
<?php if (count($records) > 0): ?>
<?php
$count = 1;
foreach (
    $records
    as $row
):
?>

<tr>

<td>
<?php
echo $count++;
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
echo date(
    "d-M-Y",
    strtotime(
        $row['date']
    )
);
?>
</td>

<td>
<?php
$display_in =
    !empty($row['time_in'])
    ? $row['time_in']
    : $row['clock_in'];

echo !empty($display_in)
    ? date(
        "H:i",
        strtotime($display_in)
    )
    : '--';
?>
</td>

<td>
<?php
$display_out =
    !empty($row['time_out'])
    ? $row['time_out']
    : $row['clock_out'];

echo !empty($display_out)
    ? date(
        "H:i",
        strtotime($display_out)
    )
    : '--';
?>
</td>

<td>
<?php
echo number_format(
    (float)$row['hours_worked'],
    2
);
?>
 hrs
</td>

<td class="overtime">
<?php
echo number_format(
    (float)$row['overtime_hours'],
    2
);
?>
 hrs
</td>

<td>
<?php
$status =
    strtolower(
        trim(
            $row['status'] ?? ''
        )
    );

if ($status == '') {

    $status = 'unknown';

}
?>

<span class="
    status
    status-<?php
        echo htmlspecialchars(
            $status
        );
    ?>
">

<?php
echo ucfirst(
    htmlspecialchars(
        $status
    )
);
?>
</span>

</td>

</tr>

<?php endforeach; ?>
<?php else: ?>

<tr>

<td colspan="10"
    style=" text-align:center; padding:30px;">No attendance records found for
<?php
echo htmlspecialchars(
    $month_name
);
?>.
</td>

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