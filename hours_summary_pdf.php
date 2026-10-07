<?php

session_start();
include "config.php";

/* =========================================================
   CHECK SUPERVISOR LOGIN
========================================================= */

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$fullname = $_SESSION['fullname'] ?? 'Supervisor';

/* =========================================================
   FPDF
   If your fpdf186 folder is in the PTSMS root:
   PTSMS/fpdf186/fpdf.php
========================================================= */

require_once __DIR__ . '/fpdf186/fpdf.php';


/* =========================================================
   GET FILTERS
========================================================= */

$staff_filter = $_GET['staff_id'] ?? 'all';
$from_date    = $_GET['from_date'] ?? date('Y-m-01');
$to_date      = $_GET['to_date'] ?? date('Y-m-d');


/* =========================================================
   FETCH STAFF BELONGING TO SUPERVISOR
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

if (!$stmt) {
    die("Staff query failed: " . $conn->error);
}

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $staff_list[] = $row;
}

$stmt->close();


/* =========================================================
   VALIDATE STAFF FILTER
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
   FETCH REPORT DATA
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
    die("Hours Summary query failed: " . $conn->error);
}

$stmt->bind_param($types, ...$params);

$stmt->execute();

$result = $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $total_hours = (float)$row['total_hours'];

    /*
     * 8 hours is considered a normal working day.
     */

    $regular_hours = min(
        $total_hours,
        ((int)$row['days_present']) * 8
    );

    $overtime = max(
        0,
        $total_hours - $regular_hours
    );

    $row['regular_hours'] = $regular_hours;
    $row['overtime_hours'] = $overtime;

    $report_data[] = $row;

    $grand_days += (int)$row['days_present'];
    $grand_hours += $total_hours;
    $grand_regular_hours += $regular_hours;
    $grand_overtime += $overtime;
}

$stmt->close();


/* =========================================================
   CREATE PDF
========================================================= */

$pdf = new FPDF('L', 'mm', 'A4');

$pdf->SetAutoPageBreak(true, 15);

$pdf->AddPage();


/* =========================================================
   ORGANIZATION NAME
========================================================= */

$pdf->SetFont('Arial', 'B', 16);

$pdf->Cell(
    0,
    8,
    strtoupper($app['organization_name']),
    0,
    1,
    'C'
);


/* =========================================================
   REPORT TITLE
========================================================= */

$pdf->SetFont('Arial', 'B', 13);

$pdf->Cell(
    0,
    8,
    'HOURS SUMMARY REPORT',
    0,
    1,
    'C'
);


/* =========================================================
   PERIOD
========================================================= */

$pdf->SetFont('Arial', '', 10);

$period =
    'Period: ' .
    date('d M Y', strtotime($from_date)) .
    ' - ' .
    date('d M Y', strtotime($to_date));

$pdf->Cell(
    0,
    7,
    $period,
    0,
    1,
    'C'
);

$pdf->Ln(5);


/* =========================================================
   REPORT DETAILS
========================================================= */

$pdf->SetFont('Arial', '', 9);

$selected_staff_name = 'All Staff';

if ($staff_filter !== 'all') {

    foreach ($staff_list as $staff) {

        if ($staff['staff_id'] === $staff_filter) {

            $selected_staff_name =
                $staff['fullname'] .
                ' (' .
                $staff['staff_id'] .
                ')';

            break;
        }
    }
}

$pdf->Cell(
    90,
    7,
    'Staff: ' . $selected_staff_name,
    0,
    0
);

$pdf->Cell(
    90,
    7,
    'Supervisor: ' . $fullname,
    0,
    0
);

$pdf->Cell(
    90,
    7,
    'Supervisor ID: ' . $supervisor_id,
    0,
    1
);

$pdf->Ln(4);


/* =========================================================
   TABLE HEADER
========================================================= */

$pdf->SetFont('Arial', 'B', 8);

$pdf->Cell(28, 9, 'Staff ID', 1, 0, 'C');
$pdf->Cell(55, 9, 'Staff Name', 1, 0, 'C');
$pdf->Cell(45, 9, 'Department', 1, 0, 'C');
$pdf->Cell(25, 9, 'Days Present', 1, 0, 'C');
$pdf->Cell(35, 9, 'Total Hours', 1, 0, 'C');
$pdf->Cell(35, 9, 'Regular Hours', 1, 0, 'C');
$pdf->Cell(35, 9, 'Overtime', 1, 1, 'C');


/* =========================================================
   TABLE DATA
========================================================= */

$pdf->SetFont('Arial', '', 8);

if (count($report_data) > 0) {

    foreach ($report_data as $row) {

        $pdf->Cell(
            28,
            8,
            $row['staff_id'],
            1
        );

        $pdf->Cell(
            55,
            8,
            substr($row['fullname'], 0, 30),
            1
        );

        $pdf->Cell(
            45,
            8,
            substr($row['department'] ?? '', 0, 25),
            1
        );

        $pdf->Cell(
            25,
            8,
            $row['days_present'],
            1,
            0,
            'C'
        );

        $pdf->Cell(
            35,
            8,
            number_format(
                $row['total_hours'],
                2
            ) . ' h',
            1,
            0,
            'C'
        );

        $pdf->Cell(
            35,
            8,
            number_format(
                $row['regular_hours'],
                2
            ) . ' h',
            1,
            0,
            'C'
        );

        $pdf->Cell(
            35,
            8,
            number_format(
                $row['overtime_hours'],
                2
            ) . ' h',
            1,
            1,
            'C'
        );
    }

} else {

    $pdf->Cell(
        258,
        10,
        'No attendance records found.',
        1,
        1,
        'C'
    );
}


/* =========================================================
   SUMMARY
========================================================= */

$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 9);

$pdf->Cell(
    60,
    8,
    'Total Staff: ' . count($report_data),
    0,
    0
);

$pdf->Cell(
    65,
    8,
    'Days Present: ' . $grand_days,
    0,
    0
);

$pdf->Cell(
    70,
    8,
    'Total Hours: ' .
    number_format($grand_hours, 2) . ' h',
    0,
    0
);

$pdf->Cell(
    70,
    8,
    'Total Overtime: ' .
    number_format($grand_overtime, 2) . ' h',
    0,
    1
);


/* =========================================================
   PRINTED BY
========================================================= */

$pdf->Ln(8);

$pdf->SetFont('Arial', '', 8);

$pdf->Cell(
    0,
    6,
    'Printed By: ' .
    $fullname .
    ' | Supervisor ID: ' .
    $supervisor_id .
    ' | Date: ' .
    date('d M Y') .
    ' | Time: ' .
    date('h:i A'),
    0,
    1
);


/* =========================================================
   OUTPUT
========================================================= */

$filename =
    'Hours_Summary_' .
    date('Y-m-d_H-i-s') .
    '.pdf';

$pdf->Output(
    'D',
    $filename
);

exit();
?>