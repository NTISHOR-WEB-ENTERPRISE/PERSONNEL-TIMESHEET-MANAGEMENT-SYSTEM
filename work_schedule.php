<?php

session_start();

include "config.php";


/* =====================================
   CHECK LOGIN
===================================== */

if (!isset($_SESSION['staff_id'])) {

    header("Location: staff_login.php");
    exit();

}

$staff_id = $_SESSION['staff_id'];


/* =====================================
   GET STAFF INFORMATION
===================================== */

$sql = "
SELECT 
    staff.fullname,
    staff.department,
    staff.profile_picture,
    supervisors.fullname AS supervisor_name
FROM staff
LEFT JOIN supervisors
ON staff.supervisor_id = supervisors.supervisor_id
WHERE staff.staff_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die("Prepare failed: " . $conn->error);

}

$stmt->bind_param("s", $staff_id);

$stmt->execute();

$staff_data = $stmt->get_result()->fetch_assoc();

$stmt->close();


/* =====================================
   STAFF NAME
===================================== */

$fullname = $staff_data['fullname'] ?? 'Staff';


/* =====================================
   PROFILE PHOTO
===================================== */

$profile_photo = "uploads/staff/default.png";


if (!empty($staff_data['profile_picture'])) {

    $photo_name = basename(
        $staff_data['profile_picture']
    );


    /* ---------------------------------
       NEW STAFF PHOTO LOCATION
    --------------------------------- */

    if (
        file_exists(
            "uploads/staff/" . $photo_name
        )
    ) {

        $profile_photo =
            "uploads/staff/" . $photo_name;

    }


    /* ---------------------------------
       OLD PHOTO LOCATION
    --------------------------------- */

    elseif (
        file_exists(
            "uploads/" . $photo_name
        )
    ) {

        $profile_photo =
            "uploads/" . $photo_name;

    }

}


/* =====================================
   GET WORK SCHEDULE
===================================== */

$schedule_query = "
SELECT *
FROM work_schedule
WHERE staff_id = ?
ORDER BY FIELD(
    day_name,
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday'
)
";

$stmt = $conn->prepare($schedule_query);

$stmt->bind_param(
    "s",
    $staff_id
);

$stmt->execute();

$schedules = $stmt->get_result();

$stmt->close();


/* =====================================
   COUNT ASSIGNED TASKS
===================================== */

$task_query = "
SELECT COUNT(*) AS total
FROM assigned_tasks
WHERE staff_id = ?
AND status != 'Completed'
";

$stmt = $conn->prepare($task_query);

$stmt->bind_param(
    "s",
    $staff_id
);

$stmt->execute();

$task_count =
    $stmt->get_result()->fetch_assoc();

$stmt->close();

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
My Work Schedule
</title>

<link
rel="stylesheet"
href="styles.css"
>


<style>

/* =====================================
   SIDEBAR PROFILE PHOTO
===================================== */

.sidebar-profile-photo {

    width:50px;

    height:50px;

    border-radius:50%;

    object-fit:cover;

    display:block;

    border:2px solid #eee;

}


/* =====================================
   AVATAR CONTAINER
===================================== */

.profile .avatar {

    width:50px;

    height:50px;

    border-radius:50%;

    overflow:hidden;

    display:flex;

    align-items:center;

    justify-content:center;

}


/* =====================================
   SCHEDULE MAIN
===================================== */

.schedule-main {

    padding-bottom:30px;

}


/* =====================================
   TOP BAR
===================================== */

.schedule-topbar {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:25px;

}


.schedule-topbar h1 {

    margin:0;

}


.schedule-clock {

    font-size:18px;

    font-weight:bold;

}


/* =====================================
   STAFF INFORMATION
===================================== */

.staff-info-card {

    background:#fff;

    padding:25px;

    border-radius:12px;

    margin-bottom:25px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.07);

}


.staff-info-card h2 {

    margin-top:0;

    margin-bottom:20px;

}


.staff-info-grid {

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:20px;

}


.info-item {

    background:#f8f9fa;

    padding:18px;

    border-radius:8px;

}


.info-item span {

    display:block;

    color:#777;

    font-size:13px;

    margin-bottom:7px;

}


.info-item strong {

    color:#333;

}


/* =====================================
   SCHEDULE CARD
===================================== */

.schedule-card {

    background:#fff;

    padding:25px;

    border-radius:12px;

    margin-bottom:25px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.07);

}


.schedule-card h2 {

    margin-top:0;

    margin-bottom:20px;

}


/* =====================================
   SCHEDULE TABLE
===================================== */

.schedule-table {

    width:100%;

    border-collapse:collapse;

}


.schedule-table th,
.schedule-table td {

    padding:15px;

    text-align:left;

    border-bottom:1px solid #eee;

}


.schedule-table th {

    background:#f8f9fa;

    color:#555;

}


/* =====================================
   STATUS
===================================== */

.status {

    display:inline-block;

    padding:6px 12px;

    border-radius:20px;

    font-size:12px;

    font-weight:bold;

}


.status.working {

    background:#d1e7dd;

    color:#0f5132;

}


.status.leave {

    background:#fff3cd;

    color:#856404;

}


.status.off {

    background:#f8d7da;

    color:#842029;

}


/* =====================================
   NO SCHEDULE
===================================== */

.no-schedule {

    text-align:center;

    padding:40px;

    color:#777;

}


/* =====================================
   TASK CARD
===================================== */

.task-card {

    background:#fff;

    padding:25px;

    border-radius:12px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.07);

}


.task-card h2 {

    margin-top:0;

}


.task-content {

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:20px;

}


.task-number {

    font-size:35px;

    font-weight:bold;

    margin-bottom:5px;

}


.task-description {

    color:#777;

}


.view-task-btn {

    background:#0d6efd;

    color:#fff;

    text-decoration:none;

    padding:12px 18px;

    border-radius:6px;

    font-weight:bold;

}


.view-task-btn:hover {

    opacity:.9;

}


/* =====================================
   RESPONSIVE
===================================== */

@media(max-width:700px) {

    .staff-info-grid {

        grid-template-columns:1fr;

    }


    .schedule-topbar {

        flex-direction:column;

        align-items:flex-start;

        gap:10px;

    }


    .task-content {

        flex-direction:column;

        align-items:flex-start;

    }

}

</style>

</head>


<body>


<div class="container">


<!-- =====================================
     SIDEBAR
===================================== -->

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

                alt="Profile Photo"

                class="sidebar-profile-photo"

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

                Clock In/Out

            </a>

        </li>



        <li>

            <a href="staff_submit_task_report.php">

                My Task

            </a>

        </li>



        <li>
                <a href="task_history.php"> Task Submission History </a>
            </li>


<li>

            <a href="staff_timesheet_report.php">
                Timesheet Report
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



<!-- =====================================
     MAIN CONTENT
===================================== -->

<div class="main schedule-main">


    <!-- TOP BAR -->

    <div class="schedule-topbar">


        <h1>

            My Work Schedule

        </h1>


        <div
            class="schedule-clock"
            id="clock"
        ></div>


    </div>



    <!-- =================================
         STAFF INFORMATION
    ================================== -->

    <div class="staff-info-card">


        <h2>
            Staff Information
        </h2>


        <div class="staff-info-grid">


            <div class="info-item">

                <span>
                    Full Name
                </span>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $staff_data['fullname']
                        ?? 'N/A'
                    );

                    ?>

                </strong>

            </div>



            <div class="info-item">

                <span>
                    Department
                </span>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $staff_data['department']
                        ?? 'N/A'
                    );

                    ?>

                </strong>

            </div>



            <div class="info-item">

                <span>
                    Supervisor
                </span>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $staff_data['supervisor_name']
                        ?? 'Not Assigned'
                    );

                    ?>

                </strong>

            </div>



            <div class="info-item">

                <span>
                    Official Working Hours
                </span>

                <strong>
                    08:00 AM - 05:00 PM
                </strong>

            </div>


        </div>


    </div>



    <!-- =================================
         WEEKLY SCHEDULE
    ================================== -->

    <div class="schedule-card">


        <h2>
            Weekly Schedule
        </h2>


        <?php if(mysqli_num_rows($schedules) > 0): ?>


        <div style="overflow-x:auto;">


            <table class="schedule-table">


                <thead>


                    <tr>

                        <th>
                            Day
                        </th>

                        <th>
                            Shift
                        </th>

                        <th>
                            Start Time
                        </th>

                        <th>
                            End Time
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>


                </thead>


                <tbody>


                <?php while(
                    $row =
                    $schedules->fetch_assoc()
                ): ?>


                    <tr>


                        <td>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $row['day_name']
                                );

                                ?>

                            </strong>

                        </td>



                        <td>

                            <?php

                            echo htmlspecialchars(
                                $row['shift']
                            );

                            ?>

                        </td>



                        <td>

                            <?php

                            echo date(
                                "h:i A",
                                strtotime(
                                    $row['start_time']
                                )
                            );

                            ?>

                        </td>



                        <td>

                            <?php

                            echo date(
                                "h:i A",
                                strtotime(
                                    $row['end_time']
                                )
                            );

                            ?>

                        </td>



                        <td>


                        <?php

                        $status =
                            $row['status'];


                        if(
                            $status ==
                            "Working"
                        ) {


                            echo "

                            <span
                                class='status working'
                            >

                                Working

                            </span>

                            ";


                        }

                        elseif(
                            $status ==
                            "Leave"
                        ) {


                            echo "

                            <span
                                class='status leave'
                            >

                                Leave

                            </span>

                            ";


                        }

                        else {


                            echo "

                            <span
                                class='status off'
                            >

                                Off Day

                            </span>

                            ";

                        }

                        ?>


                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>


            </table>


        </div>


        <?php else: ?>


            <div class="no-schedule">

                No work schedule has been assigned
                to you yet.

            </div>


        <?php endif; ?>


    </div>



    <!-- =================================
         ASSIGNED TASKS
    ================================== -->

    <div class="task-card">


        <h2>
            Assigned Tasks
        </h2>


        <div class="task-content">


            <div>


                <div class="task-number">

                    <?php

                    echo $task_count['total'];

                    ?>

                </div>


                <div class="task-description">

                    Pending tasks assigned to you

                </div>


            </div>



            <a
                href="assigned_tasks.php"
                class="view-task-btn"
            >

                View Tasks

            </a>


        </div>


    </div>


</div>


</div>



<!-- =====================================
     CLOCK
===================================== -->

<script>

function updateClock() {

    const now = new Date();

    document.getElementById("clock").innerHTML =
        now.toLocaleTimeString();

}

setInterval(updateClock, 1000);

updateClock();

</script>


</body>

</html>