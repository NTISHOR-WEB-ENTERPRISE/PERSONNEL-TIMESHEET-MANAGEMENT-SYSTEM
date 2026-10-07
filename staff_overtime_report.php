<?php
session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
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

/* ==========================================
   SUPERVISOR INFORMATION
========================================== */

$stmt = $conn->prepare("
    SELECT supervisor_id, fullname
    FROM supervisors
    WHERE supervisor_id = ?
");

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$supervisor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supervisor) {
    die("Supervisor not found.");
}

$supervisor_name = $supervisor['fullname'];
$supervisor_code = $supervisor['supervisor_id'];


/* ==========================================
   FILTERS
========================================== */

$staff_filter = $_GET['staff_id'] ?? 'all';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date'] ?? date('Y-m-d');

$generate = isset($_GET['generate']);


/* ==========================================
   FETCH STAFF UNDER SUPERVISOR
========================================== */

$staff_list = [];

$stmt = $conn->prepare("
    SELECT
        staff_id,
        fullname,
        department,
        position
    FROM staff
    WHERE supervisor_id = ?
    ORDER BY fullname ASC
");

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $staff_list[] = $row;
}

$stmt->close();


/* ==========================================
   REPORT VARIABLES
========================================== */

$report_data = [];

$total_regular = 0;
$total_overtime = 0;
$total_hours = 0;

$staff_with_overtime = [];


/* ==========================================
   GENERATE REPORT
========================================== */

if ($generate) {

    /*
     * Overtime is based on attendance.
     *
     * Normal working day = 8 hours.
     *
     * Overtime = hours worked above 8 hours
     * for that particular day.
     */

    $sql = "
        SELECT
            a.date,
            a.staff_id,
            s.fullname,
            s.department,
            s.position,
            a.time_in,
            a.time_out,
            a.clock_in,
            a.clock_out,
            a.status
        FROM attendance a
        INNER JOIN staff s
            ON a.staff_id = s.staff_id
        WHERE s.supervisor_id = ?
        AND a.date BETWEEN ? AND ?
    ";

    $params = [
        $supervisor_id,
        $from_date,
        $to_date
    ];

    $types = "sss";


    /*
     * If one staff member was selected,
     * restrict the report to that staff.
     */

    if ($staff_filter !== 'all') {

        $sql .= " AND a.staff_id = ?";

        $params[] = $staff_filter;
        $types .= "s";
    }


    $sql .= "
        ORDER BY
            a.date DESC,
            s.fullname ASC
    ";


    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die(
            "Report query failed: " .
            htmlspecialchars($conn->error)
        );
    }


    $stmt->bind_param(
        $types,
        ...$params
    );

    $stmt->execute();

    $result = $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        /*
         * Determine clock-in and clock-out.
         */

        $time_in = !empty($row['time_in'])
            ? $row['time_in']
            : $row['clock_in'];

        $time_out = !empty($row['time_out'])
            ? $row['time_out']
            : $row['clock_out'];


        $worked_hours = 0;


        if (
            !empty($time_in) &&
            !empty($time_out)
        ) {

            $start = strtotime(
                $row['date'] . ' ' . $time_in
            );

            $end = strtotime(
                $row['date'] . ' ' . $time_out
            );


            if ($end > $start) {

                $worked_hours =
                    ($end - $start) / 3600;

            }
        }


        /*
         * Normal working hours.
         */

        $regular_hours = min(
            $worked_hours,
            8
        );


        /*
         * Overtime is anything above 8 hours.
         */

        $overtime_hours = max(
            0,
            $worked_hours - 8
        );


        /*
         * We only show actual overtime records.
         */

        if ($overtime_hours <= 0) {
            continue;
        }


        $row['worked_hours'] =
            $worked_hours;

        $row['regular_hours'] =
            $regular_hours;

        $row['overtime_hours'] =
            $overtime_hours;


        $report_data[] = $row;


        /*
         * Totals.
         */

        $total_regular +=
            $regular_hours;

        $total_overtime +=
            $overtime_hours;

        $total_hours +=
            $worked_hours;


        /*
         * Track staff with overtime.
         */

        $staff_with_overtime[
            $row['staff_id']
        ] = true;
    }

    $stmt->close();
}


$staff_overtime_count =
    count($staff_with_overtime);

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Overtime Report
</title>

<link rel="stylesheet"
      href="styles.css">


<style>

/* ==========================================
   REPORT CONTAINER
========================================== */

.report-container {
    padding: 25px;
}


/* ==========================================
   FILTER
========================================== */

.report-filter {

    background: #fff;

    padding: 25px;

    border-radius: 10px;

    margin-bottom: 25px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.08);
}

.report-filter h2 {

    margin-top: 0;

    margin-bottom: 20px;
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

    font-size: 14px;

}


.generate-btn {

    padding: 11px 20px;

    border: none;

    border-radius: 6px;

    cursor: pointer;

    background: #007bff;

    color: white;

    font-weight: 600;
}


.generate-btn:hover {

    opacity: .9;
}


/* ==========================================
   REPORT PREVIEW
========================================== */

.report-result {

    background: #fff;

    padding: 30px;

    border-radius: 10px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.08);
}


/* ==========================================
   REPORT HEADER
========================================== */

.report-header {

    text-align: center;

    border-bottom: 2px solid #222;

    padding-bottom: 20px;

    margin-bottom: 20px;
}


.report-header h2 {

    margin: 5px 0;
}


.report-header h3 {

    margin: 5px 0;
}


.report-header p {

    margin: 5px 0;

    color: #555;
}


/* ==========================================
   STAFF INFORMATION
========================================== */

.report-info {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

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


/* ==========================================
   TABLE
========================================== */

.report-table {

    width: 100%;

    border-collapse: collapse;
}


.report-table th,
.report-table td {

    padding: 10px;

    border: 1px solid #ddd;

    text-align: left;
}


.report-table th {

    background: #f2f2f2;

    font-weight: 600;
}


.report-table td {

    vertical-align: top;
}


.overtime-value {

    font-weight: 700;
}


/* ==========================================
   SUMMARY
========================================== */

.summary {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-top: 25px;
}


.summary-card {

    padding: 18px;

    background: #f5f5f5;

    border-radius: 7px;

    text-align: center;
}


.summary-card strong {

    display: block;

    font-size: 22px;

    margin-bottom: 5px;
}


/* ==========================================
   PRINTED BY
========================================== */

.printed-by {

    margin-top: 30px;

    padding-top: 15px;

    border-top: 1px solid #ccc;

    font-size: 13px;

    color: #555;
}


/* ==========================================
   ACTIONS
========================================== */

.report-actions {

    margin-top: 25px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.report-actions button,
.report-actions a {

    padding: 11px 20px;

    border: none;

    border-radius: 6px;

    color: white;

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


/* ==========================================
   NO DATA
========================================== */

.no-data {

    text-align: center;

    padding: 35px;

    color: #777;
}


/* ==========================================
   PRINT
========================================== */

@media print {

    body {

        background: white;
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


    .report-table th {

        background: #eee !important;

        color: #000 !important;
    }


    .report-header {

        display: block;
    }

}


/* ==========================================
   MOBILE
========================================== */

@media(max-width: 768px) {

    .filter-grid {

        grid-template-columns: 1fr;
    }


    .report-info {

        grid-template-columns: 1fr;
    }


    .summary {

        grid-template-columns: 1fr;
    }


    .report-table {

        font-size: 12px;
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
</style>

</head>


<body>


<div class="container">


<!-- ==========================================
     SIDEBAR
========================================== -->

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
            Timesheet System
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
            echo htmlspecialchars(
                $supervisor_name
            );
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


<!-- ==========================================
     MAIN
========================================== -->

<div class="main">


    <div class="page-header">

        <div>

            <h2>
                Overtime Report
            </h2>

        </div>


        <div id="clock"></div>

    </div>


    <div class="report-container">


        <!-- ==========================================
             FILTER
        ========================================== -->

        <div class="report-filter">

            <h2>
                Generate Overtime Report
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
                                echo $staff_filter === 'all'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                All Staff
                            </option>


                            <?php foreach (
                                $staff_list
                                as $member
                            ): ?>

                                <option
                                    value="<?php
                                    echo htmlspecialchars(
                                        $member['staff_id']
                                    );
                                    ?>"
                                    <?php
                                    echo $staff_filter ===
                                         $member['staff_id']
                                        ? 'selected'
                                        : '';
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $member['fullname']
                                    );
                                    ?>

                                    -
                                    <?php
                                    echo htmlspecialchars(
                                        $member['staff_id']
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- FROM -->

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


                    <!-- TO -->

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


        <?php if ($generate): ?>


        <!-- ==========================================
             REPORT PREVIEW
        ========================================== -->

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
                    OVERTIME REPORT
                </h3>


                <p>

                    Period:

                    <strong>

                        <?php
                        echo date(
                            "d M Y",
                            strtotime(
                                $from_date
                            )
                        );
                        ?>

                        -

                        <?php
                        echo date(
                            "d M Y",
                            strtotime(
                                $to_date
                            )
                        );
                        ?>

                    </strong>

                </p>

            </div>


            <!-- REPORT INFORMATION -->

            <div class="report-info">


                <div class="info-item">

                    <strong>
                        Staff
                    </strong>

                    <?php

                    if ($staff_filter === 'all') {

                        echo "All Staff";

                    } else {

                        foreach (
                            $staff_list
                            as $member
                        ) {

                            if (
                                $member['staff_id']
                                ===
                                $staff_filter
                            ) {

                                echo htmlspecialchars(
                                    $member['fullname']
                                );

                                echo " (";

                                echo htmlspecialchars(
                                    $member['staff_id']
                                );

                                echo ")";

                                break;
                            }

                        }

                    }

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


                <div class="info-item">

                    <strong>
                        Working Day Overtime
                    </strong>

                    More than 8 hours/day

                </div>

            </div>


            <?php if (
                count($report_data) > 0
            ): ?>


                <!-- ==========================================
                     TABLE
                ========================================== -->

                <table
                    class="report-table"
                >

                    <thead>

                        <tr>

                            <th>
                                Date
                            </th>

                            <th>
                                Staff ID
                            </th>

                            <th>
                                Staff Name
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

                                <?php
                                echo htmlspecialchars(
                                    $row['fullname']
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo number_format(
                                    $row['regular_hours'],
                                    2
                                );
                                ?>
                                h

                            </td>


                            <td
                                class="overtime-value"
                            >

                                <?php
                                echo number_format(
                                    $row['overtime_hours'],
                                    2
                                );
                                ?>
                                h

                            </td>


                            <td>

                                <?php
                                echo number_format(
                                    $row['worked_hours'],
                                    2
                                );
                                ?>
                                h

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>


                <!-- ==========================================
                     SUMMARY
                ========================================== -->

                <div class="summary">


                    <div class="summary-card">

                        <strong>

                            <?php
                            echo $staff_overtime_count;
                            ?>

                        </strong>

                        Staff with Overtime

                    </div>


                    <div class="summary-card">

                        <strong>

                            <?php
                            echo number_format(
                                $total_regular,
                                2
                            );
                            ?>
                            h

                        </strong>

                        Regular Hours

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

                        Total Overtime

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


                </div>


            <?php else: ?>


                <div class="no-data">

                    <h3>
                        No Overtime Records Found
                    </h3>


                    <p>

                        No staff under your supervision
                        recorded overtime between

                        <strong>

                            <?php
                            echo date(
                                "d M Y",
                                strtotime(
                                    $from_date
                                )
                            );
                            ?>

                        </strong>

                        and

                        <strong>

                            <?php
                            echo date(
                                "d M Y",
                                strtotime(
                                    $to_date
                                )
                            );
                            ?>

                        </strong>.

                    </p>

                </div>


            <?php endif; ?>


            <!-- ==========================================
                 PRINTED BY
            ========================================== -->

            <div class="printed-by">

                <strong>
                    Printed By:
                </strong>

                <?php
                echo htmlspecialchars(
                    $supervisor_name
                );
                ?>

                —

                <?php
                echo htmlspecialchars(
                    $supervisor_code
                );
                ?>

                &nbsp; | &nbsp;

                <strong>
                    Date:
                </strong>

                <?php
                echo date(
                    "d M Y"
                );
                ?>

                &nbsp; | &nbsp;

                <strong>
                    Time:
                </strong>

                <?php
                echo date(
                    "h:i A"
                );
                ?>

            </div>


            <!-- ==========================================
                 ACTION BUTTONS
            ========================================== -->

            <div class="report-actions">


                <button
                    type="button"
                    class="print-btn"
                    onclick="window.print()"
                >

                    🖨 Print Report

                </button>


                <a
                    href="staff_overtime_report_pdf.php?staff_id=<?php echo urlencode($staff_filter); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&generate=1"
                    class="pdf-btn"
                >

                    📄 Download PDF

                </a>


                <a
                    href="staff_overtime_report_excel.php?staff_id=<?php echo urlencode($staff_filter); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&generate=1"
                    class="excel-btn"
                >

                    📊 Download Excel

                </a>


            </div>


        </div>


        <?php endif; ?>


    </div>

</div>


<script>

function updateClock() {

    const now = new Date();

    document.getElementById(
        "clock"
    ).innerHTML =
        "● " +
        now.toLocaleTimeString(
            "en-GB",
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