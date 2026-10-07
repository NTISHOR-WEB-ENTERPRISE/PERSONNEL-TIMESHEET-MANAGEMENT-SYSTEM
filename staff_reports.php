<?php
session_start();
include "config.php";

if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

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

if ($result->num_rows === 0) {
    die("Staff member not found.");
}

$staff = $result->fetch_assoc();
$stmt->close();

$fullname = $staff['fullname'];
$department = $staff['department'] ?? '';
$supervisor_name = $staff['supervisor_name'] ?? 'Not Assigned';


/* =========================================================
   REPORT SETTINGS
========================================================= */

$report_type = $_GET['report_type'] ?? 'task';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date'] ?? date('Y-m-d');

$show_report = isset($_GET['generate']);


/* =========================================================
   VALIDATE REPORT TYPE
========================================================= */

$allowed_reports = [
    'task',
    'attendance',
    'overtime'
];

if (!in_array($report_type, $allowed_reports)) {
    $report_type = 'task';
}


/* =========================================================
   REPORT DATA
========================================================= */

$report_data = [];
$total_hours = 0;
$total_days = 0;
$total_overtime = 0;
$report_title = '';


/* =========================================================
   TASK REPORT
========================================================= */

if ($show_report && $report_type === 'task') {

    $report_title = "Task Report";

    $stmt = $conn->prepare("
        SELECT
            id,
            report_date,
            start_time,
            end_time,
            hours_worked,
            tasks_completed,
            challenges,
            remarks,
            status,
            supervisor_comment,
            created_at
        FROM task_reports
        WHERE staff_id = ?
        AND report_date BETWEEN ? AND ?
        ORDER BY report_date DESC, created_at DESC
    ");

    if (!$stmt) {
        die("Task report query failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sss",
        $staff_id,
        $from_date,
        $to_date
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $report_data[] = $row;

        $total_hours += (float)$row['hours_worked'];
    }

    $stmt->close();
}


/* =========================================================
   ATTENDANCE REPORT
========================================================= */

if ($show_report && $report_type === 'attendance') {

    $report_title = "Attendance Report";

    $stmt = $conn->prepare("
        SELECT
            date,
            time_in,
            time_out,
            clock_in,
            clock_out,
            status
        FROM attendance
        WHERE staff_id = ?
        AND date BETWEEN ? AND ?
        ORDER BY date DESC
    ");

    if (!$stmt) {
        die("Attendance report query failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sss",
        $staff_id,
        $from_date,
        $to_date
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $report_data[] = $row;

        if (strtolower($row['status']) === 'present') {
            $total_days++;
        }

        /*
         * Calculate hours
         */

        $time_in = $row['time_in'] ?: $row['clock_in'];
        $time_out = $row['time_out'] ?: $row['clock_out'];

        if (!empty($time_in) && !empty($time_out)) {

            $start = strtotime($time_in);
            $end = strtotime($time_out);

            if ($end > $start) {

                $hours = ($end - $start) / 3600;

                $total_hours += $hours;

                /*
                 * Anything above 8 hours is overtime
                 */

                if ($hours > 8) {
                    $total_overtime += ($hours - 8);
                }
            }
        }
    }

    $stmt->close();
}


/* =========================================================
   OVERTIME REPORT
========================================================= */

if ($show_report && $report_type === 'overtime') {

    $report_title = "Overtime Report";

    $stmt = $conn->prepare("
        SELECT
            id,
            work_date,
            hours_worked,
            overtime_hours,
            status,
            created_at
        FROM timesheets
        WHERE staff_id = ?
        AND work_date BETWEEN ? AND ?
        AND overtime_hours > 0
        ORDER BY work_date DESC, created_at DESC
    ");

    if (!$stmt) {
        die("Overtime report query failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sss",
        $staff_id,
        $from_date,
        $to_date
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $report_data[] = $row;

        $total_hours += (float)$row['hours_worked'];

        $total_overtime += (float)$row['overtime_hours'];
    }

    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Staff Reports</title>

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
    grid-template-columns:
        repeat(3, 1fr)
        auto;

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
    margin-bottom: 20px;
}

.report-header h2 {
    margin: 5px 0;
}

.staff-info {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.info-item {
    background: #f7f7f7;
    padding: 12px;
    border-radius: 6px;
}

.info-item strong {
    display: block;
    margin-bottom: 5px;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th,
.report-table td {
    padding: 11px;
    border: 1px solid #ddd;
    text-align: left;
}

.report-table th {
    background: #f2f2f2;
}

.summary {
    display: flex;
    gap: 20px;
    margin-top: 25px;
}

.summary-card {
    flex: 1;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 7px;
    text-align: center;
}

.summary-card strong {
    display: block;
    font-size: 20px;
}

.report-actions {
    margin-top: 25px;
    display: flex;
    gap: 10px;
}

.print-btn,
.pdf-btn {
    padding: 11px 20px;
    border: none;
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
    text-decoration: none;
}

.print-btn {
    background: #333;
}

.pdf-btn {
    background: #d9534f;
}

.no-data {
    text-align: center;
    padding: 30px;
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
    .topbar,
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

    .report-table th {
        background: #eee !important;
        color: #000 !important;
    }

    .report-header {
        display: block;
    }
}

.excel-btn {
    padding: 11px 20px;
    border: none;
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
    text-decoration: none;
    background: #198754;
}

.excel-btn:hover {
    opacity: .9;
}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width: 768px) {

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .staff-info {
        grid-template-columns: 1fr;
    }

    .summary {
        flex-direction: column;
    }

    .report-table {
        font-size: 13px;
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

        <p>Personnel Timesheet System</p>

    </div>


    <div class="profile">

        <div class="avatar">

            <?php

            $profile_photo =
                "uploads/staff/default.png";

            if (!empty($staff['profile_picture'])) {

                $photo_name =
                    basename(
                        $staff['profile_picture']
                    );

                if (
                    file_exists(
                        "uploads/staff/" .
                        $photo_name
                    )
                ) {

                    $profile_photo =
                        "uploads/staff/" .
                        $photo_name;

                } elseif (
                    file_exists(
                        "uploads/" .
                        $photo_name
                    )
                ) {

                    $profile_photo =
                        "uploads/" .
                        $photo_name;

                }

            }

            ?>

            <img
                src="<?php echo htmlspecialchars($profile_photo); ?>"
                class="profile-small"
                alt="Profile Photo"
                onerror="this.src='uploads/staff/default.png';"
            >

        </div>


        <h3>
            <?php
            echo htmlspecialchars($fullname);
            ?>
        </h3>

        <p>Staff</p>

    </div>


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

        <li>
            <a href="staff_submit_task_report.php">
                My Task
            </a>
        </li>

        <li>
            <a href="task_history.php">
                Task History
            </a>
        </li>

        <li>
            <a href="staff_reports.php">
                Reports
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
     MAIN
===================================================== -->

<div class="main">


    <div class="topbar">

        <div>

            <h1>
                Staff Reports
            </h1>

        </div>

        <div id="clock"></div>

    </div>


    <div class="report-container">


        <!-- =================================================
             REPORT FILTER
        ================================================== -->

        <div class="report-filter">

            <h2>
                Generate Report
            </h2>

            <form method="GET">

                <div class="filter-grid">


                    <div class="form-group">

                        <label>
                            Report Type
                        </label>

                        <select
                            name="report_type"
                            required
                        >

                            <option
                                value="task"
                                <?php
                                echo $report_type === 'task'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Task Report
                            </option>

                            <option
                                value="attendance"
                                <?php
                                echo $report_type === 'attendance'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Attendance Report
                            </option>

                            <option
    value="overtime"
    <?php
    echo $report_type === 'overtime'
        ? 'selected'
        : '';
    ?>
>
    Overtime Report
</option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            From Date
                        </label>

                        <input
                            type="date"
                            name="from_date"
                            value="<?php
                            echo htmlspecialchars(
                                $from_date
                            );
                            ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            To Date
                        </label>

                        <input
                            type="date"
                            name="to_date"
                            value="<?php
                            echo htmlspecialchars(
                                $to_date
                            );
                            ?>"
                            required
                        >

                    </div>


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
             REPORT
        ================================================== -->

        <div class="report-result"
             id="printArea">


            <div class="report-header">

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $app['organization_name']
                    );
                    ?>
                </h2>

                <h3>
                    <?php
                    echo htmlspecialchars(
                        $report_title
                    );
                    ?>
                </h3>

                <p>

                    Period:

                    <?php
                    echo date(
                        "d M Y",
                        strtotime($from_date)
                    );
                    ?>

                    -

                    <?php
                    echo date(
                        "d M Y",
                        strtotime($to_date)
                    );
                    ?>

                </p>

            </div>


            <!-- STAFF INFORMATION -->

            <div class="staff-info">

                <div class="info-item">

                    <strong>
                        Staff Name
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $fullname
                    );
                    ?>

                </div>


                <div class="info-item">

                    <strong>
                        Staff ID
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $staff_id
                    );
                    ?>

                </div>


                <div class="info-item">

                    <strong>
                        Department
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $department
                    );
                    ?>

                </div>


                <div class="info-item">

                    <strong>
                        Supervisor
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $supervisor_name
                    );
                    ?>

                </div>

            </div>


            <?php if (count($report_data) > 0): ?>


            <!-- =================================================
                 TASK REPORT
            ================================================== -->

            <?php if ($report_type === 'task'): ?>

                <table class="report-table">

                    <thead>

                        <tr>

                            <th>
                                Date
                            </th>

                            <th>
                                Task
                            </th>

                            <th>
                                Start
                            </th>

                            <th>
                                End
                            </th>

                            <th>
                                Hours
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $report_data
                        as $row
                    ): ?>

                        <tr>

                            <td>
                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $row['report_date']
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $row['tasks_completed']
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo date(
                                    "h:i A",
                                    strtotime(
                                        $row['start_time']
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo date(
                                    "h:i A",
                                    strtotime(
                                        $row['end_time']
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo number_format(
                                    (float)$row['hours_worked'],
                                    2
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row['status']
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>


                <div class="summary">

                    <div class="summary-card">

                        <strong>
                            <?php
                            echo count(
                                $report_data
                            );
                            ?>
                        </strong>

                        Task Reports

                    </div>


                    <div class="summary-card">

                        <strong>
                            <?php
                            echo number_format(
                                $total_hours,
                                2
                            );
                            ?>
                        </strong>

                        Total Hours

                    </div>

                </div>


            <!-- =================================================
                 ATTENDANCE REPORT
            ================================================== -->

            <?php elseif (
                $report_type === 'attendance'
            ): ?>

                <table class="report-table">

                    <thead>

                        <tr>

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
                                Hours
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $report_data
                        as $row
                    ): ?>

                        <?php

                        $time_in =
                            $row['time_in']
                            ?: $row['clock_in'];

                        $time_out =
                            $row['time_out']
                            ?: $row['clock_out'];

                        $day_hours = 0;

                        if (
                            !empty($time_in) &&
                            !empty($time_out)
                        ) {

                            $start =
                                strtotime($time_in);

                            $end =
                                strtotime($time_out);

                            if ($end > $start) {

                                $day_hours =
                                    ($end - $start)
                                    / 3600;

                            }

                        }

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

                                echo !empty($time_in)
                                    ? date(
                                        "h:i A",
                                        strtotime(
                                            $time_in
                                        )
                                    )
                                    : "-";

                                ?>
                            </td>

                            <td>
                                <?php

                                echo !empty($time_out)
                                    ? date(
                                        "h:i A",
                                        strtotime(
                                            $time_out
                                        )
                                    )
                                    : "-";

                                ?>
                            </td>

                            <td>
                                <?php
                                echo number_format(
                                    $day_hours,
                                    2
                                );
                                ?> h
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row['status']
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>


                <div class="summary">

                    <div class="summary-card">

                        <strong>
                            <?php
                            echo $total_days;
                            ?>
                        </strong>

                        Days Present

                    </div>


                    <div class="summary-card">

                        <strong>
                            <?php
                            echo number_format(
                                $total_hours,
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
                                $total_overtime,
                                2
                            );
                            ?>
                        h
                    </strong>

                        Overtime

                    </div>

                </div>


           <!-- =================================================
     OVERTIME REPORT
================================================= -->

<?php elseif (
    $report_type === 'overtime'
): ?>

    <table class="report-table">

        <thead>

            <tr>

                <th>
                    Date
                </th>

                <th>
                    Regular Hours
                </th>

                <th>
                    Overtime Hours
                </th>

                <th>
                    Total Hours
                </th>

                <th>
                    Status
                </th>

            </tr>

        </thead>

        <tbody>

        <?php foreach (
            $report_data
            as $row
        ): ?>

            <tr>

                <td>
                    <?php
                    echo date(
                        "d M Y",
                        strtotime($row['work_date'])
                    );
                    ?>
                </td>

                <td>
                    <?php
                    echo number_format(
                        (float)$row['hours_worked']
                        - (float)$row['overtime_hours'],
                        2
                    );
                    ?> h
                </td>

                <td>
                    <strong>
                        <?php
                        echo number_format(
                            (float)$row['overtime_hours'],
                            2
                        );
                        ?> h
                    </strong>
                </td>

                <td>
                    <?php
                    echo number_format(
                        (float)$row['hours_worked'],
                        2
                    );
                    ?> h
                </td>

                <td>
                    <?php
                    echo htmlspecialchars(
                        $row['status']
                    );
                    ?>
                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>


    <div class="summary">

        <div class="summary-card">

            <strong>
                <?php
                echo count($report_data);
                ?>
            </strong>

            Overtime Records

        </div>


        <div class="summary-card">

            <strong>

                <?php
                echo number_format(
                    $total_hours,
                    2
                );
                ?> h

            </strong>

            Total Hours

        </div>


        <div class="summary-card">

            <strong>

                <?php
                echo number_format(
                    $total_overtime,
                    2
                );
                ?> h

            </strong>

            Total Overtime

        </div>

    </div>

                    <?php endif; ?>
            <!-- =================================================
                 ACTION BUTTONS
            ================================================== -->

           <div class="report-actions">

    <button
        type="button"
        onclick="window.print()"
        class="print-btn"
    >
        🖨 Print Report
    </button>

   <!-- PDF -->
    <a
        href="staff_report_pdf.php?report_type=<?php echo urlencode($report_type); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>"
        class="pdf-btn"
    >
        📄 Download PDF
    </a>

    <!-- EXCEL -->
    <a
        href="staff_report_excel.php?report_type=<?php echo urlencode($report_type); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>"
        class="excel-btn"
    >
        📊 Download Excel
    </a>
</div>


            <?php else: ?>


                <div class="no-data">

                    <h3>
                        No Records Found
                    </h3>

                    <p>
                        There are no
                        <?php
                        echo htmlspecialchars(
                            $report_type
                        );
                        ?>
                        records between

                        <strong>
                            <?php
                            echo date(
                                "d M Y",
                                strtotime($from_date)
                            );
                            ?>
                        </strong>

                        and

                        <strong>
                            <?php
                            echo date(
                                "d M Y",
                                strtotime($to_date)
                            );
                            ?>
                        </strong>.
                    </p>

                </div>


            <?php endif; ?>


        </div>

        <?php endif; ?>


    </div>

</div>

</div>


<script>

function updateClock() {

    const now = new Date();

    document.getElementById("clock").innerHTML =
        now.toLocaleTimeString();

}

setInterval(updateClock, 1000);

updateClock();

</script>

</body>
</html>