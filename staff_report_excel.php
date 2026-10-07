<?php

session_start();
include "config.php";

/* =========================================================
   CHECK LOGIN
========================================================= */

if (!isset($_SESSION['staff_id'])) {
    exit("Unauthorized access.");
}

$staff_id = $_SESSION['staff_id'];


/* =========================================================
   FETCH STAFF INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT
        staff.*,
        supervisors.fullname AS supervisor_name
    FROM staff
    LEFT JOIN supervisors
        ON staff.supervisor_id = supervisors.supervisor_id
    WHERE staff.staff_id = ?
");

if (!$stmt) {
    exit("Staff query failed: " . $conn->error);
}

$stmt->bind_param("s", $staff_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("Staff member not found.");
}

$staff = $result->fetch_assoc();

$stmt->close();


$fullname = $staff['fullname'];
$department = $staff['department'] ?? '';
$supervisor_name = $staff['supervisor_name'] ?? 'Not Assigned';


/* =========================================================
   REPORT SETTINGS
========================================================= */

$report_type = $_GET['report_type'] ?? 'task';

$from_date = $_GET['from_date'] ?? date('Y-m-01');

$to_date = $_GET['to_date'] ?? date('Y-m-d');


$allowed_reports = [
    'task',
    'attendance',
    'overtime'
];

if (!in_array($report_type, $allowed_reports)) {
    $report_type = 'task';
}


/* =========================================================
   EXCEL HEADERS
========================================================= */

$filename = "Staff_Report_" .
            ucfirst($report_type) . "_" .
            date("Y-m-d") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");

header(
    "Content-Disposition: attachment; filename=\"$filename\""
);

header("Pragma: no-cache");
header("Expires: 0");


/* =========================================================
   REPORT TITLE
========================================================= */

if ($report_type === 'task') {

    $report_title = "Task Report";

} elseif ($report_type === 'attendance') {

    $report_title = "Attendance Report";

} else {

    $report_title = "Overtime Report";

}


/* =========================================================
   EXCEL DOCUMENT
========================================================= */

echo '<html>';
echo '<head>';

echo '<meta charset="UTF-8">';

echo '<style>

body {
    font-family: Arial, sans-serif;
}

h1 {
    text-align: center;
}

h2 {
    text-align: center;
}

.info {
    margin-bottom: 20px;
}

table {
    border-collapse: collapse;
    width: 100%;
}

th {
    background-color: #dddddd;
    font-weight: bold;
}

th, td {
    border: 1px solid #000;
    padding: 8px;
}

.summary {
    font-weight: bold;
    background-color: #eeeeee;
}

</style>';

echo '</head>';

echo '<body>';


/* =========================================================
   HEADER
========================================================= */

echo '<h1>';

echo htmlspecialchars(
    $app['organization_name']
);

echo '</h1>';


echo '<h2>';

echo htmlspecialchars(
    $report_title
);

echo '</h2>';


echo '<div class="info">';

echo '<strong>Staff Name:</strong> ';
echo htmlspecialchars($fullname);
echo '<br>';

echo '<strong>Staff ID:</strong> ';
echo htmlspecialchars($staff_id);
echo '<br>';

echo '<strong>Department:</strong> ';
echo htmlspecialchars($department);
echo '<br>';

echo '<strong>Supervisor:</strong> ';
echo htmlspecialchars($supervisor_name);
echo '<br>';

echo '<strong>Period:</strong> ';
echo date("d M Y", strtotime($from_date));
echo ' - ';
echo date("d M Y", strtotime($to_date));

echo '</div>';


/* =========================================================
   TASK REPORT
========================================================= */

if ($report_type === 'task') {

    $stmt = $conn->prepare("
        SELECT
            report_date,
            start_time,
            end_time,
            hours_worked,
            tasks_completed,
            status
        FROM task_reports
        WHERE staff_id = ?
        AND report_date BETWEEN ? AND ?
        ORDER BY report_date DESC, created_at DESC
    ");

    $stmt->bind_param(
        "sss",
        $staff_id,
        $from_date,
        $to_date
    );

    $stmt->execute();

    $result = $stmt->get_result();


    echo '<table>';

    echo '<tr>';

    echo '<th>Date</th>';
    echo '<th>Task</th>';
    echo '<th>Start Time</th>';
    echo '<th>End Time</th>';
    echo '<th>Hours Worked</th>';
    echo '<th>Status</th>';

    echo '</tr>';


    $total_hours = 0;
    $records = 0;


    while ($row = $result->fetch_assoc()) {

        $records++;

        $total_hours += (float)$row['hours_worked'];


        echo '<tr>';

        echo '<td>';
        echo date(
            "d M Y",
            strtotime($row['report_date'])
        );
        echo '</td>';


        echo '<td>';
        echo htmlspecialchars(
            $row['tasks_completed']
        );
        echo '</td>';


        echo '<td>';
        echo !empty($row['start_time'])
            ? date(
                "h:i A",
                strtotime($row['start_time'])
            )
            : '-';
        echo '</td>';


        echo '<td>';
        echo !empty($row['end_time'])
            ? date(
                "h:i A",
                strtotime($row['end_time'])
            )
            : '-';
        echo '</td>';


        echo '<td>';
        echo number_format(
            (float)$row['hours_worked'],
            2
        );
        echo '</td>';


        echo '<td>';
        echo htmlspecialchars(
            $row['status']
        );
        echo '</td>';

        echo '</tr>';

    }


    echo '<tr class="summary">';

    echo '<td colspan="4">TOTAL</td>';

    echo '<td>';
    echo number_format($total_hours, 2);
    echo '</td>';

    echo '<td>';
    echo $records . ' Task Reports';
    echo '</td>';

    echo '</tr>';

    echo '</table>';

}


/* =========================================================
   ATTENDANCE REPORT
========================================================= */

elseif ($report_type === 'attendance') {

    $stmt = $conn->prepare("
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
        ORDER BY date DESC
    ");

    $stmt->bind_param(
        "sss",
        $staff_id,
        $from_date,
        $to_date
    );

    $stmt->execute();

    $result = $stmt->get_result();


    echo '<table>';

    echo '<tr>';

    echo '<th>Date</th>';
    echo '<th>Clock In</th>';
    echo '<th>Clock Out</th>';
    echo '<th>Hours</th>';
    echo '<th>Status</th>';

    echo '</tr>';


    $total_hours = 0;
    $total_days = 0;
    $total_overtime = 0;


    while ($row = $result->fetch_assoc()) {

        $time_in =
            $row['time_in']
            ?: $row['clock_in'];

        $time_out =
            $row['time_out']
            ?: $row['clock_out'];


        $day_hours = 0;


        if (
            !empty($time_in) &&
            !empty($time_out)
        ) {

            $start = strtotime($time_in);

            $end = strtotime($time_out);


            if ($end > $start) {

                $day_hours =
                    ($end - $start) / 3600;

                $total_hours += $day_hours;


                if ($day_hours > 8) {

                    $total_overtime +=
                        $day_hours - 8;

                }

            }

        }


        if (
            strtolower(
                $row['status']
            ) === 'present'
        ) {

            $total_days++;

        }


        echo '<tr>';

        echo '<td>';
        echo date(
            "d M Y",
            strtotime($row['date'])
        );
        echo '</td>';


        echo '<td>';

        echo !empty($time_in)
            ? date(
                "h:i A",
                strtotime($time_in)
            )
            : '-';

        echo '</td>';


        echo '<td>';

        echo !empty($time_out)
            ? date(
                "h:i A",
                strtotime($time_out)
            )
            : '-';

        echo '</td>';


        echo '<td>';

        echo number_format(
            $day_hours,
            2
        );

        echo '</td>';


        echo '<td>';

        echo htmlspecialchars(
            $row['status']
        );

        echo '</td>';

        echo '</tr>';

    }


    echo '<tr class="summary">';

    echo '<td colspan="3">SUMMARY</td>';

    echo '<td>';
    echo number_format(
        $total_hours,
        2
    );
    echo ' h';
    echo '</td>';

    echo '<td>';
    echo $total_days . ' Days Present';
    echo '</td>';

    echo '</tr>';


    echo '<tr class="summary">';

    echo '<td colspan="4">TOTAL OVERTIME</td>';

    echo '<td>';

    echo number_format(
        $total_overtime,
        2
    );

    echo ' h';

    echo '</td>';

    echo '</tr>';


    echo '</table>';

}


/* =========================================================
   OVERTIME REPORT
========================================================= */

elseif ($report_type === 'overtime') {

    $stmt = $conn->prepare("
        SELECT
            id,
            work_date,
            hours_worked,
            overtime_hours,
            status,
            created_at
        FROM timesheets
        WHERE staff_id = ?
        AND work_date BETWEEN ? AND ?
        AND overtime_hours > 0
        ORDER BY work_date DESC, created_at DESC
    ");

    $stmt->bind_param(
        "sss",
        $staff_id,
        $from_date,
        $to_date
    );

    $stmt->execute();

    $result = $stmt->get_result();


    echo '<table>';

    echo '<tr>';

    echo '<th>Date</th>';
    echo '<th>Regular Hours</th>';
    echo '<th>Overtime Hours</th>';
    echo '<th>Total Hours</th>';
    echo '<th>Status</th>';

    echo '</tr>';


    $total_hours = 0;
    $total_overtime = 0;
    $records = 0;


    while ($row = $result->fetch_assoc()) {

        $records++;


        $hours =
            (float)$row['hours_worked'];

        $overtime =
            (float)$row['overtime_hours'];

        $regular =
            $hours - $overtime;


        $total_hours += $hours;

        $total_overtime += $overtime;


        echo '<tr>';

        echo '<td>';

        echo date(
            "d M Y",
            strtotime(
                $row['work_date']
            )
        );

        echo '</td>';


        echo '<td>';

        echo number_format(
            $regular,
            2
        );

        echo ' h</td>';


        echo '<td>';

        echo number_format(
            $overtime,
            2
        );

        echo ' h</td>';


        echo '<td>';

        echo number_format(
            $hours,
            2
        );

        echo ' h</td>';


        echo '<td>';

        echo htmlspecialchars(
            $row['status']
        );

        echo '</td>';

        echo '</tr>';

    }


    echo '<tr class="summary">';

    echo '<td colspan="3">TOTAL</td>';

    echo '<td>';

    echo number_format(
        $total_hours,
        2
    );

    echo ' h';

    echo '</td>';

    echo '<td>';

    echo number_format(
        $total_overtime,
        2
    );

    echo ' h Overtime';

    echo '</td>';

    echo '</tr>';


    echo '</table>';

}


echo '</body>';
echo '</html>';

exit;