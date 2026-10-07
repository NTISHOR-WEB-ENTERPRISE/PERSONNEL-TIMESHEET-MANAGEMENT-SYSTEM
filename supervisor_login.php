<?php
session_start();
include 'config.php'; // database connection ($conn)
include "activity_logger.php";
include "notification_function.php";

if(isset($_SESSION['admin_id'])){
    logActivity($conn,$_SESSION['admin_id'],"Admin","Page Visit","Visited page");
}

if(isset($_SESSION['lecturer_id'])){
    logActivity($conn,$_SESSION['lecturer_id'],"Lecturer","Page Visit","Visited page");
}

if(isset($_SESSION['supervisor_id'])){
    logActivity($conn,$_SESSION['supervisor_id'],"Supervisor","Page Visit","Visited page");
}

$error = "";

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $supervisor_id = mysqli_real_escape_string($conn, $_POST['supervisor_id']);
    $password      = $_POST['password'];

    // Check if supervisor exists
    $sql = "SELECT * FROM supervisors WHERE supervisor_id='$supervisor_id'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);

        // Verify password
        if (password_verify($password, $row['password'])) {
            // Successful login
            $_SESSION['supervisor_id'] = $row['supervisor_id'];
            $_SESSION['fullname'] = $row['fullname'];

            // --- LOG SUPERVISOR LOGIN ---
            $user_id = $row['supervisor_id'];
            $user_type = 'Supervisor';
            mysqli_query($conn, "INSERT INTO login_logs (user_id, user_type) VALUES ('$user_id','$user_type')");
            // --- END LOGGING ---

            // Redirect to supervisor dashboard
            header("Location: supervisor_dashboard.php");
            exit();
        } else {
            $error = "Incorrect Password!";
        }
    } else {
        $error = "Invalid Supervisor ID!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $app['organization_name']; ?> - Supervisor Login</title>
<link rel="stylesheet" href="styles.css">
</head>

<body class="login-page">

<div class="login-container">

<div class="login-card">
    <h1><?php echo $app['organization_name']; ?> - Supervisor Login</h1>

    <form method="POST">
        <label>Supervisor ID</label>
        <input type="text" name="supervisor_id" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit">Login</button>
    </form>
    

    <?php if ($error != ""): ?>
        <p style="color:red; text-align:center;"><?php echo $error; ?></p>
    <?php endif; ?>
</div>

</body>
</html>