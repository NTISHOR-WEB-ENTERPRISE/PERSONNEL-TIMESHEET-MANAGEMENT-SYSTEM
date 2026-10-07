<?php
session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    die("Unauthorized Access");
}

$supervisor_id = $_SESSION['supervisor_id'];

$month = (int)$_GET['month'];
$year  = (int)$_GET['year'];

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Staff_Monthly_Report.xls");

echo "<table border='1'>";

echo "<tr>";
echo "<th>Staff ID</th>";
echo "<th>Staff Name</th>";
echo "<th>Department</th>";
echo "<th>Position</th>";

for ($d=1; $d<=$daysInMonth; $d++) {
    echo "<th>" . date('d-M', strtotime("$year-$month-$d")) . "</th>";
}

echo "<th>Total Hours</th>";
echo "<th>Days Present</th>";
echo "<th>Days Absent</th>";
echo "<th>Overtime</th>";
echo "<th>Late Arrivals</th>";
echo "<th>Early Departures</th>";
echo "<th>Attendance %</th>";
echo "</tr>";

$staffQuery = mysqli_query($conn,"
SELECT *
FROM staff
WHERE supervisor_id='$supervisor_id'
ORDER BY fullname
");

while($staff = mysqli_fetch_assoc($staffQuery))
{
    $staff_id = $staff['staff_id'];

    $records = [];

    $totalHours = 0;
    $overtime = 0;
    $present = 0;
    $late = 0;
    $early = 0;

    $timesheetQuery = mysqli_query($conn,"
    SELECT *
    FROM timesheets
    WHERE staff_id='$staff_id'
    AND MONTH(work_date)='$month'
    AND YEAR(work_date)='$year'
    ");

    while($t = mysqli_fetch_assoc($timesheetQuery))
    {
        $day = date('j', strtotime($t['work_date']));

        $records[$day] = $t['hours_worked'];

        $totalHours += $t['hours_worked'];
        $overtime += $t['overtime_hours'];
        $late += $t['late_arrival'];
        $early += $t['early_departure'];

        $present++;
    }

    $workingDays = 0;

    for($d=1; $d<=$daysInMonth; $d++)
    {
        $weekday = date('N', strtotime("$year-$month-$d"));

        if($weekday < 6)
            $workingDays++;
    }

    $absent = $workingDays - $present;

    if($absent < 0)
        $absent = 0;

   if($workingDays > 0)
{
    $attendance = round(
        (min($present,$workingDays) / $workingDays) * 100,
        2
    );
}
else
{
    $attendance = 0;
}

    echo "<tr>";

    echo "<td>{$staff['staff_id']}</td>";
    echo "<td>{$staff['fullname']}</td>";
    echo "<td>{$staff['department']}</td>";
    echo "<td>{$staff['position']}</td>";

    for($d=1; $d<=$daysInMonth; $d++)
    {
        $value = isset($records[$d]) ? $records[$d] : '';
        echo "<td>$value</td>";
    }

    echo "<td>$totalHours</td>";
    echo "<td>$present</td>";
    echo "<td>$absent</td>";
    echo "<td>$overtime</td>";
    echo "<td>$late</td>";
    echo "<td>$early</td>";
    echo "<td>{$attendance}%</td>";

    echo "</tr>";
}

echo "</table>";
?>