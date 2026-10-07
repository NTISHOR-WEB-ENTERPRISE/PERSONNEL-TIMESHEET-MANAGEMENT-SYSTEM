<?php
session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$supervisor_name = $_SESSION['fullname'] ?? 'Supervisor';

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

/* =========================================================
   REPORT SETTINGS
========================================================= */

$staff_id = $_GET['staff_id'] ?? 'all';

$from_date = $_GET['from_date']
    ?? date('Y-m-01');

$to_date = $_GET['to_date']
    ?? date('Y-m-d');

$show_report = isset($_GET['generate']);


/* =========================================================
   GET SUPERVISOR INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT fullname
    FROM supervisors
    WHERE supervisor_id = ?
");

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $supervisor = $result->fetch_assoc();

    $supervisor_name =
        $supervisor['fullname'];
}

$stmt->close();


/* =========================================================
   GET STAFF UNDER THIS SUPERVISOR
========================================================= */

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

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $staff_list[] = $row;
}

$stmt->close();


/* =========================================================
   REPORT DATA
========================================================= */

$report_data = [];

$total_hours = 0;
$total_reports = 0;
$staff_count = 0;

$report_staff = "All Staff";


/* =========================================================
   GET SELECTED STAFF NAME
========================================================= */

if ($staff_id !== 'all') {

    foreach ($staff_list as $staff) {

        if ($staff['staff_id'] === $staff_id) {

            $report_staff =
                $staff['fullname'];

            break;
        }
    }
}


/* =========================================================
   TASK REPORT
========================================================= */

if ($show_report) {

    /*
     * Only allow a staff member who actually
     * belongs to this supervisor.
     */

    $sql = "
        SELECT
            t.id,
            t.staff_id,
            t.supervisor_id,
            t.report_date,
            t.start_time,
            t.end_time,
            t.hours_worked,
            t.tasks_completed,
            t.status,
            t.supervisor_comment,
            t.created_at,

            s.fullname,
            s.department,
            s.position

        FROM task_reports t

        INNER JOIN staff s
            ON t.staff_id = s.staff_id

        WHERE t.supervisor_id = ?

        AND t.report_date BETWEEN ? AND ?
    ";

    $params = [
        $supervisor_id,
        $from_date,
        $to_date
    ];

    $types = "sss";


    /*
     * Individual staff filter
     */

    if ($staff_id !== 'all') {

        $sql .= "
            AND t.staff_id = ?
        ";

        $params[] = $staff_id;
        $types .= "s";
    }


    $sql .= "
        ORDER BY
            t.report_date DESC,
            t.created_at DESC
    ";


    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die(
            "Task report query failed: "
            . $conn->error
        );
    }


    $stmt->bind_param(
        $types,
        ...$params
    );

    $stmt->execute();

    $result =
        $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $report_data[] = $row;

        $total_reports++;

        $total_hours +=
            (float)$row['hours_worked'];
    }


    $stmt->close();


    /*
     * Count unique staff
     */

    $unique_staff = [];

    foreach ($report_data as $row) {

        $unique_staff[
            $row['staff_id']
        ] = true;
    }

    $staff_count =
        count($unique_staff);
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Staff Task Report</title>

<link
    rel="stylesheet"
    href="styles.css"
>

<style>

/* =========================================================
   REPORT CONTAINER
========================================================= */

.report-container {
    padding: 25px;
}


/* =========================================================
   FILTER
========================================================= */

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
        1.2fr 1fr 1fr auto;

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

    border:
        1px solid #ccc;

    border-radius: 6px;

    font-size: 14px;
}


.generate-btn {

    padding:
        11px 20px;

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


/* =========================================================
   REPORT RESULT
========================================================= */

.report-result {

    background: #fff;

    padding: 30px;

    border-radius: 10px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.08);
}


/* =========================================================
   HEADER
========================================================= */

.report-header {

    text-align: center;

    border-bottom:
        2px solid #222;

    padding-bottom: 20px;

    margin-bottom: 20px;
}


.report-header h2 {
    margin: 5px 0;
}


.report-header h3 {
    margin: 5px 0;
}


/* =========================================================
   STAFF INFORMATION
========================================================= */

.staff-info {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

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


/* =========================================================
   TABLE
========================================================= */

.report-table {

    width: 100%;

    border-collapse:
        collapse;
}


.report-table th,
.report-table td {

    padding: 10px;

    border:
        1px solid #ddd;

    text-align: left;

    vertical-align: top;
}


.report-table th {

    background:
        #f2f2f2;

    font-weight: 600;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary {

    display: flex;

    gap: 20px;

    margin-top: 25px;
}


.summary-card {

    flex: 1;

    padding: 15px;

    background:
        #f5f5f5;

    border-radius: 7px;

    text-align: center;
}


.summary-card strong {

    display: block;

    font-size: 20px;

    margin-bottom: 5px;
}


/* =========================================================
   ACTIONS
========================================================= */

.report-actions {

    margin-top: 25px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.print-btn,
.pdf-btn,
.excel-btn {

    padding:
        11px 20px;

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


.print-btn:hover,
.pdf-btn:hover,
.excel-btn:hover {
    opacity: .9;
}


/* =========================================================
   NO DATA
========================================================= */

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


    .report-table th {

        background:
            #eee !important;

        color: #000 !important;
    }


    .report-header {
        display: block;
    }


    .summary-card {

        border:
            1px solid #ddd;
    }

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


    .report-result {
        overflow-x: auto;
    }


    .report-table {
        font-size: 13px;
        min-width: 800px;
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


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


    <div class="page-header">

        <div>

            <h2>
                Staff Task Report
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
                Generate Staff Task Report
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
                                echo
                                $staff_id === 'all'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                All Staff
                            </option>


                            <?php foreach (
                                $staff_list
                                as $staff
                            ): ?>

                                <option
                                    value="<?php
                                    echo htmlspecialchars(
                                        $staff['staff_id']
                                    );
                                    ?>"
                                    <?php
                                    echo
                                    $staff_id ===
                                    $staff['staff_id']
                                        ? 'selected'
                                        : '';
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $staff['fullname']
                                    );
                                    ?>

                                    -
                                    <?php
                                    echo htmlspecialchars(
                                        $staff['staff_id']
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- FROM DATE -->

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


                    <!-- TO DATE -->

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


                    <!-- BUTTON -->

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


            <!-- REPORT HEADER -->

            <div class="report-header">

                <h2>

                    <?php
                    echo htmlspecialchars(
                        $app['organization_name']
                    );
                    ?>

                </h2>


                <h3>
                    STAFF TASK REPORT
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
                        Staff
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $report_staff
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


                <div class="info-item">

                    <strong>
                        Supervisor ID
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $supervisor_id
                    );
                    ?>

                </div>


                <div class="info-item">

                    <strong>
                        Report Period
                    </strong>

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

                </div>


            </div>


            <?php if (
                count($report_data) > 0
            ): ?>


            <!-- =================================================
                 TASK TABLE
            ================================================== -->

            <table class="report-table">

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
                            Department
                        </th>

                        <th>
                            Task Performed
                        </th>

                        <th>
                            Start Time
                        </th>

                        <th>
                            End Time
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
                                ?? ''
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $row[
                                        'tasks_completed'
                                    ]
                                )
                            );
                            ?>

                        </td>


                        <td>

                            <?php

                            if (
                                !empty(
                                    $row['start_time']
                                )
                            ) {

                                echo date(
                                    "h:i A",
                                    strtotime(
                                        $row[
                                            'start_time'
                                        ]
                                    )
                                );

                            } else {

                                echo "-";

                            }

                            ?>

                        </td>


                        <td>

                            <?php

                            if (
                                !empty(
                                    $row['end_time']
                                )
                            ) {

                                echo date(
                                    "h:i A",
                                    strtotime(
                                        $row[
                                            'end_time'
                                        ]
                                    )
                                );

                            } else {

                                echo "-";

                            }

                            ?>

                        </td>


                        <td>

                            <?php
                            echo number_format(
                                (float)
                                $row['hours_worked'],
                                2
                            );
                            ?>

                            h

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


            <!-- =================================================
                 SUMMARY
            ================================================== -->

            <div class="summary">


                <div class="summary-card">

                    <strong>
                        <?php
                        echo $staff_count;
                        ?>
                    </strong>

                    Staff

                </div>


                <div class="summary-card">

                    <strong>
                        <?php
                        echo $total_reports;
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

                        h

                    </strong>

                    Total Hours

                </div>


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
                    🖨 Print Report
                </button>


                <a
                    href="staff_task_report_pdf.php?staff_id=<?php echo urlencode($staff_id); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>"
                    class="pdf-btn"
                >
                    📄 Download PDF
                </a>


                <a
                    href="staff_task_report_excel.php?staff_id=<?php echo urlencode($staff_id); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>"
                    class="excel-btn"
                >
                    📊 Download Excel
                </a>


            </div>


            <?php else: ?>


                <div class="no-data">

                    <h3>
                        No Task Reports Found
                    </h3>

                    <p>

                        There are no task reports
                        for the selected staff
                        and date range.

                    </p>

                </div>


            <?php endif; ?>


            <!-- =================================================
                 PRINTED BY
            ================================================== -->

            <?php if (
                count($report_data) > 0
            ): ?>

                <div
                    style="
                        margin-top:30px;
                        padding-top:15px;
                        border-top:1px solid #ddd;
                        font-size:13px;
                    "
                >

                    <strong>
                        Printed By:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $supervisor_name
                    );
                    ?>

                    &nbsp; | &nbsp;

                    <strong>
                        Supervisor ID:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $supervisor_id
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

            <?php endif; ?>


        </div>

        <?php endif; ?>


    </div>

</div>


<script>

function updateClock() {

    const now =
        new Date();

    const clock =
        document.getElementById(
            "clock"
        );

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