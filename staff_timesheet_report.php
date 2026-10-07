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

$fullname =
    $_SESSION['fullname'] ?? "Staff";


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
    LIMIT 1
");

if (!$stmt) {

    die("Database error: " . $conn->error);

}

$stmt->bind_param(
    "s",
    $staff_id
);

$stmt->execute();

$result =
    $stmt->get_result();

if ($result->num_rows == 0) {

    die("Staff member not found.");

}

$staff =
    $result->fetch_assoc();

$stmt->close();


$fullname =
    $staff['fullname'];

$department =
    $staff['department'] ?? "";

$supervisor_id =
    $staff['supervisor_id'] ?? "";

$supervisor_name =
    $staff['supervisor_name'] ?? "Not Assigned";


/* =========================================================
   PROFILE PHOTO
========================================================= */

$profile_photo =
    "uploads/staff/default.png";


if (!empty($staff['profile_picture'])) {

    $photo_name =
        basename(
            $staff['profile_picture']
        );


    if (
        file_exists(
            "uploads/staff/" . $photo_name
        )
    ) {

        $profile_photo =
            "uploads/staff/" . $photo_name;

    }

    elseif (
        file_exists(
            "uploads/" . $photo_name
        )
    ) {

        $profile_photo =
            "uploads/" . $photo_name;

    }

}


/* =========================================================
   SELECT WEEK
========================================================= */

$selected_week =
    $_GET['week'] ?? date('Y-m-d');


$timestamp =
    strtotime($selected_week);


$day_of_week =
    date(
        'N',
        $timestamp
    );


$week_start =
    date(
        'Y-m-d',
        strtotime(
            "-" . ($day_of_week - 1) . " days",
            $timestamp
        )
    );


$week_end =
    date(
        'Y-m-d',
        strtotime(
            "+6 days",
            strtotime($week_start)
        )
    );


/* =========================================================
   LOAD TIMESHEET
========================================================= */

$entries = [];

$stmt = $conn->prepare("
    SELECT
        id,
        work_date,
        task_description,
        hours_worked,
        status,
        submitted_at
    FROM timesheets
    WHERE staff_id = ?
    AND week_start = ?
    AND week_end = ?
    ORDER BY work_date ASC, id ASC
");

if (!$stmt) {

    die(
        "Unable to load timesheet: " .
        $conn->error
    );

}

$stmt->bind_param(
    "sss",
    $staff_id,
    $week_start,
    $week_end
);

$stmt->execute();

$result =
    $stmt->get_result();

while (
    $row =
    $result->fetch_assoc()
) {

    $entries[] =
        $row;

}

$stmt->close();


/* =========================================================
   DEFAULT VALUES
========================================================= */

$status =
    "Not Submitted";

$submitted_at =
    "";

$approval_comment =
    "";

$decision_date =
    "";

$approval_decision =
    "";

$total_hours =
    0;


/* =========================================================
   GET STATUS
========================================================= */

if (!empty($entries)) {

    $status =
        $entries[0]['status']
        ?? "Pending";


    $submitted_at =
        $entries[0]['submitted_at']
        ?? "";


    foreach ($entries as $entry) {

        $total_hours +=
            (float)$entry['hours_worked'];

    }


    /* =====================================================
       GET SUPERVISOR APPROVAL / COMMENT

       We use the first timesheet ID because your
       supervisor approval is stored against timesheet_id.
    ===================================================== */

    $timesheet_id =
        (int)$entries[0]['id'];


    $stmt = $conn->prepare("
        SELECT
            ta.decision,
            ta.comment,
            ta.approved_at,
            s.fullname AS supervisor_name
        FROM timesheet_approval ta
        LEFT JOIN supervisors s
            ON ta.supervisor_id = s.supervisor_id
        WHERE ta.timesheet_id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $timesheet_id
        );

        $stmt->execute();

        $approval_result =
            $stmt->get_result();

        if (
            $approval_result->num_rows > 0
        ) {

            $approval =
                $approval_result->fetch_assoc();


            $approval_decision =
                $approval['decision']
                ?? "";


            $approval_comment =
                $approval['comment']
                ?? "";


            $decision_date =
                $approval['approved_at']
                ?? "";


            if (
                !empty(
                    $approval['supervisor_name']
                )
            ) {

                $supervisor_name =
                    $approval['supervisor_name'];

            }

        }

        $stmt->close();

    }

}


/* =========================================================
   DISPLAY STATUS
========================================================= */

$status_class =
    "pending";


if (
    strtolower($status)
    === "approved"
) {

    $status_class =
        "approved";

}
elseif (
    strtolower($status)
    === "rejected"
) {

    $status_class =
        "rejected";

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Timesheet Report
</title>

<link
    rel="stylesheet"
    href="styles.css"
>


<style>

/* =========================================================
   MAIN
========================================================= */

.main {

    flex:1;

    width:auto;

    min-width:0;

    min-height:100vh;

    background:#f5f6f8;

}


/* =========================================================
   PAGE
========================================================= */

.report-page {

    padding:25px;

}


/* =========================================================
   HEADER
========================================================= */

.page-header {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:20px;

}

.page-header h1 {

    margin:0;

    font-size:28px;

}

.page-header p {

    margin:7px 0 0;

    color:#666;

}


/* =========================================================
   WEEK SELECTOR
========================================================= */

.week-box {

    background:#fff;

    padding:20px;

    border-radius:10px;

    border:1px solid #e5e7eb;

    margin-bottom:20px;

}

.week-form {

    display:flex;

    align-items:end;

    gap:15px;

    flex-wrap:wrap;

}

.week-group {

    display:flex;

    flex-direction:column;

}

.week-group label {

    font-weight:600;

    margin-bottom:7px;

}

.week-group input {

    padding:11px;

    border:1px solid #ccc;

    border-radius:6px;

}


/* =========================================================
   REPORT
========================================================= */

.report-box {

    background:#fff;

    border-radius:10px;

    padding:25px;

    border:1px solid #e5e7eb;

    box-shadow:
        0 2px 10px rgba(0,0,0,.06);

}


/* =========================================================
   REPORT HEADER
========================================================= */

.report-header {

    text-align:center;

    margin-bottom:25px;

}

.report-header h2 {

    margin:0;

    font-size:24px;

}

.report-header p {

    margin:6px 0;

    color:#666;

}


/* =========================================================
   STAFF INFORMATION
========================================================= */

.staff-details {

    display:grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap:12px;

    margin-bottom:20px;

}

.detail {

    padding:12px;

    background:#f8fafc;

    border-radius:6px;

}

.detail strong {

    display:block;

    margin-bottom:4px;

    color:#475569;

}


/* =========================================================
   STATUS
========================================================= */

.status-box {

    padding:15px;

    border-radius:8px;

    margin-bottom:20px;

}

.status-box.approved {

    background:#d1fae5;

    color:#065f46;

}

.status-box.rejected {

    background:#fee2e2;

    color:#991b1b;

}

.status-box.pending {

    background:#fef3c7;

    color:#92400e;

}

.status-badge {

    display:inline-block;

    padding:6px 13px;

    border-radius:20px;

    font-size:13px;

    font-weight:700;

}


/* =========================================================
   COMMENT
========================================================= */

.comment-box {

    padding:18px;

    border:1px solid #e5e7eb;

    border-radius:8px;

    margin-bottom:25px;

    background:#fafafa;

}

.comment-box h3 {

    margin-top:0;

    margin-bottom:10px;

}

.comment-text {

    padding:13px;

    background:#fff;

    border-radius:6px;

    border:1px solid #e5e7eb;

    white-space:pre-wrap;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    overflow-x:auto;

}

.timesheet-table {

    width:100%;

    border-collapse:collapse;

}

.timesheet-table th,
.timesheet-table td {

    padding:13px;

    border:1px solid #e5e7eb;

    text-align:left;

}

.timesheet-table th {

    background:#f8fafc;

    color:#475569;

}

.total-row {

    background:#f8fafc;

    font-weight:700;

}


/* =========================================================
   BUTTONS
========================================================= */

.action-buttons {

    display:flex;

    gap:10px;

    justify-content:flex-end;

    margin-top:25px;

    flex-wrap:wrap;

}

.action-btn {

    border:none;

    padding:11px 18px;

    border-radius:6px;

    cursor:pointer;

    font-weight:600;

    color:#fff;

    text-decoration:none;

    display:inline-block;

}

.print-btn {

    background:#2563eb;

}

.pdf-btn {

    background:#dc3545;

}

.excel-btn {

    background:#198754;

}


/* =========================================================
   EMPTY
========================================================= */

.empty-state {

    text-align:center;

    padding:50px 20px;

    background:#fff;

    border-radius:10px;

    border:1px solid #e5e7eb;

}

.empty-state h2 {

    margin-bottom:8px;

}

.empty-state p {

    color:#64748b;

}


/* =========================================================
   PRINT
========================================================= */

@media print {

    body {

        background:#fff !important;

    }

    .sidebar,
    .week-box,
    .action-buttons {

        display:none !important;

    }

    .main {

        margin:0 !important;

        width:100% !important;

    }

    .report-page {

        padding:0;

    }

    .report-box {

        border:none;

        box-shadow:none;

        padding:0;

    }

    .timesheet-table th {

        background:#eee !important;

        color:#000 !important;

    }

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:600px) {

    .staff-details {

        grid-template-columns:1fr;

    }

    .report-page {

        padding:15px;

    }

}

</style>

</head>


<body>

<div class="container">


<!-- =====================================================
     SIDEBAR
====================================================== -->

<div class="sidebar">

    <div class="logo">

        <h2>

            <?php

            echo htmlspecialchars(
                $app['organization_name']
                ?? 'NTISHOR WEB ENTERPRISE'
            );

            ?>

        </h2>

        <p>
            Personnel Timesheet System
        </p>

    </div>


    <div class="profile">

        <div class="avatar">

            <img
                src="<?php
                    echo htmlspecialchars(
                        $profile_photo
                    );
                ?>"
                class="profile-small"
                alt="Profile Photo"
                onerror="this.src='uploads/staff/default.png';"
            >

        </div>


        <h3>

            <?php

            echo htmlspecialchars(
                $fullname
            );

            ?>

        </h3>


        <p>
            Staff
        </p>

    </div>


    <ul>

        <li>

            <a href="staff_dashboard.php">
                Dashboard
            </a>

        </li>


        <li>

            <a href="attendance.php">
                Clock In / Out
            </a>

        </li>


        <li>

            <a href="staff_attendance_history.php">
                Attendance History
            </a>

        </li>


        <li>

            <a href="submit_timesheet.php">
               Submit Timesheet
            </a>

        </li>


        <li>

            <a href="staff_tasks.php">
                Assigned Tasks
            </a>

        </li>


        <li>

            <a href="staff_task_reports.php">
                Task Reports
            </a>

        </li>

        <li>

            <a href="staff_logout.php">
                Logout
            </a>

        </li>

    </ul>

</div>


<!-- =====================================================
     MAIN
====================================================== -->

<div class="main">

<div class="report-page">


<div class="page-header">

    <div>

        <h1>
            Timesheet Report
        </h1>

        <p>
            View your submitted timesheet and supervisor decision.
        </p>

    </div>

</div>


<!-- =====================================================
     WEEK SELECTOR
====================================================== -->

<div class="week-box">

<form
    method="GET"
    class="week-form"
>

    <div class="week-group">

        <label>
            Select Week
        </label>

        <input
            type="date"
            name="week"
            value="<?php
                echo htmlspecialchars(
                    $week_start
                );
            ?>"
            required
        >

    </div>


    <button
        type="submit"
        class="action-btn print-btn"
    >

        Load Week

    </button>

</form>

</div>


<?php if (empty($entries)) { ?>


<div class="empty-state">

    <h2>
        No Timesheet Found
    </h2>

    <p>
        No timesheet has been submitted for this week.
    </p>

</div>


<?php } else { ?>


<!-- =====================================================
     REPORT
====================================================== -->

<div class="report-box" id="printArea">


<div class="report-header">

    <h2>

        <?php

        echo htmlspecialchars(
            $app['organization_name']
            ?? 'NTISHOR WEB ENTERPRISE'
        );

        ?>

    </h2>

    <p>
        Personnel Timesheet Management System
    </p>

    <h2>
        WEEKLY TIMESHEET REPORT
    </h2>

    <p>

        <?php

        echo date(
            "d M Y",
            strtotime($week_start)
        );

        ?>

        –

        <?php

        echo date(
            "d M Y",
            strtotime($week_end)
        );

        ?>

    </p>

</div>


<!-- =====================================================
     STAFF DETAILS
====================================================== -->

<div class="staff-details">

    <div class="detail">

        <strong>
            Staff Name
        </strong>

        <?php

        echo htmlspecialchars(
            $fullname
        );

        ?>

    </div>


    <div class="detail">

        <strong>
            Staff ID
        </strong>

        <?php

        echo htmlspecialchars(
            $staff_id
        );

        ?>

    </div>


    <div class="detail">

        <strong>
            Department
        </strong>

        <?php

        echo htmlspecialchars(
            $department
            ?: "Not Specified"
        );

        ?>

    </div>


    <div class="detail">

        <strong>
            Supervisor
        </strong>

        <?php

        echo htmlspecialchars(
            $supervisor_name
        );

        ?>

    </div>

</div>


<!-- =====================================================
     STATUS
====================================================== -->

<div class="status-box <?php
    echo $status_class;
?>">

    <strong>
        Timesheet Status:
    </strong>

    <?php

    echo htmlspecialchars(
        $status
    );

    ?>


    <?php if (!empty($decision_date)) { ?>

        <br>

        <strong>
            Decision Date:
        </strong>

        <?php

        echo htmlspecialchars(
            $decision_date
        );

        ?>

    <?php } ?>

</div>


<!-- =====================================================
     SUPERVISOR COMMENT
====================================================== -->

<?php if (
    strtolower($status)
    === "approved"
    ||
    strtolower($status)
    === "rejected"
) { ?>


<div class="comment-box">

    <h3>
        Supervisor's Comment / Remark
    </h3>


    <?php if (
        !empty($approval_comment)
    ) { ?>

        <div class="comment-text">

            <?php

            echo nl2br(
                htmlspecialchars(
                    $approval_comment
                )
            );

            ?>

        </div>

    <?php } else { ?>

        <div class="comment-text">

            No comment was provided by the supervisor.

        </div>

    <?php } ?>

</div>


<?php } else { ?>


<div class="comment-box">

    <h3>
        Supervisor's Comment / Remark
    </h3>

    <div class="comment-text">

        Awaiting supervisor review.

    </div>

</div>


<?php } ?>


<!-- =====================================================
     TIMESHEET TABLE
====================================================== -->

<div class="table-wrapper">

<table class="timesheet-table">

<thead>

<tr>

    <th>
        Day
    </th>

    <th>
        Date
    </th>

    <th>
        Task / Work Performed
    </th>

    <th>
        Hours
    </th>

</tr>

</thead>


<tbody>

<?php

foreach ($entries as $entry) {

?>

<tr>

    <td>

        <?php

        echo date(
            "l",
            strtotime(
                $entry['work_date']
            )
        );

        ?>

    </td>


    <td>

        <?php

        echo date(
            "d M Y",
            strtotime(
                $entry['work_date']
            )
        );

        ?>

    </td>


    <td>

        <?php

        echo nl2br(
            htmlspecialchars(
                $entry['task_description']
            )
        );

        ?>

    </td>


    <td>

        <?php

        echo number_format(
            (float)$entry['hours_worked'],
            2
        );

        ?>

        hrs

    </td>

</tr>

<?php

}

?>


<tr class="total-row">

    <td colspan="3">

        TOTAL WEEKLY HOURS

    </td>

    <td>

        <?php

        echo number_format(
            $total_hours,
            2
        );

        ?>

        hrs

    </td>

</tr>

</tbody>

</table>

</div>


<?php if (!empty($submitted_at)) { ?>

<p style="margin-top:20px;">

    <strong>
        Submitted:
    </strong>

    <?php

    echo htmlspecialchars(
        $submitted_at
    );

    ?>

</p>

<?php } ?>


</div>


<!-- =====================================================
     ACTION BUTTONS
====================================================== -->

<div class="action-buttons">

    <button
        type="button"
        class="action-btn print-btn"
        onclick="window.print();"
    >

        🖨 Print

    </button>


    <a
        href="staff_timesheet_pdf.php?week=<?php
            echo urlencode($week_start);
        ?>"
        class="action-btn pdf-btn"
    >

        📄 Download PDF

    </a>


    <a
        href="staff_timesheet_excel.php?week=<?php
            echo urlencode($week_start);
        ?>"
        class="action-btn excel-btn"
    >

        📊 Export Excel

    </a>

</div>


<?php } ?>


</div>

</div>

</div>

</body>

</html>