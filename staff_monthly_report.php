```php
<?php
session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$fullname = $_SESSION['fullname'] ?? 'Supervisor';

/* ==================================
   SUPERVISOR PROFILE PHOTO
================================== */

$stmt = $conn->prepare("
    SELECT profile_photo
    FROM supervisors
    WHERE supervisor_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$result = $stmt->get_result();

$supervisor_data = $result->fetch_assoc();

$stmt->close();


$profile_photo = "uploads/supervisors/default.png";


if (
    $supervisor_data &&
    !empty($supervisor_data['profile_photo'])
) {

    $photo = basename(
        $supervisor_data['profile_photo']
    );

    $photo_path =
        "uploads/supervisors/" . $photo;

    if (file_exists($photo_path)) {

        $profile_photo = $photo_path;

    }

}

/*
|--------------------------------------------------------------------------
| SELECTED MONTH
|--------------------------------------------------------------------------
*/

$selected_month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = date('Y-m');
}

$month_start = $selected_month . '-01';
$month_end   = date('Y-m-t', strtotime($month_start));

$show_report = isset($_GET['generate']);


/*
|--------------------------------------------------------------------------
| SELECTED STAFF
|--------------------------------------------------------------------------
|
| "all" = all active staff under this supervisor
| otherwise = one selected staff member
|--------------------------------------------------------------------------
*/

$selected_staff = $_GET['staff_id'] ?? 'all';


/*
|--------------------------------------------------------------------------
| FETCH STAFF UNDER SUPERVISOR
|--------------------------------------------------------------------------
*/

$staff_list = [];

$stmt = $conn->prepare("
    SELECT
        staff_id,
        fullname,
        department,
        position
    FROM staff
    WHERE supervisor_id = ?
    AND status = 'Active'
    ORDER BY fullname ASC
");

if (!$stmt) {
    die("Staff query failed: " . $conn->error);
}

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $staff_list[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function formatTime($time)
{
    if (empty($time)) {
        return '-';
    }

    return date("h:i A", strtotime($time));
}


function calculateHours($clock_in, $clock_out)
{
    if (empty($clock_in) || empty($clock_out)) {
        return 0;
    }

    $start = strtotime($clock_in);
    $end   = strtotime($clock_out);

    if ($end <= $start) {
        return 0;
    }

    return ($end - $start) / 3600;
}


function getDayName($date)
{
    return date("l", strtotime($date));
}


/*
|--------------------------------------------------------------------------
| REPORT DATA
|--------------------------------------------------------------------------
*/

$report_data = [];

$total_working_days = 0;
$total_present = 0;
$total_absent = 0;
$total_hours = 0;
$total_overtime = 0;
$total_days_with_hours = 0;

$report_staff_count = 0;


/*
|--------------------------------------------------------------------------
| GENERATE REPORT
|--------------------------------------------------------------------------
*/

if ($show_report) {

    /*
    |--------------------------------------------------------------------------
    | BUILD STAFF QUERY
    |--------------------------------------------------------------------------
    */

    if ($selected_staff === 'all') {

        $staff_query = "
            SELECT
                staff_id,
                fullname,
                department,
                position
            FROM staff
            WHERE supervisor_id = ?
            AND status = 'Active'
            ORDER BY fullname ASC
        ";

        $stmt = $conn->prepare($staff_query);

        if (!$stmt) {
            die("Staff selection query failed: " . $conn->error);
        }

        $stmt->bind_param("s", $supervisor_id);

    } else {

        $staff_query = "
            SELECT
                staff_id,
                fullname,
                department,
                position
            FROM staff
            WHERE supervisor_id = ?
            AND staff_id = ?
            AND status = 'Active'
            LIMIT 1
        ";

        $stmt = $conn->prepare($staff_query);

        if (!$stmt) {
            die("Staff selection query failed: " . $conn->error);
        }

        $stmt->bind_param(
            "ss",
            $supervisor_id,
            $selected_staff
        );
    }

    $stmt->execute();

    $staff_result = $stmt->get_result();

    while ($staff = $staff_result->fetch_assoc()) {

        $report_staff_count++;

        /*
        |--------------------------------------------------------------------------
        | DETERMINE WORKING DAYS FROM WORK SCHEDULE
        |--------------------------------------------------------------------------
        */

        $working_days = [];

        $schedule_stmt = $conn->prepare("
            SELECT
                day_name,
                start_time,
                end_time,
                status
            FROM work_schedule
            WHERE staff_id = ?
            AND supervisor_id = ?
        ");

        if (!$schedule_stmt) {
            die("Schedule query failed: " . $conn->error);
        }

        $schedule_stmt->bind_param(
            "ss",
            $staff['staff_id'],
            $supervisor_id
        );

        $schedule_stmt->execute();

        $schedule_result = $schedule_stmt->get_result();

        while ($schedule = $schedule_result->fetch_assoc()) {

            if ($schedule['status'] === 'Working') {

                $working_days[$schedule['day_name']] = [
                    'start_time' => $schedule['start_time'],
                    'end_time'   => $schedule['end_time']
                ];
            }
        }

        $schedule_stmt->close();


        /*
        |--------------------------------------------------------------------------
        | DEFAULT WORKING DAYS
        |--------------------------------------------------------------------------
        |
        | If no schedule exists for the staff member, use Monday-Friday.
        |
        */

        if (empty($working_days)) {

            $working_days = [
                'Monday' => [
                    'start_time' => '08:00:00',
                    'end_time'   => '17:00:00'
                ],
                'Tuesday' => [
                    'start_time' => '08:00:00',
                    'end_time'   => '17:00:00'
                ],
                'Wednesday' => [
                    'start_time' => '08:00:00',
                    'end_time'   => '17:00:00'
                ],
                'Thursday' => [
                    'start_time' => '08:00:00',
                    'end_time'   => '17:00:00'
                ],
                'Friday' => [
                    'start_time' => '08:00:00',
                    'end_time'   => '17:00:00'
                ]
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | FETCH TIMESHEET RECORDS FOR MONTH
        |--------------------------------------------------------------------------
        */

        $timesheets = [];

        $timesheet_stmt = $conn->prepare("
            SELECT
                work_date,
                clock_in,
                clock_out,
                hours_worked,
                overtime_hours,
                status,
                late_arrival,
                early_departure
            FROM timesheets
            WHERE staff_id = ?
            AND work_date BETWEEN ? AND ?
            ORDER BY work_date ASC
        ");

        if (!$timesheet_stmt) {
            die("Timesheet query failed: " . $conn->error);
        }

        $timesheet_stmt->bind_param(
            "sss",
            $staff['staff_id'],
            $month_start,
            $month_end
        );

        $timesheet_stmt->execute();

        $timesheet_result = $timesheet_stmt->get_result();

        while ($timesheet = $timesheet_result->fetch_assoc()) {

            $timesheets[$timesheet['work_date']] = $timesheet;
        }

        $timesheet_stmt->close();


        /*
        |--------------------------------------------------------------------------
        | GENERATE EVERY DATE IN THE MONTH
        |--------------------------------------------------------------------------
        */

        $current_date = new DateTime($month_start);
        $end_date = new DateTime($month_end);

        while ($current_date <= $end_date) {

            $date_string = $current_date->format('Y-m-d');
            $day_name = $current_date->format('l');

            /*
            |--------------------------------------------------------------------------
            | ONLY SCHEDULED WORKING DAYS
            |--------------------------------------------------------------------------
            */

            if (isset($working_days[$day_name])) {

                $total_working_days++;

                $record = $timesheets[$date_string] ?? null;

                /*
                |--------------------------------------------------------------------------
                | DEFAULT ABSENT RECORD
                |--------------------------------------------------------------------------
                */

                if (!$record) {

                    $report_data[] = [
                        'staff_id' => $staff['staff_id'],
                        'fullname' => $staff['fullname'],
                        'department' => $staff['department'],
                        'position' => $staff['position'],
                        'date' => $date_string,
                        'day' => $day_name,
                        'clock_in' => null,
                        'clock_out' => null,
                        'hours_worked' => 0,
                        'overtime_hours' => 0,
                        'status' => 'Absent',
                        'late_arrival' => 0,
                        'early_departure' => 0
                    ];

                    $total_absent++;

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CALCULATE HOURS
                    |--------------------------------------------------------------------------
                    */

                    $hours = (float)$record['hours_worked'];

                    if ($hours <= 0) {

                        $hours = calculateHours(
                            $record['clock_in'],
                            $record['clock_out']
                        );
                    }

                    $overtime = (float)$record['overtime_hours'];

                    /*
                    |--------------------------------------------------------------------------
                    | DETERMINE STATUS
                    |--------------------------------------------------------------------------
                    */

                    $status = trim(
                        (string)$record['status']
                    );

                    if ($status === '') {
                        $status = 'Present';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | A RECORD WITH CLOCK-IN IS PRESENT
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !empty($record['clock_in']) ||
                        !empty($record['clock_out']) ||
                        $hours > 0
                    ) {

                        $total_present++;

                        if ($hours > 0) {
                            $total_days_with_hours++;
                        }

                        $total_hours += $hours;
                        $total_overtime += $overtime;

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | No actual attendance
                        |--------------------------------------------------------------------------
                        */

                        $status = 'Absent';

                        $total_absent++;
                    }

                    $report_data[] = [
                        'staff_id' => $staff['staff_id'],
                        'fullname' => $staff['fullname'],
                        'department' => $staff['department'],
                        'position' => $staff['position'],
                        'date' => $date_string,
                        'day' => $day_name,
                        'clock_in' => $record['clock_in'],
                        'clock_out' => $record['clock_out'],
                        'hours_worked' => $hours,
                        'overtime_hours' => $overtime,
                        'status' => $status,
                        'late_arrival' => (int)$record['late_arrival'],
                        'early_departure' => (int)$record['early_departure']
                    ];
                }
            }

            $current_date->modify('+1 day');
        }
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| ATTENDANCE PERCENTAGE
|--------------------------------------------------------------------------
*/

$attendance_percentage = 0;

if ($total_working_days > 0) {
    $attendance_percentage =
        ($total_present / $total_working_days) * 100;
}


/*
|--------------------------------------------------------------------------
| AVERAGE HOURS
|--------------------------------------------------------------------------
*/

$average_hours = 0;

if ($total_days_with_hours > 0) {

    $average_hours =
        $total_hours / $total_days_with_hours;
}


/*
|--------------------------------------------------------------------------
| MONTH DISPLAY
|--------------------------------------------------------------------------
*/

$month_display = date(
    "F Y",
    strtotime($month_start)
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Staff Monthly Report</title>

<link rel="stylesheet" href="styles.css">

<style>

.report-container {
    padding: 25px;
}

.report-filter {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

.report-filter h2 {
    margin-top: 0;
}

.filter-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr auto;
    gap: 15px;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 6px;
}

.form-group select,
.form-group input {
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}

.generate-btn {
    padding: 11px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    background: #007bff;
    color: #fff;
    font-weight: 600;
}

.generate-btn:hover {
    opacity: .9;
}

.report-result {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

.report-header {
    text-align: center;
    border-bottom: 2px solid #222;
    padding-bottom: 20px;
    margin-bottom: 25px;
}

.report-header h2 {
    margin: 5px 0;
}

.report-header h3 {
    margin: 8px 0;
}

.report-header p {
    margin: 5px 0;
}

.staff-heading {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.staff-heading h3 {
    margin: 0 0 6px;
}

.staff-heading p {
    margin: 3px 0;
}

.report-table-wrapper {
    overflow-x: auto;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.report-table th,
.report-table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}

.report-table th {
    background: #f2f2f2;
    font-weight: 700;
}

.report-table td.hours,
.report-table td.overtime {
    text-align: right;
}

.status-present {
    color: #198754;
    font-weight: 600;
}

.status-absent {
    color: #dc3545;
    font-weight: 600;
}

.status-pending {
    color: #856404;
    font-weight: 600;
}

.summary {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
    margin-top: 25px;
}

.summary-card {
    padding: 15px;
    background: #f5f5f5;
    border-radius: 7px;
    text-align: center;
}

.summary-card strong {
    display: block;
    font-size: 20px;
    margin-bottom: 5px;
}

.print-info {
    border-top: 1px solid #ddd;
    margin-top: 30px;
    padding-top: 15px;
    font-size: 13px;
    color: #555;
}

.print-info p {
    margin: 4px 0;
}

.report-actions {
    margin-top: 25px;
    display: flex;
    gap: 10px;
}

.report-actions button,
.report-actions a {
    padding: 11px 20px;
    border: none;
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
    text-decoration: none;
    font-weight: 600;
}

.print-btn {
    background: #333;
}

.pdf-btn {
    background: #d9534f;
}

.excel-btn {
    background: #198754;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #777;
}


/* =========================================================
   PRINT
========================================================= */

@media print {

    body {
        background: #fff;
    }

    .sidebar,
    .page-header,
    .report-filter,
    .report-actions {
        display: none !important;
    }

    .main {
        width: 100%;
        margin: 0;
        padding: 0;
    }

    .report-container {
        padding: 0;
    }

    .report-result {
        box-shadow: none;
        padding: 0;
    }

    .report-table {
        min-width: 0;
    }

    .report-table th {
        background: #eee !important;
        color: #000 !important;
    }

    .staff-heading {
        background: #f5f5f5 !important;
    }

    .print-info {
        margin-top: 25px;
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width: 1000px) {

    .summary {
        grid-template-columns: repeat(3, 1fr);
    }

}

@media(max-width: 768px) {

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .summary {
        grid-template-columns: 1fr 1fr;
    }

}

@media(max-width: 480px) {

    .summary {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="container">


<!-- =====================================================
     SIDEBAR
===================================================== -->

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
    src="<?php echo htmlspecialchars($profile_photo); ?>"
    class="profile-small"
    alt="Profile"
    onerror="this.src='uploads/supervisors/default.png';"
>

        </div>

        <h4>
            <?php
            echo htmlspecialchars($fullname);
            ?>
        </h4>

        <p>
            Supervisor
        </p>

    </div>


    <ul>

        <li>
            <a href="supervisor_dashboard.php">
                Dashboard
            </a>
        </li>

        <li>
            <a href="approvals.php">
                Approvals
            </a>
        </li>

        <li>
            <a href="supervisor_assign_task.php">
                Assign Task
            </a>
        </li>

        <li>
            <a href="task_report_approvals.php">
                Task Reports
            </a>
        </li>

        <li class="active">
            <a href="reports.php">
                Reports
            </a>
        </li>

        <li>
            <a href="supervisor_notifications.php">
                Notifications
            </a>
        </li>

        <li>
            <a href="supervisor_profile.php">
                My Profile
            </a>
        </li>

        <li>
            <a href="supervisor_logout.php">
                Logout
            </a>
        </li>

    </ul>

</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


    <div class="page-header">

        <div>

            <h2>
                Staff Monthly Report
            </h2>

        </div>

        <div id="clock"></div>

    </div>


    <div class="report-container">


        <!-- =================================================
             FILTER
        ================================================== -->

        <div class="report-filter">

            <h2>
                Generate Staff Monthly Report
            </h2>

            <form method="GET">

                <div class="filter-grid">


                    <!-- STAFF -->

                    <div class="form-group">

                        <label>
                            Staff
                        </label>

                        <select
                            name="staff_id"
                            required
                        >

                            <option
                                value="all"
                                <?php
                                echo $selected_staff === 'all'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                All Staff
                            </option>

                            <?php foreach (
                                $staff_list
                                as $staff_option
                            ): ?>

                                <option
                                    value="<?php
                                    echo htmlspecialchars(
                                        $staff_option['staff_id']
                                    );
                                    ?>"
                                    <?php
                                    echo $selected_staff ===
                                        $staff_option['staff_id']
                                        ? 'selected'
                                        : '';
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $staff_option['fullname']
                                    );
                                    ?>

                                    -
                                    <?php
                                    echo htmlspecialchars(
                                        $staff_option['staff_id']
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- MONTH -->

                    <div class="form-group">

                        <label>
                            Month
                        </label>

                        <input
                            type="month"
                            name="month"
                            value="<?php
                            echo htmlspecialchars(
                                $selected_month
                            );
                            ?>"
                            required
                        >

                    </div>


                    <!-- GENERATE -->

                    <div>

                        <input
                            type="hidden"
                            name="generate"
                            value="1"
                        >

                        <button
                            type="submit"
                            class="generate-btn"
                        >
                            Generate Report
                        </button>

                    </div>

                </div>

            </form>

        </div>


        <?php if ($show_report): ?>


        <!-- =================================================
             REPORT PREVIEW
        ================================================== -->

        <div
            class="report-result"
            id="printArea"
        >


            <!-- HEADER -->

            <div class="report-header">

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $app['organization_name']
                    );
                    ?>
                </h2>

                <h3>
                    STAFF MONTHLY REPORT
                </h3>

                <p>
                    Month:
                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $month_display
                        );
                        ?>
                    </strong>
                </p>

                <p>
                    Staff:
                    <strong>
                        <?php
                        echo $selected_staff === 'all'
                            ? 'All Staff'
                            : 'Selected Staff';
                        ?>
                    </strong>
                </p>

            </div>


            <?php if (
                $report_staff_count > 0 &&
                count($report_data) > 0
            ): ?>


                <!-- =================================================
                     STAFF DAILY RECORDS
                ================================================== -->

                <?php

                /*
                |--------------------------------------------------------------------------
                | GROUP DATA BY STAFF
                |--------------------------------------------------------------------------
                */

                $grouped_data = [];

                foreach (
                    $report_data
                    as $row
                ) {

                    $grouped_data[
                        $row['staff_id']
                    ][] = $row;
                }

                ?>


                <?php foreach (
                    $grouped_data
                    as $staff_id_key =>
                    $staff_records
                ): ?>


                    <?php

                    $first_record =
                        $staff_records[0];

                    $staff_total_hours = 0;
                    $staff_total_overtime = 0;
                    $staff_present = 0;
                    $staff_absent = 0;
                    $staff_working_days = count(
                        $staff_records
                    );
                    $staff_days_with_hours = 0;

                    foreach (
                        $staff_records
                        as $staff_record
                    ) {

                        $staff_total_hours +=
                            (float)$staff_record[
                                'hours_worked'
                            ];

                        $staff_total_overtime +=
                            (float)$staff_record[
                                'overtime_hours'
                            ];

                        if (
                            strtolower(
                                $staff_record['status']
                            ) === 'absent'
                        ) {

                            $staff_absent++;

                        } else {

                            $staff_present++;

                            if (
                                (float)$staff_record[
                                    'hours_worked'
                                ] > 0
                            ) {

                                $staff_days_with_hours++;
                            }
                        }
                    }

                    $staff_average_hours = 0;

                    if (
                        $staff_days_with_hours > 0
                    ) {

                        $staff_average_hours =
                            $staff_total_hours /
                            $staff_days_with_hours;
                    }

                    ?>


                    <!-- STAFF INFORMATION -->

                    <div class="staff-heading">

                        <h3>
                            <?php
                            echo htmlspecialchars(
                                $first_record['fullname']
                            );
                            ?>
                        </h3>

                        <p>
                            <strong>
                                Staff ID:
                            </strong>

                            <?php
                            echo htmlspecialchars(
                                $first_record['staff_id']
                            );
                            ?>
                        </p>

                        <p>
                            <strong>
                                Department:
                            </strong>

                            <?php
                            echo htmlspecialchars(
                                $first_record['department']
                            );
                            ?>
                        </p>

                        <p>
                            <strong>
                                Position:
                            </strong>

                            <?php
                            echo htmlspecialchars(
                                $first_record['position']
                            );
                            ?>
                        </p>

                    </div>


                    <!-- DAILY TABLE -->

                    <div class="report-table-wrapper">

                        <table class="report-table">

                            <thead>

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Day
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
                                        Overtime
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach (
                                $staff_records
                                as $row
                            ): ?>

                                <?php

                                $status_class =
                                    strtolower(
                                        $row['status']
                                    );

                                ?>

                                <tr>

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
                                        echo htmlspecialchars(
                                            $row['day']
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        echo formatTime(
                                            $row['clock_in']
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        echo formatTime(
                                            $row['clock_out']
                                        );
                                        ?>

                                    </td>

                                    <td class="hours">

                                        <?php
                                        echo number_format(
                                            (float)$row[
                                                'hours_worked'
                                            ],
                                            2
                                        );
                                        ?>
                                        h

                                    </td>

                                    <td class="overtime">

                                        <?php
                                        echo number_format(
                                            (float)$row[
                                                'overtime_hours'
                                            ],
                                            2
                                        );
                                        ?>
                                        h

                                    </td>

                                    <td>

                                        <span class="<?php
                                        if (
                                            $status_class ===
                                            'present'
                                        ) {
                                            echo 'status-present';
                                        } elseif (
                                            $status_class ===
                                            'absent'
                                        ) {
                                            echo 'status-absent';
                                        } elseif (
                                            $status_class ===
                                            'pending'
                                        ) {
                                            echo 'status-pending';
                                        }
                                        ?>">

                                            <?php
                                            echo htmlspecialchars(
                                                $row['status']
                                            );
                                            ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- STAFF SUMMARY -->

                    <div class="summary">

                        <div class="summary-card">

                            <strong>
                                <?php
                                echo $staff_working_days;
                                ?>
                            </strong>

                            Working Days

                        </div>


                        <div class="summary-card">

                            <strong>
                                <?php
                                echo $staff_present;
                                ?>
                            </strong>

                            Present

                        </div>


                        <div class="summary-card">

                            <strong>
                                <?php
                                echo $staff_absent;
                                ?>
                            </strong>

                            Absent

                        </div>


                        <div class="summary-card">

                            <strong>

                                <?php
                                echo number_format(
                                    $staff_total_hours,
                                    2
                                );
                                ?>

                                h

                            </strong>

                            Total Hours

                        </div>


                        <div class="summary-card">

                            <strong>

                                <?php
                                echo number_format(
                                    $staff_total_overtime,
                                    2
                                );
                                ?>

                                h

                            </strong>

                            Total Overtime

                        </div>


                        <div class="summary-card">

                            <strong>

                                <?php
                                echo number_format(
                                    $staff_average_hours,
                                    2
                                );
                                ?>

                                h

                            </strong>

                            Average Hours/Day

                        </div>

                    </div>


                    <br>


                <?php endforeach; ?>


                <!-- =================================================
                     PRINT INFORMATION
                ================================================== -->

                <div class="print-info">

                    <p>
                        <strong>
                            Printed By:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            $fullname
                        );
                        ?>
                    </p>

                    <p>
                        <strong>
                            Supervisor ID:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            $supervisor_id
                        );
                        ?>
                    </p>

                    <p>
                        <strong>
                            Date:
                        </strong>

                        <?php
                        echo date("d M Y");
                        ?>
                    </p>

                    <p>
                        <strong>
                            Time:
                        </strong>

                        <?php
                        echo date("h:i A");
                        ?>
                    </p>

                </div>


                <!-- =================================================
                     ACTION BUTTONS
                ================================================== -->

                <div class="report-actions">

                    <button
                        type="button"
                        onclick="window.print()"
                        class="print-btn"
                    >
                        🖨 Print Preview
                    </button>

                    <a
                        href="staff_monthly_report_pdf.php?staff_id=<?php echo urlencode($selected_staff); ?>&month=<?php echo urlencode($selected_month); ?>"
                        class="pdf-btn"
                    >
                        📄 Download PDF
                    </a>

                    <a
                        href="staff_monthly_report_excel.php?staff_id=<?php echo urlencode($selected_staff); ?>&month=<?php echo urlencode($selected_month); ?>"
                        class="excel-btn"
                    >
                        📊 Download Excel
                    </a>

                </div>


            <?php else: ?>


                <div class="no-data">

                    <h3>
                        No Staff Records Found
                    </h3>

                    <p>
                        No active staff members under this
                        supervisor were found for the selected
                        month.
                    </p>

                </div>


            <?php endif; ?>


        </div>


        <?php endif; ?>


    </div>

</div>


<script>

function updateClock()
{
    const now = new Date();

    const clock =
        document.getElementById("clock");

    if (clock) {

        clock.innerHTML =
            "● " +
            now.toLocaleTimeString(
                "en-GB",
                {
                    hour12: false
                }
            );
    }
}

updateClock();

setInterval(
    updateClock,
    1000
);

</script>

</body>

</html>
```
