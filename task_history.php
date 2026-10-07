<?php
session_start();
include "config.php";

if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$fullname = $_SESSION['fullname'] ?? '';

/* =========================================================
   FETCH STAFF INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT
        profile_picture,
        fullname
    FROM staff
    WHERE staff_id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("s", $staff_id);
$stmt->execute();

$staff_result = $stmt->get_result();
$staff_data = $staff_result->fetch_assoc();

$stmt->close();

$fullname = $staff_data['fullname'] ?? $fullname;


/* =========================================================
   PROFILE PHOTO
========================================================= */

$profile_photo = "uploads/staff/default.png";

if (!empty($staff_data['profile_picture'])) {

    $photo_name = basename($staff_data['profile_picture']);

    if (file_exists("uploads/staff/" . $photo_name)) {

        $profile_photo = "uploads/staff/" . $photo_name;

    } elseif (file_exists("uploads/" . $photo_name)) {

        $profile_photo = "uploads/" . $photo_name;

    }
}


/* =========================================================
   TASK REPORT STATISTICS
========================================================= */

/* Total Tasks */
$result = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM task_reports
    WHERE staff_id = '$staff_id'
");

$total = mysqli_fetch_assoc($result)['total'];


/* Pending Tasks */
$result = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM task_reports
    WHERE staff_id = '$staff_id'
    AND status = 'Pending'
");

$pending = mysqli_fetch_assoc($result)['total'];


/* Approved Tasks */
$result = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM task_reports
    WHERE staff_id = '$staff_id'
    AND status = 'Approved'
");

$approved = mysqli_fetch_assoc($result)['total'];


/* Rejected Tasks */
$result = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM task_reports
    WHERE staff_id = '$staff_id'
    AND status = 'Rejected'
");

$rejected = mysqli_fetch_assoc($result)['total'];


/* =========================================================
   STATUS FILTER
========================================================= */

$status = "";

if (isset($_GET['status'])) {
    $status = $_GET['status'];
}


/* =========================================================
   TASK HISTORY QUERY
========================================================= */

$sql = "
    SELECT *
    FROM task_reports
    WHERE staff_id = '$staff_id'
";

if ($status != "") {

    $status_safe = mysqli_real_escape_string($conn, $status);

    $sql .= "
        AND status = '$status_safe'
    ";
}

$sql .= "
    ORDER BY created_at DESC
";

$task_reports = mysqli_query($conn, $sql);

if (!$task_reports) {
    die("Task report query failed: " . mysqli_error($conn));
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Task History</title>

    <link rel="stylesheet" href="styles.css">

    <style>

        .sidebar-profile-photo {
            width:55px;
            height:55px;
            border-radius:50%;
            object-fit:cover;
            display:block;
            border:3px solid #ffffff;
            box-shadow:0 2px 8px rgba(0,0,0,0.15);
        }

        .profile .avatar {
            width:60px;
            height:60px;
            border-radius:50%;
            overflow:hidden;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#f1f1f1;
        }

        .profile .avatar img {
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
        }

        .task-description {
            max-width:350px;
            white-space:normal;
            line-height:1.5;
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
                Personnel Timesheet System
            </p>

        </div>


        <!-- PROFILE -->

        <div class="profile">

            <div class="avatar">

                <img
                    src="<?php echo htmlspecialchars($profile_photo); ?>"
                    class="sidebar-profile-photo"
                    alt="Profile Photo"
                    onerror="
                        this.onerror=null;
                        this.src='uploads/staff/default.png';
                    "
                >

            </div>


            <h3>
                <?php echo htmlspecialchars($fullname); ?>
            </h3>

            <p>Staff</p>

        </div>


        <!-- NAVIGATION -->

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

            <a href="staff_reports.php">

                Task Reports

            </a>

        </li>


            <li class="active">
                <a href="task_history.php">
                    Task History
                </a>
            </li>


            <li>
                <a href="staff_notifications.php">
                    Notifications
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
                <a href="staff_logout.php">
                    Logout
                </a>
            </li>

        </ul>

    </div>



    <!-- =====================================================
         MAIN CONTENT
    ===================================================== -->

    <div class="main">


        <!-- TOP BAR -->

        <div class="topbar">

            <h1>
                Task History
            </h1>

            <div id="clock"></div>

        </div>



        <!-- =================================================
             SUMMARY CARDS
        ================================================= -->

        <div class="cards">


            <div class="card">

                <h4>TOTAL</h4>

                <h1>
                    <?php echo $total; ?>
                </h1>

                <p>
                    Total Task Reports
                </p>

            </div>


            <div class="card">

                <h4>PENDING</h4>

                <h1>
                    <?php echo $pending; ?>
                </h1>

                <p>
                    Awaiting Approval
                </p>

            </div>


            <div class="card">

                <h4>APPROVED</h4>

                <h1>
                    <?php echo $approved; ?>
                </h1>

                <p>
                    Approved Tasks
                </p>

            </div>


            <div class="card">

                <h4>REJECTED</h4>

                <h1>
                    <?php echo $rejected; ?>
                </h1>

                <p>
                    Rejected Tasks
                </p>

            </div>


        </div>



        <!-- =================================================
             FILTER
        ================================================= -->

        <div class="history-box">


            <form
                method="GET"
                class="filter-form"
            >

                <label>
                    Status
                </label>


                <select name="status">

                    <option value="">
                        All
                    </option>


                    <option
                        value="Pending"
                        <?php
                        if ($status == "Pending") {
                            echo "selected";
                        }
                        ?>
                    >
                        Pending
                    </option>


                    <option
                        value="Approved"
                        <?php
                        if ($status == "Approved") {
                            echo "selected";
                        }
                        ?>
                    >
                        Approved
                    </option>


                    <option
                        value="Rejected"
                        <?php
                        if ($status == "Rejected") {
                            echo "selected";
                        }
                        ?>
                    >
                        Rejected
                    </option>

                </select>


                <button type="submit">
                    Filter
                </button>


            </form>



            <!-- =================================================
                 TASK HISTORY TABLE
            ================================================= -->

            <table class="history-table">


                <thead>

                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Time
                        </th>

                        <th>
                            Hours
                        </th>

                        <th>
                            Task Completed
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Submitted
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php

                if (mysqli_num_rows($task_reports) > 0) {

                    while ($row = mysqli_fetch_assoc($task_reports)) {

                ?>

                    <tr>


                        <!-- DATE -->

                        <td>

                            <?php

                            echo date(
                                "d M Y",
                                strtotime($row['report_date'])
                            );

                            ?>

                        </td>



                        <!-- TIME -->

                        <td>

                            <?php

                            echo date(
                                "h:i A",
                                strtotime($row['start_time'])
                            );

                            ?>

                            -

                            <?php

                            echo date(
                                "h:i A",
                                strtotime($row['end_time'])
                            );

                            ?>

                        </td>



                        <!-- HOURS -->

                        <td>

                            <?php

                            echo number_format(
                                $row['hours_worked'],
                                2
                            );

                            ?>

                            h

                        </td>



                        <!-- TASK -->

                        <td class="task-description">

                            <?php

                            echo nl2br(
                                htmlspecialchars(
                                    $row['tasks_completed']
                                )
                            );

                            ?>

                        </td>



                        <!-- STATUS -->

                        <td>

                            <span
                                class="status <?php
                                echo strtolower(
                                    $row['status']
                                );
                                ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $row['status']
                                );

                                ?>

                            </span>

                        </td>



                        <!-- SUBMITTED -->

                        <td>

                            <?php

                            echo date(
                                "d M Y H:i",
                                strtotime(
                                    $row['created_at']
                                )
                            );

                            ?>

                        </td>


                    </tr>


                <?php

                    }

                } else {

                ?>

                    <tr>

                        <td
                            colspan="6"
                            style="text-align:center;"
                        >

                            No task reports found.

                        </td>

                    </tr>

                <?php

                }

                ?>


                </tbody>

            </table>


        </div>


    </div>


</div>



<!-- CLOCK -->

<script>

function updateClock() {

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();

}

setInterval(updateClock, 1000);

updateClock();

</script>


</body>

</html>