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

/* =========================================================
   REPORT FILTERS
========================================================= */

$staff_filter = $_GET['staff_id'] ?? 'all';
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date'] ?? date('Y-m-d');

$show_report = isset($_GET['generate']);

/* =========================================================
   FETCH STAFF UNDER THIS SUPERVISOR
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

/* =========================================================
   REPORT DATA
========================================================= */

$report_data = [];

$grand_days = 0;
$grand_hours = 0;
$grand_regular_hours = 0;
$grand_overtime = 0;

/* =========================================================
   GENERATE REPORT
========================================================= */

if ($show_report) {

    /*
     * Only allow a staff ID that belongs to this supervisor.
     */
    $staff_condition = "";
    $params = [];
    $types = "";

    if ($staff_filter !== 'all') {

        $valid_staff = false;

        foreach ($staff_list as $staff) {
            if ($staff['staff_id'] === $staff_filter) {
                $valid_staff = true;
                break;
            }
        }

        if (!$valid_staff) {
            $staff_filter = 'all';
        }
    }

    /*
     * Build query.
     *
     * Attendance is used for:
     * - Days Present
     * - Total Hours
     *
     * Only staff belonging to the logged-in supervisor
     * are included.
     */

    $sql = "
        SELECT
            s.staff_id,
            s.fullname,
            s.department,
            s.position,

            COUNT(
                CASE
                    WHEN a.status = 'Present'
                    THEN 1
                END
            ) AS days_present,

            COALESCE(
                SUM(
                    CASE
                        WHEN a.clock_in IS NOT NULL
                         AND a.clock_out IS NOT NULL
                         AND a.clock_out > a.clock_in
                        THEN TIME_TO_SEC(
                            TIMEDIFF(
                                a.clock_out,
                                a.clock_in
                            )
                        ) / 3600
                        ELSE 0
                    END
                ),
                0
            ) AS total_hours

        FROM staff s

        LEFT JOIN attendance a
            ON s.staff_id = a.staff_id
            AND a.date BETWEEN ? AND ?

        WHERE s.supervisor_id = ?
    ";

    $params[] = $from_date;
    $params[] = $to_date;
    $params[] = $supervisor_id;

    $types = "sss";

    if ($staff_filter !== 'all') {

        $sql .= " AND s.staff_id = ?";

        $params[] = $staff_filter;
        $types .= "s";
    }

    $sql .= "
        GROUP BY
            s.staff_id,
            s.fullname,
            s.department,
            s.position

        ORDER BY s.fullname ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Hours Summary query failed: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $total_hours = (float)$row['total_hours'];

        /*
         * Normal working day = 8 hours.
         */

        $regular_hours = min($total_hours, $row['days_present'] * 8);

        $overtime = max(
            0,
            $total_hours - $regular_hours
        );

        $row['regular_hours'] = $regular_hours;
        $row['overtime_hours'] = $overtime;

        $report_data[] = $row;

        $grand_days += (int)$row['days_present'];
        $grand_hours += $total_hours;
        $grand_regular_hours += $regular_hours;
        $grand_overtime += $overtime;
    }

    $stmt->close();
}

/* =========================================================
   STAFF DISPLAY NAME
========================================================= */

$selected_staff_name = "All Staff";

if ($staff_filter !== 'all') {

    foreach ($staff_list as $staff) {

        if ($staff['staff_id'] === $staff_filter) {

            $selected_staff_name =
                $staff['fullname'] .
                " (" .
                $staff['staff_id'] .
                ")";

            break;
        }
    }
}

/* =========================================================
   PRINT INFORMATION
========================================================= */

$printed_date = date("d M Y");
$printed_time = date("h:i A");

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Hours Summary Report</title>

<link rel="stylesheet" href="styles.css">

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
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

.report-filter h2 {
    margin-top: 0;
}

.filter-grid {
    display: grid;
    grid-template-columns:
        1fr
        1fr
        1fr
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
    color: white;
    font-weight: 600;
}

/* =========================================================
   REPORT
========================================================= */

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

.report-header h3 {
    margin: 5px 0;
}

/* =========================================================
   REPORT INFORMATION
========================================================= */

.report-info {
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

/* =========================================================
   TABLE
========================================================= */

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

/* =========================================================
   SUMMARY
========================================================= */

.summary {
    display: flex;
    gap: 15px;
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

/* =========================================================
   PRINT INFORMATION
========================================================= */

.printed-by {
    margin-top: 30px;
    padding-top: 15px;
    border-top: 1px solid #ccc;
    font-size: 13px;
}

.report-actions {
    margin-top: 25px;
    display: flex;
    gap: 10px;
}

.print-btn,
.pdf-btn,
.excel-btn {
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

.excel-btn {
    background: #198754;
}

/* =========================================================
   NO DATA
========================================================= */

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
}

/* =========================================================
   MOBILE
========================================================= */

@media(max-width: 768px) {

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .report-info {
        grid-template-columns: 1fr;
    }

    .summary {
        flex-direction: column;
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

        <p>Timesheet System</p>

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

        <p>Supervisor</p>

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

        <li class="active">
            <a href="reports.php">
                Reports
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
            <h2>Hours Summary</h2>
        </div>

        <div id="clock"></div>

    </div>

    <div class="report-container">

        <!-- =================================================
             FILTER
        ================================================== -->

        <div class="report-filter">

            <h2>
                Generate Hours Summary
            </h2>

            <form method="GET">

                <div class="filter-grid">

                    <div class="form-group">

                        <label>
                            Staff
                        </label>

                        <select
                            name="staff_id"
                            required
                        >

                            <option value="all">
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
                                    echo $staff_filter ===
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
             REPORT PREVIEW
        ================================================== -->

        <div
            class="report-result"
            id="printArea"
        >

            <div class="report-header">

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $app['organization_name']
                    );
                    ?>
                </h2>

                <h3>
                    HOURS SUMMARY REPORT
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

            <!-- REPORT INFO -->

            <div class="report-info">

                <div class="info-item">

                    <strong>
                        Staff
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $selected_staff_name
                    );
                    ?>

                </div>

                <div class="info-item">

                    <strong>
                        Supervisor
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $fullname
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

            </div>

            <?php if (count($report_data) > 0): ?>

            <!-- =================================================
                 TABLE
            ================================================== -->

            <table class="report-table">

                <thead>

                    <tr>

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
                            Days Present
                        </th>

                        <th>
                            Total Hours
                        </th>

                        <th>
                            Regular Hours
                        </th>

                        <th>
                            Overtime
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
                                $row['department'] ?? ''
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo (int)$row['days_present'];
                            ?>
                        </td>

                        <td>
                            <?php
                            echo number_format(
                                (float)$row['total_hours'],
                                2
                            );
                            ?> h
                        </td>

                        <td>
                            <?php
                            echo number_format(
                                (float)$row['regular_hours'],
                                2
                            );
                            ?> h
                        </td>

                        <td>
                            <?php
                            echo number_format(
                                (float)$row['overtime_hours'],
                                2
                            );
                            ?> h
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

            <!-- =================================================
                 GRAND SUMMARY
            ================================================== -->

            <div class="summary">

                <div class="summary-card">

                    <strong>
                        <?php
                        echo count($report_data);
                        ?>
                    </strong>

                    Staff

                </div>

                <div class="summary-card">

                    <strong>
                        <?php
                        echo $grand_days;
                        ?>
                    </strong>

                    Days Present

                </div>

                <div class="summary-card">

                    <strong>
                        <?php
                        echo number_format(
                            $grand_hours,
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
                            $grand_overtime,
                            2
                        );
                        ?> h
                    </strong>

                    Total Overtime

                </div>

            </div>

            <!-- =================================================
                 PRINTED BY
            ================================================== -->

            <div class="printed-by">

                <strong>
                    Printed By:
                </strong>

                <?php
                echo htmlspecialchars(
                    $fullname
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
                echo $printed_date;
                ?>

                &nbsp; | &nbsp;

                <strong>
                    Time:
                </strong>

                <?php
                echo $printed_time;
                ?>

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
                    href="hours_summary_pdf.php?staff_id=<?php echo urlencode($staff_filter); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>"
                    class="pdf-btn"
                >
                    📄 Download PDF
                </a>

                <a
                    href="hours_summary_excel.php?staff_id=<?php echo urlencode($staff_filter); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>"
                    class="excel-btn"
                >
                    📊 Download Excel
                </a>

            </div>

            <?php else: ?>

                <div class="no-data">

                    <h3>
                        No Attendance Records Found
                    </h3>

                    <p>
                        No attendance records were found
                        for the selected staff and date
                        range.
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

    let now = new Date();

    document.getElementById('clock').innerHTML =
        '● ' +
        now.toLocaleTimeString(
            'en-GB',
            {hour12:false}
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