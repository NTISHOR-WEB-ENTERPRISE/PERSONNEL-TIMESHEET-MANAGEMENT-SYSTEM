<?php

session_start();

include "config.php";


/* ==============================
   ADMIN AUTHENTICATION
============================== */

if (!isset($_SESSION['admin_id'])) {

    header("Location: admin_login.php");
    exit();

}

if ($_SESSION['admin_role'] != "Super Admin") {

    die("Access Denied");

}


$fullname   = $_SESSION['fullname'];
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
   FILTERS
============================== */

$month = isset($_GET['month'])
    ? $_GET['month']
    : date('Y-m');

$staff_id = isset($_GET['staff_id'])
    ? trim($_GET['staff_id'])
    : '';

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';


/* ==============================
   GET STAFF
============================== */

$staff_sql = "
    SELECT staff_id, fullname
    FROM staff
    ORDER BY fullname ASC
";

$staff_result = $conn->query($staff_sql);

if (!$staff_result) {

    die(
        "Error loading staff: "
        . $conn->error
    );

}


/* ==============================
   TASK REPORT QUERY
============================== */

$sql = "
    SELECT
        tr.id,
        tr.staff_id,
        s.fullname,
        s.department,
        tr.report_date,
        tr.start_time,
        tr.end_time,
        tr.hours_worked,
        tr.tasks_completed,
        tr.challenges,
        tr.remarks,
        tr.status,
        tr.supervisor_comment,
        tr.approved_at

    FROM task_reports tr

    INNER JOIN staff s
        ON tr.staff_id = s.staff_id

    WHERE DATE_FORMAT(
        tr.report_date,
        '%Y-%m'
    ) = ?
";


$params = [$month];

$types = "s";


/* ==============================
   STAFF FILTER
============================== */

if ($staff_id != '') {

    $sql .= "
        AND tr.staff_id = ?
    ";

    $params[] = $staff_id;

    $types .= "s";

}


/* ==============================
   STATUS FILTER
============================== */

if ($status != '') {

    $sql .= "
        AND LOWER(tr.status) = LOWER(?)
    ";

    $params[] = $status;

    $types .= "s";

}


/* ==============================
   ORDER
============================== */

$sql .= "
    ORDER BY
        tr.report_date ASC,
        s.fullname ASC
";


/* ==============================
   PREPARE
============================== */

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "SQL Error: "
        . $conn->error
    );

}


$stmt->bind_param(
    $types,
    ...$params
);


$stmt->execute();

$result = $stmt->get_result();


/* ==============================
   REPORT DATA
============================== */

$records = [];

$total_reports = 0;

$approved_reports = 0;

$pending_reports = 0;

$rejected_reports = 0;

$total_hours = 0;


while ($row = $result->fetch_assoc()) {

    $records[] = $row;

    $total_reports++;

    $total_hours +=
        (float)$row['hours_worked'];


    $row_status =
        strtolower(
            trim($row['status'])
        );


    if ($row_status == 'approved') {

        $approved_reports++;

    }


    if ($row_status == 'pending') {

        $pending_reports++;

    }


    if ($row_status == 'rejected') {

        $rejected_reports++;

    }

}


/* ==============================
   MONTH NAME
============================== */

$month_name = date(
    "F Y",
    strtotime($month . "-01")
);

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Staff Task Report</title>

<link rel="stylesheet"
      href="styles.css">


<style>

/* ==============================
   REPORT CONTAINER
============================== */

.report-container {

    background: white;

    padding: 25px;

    border-radius: 10px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);

    margin-top: 25px;

}


/* ==============================
   FILTERS
============================== */

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


/* ==============================
   BUTTONS
============================== */

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


/* ==============================
   SUMMARY
============================== */

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 25px;

}


.summary-card {

    background: #f8f9fa;

    padding: 20px;

    border-radius: 8px;

    border-left: 5px solid #0056b3;

}


.summary-card h4 {

    margin: 0 0 8px;

    color: #555;

}


.summary-card strong {

    font-size: 24px;

    color: #0056b3;

}


/* ==============================
   TABLE
============================== */

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

    white-space: nowrap;

}


table td {

    padding: 11px;

    border-bottom: 1px solid #ddd;

    vertical-align: top;

}


table tr:hover {

    background: #f5f5f5;

}


/* ==============================
   TASK CELL
============================== */

.task-cell {

    min-width: 250px;

    max-width: 400px;

    white-space: normal;

}


/* ==============================
   CHALLENGES
============================== */

.challenge-cell {

    min-width: 200px;

    max-width: 300px;

}


/* ==============================
   STATUS
============================== */

.status {

    padding: 5px 10px;

    border-radius: 15px;

    font-size: 12px;

    font-weight: bold;

    display: inline-block;

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


/* ==============================
   HOURS
============================== */

.hours {

    font-weight: bold;

    color: #0056b3;

}


/* ==============================
   PRINT
============================== */

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


/* ==============================
   RESPONSIVE
============================== */

@media(max-width:900px) {

    .summary-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media(max-width:600px) {

    .summary-grid {

        grid-template-columns: 1fr;

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

echo htmlspecialchars(
    $app['organization_name']
);

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


<li>

<a href="admin_dashboard.php">

Dashboard

</a>

</li>


<li>

<a href="manage_admins.php">

Manage Admins

</a>

</li>


<li>

<a href="manage_supervisors.php">

Manage Supervisors

</a>

</li>


<li>

<a href="manage_staff.php">

Manage Staff

</a>

</li>


<li class="active">

<a href="admin_reports.php">

Reports

</a>

</li>


<li>

<a href="admin_logout.php">

Logout

</a>

</li>


</ul>


</div>


<!-- ==============================
     MAIN
============================== -->

<div class="main">


<div class="topbar">


<div>

<h1>

Staff Task Report

</h1>

<p>

View tasks and daily work reports
submitted by staff members.

</p>

</div>


<div id="clock"></div>


</div>



<div class="report-container">


<!-- ==============================
     FILTERS
============================== -->

<form method="GET"
      class="filters no-print">


<div class="filter-group">

<label>

Month

</label>

<input
type="month"
name="month"
value="<?php
echo htmlspecialchars($month);
?>"
required
>

</div>


<div class="filter-group">

<label>

Staff

</label>

<select name="staff_id">

<option value="">

All Staff

</option>


<?php

while (
    $staff =
    $staff_result->fetch_assoc()
):

?>

<option
value="<?php
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

<label>

Status

</label>

<select name="status">

<option value="">

All Statuses

</option>


<option
value="Pending"

<?php

if (
    strtolower($status)
    == 'pending'
) {

    echo "selected";

}

?>

>

Pending

</option>


<option
value="Approved"

<?php

if (
    strtolower($status)
    == 'approved'
) {

    echo "selected";

}

?>

>

Approved

</option>


<option
value="Rejected"

<?php

if (
    strtolower($status)
    == 'rejected'
) {

    echo "selected";

}

?>

>

Rejected

</option>


</select>

</div>


<div class="filter-group">

<button
type="submit"
class="btn btn-primary"
>

Generate Report

</button>

</div>


<div class="filter-group">

<button
type="button"
onclick="window.print()"
class="btn btn-secondary"
>

Print

</button>

</div>


</form>



<!-- ==============================
     TITLE
============================== -->

<div style="margin-bottom:20px;">

<h2>

Staff Task Report -
<?php

echo htmlspecialchars(
    $month_name
);

?>

</h2>

<p>

Showing staff task reports for
<?php

echo htmlspecialchars(
    $month_name
);

?>.

</p>

</div>



<!-- ==============================
     SUMMARY
============================== -->

<div class="summary-grid">


<div class="summary-card">

<h4>

Total Reports

</h4>

<strong>

<?php

echo $total_reports;

?>

</strong>

</div>


<div class="summary-card">

<h4>

Approved

</h4>

<strong>

<?php

echo $approved_reports;

?>

</strong>

</div>


<div class="summary-card">

<h4>

Pending

</h4>

<strong>

<?php

echo $pending_reports;

?>

</strong>

</div>


<div class="summary-card">

<h4>

Total Hours

</h4>

<strong>

<?php

echo number_format(
    $total_hours,
    2
);

?> hrs

</strong>

</div>


</div>



<!-- ==============================
     TABLE
============================== -->

<div class="table-wrapper">


<table>


<thead>

<tr>

<th>#</th>

<th>Staff ID</th>

<th>Staff Name</th>

<th>Department</th>

<th>Date</th>

<th>Start Time</th>

<th>End Time</th>

<th>Hours</th>

<th>Tasks Completed</th>

<th>Challenges</th>

<th>Status</th>

<th>Supervisor Comment</th>

<th>Remarks</th>

</tr>

</thead>


<tbody>


<?php

if (
    count($records) > 0
):


$count = 1;

foreach (
    $records as $row
):


$row_status =
    strtolower(
        trim(
            $row['status']
        )
    );

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
        $row['report_date']
    )
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['start_time']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['end_time']
);

?>

</td>


<td class="hours">

<?php

echo number_format(
    (float)$row['hours_worked'],
    2
);

?>

</td>


<td class="task-cell">

<?php

echo nl2br(
    htmlspecialchars(
        $row['tasks_completed']
    )
);

?>

</td>


<td class="challenge-cell">

<?php

echo nl2br(
    htmlspecialchars(
        $row['challenges']
        ?? ''
    )
);

?>

</td>


<td>

<span class="status
status-<?php

echo htmlspecialchars(
    $row_status
);

?>">

<?php

echo ucfirst(
    htmlspecialchars(
        $row_status
    )
);

?>

</span>

</td>


<td>

<?php

echo nl2br(
    htmlspecialchars(
        $row['supervisor_comment']
        ?? ''
    )
);

?>

</td>


<td>

<?php

echo nl2br(
    htmlspecialchars(
        $row['remarks']
        ?? ''
    )
);

?>

</td>


</tr>


<?php

endforeach;

?>


<?php else: ?>


<tr>

<td
colspan="13"
style="
text-align:center;
padding:30px;
">

No staff task reports found
for
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

    document.getElementById(
        "clock"
    ).innerHTML =
        new Date().toLocaleTimeString(
            'en-GB',
            {
                hour12:false
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