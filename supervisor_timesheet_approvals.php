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

$message = "";
$error = "";


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

$supervisor_name =
    $supervisor['fullname'] ?? "Supervisor";


/* =========================================================
   PROCESS APPROVAL / REJECTION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? "";

    $staff_id =
        trim($_POST['staff_id'] ?? "");

    $week_start =
        trim($_POST['week_start'] ?? "");

    $week_end =
        trim($_POST['week_end'] ?? "");

    $comment =
        trim($_POST['comment'] ?? "");


    /* -----------------------------------------------------
       VALIDATE ACTION
    ----------------------------------------------------- */

    if (
        !in_array(
            $action,
            ['approve', 'reject'],
            true
        )
    ) {

        $error =
            "Invalid approval action.";

    }


    /* -----------------------------------------------------
       VALIDATE STAFF
    ----------------------------------------------------- */

    elseif (empty($staff_id)) {

        $error =
            "Staff member was not specified.";

    }


    /* -----------------------------------------------------
       VALIDATE WEEK
    ----------------------------------------------------- */

    elseif (
        empty($week_start) ||
        empty($week_end)
    ) {

        $error =
            "Timesheet week was not specified.";

    }


    /* -----------------------------------------------------
       REJECTION REQUIRES COMMENT
    ----------------------------------------------------- */

    elseif (
        $action === 'reject' &&
        empty($comment)
    ) {

        $error =
            "Please enter a comment explaining why the timesheet is being rejected.";

    }


    /* -----------------------------------------------------
       PROCESS REQUEST
    ----------------------------------------------------- */

    else {

        /*
         * Verify that this staff member actually belongs
         * to the logged-in supervisor.
         */

        $stmt = $conn->prepare("
            SELECT
                staff_id,
                fullname
            FROM staff
            WHERE staff_id = ?
            AND supervisor_id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error =
                "Unable to verify staff member.";

        } else {

            $stmt->bind_param(
                "ss",
                $staff_id,
                $supervisor_id
            );

            $stmt->execute();

            $staff_result =
                $stmt->get_result();

            if ($staff_result->num_rows == 0) {

                $error =
                    "You are not authorized to process this staff member's timesheet.";

                $stmt->close();

            } else {

                $staff_info =
                    $staff_result->fetch_assoc();

                $staff_name =
                    $staff_info['fullname'];

                $stmt->close();


                /* =========================================
                   GET WEEKLY TIMESHEET
                ========================================= */

                $stmt = $conn->prepare("
                    SELECT
                        id,
                        staff_id,
                        work_date,
                        task_description,
                        hours_worked,
                        status
                    FROM timesheets
                    WHERE staff_id = ?
                    AND week_start = ?
                    AND week_end = ?
                    ORDER BY work_date ASC, id ASC
                ");

                if (!$stmt) {

                    $error =
                        "Unable to load the timesheet.";

                } else {

                    $stmt->bind_param(
                        "sss",
                        $staff_id,
                        $week_start,
                        $week_end
                    );

                    $stmt->execute();

                    $timesheet_result =
                        $stmt->get_result();


                    if (
                        $timesheet_result->num_rows == 0
                    ) {

                        $error =
                            "No timesheet was found for the selected week.";

                        $stmt->close();

                    } else {

                        $entries = [];

                        while (
                            $entry =
                            $timesheet_result->fetch_assoc()
                        ) {

                            $entries[] = $entry;

                        }

                        $stmt->close();


                        /* =================================
                           CHECK CURRENT STATUS
                        ================================= */

                        $current_status =
                            strtolower(
                                $entries[0]['status'] ?? ''
                            );


                        if (
                            $current_status !== 'pending'
                        ) {

                            $error =
                                "This timesheet has already been processed.";

                        } else {


                            /* =============================
                               FIRST TIMESHEET ID
                            ============================= */

                            $timesheet_id =
                                (int)$entries[0]['id'];


                            /* =============================
                               NEW STATUS
                            ============================= */

                            $new_status =
                                ($action === 'approve')
                                    ? 'Approved'
                                    : 'Rejected';

                            $decision =
                                ($action === 'approve')
                                    ? 'Approved'
                                    : 'Rejected';


                            /* =============================
                               TRANSACTION
                            ============================= */

                            mysqli_begin_transaction(
                                $conn
                            );


                            try {


                                /* =========================
                                   UPDATE ALL WEEKLY ROWS
                                ========================= */

                                $stmt =
                                    $conn->prepare("
                                        UPDATE timesheets
                                        SET status = ?
                                        WHERE staff_id = ?
                                        AND week_start = ?
                                        AND week_end = ?
                                        AND status = 'Pending'
                                    ");

                                if (!$stmt) {

                                    throw new Exception(
                                        "Unable to update timesheet status."
                                    );

                                }

                                $stmt->bind_param(
                                    "ssss",
                                    $new_status,
                                    $staff_id,
                                    $week_start,
                                    $week_end
                                );

                                if (
                                    !$stmt->execute()
                                ) {

                                    throw new Exception(
                                        "Unable to update timesheet."
                                    );

                                }

                                $stmt->close();


                                /* =========================
                                   CHECK EXISTING APPROVAL
                                ========================= */

                                $stmt =
                                    $conn->prepare("
                                        SELECT id
                                        FROM timesheet_approval
                                        WHERE timesheet_id = ?
                                        AND supervisor_id = ?
                                        LIMIT 1
                                    ");

                                if (!$stmt) {

                                    throw new Exception(
                                        "Unable to check approval record."
                                    );

                                }

                                $stmt->bind_param(
                                    "is",
                                    $timesheet_id,
                                    $supervisor_id
                                );

                                /*
                                 * NOTE:
                                 * supervisor_id is VARCHAR,
                                 * therefore use "ss" instead.
                                 */

                                $stmt->close();


                                $stmt =
                                    $conn->prepare("
                                        SELECT id
                                        FROM timesheet_approval
                                        WHERE timesheet_id = ?
                                        AND supervisor_id = ?
                                        LIMIT 1
                                    ");

                                if (!$stmt) {

                                    throw new Exception(
                                        "Unable to check approval record."
                                    );

                                }

                                $stmt->bind_param(
                                    "is",
                                    $timesheet_id,
                                    $supervisor_id
                                );

                                /*
                                 * If your supervisor_id is VARCHAR,
                                 * MySQLi should use "is" only if the
                                 * first parameter is integer and second
                                 * is string.
                                 */

                                $stmt->execute();

                                $approval_result =
                                    $stmt->get_result();

                                $approval_exists =
                                    $approval_result->num_rows > 0;

                                $existing_approval =
                                    $approval_exists
                                        ? $approval_result->fetch_assoc()
                                        : null;

                                $stmt->close();


                                /* =========================
                                   INSERT OR UPDATE APPROVAL
                                ========================= */

                                if (
                                    $approval_exists
                                ) {

                                    $approval_id =
                                        (int)$existing_approval['id'];

                                    $stmt =
                                        $conn->prepare("
                                            UPDATE timesheet_approval
                                            SET
                                                decision = ?,
                                                comment = ?,
                                                approved_at = NOW()
                                            WHERE id = ?
                                            AND supervisor_id = ?
                                        ");

                                    if (!$stmt) {

                                        throw new Exception(
                                            "Unable to update approval record."
                                        );

                                    }

                                    $stmt->bind_param(
                                        "ssis",
                                        $decision,
                                        $comment,
                                        $approval_id,
                                        $supervisor_id
                                    );

                                } else {

                                    $stmt =
                                        $conn->prepare("
                                            INSERT INTO timesheet_approval
                                            (
                                                timesheet_id,
                                                supervisor_id,
                                                decision,
                                                comment,
                                                approved_at
                                            )
                                            VALUES
                                            (
                                                ?,
                                                ?,
                                                ?,
                                                ?,
                                                NOW()
                                            )
                                        ");

                                    if (!$stmt) {

                                        throw new Exception(
                                            "Unable to create approval record."
                                        );

                                    }

                                    $stmt->bind_param(
                                        "isss",
                                        $timesheet_id,
                                        $supervisor_id,
                                        $decision,
                                        $comment
                                    );

                                }


                                if (
                                    !$stmt->execute()
                                ) {

                                    throw new Exception(
                                        "Unable to save approval decision: " .
                                        $stmt->error
                                    );

                                }

                                $stmt->close();


                                /* =========================
                                   NOTIFICATION
                                ========================= */

                                if (
                                    function_exists(
                                        'createNotification'
                                    )
                                ) {

                                    if (
                                        $action === 'approve'
                                    ) {

                                        $notification_message =
                                            "Your timesheet for " .
                                            date(
                                                "d M Y",
                                                strtotime($week_start)
                                            ) .
                                            " - " .
                                            date(
                                                "d M Y",
                                                strtotime($week_end)
                                            ) .
                                            " has been approved by your supervisor.";

                                    } else {

                                        $notification_message =
                                            "Your timesheet for " .
                                            date(
                                                "d M Y",
                                                strtotime($week_start)
                                            ) .
                                            " - " .
                                            date(
                                                "d M Y",
                                                strtotime($week_end)
                                            ) .
                                            " has been rejected by your supervisor.";

                                        if (
                                            !empty($comment)
                                        ) {

                                            $notification_message .=
                                                " Comment: " .
                                                $comment;
                                        }

                                    }


                                    /*
                                     * This assumes your
                                     * notification function uses:
                                     *
                                     * createNotification(
                                     *     $conn,
                                     *     $staff_id,
                                     *     $message
                                     * );
                                     *
                                     * If your function has a
                                     * different structure, we will
                                     * adjust it.
                                     */

                                    try {

                                        createNotification(
                                            $conn,
                                            $staff_id,
                                            $notification_message
                                        );

                                    } catch (Throwable $notification_error) {

                                        /*
                                         * Do not cancel approval
                                         * if notification fails.
                                         */

                                    }

                                }


                                /* =========================
                                   ACTIVITY LOG
                                ========================= */

                                if (
                                    function_exists(
                                        'logActivity'
                                    )
                                ) {

                                    logActivity(
                                        $conn,
                                        $supervisor_id,
                                        "Supervisor",
                                        "Timesheet " . $decision,
                                        $decision .
                                        " timesheet for " .
                                        $staff_name .
                                        " (" .
                                        $week_start .
                                        " to " .
                                        $week_end .
                                        ")"
                                    );

                                }


                                /* =========================
                                   COMMIT
                                ========================= */

                                mysqli_commit(
                                    $conn
                                );


                                if (
                                    $action === 'approve'
                                ) {

                                    $message =
                                        "Timesheet for " .
                                        htmlspecialchars(
                                            $staff_name
                                        ) .
                                        " has been approved successfully.";

                                } else {

                                    $message =
                                        "Timesheet for " .
                                        htmlspecialchars(
                                            $staff_name
                                        ) .
                                        " has been rejected successfully.";

                                }

                            } catch (
                                Throwable $e
                            ) {

                                mysqli_rollback(
                                    $conn
                                );

                                $error =
                                    $e->getMessage();

                            }

                        }

                    }

                }

            }

        }

    }

}


/* =========================================================
   LOAD PENDING TIMESHEETS
========================================================= */

$pending_timesheets = [];


/*
 * Get only staff belonging to this supervisor.
 *
 * Grouping is done by staff + week.
 */

$stmt = $conn->prepare("
    SELECT
        t.staff_id,
        s.fullname,
        s.department,
        t.week_start,
        t.week_end,
        MIN(t.id) AS timesheet_id,
        SUM(t.hours_worked) AS total_hours,
        MIN(t.submitted_at) AS submitted_at
    FROM timesheets t
    INNER JOIN staff s
        ON t.staff_id = s.staff_id
    WHERE s.supervisor_id = ?
    AND LOWER(t.status) = 'pending'
    GROUP BY
        t.staff_id,
        s.fullname,
        s.department,
        t.week_start,
        t.week_end
    ORDER BY
        t.submitted_at ASC
");

if (!$stmt) {

    die(
        "Unable to load pending timesheets: " .
        $conn->error
    );

}

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$result =
    $stmt->get_result();

while (
    $record =
    $result->fetch_assoc()
) {

    $pending_timesheets[] =
        $record;

}

$stmt->close();


/* =========================================================
   COUNT PENDING
========================================================= */

$pending_count =
    count($pending_timesheets);


/* =========================================================
   PROFILE PHOTO
========================================================= */

$profile_photo =
    "uploads/supervisors/default.png";


if (
    !empty(
        $supervisor['profile_photo']
    )
) {

    $photo_name =
        basename(
            $supervisor['profile_photo']
        );


    if (
        file_exists(
            "uploads/supervisors/" .
            $photo_name
        )
    ) {

        $profile_photo =
            "uploads/supervisors/" .
            $photo_name;

    }

    elseif (
        file_exists(
            "uploads/" .
            $photo_name
        )
    ) {

        $profile_photo =
            "uploads/" .
            $photo_name;

    }

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
Timesheet Approvals
</title>

<link
    rel="stylesheet"
    href="styles.css"
>

<style>
/* =========================================================
   SUPERVISOR APPROVAL PAGE - SIDEBAR
========================================================= */

/* RESET */
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
   SIDEBAR LOGO / ORGANIZATION
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
   PROFILE AREA
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

    border: 3px solid rgba(255, 255, 255, 0.9) !important;

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

    transition: background 0.2s ease,
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

    font-weight: 500;
}

/* =========================================================
   MAIN CONTENT
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

.approval-page {
    width: 100%;

    max-width: 1400px;

    margin: 0 auto;

    padding: 25px;
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
    background: rgba(255, 255, 255, 0.25);
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

    .approval-page {
        padding: 15px;
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


    <!-- PROFILE -->

    <div class="profile">

        <div class="avatar">

            <img
                src="<?php
                    echo htmlspecialchars(
                        $profile_photo
                    );
                ?>"
                alt="Profile Photo"
                class="profile-small"
                onerror="this.src='uploads/supervisors/default.png';"
            >

        </div>


        <h3>

            <?php

            echo htmlspecialchars(
                $supervisor_name
            );

            ?>

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

            <a href="supervisor_reports.php">
                Reports
            </a>

        </li>

        <li>

            <a href="supervisor_timesheet_report.php">
                Timesheet Report            </a>

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

<div class="approval-page">


<!-- =====================================================
     HEADER
====================================================== -->

<div class="page-header">

    <div>

        <h1>
            Timesheet Approvals
        </h1>

        <p>
            Review and approve or reject timesheets
            submitted by your assigned staff.
        </p>

    </div>

</div>


<!-- =====================================================
     ALERTS
====================================================== -->

<?php if (!empty($message)) { ?>

    <div class="alert alert-success">

        <?php

        echo $message;

        ?>

    </div>

<?php } ?>


<?php if (!empty($error)) { ?>

    <div class="alert alert-error">

        <?php

        echo htmlspecialchars(
            $error
        );

        ?>

    </div>

<?php } ?>


<!-- =====================================================
     SUMMARY
====================================================== -->

<div class="summary-card">

    <div>

        <div>
            Pending Timesheets
        </div>

        <div class="summary-number">

            <?php

            echo $pending_count;

            ?>

        </div>

    </div>

</div>


<!-- =====================================================
     PENDING TIMESHEETS
====================================================== -->

<?php

if (
    empty(
        $pending_timesheets
    )
) {

?>

<div class="empty-state">

    <h2>
        No Pending Timesheets
    </h2>

    <p>
        There are currently no submitted timesheets
        waiting for your approval.
    </p>

</div>

<?php

} else {

    foreach (
        $pending_timesheets
        as $sheet
    ) {

        /*
         * Load entries for this staff/week.
         */

        $stmt =
            $conn->prepare("
                SELECT
                    work_date,
                    task_description,
                    hours_worked
                FROM timesheets
                WHERE staff_id = ?
                AND week_start = ?
                AND week_end = ?
                ORDER BY work_date ASC
            ");

        $stmt->bind_param(
            "sss",
            $sheet['staff_id'],
            $sheet['week_start'],
            $sheet['week_end']
        );

        $stmt->execute();

        $entries_result =
            $stmt->get_result();

        $entries = [];

        while (
            $entry =
            $entries_result->fetch_assoc()
        ) {

            $entries[] =
                $entry;

        }

        $stmt->close();

?>

<!-- =====================================================
     APPROVAL CARD
====================================================== -->

<div class="approval-card">


<!-- HEADER -->

<div class="approval-header">


<div class="staff-info">

    <h2>

        <?php

        echo htmlspecialchars(
            $sheet['fullname']
        );

        ?>

    </h2>


    <p>

        <strong>
            Staff ID:
        </strong>

        <?php

        echo htmlspecialchars(
            $sheet['staff_id']
        );

        ?>

    </p>


    <?php if (
        !empty(
            $sheet['department']
        )
    ) { ?>

        <p>

            <strong>
                Department:
            </strong>

            <?php

            echo htmlspecialchars(
                $sheet['department']
            );

            ?>

        </p>

    <?php } ?>


    <p>

        <strong>
            Submitted:
        </strong>

        <?php

        echo htmlspecialchars(
            $sheet['submitted_at']
        );

        ?>

    </p>

</div>


<div class="week-info">

    <span>
        Timesheet Week
    </span>

    <strong>

        <?php

        echo date(
            "d M Y",
            strtotime(
                $sheet['week_start']
            )
        );

        ?>

        –

        <?php

        echo date(
            "d M Y",
            strtotime(
                $sheet['week_end']
            )
        );

        ?>

    </strong>

</div>


</div>


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

$total_hours = 0;

foreach (
    $entries
    as $entry
) {

    $hours =
        (float)(
            $entry['hours_worked']
            ?? 0
        );

    $total_hours +=
        $hours;

?>

<tr>

    <td>

        <strong>

            <?php

            echo date(
                "l",
                strtotime(
                    $entry['work_date']
                )
            );

            ?>

        </strong>

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
                $entry[
                    'task_description'
                ]
            )
        );

        ?>

    </td>


    <td>

        <?php

        echo number_format(
            $hours,
            2
        );

        ?>

        hrs

    </td>

</tr>

<?php

}

?>


<!-- TOTAL -->

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


<!-- =====================================================
     ACTION AREA
====================================================== -->

<div class="action-area">


<form
    method="POST"
    onsubmit="return confirmDecision(this);"
>


<input
    type="hidden"
    name="staff_id"
    value="<?php
        echo htmlspecialchars(
            $sheet['staff_id']
        );
    ?>"
>


<input
    type="hidden"
    name="week_start"
    value="<?php
        echo htmlspecialchars(
            $sheet['week_start']
        );
    ?>"
>


<input
    type="hidden"
    name="week_end"
    value="<?php
        echo htmlspecialchars(
            $sheet['week_end']
        );
    ?>"
>


<label
    class="comment-label"
>

    Comment / Supervisor Remark

</label>


<textarea
    name="comment"
    class="comment-box"
    placeholder="Enter a comment or remark. A comment is required when rejecting the timesheet."
></textarea>


<div class="action-buttons">


<button
    type="submit"
    name="action"
    value="approve"
    class="approve-btn"
>

    ✓ Approve Timesheet

</button>


<button
    type="submit"
    name="action"
    value="reject"
    class="reject-btn"
>

    ✕ Reject Timesheet

</button>


</div>


</form>


</div>


</div>

<?php

    }

}

?>


</div>

</div>

</div>


<script>

/* =========================================================
   APPROVAL CONFIRMATION
========================================================= */

function confirmDecision(form) {

    const clickedButton =
        document.activeElement;

    const action =
        clickedButton
            ? clickedButton.value
            : "";

    const comment =
        form.querySelector(
            'textarea[name="comment"]'
        ).value.trim();


    if (
        action === "reject" &&
        comment === ""
    ) {

        alert(
            "Please enter a comment explaining why you are rejecting this timesheet."
        );

        return false;

    }


    if (
        action === "approve"
    ) {

        return confirm(
            "Are you sure you want to approve this timesheet?"
        );

    }


    if (
        action === "reject"
    ) {

        return confirm(
            "Are you sure you want to reject this timesheet?"
        );

    }


    return true;

}

</script>


</body>

</html>