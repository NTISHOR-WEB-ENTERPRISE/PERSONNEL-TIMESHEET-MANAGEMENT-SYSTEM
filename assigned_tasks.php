<?php
session_start();

include "config.php";
include "notification_function.php";

/* =========================================================
   CHECK STAFF LOGIN
========================================================= */

if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];


/* =========================================================
   GET STAFF INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT
        fullname,
        department,
        supervisor_id,
        profile_picture
    FROM staff
    WHERE staff_id = ?
");

$stmt->bind_param("s", $staff_id);
$stmt->execute();

$staff_result = $stmt->get_result();
$staff = $staff_result->fetch_assoc();

$stmt->close();


if (!$staff) {
    die("Staff member not found.");
}


$fullname = $staff['fullname'];
$supervisor_id = $staff['supervisor_id'];

/* =========================================================
   STAFF PROFILE PICTURE
========================================================= */

if (
    !empty($staff['profile_picture']) &&
    file_exists(
        "uploads/staff/" .
        $staff['profile_picture']
    )
) {

    $profile_photo =
        "uploads/staff/" .
        $staff['profile_picture'];

} else {

    $profile_photo =
        "uploads/staff/default.png";
}

/* =========================================================
   UPDATE TASK STATUS
========================================================= */

if (isset($_POST['update_status'])) {

    $task_id = intval($_POST['task_id']);
    $new_status = $_POST['new_status'];

    /* Only allow these statuses */

    $allowed_statuses = [
        'In Progress',
        'Completed'
    ];

    if (in_array($new_status, $allowed_statuses, true)) {

        /* Make sure the task belongs to this staff member */

        $stmt = $conn->prepare("
            SELECT task_title, status
            FROM assigned_tasks
            WHERE id = ?
            AND staff_id = ?
        ");

        $stmt->bind_param(
            "is",
            $task_id,
            $staff_id
        );

        $stmt->execute();

        $task_result = $stmt->get_result();
        $task_data = $task_result->fetch_assoc();

        $stmt->close();


        if ($task_data) {

            /* Don't change an already completed task */

            if ($task_data['status'] !== 'Completed') {

                $stmt = $conn->prepare("
                    UPDATE assigned_tasks
                    SET status = ?
                    WHERE id = ?
                    AND staff_id = ?
                ");

                $stmt->bind_param(
                    "sis",
                    $new_status,
                    $task_id,
                    $staff_id
                );

                $stmt->execute();

                $updated = $stmt->affected_rows;

                $stmt->close();


                /* =================================================
                   NOTIFY SUPERVISOR
                ================================================= */

                if ($updated > 0 && !empty($supervisor_id)) {

                    if ($new_status === 'In Progress') {

                        $notification_message =
                            "Staff $fullname has started the task: "
                            . $task_data['task_title'];

                    } else {

                        $notification_message =
                            "Staff $fullname has completed the task: "
                            . $task_data['task_title'];
                    }


                    notifySupervisor(
                        $conn,
                        $supervisor_id,
                        $notification_message
                    );
                }
            }
        }
    }


    /* Refresh page */

    header("Location: assigned_tasks.php");
    exit();
}


/* =========================================================
   GET ASSIGNED TASKS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        task_title,
        task_description,
        priority,
        status,
        assigned_date,
        deadline,
        attachment,
        supervisor_comment,
        created_at
    FROM assigned_tasks
    WHERE staff_id = ?
    ORDER BY
        CASE
            WHEN status = 'Assigned' THEN 1
            WHEN status = 'In Progress' THEN 2
            WHEN status = 'Completed' THEN 3
            ELSE 4
        END,
        deadline ASC
");

$stmt->bind_param("s", $staff_id);
$stmt->execute();

$tasks = $stmt->get_result();


/* =========================================================
   COUNT TASKS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Assigned') AS assigned,
        SUM(status = 'In Progress') AS in_progress,
        SUM(status = 'Completed') AS completed
    FROM assigned_tasks
    WHERE staff_id = ?
");

$stmt->bind_param("s", $staff_id);
$stmt->execute();

$counts = $stmt->get_result()->fetch_assoc();

$stmt->close();


$total_tasks = $counts['total'] ?? 0;
$assigned_tasks = $counts['assigned'] ?? 0;
$in_progress = $counts['in_progress'] ?? 0;
$completed = $counts['completed'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Assigned Tasks</title>

<link rel="stylesheet" href="styles.css">


<style>

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #333;
}

.container {
    display: flex;
    min-height: 100vh;
}

.main {
    flex: 1;
    padding: 30px;
}

.page-header {
    margin-bottom: 25px;
}

.page-header h2 {
    color: #0056b3;
    margin-bottom: 5px;
}

.page-header p {
    color: #666;
}


/* Summary Cards */

.summary-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.summary-card h4 {
    margin: 0 0 10px;
    color: #666;
    font-size: 14px;
}

.summary-card .number {
    font-size: 28px;
    font-weight: bold;
    color: #0056b3;
}


/* Tasks Container */

.tasks-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.tasks-header {
    background: #0056b3;
    color: white;
    padding: 18px 22px;
}

.tasks-header h3 {
    margin: 0;
}


/* Task Card */

.task-card {
    padding: 22px;
    border-bottom: 1px solid #eee;
}

.task-card:last-child {
    border-bottom: none;
}

.task-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.task-title {
    color: #0056b3;
    font-size: 19px;
    font-weight: bold;
    margin: 0;
}

.task-description {
    margin: 15px 0;
    line-height: 1.6;
    color: #555;
}


/* Badges */

.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}


/* Priority */

.priority-low {
    background: #dff5e3;
    color: #167c2d;
}

.priority-medium {
    background: #fff3cd;
    color: #856404;
}

.priority-high {
    background: #f8d7da;
    color: #842029;
}


/* Status */

.status-assigned {
    background: #e7f0ff;
    color: #0056b3;
}

.status-progress {
    background: #fff3cd;
    color: #856404;
}

.status-completed {
    background: #d1e7dd;
    color: #0f5132;
}


.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 25px;
    margin-top: 15px;
    font-size: 14px;
}

.task-meta strong {
    color: #333;
}

.deadline-warning {
    color: #dc3545;
    font-weight: bold;
}


.supervisor-comment {
    background: #f4f6f9;
    padding: 12px;
    border-left: 4px solid #0056b3;
    margin-top: 15px;
}


/* Empty State */

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state h3 {
    color: #555;
}

.empty-state p {
    color: #888;
}


/* Mobile */

@media(max-width: 900px) {

    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media(max-width: 600px) {

    .main {
        padding: 15px;
    }

    .summary-cards {
        grid-template-columns: 1fr;
    }

    .task-top {
        flex-direction: column;
        align-items: flex-start;
    }

}

/* =========================================================
   TASK ACTIONS
========================================================= */

.task-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
}


/* Start Task */

.start-task-btn {

    border: none;

    background: #0056b3;

    color: white;

    padding: 10px 18px;

    border-radius: 6px;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;

}

.start-task-btn:hover {

    background: #003f82;

}


/* Complete Task */

.complete-task-btn {

    border: none;

    background: #198754;

    color: white;

    padding: 10px 18px;

    border-radius: 6px;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;

}

.complete-task-btn:hover {

    background: #146c43;

}


/* Completed */

.completed-message {

    display: inline-block;

    background: #d1e7dd;

    color: #0f5132;

    padding: 10px 16px;

    border-radius: 6px;

    font-size: 14px;

    font-weight: bold;

}

</style>

</head>


<body>


<div class="container">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <div class="sidebar">

        <!-- LOGO -->

        <div class="logo">

            <h2>
                <?php
                echo htmlspecialchars(
                    $app['organization_name']
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
                    onerror="
                        this.src='uploads/staff/default.png';
                    "
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


        <!-- MENU -->

        <ul>

            <li>
                <a href="staff_dashboard.php">
                    Dashboard
                </a>
            </li>


            <li>
                <a href="attendance.php">
                    Clock In/Out
                </a>
            </li>


            <li>
                <a href="staff_submit_task_report.php">
                    My Task
                </a>
            </li>


            <li>
                <a href="task_history.php">
                    Task Submission History
                </a>
            </li>


            <li class="active">
                <a href="assigned_tasks.php">
                    My Assigned Tasks
                </a>
            </li>


            <li>
                <a href="work_schedule.php">
                    My Schedule
                </a>
            </li>


            <li>
                <a href="staff_profile.php">
                    Profile
                </a>
            </li>


            <li>
                <a href="staff_notifications.php">
                    Notifications
                </a>
            </li>


            <li>
                <a href="staff_logout.php">
                    Logout
                </a>
            </li>

        </ul>

    </div>

    <div class="main">


        <!-- PAGE HEADER -->

        <div class="page-header">

            <h2>My Assigned Tasks</h2>

            <p>
                View tasks assigned to you by the administrator.
            </p>

        </div>



        <!-- SUMMARY -->

        <div class="summary-cards">


            <div class="summary-card">

                <h4>Total Tasks</h4>

                <div class="number">
                    <?php echo $total_tasks; ?>
                </div>

            </div>



            <div class="summary-card">

                <h4>Assigned</h4>

                <div class="number">
                    <?php echo $assigned_tasks; ?>
                </div>

            </div>



            <div class="summary-card">

                <h4>In Progress</h4>

                <div class="number">
                    <?php echo $in_progress; ?>
                </div>

            </div>



            <div class="summary-card">

                <h4>Completed</h4>

                <div class="number">
                    <?php echo $completed; ?>
                </div>

            </div>


        </div>



        <!-- TASKS -->

        <div class="tasks-container">


            <div class="tasks-header">

                <h3>
                    Tasks Assigned to <?php echo htmlspecialchars($staff['fullname']); ?>
                </h3>

            </div>



            <?php if ($tasks->num_rows > 0): ?>


                <?php while ($task = $tasks->fetch_assoc()): ?>


                    <div class="task-card">


                        <div class="task-top">


                            <h3 class="task-title">

                                <?php
                                echo htmlspecialchars($task['task_title']);
                                ?>

                            </h3>



                            <?php

                            if ($task['priority'] === 'High') {

                                echo '<span class="badge priority-high">High Priority</span>';

                            } elseif ($task['priority'] === 'Medium') {

                                echo '<span class="badge priority-medium">Medium Priority</span>';

                            } else {

                                echo '<span class="badge priority-low">Low Priority</span>';

                            }

                            ?>

                        </div>



                        <p class="task-description">

                            <?php
                            echo nl2br(
                                htmlspecialchars($task['task_description'])
                            );
                            ?>

                        </p>



                        <div class="task-meta">


                            <div>

                                <strong>Assigned Date:</strong>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime($task['assigned_date'])
                                );
                                ?>

                            </div>



                            <div>

                                <strong>Deadline:</strong>

                                <?php

                                $deadline = strtotime($task['deadline']);

                                if ($deadline < strtotime(date('Y-m-d'))
                                    && $task['status'] !== 'Completed') {

                                    echo '<span class="deadline-warning">';
                                    echo date("d M Y", $deadline);
                                    echo ' (Overdue)';
                                    echo '</span>';

                                } else {

                                    echo date("d M Y", $deadline);

                                }

                                ?>

                            </div>



                           <div>

    <strong>Status:</strong>

    <?php

    if ($task['status'] === 'Assigned') {

        echo '<span class="badge status-assigned">
                Assigned
              </span>';

    } elseif ($task['status'] === 'In Progress') {

        echo '<span class="badge status-progress">
                In Progress
              </span>';

    } else {

        echo '<span class="badge status-completed">
                Completed
              </span>';

    }

    ?>

</div>

<!-- TASK ACTIONS -->

<div class="task-actions">

    <?php if ($task['status'] === 'Assigned'): ?>

        <form method="POST" style="display:inline;">

            <input
                type="hidden"
                name="task_id"
                value="<?php echo $task['id']; ?>"
            >

            <input
                type="hidden"
                name="new_status"
                value="In Progress"
            >

            <button
                type="submit"
                name="update_status"
                class="start-task-btn"
            >
                ▶ Start Task
            </button>

        </form>


    <?php elseif ($task['status'] === 'In Progress'): ?>

        <form method="POST" style="display:inline;">

            <input
                type="hidden"
                name="task_id"
                value="<?php echo $task['id']; ?>"
            >

            <input
                type="hidden"
                name="new_status"
                value="Completed"
            >

            <button
                type="submit"
                name="update_status"
                class="complete-task-btn"
                onclick="return confirm('Are you sure you have completed this task?');"
            >
                ✓ Mark as Completed
            </button>

        </form>


    <?php elseif ($task['status'] === 'Completed'): ?>

        <span class="completed-message">
            ✓ Task Completed
        </span>

    <?php endif; ?>

</div>


                        </div>



                        <?php if (!empty($task['supervisor_comment'])): ?>


                            <div class="supervisor-comment">

                                <strong>Supervisor Comment:</strong>

                                <br>

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $task['supervisor_comment']
                                    )
                                );
                                ?>

                            </div>


                        <?php endif; ?>


                    </div>


                <?php endwhile; ?>


            <?php else: ?>


                <div class="empty-state">

                    <h3>No Tasks Assigned</h3>

                    <p>
                        You currently have no tasks assigned to you.
                    </p>

                </div>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>