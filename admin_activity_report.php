<?php
session_start();
include "config.php";

/* ============================== ADMIN AUTHENTICATION ============================== */

if (!isset($_SESSION['admin_id'])) {

    header("Location: admin_login.php");
    exit();
}

if ($_SESSION['admin_role'] != "Super Admin") {
    die("Access Denied");
}

$fullname   = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

/* ============================== ADMIN PROFILE PHOTO ============================== */

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

/* ============================== ADMIN PROFILE PHOTO PATH ============================== */

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

/* ============================== FILTERS ============================== */

$user_type = isset($_GET['user_type'])
    ? trim($_GET['user_type'])
    : '';

$activity_type = isset($_GET['activity_type'])
    ? trim($_GET['activity_type'])
    : '';

$action_type = isset($_GET['action_type'])
    ? trim($_GET['action_type'])
    : '';

$date_from = isset($_GET['date_from'])
    ? trim($_GET['date_from'])
    : '';

$date_to = isset($_GET['date_to'])
    ? trim($_GET['date_to'])
    : '';

/* ============================== GET ACTIVITY TYPES ============================== */

$activity_types = [];

$activity_type_sql = "
    SELECT DISTINCT activity_type
    FROM activity_logs
    ORDER BY activity_type ASC
";

$activity_type_result =
    $conn->query($activity_type_sql);

if ($activity_type_result) {

    while (
        $row =
        $activity_type_result->fetch_assoc()
    ) {

        if (
            !empty(
                $row['activity_type']
            )
        ) {

            $activity_types[] =
                $row['activity_type'];

        }

    }

}


/* ============================== GET ACTION TYPES ============================== */

$action_types = [];

$action_type_sql = "
    SELECT DISTINCT action_type
    FROM activity_logs
    ORDER BY action_type ASC
";

$action_type_result =
    $conn->query($action_type_sql);

if ($action_type_result) {

    while (
        $row =
        $action_type_result->fetch_assoc()
    ) {

        if (
            !empty(
                $row['action_type']
            )
        ) {

            $action_types[] =
                $row['action_type'];

        }

    }

}


/* ============================== MAIN QUERY ============================== */

$sql = "
    SELECT
        id,
        user_id,
        user_type,
        activity_type,
        activity_description,
        activity_time,
        action_type

    FROM activity_logs

    WHERE 1 = 1
";

$params = [];

$types = "";

/* ============================== USER TYPE FILTER ============================== */

if ($user_type != '') {

    $sql .= "
        AND user_type = ?
    ";

    $params[] = $user_type;

    $types .= "s";

}

/* ============================== ACTIVITY TYPE FILTER ============================== */

if ($activity_type != '') {

    $sql .= "
        AND activity_type = ?
    ";

    $params[] = $activity_type;

    $types .= "s";

}

/* ============================== ACTION TYPE FILTER ============================== */

if ($action_type != '') {

    $sql .= "
        AND action_type = ?
    ";

    $params[] = $action_type;

    $types .= "s";

}

/* ============================== DATE FROM ============================== */

if ($date_from != '') {

    $sql .= "
        AND DATE(activity_time) >= ?
    ";

    $params[] = $date_from;

    $types .= "s";

}

/* ============================== DATE TO ============================== */

if ($date_to != '') {

    $sql .= "
        AND DATE(activity_time) <= ?
    ";

    $params[] = $date_to;

    $types .= "s";

}

/* ============================== ORDER ============================== */

$sql .= "
    ORDER BY
        activity_time DESC
";

/* ============================== PREPARE ============================== */

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

/* ============================== REPORT DATA ============================== */

$records = [];

$total_activities = 0;

$admin_activities = 0;

$supervisor_activities = 0;

$staff_activities = 0;

while (
    $row =
    $result->fetch_assoc()
) {

    $records[] = $row;

    $total_activities++;


    $type = strtolower(
        trim(
            $row['user_type']
        )
    );


    if ($type == 'admin') {

        $admin_activities++;

    }


    if ($type == 'supervisor') {

        $supervisor_activities++;

    }


    if ($type == 'staff') {

        $staff_activities++;

    }

}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Activity Report</title>
<link rel="stylesheet" href="styles.css">

<style>

/* ============================== REPORT CONTAINER ============================== */

.report-container {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);
    margin-top: 25px;
}

/* ============================== FILTERS ============================== */

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

.filter-group select,
.filter-group input {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    min-width: 180px;
}

/* ============================== BUTTONS ============================== */
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

/* ============================== SUMMARY ============================== */
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

/* ============================== TABLE ============================== */
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

/* ============================== DESCRIPTION ============================== */
.description-cell {
    min-width: 300px;
    max-width: 500px;
}

/* ============================== USER TYPE ============================== */
.user-type {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}

/* ADMIN */
.user-admin {
    background: #cfe2ff;
    color: #084298;
}

/* SUPERVISOR */
.user-supervisor {
    background: #e2d9f3;
    color: #432874;
}

/* STAFF */
.user-staff {
    background: #d1e7dd;
    color: #0f5132;
}

/* OTHER */
.user-other {
    background: #e9ecef;
    color: #495057;
}

/* ============================== ACTION ============================== */
.action {
    font-weight: bold;
}

/* ============================== PRINT ============================== */
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

/* ============================== RESPONSIVE ============================== */
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
<img src="<?php echo htmlspecialchars($admin_profile_photo); ?>" class="profile-small" alt="Profile" onerror="this.onerror=null; this.src='uploads/admins/default.png';">
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

<!-- ============================== MAIN ============================== -->
<div class="main">

<div class="topbar">

<div>
<h1>System Activity Report</h1>
<p>Monitor activities performed by administrators, supervisors and staff.</p>
</div>

<div id="clock"></div>

</div>

<div class="report-container">

<!-- ============================== FILTERS ============================== -->
<form method="GET" class="filters no-print">

<div class="filter-group">

<label>User Type</label>
<select name="user_type">
<option value="">All Users</option>
<option value="Admin"
<?php
if ($user_type == "Admin")
    echo "selected";
?>
>
Admin
</option>

<option value="Supervisor"
<?php
if ($user_type == "Supervisor")
    echo "selected";
?>
>
Supervisor
</option>

<option value="Staff"
<?php
if ($user_type == "Staff")
    echo "selected";
?>
>
Staff
</option>
</select>

</div>

<div class="filter-group">
<label>Activity Type</label>
<select name="activity_type">
<option value="">All Activity Types</option>

<?php

foreach (
    $activity_types
    as $type
):

?>

<option
value="<?php
echo htmlspecialchars(
    $type
);
?>"

<?php

if (
    $activity_type == $type
) {

    echo "selected";

}

?>

>

<?php

echo htmlspecialchars(
    $type
);

?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="filter-group">
<label>Action Type</label>
<select name="action_type">
<option value="">All Actions</option>

<?php
foreach (
    $action_types
    as $action
):
?>

<option
value="<?php
echo htmlspecialchars(
    $action
);
?>"

<?php

if (
    $action_type == $action
) {

    echo "selected";

}

?>

>

<?php

echo htmlspecialchars(
    $action
);
?>
</option>

<?php endforeach; ?>

</select>

</div>

<div class="filter-group">
<label>From</label>
<input type="date" name="date_from" value="<?php
echo htmlspecialchars(
    $date_from
);
?>"
>

</div>

<div class="filter-group">
<label>To</label>
<input type="date" name="date_to"
value="<?php
echo htmlspecialchars(
    $date_to
);
?>"
>

</div>

<div class="filter-group">
<button type="submit" class="btn btn-primary">Generate Report</button>
</div>

<div class="filter-group">
<button type="button" onclick="window.print()" class="btn btn-secondary">Print</button>
</div>

</form>

<!-- ============================== TITLE ============================== -->
<div style="margin-bottom:20px;">
<h2>System Activity Report</h2>
<p>Showing recorded system activities based on the selected filters.</p>
</div>

<!-- ============================== SUMMARY ============================== -->

<div class="summary-grid">
<div class="summary-card">

<h4>Total Activities</h4>
<strong>

<?php

echo $total_activities;

?>

</strong>

</div>

<div class="summary-card">
<h4>Admin Activities</h4>

<strong>

<?php
echo $admin_activities;
?>

</strong>

</div>

<div class="summary-card">
<h4>Supervisor Activities</h4>

<strong>
<?php
echo $supervisor_activities;
?>
</strong>

</div>

<div class="summary-card">
<h4>Staff Activities</h4>

<strong>
<?php
echo $staff_activities;
?>
</strong>

</div>

</div>

<!-- ============================== TABLE ============================== -->

<div class="table-wrapper">

<table>

<thead>
<tr>
<th>#</th>
<th>User ID</th>
<th>User Type</th>
<th>Activity Type</th>
<th>Activity Description</th>
<th>Action Type</th>
<th>Date & Time</th>
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

$user_type_class =
    strtolower(
        trim(
            $row['user_type']
        )
    );

if (
    !in_array(
        $user_type_class,
        [
            'admin',
            'supervisor',
            'staff'
        ]
    )
) {

    $user_type_class =
        'other';

}

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
    $row['user_id']
);
?>
</td>

<td>

<span class="user-type
user-<?php
echo htmlspecialchars(
    $user_type_class
);
?>">

<?php

echo htmlspecialchars(
    $row['user_type']
);

?>

</span>

</td>

<td>

<?php
echo htmlspecialchars(
    $row['activity_type']
);

?>
</td>

<td class="description-cell">
<?php
echo nl2br(
    htmlspecialchars(
        $row['activity_description']
    )
);
?>
</td>

<td class="action">
<?php
echo htmlspecialchars(
    $row['action_type']
);
?>
</td>

<td>
<?php
echo date(
    "d-M-Y H:i:s",
    strtotime(
        $row['activity_time']
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
<td colspan="7" style=" text-align:center; padding:30px; "> No activity records found. </td>

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