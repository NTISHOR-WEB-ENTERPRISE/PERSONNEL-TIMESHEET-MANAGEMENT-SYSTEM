<?php

session_start();

include "config.php";
include "notification_function.php";


/* ==========================================
   CHECK ADMIN LOGIN
========================================== */

if(!isset($_SESSION['admin_id'])){

    header("Location: admin_login.php");
    exit();

}


$admin_id = $_SESSION['admin_id'];
$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

/* ==============================
   ADMIN PROFILE PHOTO
============================== */

$stmt = $conn->prepare("
    SELECT
        fullname,
        profile_photo
    FROM admins
    WHERE admin_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $admin_id
);

$stmt->execute();

$result = $stmt->get_result();

$admin_data = $result->fetch_assoc();

$stmt->close();


/* ==============================
   ADMIN PROFILE PHOTO PATH
============================== */

$admin_profile_photo =
    "uploads/admins/default.png";


if (
    $admin_data &&
    !empty($admin_data['profile_photo'])
) {

    $photo =
        basename(
            $admin_data['profile_photo']
        );

    $photo_path =
        "uploads/admins/" . $photo;


    if (file_exists($photo_path)) {

        $admin_profile_photo =
            $photo_path;

    }

}

$message = "";
$message_class = "";

if(isset($_GET['sent']) && $_GET['sent'] == "1"){

    $message = "Notification sent successfully.";
    $message_class = "success";

}


/* ==========================================
   SEND NOTIFICATION
========================================== */

if(isset($_POST['send_notification'])){

    $recipient_type = $_POST['recipient_type'] ?? "";
    $notification_message = trim($_POST['message'] ?? "");

    /* Get selected recipient */
    if($recipient_type == "staff"){

        $recipient_id = $_POST['staff_recipient'] ?? "";

    }elseif($recipient_type == "supervisor"){

        $recipient_id = $_POST['supervisor_recipient'] ?? "";

    }else{

        $recipient_id = "";

    }


    /* Validate */
    if(
        $recipient_type == "" ||
        $recipient_id == "" ||
        $notification_message == ""
    ){

        $message = "Please complete all fields.";
        $message_class = "error";

    }else{

        $success = false;


        /* Send to Staff */
        if($recipient_type == "staff"){

            $success = notifyStaff(
                $conn,
                $recipient_id,
                $notification_message
            );

        }


        /* Send to Supervisor */
        elseif($recipient_type == "supervisor"){

            $success = notifySupervisor(
                $conn,
                $recipient_id,
                $notification_message
            );

        }


        /* Result */

if($success){

    header("Location: notifications.php?sent=1");
    exit();

}else{

    $message = "Failed to send notification.";
    $message_class = "error";

}

    }

}


/* ==========================================
   GET STAFF
========================================== */

$staff_query = mysqli_query($conn,"
    SELECT staff_id, fullname, department
    FROM staff
    WHERE status='active'
    ORDER BY fullname ASC
");


/* ==========================================
   GET SUPERVISORS
========================================== */

$supervisor_query = mysqli_query($conn,"
    SELECT supervisor_id, fullname, department
    FROM supervisors
    ORDER BY fullname ASC
");


/* ==========================================
   GET RECENT NOTIFICATIONS
========================================== */

$notifications = mysqli_query($conn,"
    SELECT *
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 30
");

?>

<!DOCTYPE html>
<html>

<head>

<title>Notifications</title>

<link rel="stylesheet" href="styles.css">

<style>

/* ==========================================
   NOTIFICATION PAGE
========================================== */

.notification-form-box{

    background:#fff;

    padding:25px;

    border-radius:10px;

    margin-bottom:25px;

    box-shadow:0 2px 8px rgba(0,0,0,0.08);

}

.notification-form-box h2{

    margin-top:0;

    margin-bottom:20px;

}


.notification-form{

    display:grid;

    gap:18px;

}


.form-group{

    display:flex;

    flex-direction:column;

    gap:7px;

}


.form-group label{

    font-weight:600;

}


.form-group select,
.form-group textarea{

    width:100%;

    padding:12px;

    border:1px solid #ddd;

    border-radius:6px;

    font-size:14px;

    box-sizing:border-box;

}


.form-group textarea{

    min-height:120px;

    resize:vertical;

}


.send-notification-btn{

    border:none;

    padding:12px 20px;

    border-radius:6px;

    background:#1565c0;

    color:#fff;

    font-weight:bold;

    cursor:pointer;

    width:max-content;

}


.send-notification-btn:hover{

    background:#0d47a1;

}


.alert{

    padding:13px 16px;

    border-radius:6px;

    margin-bottom:20px;

}


.alert.success{

    background:#d4edda;

    color:#155724;

}


.alert.error{

    background:#f8d7da;

    color:#721c24;

}


.notification-message{

    max-width:500px;

}


.notification-status.unread{

    color:#d97706;

    font-weight:bold;

}


.notification-status.read{

    color:#198754;

}


</style>

</head>


<body>


<div class="container">


<!-- ==========================================
     SIDEBAR
========================================== -->

<div class="sidebar">


<div class="logo">

<h2><?php echo htmlspecialchars($app['organization_name']); ?></h2>

<p>Personnel Timesheet System</p>

</div>


<div class="profile">

<div class="avatar">

<img
    src="<?php echo htmlspecialchars($admin_profile_photo); ?>"
    class="profile-small"
    alt="Admin Profile Photo"
    onerror="this.src='uploads/admins/default.png';"
>

</div>

<h3><?php echo htmlspecialchars($fullname); ?></h3>

<p><?php echo htmlspecialchars($admin_role); ?></p>

</div>


<ul>


<li>

<a href="admin_dashboard.php">

Dashboard

</a>

</li>


<li><a href="manage_staff.php">Manage Staff</a></li>
<li><a href="manage_supervisors.php">Manage Supervisors</a></li>


<?php if($admin_role == "Super Admin"){ ?>

<li>

<a href="manage_admins.php">

Manage Admins

</a>

</li>

<?php } ?>


<li>

<a href="activity_logs.php">

Activity Logs

</a>

</li>


<li class="active">

<a href="notifications.php">

Notifications

</a>

</li>


<li>

<a href="reports.php">

Reports

</a>

</li>


<li>

<a href="admin_profile.php">

My Profile

</a>

</li>


<li>

<a href="admin_logout.php">

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

<p>Send notifications to staff and supervisors</p>

</div>


<div id="clock"></div>


</div>



<!-- ==========================================
     MESSAGE
========================================== -->

<?php if($message != ""){ ?>

<div class="alert <?php echo $message_class; ?>">

<?php echo htmlspecialchars($message); ?>

</div>

<?php } ?>



<!-- ==========================================
     SEND NOTIFICATION
========================================== -->

<div class="notification-form-box">


<h2>Send Notification</h2>


<form method="POST" class="notification-form">


<!-- RECIPIENT TYPE -->

<div class="form-group">

<label>

Send Notification To

</label>


<select
name="recipient_type"
id="recipient_type"
required
onchange="changeRecipient()"
>

<option value="">

Select Recipient Type

</option>


<option value="staff">

Staff

</option>


<option value="supervisor">

Supervisor

</option>


</select>

</div>



<!-- STAFF -->

<div
class="form-group"
id="staff_recipient"
style="display:none;"
>

<label>

Select Staff

</label>


<select name="staff_recipient">


<option value="">

Select Staff Member

</option>


<?php

while($staff = mysqli_fetch_assoc($staff_query)){

?>

<option value="<?php echo htmlspecialchars($staff['staff_id']); ?>">

<?php

echo htmlspecialchars($staff['fullname']);

if(!empty($staff['department'])){

echo " - " . htmlspecialchars($staff['department']);

}

?>

</option>


<?php

}

?>


</select>

</div>



<!-- SUPERVISOR -->

<div
class="form-group"
id="supervisor_recipient"
style="display:none;"
>

<label>

Select Supervisor

</label>


<select name="supervisor_recipient">


<option value="">

Select Supervisor

</option>


<?php

while($supervisor = mysqli_fetch_assoc($supervisor_query)){

?>

<option value="<?php echo htmlspecialchars($supervisor['supervisor_id']); ?>">

<?php

echo htmlspecialchars($supervisor['fullname']);

if(!empty($supervisor['department'])){

echo " - " . htmlspecialchars($supervisor['department']);

}

?>

</option>


<?php

}

?>


</select>

</div>



<!-- MESSAGE -->

<div class="form-group">

<label>

Notification Message

</label>


<textarea
name="message"
placeholder="Type your notification here..."
required
></textarea>

</div>



<button
type="submit"
name="send_notification"
class="send-notification-btn"
>

🔔 Send Notification

</button>


</form>


</div>



<!-- ==========================================
     RECENT NOTIFICATIONS
========================================== -->

<div class="history-box">


<h2>Recent Notifications</h2>


<table class="history-table">


<thead>

<tr>

<th>Date</th>

<th>Recipient</th>

<th>Message</th>

<th>Status</th>

</tr>

</thead>


<tbody>


<?php

if(mysqli_num_rows($notifications) > 0){

while($row = mysqli_fetch_assoc($notifications)){

?>


<tr>


<td>

<?php

echo date(
    "d M Y h:i A",
    strtotime($row['created_at'])
);

?>

</td>


<td>

<?php

if(!empty($row['staff_id'])){

echo "Staff: " . htmlspecialchars($row['staff_id']);

}

elseif(!empty($row['supervisor_id'])){

echo "Supervisor: " . htmlspecialchars($row['supervisor_id']);

}

elseif(!empty($row['admin_id'])){

echo "Admin: " . htmlspecialchars($row['admin_id']);

}

else{

echo "Unknown";

}

?>

</td>


<td class="notification-message">

<?php

echo htmlspecialchars($row['message']);

?>

</td>


<td>

<span class="notification-status
<?php echo strtolower($row['status']); ?>">

<?php

echo htmlspecialchars($row['status']);

?>

</span>

</td>


</tr>


<?php

}

}else{

?>


<tr>

<td colspan="4">

No notifications found.

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



<script>


/* ==========================================
   RECIPIENT SELECTOR
========================================== */

function changeRecipient(){

    const type =
        document.getElementById("recipient_type").value;

    const staff =
        document.getElementById("staff_recipient");

    const supervisor =
        document.getElementById("supervisor_recipient");


    staff.style.display = "none";

    supervisor.style.display = "none";


    if(type === "staff"){

        staff.style.display = "flex";

        document.querySelector(
            '[name="staff_recipient"]'
        ).required = true;

        document.querySelector(
            '[name="supervisor_recipient"]'
        ).required = false;

    }


    if(type === "supervisor"){

        supervisor.style.display = "flex";

        document.querySelector(
            '[name="supervisor_recipient"]'
        ).required = true;

        document.querySelector(
            '[name="staff_recipient"]'
        ).required = false;

    }

}


/* ==========================================
   CLOCK
========================================== */

function updateClock(){

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();

}

updateClock();

setInterval(updateClock,1000);

</script>


</body>

</html>