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
$fullname = $_SESSION['fullname'] ?? "Staff";


/* =========================================================
   FETCH STAFF INFORMATION
========================================================= */

$staff = [];

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
    die("Database error: Unable to prepare staff information query.");
}

$stmt->bind_param("s", $staff_id);

if (!$stmt->execute()) {
    die("Database error: Unable to load staff information.");
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();

    session_destroy();

    header("Location: staff_login.php");
    exit();
}

$staff = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   STAFF DETAILS
========================================================= */

$fullname = $staff['fullname'] ?? "Staff";

$supervisor_id = $staff['supervisor_id'] ?? "";

$supervisor_name = $staff['supervisor_name'] ?? "Not Assigned";


/* =========================================================
   PROFILE PHOTO
========================================================= */

$profile_photo = "uploads/staff/default.png";

/*
 * Your database may use profile_picture or profile_photo.
 * Check both so the page remains compatible with either field.
 */

$stored_photo = "";

if (!empty($staff['profile_picture'])) {

    $stored_photo = $staff['profile_picture'];

} elseif (!empty($staff['profile_photo'])) {

    $stored_photo = $staff['profile_photo'];
}


if (!empty($stored_photo)) {

    $photo_name = basename($stored_photo);


    if (file_exists("uploads/staff/" . $photo_name)) {

        $profile_photo = "uploads/staff/" . $photo_name;

    } elseif (file_exists("uploads/" . $photo_name)) {

        $profile_photo = "uploads/" . $photo_name;

    } elseif (file_exists($stored_photo)) {

        $profile_photo = $stored_photo;
    }
}


/* =========================================================
   SELECT WEEK
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selected_week = $_POST['week_start'] ?? date('Y-m-d');

} else {

    $selected_week = $_GET['week'] ?? date('Y-m-d');
}


/* =========================================================
   VALIDATE SELECTED DATE
========================================================= */

$timestamp = strtotime($selected_week);

if ($timestamp === false) {
    $timestamp = strtotime(date('Y-m-d'));
}


/* =========================================================
   CALCULATE MONDAY
========================================================= */

$day_of_week = date('N', $timestamp);

$week_start = date(
    'Y-m-d',
    strtotime(
        "-" . ($day_of_week - 1) . " days",
        $timestamp
    )
);


/* =========================================================
   CALCULATE SUNDAY
========================================================= */

$week_end = date(
    'Y-m-d',
    strtotime(
        "+6 days",
        strtotime($week_start)
    )
);


/* =========================================================
   VARIABLES
========================================================= */

$message = "";

$error = "";

$submitted = false;

$submission_status = "";

$supervisor_review = null;


/* =========================================================
   CHECK WEEK SUBMISSION STATUS
========================================================= */

/*
 * Pending and Approved prevent another submission.
 *
 * Rejected allows the staff member to correct
 * and resubmit the timesheet.
 */

$stmt = $conn->prepare("
    SELECT status
    FROM timesheets
    WHERE staff_id = ?
    AND week_start = ?
    AND week_end = ?
    AND status IN ('Pending', 'Approved')
    ORDER BY id DESC
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param(
        "sss",
        $staff_id,
        $week_start,
        $week_end
    );

    if ($stmt->execute()) {

        $check = $stmt->get_result();

        if ($check->num_rows > 0) {

            $existing = $check->fetch_assoc();

            $submitted = true;

            $submission_status = $existing['status'] ?? "Pending";
        }
    }

    $stmt->close();
}


/* =========================================================
   LOAD EXISTING TIMESHEET ENTRIES
========================================================= */

$existing_entries = [];

$stmt = $conn->prepare("
    SELECT
        id,
        work_date,
        task_description,
        hours_worked,
        status
    FROM timesheets
    WHERE staff_id = ?
    AND week_start = ?
    AND week_end = ?
    ORDER BY work_date ASC, id DESC
");

if ($stmt) {

    $stmt->bind_param(
        "sss",
        $staff_id,
        $week_start,
        $week_end
    );

    if ($stmt->execute()) {

        $entries_result = $stmt->get_result();

        while ($entry = $entries_result->fetch_assoc()) {

            /*
             * Keep the newest record for each day.
             */

            if (!isset($existing_entries[$entry['work_date']])) {

                $existing_entries[$entry['work_date']] = $entry;
            }
        }
    }

    $stmt->close();
}


/* =========================================================
   PROCESS TIMESHEET SUBMISSION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted_week_start = $_POST['week_start'] ?? "";

    $posted_week_end = $_POST['week_end'] ?? "";


    /* =====================================================
       VERIFY WEEK
    ===================================================== */

    if (
        $posted_week_start !== $week_start ||
        $posted_week_end !== $week_end
    ) {

        $error = "Invalid timesheet week.";
    }


    /* =====================================================
       PREVENT DUPLICATE SUBMISSION
    ===================================================== */

    elseif ($submitted) {

        if (
            strtolower($submission_status) === 'approved'
        ) {

            $error =
                "This week's timesheet has already been approved. You cannot submit it again.";

        } else {

            $error =
                "This week's timesheet is already pending supervisor approval.";
        }
    }


    /* =====================================================
       CHECK SUPERVISOR
    ===================================================== */

    elseif (empty($supervisor_id)) {

        $error =
            "You do not have a supervisor assigned. Please contact the administrator.";
    }


    /* =====================================================
       PROCESS FORM
    ===================================================== */

    else {

        $hours_input = $_POST['hours'] ?? [];

        $task_input = $_POST['task'] ?? [];

        $total_hours = 0;

        $has_entry = false;


        /* =================================================
           VALIDATE ALL SEVEN DAYS
        ================================================= */

        for ($i = 0; $i < 7; $i++) {

            $work_date = date(
                'Y-m-d',
                strtotime(
                    "+$i days",
                    strtotime($week_start)
                )
            );


            $hours = trim(
                $hours_input[$work_date] ?? ""
            );


            $task = trim(
                $task_input[$work_date] ?? ""
            );


            /*
             * Empty day is allowed.
             */

            if ($hours === "" && $task === "") {
                continue;
            }


            /*
             * Task requires hours.
             */

            if ($task !== "" && $hours === "") {

                $error =
                    "Please enter the hours for " .
                    date(
                        "l",
                        strtotime($work_date)
                    ) .
                    ".";

                break;
            }


            /*
             * Hours requires task.
             */

            if ($hours !== "" && $task === "") {

                $error =
                    "Please enter the task performed for " .
                    date(
                        "l",
                        strtotime($work_date)
                    ) .
                    ".";

                break;
            }


            /*
             * Hours must be numeric.
             */

            if (!is_numeric($hours)) {

                $error =
                    "Hours for " .
                    date(
                        "l",
                        strtotime($work_date)
                    ) .
                    " must be a number.";

                break;
            }


            $hours_value = (float)$hours;


            /*
             * Hours must be between 0 and 24.
             */

            if ($hours_value < 0 || $hours_value > 24) {

                $error =
                    "Hours for " .
                    date(
                        "l",
                        strtotime($work_date)
                    ) .
                    " must be between 0 and 24.";

                break;
            }


            /*
             * Prevent extremely small/empty values.
             */

            if ($hours_value <= 0) {

                $error =
                    "Hours for " .
                    date(
                        "l",
                        strtotime($work_date)
                    ) .
                    " must be greater than zero.";

                break;
            }


            $total_hours += $hours_value;

            $has_entry = true;
        }


        /* =================================================
           VALIDATION RESULT
        ================================================= */

        if (empty($error)) {

            if (!$has_entry) {

                $error =
                    "Please enter at least one day's hours and task before submitting.";

            } elseif ($total_hours <= 0) {

                $error =
                    "The total weekly hours must be greater than zero.";

            } else {


                /* =========================================
                   BEGIN TRANSACTION
                ========================================= */

                mysqli_begin_transaction($conn);


                try {


                    /* =====================================
                       PROCESS EACH DAY
                    ===================================== */

                    for ($i = 0; $i < 7; $i++) {

                        $work_date = date(
                            'Y-m-d',
                            strtotime(
                                "+$i days",
                                strtotime($week_start)
                            )
                        );


                        $hours = trim(
                            $hours_input[$work_date] ?? ""
                        );


                        $task = trim(
                            $task_input[$work_date] ?? ""
                        );


                        /*
                         * Empty days:
                         * remove only rejected records.
                         */

                        if ($hours === "" && $task === "") {

                            $delete_stmt = $conn->prepare("
                                DELETE FROM timesheets
                                WHERE staff_id = ?
                                AND work_date = ?
                                AND week_start = ?
                                AND week_end = ?
                                AND status = 'Rejected'
                            ");

                            if (!$delete_stmt) {

                                throw new Exception(
                                    "Unable to prepare rejected entry cleanup."
                                );
                            }


                            $delete_stmt->bind_param(
                                "ssss",
                                $staff_id,
                                $work_date,
                                $week_start,
                                $week_end
                            );


                            if (!$delete_stmt->execute()) {

                                throw new Exception(
                                    "Unable to remove rejected entry: " .
                                    $delete_stmt->error
                                );
                            }


                            $delete_stmt->close();

                            continue;
                        }


                        $hours_value = (float)$hours;


                        /* =================================
                           FIND REJECTED ENTRY
                        ================================= */

                        $check_stmt = $conn->prepare("
                            SELECT id
                            FROM timesheets
                            WHERE staff_id = ?
                            AND work_date = ?
                            AND week_start = ?
                            AND week_end = ?
                            AND status = 'Rejected'
                            ORDER BY id DESC
                            LIMIT 1
                        ");

                        if (!$check_stmt) {

                            throw new Exception(
                                "Unable to check existing rejected timesheet."
                            );
                        }


                        $check_stmt->bind_param(
                            "ssss",
                            $staff_id,
                            $work_date,
                            $week_start,
                            $week_end
                        );


                        if (!$check_stmt->execute()) {

                            throw new Exception(
                                "Unable to check existing timesheet."
                            );
                        }


                        $check_result =
                            $check_stmt->get_result();


                        $existing_rejected =
                            $check_result->fetch_assoc();


                        $check_stmt->close();


                        /* =================================
                           REJECTED ENTRY EXISTS
                           UPDATE IT
                        ================================= */

                        if ($existing_rejected) {

                            $timesheet_id =
                                (int)$existing_rejected['id'];


                            $update_stmt = $conn->prepare("
                                UPDATE timesheets
                                SET
                                    task_description = ?,
                                    hours_worked = ?,
                                    status = 'Pending',
                                    submitted_at = NOW()
                                WHERE id = ?
                                AND staff_id = ?
                                AND status = 'Rejected'
                            ");

                            if (!$update_stmt) {

                                throw new Exception(
                                    "Unable to prepare timesheet update."
                                );
                            }


                            $update_stmt->bind_param(
                                "sdis",
                                $task,
                                $hours_value,
                                $timesheet_id,
                                $staff_id
                            );


                            if (!$update_stmt->execute()) {

                                throw new Exception(
                                    "Unable to update rejected timesheet: " .
                                    $update_stmt->error
                                );
                            }


                            $update_stmt->close();


                        } else {


                            /* =============================
                               CREATE NEW ENTRY
                            ============================= */

                            $insert_stmt = $conn->prepare("
                                INSERT INTO timesheets
                                (
                                    staff_id,
                                    work_date,
                                    week_start,
                                    week_end,
                                    task_description,
                                    hours_worked,
                                    status,
                                    submitted_at
                                )
                                VALUES
                                (
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    'Pending',
                                    NOW()
                                )
                            ");

                            if (!$insert_stmt) {

                                throw new Exception(
                                    "Unable to prepare timesheet entry."
                                );
                            }


                            $insert_stmt->bind_param(
                                "sssssd",
                                $staff_id,
                                $work_date,
                                $week_start,
                                $week_end,
                                $task,
                                $hours_value
                            );


                            if (!$insert_stmt->execute()) {

                                throw new Exception(
                                    "Unable to save timesheet entry: " .
                                    $insert_stmt->error
                                );
                            }


                            $insert_stmt->close();
                        }
                    }


                    /* =====================================
                       COMMIT
                    ===================================== */

                    mysqli_commit($conn);


                    $message =
                        "Your timesheet has been submitted successfully and is now awaiting supervisor approval.";


                    $submitted = true;

                    $submission_status = "Pending";


                    /* =====================================
                       CLEAR POST VALUES
                    ===================================== */

                    $_POST['hours'] = [];

                    $_POST['task'] = [];


                    /* =====================================
                       RELOAD ENTRIES
                    ===================================== */

                    $existing_entries = [];


                    $stmt = $conn->prepare("
                        SELECT
                            id,
                            work_date,
                            task_description,
                            hours_worked,
                            status
                        FROM timesheets
                        WHERE staff_id = ?
                        AND week_start = ?
                        AND week_end = ?
                        ORDER BY work_date ASC, id DESC
                    ");


                    if ($stmt) {

                        $stmt->bind_param(
                            "sss",
                            $staff_id,
                            $week_start,
                            $week_end
                        );


                        $stmt->execute();


                        $saved_result =
                            $stmt->get_result();


                        while (
                            $entry =
                            $saved_result->fetch_assoc()
                        ) {

                            if (
                                !isset(
                                    $existing_entries[
                                        $entry['work_date']
                                    ]
                                )
                            ) {

                                $existing_entries[
                                    $entry['work_date']
                                ] = $entry;
                            }
                        }


                        $stmt->close();
                    }


                } catch (Exception $e) {


                    /* =====================================
                       ROLLBACK
                    ===================================== */

                    mysqli_rollback($conn);


                    $error =
                        $e->getMessage();
                }
            }
        }
    }
}


/* =========================================================
   LOAD SUPERVISOR APPROVAL / REJECTION COMMENT
========================================================= */

$stmt = $conn->prepare("
    SELECT
        ta.decision,
        ta.comment,
        ta.approved_at,
        s.fullname AS supervisor_name
    FROM timesheet_approval ta
    INNER JOIN timesheets t
        ON ta.timesheet_id = t.id
    LEFT JOIN supervisors s
        ON ta.supervisor_id = s.supervisor_id
    WHERE t.staff_id = ?
    AND t.week_start = ?
    AND t.week_end = ?
    ORDER BY ta.approved_at DESC
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param(
        "sss",
        $staff_id,
        $week_start,
        $week_end
    );

    if ($stmt->execute()) {

        $review_result = $stmt->get_result();

        if ($review_result->num_rows > 0) {

            $supervisor_review =
                $review_result->fetch_assoc();
        }
    }

    $stmt->close();
}


/* =========================================================
   DISPLAY DAYS
========================================================= */

$days = [
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
    "Sunday"
];


/* =========================================================
   CALCULATE SAVED TOTAL
========================================================= */

$week_total = 0;

foreach ($existing_entries as $entry) {

    $week_total +=
        (float)$entry['hours_worked'];
}


/* =========================================================
   PRESERVE FORM VALUES
========================================================= */

$form_hours =
    $_POST['hours'] ?? [];

$form_tasks =
    $_POST['task'] ?? [];


/* =========================================================
   APPLICATION SETTINGS
========================================================= */

$organization_name =
    $app['organization_name']
    ?? 'NTISHOR WEB ENTERPRISE';

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Timesheet - <?php echo htmlspecialchars($organization_name); ?></title>

<link
    rel="stylesheet"
    href="styles.css"
>

<style>

/* =========================================================
   MAIN
========================================================= */

.main {
    flex: 1;
    width: auto;
    min-width: 0;
    min-height: 100vh;
    margin-left: 0 !important;
    background: #f5f6f8;
    box-sizing: border-box;
}


/* =========================================================
   PAGE
========================================================= */

.timesheet-page {
    padding: 25px;
    box-sizing: border-box;
}


/* =========================================================
   HEADER
========================================================= */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h1 {
    margin: 0;
    font-size: 28px;
}

.page-header p {
    margin: 7px 0 0;
    color: #666;
}


/* =========================================================
   WEEK SELECTOR
========================================================= */

.week-box {
    background: #ffffff;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    margin-bottom: 20px;
}

.week-form {
    display: flex;
    align-items: flex-end;
    gap: 15px;
    flex-wrap: wrap;
}

.week-group {
    display: flex;
    flex-direction: column;
}

.week-group label {
    font-weight: 600;
    margin-bottom: 7px;
}

.week-group input {
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-sizing: border-box;
}

.week-info {
    padding: 11px 15px;
    background: #f1f5f9;
    border-radius: 6px;
    color: #475569;
}


/* =========================================================
   ALERTS
========================================================= */

.alert {
    padding: 14px 18px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}


/* =========================================================
   SUPERVISOR
========================================================= */

.supervisor-box {
    background: #ffffff;
    padding: 18px 20px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
}

.supervisor-box strong {
    color: #334155;
}


/* =========================================================
   TIMESHEET BOX
========================================================= */

.timesheet-box {
    background: #ffffff;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}

.timesheet-box h2 {
    margin-top: 0;
    margin-bottom: 20px;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.timesheet-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

.timesheet-table th,
.timesheet-table td {
    padding: 13px 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: middle;
}

.timesheet-table th {
    background: #f8fafc;
    font-size: 13px;
    color: #475569;
}

.timesheet-table tr:hover {
    background: #fafafa;
}


/* =========================================================
   HOURS INPUT
========================================================= */

.hours-input {
    width: 95px;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    text-align: center;
    box-sizing: border-box;
}

.hours-input:focus {
    outline: none;
    border-color: #2563eb;
}


/* =========================================================
   TASK INPUT
========================================================= */

.task-input {
    width: 100%;
    min-width: 300px;
    min-height: 70px;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    resize: vertical;
    box-sizing: border-box;
    font-family: inherit;
    font-size: 14px;
}

.task-input:focus {
    outline: none;
    border-color: #2563eb;
}


/* =========================================================
   DISABLED INPUT
========================================================= */

.hours-input:disabled,
.task-input:disabled {
    background: #f1f5f9;
    color: #475569;
    cursor: not-allowed;
}


/* =========================================================
   TOTAL
========================================================= */

.total-row {
    background: #f8fafc;
}

.total-row td {
    font-weight: 700;
    font-size: 15px;
}


/* =========================================================
   SUBMIT AREA
========================================================= */

.submit-area {
    margin-top: 25px;
    display: flex;
    justify-content: flex-end;
}

.submit-btn {
    border: none;
    background: #2563eb;
    color: #ffffff;
    padding: 13px 25px;
    border-radius: 7px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.submit-btn:hover {
    background: #1d4ed8;
}

.submit-btn:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}


/* =========================================================
   STATUS
========================================================= */

.status {
    display: inline-block;
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}


/* =========================================================
   SUPERVISOR REVIEW
========================================================= */

.supervisor-review {
    margin-bottom: 20px;
    padding: 20px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.review-approved {
    border-left: 5px solid #198754;
}

.review-rejected {
    border-left: 5px solid #dc3545;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.review-header h3 {
    margin: 0;
    font-size: 18px;
}

.review-decision {
    display: inline-block;
    padding: 6px 13px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.decision-approved {
    background: #d1fae5;
    color: #065f46;
}

.decision-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.review-details {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.review-details p {
    margin: 0;
    color: #475569;
}

.review-comment {
    background: #f8fafc;
    padding: 15px;
    border-radius: 7px;
    border: 1px solid #e5e7eb;
}

.review-comment strong {
    color: #334155;
}

.review-comment p {
    margin: 8px 0 0;
    color: #475569;
    line-height: 1.6;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {

    .sidebar {
        width: 173px;
        flex: 0 0 173px;
    }

    .main {
        margin-left: 0 !important;
        width: auto;
        flex: 1;
    }

}


@media (max-width: 600px) {

    .container {
        flex-direction: column;
    }

    .sidebar {
        position: relative;
        width: 100%;
        min-width: 100%;
        max-width: 100%;
        flex: none;
        min-height: auto;
    }

    .main {
        margin-left: 0 !important;
        width: 100%;
        min-width: 100%;
    }

    .timesheet-page {
        padding: 15px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .week-form {
        align-items: stretch;
        flex-direction: column;
    }

    .week-group,
    .week-group input,
    .week-form .submit-btn {
        width: 100%;
    }

    .submit-area {
        justify-content: stretch;
    }

    .submit-area .submit-btn {
        width: 100%;
    }

    .review-header {
        align-items: flex-start;
        flex-direction: column;
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
                $organization_name
            );
            ?>
        </h2>

        <p>
            Personnel Timesheet System
        </p>

    </div>


    <!-- PROFILE -->

    <div class="profile">

        <div class="avatar">

            <img
                src="<?php echo htmlspecialchars($profile_photo); ?>"
                class="profile-small"
                alt="Profile Photo"
                onerror="this.src='uploads/staff/default.png';"
            >

        </div>


        <h3>
            <?php
            echo htmlspecialchars($fullname);
            ?>
        </h3>

        <p>Staff</p>

    </div>

    <!-- NAVIGATION -->

    <ul>
        <li><a href="staff_dashboard.php">Dashboard</a></li>
        <li><a href="attendance.php">Clock In / Out</a></li>
        <li><a href="staff_attendance_history.php">Attendance History</a></li>
        <li><a href="staff_tasks.php">Assigned Tasks</a></li>
        <li><a href="staff_timesheet_report.php">Timesheet Report</a></li>
        <li><a href="staff_logout.php">Logout</a></li>
    </ul>

</div>

<!-- =====================================================
     MAIN CONTENT
====================================================== -->

<div class="main">

<div class="timesheet-page">

<!-- =====================================================
     PAGE HEADER
====================================================== -->

<div class="page-header">

    <div>
        <h1>My Timesheet</h1>
        <p>Complete and submit your weekly timesheet for supervisor approval.</p>
    </div>

</div>

<!-- =====================================================
     SUCCESS MESSAGE
====================================================== -->

<?php if (!empty($message)) { ?>

    <div class="alert alert-success">

        <?php
        echo htmlspecialchars($message);
        ?>

    </div>

<?php } ?>


<!-- =====================================================
     ERROR MESSAGE
====================================================== -->

<?php if (!empty($error)) { ?>

    <div class="alert alert-error">

        <?php
        echo htmlspecialchars($error);
        ?>

    </div>

<?php } ?>


<!-- =====================================================
     WEEK SELECTOR
====================================================== -->

<div class="week-box">

<form
    method="GET"
    class="week-form"
>

    <div class="week-group">

        <label for="week">
            Select Week
        </label>

        <input
            type="date"
            id="week"
            name="week"
            value="<?php
                echo htmlspecialchars($week_start);
            ?>"
            required
        >

    </div>


    <div class="week-info">

        Week:

        <strong>
            <?php
            echo date(
                "d M Y",
                strtotime($week_start)
            );
            ?>
        </strong>

        &nbsp;–&nbsp;

        <strong>
            <?php
            echo date(
                "d M Y",
                strtotime($week_end)
            );
            ?>
        </strong>

    </div>


    <button
        type="submit"
        class="submit-btn"
    >
        Load Week
    </button>

</form>

</div>


<!-- =====================================================
     SUPERVISOR
====================================================== -->

<div class="supervisor-box">

    <strong>
        Supervisor:
    </strong>

    <?php
    echo htmlspecialchars($supervisor_name);
    ?>

</div>


<!-- =====================================================
     TIMESHEET
====================================================== -->

<div class="timesheet-box">

<h2>
    Weekly Timesheet
</h2>


<!-- =====================================================
     SUBMISSION STATUS
====================================================== -->

<?php if ($submitted) { ?>

    <div class="alert alert-success">

        This week's timesheet has already been submitted.

        <strong>
            Status:
        </strong>

        <span class="status status-<?php
            echo strtolower(
                htmlspecialchars(
                    $submission_status
                )
            );
        ?>">

            <?php
            echo htmlspecialchars(
                ucfirst(
                    $submission_status
                )
            );
            ?>

        </span>

    </div>

<?php } ?>


<!-- =====================================================
     SUPERVISOR REVIEW
====================================================== -->

<?php if ($supervisor_review) { ?>

<?php

$review_decision =
    strtolower(
        trim(
            $supervisor_review['decision'] ?? ''
        )
    );

$is_review_approved =
    $review_decision === 'approved';

?>

<div class="supervisor-review <?php
    echo $is_review_approved
        ? 'review-approved'
        : 'review-rejected';
?>">

    <div class="review-header">

        <h3>
            Supervisor Review
        </h3>

        <span class="review-decision <?php
            echo $is_review_approved
                ? 'decision-approved'
                : 'decision-rejected';
        ?>">

            <?php
            echo htmlspecialchars(
                ucfirst(
                    $supervisor_review['decision']
                    ?? ''
                )
            );
            ?>

        </span>

    </div>


    <div class="review-details">

        <p>

            <strong>
                Supervisor:
            </strong>

            <?php
            echo htmlspecialchars(
                $supervisor_review['supervisor_name']
                ?? 'Supervisor'
            );
            ?>

        </p>


        <p>

            <strong>
                Reviewed:
            </strong>

            <?php

            if (
                !empty(
                    $supervisor_review['approved_at']
                )
            ) {

                echo date(
                    "d M Y, h:i A",
                    strtotime(
                        $supervisor_review['approved_at']
                    )
                );

            } else {

                echo "Not available";
            }

            ?>

        </p>

    </div>


    <?php if (
        !empty(
            $supervisor_review['comment']
        )
    ) { ?>

        <div class="review-comment">

            <strong>
                Supervisor Comment:
            </strong>

            <p>

                <?php
                echo nl2br(
                    htmlspecialchars(
                        $supervisor_review['comment']
                    )
                );
                ?>

            </p>

        </div>

    <?php } else { ?>

        <div class="review-comment">

            <strong>
                Supervisor Comment:
            </strong>

            <p>
                No comment was provided by the supervisor.
            </p>

        </div>

    <?php } ?>

</div>

<?php } ?>


<!-- =====================================================
     TIMESHEET FORM
====================================================== -->

<form method="POST">

<input
    type="hidden"
    name="week_start"
    value="<?php
        echo htmlspecialchars($week_start);
    ?>"
>

<input
    type="hidden"
    name="week_end"
    value="<?php
        echo htmlspecialchars($week_end);
    ?>"
>


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
        Hours
    </th>

    <th>
        Task / Work Performed
    </th>

</tr>

</thead>


<tbody>

<?php

$week_total_display = 0;

for ($i = 0; $i < 7; $i++) {

    $day_date = date(
        'Y-m-d',
        strtotime(
            "+$i days",
            strtotime($week_start)
        )
    );


    $saved_entry =
        $existing_entries[$day_date]
        ?? null;


    $saved_hours = "";

    $saved_task = "";


    /*
     * Preserve POST values when there
     * was a validation error.
     */

    if (
        isset($form_hours[$day_date])
    ) {

        $saved_hours =
            $form_hours[$day_date];

    } elseif ($saved_entry) {

        $saved_hours =
            $saved_entry['hours_worked'];
    }


    if (
        isset($form_tasks[$day_date])
    ) {

        $saved_task =
            $form_tasks[$day_date];

    } elseif ($saved_entry) {

        $saved_task =
            $saved_entry['task_description'];
    }


    /*
     * Calculate total.
     */

    if (
        $saved_hours !== "" &&
        is_numeric($saved_hours)
    ) {

        $week_total_display +=
            (float)$saved_hours;
    }

?>

<tr>


    <!-- DAY -->

    <td>

        <strong>
            <?php
            echo $days[$i];
            ?>
        </strong>

    </td>


    <!-- DATE -->

    <td>

        <?php
        echo date(
            "d M Y",
            strtotime($day_date)
        );
        ?>

    </td>


    <!-- HOURS -->

    <td>

        <input
            type="number"
            name="hours[<?php
                echo htmlspecialchars($day_date);
            ?>]"
            class="hours-input"
            min="0"
            max="24"
            step="0.01"
            placeholder="0.00"
            value="<?php
                echo htmlspecialchars($saved_hours);
            ?>"
            <?php
            echo $submitted
                ? 'disabled'
                : '';
            ?>
        >

    </td>


    <!-- TASK -->

    <td>

        <textarea
            name="task[<?php
                echo htmlspecialchars($day_date);
            ?>]"
            class="task-input"
            placeholder="Enter the work or task performed on this day..."
            <?php
            echo $submitted
                ? 'disabled'
                : '';
            ?>
        ><?php
            echo htmlspecialchars($saved_task);
        ?></textarea>

    </td>

</tr>

<?php } ?>


<!-- =====================================================
     TOTAL
====================================================== -->

<tr class="total-row">

    <td colspan="2">

        TOTAL WEEKLY HOURS

    </td>


    <td>

        <span id="weeklyTotal">

            <?php
            echo number_format(
                $week_total_display,
                2
            );
            ?>

        </span>

        hrs

    </td>


    <td>

        Weekly Timesheet

    </td>

</tr>

</tbody>

</table>

</div>


<!-- =====================================================
     SUBMIT BUTTON
====================================================== -->

<div class="submit-area">

<button
    type="submit"
    class="submit-btn"
    <?php
    echo $submitted
        ? 'disabled'
        : '';
    ?>
>

    <?php

    echo $submitted
        ? 'Timesheet Already Submitted'
        : 'Submit Timesheet for Approval';

    ?>

</button>

</div>


</form>

</div>


</div>

</div>

</div>


<!-- =====================================================
     AUTOMATIC WEEKLY TOTAL
====================================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const hourInputs =
            document.querySelectorAll(
                ".hours-input"
            );

        const weeklyTotal =
            document.getElementById(
                "weeklyTotal"
            );


        function calculateTotal() {

            let total = 0;


            hourInputs.forEach(
                function (input) {

                    const value =
                        parseFloat(
                            input.value
                        );


                    if (!isNaN(value)) {

                        total += value;
                    }

                }
            );


            weeklyTotal.textContent =
                total.toFixed(2);
        }


        hourInputs.forEach(
            function (input) {

                input.addEventListener(
                    "input",
                    calculateTotal
                );

            }
        );


        calculateTotal();

    }
);

</script>


</body>

</html>