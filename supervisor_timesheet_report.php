<?php

session_start();

include "config.php";
include "activity_logger.php";
include "notification_function.php";


/* =========================================================
   SUPERVISOR AUTHENTICATION
========================================================= */

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$fullname = $_SESSION['fullname'] ?? "Supervisor";


/* =========================================================
   FETCH SUPERVISOR INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT *
    FROM supervisors
    WHERE supervisor_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Supervisor account not found.");
}

$supervisor = $result->fetch_assoc();
$stmt->close();

$supervisor_name = $supervisor['fullname'] ?? $fullname;


/* =========================================================
   PROFILE PHOTO
========================================================= */

$profile_photo = "uploads/supervisors/default.png";

if (!empty($supervisor['profile_photo'])) {

    $photo_name = basename($supervisor['profile_photo']);

    if (file_exists("uploads/supervisors/" . $photo_name)) {

        $profile_photo = "uploads/supervisors/" . $photo_name;

    } elseif (file_exists("uploads/" . $photo_name)) {

        $profile_photo = "uploads/" . $photo_name;
    }
}


/* =========================================================
   SELECT WEEK
========================================================= */

$selected_week_date = $_GET['week'] ?? date('Y-m-d');

$timestamp = strtotime($selected_week_date);

$day_number = date('N', $timestamp);


/* Monday */

$week_start = date(
    'Y-m-d',
    strtotime("-" . ($day_number - 1) . " days", $timestamp)
);


/* Sunday */

$week_end = date(
    'Y-m-d',
    strtotime("+6 days", strtotime($week_start))
);


/* =========================================================
   SELECT STAFF
========================================================= */

$selected_staff = $_GET['staff_id'] ?? '';


/* =========================================================
   FETCH STAFF UNDER THIS SUPERVISOR
========================================================= */

$staff_list = [];

$stmt = $conn->prepare("
    SELECT
        staff_id,
        fullname,
        department
    FROM staff
    WHERE supervisor_id = ?
    AND status = 'active'
    ORDER BY fullname ASC
");

if ($stmt) {

    $stmt->bind_param("s", $supervisor_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $staff_list[] = $row;
    }

    $stmt->close();
}


/* =========================================================
   INITIAL VALUES
========================================================= */

$timesheets = [];

$total_hours = 0;

$staff_name = '';
$staff_department = '';
$staff_id_display = '';

$status = 'Pending';

$submitted_at = '';
$decision_date = '';
$remark = '';


/* =========================================================
   FETCH SELECTED STAFF TIMESHEET
========================================================= */

if ($selected_staff != '') {


    /* =====================================================
       VERIFY STAFF BELONGS TO SUPERVISOR
    ===================================================== */

    $stmt = $conn->prepare("
        SELECT
            staff_id,
            fullname,
            department
        FROM staff
        WHERE staff_id = ?
        AND supervisor_id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "ss",
            $selected_staff,
            $supervisor_id
        );

        $stmt->execute();

        $staff = $stmt->get_result()->fetch_assoc();

        $stmt->close();


        /* =================================================
           STAFF FOUND
        ================================================= */

        if ($staff) {

            $staff_name =
                $staff['fullname'] ?? '';

            $staff_department =
                $staff['department'] ?? '';

            $staff_id_display =
                $staff['staff_id'] ?? '';


            /* =============================================
               FETCH WEEKLY TIMESHEET
            ============================================= */

            $stmt = $conn->prepare("
                SELECT
                    id,
                    staff_id,
                    work_date,
                    task_description,
                    hours_worked,
                    total_hours,
                    submitted_at,
                    status
                FROM timesheets
                WHERE staff_id = ?
                AND work_date BETWEEN ? AND ?
                ORDER BY work_date ASC, id ASC
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "sss",
                    $selected_staff,
                    $week_start,
                    $week_end
                );

                $stmt->execute();

                $result = $stmt->get_result();


                while ($row = $result->fetch_assoc()) {

                    /* -------------------------------------
                       GET HOURS WORKED
                    ------------------------------------- */

                    $hours =
                        (float)($row['hours_worked'] ?? 0);


                    /* -------------------------------------
                       FALLBACK TO TOTAL HOURS
                    ------------------------------------- */

                    if (
                        $hours <= 0 &&
                        isset($row['total_hours'])
                    ) {

                        $hours =
                            (float)$row['total_hours'];
                    }


                    $row['display_hours'] = $hours;


                    /* -------------------------------------
                       GET STATUS
                    ------------------------------------- */

                    if (!empty($row['status'])) {

                        $status =
                            $row['status'];
                    }


                    /* -------------------------------------
                       TOTAL HOURS
                    ------------------------------------- */

                    $total_hours += $hours;


                    /* -------------------------------------
                       SUBMITTED DATE
                    ------------------------------------- */

                    if (
                        empty($submitted_at) &&
                        !empty($row['submitted_at'])
                    ) {

                        $submitted_at =
                            $row['submitted_at'];
                    }


                    $timesheets[] = $row;
                }

                $stmt->close();
            }


            /* =============================================
               GET WEEK SUBMISSION INFORMATION
            ============================================= */

            $stmt = $conn->prepare("
                SELECT
                    submitted_at
                FROM timesheet_submissions
                WHERE staff_id = ?
                AND week_start = ?
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "ss",
                    $selected_staff,
                    $week_start
                );

                $stmt->execute();

                $submission =
                    $stmt->get_result()->fetch_assoc();

                $stmt->close();


                if ($submission) {

                    $submitted_at =
                        $submission['submitted_at'] ?? '';
                }
            }


            /* =============================================
               GET SUPERVISOR APPROVAL INFORMATION
            ============================================= */

            $stmt = $conn->prepare("
                SELECT
                    ta.decision,
                    ta.comment,
                    ta.approved_at
                FROM timesheet_approval ta
                INNER JOIN timesheets t
                    ON ta.timesheet_id = t.id
                WHERE t.staff_id = ?
                AND t.work_date BETWEEN ? AND ?
                AND ta.supervisor_id = ?
                ORDER BY ta.id DESC
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "ssss",
                    $selected_staff,
                    $week_start,
                    $week_end,
                    $supervisor_id
                );

                $stmt->execute();

                $approval =
                    $stmt->get_result()->fetch_assoc();

                $stmt->close();


                if ($approval) {

                    if (!empty($approval['decision'])) {

                        $status =
                            $approval['decision'];
                    }


                    $remark =
                        $approval['comment'] ?? '';


                    if (!empty($approval['approved_at'])) {

                        $decision_date =
                            $approval['approved_at'];
                    }
                }
            }
        }
    }
}


/* =========================================================
   STATUS CLASS
========================================================= */

$status_class =
    strtolower(trim($status));

if ($status_class == '') {
    $status_class = 'pending';
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

<title>Supervisor Timesheet Report</title>

<link
    rel="stylesheet"
    href="styles.css"
>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100%;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f6f8;
    color: #222;
}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {
    display: flex;
    width: 100%;
    min-height: 100vh;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position: fixed !important;
    top: 0;
    left: 0;
    bottom: 0;

    width: 260px !important;
    min-width: 260px !important;

    height: 100vh;

    background: #858585 !important;
    color: #ffffff;

    overflow-y: auto;
    overflow-x: hidden;

    z-index: 1000;

    padding: 0;
}


/* =========================================================
   SIDEBAR LOGO
========================================================= */

.sidebar .logo {
    width: 100%;
    padding: 22px 15px 18px !important;

    text-align: center;

    border: none !important;
    background: transparent !important;
}

.sidebar .logo h2 {
    margin: 0;

    color: #ffffff !important;

    font-size: 23px !important;
    font-weight: 700;

    line-height: 1.45;

    text-transform: uppercase;
}

.sidebar .logo p {
    margin: 7px 0 0;

    color: #eeeeee !important;

    font-size: 14px !important;
    line-height: 1.4;
}


/* =========================================================
   PROFILE
========================================================= */

.sidebar .profile {
    width: 100%;

    padding: 8px 15px 22px !important;

    text-align: center;

    border: none !important;
    background: transparent !important;
}


/* =========================================================
   PROFILE IMAGE
========================================================= */

.sidebar .avatar {
    width: 82px !important;
    height: 82px !important;

    margin: 0 auto 12px;

    border-radius: 50%;

    overflow: hidden;

    background: #666666;

    border: 3px solid rgba(255,255,255,0.9) !important;

    display: flex;
    align-items: center;
    justify-content: center;
}

.sidebar .profile-small {
    width: 100% !important;
    height: 100% !important;

    display: block;

    object-fit: cover;

    border-radius: 50%;
}


/* =========================================================
   PROFILE NAME
========================================================= */

.sidebar .profile h3 {
    margin: 8px 0 4px !important;

    color: #ffffff !important;

    font-size: 17px !important;
    font-weight: 700;

    line-height: 1.35;

    text-transform: uppercase;
}

.sidebar .profile p {
    margin: 0 !important;

    color: #eeeeee !important;

    font-size: 14px !important;
}


/* =========================================================
   SIDEBAR NAVIGATION
========================================================= */

.sidebar ul {
    list-style: none;

    width: 100%;

    margin: 0;

    padding: 8px 20px 20px !important;
}


/* =========================================================
   NAVIGATION ITEM
========================================================= */

.sidebar ul li {
    width: 100%;

    margin: 0 0 10px !important;

    padding: 0;
}


/* =========================================================
   NAVIGATION LINKS
========================================================= */

.sidebar ul li a {
    display: flex !important;

    align-items: center;

    width: 100%;

    min-height: 48px;

    padding: 13px 15px !important;

    border-radius: 7px !important;

    background: #1264d6 !important;

    color: #ffffff !important;

    text-decoration: none !important;

    font-size: 16px !important;
    font-weight: 400;

    transition:
        background 0.2s ease,
        transform 0.1s ease !important;
}


/* =========================================================
   HOVER
========================================================= */

.sidebar ul li a:hover {
    background: #0755bd !important;

    color: #ffffff !important;

    transform: translateY(-1px);
}


/* =========================================================
   ACTIVE PAGE
========================================================= */

.sidebar ul li a.active {
    background: #06479f !important;

    color: #ffffff !important;

    font-weight: 600;
}


/* =========================================================
   MAIN
========================================================= */

.main {
    width: calc(100% - 260px) !important;

    min-width: 0;
    min-height: 100vh;

    margin-left: 260px !important;

    background: #f5f6f8;

    overflow-x: hidden;
}


/* =========================================================
   PAGE CONTENT
========================================================= */

.report-page {
    width: 100%;

    max-width: 1400px;

    margin: 0 auto;

    padding: 25px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0 0 8px;

    font-size: 28px;

    color: #222;
}

.page-header p {
    margin: 0;

    color: #666;

    font-size: 15px;
}


/* =========================================================
   REPORT CARD
========================================================= */

.report-card {
    background: #ffffff;

    border-radius: 10px;

    padding: 30px;

    box-shadow:
        0 3px 15px rgba(0,0,0,0.08);
}


/* =========================================================
   COMPANY HEADER
========================================================= */

.company-header {
    text-align: center;

    border-bottom: 2px solid #222;

    padding-bottom: 18px;

    margin-bottom: 25px;
}

.company-header h2 {
    margin: 0;

    font-size: 24px;

    text-transform: uppercase;
}

.company-header p {
    margin: 7px 0 0;

    font-size: 14px;
}


/* =========================================================
   REPORT TITLE
========================================================= */

.report-title {
    text-align: center;

    margin-bottom: 25px;
}

.report-title h2 {
    margin: 0;

    font-size: 22px;
}

.report-title p {
    margin-top: 8px;

    font-size: 15px;

    color: #555;
}


/* =========================================================
   FILTERS
========================================================= */

.filters {
    display: flex;

    gap: 15px;

    flex-wrap: wrap;

    align-items: end;

    margin-bottom: 25px;

    padding: 18px;

    background: #f8f9fa;

    border-radius: 8px;
}

.filter-group {
    display: flex;

    flex-direction: column;

    gap: 6px;
}

.filter-group label {
    font-size: 13px;

    font-weight: bold;
}

.filter-group input,
.filter-group select {
    padding: 10px 12px;

    border: 1px solid #ccc;

    border-radius: 5px;

    min-width: 190px;

    background: #ffffff;
}


/* =========================================================
   BUTTONS
========================================================= */

.btn {
    border: none;

    padding: 10px 16px;

    border-radius: 5px;

    cursor: pointer;

    text-decoration: none;

    display: inline-block;

    font-size: 14px;
}

.btn-load {
    background: #222;
    color: white;
}

.btn-print {
    background: #333;
    color: white;
}

.btn-pdf {
    background: #b00020;
    color: white;
}

.btn-excel {
    background: #167c3a;
    color: white;
}


/* =========================================================
   ACTION BUTTONS
========================================================= */

.action-buttons {
    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    margin-bottom: 25px;
}


/* =========================================================
   STAFF INFORMATION
========================================================= */

.staff-info {
    display: grid;

    grid-template-columns: repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}

.info-box {
    background: #f8f9fa;

    padding: 15px;

    border-radius: 6px;
}

.info-box span {
    display: block;

    font-size: 12px;

    color: #666;

    margin-bottom: 5px;
}

.info-box strong {
    font-size: 14px;
}


/* =========================================================
   STATUS
========================================================= */

.status {
    display: inline-block;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    text-transform: capitalize;
}

.status.approved {
    background: #d4edda;
    color: #155724;
}

.status.pending {
    background: #fff3cd;
    color: #856404;
}

.status.rejected {
    background: #f8d7da;
    color: #721c24;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    margin-top: 10px;
}

th,
td {
    border: 1px solid #ddd;

    padding: 11px;

    text-align: left;

    font-size: 13px;

    vertical-align: top;
}

th {
    background: #f1f1f1;

    font-weight: bold;
}

.total-row td {
    font-weight: bold;

    background: #f8f9fa;
}


/* =========================================================
   REMARK
========================================================= */

.remark-box {
    margin-top: 25px;

    padding: 18px;

    background: #f8f9fa;

    border-left: 4px solid #333;
}

.remark-box h4 {
    margin-top: 0;
}


/* =========================================================
   EMPTY MESSAGE
========================================================= */

.empty {
    text-align: center;

    padding: 40px;

    color: #777;
}


/* =========================================================
   SIDEBAR SCROLLBAR
========================================================= */

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.25);

    border-radius: 10px;
}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 1000px) {

    .sidebar {
        width: 220px !important;

        min-width: 220px !important;
    }

    .main {
        width: calc(100% - 220px) !important;

        margin-left: 220px !important;
    }

    .sidebar .logo h2 {
        font-size: 20px !important;
    }

    .sidebar ul {
        padding-left: 15px !important;
        padding-right: 15px !important;
    }

    .staff-info {
        grid-template-columns: repeat(2, 1fr);
    }
}


/* =========================================================
   SMALL TABLET
========================================================= */

@media (max-width: 800px) {

    .sidebar {
        width: 200px !important;

        min-width: 200px !important;
    }

    .main {
        width: calc(100% - 200px) !important;

        margin-left: 200px !important;
    }

    .sidebar .logo h2 {
        font-size: 18px !important;
    }

    .sidebar .profile h3 {
        font-size: 15px !important;
    }

    .sidebar ul {
        padding-left: 12px !important;

        padding-right: 12px !important;
    }

    .sidebar ul li a {
        font-size: 14px !important;

        padding: 11px 12px !important;
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 600px) {

    .container {
        display: block;

        width: 100%;
    }

    .sidebar {
        position: relative !important;

        width: 100% !important;

        min-width: 100% !important;

        height: auto;

        min-height: auto;

        overflow: visible;
    }

    .sidebar .logo {
        padding: 18px 15px 15px !important;
    }

    .sidebar .logo h2 {
        font-size: 21px !important;
    }

    .sidebar .profile {
        padding: 5px 15px 18px !important;
    }

    .sidebar .avatar {
        width: 75px !important;

        height: 75px !important;
    }

    .sidebar ul {
        display: flex;

        flex-wrap: wrap;

        gap: 7px;

        padding: 10px 12px 15px !important;
    }

    .sidebar ul li {
        flex: 1 1 140px;

        width: auto;

        margin: 0 !important;
    }

    .sidebar ul li a {
        justify-content: center;

        text-align: center;

        font-size: 14px !important;
    }

    .main {
        width: 100% !important;

        min-width: 100%;

        margin-left: 0 !important;
    }

    .report-page {
        padding: 15px;
    }

    .report-card {
        padding: 20px;
    }

    .staff-info {
        grid-template-columns: 1fr;
    }

    .filters {
        flex-direction: column;

        align-items: stretch;
    }

    .filter-group input,
    .filter-group select {
        width: 100%;

        min-width: 0;
    }
}


/* =========================================================
   PRINT
========================================================= */

@media print {

    body {
        background: white;
    }

    .sidebar,
    .page-header,
    .filters,
    .action-buttons {
        display: none !important;
    }

    .main {
        width: 100% !important;

        margin-left: 0 !important;
    }

    .report-page {
        width: 100%;

        max-width: none;

        padding: 0;
    }

    .report-card {
        box-shadow: none;

        padding: 0;
    }

    table {
        page-break-inside: avoid;
    }

    .company-header {
        margin-top: 0;
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
            <?= htmlspecialchars(
                $app['organization_name']
                ?? 'NTISHOR WEB ENTERPRISE'
            ); ?>
        </h2>

        <p>
            Personnel Timesheet System
        </p>

    </div>


    <!-- PROFILE -->

    <div class="profile">

        <div class="avatar">

            <img
                src="<?= htmlspecialchars($profile_photo); ?>"
                alt="Profile Photo"
                class="profile-small"
                onerror="this.src='uploads/supervisors/default.png';"
            >

        </div>


        <h3>
            <?= htmlspecialchars($supervisor_name); ?>
        </h3>


        <p>
            Supervisor
        </p>

    </div>


    <!-- NAVIGATION -->

    <ul>

        <li>

            <a href="supervisor_dashboard.php">

                Dashboard

            </a>

        </li>


        <li>

            <a
                href="supervisor_timesheet_report.php"
                class="active"
            >

                Timesheet Report

            </a>

        </li>


        <li>

            <a href="supervisor_notifications.php">

                Notifications

            </a>

        </li>


        <li>

            <a href="supervisor_profile.php">

                My Profile

            </a>

        </li>


        <li>

            <a href="supervisor_logout.php">

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


<!-- =====================================================
     PAGE HEADER
====================================================== -->

<div class="page-header">

    <h1>
        Supervisor Timesheet Report
    </h1>

    <p>
        View and review weekly timesheet records
        submitted by your assigned staff.
    </p>

</div>


<!-- =====================================================
     REPORT CARD
====================================================== -->

<div class="report-card">


<!-- =====================================================
     COMPANY HEADER
====================================================== -->

<div class="company-header">

    <h2>
        NTISHOR WEB ENTERPRISE
    </h2>

    <p>
        Personnel Timesheet Management System
    </p>

</div>


<!-- =====================================================
     REPORT TITLE
====================================================== -->

<div class="report-title">

    <h2>
        WEEKLY TIMESHEET REPORT
    </h2>

    <p>

        <?= date(
            'd M Y',
            strtotime($week_start)
        ); ?>

        –

        <?= date(
            'd M Y',
            strtotime($week_end)
        ); ?>

    </p>

</div>


<!-- =====================================================
     FILTERS
====================================================== -->

<form method="GET">

<div class="filters">


    <div class="filter-group">

        <label>
            Select Week
        </label>

        <input
            type="date"
            name="week"
            value="<?= htmlspecialchars($selected_week_date); ?>"
            required
        >

    </div>


    <div class="filter-group">

        <label>
            Select Staff
        </label>

        <select
            name="staff_id"
            required
        >

            <option value="">
                -- Select Staff --
            </option>


            <?php foreach ($staff_list as $staff): ?>

                <option
                    value="<?= htmlspecialchars(
                        $staff['staff_id']
                    ); ?>"

                    <?= (
                        $selected_staff ===
                        $staff['staff_id']
                    )
                        ? 'selected'
                        : ''; ?>
                >

                    <?= htmlspecialchars(
                        $staff['fullname']
                    ); ?>

                    -

                    <?= htmlspecialchars(
                        $staff['staff_id']
                    ); ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <button
        type="submit"
        class="btn btn-load"
    >

        Load Week

    </button>

</div>

</form>


<?php if (
    $selected_staff != '' &&
    $staff_name != ''
): ?>


<!-- =====================================================
     ACTION BUTTONS
====================================================== -->

<div class="action-buttons">


    <button
        type="button"
        class="btn btn-print"
        onclick="window.print()"
    >

        🖨 Print

    </button>


    <a
        href="supervisor_timesheet_pdf.php?week=<?= urlencode($week_start); ?>&staff_id=<?= urlencode($selected_staff); ?>"
        class="btn btn-pdf"
    >

        📄 Download PDF

    </a>


    <a
        href="supervisor_timesheet_excel.php?week=<?= urlencode($week_start); ?>&staff_id=<?= urlencode($selected_staff); ?>"
        class="btn btn-excel"
    >

        📊 Export Excel

    </a>

</div>


<!-- =====================================================
     STAFF INFORMATION
====================================================== -->

<div class="staff-info">


    <div class="info-box">

        <span>
            Staff Name
        </span>

        <strong>

            <?= htmlspecialchars(
                $staff_name
            ); ?>

        </strong>

    </div>


    <div class="info-box">

        <span>
            Staff ID
        </span>

        <strong>

            <?= htmlspecialchars(
                $staff_id_display
            ); ?>

        </strong>

    </div>


    <div class="info-box">

        <span>
            Department
        </span>

        <strong>

            <?= htmlspecialchars(
                $staff_department
            ); ?>

        </strong>

    </div>


    <div class="info-box">

        <span>
            Supervisor
        </span>

        <strong>

            <?= htmlspecialchars(
                $supervisor_name
            ); ?>

        </strong>

    </div>

</div>


<!-- =====================================================
     STATUS
====================================================== -->

<p>

    <strong>
        Timesheet Status:
    </strong>


    <span
        class="status <?= htmlspecialchars(
            $status_class
        ); ?>"
    >

        <?= htmlspecialchars(
            $status ?: 'Pending'
        ); ?>

    </span>

</p>


<?php if ($decision_date != ''): ?>

<p>

    <strong>
        Decision Date:
    </strong>

    <?= htmlspecialchars(
        $decision_date
    ); ?>

</p>

<?php endif; ?>


<!-- =====================================================
     REMARK
====================================================== -->

<?php if ($remark != ''): ?>

<div class="remark-box">

    <h4>
        Supervisor's Comment / Remark
    </h4>

    <p>

        <?= nl2br(
            htmlspecialchars($remark)
        ); ?>

    </p>

</div>

<?php endif; ?>


<!-- =====================================================
     TIMESHEET TABLE
====================================================== -->

<div class="table-wrapper">

<table>

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

$current_date =
    strtotime($week_start);

for ($i = 0; $i < 7; $i++):

    $date =
        date(
            'Y-m-d',
            strtotime(
                "+$i days",
                $current_date
            )
        );

    $day_name =
        date(
            'l',
            strtotime($date)
        );

    $found = false;


    foreach (
        $timesheets
        as $entry
    ):

        if (
            $entry['work_date'] ==
            $date
        ):

            $found = true;

?>

<tr>

    <td>

        <?= htmlspecialchars(
            $day_name
        ); ?>

    </td>


    <td>

        <?= date(
            'd M Y',
            strtotime($date)
        ); ?>

    </td>


    <td>

        <?= nl2br(
            htmlspecialchars(
                $entry['task_description']
                ?? ''
            )
        ); ?>

    </td>


    <td>

        <?= number_format(
            (float)$entry['display_hours'],
            2
        ); ?>

        hrs

    </td>

</tr>

<?php

        endif;

    endforeach;


    if (!$found):

?>

<tr>

    <td>

        <?= htmlspecialchars(
            $day_name
        ); ?>

    </td>


    <td>

        <?= date(
            'd M Y',
            strtotime($date)
        ); ?>

    </td>


    <td>
        —
    </td>


    <td>
        0.00 hrs
    </td>

</tr>

<?php

    endif;

endfor;

?>


<!-- TOTAL -->

<tr class="total-row">

    <td colspan="3">

        TOTAL WEEKLY HOURS

    </td>

    <td>

        <?= number_format(
            $total_hours,
            2
        ); ?>

        hrs

    </td>

</tr>

</tbody>

</table>

</div>


<!-- =====================================================
     SUBMISSION DATE
====================================================== -->

<?php if ($submitted_at != ''): ?>

<p style="margin-top:20px;">

    <strong>
        Submitted:
    </strong>

    <?= htmlspecialchars(
        $submitted_at
    ); ?>

</p>

<?php endif; ?>


<?php else: ?>


<!-- =====================================================
     EMPTY STATE
====================================================== -->

<div class="empty">

    <h3>
        Select a Staff Member
    </h3>

    <p>
        Select a week and staff member above
        to view the weekly timesheet report.
    </p>

</div>


<?php endif; ?>


</div>

</div>

</div>


</body>

</html>