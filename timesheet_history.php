<?php

session_start();

include "config.php";


/* =========================================================
   CHECK LOGIN
========================================================= */

if (!isset($_SESSION['staff_id'])) {

    header("Location: staff_login.php");
    exit();

}


$staff_id = $_SESSION['staff_id'];

$fullname = $_SESSION['fullname'] ?? '';



/* =========================================================
   FETCH STAFF PROFILE PHOTO
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



/* =========================================================
   STAFF NAME
========================================================= */

$fullname = $staff_data['fullname'] ?? $fullname;



/* =========================================================
   PROFILE PHOTO
========================================================= */

$profile_photo = "uploads/staff/default.png";


if (!empty($staff_data['profile_picture'])) {

    $photo_name = basename(
        $staff_data['profile_picture']
    );


    /*
     * NEW STAFF PHOTO LOCATION
     */

    if (
        file_exists(
            "uploads/staff/" . $photo_name
        )
    ) {

        $profile_photo =
            "uploads/staff/" . $photo_name;

    }


    /*
     * OLD PHOTO LOCATION
     */

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
   TOTAL TIMESHEETS
========================================================= */

$result = mysqli_query($conn, "

    SELECT COUNT(*) total

    FROM timesheets

    WHERE staff_id='$staff_id'

");


$total =
    mysqli_fetch_assoc($result)['total'];



/* =========================================================
   PENDING
========================================================= */

$result = mysqli_query($conn, "

    SELECT COUNT(*) total

    FROM timesheets

    WHERE staff_id='$staff_id'

    AND status='pending'

");


$pending =
    mysqli_fetch_assoc($result)['total'];



/* =========================================================
   APPROVED
========================================================= */

$result = mysqli_query($conn, "

    SELECT COUNT(*) total

    FROM timesheets

    WHERE staff_id='$staff_id'

    AND status='approved'

");


$approved =
    mysqli_fetch_assoc($result)['total'];



/* =========================================================
   REJECTED
========================================================= */

$result = mysqli_query($conn, "

    SELECT COUNT(*) total

    FROM timesheets

    WHERE staff_id='$staff_id'

    AND status='rejected'

");


$rejected =
    mysqli_fetch_assoc($result)['total'];



/* =========================================================
   STATUS FILTER
========================================================= */

$status = "";


if (isset($_GET['status'])) {

    $status = $_GET['status'];

}



/* =========================================================
   TIMESHEET QUERY
========================================================= */

$sql = "

SELECT *

FROM timesheets

WHERE staff_id='$staff_id'

";


if ($status != "") {

    $sql .= "
        AND status='$status'
    ";

}


$sql .= "

ORDER BY created_at DESC

";


$timesheets =
    mysqli_query($conn, $sql);

?>

<!DOCTYPE html>

<html>

<head>

    <title>
        Timesheet History
    </title>

    <link
        rel="stylesheet"
        href="styles.css"
    >

    <style>

        /* =====================================================
           SIDEBAR PROFILE PHOTO
        ===================================================== */

        .sidebar-profile-photo {

            width:55px;

            height:55px;

            border-radius:50%;

            object-fit:cover;

            display:block;

            border:3px solid #ffffff;

            box-shadow:
                0 2px 8px rgba(0,0,0,0.15);

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

    </style>

</head>


<body>


<div class="container">


    <!-- =====================================================
         SIDEBAR
    ===================================================== -->

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

                    class="sidebar-profile-photo"

                    alt="Profile Photo"

                    onerror="
                        this.onerror=null;
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



        <!-- =================================================
             NAVIGATION
        ================================================= -->

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



            <li class="active">

                <a href="timesheet_history.php">

                    Submission History

                </a>

            </li>

            <li><a href="staff_notifications.php">Notifications</a></li>

            <li>

                <a href="work_schedule.php">

                    My Schedule

                </a>

            </li>

            <li><a href="staff_profile.php">Profile</a></li>

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
                Timesheet History
            </h1>


            <div id="clock"></div>


        </div>



        <!-- =================================================
             SUMMARY CARDS
        ================================================= -->

        <div class="cards">


            <div class="card">

                <h4>
                    TOTAL
                </h4>

                <h1>
                    <?php echo $total; ?>
                </h1>

                <p>
                    Total Timesheets
                </p>

            </div>



            <div class="card">

                <h4>
                    PENDING
                </h4>

                <h1>
                    <?php echo $pending; ?>
                </h1>

                <p>
                    Awaiting Approval
                </p>

            </div>



            <div class="card">

                <h4>
                    APPROVED
                </h4>

                <h1>
                    <?php echo $approved; ?>
                </h1>

                <p>
                    Approved Timesheets
                </p>

            </div>



            <div class="card">

                <h4>
                    REJECTED
                </h4>

                <h1>
                    <?php echo $rejected; ?>
                </h1>

                <p>
                    Rejected Timesheets
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
                        value="pending"

                        <?php

                        if ($status == "pending") {

                            echo "selected";

                        }

                        ?>

                    >

                        Pending

                    </option>



                    <option
                        value="approved"

                        <?php

                        if ($status == "approved") {

                            echo "selected";

                        }

                        ?>

                    >

                        Approved

                    </option>



                    <option
                        value="rejected"

                        <?php

                        if ($status == "rejected") {

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
                 TIMESHEET TABLE
            ================================================= -->

            <table class="history-table">


                <thead>


                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Hours
                        </th>

                        <th>
                            Task
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

                if (
                    mysqli_num_rows(
                        $timesheets
                    ) > 0
                ) {


                    while (
                        $row =
                        mysqli_fetch_assoc(
                            $timesheets
                        )
                    ) {

                ?>


                    <tr>


                        <!-- DATE -->

                        <td>

                            <?php

                            echo date(
                                "d M Y",
                                strtotime(
                                    $row['work_date']
                                )
                            );

                            ?>

                        </td>



                        <!-- HOURS -->

                        <td>

                            <?php

                            echo number_format(
                                $row['hours_worked'],
                                1
                            );

                            ?>

                            h

                        </td>



                        <!-- TASK -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $row['task_description']
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

                                echo ucfirst(
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


                    echo "

                    <tr>

                        <td
                            colspan='5'
                            style='text-align:center;'
                        >

                            No timesheets found.

                        </td>

                    </tr>

                    ";

                }

                ?>


                </tbody>


            </table>


        </div>


    </div>


</div>



<!-- =========================================================
     CLOCK
========================================================= -->

<script>

function updateClock(){

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();

}

setInterval(
    updateClock,
    1000
);

updateClock();

</script>


</body>

</html>