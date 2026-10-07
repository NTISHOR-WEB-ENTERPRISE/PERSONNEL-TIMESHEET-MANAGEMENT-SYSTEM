<?php
session_start();
include "config.php";

if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

/* ==========================================
   GET TASK ID
========================================== */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid task.");
}

$task_id = (int) $_GET['id'];


/* ==========================================
   FETCH TASK
   IMPORTANT:
   Only allow the logged-in staff member
   to view their own task.
========================================== */

$stmt = $conn->prepare("
    SELECT
        tr.*,
        s.fullname AS staff_name,
        sup.fullname AS supervisor_name
    FROM task_reports tr

    LEFT JOIN staff s
        ON tr.staff_id = s.staff_id

    LEFT JOIN supervisors sup
        ON tr.supervisor_id = sup.supervisor_id

    WHERE tr.id = ?
    AND tr.staff_id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("is", $task_id, $staff_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Task report not found.");
}

$task = $result->fetch_assoc();

$stmt->close();


/* ==========================================
   STATUS CLASS
========================================== */

$status = $task['status'] ?? 'Pending';

$status_class = strtolower($status);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Task Details</title>

    <link rel="stylesheet" href="styles.css">

    <style>

        .details-container {
            max-width: 900px;
            margin: 30px auto;
        }

        .details-box {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }

        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .details-header h2 {
            margin: 0;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 20px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .detail-label {
            font-weight: bold;
            color: #555;
        }

        .detail-value {
            color: #222;
        }

        .task-description {
            line-height: 1.7;
            white-space: pre-wrap;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .pending {
            background: #fff3cd;
            color: #856404;
        }

        .approved {
            background: #d4edda;
            color: #155724;
        }

        .rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .back-btn {
            display: inline-block;
            margin-top: 25px;
            padding: 10px 18px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-btn:hover {
            opacity: 0.9;
        }

        @media(max-width: 600px) {

            .detail-row {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .details-box {
                padding: 20px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="main">

        <div class="topbar">

            <div>
                <h1>Task Details</h1>
            </div>

        </div>


        <div class="details-container">

            <div class="details-box">

                <div class="details-header">

                    <h2>Submitted Task</h2>

                    <span class="status <?php echo $status_class; ?>">
                        <?php echo htmlspecialchars($status); ?>
                    </span>

                </div>


                <!-- Staff -->

                <div class="detail-row">

                    <div class="detail-label">
                        Staff
                    </div>

                    <div class="detail-value">
                        <?php
                        echo htmlspecialchars(
                            $task['staff_name']
                        );
                        ?>
                    </div>

                </div>


                <!-- Supervisor -->

                <div class="detail-row">

                    <div class="detail-label">
                        Supervisor
                    </div>

                    <div class="detail-value">

                        <?php
                        echo !empty($task['supervisor_name'])
                            ? htmlspecialchars($task['supervisor_name'])
                            : "Not assigned";
                        ?>

                    </div>

                </div>


                <!-- Report Date -->

                <div class="detail-row">

                    <div class="detail-label">
                        Report Date
                    </div>

                    <div class="detail-value">

                        <?php
                        echo date(
                            "d M Y",
                            strtotime($task['report_date'])
                        );
                        ?>

                    </div>

                </div>


                <!-- Start Time -->

                <div class="detail-row">

                    <div class="detail-label">
                        Start Time
                    </div>

                    <div class="detail-value">

                        <?php
                        echo date(
                            "h:i A",
                            strtotime($task['start_time'])
                        );
                        ?>

                    </div>

                </div>


                <!-- End Time -->

                <div class="detail-row">

                    <div class="detail-label">
                        End Time
                    </div>

                    <div class="detail-value">

                        <?php
                        echo date(
                            "h:i A",
                            strtotime($task['end_time'])
                        );
                        ?>

                    </div>

                </div>


                <!-- Hours -->

                <div class="detail-row">

                    <div class="detail-label">
                        Hours Worked
                    </div>

                    <div class="detail-value">

                        <?php
                        echo number_format(
                            (float)$task['hours_worked'],
                            2
                        );
                        ?> hours

                    </div>

                </div>


                <!-- Task -->

                <div class="detail-row">

                    <div class="detail-label">
                        Task Completed
                    </div>

                    <div class="detail-value task-description">

                        <?php
                        echo htmlspecialchars(
                            $task['tasks_completed']
                        );
                        ?>

                    </div>

                </div>


                <!-- Challenges -->

                <div class="detail-row">

                    <div class="detail-label">
                        Challenges
                    </div>

                    <div class="detail-value task-description">

                        <?php

                        echo !empty($task['challenges'])
                            ? nl2br(
                                htmlspecialchars(
                                    $task['challenges']
                                )
                            )
                            : "None";

                        ?>

                    </div>

                </div>


                <!-- Remarks -->

                <div class="detail-row">

                    <div class="detail-label">
                        Remarks
                    </div>

                    <div class="detail-value task-description">

                        <?php

                        echo !empty($task['remarks'])
                            ? nl2br(
                                htmlspecialchars(
                                    $task['remarks']
                                )
                            )
                            : "None";

                        ?>

                    </div>

                </div>


                <!-- Supervisor Comment -->

                <div class="detail-row">

                    <div class="detail-label">
                        Supervisor Comment
                    </div>

                    <div class="detail-value task-description">

                        <?php

                        echo !empty($task['supervisor_comment'])
                            ? nl2br(
                                htmlspecialchars(
                                    $task['supervisor_comment']
                                )
                            )
                            : "No comment yet";

                        ?>

                    </div>

                </div>


                <!-- Submitted -->

                <div class="detail-row">

                    <div class="detail-label">
                        Submitted On
                    </div>

                    <div class="detail-value">

                        <?php
                        echo date(
                            "d M Y, h:i A",
                            strtotime($task['created_at'])
                        );
                        ?>

                    </div>

                </div>


                <!-- Reviewed -->

                <?php if (!empty($task['reviewed_at'])): ?>

                <div class="detail-row">

                    <div class="detail-label">
                        Reviewed On
                    </div>

                    <div class="detail-value">

                        <?php
                        echo date(
                            "d M Y, h:i A",
                            strtotime($task['reviewed_at'])
                        );
                        ?>

                    </div>

                </div>

                <?php endif; ?>


                <a
                    href="task_history.php"
                    class="back-btn"
                >
                    ← Back to Task History
                </a>

            </div>

        </div>

    </div>

</div>

</body>
</html>