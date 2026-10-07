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
$total_hours = 0;
$status = "Pending";

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
$approved_at = "";

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
        $approved_at = $approval['approved_at'] ?? "";
    }

    $stmt->close();
}

/* =========================================================
   EXCEL-COMPATIBLE HTML
   No external Excel library is required.
========================================================= */
$filename =
    'Timesheet_' .
    preg_replace('/[^A-Za-z0-9_-]/', '_', $staff['fullname']) .
    '_' . $week_start . '.xls';

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

function excel_safe($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

echo '<html><head><meta charset="UTF-8"></head><body>';

echo '<table border="1">';

echo '<tr><th colspan="5" style="font-size:18px;">NTISHOR WEB ENTERPRISE</th></tr>';
echo '<tr><th colspan="5">STAFF WEEKLY TIMESHEET</th></tr>';

echo '<tr><td><strong>Staff Name</strong></td><td>' .
     excel_safe($staff['fullname']) .
     '</td><td><strong>Staff ID</strong></td><td>' .
     excel_safe($staff['staff_id']) .
     '</td><td></td></tr>';

echo '<tr><td><strong>Department</strong></td><td>' .
     excel_safe($staff['department'] ?? '') .
     '</td><td><strong>Supervisor</strong></td><td>' .
     excel_safe($staff['supervisor_name'] ?? 'Not Assigned') .
     '</td><td></td></tr>';

echo '<tr><td><strong>Week</strong></td><td colspan="4">' .
     excel_safe(
         date('d M Y', strtotime($week_start)) .
         ' - ' .
         date('d M Y', strtotime($week_end))
     ) .
     '</td></tr>';

echo '<tr>';
echo '<th>Day</th>';
echo '<th>Date</th>';
echo '<th>Hours</th>';
echo '<th>Task / Work Performed</th>';
echo '<th>Status</th>';
echo '</tr>';

foreach ($entries as $entry) {
    echo '<tr>';

    echo '<td>' .
         excel_safe(date('l', strtotime($entry['work_date']))) .
         '</td>';

    echo '<td>' .
         excel_safe(date('d M Y', strtotime($entry['work_date']))) .
         '</td>';

    echo '<td>' .
         number_format((float)$entry['hours_worked'], 2) .
         '</td>';

    echo '<td>' .
         nl2br(excel_safe($entry['task_description'] ?? '')) .
         '</td>';

    echo '<td>' .
         excel_safe($entry['status'] ?? '') .
         '</td>';

    echo '</tr>';
}

echo '<tr>';
echo '<th colspan="2">TOTAL WEEKLY HOURS</th>';
echo '<th>' . number_format($total_hours, 2) . '</th>';
echo '<th colspan="2"></th>';
echo '</tr>';

echo '<tr><td><strong>Approval Decision</strong></td><td colspan="4">' .
     excel_safe($approval_decision ?: 'Not yet processed') .
     '</td></tr>';

echo '<tr><td><strong>Supervisor Comment / Remark</strong></td><td colspan="4">' .
     nl2br(excel_safe($supervisor_comment ?: 'No comment provided')) .
     '</td></tr>';

if (!empty($approved_at)) {
    echo '<tr><td><strong>Decision Date</strong></td><td colspan="4">' .
         excel_safe($approved_at) .
         '</td></tr>';
}

echo '</table>';

echo '</body></html>';
exit;
?>
