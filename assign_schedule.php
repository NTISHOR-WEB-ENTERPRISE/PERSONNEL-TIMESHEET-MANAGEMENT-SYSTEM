<?php

session_start();

include "config.php";
include "activity_logger.php";


/* ==============================
   ADMIN AUTHENTICATION
============================== */

if (!isset($_SESSION['admin_id'])) {

    header("Location: admin_login.php");
    exit();

}



$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];


/* ==============================
   GET STAFF ID
============================== */

if (!isset($_GET['staff_id']) || empty($_GET['staff_id'])) {

    header("Location: manage_staff.php");
    exit();

}

$staff_id = $_GET['staff_id'];


/* ==============================
   GET STAFF INFORMATION
============================== */

$sql = "
    SELECT
        staff.staff_id,
        staff.fullname,
        staff.department,
        staff.supervisor_id,
        supervisors.fullname AS supervisor_name

    FROM staff

    LEFT JOIN supervisors
        ON staff.supervisor_id = supervisors.supervisor_id

    WHERE staff.staff_id = ?
";


$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $staff_id);

$stmt->execute();

$result = $stmt->get_result();

$staff = $result->fetch_assoc();


if (!$staff) {

    die("Staff member not found.");

}


$supervisor_id = $staff['supervisor_id'];


/* ==============================
   DAYS OF THE WEEK
============================== */

$days = [

    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
    "Sunday"

];


/* ==============================
   GET EXISTING SCHEDULE
============================== */

$existing_schedule = [];

$schedule_query = $conn->prepare("
    SELECT *
    FROM work_schedule
    WHERE staff_id = ?
");

$schedule_query->bind_param("s", $staff_id);

$schedule_query->execute();

$schedule_result = $schedule_query->get_result();


while ($row = $schedule_result->fetch_assoc()) {

    $existing_schedule[$row['day_name']] = $row;

}


/* ==============================
   SAVE SCHEDULE
============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $success = true;

    foreach ($days as $day) {

        $shift = $_POST['shift'][$day] ?? 'Morning';

        $start_time = $_POST['start_time'][$day] ?? '08:00';

        $end_time = $_POST['end_time'][$day] ?? '17:00';

        $status = $_POST['status'][$day] ?? 'Working';


        /* ==============================
           CHECK IF DAY ALREADY EXISTS
        ============================== */

        $check = $conn->prepare("
            SELECT id
            FROM work_schedule
            WHERE staff_id = ?
            AND day_name = ?
        ");

        $check->bind_param(
            "ss",
            $staff_id,
            $day
        );

        $check->execute();

        $check_result = $check->get_result();


        /* ==============================
           UPDATE EXISTING DAY
        ============================== */

        if ($check_result->num_rows > 0) {

            $row = $check_result->fetch_assoc();

            $schedule_id = $row['id'];


            $update = $conn->prepare("
                UPDATE work_schedule

                SET
                    supervisor_id = ?,
                    shift = ?,
                    start_time = ?,
                    end_time = ?,
                    status = ?

                WHERE id = ?
            ");


            $update->bind_param(
                "sssssi",
                $supervisor_id,
                $shift,
                $start_time,
                $end_time,
                $status,
                $schedule_id
            );


            if (!$update->execute()) {

                $success = false;

            }

        }


        /* ==============================
           INSERT NEW DAY
        ============================== */

        else {

            $insert = $conn->prepare("
                INSERT INTO work_schedule
                (
                    staff_id,
                    supervisor_id,
                    day_name,
                    shift,
                    start_time,
                    end_time,
                    status
                )

                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");


            $insert->bind_param(
                "sssssss",
                $staff_id,
                $supervisor_id,
                $day,
                $shift,
                $start_time,
                $end_time,
                $status
            );


            if (!$insert->execute()) {

                $success = false;

            }

        }

    }


    /* ==============================
       RESULT
    ============================== */

    if ($success) {

        header(
            "Location: view_staff.php?id=" .
            urlencode($_GET['id'] ?? '') .
            "&schedule=success"
        );

        exit();

    } else {

        $error = "Some schedule records could not be saved.";

    }

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Assign Weekly Schedule</title>

<link rel="stylesheet" href="styles.css">


<style>

.schedule-container {

    max-width: 1100px;

    margin: 30px auto;

    background: #fff;

    padding: 30px;

    border-radius: 12px;

    box-shadow: 0 4px 15px rgba(0,0,0,.08);

}


.staff-header {

    background: #f5f6f8;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 25px;

}


.staff-header h2 {

    margin: 0 0 8px;

}


.staff-header p {

    margin: 5px 0;

    color: #666;

}


.schedule-table {

    width: 100%;

    border-collapse: collapse;

}


.schedule-table th,
.schedule-table td {

    padding: 12px;

    border-bottom: 1px solid #eee;

}


.schedule-table th {

    background: #f5f6f8;

    text-align: left;

}


.schedule-table input,
.schedule-table select {

    width: 100%;

    padding: 9px;

    border: 1px solid #ddd;

    border-radius: 6px;

    box-sizing: border-box;

}


.day-name {

    font-weight: bold;

}


.form-actions {

    margin-top: 25px;

    display: flex;

    gap: 10px;

}


.btn-save {

    background: #198754;

    color: white;

    border: none;

    padding: 12px 20px;

    border-radius: 6px;

    cursor: pointer;

}


.btn-cancel {

    background: #6c757d;

    color: white;

    padding: 12px 20px;

    border-radius: 6px;

    text-decoration: none;

}


.error {

    background: #f8d7da;

    color: #842029;

    padding: 12px;

    border-radius: 6px;

    margin-bottom: 20px;

}


@media(max-width:900px) {

    .schedule-container {

        overflow-x: auto;

    }

    .schedule-table {

        min-width: 800px;

    }

}

</style>

</head>


<body>


<div class="container">


<div class="main">


<div class="schedule-container">


<h2>Assign Weekly Schedule</h2>

<p>Set the working schedule for this staff member.</p>


<div class="staff-header">

<h2>

<?php echo htmlspecialchars($staff['fullname']); ?>

</h2>


<p>

<strong>Staff ID:</strong>

<?php echo htmlspecialchars($staff['staff_id']); ?>

</p>


<p>

<strong>Department:</strong>

<?php echo htmlspecialchars($staff['department']); ?>

</p>


<p>

<strong>Supervisor:</strong>

<?php

echo htmlspecialchars(

    $staff['supervisor_name'] ?? 'Not Assigned'

);

?>

</p>

</div>


<?php if (isset($error)): ?>

<div class="error">

<?php echo htmlspecialchars($error); ?>

</div>

<?php endif; ?>


<form method="POST">


<table class="schedule-table">


<thead>

<tr>

<th>Day</th>

<th>Shift</th>

<th>Start Time</th>

<th>End Time</th>

<th>Status</th>

</tr>

</thead>


<tbody>


<?php foreach ($days as $day): ?>


<?php

$current = $existing_schedule[$day] ?? null;

?>


<tr>


<td class="day-name">

<?php echo $day; ?>

</td>


<td>

<input

type="text"

name="shift[<?php echo $day; ?>]"

value="<?php

echo htmlspecialchars(

    $current['shift'] ?? 'Morning'

);

?>"

>

</td>


<td>

<input

type="time"

name="start_time[<?php echo $day; ?>]"

value="<?php

echo htmlspecialchars(

    $current['start_time'] ?? '08:00:00'

);

?>"

>

</td>


<td>

<input

type="time"

name="end_time[<?php echo $day; ?>]"

value="<?php

echo htmlspecialchars(

    $current['end_time'] ?? '17:00:00'

);

?>"

>

</td>


<td>

<select name="status[<?php echo $day; ?>]">


<option value="Working"

<?php

if (($current['status'] ?? 'Working') == 'Working')

echo 'selected';

?>

>

Working

</option>


<option value="Off Day"

<?php

if (($current['status'] ?? '') == 'Off Day')

echo 'selected';

?>

>

Off Day

</option>


<option value="Leave"

<?php

if (($current['status'] ?? '') == 'Leave')

echo 'selected';

?>

>

Leave

</option>


</select>

</td>


</tr>


<?php endforeach; ?>


</tbody>

</table>


<div class="form-actions">


<button type="submit" class="btn-save">

💾 Save Weekly Schedule

</button>


<a

href="view_staff.php?id=<?php echo urlencode($_GET['id'] ?? ''); ?>"

class="btn-cancel"

>

Cancel

</a>


</div>


</form>


</div>


</div>


</div>


</body>

</html>