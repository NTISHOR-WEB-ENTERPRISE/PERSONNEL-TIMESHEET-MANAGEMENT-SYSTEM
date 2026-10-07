<?php

session_start();

include "config.php";
include "notification_function.php";


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
     * OLD STAFF PHOTO LOCATION
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
   MARK ONE AS READ
========================================================= */

if (isset($_GET['read'])) {

    $notification_id =
        intval($_GET['read']);


    markNotificationRead(
        $conn,
        $notification_id
    );


    header(
        "Location: staff_notifications.php"
    );

    exit();

}



/* =========================================================
   MARK ALL AS READ
========================================================= */

if (isset($_GET['mark_all'])) {

    markAllStaffNotificationsRead(
        $conn,
        $staff_id
    );


    header(
        "Location: staff_notifications.php"
    );

    exit();

}



/* =========================================================
   GET STAFF NOTIFICATIONS
========================================================= */

$notifications =
    getStaffNotifications(
        $conn,
        $staff_id,
        100
    );



/* =========================================================
   UNREAD COUNT
========================================================= */

$unread =
    countStaffNotifications(
        $conn,
        $staff_id
    );

?>

<!DOCTYPE html>

<html>

<head>

<title>
Notifications
</title>


<link
    rel="stylesheet"
    href="styles.css"
>


<style>

/* =========================================================
   SIDEBAR PROFILE PHOTO
========================================================= */

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


.sidebar-profile-photo {

    width:100%;

    height:100%;

    object-fit:cover;

    display:block;

    border-radius:50%;

}



/* =========================================================
   NOTIFICATION HEADER
========================================================= */

.notification-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:20px;

}


.notification-count{

    background:#dc3545;

    color:white;

    padding:5px 10px;

    border-radius:20px;

    font-size:13px;

}


.notification-actions a{

    background:#0d47a1;

    color:white;

    padding:9px 15px;

    border-radius:6px;

    text-decoration:none;

}



/* =========================================================
   NOTIFICATION LIST
========================================================= */

.notification-list{

    display:flex;

    flex-direction:column;

    gap:12px;

}


.notification-item{

    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:18px;

    background:white;

    border-radius:10px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.08);

    border-left:4px solid #ccc;

}


.notification-item.unread{

    border-left-color:#0d47a1;

    background:#f4f8ff;

}


.notification-message{

    font-size:15px;

    font-weight:500;

}


.notification-date{

    margin-top:6px;

    color:#777;

    font-size:12px;

}


.notification-read-btn{

    display:inline-block;

    margin-top:10px;

    background:#0d47a1;

    color:white;

    padding:7px 12px;

    border-radius:5px;

    text-decoration:none;

    font-size:13px;

}


.notification-status{

    padding:5px 10px;

    border-radius:15px;

    font-size:12px;

}


.notification-status.unread{

    background:#fff3cd;

    color:#856404;

}


.notification-status.read{

    background:#d4edda;

    color:#155724;

}


.empty-notifications{

    text-align:center;

    padding:50px;

    color:#777;

}

</style>

</head>


<body>


<div class="container">


<!-- =========================================================
     SIDEBAR
========================================================= -->

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
            Timesheet System
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


        <h4>

            <?php

            echo htmlspecialchars(
                $fullname
            );

            ?>

        </h4>


        <p>
            Staff
        </p>


    </div>



    <!-- =====================================================
         NAVIGATION
    ===================================================== -->

    <ul>


        <li>

            <a href="staff_dashboard.php">

                Dashboard

            </a>

        </li>

        <li>

            <a href="staff_reports.php">

                Task Reports

            </a>

        </li>



        <li class="active">

            <a href="staff_notifications.php">

                Notifications

            </a>

        </li>



        <li>

            <a href="staff_profile.php">

                My Profile

            </a>

        </li>



        <li>

            <a href="staff_logout.php">

                Logout

            </a>

        </li>


    </ul>


</div>



<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">


    <!-- TOPBAR -->

    <div class="topbar">


        <div>

            <h1>
                Notifications
            </h1>


            <p>
                Your system notifications
            </p>

        </div>


        <div id="clock"></div>


    </div>



    <!-- =====================================================
         NOTIFICATIONS BOX
    ===================================================== -->

    <div class="history-box">


        <!-- HEADER -->

        <div class="notification-header">


            <h2>

                Notifications


                <?php if ($unread > 0) { ?>

                    <span class="notification-count">

                        <?php echo $unread; ?>

                        Unread

                    </span>

                <?php } ?>


            </h2>



            <?php if ($unread > 0) { ?>

                <div class="notification-actions">


                    <a
                        href="staff_notifications.php?mark_all=1"
                    >

                        ✓ Mark All as Read

                    </a>


                </div>

            <?php } ?>


        </div>



        <!-- =================================================
             NOTIFICATION LIST
        ================================================= -->

        <div class="notification-list">


        <?php


        if (
            mysqli_num_rows(
                $notifications
            ) > 0
        ) {


            while (
                $notification =
                mysqli_fetch_assoc(
                    $notifications
                )
            ) {


                $is_unread =
                    ($notification['is_read'] == 0);


        ?>


            <div
                class="notification-item
                <?php

                echo $is_unread
                    ? 'unread'
                    : 'read';

                ?>"
            >


                <div>


                    <div class="notification-message">

                        <?php

                        echo htmlspecialchars(
                            $notification['message']
                        );

                        ?>

                    </div>



                    <div class="notification-date">

                        <?php

                        echo date(
                            "d M Y h:i A",
                            strtotime(
                                $notification['created_at']
                            )
                        );

                        ?>

                    </div>



                    <?php if ($is_unread) { ?>


                        <a

                            href="staff_notifications.php?read=<?php
                            echo $notification['id'];
                            ?>"

                            class="notification-read-btn"

                        >

                            ✓ Mark as Read

                        </a>


                    <?php } ?>


                </div>



                <div>


                    <span

                        class="notification-status
                        <?php

                        echo $is_unread
                            ? 'unread'
                            : 'read';

                        ?>"

                    >

                        <?php

                        echo $is_unread
                            ? "Unread"
                            : "Read";

                        ?>

                    </span>


                </div>


            </div>


        <?php


            }


        } else {


        ?>


            <div class="empty-notifications">


                <h2>
                    🔔
                </h2>


                <h3>
                    No Notifications
                </h3>


                <p>
                    You currently have no notifications.
                </p>


            </div>


        <?php


        }


        ?>


        </div>


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

updateClock();

setInterval(
    updateClock,
    1000
);

</script>


</body>

</html>