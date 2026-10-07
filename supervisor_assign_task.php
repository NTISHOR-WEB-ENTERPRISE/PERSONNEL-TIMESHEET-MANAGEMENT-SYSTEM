<?php

session_start();

include "config.php";
include "notification_function.php";


/* =========================================================
   CHECK SUPERVISOR LOGIN
========================================================= */

if (!isset($_SESSION['supervisor_id'])) {

    header("Location: supervisor_login.php");
    exit();

}


$supervisor_id = $_SESSION['supervisor_id'];

$fullname = $_SESSION['fullname'] ?? 'Supervisor';


/* ==================================
   SUPERVISOR PROFILE PHOTO
================================== */

$stmt = $conn->prepare("
    SELECT profile_photo
    FROM supervisors
    WHERE supervisor_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$result = $stmt->get_result();

$supervisor_data = $result->fetch_assoc();

$stmt->close();


$profile_photo = "uploads/supervisors/default.png";


if (
    $supervisor_data &&
    !empty($supervisor_data['profile_photo'])
) {

    $photo = basename(
        $supervisor_data['profile_photo']
    );

    $photo_path =
        "uploads/supervisors/" . $photo;

    if (file_exists($photo_path)) {

        $profile_photo = $photo_path;

    }

}

/* =========================================================
   GET SUPERVISOR INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT
        supervisor_id,
        fullname
    FROM supervisors
    WHERE supervisor_id = ?
");

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$supervisor_result = $stmt->get_result();

$supervisor = $supervisor_result->fetch_assoc();

$stmt->close();


if (!$supervisor) {

    die("Supervisor account not found.");

}


/* =========================================================
   GET STAFF ASSIGNED TO THIS SUPERVISOR
========================================================= */

$stmt = $conn->prepare("
    SELECT
        staff_id,
        fullname,
        department,
        position,
        status
    FROM staff
    WHERE supervisor_id = ?
    AND status = 'active'
    ORDER BY fullname ASC
");

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$staff_result = $stmt->get_result();


/* =========================================================
   FORM VARIABLES
========================================================= */

$error = "";

$success = "";


/* =========================================================
   HANDLE TASK SUBMISSION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $staff_id = trim($_POST['staff_id'] ?? '');

    $task_title = trim(
        $_POST['task_title'] ?? ''
    );

    $task_description = trim(
        $_POST['task_description'] ?? ''
    );

    $priority = trim(
        $_POST['priority'] ?? 'Medium'
    );

    $deadline = trim(
        $_POST['deadline'] ?? ''
    );


    /* =====================================================
       VALIDATE BASIC INFORMATION
    ===================================================== */

    if (
        empty($staff_id) ||
        empty($task_title) ||
        empty($task_description) ||
        empty($deadline)
    ) {

        $error =
            "Please fill in all required fields.";

    }


    /* =====================================================
       VALIDATE PRIORITY
    ===================================================== */

    elseif (
        !in_array(
            $priority,
            ['Low', 'Medium', 'High'],
            true
        )
    ) {

        $error =
            "Invalid task priority.";

    }


    /* =====================================================
       VERIFY STAFF BELONGS TO THIS SUPERVISOR
    ===================================================== */

    else {

        $stmt = $conn->prepare("
            SELECT
                staff_id,
                fullname
            FROM staff
            WHERE staff_id = ?
            AND supervisor_id = ?
            AND status = 'active'
        ");

        $stmt->bind_param(
            "ss",
            $staff_id,
            $supervisor_id
        );

        $stmt->execute();

        $selected_staff_result =
            $stmt->get_result();

        $selected_staff =
            $selected_staff_result->fetch_assoc();

        $stmt->close();


        if (!$selected_staff) {

            $error =
                "You are not authorized to assign a task to this staff member.";

        }


        /* =================================================
           INSERT TASK
        ================================================= */

        else {

            $stmt = $conn->prepare("
                INSERT INTO assigned_tasks
                (
                    staff_id,
                    supervisor_id,
                    task_title,
                    task_description,
                    priority,
                    status,
                    assigned_date,
                    deadline
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Assigned',
                    CURDATE(),
                    ?
                )
            ");


            $stmt->bind_param(
                "ssssss",
                $staff_id,
                $supervisor_id,
                $task_title,
                $task_description,
                $priority,
                $deadline
            );


            if ($stmt->execute()) {


                /* =========================================
                   NOTIFY STAFF
                ========================================= */

                if (function_exists('notifyStaff')) {

                    notifyStaff(
                        $conn,
                        $staff_id,
                        "New task assigned by Supervisor "
                        . $fullname
                        . ": "
                        . $task_title
                    );

                }


                $success =
                    "Task assigned successfully to "
                    . $selected_staff['fullname']
                    . ".";


                /*
                 * Clear form fields after success
                 */

                $_POST = [];

            } else {

                $error =
                    "Failed to assign task: "
                    . $stmt->error;

            }

            $stmt->close();

        }

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
Assign Task
</title>

<link
    rel="stylesheet"
    href="styles.css"
>


<style>

/* =========================================================
   TASK ASSIGNMENT PAGE
========================================================= */

.task-container {

    max-width:900px;

    margin:20px auto;

    background:#fff;

    padding:30px;

    border-radius:12px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.08);

}


.task-container h2 {

    margin-top:0;

    color:#0056b3;

}


.task-container > p {

    color:#666;

    margin-bottom:25px;

}


/* =========================================================
   ALERTS
========================================================= */

.alert {

    padding:14px 18px;

    border-radius:7px;

    margin-bottom:20px;

    font-weight:500;

}


.alert-success {

    background:#d1e7dd;

    color:#0f5132;

    border-left:4px solid #198754;

}


.alert-error {

    background:#f8d7da;

    color:#842029;

    border-left:4px solid #dc3545;

}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom:20px;

}


.form-group label {

    display:block;

    margin-bottom:7px;

    font-weight:bold;

    color:#333;

}


.form-group input,
.form-group select,
.form-group textarea {

    width:100%;

    padding:12px;

    border:1px solid #ddd;

    border-radius:6px;

    font-size:14px;

    box-sizing:border-box;

}


.form-group textarea {

    min-height:130px;

    resize:vertical;

}


.form-row {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:20px;

}


/* =========================================================
   STAFF INFORMATION
========================================================= */

.selected-staff {

    background:#f4f6f9;

    border-left:4px solid #0056b3;

    padding:15px;

    margin-top:8px;

    color:#555;

    font-size:14px;

}


/* =========================================================
   BUTTONS
========================================================= */

.button-group {

    display:flex;

    gap:12px;

    margin-top:25px;

}


.btn-submit {

    background:#0056b3;

    color:white;

    border:none;

    padding:12px 22px;

    border-radius:6px;

    cursor:pointer;

    font-weight:bold;

}


.btn-submit:hover {

    background:#004494;

}


.btn-cancel {

    background:#6c757d;

    color:white;

    padding:12px 22px;

    border-radius:6px;

    text-decoration:none;

    font-weight:bold;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:600px) {

    .task-container {

        margin:10px;

        padding:20px;

    }


    .form-row {

        grid-template-columns:1fr;

    }


    .button-group {

        flex-direction:column;

    }

}

</style>

</head>


<body>


<div class="container">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">


<div class="logo">

<h2>

<?php

echo htmlspecialchars(
    $app['organization_name']
);

?>

</h2>

<p>
Timesheet System
</p>

</div>

<div class="profile">

    <div class="avatar">

        <img
            src="<?php echo htmlspecialchars($profile_photo); ?>"
            class="profile-small"
            alt="Supervisor Profile"
            onerror="this.onerror=null; this.src='uploads/supervisors/default.png';"
        >

    </div>

    <h4>
        <?php echo htmlspecialchars($fullname); ?>
    </h4>

    <p>Supervisor</p>

</div>


<ul>


<li>

<a href="supervisor_dashboard.php">
Dashboard
</a>

</li>


<li>

<a href="approvals.php">
Approvals
</a>

</li>


<li class="active">

<a href="supervisor_assign_task.php">
Assign Task
</a>

</li>


<li>

<a href="task_report_approvals.php">
Task Reports
</a>

</li>


<li>

<a href="reports.php">
Reports
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
Sign Out
</a>

</li>


</ul>


</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


<div class="topbar">

<div>

<h1>
Assign Task
</h1>

<p>
Assign a task to a staff member under your supervision.
</p>

</div>


<div id="clock"></div>


</div>



<div class="task-container">


<h2>
Assign New Task
</h2>


<p>
Only staff members assigned to you are available for task assignment.
</p>


<?php if (!empty($success)): ?>

<div class="alert alert-success">

<?php

echo htmlspecialchars($success);

?>

</div>

<?php endif; ?>


<?php if (!empty($error)): ?>

<div class="alert alert-error">

<?php

echo htmlspecialchars($error);

?>

</div>

<?php endif; ?>


<form method="POST">


<!-- =================================================
     STAFF
================================================= -->

<div class="form-group">

<label>
Staff Member *
</label>


<select
    name="staff_id"
    required
>

<option value="">
-- Select Staff Member --
</option>


<?php

if ($staff_result->num_rows > 0) {

    while (
        $staff_member =
        $staff_result->fetch_assoc()
    ) {

?>


<option

value="<?php
    echo htmlspecialchars(
        $staff_member['staff_id']
    );
?>"

<?php

if (
    isset($_POST['staff_id']) &&
    $_POST['staff_id'] ===
    $staff_member['staff_id']
) {

    echo "selected";

}

?>

>

<?php

echo htmlspecialchars(
    $staff_member['fullname']
);

?>

<?php

if (
    !empty(
        $staff_member['department']
    )
) {

    echo " - ";

    echo htmlspecialchars(
        $staff_member['department']
    );

}

?>

</option>


<?php

    }

} else {

?>


<option value="" disabled>

No staff members are assigned to you.

</option>


<?php

}

?>

</select>




</div>



<!-- =================================================
     TASK TITLE
================================================= -->

<div class="form-group">

<label>
Task Title *
</label>


<input

type="text"

name="task_title"

placeholder="Enter task title"

value="<?php
    echo htmlspecialchars(
        $_POST['task_title'] ?? ''
    );
?>"

required

>

</div>



<!-- =================================================
     DESCRIPTION
================================================= -->

<div class="form-group">

<label>
Task Description *
</label>


<textarea

name="task_description"

placeholder="Describe what the staff member is expected to do..."

required

><?php

echo htmlspecialchars(
    $_POST['task_description'] ?? ''
);

?></textarea>


</div>



<!-- =================================================
     PRIORITY / DEADLINE
================================================= -->

<div class="form-row">


<div class="form-group">

<label>
Priority *
</label>


<select
    name="priority"
    required
>


<option value="Low">

Low

</option>


<option
    value="Medium"
    <?php

    if (
        ($_POST['priority'] ?? 'Medium')
        === 'Medium'
    ) {

        echo "selected";

    }

    ?>
>

Medium

</option>


<option
    value="High"
    <?php

    if (
        ($_POST['priority'] ?? '')
        === 'High'
    ) {

        echo "selected";

    }

    ?>
>

High

</option>


</select>

</div>



<div class="form-group">

<label>
Deadline *
</label>


<input

type="date"

name="deadline"

min="<?php
    echo date('Y-m-d');
?>"

value="<?php
    echo htmlspecialchars(
        $_POST['deadline'] ?? ''
    );
?>"

required

>

</div>


</div>



<!-- =================================================
     BUTTONS
================================================= -->

<div class="button-group">


<button
    type="submit"
    class="btn-submit"
>

+ Assign Task

</button>


<a
    href="supervisor_dashboard.php"
    class="btn-cancel"
>

Cancel

</a>


</div>


</form>


</div>


</div>


</div>


<script>

function updateClock(){

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();

}

updateClock();

setInterval(
    updateClock,
    1000
);

</script>


</body>

</html>