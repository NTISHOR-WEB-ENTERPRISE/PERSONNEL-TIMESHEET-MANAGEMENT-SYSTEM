<?php
session_start();
require('fpdf186/fpdf.php');
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    die("Unauthorized Access");
}

$supervisor_id = $_SESSION['supervisor_id'];

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$pdf = new FPDF('L','mm','A4');
$pdf->AddPage();

$pdf->SetFont('Arial','B',14);

$pdf->Cell(
    0,
    10,
    'STAFF MONTHLY ATTENDANCE AND TIMESHEET REPORT - '
    . date('F Y', strtotime("$year-$month-01")),
    0,
    1,
    'C'
);

$pdf->Ln(3);

$pdf->SetFont('Arial','B',8);

$pdf->Cell(20,8,'Staff ID',1);
$pdf->Cell(40,8,'Staff Name',1);
$pdf->Cell(30,8,'Department',1);
$pdf->Cell(30,8,'Position',1);
$pdf->Cell(20,8,'Hours',1);
$pdf->Cell(18,8,'Present',1);
$pdf->Cell(18,8,'Absent',1);
$pdf->Cell(18,8,'OT',1);
$pdf->Cell(18,8,'Late',1);
$pdf->Cell(18,8,'Early',1);
$pdf->Cell(22,8,'Attend %',1);

$pdf->Ln();

$pdf->SetFont('Arial','',8);

$staffQuery = mysqli_query($conn,"
SELECT *
FROM staff
WHERE supervisor_id='$supervisor_id'
ORDER BY fullname
");

while($staff = mysqli_fetch_assoc($staffQuery))
{
    $staff_id = $staff['staff_id'];

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
        $totalHours += (float)$t['hours_worked'];
        $overtime += (float)$t['overtime_hours'];
        $late += (int)$t['late_arrival'];
        $early += (int)$t['early_departure'];

        $present++;
    }

    $workingDays = 0;

    for($d=1; $d<=$daysInMonth; $d++)
    {
        $weekday = date('N', strtotime("$year-$month-$d"));

        if($weekday < 6)
        {
            $workingDays++;
        }
    }

    $absent = $workingDays - $present;

    if($absent < 0)
    {
        $absent = 0;
    }

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

    $pdf->Cell(20,8,$staff['staff_id'],1);
    $pdf->Cell(40,8,$staff['fullname'],1);
    $pdf->Cell(30,8,$staff['department'],1);
    $pdf->Cell(30,8,$staff['position'],1);
    $pdf->Cell(20,8,number_format($totalHours,2),1);
    $pdf->Cell(18,8,$present,1);
    $pdf->Cell(18,8,$absent,1);
    $pdf->Cell(18,8,number_format($overtime,2),1);
    $pdf->Cell(18,8,$late,1);
    $pdf->Cell(18,8,$early,1);
    $pdf->Cell(22,8,$attendance.'%',1);

    $pdf->Ln();
}

$pdf->Output(
    'D',
    'Staff_Monthly_Report_'.$month.'_'.$year.'.pdf'
);
?>