<?php
session_start();
include "config.php";
include "notification_function.php";

if(!isset($_SESSION['supervisor_id'])){
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$fullname = $_SESSION['fullname'];

/* =====================================================
   SUPERVISOR PROFILE PHOTO
===================================================== */

$stmt = $conn->prepare("
    SELECT profile_photo
    FROM supervisors
    WHERE supervisor_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$result_photo = $stmt->get_result();
$supervisor_data = $result_photo->fetch_assoc();

$stmt->close();

$supervisor_profile_photo = "uploads/supervisors/default.png";

if (
    $supervisor_data &&
    !empty($supervisor_data['profile_photo'])
) {

    $photo = basename($supervisor_data['profile_photo']);

    $photo_path = "uploads/supervisors/" . $photo;

    if (file_exists($photo_path)) {
        $supervisor_profile_photo = $photo_path;
    }
}

if(!isset($_GET['id'])){
    header("Location: task_report_approvals.php");
    exit();
}

$id = intval($_GET['id']);

/* =====================================================
   GET TASK REPORT
===================================================== */

$sql = "
SELECT
    tr.*,
    s.fullname,
    s.department,
    s.profile_picture
FROM task_reports tr
INNER JOIN staff s
    ON tr.staff_id = s.staff_id
WHERE tr.id = '$id'
AND tr.supervisor_id = '$supervisor_id'
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) == 0) {
    die("Task Report Not Found.");
}

$report = mysqli_fetch_assoc($result);

/* =====================================================
   STAFF PROFILE PHOTO
===================================================== */

$staff_profile_photo = "uploads/staff/default.png";

if (!empty($report['profile_photo'])) {

    $staff_photo = basename($report['profile_photo']);

    $staff_photo_path =
        "uploads/staff/" . $staff_photo;

    if (file_exists($staff_photo_path)) {

        $staff_profile_photo = $staff_photo_path;

    }
}

/* =====================================================
   APPROVE REPORT
===================================================== */

if(isset($_POST['approve'])){

    $comment = mysqli_real_escape_string(
        $conn,
        $_POST['supervisor_comment']
    );

    $update = mysqli_query($conn,"
        UPDATE task_reports
        SET
            status='Approved',
            supervisor_comment='$comment',
            approved_by='$fullname',
            approved_at=NOW(),
            reviewed_at=NOW()
        WHERE id='$id'
        AND supervisor_id='$supervisor_id'
    ");

    if($update){

        $report_date = date(
            "d M Y",
            strtotime($report['report_date'])
        );

        $message = "Your task report dated "
            . $report_date
            . " has been approved by Supervisor "
            . $fullname
            . ".";

        if(!empty($comment)){
            $message .= " Supervisor comment: " . $comment;
        }

        notifyStaff(
            $conn,
            $report['staff_id'],
            $message
        );
    }

    header("Location: task_report_details.php?id=".$id);
    exit();
}


/* =====================================================
   REJECT REPORT
===================================================== */

if(isset($_POST['reject'])){

    $comment = mysqli_real_escape_string(
        $conn,
        $_POST['supervisor_comment']
    );

    $update = mysqli_query($conn,"
        UPDATE task_reports
        SET
            status='Rejected',
            supervisor_comment='$comment',
            reviewed_at=NOW()
        WHERE id='$id'
        AND supervisor_id='$supervisor_id'
    ");

    if($update){

        $report_date = date(
            "d M Y",
            strtotime($report['report_date'])
        );

        $message = "Your task report dated "
            . $report_date
            . " has been rejected by Supervisor "
            . $fullname
            . ".";

        if(!empty($comment)){
            $message .= " Supervisor comment: " . $comment;
        }

        notifyStaff(
            $conn,
            $report['staff_id'],
            $message
        );
    }

    header("Location: task_report_details.php?id=".$id);
    exit();
}


/* =====================================================
   REFRESH REPORT AFTER APPROVAL / REJECTION
===================================================== */

$result = mysqli_query($conn,$sql);

$report = mysqli_fetch_assoc($result);

?>

<!DOCTYPE html>
<html>
<head>

<title>Task Report Details</title>

<link rel="stylesheet" href="styles.css">

<style>
    .avatar img,
.avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.profile-small {
    width: 45px;
    height: 45px;
    object-fit: cover;
    border-radius: 50%;
    display: block;
}
</style>
</head>

<body>

<div class="container">

<div class="sidebar">

<div class="logo">

<h2><?php echo $app['organization_name']; ?></h2>

<p>Timesheet System</p>

</div>

<div class="profile">

<div class="avatar">

    <img
        src="<?php echo htmlspecialchars($supervisor_profile_photo); ?>"
        class="profile-small"
        alt="Supervisor Profile"
        onerror="this.src='uploads/supervisors/default.png';"
    >

</div>

<h3><?php echo $fullname; ?></h3>

<p>Supervisor</p>

</div>

<ul>

<li>
<a href="supervisor_dashboard.php">Dashboard</a>
</li>

<li>
<a href="approvals.php">Approvals</a>
</li>

<li class="active">
<a href="task_report_approvals.php">
Task Reports
</a>
</li>

<li>
<a href="reports.php">Reports</a>
</li>

<li>
<a href="supervisor_logout.php">Logout</a>
</li>

</ul>

</div>


<div class="main">

    <div class="topbar">

        <div>
            <h1>Task Report Details</h1>
            <p>Review staff task submission</p>
        </div>

        <div id="clock"></div>

    </div>

<div class="details-card">

    <div class="staff-header">

        <div class="avatar-large">




<img

src="uploads/staff/<?php echo htmlspecialchars($photo); ?>"

width="50"

height="50"

style="border-radius:50%;object-fit:cover;"

>

</div>

        <div>
            <h2><?php echo $report['fullname']; ?></h2>
            <p><?php echo $report['department']; ?></p>
        </div>

    </div>


    <div class="info-grid">

        <div class="info-card">
            <span>Report Date</span>
            <h3><?php echo date("d M Y",strtotime($report['report_date'])); ?></h3>
        </div>

        <div class="info-card">
            <span>Start Time</span>
            <h3><?php echo date("h:i A",strtotime($report['start_time'])); ?></h3>
        </div>

        <div class="info-card">
            <span>End Time</span>
            <h3><?php echo date("h:i A",strtotime($report['end_time'])); ?></h3>
        </div>

        <div class="info-card">
            <span>Hours Worked</span>
            <h3><?php echo $report['hours_worked']; ?> hrs</h3>
        </div>

    </div>


    <div class="detail-section">

        <h3>Tasks Completed</h3>

        <div class="content-box">
            <?php echo nl2br(htmlspecialchars($report['tasks_completed'])); ?>
        </div>

    </div>


    <div class="detail-section">

        <h3>Challenges</h3>

        <div class="content-box">

        <?php

        echo !empty($report['challenges'])

        ? nl2br(htmlspecialchars($report['challenges']))

        : "<em>No challenges reported.</em>";

        ?>

        </div>

    </div>


    <div class="detail-section">

        <h3>Remarks</h3>

        <div class="content-box">

        <?php

        echo !empty($report['remarks'])

        ? nl2br(htmlspecialchars($report['remarks']))

        : "<em>No remarks.</em>";

        ?>

        </div>

    </div>


    <div class="status-card">

        <strong>Status</strong>

        <span class="status <?php echo strtolower($report['status']); ?>">

            <?php echo $report['status']; ?>

        </span>

    </div>

<?php if($report['status']=="Pending"){ ?>

<form method="POST">

<div class="form-group">

<label>Supervisor Comment</label>

<textarea
name="supervisor_comment"
rows="5"
placeholder="Write your review..."></textarea>

</div>

<div class="button-group">

<button
type="submit"
name="approve"
class="approve-btn">

✔ Approve

</button>

<button
type="submit"
name="reject"
class="reject-btn">

✖ Reject

</button>

<a
href="task_report_approvals.php"
class="back-btn">

← Back

</a>

</div>

</form>

<?php } else { ?>

<div class="review-box">

<h3>Supervisor Comment</h3>

<div class="content-box">

<?php

echo !empty($report['supervisor_comment'])

? nl2br(htmlspecialchars($report['supervisor_comment']))

: "<em>No comment.</em>";

?>

</div>

</div>

<div class="button-group">

<a
href="task_report_approvals.php"
class="back-btn">

← Back

</a>

</div>

<?php } ?>

</div>

</div>

<script>

function updateClock(){

document.getElementById("clock").innerHTML=
new Date().toLocaleTimeString();

}

updateClock();

setInterval(updateClock,1000);

</script>

</body>

</html>