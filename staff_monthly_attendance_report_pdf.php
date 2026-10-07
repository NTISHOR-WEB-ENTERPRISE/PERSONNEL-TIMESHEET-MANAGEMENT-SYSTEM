<?php

session_start();

include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];

$month = $_GET['month'] ?? date('Y-m');

$selected_staff = $_GET['staff_id'] ?? 'all';


/* =========================================================
   SUPERVISOR INFORMATION
========================================================= */

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

if ($result->num_rows === 0) {
    die("Supervisor not found.");
}

$supervisor = $result->fetch_assoc();

$supervisor_name = $supervisor['fullname'];

$stmt->close();


/* =========================================================
   MONTH
========================================================= */

$month_start = $month . "-01";

$month_end = date(
    "Y-m-t",
    strtotime($month_start)
);

$month_name = date(
    "F Y",
    strtotime($month_start)
);


/* =========================================================
   GET STAFF
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
    AND status = 'Active'
    ORDER BY fullname ASC
");

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    if (
        $selected_staff !== 'all' &&
        $selected_staff !== $row['staff_id']
    ) {
        continue;
    }

    $staff_list[] = $row;
}

$stmt->close();


/* =========================================================
   REPORT DATA
========================================================= */

$report_data = [];


/* =========================================================
   PROCESS STAFF
========================================================= */

foreach ($staff_list as $staff) {

    $staff_id = $staff['staff_id'];

    $hours = 0;

    $present = 0;

    $absent = 0;

    $overtime = 0;

    $late = 0;

    $early = 0;

    $working_days_count = 0;


    /*
     * Go through every date in the month
     */

    $current_date = new DateTime($month_start);

    $end_date = new DateTime($month_end);


    while ($current_date <= $end_date) {

        $date = $current_date->format("Y-m-d");

        $day_name = $current_date->format("l");


        /* =================================================
           GET WORK SCHEDULE
        ================================================= */

        $schedule_stmt = $conn->prepare("
            SELECT
                start_time,
                end_time,
                status
            FROM work_schedule
            WHERE staff_id = ?
            AND supervisor_id = ?
            AND day_name = ?
            LIMIT 1
        ");

        $schedule_stmt->bind_param(
            "sss",
            $staff_id,
            $supervisor_id,
            $day_name
        );

        $schedule_stmt->execute();

        $schedule_result =
            $schedule_stmt->get_result();

        $schedule =
            $schedule_result->fetch_assoc();

        $schedule_stmt->close();


        /*
         * No schedule = don't count as working day
         */

        if (!$schedule) {

            $current_date->modify("+1 day");

            continue;
        }


        /*
         * Off day or leave
         */

        if ($schedule['status'] !== 'Working') {

            $current_date->modify("+1 day");

            continue;
        }


        $working_days_count++;


        /* =================================================
           GET ATTENDANCE
        ================================================= */

        $attendance_stmt = $conn->prepare("
            SELECT
                time_in,
                time_out,
                clock_in,
                clock_out,
                status
            FROM attendance
            WHERE staff_id = ?
            AND date = ?
            LIMIT 1
        ");

        $attendance_stmt->bind_param(
            "ss",
            $staff_id,
            $date
        );

        $attendance_stmt->execute();

        $attendance_result =
            $attendance_stmt->get_result();

        $attendance =
            $attendance_result->fetch_assoc();

        $attendance_stmt->close();


        /*
         * No attendance = absent
         */

        if (!$attendance) {

            $absent++;

            $current_date->modify("+1 day");

            continue;
        }


        /* =================================================
           TIME IN / TIME OUT
        ================================================= */

        $time_in =
            !empty($attendance['time_in'])
            ? $attendance['time_in']
            : $attendance['clock_in'];


        $time_out =
            !empty($attendance['time_out'])
            ? $attendance['time_out']
            : $attendance['clock_out'];


        /* =================================================
           PRESENT
        ================================================= */

        if (
            strtolower(
                trim($attendance['status'] ?? '')
            ) === 'present'
        ) {

            $present++;

        } elseif (!empty($time_in)) {

            $present++;

        } else {

            $absent++;
        }


        /* =================================================
           HOURS
        ================================================= */

        if (
            !empty($time_in) &&
            !empty($time_out)
        ) {

            $start =
                strtotime($time_in);

            $end =
                strtotime($time_out);


            if ($end > $start) {

                $day_hours =
                    ($end - $start) / 3600;

                $hours += $day_hours;


                /* -----------------------------------------
                   SCHEDULED HOURS
                ----------------------------------------- */

                $schedule_start =
                    strtotime(
                        $schedule['start_time']
                    );

                $schedule_end =
                    strtotime(
                        $schedule['end_time']
                    );

                $scheduled_hours =
                    (
                        $schedule_end -
                        $schedule_start
                    ) / 3600;


                /* -----------------------------------------
                   OVERTIME
                ----------------------------------------- */

                if (
                    $day_hours >
                    $scheduled_hours
                ) {

                    $overtime +=
                        $day_hours -
                        $scheduled_hours;
                }
            }
        }


        /* =================================================
           LATE
        ================================================= */

        if (!empty($time_in)) {

            $actual_in =
                strtotime($time_in);

            $official_in =
                strtotime(
                    $schedule['start_time']
                );

            if (
                $actual_in >
                $official_in
            ) {

                $late++;
            }
        }


        /* =================================================
           EARLY
        ================================================= */

        if (!empty($time_out)) {

            $actual_out =
                strtotime($time_out);

            $official_out =
                strtotime(
                    $schedule['end_time']
                );

            if (
                $actual_out <
                $official_out
            ) {

                $early++;
            }
        }


        $current_date->modify("+1 day");
    }


    /* =====================================================
       ATTENDANCE %
    ===================================================== */

    $attendance_percentage = 0;

    if ($working_days_count > 0) {

        $attendance_percentage =
            (
                $present /
                $working_days_count
            ) * 100;
    }


    $report_data[] = [

        'staff_id' =>
            $staff['staff_id'],

        'fullname' =>
            $staff['fullname'],

        'department' =>
            $staff['department'],

        'position' =>
            $staff['position'],

        'hours' =>
            $hours,

        'present' =>
            $present,

        'absent' =>
            $absent,

        'overtime' =>
            $overtime,

        'late' =>
            $late,

        'early' =>
            $early,

        'attendance_percentage' =>
            $attendance_percentage

    ];
}


/* =========================================================
   FPDF
========================================================= */

require_once "fpdf186/fpdf.php";


class MonthlyAttendancePDF extends FPDF
{

    function Header()
    {

        global $app;
        global $month_name;

        $this->SetFont(
            'Arial',
            'B',
            15
        );

        $this->Cell(
            0,
            8,
            $app['organization_name'],
            0,
            1,
            'C'
        );


        $this->SetFont(
            'Arial',
            'B',
            11
        );

        $this->Cell(
            0,
            7,
            'STAFF MONTHLY ATTENDANCE AND TIMESHEET REPORT',
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
            6,
            $month_name,
            0,
            1,
            'C'
        );


        $this->Ln(5);
    }


    function Footer()
    {

        global $supervisor_name;
        global $supervisor_id;


        $this->SetY(-18);


        $this->SetFont(
            'Arial',
            '',
            8
        );


        $printed =
            'Printed By: ' .
            $supervisor_name .
            ' | Supervisor ID: ' .
            $supervisor_id .
            ' | Date: ' .
            date('d M Y') .
            ' | Time: ' .
            date('h:i A');


        $this->Cell(
            0,
            5,
            $printed,
            0,
            1,
            'C'
        );


        $this->Cell(
            0,
            5,
            'Page ' .
            $this->PageNo(),
            0,
            0,
            'C'
        );
    }
}


/* =========================================================
   CREATE PDF
========================================================= */

$pdf = new MonthlyAttendancePDF(
    'L',
    'mm',
    'A4'
);

$pdf->SetMargins(
    8,
    10,
    8
);

$pdf->SetAutoPageBreak(
    true,
    22
);

$pdf->AddPage();


/* =========================================================
   TABLE
========================================================= */

$headers = [

    'Staff ID',
    'Staff Name',
    'Department',
    'Position',
    'Hours',
    'Present',
    'Absent',
    'OT',
    'Late',
    'Early',
    'Attend %'

];


$widths = [

    25,
    43,
    32,
    38,
    18,
    18,
    18,
    18,
    18,
    18,
    22

];


$pdf->SetFont(
    'Arial',
    'B',
    7
);


/* HEADER */

for (
    $i = 0;
    $i < count($headers);
    $i++
) {

    $pdf->Cell(
        $widths[$i],
        9,
        $headers[$i],
        1,
        0,
        'C'
    );
}

$pdf->Ln();


/* DATA */

$pdf->SetFont(
    'Arial',
    '',
    7
);


foreach ($report_data as $row) {

    $pdf->Cell(
        $widths[0],
        8,
        $row['staff_id'],
        1,
        0,
        'C'
    );


    $pdf->Cell(
        $widths[1],
        8,
        substr(
            $row['fullname'],
            0,
            28
        ),
        1,
        0,
        'L'
    );


    $pdf->Cell(
        $widths[2],
        8,
        substr(
            $row['department'],
            0,
            20
        ),
        1,
        0,
        'L'
    );


    $pdf->Cell(
        $widths[3],
        8,
        substr(
            $row['position'],
            0,
            24
        ),
        1,
        0,
        'L'
    );


    $pdf->Cell(
        $widths[4],
        8,
        number_format(
            $row['hours'],
            2
        ),
        1,
        0,
        'C'
    );


    $pdf->Cell(
        $widths[5],
        8,
        $row['present'],
        1,
        0,
        'C'
    );


    $pdf->Cell(
        $widths[6],
        8,
        $row['absent'],
        1,
        0,
        'C'
    );


    $pdf->Cell(
        $widths[7],
        8,
        number_format(
            $row['overtime'],
            2
        ),
        1,
        0,
        'C'
    );


    $pdf->Cell(
        $widths[8],
        8,
        $row['late'],
        1,
        0,
        'C'
    );


    $pdf->Cell(
        $widths[9],
        8,
        $row['early'],
        1,
        0,
        'C'
    );


    $pdf->Cell(
        $widths[10],
        8,
        number_format(
            $row['attendance_percentage'],
            0
        ) . '%',
        1,
        0,
        'C'
    );


    $pdf->Ln();
}


/* =========================================================
   OUTPUT
========================================================= */

$pdf->Output(
    'D',
    'Staff_Monthly_Attendance_' .
    $month .
    '.pdf'
);

exit();

?>