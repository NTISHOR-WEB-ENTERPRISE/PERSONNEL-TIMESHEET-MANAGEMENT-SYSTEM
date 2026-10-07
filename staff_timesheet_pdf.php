<?php
session_start();
include "config.php";

/* =========================================================
   STAFF AUTHENTICATION
========================================================= */
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

/* =========================================================
   SELECT WEEK
========================================================= */
$selected_week = $_GET['week'] ?? date('Y-m-d');
$timestamp = strtotime($selected_week);

if ($timestamp === false) {
    die("Invalid week selected.");
}

$day_of_week = date('N', $timestamp);

$week_start = date(
    'Y-m-d',
    strtotime("-" . ($day_of_week - 1) . " days", $timestamp)
);

$week_end = date(
    'Y-m-d',
    strtotime("+6 days", strtotime($week_start))
);

/* =========================================================
   FETCH STAFF + SUPERVISOR
========================================================= */
$stmt = $conn->prepare("
    SELECT
        staff.fullname,
        staff.staff_id,
        staff.department,
        supervisors.fullname AS supervisor_name
    FROM staff
    LEFT JOIN supervisors
        ON staff.supervisor_id = supervisors.supervisor_id
    WHERE staff.staff_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Staff member not found.");
}

$staff = $result->fetch_assoc();
$stmt->close();

/* =========================================================
   FETCH TIMESHEET
========================================================= */
$stmt = $conn->prepare("
    SELECT
        work_date,
        task_description,
        hours_worked,
        status
    FROM timesheets
    WHERE staff_id = ?
      AND week_start = ?
      AND week_end = ?
    ORDER BY work_date ASC
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("sss", $staff_id, $week_start, $week_end);
$stmt->execute();
$result = $stmt->get_result();

$entries = [];
$status = "Pending";
$total_hours = 0;

while ($row = $result->fetch_assoc()) {
    $entries[] = $row;
    $total_hours += (float)$row['hours_worked'];
    $status = $row['status'] ?? $status;
}

$stmt->close();

if (empty($entries)) {
    die("No timesheet entries found for this week.");
}

/* =========================================================
   FETCH SUPERVISOR COMMENT / DECISION
   ========================================================= */
$supervisor_comment = "";
$approval_decision = "";

$stmt = $conn->prepare("
    SELECT
        ta.decision,
        ta.comment,
        ta.approved_at
    FROM timesheet_approval ta
    INNER JOIN timesheets t
        ON t.id = ta.timesheet_id
    WHERE t.staff_id = ?
      AND t.week_start = ?
      AND t.week_end = ?
    ORDER BY ta.approved_at DESC, ta.id DESC
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("sss", $staff_id, $week_start, $week_end);
    $stmt->execute();
    $approval_result = $stmt->get_result();

    if ($approval_result->num_rows > 0) {
        $approval = $approval_result->fetch_assoc();
        $approval_decision = $approval['decision'] ?? "";
        $supervisor_comment = $approval['comment'] ?? "";
    }

    $stmt->close();
}

/* =========================================================
   LOAD FPDF
========================================================= */
$fpdf_paths = [
    __DIR__ . "/fpdf186/fpdf.php",
    __DIR__ . "/FPDF/fpdf.php",
    __DIR__ . "/vendor/fpdf/fpdf.php"
];

$fpdf_loaded = false;

foreach ($fpdf_paths as $fpdf_path) {
    if (file_exists($fpdf_path)) {
        require_once $fpdf_path;
        $fpdf_loaded = true;
        break;
    }
}

if (!$fpdf_loaded) {
    die("FPDF library not found. Place fpdf.php inside an fpdf folder.");
}

/* =========================================================
   CREATE PDF
========================================================= */
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetTitle("Staff Timesheet - " . $staff['fullname']);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'NTISHOR WEB ENTERPRISE', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 8, 'STAFF WEEKLY TIMESHEET', 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(
    0,
    7,
    'Week: ' .
    date('d M Y', strtotime($week_start)) .
    ' - ' .
    date('d M Y', strtotime($week_end)),
    0,
    1,
    'C'
);

$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, 'Staff Name:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(70, 7, $staff['fullname'], 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 7, 'Staff ID:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(45, 7, $staff['staff_id'], 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, 'Department:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(70, 7, $staff['department'] ?? '', 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 7, 'Supervisor:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(45, 7, $staff['supervisor_name'] ?? 'Not Assigned', 0, 1);

$pdf->Ln(5);

/* Table header */
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(28, 8, 'Day', 1, 0, 'C');
$pdf->Cell(30, 8, 'Date', 1, 0, 'C');
$pdf->Cell(22, 8, 'Hours', 1, 0, 'C');
$pdf->Cell(100, 8, 'Task / Work Performed', 1, 1, 'C');

$pdf->SetFont('Arial', '', 8);

foreach ($entries as $entry) {
    $task = $entry['task_description'] ?? '';
    $task = str_replace(["\r", "\n"], ' ', $task);

    $pdf->Cell(
        28,
        8,
        date('l', strtotime($entry['work_date'])),
        1,
        0
    );

    $pdf->Cell(
        30,
        8,
        date('d M Y', strtotime($entry['work_date'])),
        1,
        0
    );

    $pdf->Cell(
        22,
        8,
        number_format((float)$entry['hours_worked'], 2),
        1,
        0,
        'C'
    );

    $pdf->Cell(
        100,
        8,
        substr($task, 0, 75),
        1,
        1
    );
}

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(158, 8, 'TOTAL WEEKLY HOURS', 1, 0, 'R');
$pdf->Cell(22, 8, number_format($total_hours, 2), 1, 1, 'C');

$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, 'Status:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, ucfirst($status), 0, 1);

if (!empty($approval_decision)) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 7, 'Decision:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, $approval_decision, 0, 1);
}

if (!empty($supervisor_comment)) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, 'Supervisor Comment / Remark:', 0, 1);

    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 7, $supervisor_comment, 1);
}

$pdf->Ln(8);

$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(
    0,
    6,
    'Generated on ' . date('d M Y h:i A'),
    0,
    1,
    'C'
);

$pdf->Output(
    'D',
    'Timesheet_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $staff['fullname']) .
    '_' . $week_start . '.pdf'
);
exit;
?>
