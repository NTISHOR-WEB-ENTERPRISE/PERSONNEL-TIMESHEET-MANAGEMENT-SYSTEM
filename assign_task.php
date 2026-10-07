<?php
session_start();

include "config.php";
include "notification_function.php";

/* Check Admin Login */
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

/* Get Staff ID */
if (!isset($_GET['staff_id']) || empty($_GET['staff_id'])) {
    die("Staff member not specified.");
}

$staff_id = $_GET['staff_id'];

/* Get staff information and supervisor */
$sql = "
    SELECT 
        staff.staff_id,
        staff.fullname,
        staff.department,
        staff.supervisor_id,
        supervisors.fullname AS supervisor_name
    FROM staff
    LEFT JOIN supervisors
        ON staff.supervisor_id = supervisors.supervisor_id
    WHERE staff.staff_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $staff_id);
$stmt->execute();

$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if (!$staff) {
    die("Staff member not found.");
}


/* Handle Form Submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $task_title = trim($_POST['task_title']);
    $task_description = trim($_POST['task_description']);
    $priority = $_POST['priority'];
    $deadline = $_POST['deadline'];

    $supervisor_id = $staff['supervisor_id'];

    /* Basic validation */
    if (
        empty($task_title) ||
        empty($task_description) ||
        empty($deadline)
    ) {
        $error = "Please fill in all required fields.";
    } elseif (empty($supervisor_id)) {
        $error = "This staff member does not have a supervisor assigned.";
    } else {

        /* Insert Task */
        $insert = "
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
            VALUES (?, ?, ?, ?, ?, 'Assigned', CURDATE(), ?)
        ";

        $stmt = $conn->prepare($insert);

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

            /* Notify Staff */
            if (function_exists('notifyStaff')) {
               notifyStaff(
    $conn,
    $staff_id,
    "A new task has been assigned to you: " . $task_title
);
            }

            /* Notify Supervisor */
            if (function_exists('notifySupervisor')) {
               notifySupervisor(
    $conn,
    $supervisor_id,
    "A new task has been assigned to " . $staff['fullname'] . ": " . $task_title
);
            }

            header("Location: view_staff.php?staff_id=" . urlencode($staff_id) . "&task=success");
            exit();

        } else {

            $error = "Failed to assign task: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Assign Task</title>

    <link rel="stylesheet" href="styles.css">

</head>

<body>

<div class="container">

    <div class="main">

        <div class="task-container">

            <h2>Assign New Task</h2>

            <p>Assign a task to the selected staff member.</p>

            <div class="staff-info">

                <p>
                    <strong>Staff:</strong>
                    <?php echo htmlspecialchars($staff['fullname']); ?>
                </p>

                <p>
                    <strong>Staff ID:</strong>
                    <?php echo htmlspecialchars($staff['staff_id']); ?>
                </p>

                <p>
                    <strong>Department:</strong>
                    <?php echo htmlspecialchars($staff['department']); ?>
                </p>

                <p>
                    <strong>Supervisor:</strong>
                    <?php
                    echo htmlspecialchars(
                        $staff['supervisor_name'] ?? 'Not Assigned'
                    );
                    ?>
                </p>

            </div>


            <?php if (isset($error)): ?>

                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="form-group">

                    <label>Task Title *</label>

                    <input
                        type="text"
                        name="task_title"
                        placeholder="Enter task title"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Task Description *</label>

                    <textarea
                        name="task_description"
                        placeholder="Describe what the staff member is expected to do..."
                        required
                    ></textarea>

                </div>


                <div class="form-row">

                    <div class="form-group">

                        <label>Priority *</label>

                        <select name="priority" required>

                            <option value="Low">Low</option>

                            <option value="Medium" selected>
                                Medium
                            </option>

                            <option value="High">High</option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Deadline *</label>

                        <input
                            type="date"
                            name="deadline"
                            min="<?php echo date('Y-m-d'); ?>"
                            required
                        >

                    </div>

                </div>


                <button type="submit" class="btn-submit">
                    + Assign Task
                </button>


                <a
    href="view_staff.php?staff_id=<?php echo urlencode($staff_id); ?>"
    class="btn-cancel"
>
    Cancel
</a>

            </form>

        </div>

    </div>

</div>

</body>

</html>