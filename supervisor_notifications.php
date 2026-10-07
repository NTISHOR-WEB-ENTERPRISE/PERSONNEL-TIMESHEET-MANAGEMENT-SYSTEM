<?php

session_start();

include "config.php";
include "notification_function.php";


/* ==========================================
   CHECK LOGIN
========================================== */

if(!isset($_SESSION['supervisor_id'])){

    header("Location: supervisor_login.php");
    exit();

}


$supervisor_id = $_SESSION['supervisor_id'];
$fullname = $_SESSION['fullname'];

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

/* ==========================================
   MARK ALL AS READ
========================================== */

if(isset($_GET['mark_all'])){

    markAllSupervisorNotificationsRead(
        $conn,
        $supervisor_id
    );

    header("Location: supervisor_notifications.php");
    exit();

}


/* ==========================================
   MARK ONE AS READ
========================================== */

if(isset($_GET['read'])){

    $notification_id = intval($_GET['read']);

    markSupervisorNotificationRead(
        $conn,
        $notification_id,
        $supervisor_id
    );

    header("Location: supervisor_notifications.php");
    exit();
}


/* ==========================================
   GET NOTIFICATIONS
========================================== */

$notifications = getSupervisorNotifications(
    $conn,
    $supervisor_id,
    100
);


/* ==========================================
   UNREAD COUNT
========================================== */

$unread = countSupervisorNotifications(
    $conn,
    $supervisor_id
);

?>

<!DOCTYPE html>
<html>

<head>

<title>Notifications</title>

<link rel="stylesheet" href="styles.css">

</head>


<body>


<div class="container">


<!-- ==========================================
     SIDEBAR
========================================== -->

<div class="sidebar">


<div class="logo">

<h2><?php echo $app['organization_name']; ?></h2>

<p>Timesheet System</p>

</div>


<div class="profile">

<div class="avatar">

<img
    src="<?php echo htmlspecialchars($profile_photo); ?>"
    class="profile-small"
    alt="Profile"
    onerror="this.src='uploads/supervisors/default.png';"
>

</div>

<h3><?php echo $fullname; ?></h3>

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


<li class="active">

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


<!-- ==========================================
     MAIN
========================================== -->

<div class="main">


<div class="topbar">

<div>

<h1>Notifications</h1>

<p>
Your system notifications
</p>

</div>


<div id="clock"></div>

</div>


<!-- ==========================================
     NOTIFICATION HEADER
========================================== -->

<div class="history-box">


<div class="notification-header">


<div>

<h2>

Notifications

<?php if($unread > 0){ ?>

<span class="notification-count">

<?php echo $unread; ?>

Unread

</span>

<?php } ?>

</h2>

</div>


<div class="notification-actions">

<?php if($unread > 0){ ?>

<a href="supervisor_notifications.php?mark_all=1">

✓ Mark All as Read

</a>

<?php } ?>

</div>


</div>


<!-- ==========================================
     NOTIFICATION LIST
========================================== -->

<div class="notification-list">


<?php

if(mysqli_num_rows($notifications) > 0){

    while($notification =
    mysqli_fetch_assoc($notifications)){

        $is_unread =
        ($notification['is_read'] == 0);

?>


<div class="notification-item
<?php echo $is_unread ? 'unread' : 'read'; ?>">


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


<?php if($is_unread){ ?>

<a
href="supervisor_notifications.php?read=<?php echo $notification['id']; ?>"
class="notification-read-btn">

✓ Mark as Read

</a>

<?php } ?>


</div>


<div>

<span class="notification-status
<?php echo $is_unread ? 'unread' : 'read'; ?>">

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

}else{

?>


<div class="empty-notifications">

<h2>🔔</h2>

<h3>No Notifications</h3>

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

<script>

function updateClock(){

    document.getElementById("clock").innerHTML =
    new Date().toLocaleTimeString();

}

updateClock();

setInterval(updateClock,1000);

</script>


</body>

</html>