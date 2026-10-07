<?php
session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];

/*
|--------------------------------------------------------------------------
| GET FILTERS
|--------------------------------------------------------------------------
*/

$staff_id = $_GET['staff_id'] ?? 'all';
$month   = $_GET['month'] ?? date('Y-m');

/*
|--------------------------------------------------------------------------
| MONTH DATES
|--------------------------------------------------------------------------
*/

$start_date = date('Y-m-01', strtotime($month));
$end_date   = date('Y-m-t', strtotime($month));

/*
|--------------------------------------------------------------------------
| SUPERVISOR INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT supervisor_id, fullname
    FROM supervisors
    WHERE supervisor_id = ?
");

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$supervisor = $stmt->get_result()->fetch_assoc();

$stmt->close();

$supervisor_name = $supervisor['fullname'] ?? 'Supervisor';

/*
|--------------------------------------------------------------------------
| EXCEL HEADERS
|--------------------------------------------------------------------------
*/

header("Content-Type: application/vnd.ms-excel");
header(
    "Content-Disposition: attachment; filename=\"Staff_Monthly_Attendance_Report_" .
    date('F_Y', strtotime($month)) .
    ".xls\""
);

header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| ORGANIZATION
|--------------------------------------------------------------------------
*/

$organization_name =
    $app['organization_name'] ?? 'NTISHOR WEB ENTERPRISE';

?>

<!DOCTYPE html>
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
    font-size: 15px;
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
    background: #d9eaf7;
    font-weight: bold;
    border: 1px solid #000;
    padding: 8px;
    text-align: center;
}

td {
    border: 1px solid #000;
    padding: 7px;
}

.center {
    text-align: center;
}

</style>

</head>

<body>

<!-- =========================================================
     REPORT HEADER
========================================================= -->

<table>

<tr>
    <td colspan="11" class="title">
        <?php echo htmlspecialchars($organization_name); ?>
    </td>
</tr>

<tr>
    <td colspan="11" class="subtitle">
        STAFF MONTHLY ATTENDANCE AND TIMESHEET REPORT
    </td>
</tr>

<tr>
    <td colspan="11" class="subtitle">
        <?php echo date("F Y", strtotime($month)); ?>
    </td>
</tr>

</table>

<br>

<!-- =========================================================
     STAFF INFORMATION
========================================================= -->

<table>

<tr>
    <td class="info">Supervisor</td>
    <td>
        <?php echo htmlspecialchars($supervisor_name); ?>
    </td>

    <td class="info">Supervisor ID</td>
    <td>
        <?php echo htmlspecialchars($supervisor_id); ?>
    </td>
</tr>

</table>

<br>

<!-- =========================================================
     REPORT DATA
========================================================= -->

<table>

<thead>

<tr>

    <th>Staff ID</th>

    <th>Staff Name</th>

    <th>Department</th>

    <th>Position</th>

    <th>Hours</th>

    <th>Present</th>

    <th>Absent</th>

    <th>OT</th>

    <th>Late</th>

    <th>Early</th>

    <th>Attend %</th>

</tr>

</thead>

<tbody>

<?php

/*
|--------------------------------------------------------------------------
| STAFF QUERY
|--------------------------------------------------------------------------
*/

if ($staff_id === 'all') {

    $stmt = $conn->prepare("
        SELECT
            staff_id,
            fullname,
            department,
            position
        FROM staff
        WHERE supervisor_id = ?
        AND status = 'Active'
        ORDER BY fullname ASC
    ");

    $stmt->bind_param(
        "s",
        $supervisor_id
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            staff_id,
            fullname,
            department,
            position
        FROM staff
        WHERE supervisor_id = ?
        AND staff_id = ?
        AND status = 'Active'
    ");

    $stmt->bind_param(
        "ss",
        $supervisor_id,
        $staff_id
    );
}

$stmt->execute();

$staff_result = $stmt->get_result();

while ($staff = $staff_result->fetch_assoc()) {

    $current_staff_id = $staff['staff_id'];

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE
    |--------------------------------------------------------------------------
    */

    $attendance_stmt = $conn->prepare("
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
        ORDER BY date ASC
    ");

    $attendance_stmt->bind_param(
        "sss",
        $current_staff_id,
        $start_date,
        $end_date
    );

    $attendance_stmt->execute();

    $attendance_result =
        $attendance_stmt->get_result();

    /*
    |--------------------------------------------------------------------------
    | CALCULATIONS
    |--------------------------------------------------------------------------
    */

    $hours_present = 0;

    $present = 0;
    $absent = 0;
    $overtime = 0;
    $late = 0;
    $early = 0;

    /*
     * Number of working days.
     *
     * Monday-Friday
     */

    $working_days = 0;

    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        (new DateTime($end_date))
            ->modify('+1 day')
    );

    foreach ($period as $day) {

        $day_number =
            (int)$day->format('N');

        if ($day_number <= 5) {
            $working_days++;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | READ ATTENDANCE
    |--------------------------------------------------------------------------
    */

    while ($attendance = $attendance_result->fetch_assoc()) {

        $status =
            strtolower(
                trim(
                    $attendance['status'] ?? ''
                )
            );

        $time_in =
            $attendance['time_in']
            ?: $attendance['clock_in'];

        $time_out =
            $attendance['time_out']
            ?: $attendance['clock_out'];

        if ($status === 'present') {

            $present++;

        } else {

            /*
             * Only count absent if an actual
             * absent record exists.
             */

            if ($status === 'absent') {
                $absent++;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | HOURS
        |--------------------------------------------------------------------------
        */

        if (
            !empty($time_in) &&
            !empty($time_out)
        ) {

            $start =
                strtotime($time_in);

            $end =
                strtotime($time_out);

            if ($end > $start) {

                $daily_hours =
                    ($end - $start) / 3600;

                $hours_present +=
                    $daily_hours;

                /*
                 * Overtime above 8 hours
                 */

                if ($daily_hours > 8) {

                    $overtime +=
                        $daily_hours - 8;
                }

                /*
                 * Late after 08:00 AM
                 */

                if (
                    strtotime($time_in) >
                    strtotime('08:00:00')
                ) {

                    $late++;
                }

                /*
                 * Early departure before 05:00 PM
                 */

                if (
                    strtotime($time_out) <
                    strtotime('17:00:00')
                ) {

                    $early++;
                }
            }
        }
    }

    $attendance_stmt->close();

    /*
    |--------------------------------------------------------------------------
    | CALCULATE ABSENT DAYS
    |--------------------------------------------------------------------------
    */

    if ($working_days > 0) {

        $absent =
            max(
                0,
                $working_days - $present
            );
    }

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE PERCENTAGE
    |--------------------------------------------------------------------------
    */

    $attendance_percentage = 0;

    if ($working_days > 0) {

        $attendance_percentage =
            ($present / $working_days) * 100;
    }

?>

<tr>

    <td>
        <?php
        echo htmlspecialchars(
            $staff['staff_id']
        );
        ?>
    </td>

    <td>
        <?php
        echo htmlspecialchars(
            $staff['fullname']
        );
        ?>
    </td>

    <td>
        <?php
        echo htmlspecialchars(
            $staff['department'] ?? ''
        );
        ?>
    </td>

    <td>
        <?php
        echo htmlspecialchars(
            $staff['position'] ?? ''
        );
        ?>
    </td>

    <td class="center">
        <?php
        echo number_format(
            $hours_present,
            2
        );
        ?>
    </td>

    <td class="center">
        <?php echo $present; ?>
    </td>

    <td class="center">
        <?php echo $absent; ?>
    </td>

    <td class="center">
        <?php
        echo number_format(
            $overtime,
            2
        );
        ?>
    </td>

    <td class="center">
        <?php echo $late; ?>
    </td>

    <td class="center">
        <?php echo $early; ?>
    </td>

    <td class="center">
        <?php
        echo number_format(
            $attendance_percentage,
            0
        );
        ?>%
    </td>

</tr>

<?php

}

$stmt->close();

?>

</tbody>

</table>

<br>

<!-- =========================================================
     PRINTED BY
========================================================= -->

<table>

<tr>

    <td colspan="11" class="info">

        Printed By:

        <?php
        echo htmlspecialchars(
            $supervisor_name
        );
        ?>

        |

        Supervisor ID:

        <?php
        echo htmlspecialchars(
            $supervisor_id
        );
        ?>

        |

        Date:

        <?php
        echo date("d M Y");
        ?>

        |

        Time:

        <?php
        echo date("h:i A");
        ?>

    </td>

</tr>

</table>

</body>

</html>