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

$fullname   = $_SESSION['fullname'];
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

$supervisor_id = isset($_GET['supervisor_id'])
    ? trim($_GET['supervisor_id'])
    : '';

$supervisor_sql = "
    SELECT
        supervisor_id,
        fullname,
        department,
        status
    FROM supervisors
    ORDER BY fullname ASC
";

$supervisor_result = $conn->query($supervisor_sql);

if (!$supervisor_result) {
    die(
        "Error loading supervisors: "
        . $conn->error
    );
}

$sql = "
    SELECT
        s.supervisor_id,
        s.fullname AS supervisor_name,
        s.email,
        s.phone,
        s.department,
        s.status,

        COUNT(st.id) AS staff_count,

        GROUP_CONCAT(
            CONCAT(
                st.fullname,
                ' (',
                st.staff_id,
                ')'
            )
            ORDER BY st.fullname
            SEPARATOR ', '
        ) AS assigned_staff

    FROM supervisors s

    LEFT JOIN staff st
        ON st.supervisor_id = s.supervisor_id

    WHERE 1 = 1
";

$params = [];
$types = "";

if ($supervisor_id != '') {
    $sql .= "
        AND s.supervisor_id = ?
    ";

    $params[] = $supervisor_id;
    $types .= "s";

}

$sql .= "
    GROUP BY
        s.supervisor_id,
        s.fullname,
        s.email,
        s.phone,
        s.department,
        s.status

    ORDER BY
        s.fullname ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "SQL Error: "
        . $conn->error
    );
}

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );

}

$stmt->execute();
$result = $stmt->get_result();

$records = [];
$total_supervisors = 0;
$active_supervisors = 0;
$inactive_supervisors = 0;
$total_assigned_staff = 0;

while ($row = $result->fetch_assoc()) {

    $records[] = $row;
    $total_supervisors++;
    $status = strtolower(
        trim($row['status'])
    );

    if ($status == 'active') {
        $active_supervisors++;
    }

    if ($status == 'inactive') {
        $inactive_supervisors++;
    }

    $total_assigned_staff +=
        (int)$row['staff_count'];
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Supervisor Report</title>
<link rel="stylesheet" href="styles.css">

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


.filter-group select {

    padding: 10px;

    border: 1px solid #ccc;

    border-radius: 6px;

    min-width: 220px;

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
   STAFF LIST
============================== */

.staff-list {

    min-width: 250px;

    max-width: 400px;

    line-height: 1.6;

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


.status-active {

    background: #d1e7dd;

    color: #0f5132;

}


.status-inactive {

    background: #f8d7da;

    color: #842029;

}


/* ==============================
   STAFF COUNT
============================== */

.staff-count {

    font-size: 18px;

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

Supervisor Report

</h1>

<p>

View supervisors and their assigned
staff members.

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

Supervisor

</label>


<select name="supervisor_id">

<option value="">

All Supervisors

</option>


<?php

while (
    $sup =
    $supervisor_result->fetch_assoc()
):

?>

<option
value="<?php
echo htmlspecialchars(
    $sup['supervisor_id']
);
?>"

<?php

if (
    $supervisor_id ==
    $sup['supervisor_id']
) {

    echo "selected";

}

?>

>

<?php

echo htmlspecialchars(
    $sup['fullname']
);

?>

</option>

<?php endwhile; ?>

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

Supervisor Report

</h2>

<p>

Organization-wide supervisor and
staff assignment report.

</p>

</div>


<!-- ==============================
     SUMMARY
============================== -->

<div class="summary-grid">


<div class="summary-card">

<h4>

Total Supervisors

</h4>

<strong>

<?php

echo $total_supervisors;

?>

</strong>

</div>


<div class="summary-card">

<h4>

Active Supervisors

</h4>

<strong>

<?php

echo $active_supervisors;

?>

</strong>

</div>


<div class="summary-card">

<h4>

Inactive Supervisors

</h4>

<strong>

<?php

echo $inactive_supervisors;

?>

</strong>

</div>


<div class="summary-card">

<h4>

Assigned Staff

</h4>

<strong>

<?php

echo $total_assigned_staff;

?>

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

<th>Supervisor ID</th>

<th>Supervisor Name</th>

<th>Department</th>

<th>Email</th>

<th>Phone</th>

<th>Assigned Staff</th>

<th>Status</th>

</tr>

</thead>


<tbody>


<?php

if (
    count($records) > 0
):

?>


<?php

$count = 1;

foreach (
    $records as $row
):


$status_class =
    strtolower(
        trim($row['status'])
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
    $row['supervisor_id']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['supervisor_name']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['department'] ?? ''
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['email'] ?? ''
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['phone'] ?? ''
);

?>

</td>


<td class="staff-list">


<strong class="staff-count">

<?php

echo (int)$row['staff_count'];

?>

staff

</strong>


<br>


<?php

if (
    !empty(
        $row['assigned_staff']
    )
) {

    echo nl2br(
        htmlspecialchars(
            str_replace(
                ', ',
                "\n",
                $row['assigned_staff']
            )
        )
    );

} else {

    echo "<span style='color:#777;'>No staff assigned</span>";

}

?>


</td>


<td>

<span class="status
status-<?php
echo htmlspecialchars(
    $status_class
);
?>">

<?php

echo ucfirst(
    htmlspecialchars(
        $row['status']
    )
);

?>

</span>

</td>


</tr>


<?php

endforeach;

?>


<?php else: ?>


<tr>

<td
colspan="8"
style="
text-align:center;
padding:30px;
">

No supervisor records found.

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