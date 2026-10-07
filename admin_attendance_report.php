<?php
session_start();
require_once "config.php";

/* ===================================================== ADMIN AUTHENTICATION ===================================================== */

if (!isset($_SESSION['admin_id'])) {

    header("Location: admin_login.php");
    exit();

}

$admin_id   = $_SESSION['admin_id'];
$fullname   = $_SESSION['fullname'] ?? '';
$admin_role = $_SESSION['admin_role'] ?? '';

/* ===================================================== ADMIN PROFILE ===================================================== */

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

/* ===================================================== ADMIN PROFILE PHOTO ===================================================== */

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

$from_date =
    $_GET['from_date']
    ?? date('Y-m-01');

$to_date =
    $_GET['to_date']
    ?? date('Y-m-d');

$staff_id =
    $_GET['staff_id']
     ?? '';

$department =
    $_GET['department']
    ?? '';

/* ===================================================== GET STAFF ===================================================== */

$staff_sql = "

    SELECT
        staff_id,
        fullname,
        department

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

/* ===================================================== ATTENDANCE QUERY ===================================================== */

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

    LEFT JOIN staff s
        ON a.staff_id =
           s.staff_id

    WHERE a.date
    BETWEEN ?
    AND ?

";

$params = [
    $from_date,
    $to_date
];

$types = "ss";

/* ===================================================== STAFF FILTER ===================================================== */

if (!empty($staff_id)) {

    $sql .= "
        AND a.staff_id = ?
    ";

    $params[] =
        $staff_id;

    $types .= "s";

}

/* ===================================================== DEPARTMENT FILTER ===================================================== */

if (!empty($department)) {

    $sql .= "
        AND s.department = ?
    ";

    $params[] =
        $department;

    $types .= "s";

}

/* ===================================================== ORDER ===================================================== */

$sql .= "

    ORDER BY
        a.date DESC,
        s.fullname ASC

";

/* ===================================================== PREPARE QUERY ===================================================== */

$stmt =
    $conn->prepare($sql);

if (!$stmt) {

    die(
        "SQL Prepare Error: "
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

/* ===================================================== SUMMARY ===================================================== */

$total_records = 0;
$present = 0;
$late = 0;
$absent = 0;
$attendance_rows = [];

/* ===================================================== PROCESS ATTENDANCE ===================================================== */

while ($row = $result->fetch_assoc()) {

    /* Store record */
    $attendance_rows[] = $row;

    $total_records++;

    /* ================================================= GET STATUS ================================================= */

    $status = strtolower(
        trim(
            $row['status'] ?? ''
        )
    );

    /* ================================================= GET ACTUAL CLOCK-IN TIME ================================================= */

    /*
     * Prefer clock_in.
     * If clock_in is empty, use time_in.
     */

    $actual_time_in = '';

    if (!empty($row['clock_in'])) {

        $actual_time_in = $row['clock_in'];

    } elseif (!empty($row['time_in'])) {

        $actual_time_in = $row['time_in'];

    }

    /* ================================================= OFFICIAL START TIME ================================================= */

    $official_start_time = '08:00:00';

    /* ================================================= DETERMINE ATTENDANCE STATUS ================================================= */

    /*
     * ABSENT
     *
     * If the database explicitly says absent,
     * count it as absent.
     */

    if (
        $status === 'absent' ||
        strpos($status, 'absent') !== false
    ) {

        $absent++;

    }

    /*
     * LATE
     *
     * If the database explicitly says late,
     * count it as late.
     */

    elseif (
        $status === 'late' ||
        strpos($status, 'late') !== false
    ) {

        $late++;

    }

    /*
     * PRESENT
     *
     * If database says present, check the
     * actual clock-in time.
     */

    elseif (
        $status === 'present' ||
        strpos($status, 'present') !== false
    ) {

        /*
         * If a clock-in time exists,
         * compare it with 08:00 AM.
         */

        if (!empty($actual_time_in)) {

            $clock_in_timestamp =
                strtotime($actual_time_in);

            $official_start_timestamp =
                strtotime($official_start_time);


            /*
             * Clocked in after 08:00 AM
             * = Late
             */

            if (
                $clock_in_timestamp >
                $official_start_timestamp
            ) {

                $late++;

            }

            /*
             * Clocked in at or before 08:00 AM
             * = Present
             */

            else {

                $present++;

            }

        }

        /*
         * No clock-in time available.
         *
         * Trust the database status.
         */

        else {

            $present++;

        }

    }


    /*
     * UNKNOWN / OTHER STATUS
     *
     * If the database contains another status,
     * do not count it as present, late or absent.
     */

}

/* ===================================================== ATTENDANCE RATE ===================================================== */

/*
 * Count working days
 *
 * Monday - Friday = working days
 * Saturday/Sunday = excluded
 */

$working_days = 0;

$current_date = new DateTime($from_date);
$end_date_obj = new DateTime($to_date);

while ($current_date <= $end_date_obj) {

    $day_of_week = (int)$current_date->format('N');

    if ($day_of_week <= 5) {

        $working_days++;

    }

    $current_date->modify('+1 day');
}

/* ===================================================== DETERMINE NUMBER OF STAFF ===================================================== */

$staff_count = 0;

/*
 * If a specific staff member is selected,
 * only one staff member is being reported.
 */

if (!empty($staff_id)) {

    $staff_count = 1;

}

/*
 * If All Staff is selected,
 * count all staff in the selected department.
 */

else {

    if (!empty($department)) {

        $count_stmt = $conn->prepare("
            SELECT COUNT(*)
            AS staff_count
            FROM staff
            WHERE department = ?
        ");

        $count_stmt->bind_param(
            "s",
            $department
        );

    } else {

        $count_stmt = $conn->prepare("
            SELECT COUNT(*)
            AS staff_count
            FROM staff
        ");

    }

    $count_stmt->execute();

    $count_result =
        $count_stmt->get_result();

    $count_row =
        $count_result->fetch_assoc();

    $staff_count =
        (int)$count_row['staff_count'];

    $count_stmt->close();

}

/* ===================================================== EXPECTED ATTENDANCE ===================================================== */

/*
 * Expected attendance =
 *
 * Working Days × Number of Staff
 */

$expected_attendance =
    $working_days * $staff_count;

/* ===================================================== ACTUAL ATTENDANCE ===================================================== */

/*
 * Present + Late both count as attendance.
 */

$attendance_days =
    $present + $late;

    /* ===================================================== CALCULATE ABSENT DAYS ===================================================== */

$calculated_absent =
    $expected_attendance - $attendance_days;

if ($calculated_absent < 0) {

    $calculated_absent = 0;

}

/* ===================================================== CALCULATE ATTENDANCE RATE ===================================================== */

$attendance_rate = 0;

if ($expected_attendance > 0) {

    $attendance_rate =
        (
            $attendance_days
            /
            $expected_attendance
        ) * 100;

}


/*
 * Safety check
 */

if ($attendance_rate > 100) {

    $attendance_rate = 100;

}

if ($attendance_rate < 0) {

    $attendance_rate = 0;

}


/* ===================================================== DEPARTMENTS ===================================================== */

$department_sql = "

    SELECT DISTINCT
        department

    FROM staff

    WHERE department IS NOT NULL

    AND department != ''

    ORDER BY department ASC

";

$department_result =
    $conn->query(
        $department_sql
    );

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Attendance Report</title>

<style>

/* ============================== GLOBAL ============================== */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #333;
}

/* ==============================
   MAIN LAYOUT
============================== */

.container {
    display: flex;
    min-height: 100vh;
    width: 100%;
}

/* ============================== SIDEBAR ============================== */

.sidebar {
    width: 250px;
    min-width: 250px;
    background: #888;
    min-height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    overflow-y: auto;
    z-index: 1000;
}

/* LOGO */

.logo {
    text-align: center;
    color: white;
    padding: 16px 8px 10px;
}

.logo h2 {
    margin: 0;
    font-size: 15px;
    line-height: 1.4;
    font-weight: bold;
}

.logo p {
    margin: 5px 0 0;
    font-size: 10px;
    color: #eee;
}

/* PROFILE */
.profile {
    text-align: center;
    color: white;
    padding: 8px 5px 12px;
}

.avatar {
    width: 55px;
    height: 55px;
    margin: 0 auto 7px;
    border-radius: 50%;
    overflow: hidden;
    background: #ddd;
}

.profile-small {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.profile h3 {
    margin: 5px 0 3px;
    font-size: 12px;
    text-transform: uppercase;
}

.profile p {
    margin: 0;
    font-size: 10px;
    color: #f1f1f1;
}

/* SIDEBAR MENU */
.sidebar ul {
    list-style: none;
    padding: 0;
    margin: 8px 13px 0;
}

.sidebar ul li {
    margin-bottom: 6px;
}

.sidebar ul li a {
    display: block;
    text-decoration: none;
    color: white;
    background: #0866d6;
    padding: 9px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    transition: 0.2s;
}

.sidebar ul li a:hover {
    background: #0754b5;
}

.sidebar ul li.active a {
    background: #06418f;
}

/* ============================== MAIN CONTENT ============================== */

.main {
    margin-left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;
    padding: 20px 25px;
}

/* ============================== TOPBAR ============================== */

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.topbar h1 {
    margin: 0 0 5px;
    color: #0056b3;
    font-size: 24px;
}

.topbar p {
    margin: 0;
    color: #777;
    font-size: 13px;
}

#clock {
    color: #0056b3;
    font-size: 12px;
    font-weight: bold;
}

/* ============================== HEADER ============================== */

.header {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.header h1 {
    margin: 0 0 5px;
    color: #0056b3;
}

.header p {
    margin: 0;
    color: #777;
}

/* ============================== FILTERS ============================== */

.filters {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: bold;
    font-size: 14px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.filter-buttons {
    display: flex;
    align-items: end;
    gap: 10px;
}

/* ============================== BUTTONS ============================== */

.btn {
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

/* ============================== SUMMARY ============================== */

.summary {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.card h3 {
    margin: 0 0 8px;
    font-size: 14px;
    color: #777;
}

.card .number {
    font-size: 28px;
    font-weight: bold;
    color: #0056b3;
}

/* ============================== TABLE ============================== */

.table-container {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    overflow-x: auto;
}

.report-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-bottom: 15px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
    white-space: nowrap;
}

th {
    background: #0056b3;
    color: white;
}

/* ============================== STATUS ============================== */

.status {
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status-present {
    background: #d4edda;
    color: #155724;
}

.status-late {
    background: #fff3cd;
    color: #856404;
}

.status-absent {
    background: #f8d7da;
    color: #721c24;
}

.status-other {
    background: #e2e3e5;
    color: #383d41;
}

/* ============================== RESPONSIVE ============================== */

@media(max-width:1100px) {

    .summary {
        grid-template-columns: repeat(3, 1fr);
    }

    .filters {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media(max-width:700px) {

    .sidebar {
        width: 150px;
        min-width: 150px;
    }

    .main {
        margin-left: 150px;
        width: calc(100% - 150px);
    }

    .summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .filters {
        grid-template-columns: 1fr;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

}

/* ============================== PRINT ============================== */

@media print {

    .sidebar,
    .topbar,
    .filters,
    .report-actions {
        display: none !important;
    }

    .main {
        margin: 0;
        width: 100%;
        padding: 0;
    }

    body {
        background: white;
    }

    .header,
    .card,
    .table-container {
        box-shadow: none;
    }

    .summary {
        grid-template-columns: repeat(5, 1fr);
    }

}
</style>
</head>

<body>

<div class="container">

<!-- ===================================================== SIDEBAR ===================================================== -->

<div class="sidebar">

    <!-- LOGO -->
    <div class="logo">

        <h2>
            <?php
            echo htmlspecialchars(
                $app['organization_name']
                ?? 'NTISHOR WEB ENTERPRISE'
            );
            ?>
        </h2>

        <p>Personnel Timesheet System</p>

    </div>

    <!-- PROFILE -->
    <div class="profile">

        <div class="avatar">

            <img src="<?php
                    echo htmlspecialchars(
                        $admin_profile_photo
                    );
                ?>"
                class="profile-small" alt="Profile" onerror="
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

    <!-- MENU -->
    <ul>
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <?php if ($admin_role === "Super Admin"): ?>
        <li><a href="manage_admins.php">Manage Admins</a></li>
        <?php endif; ?>
        <li><a href="manage_supervisors.php">Manage Supervisors</a></li>
        <li><a href="manage_staff.php">Manage Staff</a></li>
        <li class="active"><a href="admin_attendance_report.php">Attendance Report</a></li>
        <li><a href="admin_staff_hours_report.php">Staff Hours</a></li>
        <li><a href="admin_reports.php">Reports</a></li>
        <li><a href="admin_logout.php">Logout</a></li>
    </ul>

</div>

<div class="main">

    <div class="topbar">

        <div>
            <h1>Staff Attendance Report</h1>
            <p>Administrative attendance monitoring and reporting</p>
        </div>

        <div id="clock"></div>

    </div>

    <div class="page-content">

        <div class="header">
            <h2>Staff Attendance Report</h2>
            <p>View and filter attendance  for all staff members.</p>
        </div>

        <form method="GET" class="filters">

            <!-- FROM DATE -->
            <div class="form-group">
                <label>From Date</label>
                <input type="date" name="from_date" value="<?php
                        echo htmlspecialchars(
                            $from_date
                        );
                    ?>"
                >
            </div>

            <!-- TO DATE -->
            <div class="form-group">
                <label> To Date</label>
                <input type="date" name="to_date" value="<?php
                        echo htmlspecialchars(
                            $to_date
                        );
                    ?>"
                >

            </div>

            <!-- STAFF -->
            <div class="form-group">

                <label>Staff</label>
                <select name="staff_id">
                    <option value="">All Staff</option>

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
                            echo (
                                $staff_id ===
                                $staff['staff_id']
                            )
                            ? 'selected'
                            : '';
                            ?>
                        >

                            <?php

                            echo htmlspecialchars(

                                $staff['fullname']
                                .
                                ' ('
                                .
                                $staff['staff_id']
                                .
                                ')'

                            );

                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>

            <!-- DEPARTMENT -->
            <div class="form-group">

                <label>Department</label>
                <select name="department">
                    <option value="">All Departments</option>

                    <?php

                    while (
                        $dept =
                        $department_result->fetch_assoc()
                    ):

                    ?>

                        <option
                            value="<?php
                                echo htmlspecialchars(
                                    $dept['department']
                                );
                            ?>"
                            <?php
                            echo (
                                $department ===
                                $dept['department']
                            )
                            ? 'selected'
                            : '';
                            ?>
                        >

                            <?php

                            echo htmlspecialchars(
                                $dept['department']
                            );

                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>

            <!-- BUTTONS -->
            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">📊 Filter Report</button>
                <a href="admin_attendance_report.php" class="btn btn-secondary">Reset</a>
            </div>

        </form>

        <!-- ================================================= SUMMARY ================================================= -->
        <div class="summary">

            <div class="card">

                <h3>Total Attendance Records</h3>

                <div class="number">
                    <?php
                    echo $total_records;
                    ?>
                </div>

            </div>

            <div class="card">

                <h3>Present</h3>

                <div class="number">
                    <?php
                    echo $present;
                    ?>
                </div>

            </div>

            <div class="card">

                <h3>Late</h3>

                <div class="number">
                    <?php
                    echo $late;
                    ?>
                </div>

            </div>

           <div class="card">

                <h3>Absent</h3>

    <div class="number">
        <?php
        echo $calculated_absent;
        ?>
    </div>

</div>

         <div class="card">

                <h3>Attendance Rate</h3>

                <div class="number">
                    <?php
                    echo number_format(
                        $attendance_rate,
                        1
                    );
                    ?>%
                </div>

            </div>

        </div>

        <!-- ================================================= TABLE ================================================= -->

        <div class="table-container">

            <div class="report-actions">
                <button onclick="window.print()" class="btn btn-primary">🖨 Print</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Staff ID</th>
                        <th>Staff Name</th>
                        <th>Department</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    count($attendance_rows) > 0
                ): ?>

                    <?php
                    $counter = 1;
                    ?>

                    <?php foreach (
                        $attendance_rows
                        as $row
                    ): ?>


                        <?php

                        $status =
                            trim(
                                $row['status']
                                ?? ''
                            );


                        $status_class =
                            'status-other';

$status_lower = strtolower(
    trim($status)
);

$status_class = 'status-other';


if (
    $status_lower === 'present'
) {

    $status_class = 'status-present';

}

elseif (
    $status_lower === 'late' ||
    strpos($status_lower, 'late') !== false
) {

    $status_class = 'status-late';

}

elseif (
    $status_lower === 'absent' ||
    strpos($status_lower, 'absent') !== false
) {

    $status_class = 'status-absent';

}

                        /*
                         * Prefer clock_in
                         * and clock_out.
                         */

                        $time_in =
                            !empty(
                                $row['clock_in']
                            )

                            ? $row['clock_in']

                            : $row['time_in'];

                        $time_out =
                            !empty(
                                $row['clock_out']
                            )

                            ? $row['clock_out']

                            : $row['time_out'];

                        ?>

                        <tr>
                            <td>
                                <?php
                                echo $counter++;
                                ?>
                            </td>

                            <td>
                                <?php
                                echo date(
                                    'd-M-Y',
                                    strtotime(
                                        $row['date']
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
                                    ??
                                    'Unknown'
                                );
                                ?>
                                </strong>

                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row['department']
                                    ??
                                    'N/A'
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo $time_in
                                    ? date(
                                        'h:i A',
                                        strtotime(
                                            $time_in
                                        )
                                      )

                                    : '--';

                                ?>

                            </td>

                            <td>
                                <?php
                                echo $time_out
                                    ? date(
                                        'h:i A',
                                        strtotime(
                                            $time_out
                                        )
                                      )

                                    : '--';

                                ?>
                            </td>

                            <td>
                                <span class="status
                                    <?php
                                    echo $status_class;
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $status
                                        ?: 'Unknown'
                                    );
                                    ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" style=" text-align:center; padding:30px;">
                            No attendance records found for the selected period.
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

/* ===================================================== LIVE CLOCK ===================================================== */

function updateClock() {
    const clock =
        document.getElementById(
            "clock"
        );
    if (!clock) {
        return;
    }
    clock.innerHTML =
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