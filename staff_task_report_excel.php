<?php
session_start();
include "config.php";

/* =========================================================
   AUTHENTICATION
========================================================= */

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$supervisor_name = $_SESSION['fullname'] ?? 'Supervisor';


/* =========================================================
   GET FILTER VALUES
========================================================= */

$staff_id = $_GET['staff_id'] ?? '';
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date'] ?? date('Y-m-d');


/* =========================================================
   VALIDATE DATES
========================================================= */

if (empty($from_date)) {
    $from_date = date('Y-m-01');
}

if (empty($to_date)) {
    $to_date = date('Y-m-d');
}


/* =========================================================
   FETCH SUPERVISOR INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT
        supervisor_id,
        fullname
    FROM supervisors
    WHERE supervisor_id = ?
");

if (!$stmt) {
    die("Supervisor query failed: " . $conn->error);
}

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$supervisor_result = $stmt->get_result();

if ($supervisor_result->num_rows > 0) {

    $supervisor = $supervisor_result->fetch_assoc();

    $supervisor_name =
        $supervisor['fullname'];

    $supervisor_id =
        $supervisor['supervisor_id'];
}

$stmt->close();


/* =========================================================
   ORGANIZATION NAME
========================================================= */

$organization_name =
    $app['organization_name']
    ?? 'NTISHOR WEB ENTERPRISE';


/* =========================================================
   STAFF INFORMATION
========================================================= */

$staff_name = 'All Staff';
$department = 'All Departments';

if (!empty($staff_id)) {

    $stmt = $conn->prepare("
        SELECT
            staff_id,
            fullname,
            department
        FROM staff
        WHERE staff_id = ?
        AND supervisor_id = ?
    ");

    if (!$stmt) {
        die("Staff query failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ss",
        $staff_id,
        $supervisor_id
    );

    $stmt->execute();

    $staff_result = $stmt->get_result();

    if ($staff_result->num_rows > 0) {

        $staff = $staff_result->fetch_assoc();

        $staff_name =
            $staff['fullname'];

        $department =
            $staff['department'] ?: 'N/A';

    } else {

        die("Invalid staff selection.");

    }

    $stmt->close();
}


/* =========================================================
   FETCH TASK REPORTS
========================================================= */

$sql = "
    SELECT
        t.id,
        t.staff_id,
        s.fullname,
        s.department,
        t.report_date,
        t.start_time,
        t.end_time,
        t.hours_worked,
        t.tasks_completed,
        t.status
    FROM task_reports t

    INNER JOIN staff s
        ON t.staff_id = s.staff_id

    WHERE s.supervisor_id = ?

    AND t.report_date BETWEEN ? AND ?
";


/* =========================================================
   STAFF FILTER
========================================================= */

if (!empty($staff_id)) {

    $sql .= "
        AND t.staff_id = ?
    ";

}

$sql .= "
    ORDER BY
        t.report_date ASC,
        t.created_at ASC
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Task report query failed: " . $conn->error);
}


/* =========================================================
   BIND PARAMETERS
========================================================= */

if (!empty($staff_id)) {

    $stmt->bind_param(
        "ssss",
        $supervisor_id,
        $from_date,
        $to_date,
        $staff_id
    );

} else {

    $stmt->bind_param(
        "sss",
        $supervisor_id,
        $from_date,
        $to_date
    );
}


$stmt->execute();

$result = $stmt->get_result();


/* =========================================================
   STORE REPORT DATA
========================================================= */

$report_data = [];

$total_hours = 0;
$total_records = 0;

while ($row = $result->fetch_assoc()) {

    $report_data[] = $row;

    $total_hours +=
        (float)$row['hours_worked'];

    $total_records++;
}

$stmt->close();


/* =========================================================
   PRINT INFORMATION
========================================================= */

$printed_date =
    date('d M Y');

$printed_time =
    date('h:i A');


/* =========================================================
   EXCEL FILE NAME
========================================================= */

if (!empty($staff_id)) {

    $safe_name =
        preg_replace(
            '/[^A-Za-z0-9_-]/',
            '_',
            $staff_name
        );

    $filename =
        "Staff_Task_Report_" .
        $safe_name . "_" .
        date('Y-m-d') .
        ".xls";

} else {

    $filename =
        "Staff_Task_Report_All_Staff_" .
        date('Y-m-d') .
        ".xls";
}


/* =========================================================
   EXCEL HEADERS
========================================================= */

header(
    "Content-Type: application/vnd.ms-excel; charset=UTF-8"
);

header(
    "Content-Disposition: attachment; filename=\"$filename\""
);

header("Pragma: no-cache");
header("Expires: 0");

?>
<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

body {
    font-family: Arial, sans-serif;
    font-size: 12px;
}

.title {
    font-size: 20px;
    font-weight: bold;
    text-align: center;
}

.subtitle {
    font-size: 16px;
    font-weight: bold;
    text-align: center;
}

.period {
    text-align: center;
    font-size: 12px;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    margin-bottom: 20px;
}

.info-table td {
    border: 1px solid #999;
    padding: 8px;
}

.info-label {
    font-weight: bold;
    background: #eeeeee;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th {
    background: #d9eaf7;
    border: 1px solid #000;
    padding: 8px;
    font-weight: bold;
    text-align: center;
}

.report-table td {
    border: 1px solid #999;
    padding: 7px;
    vertical-align: top;
}

.center {
    text-align: center;
}

.number {
    text-align: right;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.summary-table td {
    border: 1px solid #999;
    padding: 8px;
}

.summary-label {
    font-weight: bold;
    background: #eeeeee;
}

.printed {
    margin-top: 25px;
    font-size: 11px;
}

</style>

</head>

<body>


<!-- =====================================================
     REPORT HEADING
====================================================== -->

<table width="100%">

<tr>

<td
    colspan="10"
    class="title"
>

    <?php
    echo htmlspecialchars(
        $organization_name
    );
    ?>

</td>

</tr>


<tr>

<td
    colspan="10"
    class="subtitle"
>

    STAFF TASK REPORT

</td>

</tr>


<tr>

<td
    colspan="10"
    class="period"
>

    Reporting Period:

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

</td>

</tr>

</table>


<!-- =====================================================
     STAFF INFORMATION
====================================================== -->

<table class="info-table">

<tr>

<td class="info-label">
    Staff
</td>

<td>
    <?php
    echo htmlspecialchars(
        $staff_name
    );
    ?>
</td>


<td class="info-label">
    Staff ID
</td>

<td>

    <?php

    echo !empty($staff_id)
        ? htmlspecialchars($staff_id)
        : 'ALL STAFF';

    ?>

</td>

</tr>


<tr>

<td class="info-label">
    Department
</td>

<td>

    <?php
    echo htmlspecialchars(
        $department
    );
    ?>

</td>


<td class="info-label">
    Supervisor
</td>

<td>

    <?php
    echo htmlspecialchars(
        $supervisor_name
    );
    ?>

</td>

</tr>

</table>


<!-- =====================================================
     TASK REPORT TABLE
====================================================== -->

<table class="report-table">

<thead>

<tr>

<th>
    S/N
</th>

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
    Hours Worked
</th>

<th>
    Status
</th>

</tr>

</thead>


<tbody>


<?php if (count($report_data) > 0): ?>

<?php

$sn = 1;

foreach ($report_data as $row):

?>


<tr>


<td class="center">

    <?php
    echo $sn++;
    ?>

</td>


<td>

    <?php

    if (!empty($row['report_date'])) {

        echo date(
            "d M Y",
            strtotime(
                $row['report_date']
            )
        );

    } else {

        echo "-";

    }

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
        ?: 'N/A'
    );
    ?>

</td>


<td>

    <?php

    echo nl2br(
        htmlspecialchars(
            $row['tasks_completed']
            ?: '-'
        )
    );

    ?>

</td>


<td class="center">

    <?php

    if (!empty($row['start_time'])) {

        echo date(
            "h:i A",
            strtotime(
                $row['start_time']
            )
        );

    } else {

        echo "-";

    }

    ?>

</td>


<td class="center">

    <?php

    if (!empty($row['end_time'])) {

        echo date(
            "h:i A",
            strtotime(
                $row['end_time']
            )
        );

    } else {

        echo "-";

    }

    ?>

</td>


<td class="number">

    <?php

    echo number_format(
        (float)$row['hours_worked'],
        2
    );

    ?>

</td>


<td class="center">

    <?php

    echo htmlspecialchars(
        $row['status']
        ?: 'N/A'
    );

    ?>

</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
    colspan="10"
    class="center"
>

    No task reports found for the selected
    staff member and date range.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>


<!-- =====================================================
     SUMMARY
====================================================== -->

<table class="summary-table">

<tr>

<td class="summary-label">
    Total Task Reports
</td>

<td>

    <?php
    echo $total_records;
    ?>

</td>


<td class="summary-label">
    Total Hours Worked
</td>

<td>

    <?php

    echo number_format(
        $total_hours,
        2
    );

    ?>

    hours

</td>

</tr>

</table>


<!-- =====================================================
     PRINTED BY
====================================================== -->

<table class="info-table">

<tr>

<td class="info-label">
    Printed By
</td>

<td>

    <?php
    echo htmlspecialchars(
        $supervisor_name
    );
    ?>

</td>


<td class="info-label">
    Supervisor ID
</td>

<td>

    <?php
    echo htmlspecialchars(
        $supervisor_id
    );
    ?>

</td>

</tr>


<tr>

<td class="info-label">
    Date Printed
</td>

<td>

    <?php
    echo $printed_date;
    ?>

</td>


<td class="info-label">
    Time Printed
</td>

<td>

    <?php
    echo $printed_time;
    ?>

</td>

</tr>

</table>


<p class="printed">

    This report was generated electronically
    from the Personnel Timesheet Management System.

</p>


</body>

</html>
<?php
exit;
?>