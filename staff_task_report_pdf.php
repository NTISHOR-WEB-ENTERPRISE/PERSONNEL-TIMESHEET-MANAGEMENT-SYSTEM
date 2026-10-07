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
$supervisor_name = $_SESSION['fullname'] ?? 'Supervisor';


/* =========================================================
   GET FILTER VALUES
========================================================= */

$selected_staff_id = $_GET['staff_id'] ?? '';
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date'] ?? date('Y-m-d');


if (empty($selected_staff_id)) {
    die("No staff member selected.");
}


/* =========================================================
   VERIFY STAFF BELONGS TO SUPERVISOR
========================================================= */

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

if (!$stmt) {
    die("Staff query failed: " . $conn->error);
}

$stmt->bind_param(
    "ss",
    $selected_staff_id,
    $supervisor_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Staff member not found or not assigned to you.");
}

$staff = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   FETCH TASK REPORTS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        report_date,
        tasks_completed,
        start_time,
        end_time,
        hours_worked,
        status
    FROM task_reports
    WHERE staff_id = ?
    AND supervisor_id = ?
    AND report_date BETWEEN ? AND ?
    ORDER BY report_date ASC, created_at ASC
");

if (!$stmt) {
    die("Task report query failed: " . $conn->error);
}

$stmt->bind_param(
    "ssss",
    $selected_staff_id,
    $supervisor_id,
    $from_date,
    $to_date
);

$stmt->execute();

$result = $stmt->get_result();

$reports = [];

$total_hours = 0;

while ($row = $result->fetch_assoc()) {

    $reports[] = $row;

    $total_hours += (float)$row['hours_worked'];
}

$stmt->close();


/* =========================================================
   PRINT INFORMATION
========================================================= */

$printed_date = date("d M Y");

$printed_time = date("h:i A");


/* =========================================================
   LOAD FPDF
========================================================= */

$fpdf_path = __DIR__ . "/fpdf186/fpdf.php";

if (!file_exists($fpdf_path)) {

    die(
        "FPDF library not found.<br><br>" .
        "Expected location:<br>" .
        htmlspecialchars($fpdf_path)
    );
}

require_once $fpdf_path;


/* =========================================================
   CREATE PDF
========================================================= */

class TaskReportPDF extends FPDF
{
    function Header()
    {
        global $app;

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
            10,
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
            'STAFF TASK REPORT',
            0,
            1,
            'C'
        );

        $this->Ln(4);

        $this->SetDrawColor(
            80,
            80,
            80
        );

        $this->Line(
            10,
            $this->GetY(),
            200,
            $this->GetY()
        );

        $this->Ln(6);
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


/* =========================================================
   INITIALIZE PDF
========================================================= */

$pdf = new TaskReportPDF(
    'P',
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


/* =========================================================
   STAFF INFORMATION
========================================================= */

$pdf->SetFont(
    'Arial',
    '',
    10
);

$pdf->Cell(
    95,
    7,
    'Staff Name: ' . $staff['fullname'],
    0,
    0
);

$pdf->Cell(
    95,
    7,
    'Staff ID: ' . $staff['staff_id'],
    0,
    1
);

$pdf->Cell(
    95,
    7,
    'Department: ' . ($staff['department'] ?? '-'),
    0,
    0
);

$pdf->Cell(
    95,
    7,
    'Position: ' . ($staff['position'] ?? '-'),
    0,
    1
);

$pdf->Cell(
    95,
    7,
    'Supervisor: ' . $supervisor_name,
    0,
    0
);

$pdf->Cell(
    95,
    7,
    'Supervisor ID: ' . $supervisor_id,
    0,
    1
);

$pdf->Cell(
    95,
    7,
    'From: ' . date(
        'd M Y',
        strtotime($from_date)
    ),
    0,
    0
);

$pdf->Cell(
    95,
    7,
    'To: ' . date(
        'd M Y',
        strtotime($to_date)
    ),
    0,
    1
);

$pdf->Ln(7);


/* =========================================================
   TABLE HEADER
========================================================= */

$pdf->SetFont(
    'Arial',
    'B',
    9
);

$pdf->SetFillColor(
    230,
    230,
    230
);

$pdf->Cell(
    25,
    9,
    'Date',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    65,
    9,
    'Task Performed',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    25,
    9,
    'Start',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    25,
    9,
    'End',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    20,
    9,
    'Hours',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    30,
    9,
    'Status',
    1,
    1,
    'C',
    true
);


/* =========================================================
   TABLE DATA
========================================================= */

$pdf->SetFont(
    'Arial',
    '',
    8
);


if (count($reports) > 0) {

    foreach ($reports as $row) {

        $task =
            $row['tasks_completed']
            ?? '-';

        /*
         * Prevent extremely long task
         * text from breaking the table.
         */

        $task =
            str_replace(
                ["\r", "\n"],
                ' ',
                $task
            );

        if (strlen($task) > 45) {
            $task =
                substr($task, 0, 42)
                . '...';
        }


        $start =
            !empty($row['start_time'])
            ? date(
                'h:i A',
                strtotime(
                    $row['start_time']
                )
            )
            : '-';


        $end =
            !empty($row['end_time'])
            ? date(
                'h:i A',
                strtotime(
                    $row['end_time']
                )
            )
            : '-';


        $hours =
            number_format(
                (float)$row['hours_worked'],
                2
            );


        $status =
            $row['status']
            ?? '-';


        $pdf->Cell(
            25,
            8,
            date(
                'd M Y',
                strtotime(
                    $row['report_date']
                )
            ),
            1,
            0,
            'C'
        );

        $pdf->Cell(
            65,
            8,
            $task,
            1,
            0,
            'L'
        );

        $pdf->Cell(
            25,
            8,
            $start,
            1,
            0,
            'C'
        );

        $pdf->Cell(
            25,
            8,
            $end,
            1,
            0,
            'C'
        );

        $pdf->Cell(
            20,
            8,
            $hours . ' h',
            1,
            0,
            'C'
        );

        $pdf->Cell(
            30,
            8,
            $status,
            1,
            1,
            'C'
        );
    }

} else {

    $pdf->Cell(
        190,
        10,
        'No task reports found for the selected period.',
        1,
        1,
        'C'
    );
}


/* =========================================================
   SUMMARY
========================================================= */

$pdf->Ln(7);

$pdf->SetFont(
    'Arial',
    'B',
    10
);

$pdf->Cell(
    95,
    8,
    'Total Task Reports: ' . count($reports),
    1,
    0,
    'L'
);

$pdf->Cell(
    95,
    8,
    'Total Hours: ' .
    number_format(
        $total_hours,
        2
    ) . ' h',
    1,
    1,
    'L'
);


/* =========================================================
   PRINTED BY
========================================================= */

$pdf->Ln(10);

$pdf->SetFont(
    'Arial',
    'B',
    9
);

$pdf->Cell(
    0,
    6,
    'Printed By',
    0,
    1
);

$pdf->SetFont(
    'Arial',
    '',
    9
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

$pdf->Cell(
    0,
    6,
    'Date: ' . $printed_date,
    0,
    1
);

$pdf->Cell(
    0,
    6,
    'Time: ' . $printed_time,
    0,
    1
);


/* =========================================================
   OUTPUT PDF
========================================================= */

$filename =
    'Staff_Task_Report_' .
    $staff['staff_id'] .
    '_' .
    date('Ymd_His') .
    '.pdf';

$pdf->Output(
    'D',
    $filename
);

exit();
?>