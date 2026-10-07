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

$month = isset($_GET['month'])
    ? $_GET['month']
    : date('Y-m');

$user_type = isset($_GET['user_type'])
    ? trim($_GET['user_type'])
    : '';

/* ============================== LOGIN REPORT QUERY ============================== */

$sql = "
    SELECT
        id,
        user_id,
        user_type,
        login_time
    FROM login_logs
    WHERE DATE_FORMAT(
        login_time,
        '%Y-%m'
    ) = ?
";

$params = [$month];

$types = "s";

/* ============================== USER TYPE FILTER ============================== */

if ($user_type != '') {

    $sql .= "
        AND LOWER(user_type) = LOWER(?)
    ";

    $params[] = $user_type;

    $types .= "s";

}

/* ============================== ORDER ============================== */

$sql .= "
    ORDER BY login_time DESC
";

/* ============================== PREPARE ============================== */

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

/* ============================== REPORT DATA ============================== */

$records = [];
$total_logins = 0;
$admin_logins = 0;
$supervisor_logins = 0;
$staff_logins = 0;

while ($row = $result->fetch_assoc()) {
    $records[] = $row;
    $total_logins++;
    $type = strtolower(
        trim($row['user_type'])
    );

    if ($type == 'admin') {
        $admin_logins++;
    }
    elseif ($type == 'supervisor') {
        $supervisor_logins++;
    }
    elseif ($type == 'staff') {
        $staff_logins++;
    }
}

/* ============================== MONTH NAME ============================== */

$month_name = date(
    "F Y",
    strtotime($month . "-01")
);

/* ============================== GET USER NAME ============================== */

function getUserName(
    $conn,
    $user_id,
    $user_type
) {

    $user_type = strtolower(
        trim($user_type)
    );

    /* ============================== ADMIN ============================== */

    if ($user_type == 'admin') {

        $sql = "
            SELECT fullname
            FROM admins
            WHERE admin_id = ?
            LIMIT 1
        ";

    }

    /* ============================== SUPERVISOR ============================== */

    elseif ($user_type == 'supervisor') {

        $sql = "
            SELECT fullname
            FROM supervisors
            WHERE supervisor_id = ?
            LIMIT 1
        ";

    }

    /* ============================== STAFF ============================== */

    elseif ($user_type == 'staff') {

        $sql = "
            SELECT fullname
            FROM staff
            WHERE staff_id = ?
            LIMIT 1
        ";
    }

    else {
        return "Unknown User";
    }


    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return "Unknown User";

    }

    $stmt->bind_param(
        "s",
        $user_id
    );


    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['fullname'];
    }

    return "Unknown User";
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Activity Report</title>
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

.filter-group input,
.filter-group select {
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
}

table tr:hover {
    background: #f5f5f5;
}

/* ============================== USER TYPE ============================== */

.user-type {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}

.user-admin {
    background: #e2e3e5;
    color: #41464b;
}

.user-supervisor {
    background: #cfe2ff;
    color: #084298;
}

.user-staff {
    background: #d1e7dd;
    color: #0f5132;
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
<h1>Login Activity Report</h1>
<p>Monitor administrator, supervisor and staff login activity.</p>
</div>

<div id="clock"></div>

</div>

<div class="report-container">

<!-- ============================== FILTERS ============================== -->

<form method="GET" class="filters no-print">

<div class="filter-group">
<label>Month</label>
<input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>" required>
</div>

<div class="filter-group">

<label>User Type</label>

<select name="user_type">
<option value="">All Users</option>
<option value="admin" <?php

if (
    $user_type == 'admin'
) {
    echo 'selected';

}
?>
>
Admin
</option>

<option value="supervisor"
<?php

if (
    $user_type == 'supervisor'
) {

    echo 'selected';

}

?>
>
Supervisor
</option>

<option value="staff"
<?php

if (
    $user_type == 'staff'
) {

    echo 'selected';

}

?>
>
Staff
</option>

</select>

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
<h2>Login Activity Report -
<?php
echo htmlspecialchars(
    $month_name
);
?>
</h2>

<p>Showing login activity for
<?php
echo htmlspecialchars(
    $month_name
);
?>.
</p>

</div>

<!-- ============================== SUMMARY ============================== -->

<div class="summary-grid">

<div class="summary-card">
<h4>Total Logins
<strong>
<?php
echo $total_logins;
?>
</strong>
</div>

<div class="summary-card">
<h4>Admin Logins</h4>
<strong>
<?php
echo $admin_logins;
?>
</strong>
</div>

<div class="summary-card">
<h4>Supervisor Logins</h4>
<strong>
<?php
echo $supervisor_logins;
?>
</strong>
</div>

<div class="summary-card">
<h4>Staff Logins</h4>
<strong>
<?php
echo $staff_logins;
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
<th>User Name</th>
<th>User Type</th>
<th>Login Date</th>
<th>Login Time</th>
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

$type = strtolower(
    trim($row['user_type'])
);


$user_name = getUserName(
    $conn,
    $row['user_id'],
    $row['user_type']
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
    $row['user_id']
);
?>
</td>

<td>
<?php
echo htmlspecialchars(
    $user_name
);
?>
</td>

<td>
<span class="user-type
user-<?php
echo htmlspecialchars(
    $type
);
?>">

<?php
echo ucfirst(
    htmlspecialchars(
        $type
    )
);
?>
</span>
</td>

<td>
<?php
echo date(
    "d-M-Y",
    strtotime(
        $row['login_time']
    )
);

?>
</td>

<td>
<?php
echo date(
    "h:i:s A",
    strtotime(
        $row['login_time']
    )
);
?>
</td>

</tr>

<?php
endforeach;
?>

<?php
else:
?>

<tr>

<td colspan="6" style=" text-align:center; padding:30px;">No login records found for
<?php
echo htmlspecialchars(
    $month_name
);
?>.
</td>

</tr>

<?php
endif;
?>

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