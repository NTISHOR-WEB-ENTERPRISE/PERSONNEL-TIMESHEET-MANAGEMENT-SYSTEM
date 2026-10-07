<?php
session_start();
include "config.php";
include "notification_function.php";

if (!isset($_SESSION['supervisor_id'])) {
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
/* ==================================
   SUPERVISOR NOTIFICATIONS
================================== */

$unread_notifications = countSupervisorNotifications(
    $conn,
    $supervisor_id
);

/* Statistics */

/* Task Approval Queue */

$pending_list = mysqli_query($conn,
"SELECT 
    t.*,
    s.fullname
 FROM task_reports t
 INNER JOIN staff s
    ON t.staff_id = s.staff_id
 WHERE t.supervisor_id = '$supervisor_id'
 AND t.status = 'Pending'
 ORDER BY t.report_date DESC, t.created_at DESC
 LIMIT 10");

/* Active Assigned Employees under this supervisor */

$employees = mysqli_query($conn,"
    SELECT COUNT(*) AS total
    FROM staff
    WHERE supervisor_id='$supervisor_id'
    AND status='active'
");

$total_staff = mysqli_fetch_assoc($employees)['total'];

/* Pending Task Reports */

$pending = mysqli_query($conn,
"SELECT COUNT(*) AS total
 FROM task_reports t
 INNER JOIN staff s
    ON t.staff_id = s.staff_id
 WHERE t.supervisor_id = '$supervisor_id'
 AND t.status = 'Pending'");

$total_pending = mysqli_fetch_assoc($pending)['total'];

/* Recently Approved Task Reports */

$approved = mysqli_query($conn,
"SELECT 
    t.*,
    s.fullname
 FROM task_reports t
 INNER JOIN staff s
    ON t.staff_id = s.staff_id
 WHERE t.supervisor_id = '$supervisor_id'
 AND t.status = 'Approved'
 ORDER BY t.report_date DESC, t.approved_at DESC, t.created_at DESC
 LIMIT 5");
 
/* Overtime Cases - Employees who worked more than 40 hours this week */

$overtime = mysqli_query($conn,"
    SELECT COUNT(*) AS total
    FROM (
        SELECT 
            t.staff_id,
            SUM(t.hours_worked) AS weekly_hours
        FROM timesheets t
        INNER JOIN staff s
            ON t.staff_id = s.staff_id
        WHERE s.supervisor_id = '$supervisor_id'
        AND t.work_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
        AND t.work_date <= CURDATE()
        GROUP BY t.staff_id
        HAVING SUM(t.hours_worked) > 40
    ) AS overtime_staff
");

$total_overtime = mysqli_fetch_assoc($overtime)['total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Supervisor Dashboard</title>
<link rel="stylesheet" href="styles.css">

<style>
/* =========================================================
   TASK DASHBOARD SECTION
========================================================= */

.bottom-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-top: 25px;
    align-items: start;
}

.big-card {
    background: #ffffff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    min-height: 180px;
}

.big-card h3 {
    margin: 0 0 18px 0;
    font-size: 17px;
    color: #222;
    padding-bottom: 12px;
    border-bottom: 1px solid #eee;
}

/* =========================================================
   TASK APPROVAL QUEUE
========================================================= */

.queue-item {
    background: #f8f9fa;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 12px;
}

.queue-item:last-child {
    margin-bottom: 0;
}

.queue-item h4 {
    margin: 0 0 8px 0;
    font-size: 15px;
    color: #222;
}

.queue-item p {
    margin: 5px 0;
    font-size: 13px;
    color: #666;
}

.queue-item .task-name {
    color: #222;
    font-weight: 500;
}

.queue-actions {
    margin-top: 12px;
    display: flex;
    gap: 8px;
}

.approve-btn,
.reject-btn {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

.approve-btn {
    background: #198754;
    color: white;
}

.reject-btn {
    background: #dc3545;
    color: white;
}

.approve-btn:hover,
.reject-btn:hover {
    opacity: 0.9;
}

/* =========================================================
   APPROVED TASKS
========================================================= */

.approved-task {
    position: relative;
    background: #f8f9fa;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 14px 15px;
    margin-bottom: 10px;
}

.approved-task:last-child {
    margin-bottom: 0;
}

.approved-task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 7px;
}

.approved-task-header strong {
    font-size: 14px;
    color: #222;
}

.approved-badge {
    background: #d1e7dd;
    color: #0f5132;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.approved-task .task-description {
    font-size: 13px;
    color: #444;
    margin: 6px 0;
    
    /* Prevent very long tasks from making the card huge */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.approved-task-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #e5e5e5;
}

.approved-date {
    font-size: 11px;
    color: #777;
}

.approved-hours {
    font-size: 12px;
    font-weight: 600;
    color: #198754;
}

/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    text-align: center;
    padding: 35px 15px;
    color: #888;
}

.empty-state-icon {
    font-size: 30px;
    margin-bottom: 10px;
}

.empty-state p {
    margin: 0;
    font-size: 13px;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 900px) {

    .bottom-grid {
        grid-template-columns: 1fr;
    }

}
</style>
</head>

<body>

<div class="container">

    <!-- Sidebar -->
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

            <h4><?php echo $fullname; ?></h4>
            <p>Supervisor</p>

        </div>

        <ul>

            <li class="active">
                <a href="supervisor_dashboard.php">Dashboard </a>
            </li>

            <li><a href="approvals.php">Approvals</a></li>

            <li><a href="supervisor_assign_task.php">Assign Task</a></li>

            <li><a href="task_report_approvals.php">Task Reports</a></li>

            <li>
                <a href="reports.php">
                    Reports
                </a>
            </li>         

            <li><a href="supervisor_notifications.php">Notifications</a></li>
            <li><a href="view_my_staff.php">View My Staff</a></li>

            <li><a href="supervisor_profile.php">My Profile</a></li>

            <li>
                <a href="supervisor_logout.php">
                    Logout
                </a>
            </li>

        </ul>

    </div>

    <!-- Main -->

    <div class="main">

        <div class="page-header">

   <div>

                <h1>Supervisor Dashboard</h1>

                <p>Welcome back, <?php echo $fullname; ?></p>

            </div>

    <div class="header-right">

        <a
            href="supervisor_notifications.php"
            class="notification-bell"
            title="Notifications"
        >

            🔔

            <?php if($unread_notifications > 0){ ?>

                <span class="notification-badge">
                    <?php echo $unread_notifications; ?>
                </span>

            <?php } ?>

        </a>

        <div id="clock"></div>

    </div>

</div>

        <!-- Cards -->

        <div class="stats">

           <div class="card">
    <h5>ACTIVE EMPLOYEES</h5>

    <h1>
        <?php echo $total_staff; ?>
    </h1>

    <p>Active staff assigned to you</p>
</div>

            <div class="card">

                <h5>PENDING APPROVALS</h5>

                <h1>
                    <?php echo $total_pending; ?>
                </h1>

                <p>Task Reports Awaiting Review</p>

            </div>

            <div class="card">

                <h5>OVERTIME CASES</h5>

                <h1><?php echo $total_overtime; ?></h1>

                <p>Employees > 40h this week</p>

            </div>

        </div>

        <!-- Bottom Section -->

        <div class="bottom-grid">

           <div class="big-card">

    <h3>Task Approval Queue</h3>

    <?php
    if (mysqli_num_rows($pending_list) > 0) {

        while ($row = mysqli_fetch_assoc($pending_list)) {
    ?>

        <div class="queue-item">

            <h4>
                <?php echo htmlspecialchars($row['fullname']); ?>
            </h4>

            <p>
                <strong>Date:</strong>
                <?php
                echo date(
                    "d M Y",
                    strtotime($row['report_date'])
                );
                ?>
            </p>

            <p class="task-name">
                <strong>Task:</strong>
                <?php
                echo htmlspecialchars(
                    $row['tasks_completed']
                );
                ?>
            </p>

            <p>
                <strong>Hours:</strong>
                <?php
                echo number_format(
                    (float)$row['hours_worked'],
                    2
                );
                ?>h
            </p>

            <div class="queue-actions">

                <a
                    href="task_report_approvals.php?action=approve&id=<?php echo urlencode($row['id']); ?>"
                    class="approve-btn"
                >
                    ✓ Approve
                </a>

                <a
                    href="task_report_approvals.php?action=reject&id=<?php echo urlencode($row['id']); ?>"
                    class="reject-btn"
                >
                    ✕ Reject
                </a>

            </div>

        </div>

    <?php
        }

    } else {
    ?>

        <div class="empty-state">

            <div class="empty-state-icon">
                ✓
            </div>

            <p>
                No pending task reports.
            </p>

        </div>

    <?php
    }
    ?>

</div>

           <div class="big-card">

    <h3>Recently Approved Task Reports</h3>

    <?php
    if (mysqli_num_rows($approved) > 0) {

        while ($row = mysqli_fetch_assoc($approved)) {
    ?>

        <div class="approved-task">

            <div class="approved-task-header">

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $row['fullname']
                    );
                    ?>
                </strong>

                <span class="approved-badge">
                    ✓ Approved
                </span>

            </div>

            <div class="task-description">

                <?php
                echo htmlspecialchars(
                    $row['tasks_completed']
                );
                ?>

            </div>

            <div class="approved-task-footer">

                <span class="approved-date">

                    <?php
                    echo date(
                        "d M Y",
                        strtotime(
                            $row['report_date']
                        )
                    );
                    ?>

                </span>

                <span class="approved-hours">

                    <?php
                    echo number_format(
                        (float)$row['hours_worked'],
                        2
                    );
                    ?>h

                </span>

            </div>

        </div>

    <?php
        }

    } else {
    ?>

        <div class="empty-state">

            <div class="empty-state-icon">
                ✓
            </div>

            <p>
                No approved task reports yet.
            </p>

        </div>

    <?php
    }
    ?>

</div>
       
</div>
            
        </div>

    </div>

</div>

<script>
function updateClock(){
    let now = new Date();

    document.getElementById('clock').innerHTML =
    '● ' +
    now.toLocaleTimeString(
        'en-GB',
        {hour12:false}
    );
}

updateClock();
setInterval(updateClock,1000);
</script>
</body>
</html>