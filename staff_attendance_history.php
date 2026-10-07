```php
<?php

session_start();

include "config.php";

/* =========================================================
   STAFF AUTHENTICATION
========================================================= */

if (!isset($_SESSION['staff_id'])) {

    header("Location: staff_login.php");
    exit();

}

$staff_id = $_SESSION['staff_id'];

$fullname = $_SESSION['fullname'] ?? "Staff";


/* =========================================================
   FETCH STAFF INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT
        staff.*,
        supervisors.fullname AS supervisor_name
    FROM staff
    LEFT JOIN supervisors
        ON staff.supervisor_id = supervisors.supervisor_id
    WHERE staff.staff_id = ?
");

if (!$stmt) {

    die("Prepare failed: " . $conn->error);

}

$stmt->bind_param("s", $staff_id);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows == 0) {

    die("Staff member not found.");

}

$staff = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   STAFF NAME
========================================================= */

$fullname = $staff['fullname'];


/* =========================================================
   PROFILE PHOTO
========================================================= */

$profile_photo = "uploads/staff/default.png";


if (!empty($staff['profile_picture'])) {

    $photo_name = basename($staff['profile_picture']);

    /* New location */

    if (file_exists("uploads/staff/" . $photo_name)) {

        $profile_photo =
            "uploads/staff/" . $photo_name;

    }

    /* Old location */

    elseif (file_exists("uploads/" . $photo_name)) {

        $profile_photo =
            "uploads/" . $photo_name;

    }

}


/* =========================================================
   DATE FILTERS
========================================================= */

$date_from = $_GET['date_from'] ?? '';

$date_to = $_GET['date_to'] ?? '';


/* =========================================================
   BUILD ATTENDANCE QUERY
========================================================= */

$sql = "

    SELECT

        id,
        staff_id,
        date,
        time_in,
        time_out,
        clock_in,
        clock_out,
        status

    FROM attendance

    WHERE staff_id = ?

";


$params = [$staff_id];

$types = "s";


/* From date */

if (!empty($date_from)) {

    $sql .= " AND date >= ?";

    $params[] = $date_from;

    $types .= "s";

}


/* To date */

if (!empty($date_to)) {

    $sql .= " AND date <= ?";

    $params[] = $date_to;

    $types .= "s";

}


$sql .= "

    ORDER BY date DESC, id DESC

";


/* =========================================================
   PREPARE ATTENDANCE QUERY
========================================================= */

$stmt = mysqli_prepare($conn, $sql);


if (!$stmt) {

    die(
        "Database query error: "
        . mysqli_error($conn)
    );

}


mysqli_stmt_bind_param(
    $stmt,
    $types,
    ...$params
);


mysqli_stmt_execute($stmt);


$result = mysqli_stmt_get_result($stmt);


/* =========================================================
   ATTENDANCE RECORDS
========================================================= */

$records = [];

$total_days = 0;

$total_seconds = 0;


while ($row = mysqli_fetch_assoc($result)) {


    /* =====================================================
       CLOCK IN
    ===================================================== */

    $clock_in = !empty($row['clock_in'])
        ? $row['clock_in']
        : $row['time_in'];


    /* =====================================================
       CLOCK OUT
    ===================================================== */

    $clock_out = !empty($row['clock_out'])
        ? $row['clock_out']
        : $row['time_out'];


    $worked_seconds = 0;


    /* =====================================================
       CALCULATE HOURS
    ===================================================== */

    if (
        !empty($clock_in)
        &&
        !empty($clock_out)
    ) {

        $in_timestamp =
            strtotime($clock_in);

        $out_timestamp =
            strtotime($clock_out);


        if (
            $in_timestamp !== false
            &&
            $out_timestamp !== false
        ) {


            /* Handle overnight attendance */

            if (
                $out_timestamp
                <
                $in_timestamp
            ) {

                $out_timestamp +=
                    24 * 60 * 60;

            }


            $worked_seconds =
                $out_timestamp
                -
                $in_timestamp;


            $total_seconds +=
                $worked_seconds;

        }

    }


    /* =====================================================
       COUNT PRESENT DAYS
    ===================================================== */

    if (
        strtolower(
            trim($row['status'] ?? '')
        )
        === 'present'
    ) {

        $total_days++;

    }


    $row['display_clock_in'] =
        $clock_in;

    $row['display_clock_out'] =
        $clock_out;

    $row['worked_seconds'] =
        $worked_seconds;


    $records[] = $row;

}


$stmt->close();


/* =========================================================
   TOTAL HOURS
========================================================= */

$total_hours =
    floor(
        $total_seconds / 3600
    );


$total_minutes =
    floor(
        ($total_seconds % 3600) / 60
    );


$total_hours_display =
    $total_hours
    . "h "
    . $total_minutes
    . "m";


/* =========================================================
   FORMAT TIME
========================================================= */

function formatTime($time)
{

    if (empty($time)) {

        return "--";

    }


    $timestamp =
        strtotime($time);


    if ($timestamp === false) {

        return htmlspecialchars($time);

    }


    return date(
        "h:i A",
        $timestamp
    );

}


/* =========================================================
   FORMAT HOURS
========================================================= */

function formatHours($seconds)
{

    if ($seconds <= 0) {

        return "--";

    }


    $hours =
        floor(
            $seconds / 3600
        );


    $minutes =
        floor(
            ($seconds % 3600) / 60
        );


    return $hours
        . "h "
        . $minutes
        . "m";

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Attendance History</title>

<link
    rel="stylesheet"
    href="styles.css"
>


<style>


/* =========================================================
   ATTENDANCE PAGE
========================================================= */

.attendance-page {

    padding: 25px;

}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    margin-bottom: 25px;

}


.page-header h1 {

    margin: 0;

    font-size: 28px;

    color: #1f2937;

}


.page-header p {

    margin-top: 7px;

    color: #666;

}


/* =========================================================
   SUMMARY CARDS
========================================================= */

.summary-cards {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;

    margin-bottom: 25px;

}


.summary-card {

    background: #ffffff;

    border-radius: 10px;

    padding: 20px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.06);

    border:
        1px solid #eeeeee;

}


.summary-card h4 {

    margin:
        0 0 10px;

    color: #666;

    font-size: 14px;

}


.summary-card h2 {

    margin: 0;

    font-size: 27px;

    color: #1f2937;

}


/* =========================================================
   FILTER
========================================================= */

.filter-box {

    background: #ffffff;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 25px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.06);

    border:
        1px solid #eeeeee;

}


.filter-box h3 {

    margin:
        0 0 18px;

    color: #1f2937;

}


.filter-form {

    display: grid;

    grid-template-columns:
        1fr 1fr auto auto;

    gap: 15px;

    align-items: end;

}


.filter-group {

    display: flex;

    flex-direction: column;

}


.filter-group label {

    font-weight: 600;

    margin-bottom: 7px;

    font-size: 13px;

}


.filter-group input {

    padding: 11px;

    border:
        1px solid #d1d5db;

    border-radius: 6px;

    outline: none;

}


.filter-group input:focus {

    border-color: #2563eb;

}


/* =========================================================
   BUTTONS
========================================================= */

.filter-btn {

    border: none;

    background: #2563eb;

    color: white;

    padding: 11px 20px;

    border-radius: 6px;

    cursor: pointer;

}


.filter-btn:hover {

    background: #1d4ed8;

}


.reset-btn {

    text-decoration: none;

    background: #6b7280;

    color: white;

    padding: 11px 20px;

    border-radius: 6px;

    text-align: center;

}


.reset-btn:hover {

    background: #4b5563;

}


/* =========================================================
   HISTORY BOX
========================================================= */

.history-box {

    background: #ffffff;

    border-radius: 10px;

    padding: 20px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.06);

    border:
        1px solid #eeeeee;

}


.history-box h2 {

    margin:
        0 0 20px;

    color: #1f2937;

}


.table-wrapper {

    overflow-x: auto;

}


.attendance-table {

    width: 100%;

    border-collapse:
        collapse;

}


.attendance-table th,
.attendance-table td {

    padding:
        13px 12px;

    border-bottom:
        1px solid #eeeeee;

    text-align: left;

}


.attendance-table th {

    background: #f8fafc;

    font-weight: 600;

    color: #374151;

}


.attendance-table td {

    color: #4b5563;

}


.attendance-table tr:hover {

    background: #fafafa;

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding:
        6px 11px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 600;

}


.status-present {

    background: #d4edda;

    color: #155724;

}


.status-absent {

    background: #f8d7da;

    color: #721c24;

}


.status-late {

    background: #fff3cd;

    color: #856404;

}


.status-default {

    background: #e2e3e5;

    color: #383d41;

}


/* =========================================================
   NO RECORDS
========================================================= */

.no-records {

    text-align: center;

    padding: 50px 20px;

    color: #777;

}


.no-records h3 {

    margin:
        0 0 8px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .sidebar {

        width: 220px;

    }

    .main {

        margin-left: 220px;

        width:
            calc(100% - 220px);

    }

    .summary-cards {

        grid-template-columns:
            1fr;

    }

    .filter-form {

        grid-template-columns:
            1fr 1fr;

    }

}


@media (max-width: 650px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

        min-height: auto;

        border-right: none;

        border-bottom:
            1px solid #e5e7eb;

    }

    .main {

        margin-left: 0;

        width: 100%;

    }

    .filter-form {

        grid-template-columns:
            1fr;

    }

    .attendance-page {

        padding: 15px;

    }

    .page-header {

        align-items: flex-start;

    }

}

</style>

</head>


<body>


<div class="container">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

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


            <p>

                Personnel Timesheet System

            </p>

        </div>


        <!-- PROFILE -->

        <div class="profile">


            <div class="avatar">

                <img

                    src="<?php

                    echo htmlspecialchars(

                        $profile_photo

                    );

                    ?>"

                    class="profile-small"

                    alt="Profile Photo"

                    onerror="this.onerror=null;this.src='uploads/staff/default.png';"

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

                Staff

            </p>


        </div>


        <!-- NAVIGATION -->

        <ul>


            <li>

                <a href="staff_dashboard.php">

                    Dashboard

                </a>

            </li>


            <li>

                <a href="attendance.php">

                    Clock In/Out

                </a>

            </li>


            <li class="active">

                <a href="staff_attendance_history.php">

                    Attendance History

                </a>

            </li>


            <li>

                <a href="staff_submit_task_report.php">

                    My Task

                </a>

            </li>


            <li>

                <a href="task_history.php">

                    Task Submission History

                </a>

            </li>


            <li>

                <a href="staff_notifications.php">

                    Notifications

                </a>

            </li>


            <li>

                <a href="work_schedule.php">

                    My Schedule

                </a>

            </li>


            <li>

                <a href="staff_profile.php">

                    Profile

                </a>

            </li>


            <li>

                <a href="staff_logout.php">

                    Logout

                </a>

            </li>


        </ul>


    </div>


    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <div class="main">


        <div class="attendance-page">


            <!-- PAGE HEADER -->

            <div class="page-header">

                <div>

                    <h1>

                        Attendance History

                    </h1>


                    <p>

                        View your attendance records
                        and hours worked.

                    </p>

                </div>

            </div>


            <!-- SUMMARY CARDS -->

            <div class="summary-cards">


                <div class="summary-card">

                    <h4>

                        Total Attendance Days

                    </h4>


                    <h2>

                        <?php

                        echo $total_days;

                        ?>

                    </h2>

                </div>


                <div class="summary-card">

                    <h4>

                        Total Hours Worked

                    </h4>


                    <h2>

                        <?php

                        echo $total_hours_display;

                        ?>

                    </h2>

                </div>


                <div class="summary-card">

                    <h4>

                        Records Found

                    </h4>


                    <h2>

                        <?php

                        echo count($records);

                        ?>

                    </h2>

                </div>


            </div>


            <!-- FILTER -->

            <div class="filter-box">


                <h3>

                    Filter Attendance

                </h3>


                <form
                    method="GET"
                    class="filter-form"
                >


                    <div class="filter-group">

                        <label for="date_from">

                            From Date

                        </label>


                        <input

                            type="date"

                            id="date_from"

                            name="date_from"

                            value="<?php

                            echo htmlspecialchars(

                                $date_from

                            );

                            ?>"

                        >

                    </div>


                    <div class="filter-group">

                        <label for="date_to">

                            To Date

                        </label>


                        <input

                            type="date"

                            id="date_to"

                            name="date_to"

                            value="<?php

                            echo htmlspecialchars(

                                $date_to

                            );

                            ?>"

                        >

                    </div>


                    <button
                        type="submit"
                        class="filter-btn"
                    >

                        Filter

                    </button>


                    <a

                        href="staff_attendance_history.php"

                        class="reset-btn"

                    >

                        Reset

                    </a>


                </form>

            </div>


            <!-- ATTENDANCE HISTORY -->

            <div class="history-box">


                <h2>

                    My Attendance History

                </h2>


                <div class="table-wrapper">


                    <?php

                    if (
                        count($records) > 0
                    ) {

                    ?>


                    <table
                        class="attendance-table"
                    >


                        <thead>

                            <tr>

                                <th>
                                    S/N
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Clock In
                                </th>

                                <th>
                                    Clock Out
                                </th>

                                <th>
                                    Hours Worked
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php

                        $sn = 1;


                        foreach (
                            $records
                            as $row
                        ) {


                            $status =
                                $row['status']
                                ?? 'Unknown';


                            $status_class =
                                'status-default';


                            if (
                                strtolower(
                                    $status
                                )
                                === 'present'
                            ) {

                                $status_class =
                                    'status-present';

                            }


                            elseif (
                                strtolower(
                                    $status
                                )
                                === 'absent'
                            ) {

                                $status_class =
                                    'status-absent';

                            }


                            elseif (
                                strtolower(
                                    $status
                                )
                                === 'late'
                            ) {

                                $status_class =
                                    'status-late';

                            }

                        ?>


                        <tr>


                            <td>

                                <?php

                                echo $sn++;

                                ?>

                            </td>


                            <td>

                                <?php

                                echo date(

                                    "d M Y",

                                    strtotime(

                                        $row['date']

                                    )

                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo formatTime(

                                    $row[
                                        'display_clock_in'
                                    ]

                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo formatTime(

                                    $row[
                                        'display_clock_out'
                                    ]

                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo formatHours(

                                    $row[
                                        'worked_seconds'
                                    ]

                                );

                                ?>

                            </td>


                            <td>


                                <span

                                    class="status
                                    <?php

                                    echo $status_class;

                                    ?>"

                                >

                                    <?php

                                    echo htmlspecialchars(

                                        $status

                                    );

                                    ?>

                                </span>


                            </td>


                        </tr>


                        <?php

                        }

                        ?>


                        </tbody>


                    </table>


                    <?php

                    }

                    else {

                    ?>


                    <div class="no-records">


                        <h3>

                            No Attendance Records Found

                        </h3>


                        <p>

                            You do not have any attendance
                            records for the selected date range.

                        </p>


                    </div>


                    <?php

                    }

                    ?>


                </div>


            </div>


        </div>


    </div>


</div>


</body>

</html>
```
