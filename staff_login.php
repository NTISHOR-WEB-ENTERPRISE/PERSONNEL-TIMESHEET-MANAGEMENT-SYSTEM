<?php
session_start();
include "config.php";
include "activity_logger.php";
include "notification_function.php";

$error = "";

/* ================= PAGE VISIT LOGGING ================= */

if(isset($_SESSION['staff_id'])){

    logActivity(
        $conn,
        $_SESSION['staff_id'],
        "Staff",
        "Page Visit",
        "Visited Staff Login Page"
    );

}

/* ================= STAFF LOGIN ================= */

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $staff_id = trim($_POST['staff_id']);
    $password = trim($_POST['password']);

    if(empty($staff_id) || empty($password)){

        $error = "All fields are required.";

    }else{

        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM staff WHERE staff_id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $staff_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if(mysqli_num_rows($result)==1){

            $row=mysqli_fetch_assoc($result);

            // Password verification

            if(password_verify($password,$row['password'])){

                session_regenerate_id(true);

                $_SESSION['staff_id']=$row['staff_id'];
                $_SESSION['fullname']=$row['fullname'];
                $_SESSION['role']=$row['role'];
                $_SESSION['department']=$row['department'];


                $user_id=$row['staff_id'];
                $user_type="Staff";

                $log_stmt=mysqli_prepare(
                    $conn,
                    "INSERT INTO login_logs(user_id,user_type)
                     VALUES(?,?)"
                );

                mysqli_stmt_bind_param(
                    $log_stmt,
                    "ss",
                    $user_id,
                    $user_type
                );

                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);

                // Activity log
                logActivity(
                    $conn,
                    $row['staff_id'],
                    "Staff",
                    "Login",
                    "Staff logged into the system"
                );

                header("Location: staff_dashboard.php");

                exit();

            }else{
                $error="Incorrect Password!";
            }

        }else{
            $error="Invalid Staff ID!";
        }
        mysqli_stmt_close($stmt);

    }

}
?>

<!DOCTYPE html>
<html>
<head>
<title><?php echo $app['organization_name']; ?> - Staff Login</title>
<link rel="stylesheet" href="styles.css">
</head>

<body class="login-page">
<div class="login-container">
<div class="login-card">


<h2>
<h1><?php echo $app['organization_name']; ?> - Staff Login</h1>
<br>

<form method="POST">
<label>Staff ID</label>

<input 
type="text"
name="staff_id"
required>

<label>Password</label>

<input 
type="password"
name="password"
required>
<button type="submit">Login</button>
</form>

<?php if(!empty($error)){ ?>

<p style="color:red;text-align:center;"><?php echo htmlspecialchars($error); ?></p>

<?php } ?>
</div>

</div>

</div>
</body>
</html>