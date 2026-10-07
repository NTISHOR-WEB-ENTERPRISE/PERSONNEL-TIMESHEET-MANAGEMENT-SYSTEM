<?php
session_start();
include "config.php";
include "activity_logger.php";

/* ============================== CHECK ADMIN LOGIN ============================== */

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SESSION['admin_role'] != "Super Admin") {
    die("Access Denied");
}

$admin_id = $_SESSION['admin_id'];
$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

/* ===================================================== GET ADMIN PROFILE PHOTO ===================================================== */

$admin_profile_photo = "uploads/admins/default.png";

$stmt = $conn->prepare("
    SELECT fullname, profile_photo
    FROM admins
    WHERE admin_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $admin_id);
$stmt->execute();

$profile_result = $stmt->get_result();
$admin_data = $profile_result->fetch_assoc();

$stmt->close();

if ($admin_data) {

    // Use database fullname if available
    if (!empty($admin_data['fullname'])) {
        $fullname = $admin_data['fullname'];
    }

    if (!empty($admin_data['profile_photo'])) {

        $photo = basename(
            $admin_data['profile_photo']
        );

        $photo_path = "uploads/admins/" . $photo;

        if (file_exists($photo_path)) {

            $admin_profile_photo = $photo_path;

        }
    }
}

$message = "";
$message_class = "";

/* ============================== HANDLE FORM SUBMISSION ============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $supervisor_id = trim($_POST['supervisor_id']);
    $supervisor_name = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $password = $_POST['password'];
    $status = $_POST['status'];

    /* ============================== VALIDATION ============================== */

    if (
        empty($supervisor_id) ||
        empty($supervisor_name) ||
        empty($password)
    ) {

        $message = "Supervisor ID, Full Name and Password are required.";
        $message_class = "error";

    } elseif (
        $status !== "Active" &&
        $status !== "Inactive"
    ) {

        $message = "Invalid supervisor status.";
        $message_class = "error";

    } else {

        /* ============================== CHECK DUPLICATE SUPERVISOR ID ============================== */

        $check = $conn->prepare("
            SELECT id
            FROM supervisors
            WHERE supervisor_id = ?
        ");

        $check->bind_param(
            "s",
            $supervisor_id
        );

        $check->execute();

        $duplicate = $check->get_result();

        $check->close();


        if ($duplicate->num_rows > 0) {

            $message = "That Supervisor ID already exists.";
            $message_class = "error";

        } else {

            /* ============================== CHECK DUPLICATE EMAIL ============================== */

            if (!empty($email)) {

                $email_check = $conn->prepare("
                    SELECT id
                    FROM supervisors
                    WHERE email = ?
                ");

                $email_check->bind_param(
                    "s",
                    $email
                );

                $email_check->execute();

                $email_result = $email_check->get_result();

                $email_check->close();

                if ($email_result->num_rows > 0) {

                    $message = "That email address is already being used.";
                    $message_class = "error";

                }

            }


            /* Continue only if there is no error */

            if ($message == "") {

                /* ============================== HASH PASSWORD ============================== */

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                /* ============================== INSERT SUPERVISOR ============================== */

                $insert = $conn->prepare("
                    INSERT INTO supervisors
                    (
                        supervisor_id,
                        fullname,
                        email,
                        phone,
                        department,
                        password,
                        status,
                        profile_photo
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, 'default.png')
                ");

                $insert->bind_param(
                    "sssssss",
                    $supervisor_id,
                    $supervisor_name,
                    $email,
                    $phone,
                    $department,
                    $hashed_password,
                    $status
                );

                if ($insert->execute()) {

                    /* ============================== ACTIVITY LOG ============================== */

                   logActivity(
    $conn,
    $admin_id,
    "Admin",
    "Create Supervisor",
    "Created supervisor: " . $fullname
);

                     $insert->close();

                    /* ============================== REDIRECT ============================== */

                    header(
                        "Location: manage_supervisors.php?success=1"
                    );

                    exit();

                } else {

                    $message =
                        "Failed to create supervisor: " .
                        $conn->error;

                    $message_class = "error";

                    $insert->close();

                }

            }

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Supervisor</title>

<link rel="stylesheet" href="styles.css">

<style>

.form-card {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);
    max-width: 800px;

}

.form-grid {
    display: grid;
    grid-template-columns:
        repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 7px;
    font-weight: bold;
}

.form-group input,
.form-group select {
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.required {
    color: red;
}

.success {
    background: #d1e7dd;
    color: #0f5132;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.error {
    background: #f8d7da;
    color: #842029;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.form-actions {
    margin-top: 25px;
    display: flex;
    gap: 10px;
}

.btn-submit {
    background: #0d47a1;
    color: white;
    border: none;
    padding: 11px 18px;
    border-radius: 6px;
    cursor: pointer;
}

.btn-cancel {
    background: #6c757d;
    color: white;
    padding: 11px 18px;
    border-radius: 6px;
    text-decoration: none;
}

@media(max-width:700px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

}
</style>
</head>

<body>

<div class="container">

<!-- ============================== SIDEBAR ============================== -->

<div class="sidebar">
    <div class="logo">
        <h2>
            <?php
            echo htmlspecialchars(
                $app['organization_name']
            );
            ?>
        </h2>

        <p>Personnel Timesheet System</p>

    </div>

    <div class="profile">
        <div class="avatar">
            <img src="<?php echo htmlspecialchars($admin_profile_photo); ?>" class="profile-small" alt="Administrator Profile Photo" onerror="this.onerror=null; this.src='uploads/admins/default.png';">
        </div>

        <h3>
            <?php
            echo htmlspecialchars($fullname);
            ?>
        </h3>

        <p>
            <?php
            echo htmlspecialchars($admin_role);
            ?>
        </p>

    </div>

    <ul>
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_admins.php">Manage Admins</a></li>
        <li class="active"><a href="manage_supervisors.php">Manage Supervisors</a></li>
        <li><a href="manage_staff.php">Manage Staff</a></li>
        <li><a href="admin_reports.php">Reports</a></li>
        <li><a href="admin_logout.php">Logout</a></li>
    </ul>

</div>

<!-- ============================== MAIN ============================== -->

<div class="main">

    <div class="topbar">

        <div>
            <h1>Add Supervisor</h1>
            <p>Create a new supervisor account.</p>
        </div>

    <div id="clock"></div>
    </div>

    <div class="form-card">
        <?php if ($message != ""): ?>

            <div class="<?php echo $message_class; ?>">
                <?php
                echo htmlspecialchars($message);
                ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="form-grid">

                <!-- SUPERVISOR ID -->
                <div class="form-group">
                    <label>Supervisor ID <span class="required">*</span></label>
                    <input type="text" name="supervisor_id" placeholder="Enter Supervisor ID" value="<?php
                        echo htmlspecialchars(
                            $_POST['supervisor_id'] ?? ''
                        );
                        ?>"
                        required
                    >

                </div>

                <!-- FULL NAME -->
                <div class="form-group">
                    <label>Full Name<span class="required">*</span></label>
                    <input type="text" name="fullname" placeholder="Enter full name" value="<?php
                        echo htmlspecialchars(
                            $_POST['fullname'] ?? ''
                        );
                        ?>"
                        required
                    >
                </div>

                <!-- EMAIL -->
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Enter email address" value="<?php
                        echo htmlspecialchars(
                            $_POST['email'] ?? ''
                        );
                        ?>"
                    >
                </div>

                <!-- PHONE -->

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="Enter phone number" value="<?php
                        echo htmlspecialchars(
                            $_POST['phone'] ?? ''
                        );
                        ?>"
                    >
                </div>

                <!-- DEPARTMENT -->
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="Enter department" value="<?php
                        echo htmlspecialchars(
                            $_POST['department'] ?? ''
                        );
                        ?>"
                    >
                </div>

                <!-- STATUS -->
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Active"
                            <?php
                            if (
                                ($_POST['status'] ?? 'Active')
                                === 'Active'
                            ) {
                                echo 'selected';
                            }
                            ?>
                        >
                            Active
                        </option>

                        <option value="Inactive"
                            <?php
                            if (
                                ($_POST['status'] ?? '')
                                === 'Inactive'
                            ) {
                                echo 'selected';
                            }
                            ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>

                <!-- PASSWORD -->
                <div class="form-group full">
                    <label>Password<span class="required">*</span></label>
                    <input type="password" name="password" placeholder="Enter supervisor password" required>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit"> ➕ Create Supervisor</button>
                <a href="manage_supervisors.php" class="btn-cancel">Cancel</a>
            </div>

        </form>

    </div>

</div>

</div>

<script>
function updateClock() {
    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();
}
updateClock();
setInterval(updateClock, 1000);
</script>

</body>
</html>