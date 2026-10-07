<?php
session_start();
require('fpdf186/fpdf.php');
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    die("Unauthorized Access");
}

$supervisor_id = $_SESSION['supervisor_id'];

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

$pdf = new FPDF('L');
$pdf->AddPage();

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Staff Timesheet Report',0,1,'C');
$pdf->Ln(5);

$pdf->SetFont('Arial','B',8);

$pdf->Cell(20,10,'Staff ID',1);
$pdf->Cell(35,10,'Staff Name',1);
$pdf->Cell(25,10,'Date',1);
$pdf->Cell(20,10,'Clock In',1);
$pdf->Cell(20,10,'Clock Out',1);
$pdf->Cell(25,10,'Regular Hrs',1);
$pdf->Cell(25,10,'Overtime Hrs',1);
$pdf->Cell(25,10,'Total Hrs',1);
$pdf->Cell(80,10,'Work Done',1);
$pdf->Ln();

$pdf->SetFont('Arial','',8);

while($row = mysqli_fetch_assoc($query))
{
    $hours = $row['hours_worked'];

    $regular = ($hours > 8) ? 8 : $hours;
    $overtime = ($hours > 8) ? ($hours - 8) : 0;
    $total = $hours;

    $pdf->Cell(20,10,$row['staff_id'],1);
    $pdf->Cell(35,10,$row['fullname'],1);
    $pdf->Cell(25,10,$row['work_date'],1);
    $pdf->Cell(20,10,$row['clock_in'],1);
    $pdf->Cell(20,10,$row['clock_out'],1);
    $pdf->Cell(25,10,$regular,1);
    $pdf->Cell(25,10,$overtime,1);
    $pdf->Cell(25,10,$total,1);
    $pdf->Cell(80,10,$row['task_description'],1);
    $pdf->Ln();
}

$pdf->Output();
?>