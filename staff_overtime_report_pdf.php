<?php
session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$supervisor_name = $_SESSION['fullname'] ?? 'Supervisor';

$staff_id = $_GET['staff_id'] ?? 'all';
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

/*
|--------------------------------------------------------------------------
| FPDF
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/fpdf186/fpdf.php';


/*
|--------------------------------------------------------------------------
| GET SUPERVISOR INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT fullname
    FROM supervisors
    WHERE supervisor_id = ?
");

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $supervisor = $result->fetch_assoc();
    $supervisor_name = $supervisor['fullname'];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| FETCH STAFF
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

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $staff = $result->fetch_assoc();

        $staff_name = $staff['fullname'];

    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| GET OVERTIME RECORDS
|--------------------------------------------------------------------------
|
| Overtime is calculated from attendance.
|
| Normal working hours = 8 hours.
|
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


$sql .= " ORDER BY a.date ASC, s.fullname ASC";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| PROCESS DATA
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


    $start = strtotime($time_in);
    $end = strtotime($time_out);


    if ($end <= $start) {
        continue;
    }


    $hours = ($end - $start) / 3600;


    /*
    |--------------------------------------------------------------------------
    | NORMAL WORKING HOURS
    |--------------------------------------------------------------------------
    */

    $regular_hours = min($hours, 8);

    $overtime_hours = max($hours - 8, 0);


    /*
    |--------------------------------------------------------------------------
    | ONLY SHOW OVERTIME
    |--------------------------------------------------------------------------
    */

    if ($overtime_hours <= 0) {
        continue;
    }


    $row['regular_hours'] = $regular_hours;
    $row['overtime_hours'] = $overtime_hours;
    $row['total_hours'] = $hours;


    $records[] = $row;


    $total_regular += $regular_hours;
    $total_overtime += $overtime_hours;
    $total_hours += $hours;


    $staff_with_overtime[$row['staff_id']] = true;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| PDF CLASS
|--------------------------------------------------------------------------
*/

class OvertimePDF extends FPDF
{
    function Header()
    {
        global $app;

        $this->SetFont(
            'Arial',
            'B',
            15
        );

        $this->Cell(
            0,
            8,
            strtoupper($app['organization_name']),
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
            'OVERTIME REPORT',
            0,
            1,
            'C'
        );

        $this->Ln(4);
    }

    function Footer()
    {
        $this->SetY(-15);

        $this->SetFont(
            'Arial',
            'I',
            8
        );

        $this->Cell(
            0,
            10,
            'Page ' . $this->PageNo(),
            0,
            0,
            'C'
        );
    }
}


/*
|--------------------------------------------------------------------------
| CREATE PDF
|--------------------------------------------------------------------------
*/

$pdf = new OvertimePDF(
    'L',
    'mm',
    'A4'
);

$pdf->SetMargins(
    10,
    10,
    10
);

$pdf->AddPage();


/*
|--------------------------------------------------------------------------
| REPORT INFORMATION
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'Arial',
    '',
    10
);

$pdf->Cell(
    0,
    6,
    'Period: ' .
    date('d M Y', strtotime($from_date)) .
    ' - ' .
    date('d M Y', strtotime($to_date)),
    0,
    1
);

$pdf->Cell(
    0,
    6,
    'Staff: ' . $staff_name,
    0,
    1
);

$pdf->Cell(
    0,
    6,
    'Supervisor: ' . $supervisor_name,
    0,
    1
);

$pdf->Cell(
    0,
    6,
    'Supervisor ID: ' . $supervisor_id,
    0,
    1
);

$pdf->Ln(5);


/*
|--------------------------------------------------------------------------
| TABLE HEADER
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'Arial',
    'B',
    8
);

$pdf->Cell(25, 8, 'Date', 1);
$pdf->Cell(25, 8, 'Staff ID', 1);
$pdf->Cell(45, 8, 'Staff Name', 1);
$pdf->Cell(35, 8, 'Department', 1);
$pdf->Cell(30, 8, 'Regular Hours', 1, 0, 'C');
$pdf->Cell(30, 8, 'Overtime Hours', 1, 0, 'C');
$pdf->Cell(30, 8, 'Total Hours', 1, 1, 'C');


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


if (count($records) > 0) {

    foreach ($records as $row) {

        $pdf->Cell(
            25,
            7,
            date(
                'd M Y',
                strtotime($row['date'])
            ),
            1
        );

        $pdf->Cell(
            25,
            7,
            $row['staff_id'],
            1
        );

        $pdf->Cell(
            45,
            7,
            substr(
                $row['fullname'],
                0,
                27
            ),
            1
        );

        $pdf->Cell(
            35,
            7,
            substr(
                $row['department'] ?? '',
                0,
                20
            ),
            1
        );

        $pdf->Cell(
            30,
            7,
            number_format(
                $row['regular_hours'],
                2
            ) . ' h',
            1,
            0,
            'C'
        );

        $pdf->Cell(
            30,
            7,
            number_format(
                $row['overtime_hours'],
                2
            ) . ' h',
            1,
            0,
            'C'
        );

        $pdf->Cell(
            30,
            7,
            number_format(
                $row['total_hours'],
                2
            ) . ' h',
            1,
            1,
            'C'
        );
    }

} else {

    $pdf->Cell(
        220,
        10,
        'No overtime records found for the selected period.',
        1,
        1,
        'C'
    );
}


$pdf->Ln(6);


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'Arial',
    'B',
    9
);

$pdf->Cell(
    60,
    8,
    'Staff With Overtime',
    1
);

$pdf->Cell(
    40,
    8,
    count($staff_with_overtime),
    1,
    1
);


$pdf->Cell(
    60,
    8,
    'Total Regular Hours',
    1
);

$pdf->Cell(
    40,
    8,
    number_format($total_regular, 2) . ' h',
    1,
    1
);


$pdf->Cell(
    60,
    8,
    'Total Overtime Hours',
    1
);

$pdf->Cell(
    40,
    8,
    number_format($total_overtime, 2) . ' h',
    1,
    1
);


$pdf->Cell(
    60,
    8,
    'Total Hours',
    1
);

$pdf->Cell(
    40,
    8,
    number_format($total_hours, 2) . ' h',
    1,
    1
);


$pdf->Ln(10);


/*
|--------------------------------------------------------------------------
| PRINTED BY
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'Arial',
    '',
    9
);

$pdf->Cell(
    0,
    6,
    'Printed By: ' . $supervisor_name,
    0,
    1
);

$pdf->Cell(
    0,
    6,
    'Supervisor ID: ' . $supervisor_id,
    0,
    1
);

$pdf->Cell(
    0,
    6,
    'Date: ' . date('d M Y'),
    0,
    1
);

$pdf->Cell(
    0,
    6,
    'Time: ' . date('h:i A'),
    0,
    1
);


/*
|--------------------------------------------------------------------------
| OUTPUT
|--------------------------------------------------------------------------
*/

$filename =
    'Overtime_Report_' .
    date('Y-m-d') .
    '.pdf';

$pdf->Output(
    'D',
    $filename
);