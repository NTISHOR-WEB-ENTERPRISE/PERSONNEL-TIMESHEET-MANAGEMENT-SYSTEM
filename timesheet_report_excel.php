<?php
session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    die("Unauthorized Access");
}

$supervisor_id = $_SESSION['supervisor_id'];

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Timesheet_Report.xls");

echo "<table border='1'>
<tr>
<th>Staff ID</th>
<th>Staff Name</th>
<th>Date</th>
<th>Clock In</th>
<th>Clock Out</th>
<th>Regular Hrs</th>
<th>Overtime Hrs</th>
<th>Total Hrs</th>
<th>Work Done</th>
</tr>";

$query = mysqli_query($conn,
"SELECT
    t.staff_id,
    s.fullname,
    t.work_date,
    t.clock_in,
    t.clock_out,
    t.hours_worked,
    t.task_description
FROM timesheet t
INNER JOIN staff s
ON t.staff_id = s.staff_id
WHERE s.supervisor_id = '$supervisor_id'
ORDER BY t.work_date DESC");

while($row = mysqli_fetch_assoc($query))
{
    $hours = $row['hours_worked'];

    $regular = ($hours > 8) ? 8 : $hours;
    $overtime = ($hours > 8) ? ($hours - 8) : 0;
    $total = $hours;

    echo "<tr>
            <td>{$row['staff_id']}</td>
            <td>{$row['fullname']}</td>
            <td>{$row['work_date']}</td>
            <td>{$row['clock_in']}</td>
            <td>{$row['clock_out']}</td>
            <td>{$regular}</td>
            <td>{$overtime}</td>
            <td>{$total}</td>
            <td>{$row['task_description']}</td>
          </tr>";
}

echo "</table>";
?>