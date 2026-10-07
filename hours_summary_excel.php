<?php

session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    exit("Unauthorized access.");
}

$supervisor_id = $_SESSION['supervisor_id'];
$fullname = $_SESSION['fullname'] ?? 'Supervisor';


/* =========================================================
   GET FILTERS
========================================================= */

$staff_filter = $_GET['staff_id'] ?? 'all';
$from_date    = $_GET['from_date'] ?? date('Y-m-01');
$to_date      = $_GET['to_date'] ?? date('Y-m-d');


/* =========================================================
   FETCH STAFF UNDER SUPERVISOR
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
   VALIDATE STAFF
========================================================= */

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


/* =========================================================
   REPORT QUERY
========================================================= */

$report_data = [];

$grand_days = 0;
$grand_hours = 0;
$grand_regular_hours = 0;
$grand_overtime = 0;


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


$params = [
    $from_date,
    $to_date,
    $supervisor_id
];

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
    exit("Report query failed: " . $conn->error);
}

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result = $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $total_hours =
        (float)$row['total_hours'];

    $regular_hours =
        min(
            $total_hours,
            ((int)$row['days_present']) * 8
        );

    $overtime =
        max(
            0,
            $total_hours - $regular_hours
        );

    $row['regular_hours'] =
        $regular_hours;

    $row['overtime_hours'] =
        $overtime;

    $report_data[] = $row;

    $grand_days +=
        (int)$row['days_present'];

    $grand_hours +=
        $total_hours;

    $grand_regular_hours +=
        $regular_hours;

    $grand_overtime +=
        $overtime;
}

$stmt->close();


/* =========================================================
   STAFF DISPLAY
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
   EXCEL HEADERS
========================================================= */

$filename =
    'Hours_Summary_' .
    date('Y-m-d_H-i-s') .
    '.xls';


header(
    "Content-Type: application/vnd.ms-excel; charset=UTF-8"
);

header(
    "Content-Disposition: attachment; filename=\"$filename\""
);

header("Pragma: no-cache");
header("Expires: 0");

?>


<html>

<head>

<meta charset="UTF-8">

<style>

body {
    font-family: Arial, sans-serif;
}

.title {
    font-size: 18px;
    font-weight: bold;
    text-align: center;
}

.subtitle {
    font-size: 14px;
    font-weight: bold;
    text-align: center;
}

.info {
    font-size: 11px;
}

table {
    border-collapse: collapse;
    width: 100%;
}

th {
    background: #D9EAF7;
    font-weight: bold;
    border: 1px solid #000;
    padding: 7px;
}

td {
    border: 1px solid #000;
    padding: 7px;
}

.summary {
    font-weight: bold;
}

</style>

</head>

<body>


<table>

<tr>

<td
    colspan="7"
    class="title"
>
    <?php
    echo htmlspecialchars(
        strtoupper(
            $app['organization_name']
        )
    );
    ?>
</td>

</tr>


<tr>

<td
    colspan="7"
    class="subtitle"
>
    HOURS SUMMARY REPORT
</td>

</tr>


<tr>

<td colspan="7">

    Period:
    <?php
    echo date(
        'd M Y',
        strtotime($from_date)
    );
    ?>

    -

    <?php
    echo date(
        'd M Y',
        strtotime($to_date)
    );
    ?>

</td>

</tr>


<tr>

<td colspan="7">

    Staff:
    <?php
    echo htmlspecialchars(
        $selected_staff_name
    );
    ?>

</td>

</tr>


<tr>

<td colspan="7">

    Supervisor:
    <?php
    echo htmlspecialchars(
        $fullname
    );
    ?>

    |

    Supervisor ID:
    <?php
    echo htmlspecialchars(
        $supervisor_id
    );
    ?>

</td>

</tr>

</table>


<br>


<table>

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
        $row['total_hours'],
        2
    );
    ?> h
</td>

<td>
    <?php
    echo number_format(
        $row['regular_hours'],
        2
    );
    ?> h
</td>

<td>
    <?php
    echo number_format(
        $row['overtime_hours'],
        2
    );
    ?> h
</td>

</tr>

<?php endforeach; ?>


<?php if (
    count($report_data) === 0
): ?>

<tr>

<td
    colspan="7"
    style="text-align:center;"
>
    No attendance records found.
</td>

</tr>

<?php endif; ?>

</tbody>


<tfoot>

<tr class="summary">

<td colspan="3">
    TOTAL
</td>

<td>
    <?php
    echo $grand_days;
    ?>
</td>

<td>
    <?php
    echo number_format(
        $grand_hours,
        2
    );
    ?> h
</td>

<td>
    <?php
    echo number_format(
        $grand_regular_hours,
        2
    );
    ?> h
</td>

<td>
    <?php
    echo number_format(
        $grand_overtime,
        2
    );
    ?> h
</td>

</tr>

</tfoot>

</table>


<br>


<table>

<tr>

<td colspan="7">

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
echo date('d M Y');
?>

&nbsp; | &nbsp;

<strong>
Time:
</strong>

<?php
echo date('h:i A');
?>

</td>

</tr>

</table>


</body>

</html>

<?php
exit();
?>