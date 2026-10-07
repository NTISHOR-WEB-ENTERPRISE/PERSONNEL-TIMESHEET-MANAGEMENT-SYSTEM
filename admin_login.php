<?php
session_start();
include "config.php";
include "activity_logger.php";
include "notification_function.php";

$error = "";

if(isset($_POST['login'])){

    $admin_id = mysqli_real_escape_string($conn,$_POST['admin_id']);
    $password = $_POST['password'];

    $sql = mysqli_query($conn,"
    SELECT *
    FROM admins
    WHERE admin_id='$admin_id'
    LIMIT 1
    ");

    if(mysqli_num_rows($sql)>0){

        $admin = mysqli_fetch_assoc($sql);

        if($admin['status']=="Inactive"){

            $error = "Your account has been deactivated.";

        }else{

            if(password_verify($password,$admin['password'])){

                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['fullname'] = $admin['fullname'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['profile_photo'] = $admin['profile_photo'];

                mysqli_query($conn,"
                UPDATE admins
                SET last_login=NOW()
                WHERE admin_id='".$admin['admin_id']."'
                ");

                logLogin(
                    $conn,
                    $admin['admin_id'],
                    "Admin"
                );

                header("Location: admin_dashboard.php");
                exit();

            }else{

                $error = "Incorrect password.";

            }

        }

    }else{

        $error = "Admin ID not found.";

    }

}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Login</title>
<link rel="stylesheet" href="styles.css">
</head>

<body class="login-page">
<div class="login-container">
<div class="login-card">

<h1><?php echo $app['organization_name']; ?> - Admin Login</h1>
<?php
if($error!=""){
echo "<div class='error'>$error</div>";
}
?>

<form method="POST">
<label>Admin ID</label>
<input type="text" name="admin_id" required>
<label>Password</label>
<input type="password" name="password" required>
<button type="submit" name="login">Login</button>
</form>

</div>

</div>

</body>
</html>