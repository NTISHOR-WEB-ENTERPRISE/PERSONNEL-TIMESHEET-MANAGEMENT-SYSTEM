<?php
session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    exit("Unauthorized access.");
}

$supervisor_id = $_SESSION['supervisor_id'];
$supervisor_name = $_SESSION['fullname'] ?? 'Supervisor';

$staff_id = $_GET['staff_id'] ?? 'all';
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');


/*
|--------------------------------------------------------------------------
| SUPERVISOR
|--------------------------------------------------------------------------
*/

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

    $supervisor =
        $result->fetch_assoc();

    $supervisor_name =
        $supervisor['fullname'];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| STAFF NAME
|--------------------------------------------------------------------------
*/

$staff_name = "All Staff";

if ($staff_id !== 'all') {

    $stmt = $conn->prepare("
        SELECT fullname
        FROM staff
        WHERE staff_id = ?
        AND supervisor_id = ?
    ");

    $stmt->bind_param(
        "ss",
        $staff_id,
        $supervisor_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result->num_rows > 0) {

        $staff =
            $result->fetch_assoc();

        $staff_name =
            $staff['fullname'];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| GET ATTENDANCE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.date,
        s.staff_id,
        s.fullname,
        s.department,
        s.position,
        a.time_in,
        a.time_out,
        a.clock_in,
        a.clock_out

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


if ($staff_id !== 'all') {

    $sql .= " AND s.staff_id = ?";

    $params[] = $staff_id;

    $types .= "s";
}


$sql .= "
    ORDER BY
        a.date ASC,
        s.fullname ASC
";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| PROCESS
|--------------------------------------------------------------------------
*/

$records = [];

$total_regular = 0;
$total_overtime = 0;
$total_hours = 0;

$staff_with_overtime = [];


while ($row = $result->fetch_assoc()) {

    $time_in =
        !empty($row['time_in'])
        ? $row['time_in']
        : $row['clock_in'];

    $time_out =
        !empty($row['time_out'])
        ? $row['time_out']
        : $row['clock_out'];


    if (
        empty($time_in) ||
        empty($time_out)
    ) {
        continue;
    }


    $start =
        strtotime($time_in);

    $end =
        strtotime($time_out);


    if ($end <= $start) {
        continue;
    }


    $hours =
        ($end - $start) / 3600;


    $regular_hours =
        min($hours, 8);

    $overtime_hours =
        max($hours - 8, 0);


    if ($overtime_hours <= 0) {
        continue;
    }


    $row['regular_hours'] =
        $regular_hours;

    $row['overtime_hours'] =
        $overtime_hours;

    $row['total_hours'] =
        $hours;


    $records[] = $row;


    $total_regular +=
        $regular_hours;

    $total_overtime +=
        $overtime_hours;

    $total_hours +=
        $hours;


    $staff_with_overtime[
        $row['staff_id']
    ] = true;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| EXCEL HEADERS
|--------------------------------------------------------------------------
*/

header(
    "Content-Type: application/vnd.ms-excel"
);

header(
    "Content-Disposition: attachment; filename=Overtime_Report_" .
    date('Y-m-d') .
    ".xls"
);

header(
    "Pragma: no-cache"
);

header(
    "Expires: 0"
);


/*
|--------------------------------------------------------------------------
| EXCEL
|--------------------------------------------------------------------------
*/

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
    font-weight: bold;
}

table {
    border-collapse: collapse;
    width: 100%;
}

th {
    background: #dddddd;
    font-weight: bold;
}

th,
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

<td colspan="7"
    class="title">

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

<td colspan="7"
    class="subtitle">

    OVERTIME REPORT

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
        $staff_name
    );
    ?>

</td>

</tr>


<tr>

<td colspan="7">

    Supervisor:

    <?php
    echo htmlspecialchars(
        $supervisor_name
    );
    ?>

</td>

</tr>


<tr>

<td colspan="7">

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

<tr>

<th>Date</th>

<th>Staff ID</th>

<th>Staff Name</th>

<th>Department</th>

<th>Regular Hours</th>

<th>Overtime Hours</th>

<th>Total Hours</th>

</tr>


<?php if (count($records) > 0): ?>

<?php foreach ($records as $row): ?>

<tr>

<td>
    <?php
    echo date(
        'd M Y',
        strtotime($row['date'])
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
        $row['department'] ?? ''
    );
    ?>
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

<td>
    <?php
    echo number_format(
        $row['total_hours'],
        2
    );
    ?> h
</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td colspan="7">
    No overtime records found.
</td>

</tr>

<?php endif; ?>

</table>


<br>


<table>

<tr>

<td class="summary">
    Staff With Overtime
</td>

<td>
    <?php
    echo count(
        $staff_with_overtime
    );
    ?>
</td>

</tr>


<tr>

<td class="summary">
    Total Regular Hours
</td>

<td>
    <?php
    echo number_format(
        $total_regular,
        2
    );
    ?> h
</td>

</tr>


<tr>

<td class="summary">
    Total Overtime Hours
</td>

<td>
    <?php
    echo number_format(
        $total_overtime,
        2
    );
    ?> h
</td>

</tr>


<tr>

<td class="summary">
    Total Hours
</td>

<td>
    <?php
    echo number_format(
        $total_hours,
        2
    );
    ?> h
</td>

</tr>

</table>


<br>


<table>

<tr>

<td>
    Printed By:
</td>

<td>
    <?php
    echo htmlspecialchars(
        $supervisor_name
    );
    ?>
</td>

</tr>


<tr>

<td>
    Supervisor ID:
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

<td>
    Date:
</td>

<td>
    <?php
    echo date('d M Y');
    ?>
</td>

</tr>


<tr>

<td>
    Time:
</td>

<td>
    <?php
    echo date('h:i A');
    ?>
</td>

</tr>

</table>


</body>

</html>