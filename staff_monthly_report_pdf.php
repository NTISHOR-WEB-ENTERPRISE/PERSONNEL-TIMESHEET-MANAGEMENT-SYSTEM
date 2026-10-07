<?php

session_start();

include "config.php";

/*
|--------------------------------------------------------------------------
| CHECK SUPERVISOR LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$fullname = $_SESSION['fullname'] ?? 'Supervisor';


/*
|--------------------------------------------------------------------------
| GET REPORT FILTERS
|--------------------------------------------------------------------------
*/

$staff_id = $_GET['staff_id'] ?? 'all';
$month    = $_GET['month'] ?? date('Y-m');


/*
|--------------------------------------------------------------------------
| VALIDATE MONTH
|--------------------------------------------------------------------------
*/

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$month_start = $month . '-01';
$month_end   = date(
    'Y-m-t',
    strtotime($month_start)
);


/*
|--------------------------------------------------------------------------
| REPORT TITLE
|--------------------------------------------------------------------------
*/

$report_month = date(
    'F Y',
    strtotime($month_start)
);


/*
|--------------------------------------------------------------------------
| FETCH SUPERVISOR INFORMATION
|--------------------------------------------------------------------------
*/

$supervisor_name = $fullname;

$stmt = $conn->prepare("
    SELECT fullname
    FROM supervisors
    WHERE supervisor_id = ?
    LIMIT 1
");

if ($stmt) {

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
}


/*
|--------------------------------------------------------------------------
| FETCH STAFF
|
| Only staff belonging to this supervisor
|--------------------------------------------------------------------------
*/

$staff_list = [];

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

if ($stmt) {

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
}


/*
|--------------------------------------------------------------------------
| REPORT DATA
|--------------------------------------------------------------------------
*/

$report_data = [];

$total_hours = 0;
$total_overtime = 0;
$days_present = 0;
$days_absent = 0;
$total_working_days = 0;


/*
|--------------------------------------------------------------------------
| DETERMINE STAFF SELECTION
|--------------------------------------------------------------------------
*/

$selected_staff = null;

if ($staff_id !== 'all') {

    $stmt = $conn->prepare("
        SELECT
            staff_id,
            fullname,
            department,
            position
        FROM staff
        WHERE staff_id = ?
        AND supervisor_id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "ss",
            $staff_id,
            $supervisor_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $selected_staff =
                $result->fetch_assoc();

        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| FETCH TIMESHEETS
|--------------------------------------------------------------------------
|
| We only fetch records belonging to staff under
| the logged-in supervisor.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.staff_id,
        s.fullname,
        s.department,
        s.position,
        t.work_date,
        t.clock_in,
        t.clock_out,
        t.hours_worked,
        t.overtime_hours,
        t.status
    FROM timesheets t
    INNER JOIN staff s
        ON t.staff_id = s.staff_id
    WHERE s.supervisor_id = ?
    AND t.work_date BETWEEN ? AND ?
";


if ($staff_id !== 'all') {

    $sql .= "
        AND t.staff_id = ?
    ";

    $sql .= "
        ORDER BY t.work_date ASC,
                 t.clock_in ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die(
            "Report query failed: " .
            $conn->error
        );
    }

    $stmt->bind_param(
        "ssss",
        $supervisor_id,
        $month_start,
        $month_end,
        $staff_id
    );

} else {

    $sql .= "
        ORDER BY
            s.fullname ASC,
            t.work_date ASC,
            t.clock_in ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die(
            "Report query failed: " .
            $conn->error
        );
    }

    $stmt->bind_param(
        "sss",
        $supervisor_id,
        $month_start,
        $month_end
    );
}


$stmt->execute();

$result = $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $report_data[] = $row;

    $hours =
        (float)($row['hours_worked'] ?? 0);

    $overtime =
        (float)($row['overtime_hours'] ?? 0);

    $total_hours += $hours;

    $total_overtime += $overtime;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| COUNT PRESENT DAYS
|--------------------------------------------------------------------------
|
| For All Staff:
| Count unique staff/date combinations.
|
| For Individual Staff:
| Count their working dates.
|
|--------------------------------------------------------------------------
*/

if ($staff_id !== 'all') {

    $present_dates = [];

    foreach ($report_data as $row) {

        $date = $row['work_date'];

        if (!empty($date)) {

            $present_dates[$date] = true;

        }

    }

    $days_present =
        count($present_dates);

} else {

    $present_staff_days = [];

    foreach ($report_data as $row) {

        $key =
            $row['staff_id'] .
            '_' .
            $row['work_date'];

        $present_staff_days[$key] = true;

    }

    $days_present =
        count($present_staff_days);
}


/*
|--------------------------------------------------------------------------
| WORKING DAYS IN MONTH
|--------------------------------------------------------------------------
|
| Monday-Friday are considered normal working days.
|
|--------------------------------------------------------------------------
*/

$working_dates = [];

$current_date =
    strtotime($month_start);

$last_date =
    strtotime($month_end);

while ($current_date <= $last_date) {

    $day_number =
        date('N', $current_date);

    if ($day_number <= 5) {

        $working_dates[] =
            date(
                'Y-m-d',
                $current_date
            );

    }

    $current_date =
        strtotime(
            '+1 day',
            $current_date
        );
}


$total_working_days =
    count($working_dates);


/*
|--------------------------------------------------------------------------
| ABSENT DAYS
|--------------------------------------------------------------------------
|
| For an individual staff member:
| working days - present days.
|
| For All Staff:
| calculate staff-working-days minus
| present staff-days.
|
|--------------------------------------------------------------------------
*/

if ($staff_id !== 'all') {

    $days_absent =
        max(
            0,
            $total_working_days -
            $days_present
        );

} else {

    $active_staff_count =
        count($staff_list);

    $expected_staff_days =
        $total_working_days *
        $active_staff_count;

    $days_absent =
        max(
            0,
            $expected_staff_days -
            $days_present
        );
}


/*
|--------------------------------------------------------------------------
| AVERAGE HOURS
|--------------------------------------------------------------------------
*/

if ($days_present > 0) {

    $average_hours =
        $total_hours /
        $days_present;

} else {

    $average_hours = 0;

}


/*
|--------------------------------------------------------------------------
| FPDF
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Change this path if your FPDF folder is elsewhere.
|
|--------------------------------------------------------------------------
*/

$fpdf_path =
    __DIR__ .
    "/fpdf186/fpdf.php";

if (!file_exists($fpdf_path)) {

    die(
        "FPDF not found.<br><br>" .
        "Expected location:<br>" .
        htmlspecialchars($fpdf_path)
    );

}

require_once $fpdf_path;


/*
|--------------------------------------------------------------------------
| CREATE PDF
|--------------------------------------------------------------------------
*/

class MonthlyReportPDF extends FPDF
{

    function Header()
    {

        global $app;
        global $report_month;

        $organization =
            $app['organization_name']
            ?? 'NTISHOR WEB ENTERPRISE';

        $this->SetFont(
            'Arial',
            'B',
            16
        );

        $this->Cell(
            0,
            8,
            strtoupper($organization),
            0,
            1,
            'C'
        );

        $this->SetFont(
            'Arial',
            'B',
            13
        );

        $this->Cell(
            0,
            8,
            'STAFF MONTHLY REPORT',
            0,
            1,
            'C'
        );

        $this->SetFont(
            'Arial',
            '',
            10
        );

        $this->Cell(
            0,
            7,
            $report_month,
            0,
            1,
            'C'
        );

        $this->Ln(4);

        $this->SetDrawColor(
            100,
            100,
            100
        );

        $this->Line(
            10,
            $this->GetY(),
            287,
            $this->GetY()
        );

        $this->Ln(5);

    }


    function Footer()
    {

        $this->SetY(-15);

        $this->SetFont(
            'Arial',
            '',
            8
        );

        $this->Cell(
            0,
            10,
            'Page ' .
            $this->PageNo(),
            0,
            0,
            'C'
        );

    }

}


/*
|--------------------------------------------------------------------------
| PDF SETTINGS
|--------------------------------------------------------------------------
*/

$pdf = new MonthlyReportPDF(
    'L',
    'mm',
    'A4'
);

$pdf->SetMargins(
    10,
    10,
    10
);

$pdf->SetAutoPageBreak(
    true,
    20
);

$pdf->AddPage();


/*
|--------------------------------------------------------------------------
| STAFF INFORMATION
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'Arial',
    'B',
    9
);

if ($staff_id === 'all') {

    $pdf->Cell(
        35,
        7,
        'Staff:',
        0,
        0
    );

    $pdf->SetFont(
        'Arial',
        '',
        9
    );

    $pdf->Cell(
        70,
        7,
        'All Staff',
        0,
        0
    );

} else {

    $pdf->Cell(
        35,
        7,
        'Staff:',
        0,
        0
    );

    $pdf->SetFont(
        'Arial',
        '',
        9
    );

    $pdf->Cell(
        70,
        7,
        $selected_staff['fullname']
        ?? 'Unknown',
        0,
        0
    );

}


$pdf->SetFont(
    'Arial',
    'B',
    9
);

$pdf->Cell(
    35,
    7,
    'Supervisor:',
    0,
    0
);

$pdf->SetFont(
    'Arial',
    '',
    9
);

$pdf->Cell(
    70,
    7,
    $supervisor_name,
    0,
    1
);


/*
|--------------------------------------------------------------------------
| SECOND INFORMATION ROW
|--------------------------------------------------------------------------
*/

if ($staff_id !== 'all' &&
    $selected_staff) {

    $pdf->SetFont(
        'Arial',
        'B',
        9
    );

    $pdf->Cell(
        35,
        7,
        'Staff ID:',
        0,
        0
    );

    $pdf->SetFont(
        'Arial',
        '',
        9
    );

    $pdf->Cell(
        70,
        7,
        $selected_staff['staff_id'],
        0,
        0
    );

    $pdf->SetFont(
        'Arial',
        'B',
        9
    );

    $pdf->Cell(
        35,
        7,
        'Department:',
        0,
        0
    );

    $pdf->SetFont(
        'Arial',
        '',
        9
    );

    $pdf->Cell(
        70,
        7,
        $selected_staff['department']
        ?? '',
        0,
        1
    );

}


$pdf->Ln(4);


/*
|--------------------------------------------------------------------------
| TABLE HEADER
|--------------------------------------------------------------------------
*/

$pdf->SetFillColor(
    230,
    230,
    230
);

$pdf->SetTextColor(
    0,
    0,
    0
);

$pdf->SetFont(
    'Arial',
    'B',
    8
);


/*
|--------------------------------------------------------------------------
| COLUMN WIDTHS
|--------------------------------------------------------------------------
*/

$w_staff =
    ($staff_id === 'all')
    ? 48
    : 0;

$w_date = 25;
$w_day = 25;
$w_in = 25;
$w_out = 25;
$w_hours = 25;
$w_ot = 25;
$w_status = 40;


/*
|--------------------------------------------------------------------------
| TABLE HEADERS
|--------------------------------------------------------------------------
*/

if ($staff_id === 'all') {

    $pdf->Cell(
        $w_staff,
        9,
        'Staff',
        1,
        0,
        'C',
        true
    );

}

$pdf->Cell(
    $w_date,
    9,
    'Date',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    $w_day,
    9,
    'Day',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    $w_in,
    9,
    'Clock In',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    $w_out,
    9,
    'Clock Out',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    $w_hours,
    9,
    'Hours Worked',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    $w_ot,
    9,
    'Overtime',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    $w_status,
    9,
    'Status',
    1,
    1,
    'C',
    true
);


/*
|--------------------------------------------------------------------------
| TABLE DATA
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'Arial',
    '',
    8
);


if (count($report_data) > 0) {

    foreach ($report_data as $row) {

        $date =
            $row['work_date'];

        $day =
            date(
                'l',
                strtotime($date)
            );

        $clock_in =
            !empty($row['clock_in'])
            ? date(
                'h:i A',
                strtotime(
                    $row['clock_in']
                )
            )
            : '-';

        $clock_out =
            !empty($row['clock_out'])
            ? date(
                'h:i A',
                strtotime(
                    $row['clock_out']
                )
            )
            : '-';

        $hours =
            number_format(
                (float)(
                    $row['hours_worked']
                    ?? 0
                ),
                2
            );

        $overtime =
            number_format(
                (float)(
                    $row['overtime_hours']
                    ?? 0
                ),
                2
            );

        $status =
            ucfirst(
                strtolower(
                    $row['status']
                    ?? 'Pending'
                )
            );


        if ($staff_id === 'all') {

            $pdf->Cell(
                $w_staff,
                8,
                substr(
                    $row['fullname'],
                    0,
                    28
                ),
                1
            );

        }


        $pdf->Cell(
            $w_date,
            8,
            date(
                'd M Y',
                strtotime($date)
            ),
            1,
            0,
            'C'
        );

        $pdf->Cell(
            $w_day,
            8,
            $day,
            1,
            0,
            'C'
        );

        $pdf->Cell(
            $w_in,
            8,
            $clock_in,
            1,
            0,
            'C'
        );

        $pdf->Cell(
            $w_out,
            8,
            $clock_out,
            1,
            0,
            'C'
        );

        $pdf->Cell(
            $w_hours,
            8,
            $hours . ' h',
            1,
            0,
            'C'
        );

        $pdf->Cell(
            $w_ot,
            8,
            $overtime . ' h',
            1,
            0,
            'C'
        );

        $pdf->Cell(
            $w_status,
            8,
            $status,
            1,
            1,
            'C'
        );

    }

} else {

    $columns_width =
        ($staff_id === 'all')
        ? $w_staff +
          $w_date +
          $w_day +
          $w_in +
          $w_out +
          $w_hours +
          $w_ot +
          $w_status
        :
          $w_date +
          $w_day +
          $w_in +
          $w_out +
          $w_hours +
          $w_ot +
          $w_status;

    $pdf->Cell(
        $columns_width,
        10,
        'No timesheet records found for the selected period.',
        1,
        1,
        'C'
    );

}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$pdf->Ln(7);

$pdf->SetFont(
    'Arial',
    'B',
    11
);

$pdf->Cell(
    0,
    7,
    'Monthly Summary',
    0,
    1
);

$pdf->SetFont(
    'Arial',
    '',
    9
);


$summary_items = [

    'Total Working Days' =>
        $total_working_days,

    'Days Present' =>
        $days_present,

    'Days Absent' =>
        $days_absent,

    'Total Hours Worked' =>
        number_format(
            $total_hours,
            2
        ) . ' h',

    'Total Overtime' =>
        number_format(
            $total_overtime,
            2
        ) . ' h',

    'Average Hours Per Day' =>
        number_format(
            $average_hours,
            2
        ) . ' h'

];


foreach (
    $summary_items
    as $label => $value
) {

    $pdf->SetFont(
        'Arial',
        'B',
        9
    );

    $pdf->Cell(
        55,
        7,
        $label . ':',
        0,
        0
    );

    $pdf->SetFont(
        'Arial',
        '',
        9
    );

    $pdf->Cell(
        45,
        7,
        $value,
        0,
        0
    );

}


/*
|--------------------------------------------------------------------------
| PRINTED BY
|--------------------------------------------------------------------------
*/

$pdf->Ln(10);

$pdf->SetDrawColor(
    150,
    150,
    150
);

$pdf->Line(
    10,
    $pdf->GetY(),
    287,
    $pdf->GetY()
);

$pdf->Ln(5);

$printed_date =
    date(
        'd M Y'
    );

$printed_time =
    date(
        'h:i A'
    );


$pdf->SetFont(
    'Arial',
    'B',
    9
);

$pdf->Cell(
    30,
    6,
    'Printed By:',
    0,
    0
);

$pdf->SetFont(
    'Arial',
    '',
    9
);

$pdf->Cell(
    70,
    6,
    $supervisor_name,
    0,
    0
);

$pdf->SetFont(
    'Arial',
    'B',
    9
);

$pdf->Cell(
    35,
    6,
    'Supervisor ID:',
    0,
    0
);

$pdf->SetFont(
    'Arial',
    '',
    9
);

$pdf->Cell(
    55,
    6,
    $supervisor_id,
    0,
    1
);


$pdf->SetFont(
    'Arial',
    'B',
    9
);

$pdf->Cell(
    30,
    6,
    'Date:',
    0,
    0
);

$pdf->SetFont(
    'Arial',
    '',
    9
);

$pdf->Cell(
    70,
    6,
    $printed_date,
    0,
    0
);

$pdf->SetFont(
    'Arial',
    'B',
    9
);

$pdf->Cell(
    35,
    6,
    'Time:',
    0,
    0
);

$pdf->SetFont(
    'Arial',
    '',
    9
);

$pdf->Cell(
    55,
    6,
    $printed_time,
    0,
    1
);


/*
|--------------------------------------------------------------------------
| DOWNLOAD PDF
|--------------------------------------------------------------------------
*/

$safe_month =
    str_replace(
        '-',
        '_',
        $month
    );

if ($staff_id === 'all') {

    $filename =
        'Staff_Monthly_Report_' .
        $safe_month .
        '.pdf';

} else {

    $filename =
        'Staff_Monthly_Report_' .
        $staff_id .
        '_' .
        $safe_month .
        '.pdf';

}


$pdf->Output(
    'D',
    $filename
);

exit();

?>