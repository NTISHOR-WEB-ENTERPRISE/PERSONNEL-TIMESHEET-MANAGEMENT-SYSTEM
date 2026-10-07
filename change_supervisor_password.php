<?php

session_start();

include "config.php";


if(!isset($_SESSION['supervisor_id'])){

    header("Location: supervisor_login.php");
    exit();

}


$supervisor_id = $_SESSION['supervisor_id'];



$query = mysqli_query($conn,"
SELECT password
FROM supervisors
WHERE supervisor_id='$supervisor_id'
");


$supervisor = mysqli_fetch_assoc($query);



if(isset($_POST['change_password'])){


    $current_password = $_POST['current_password'];

    $new_password = $_POST['new_password'];

    $confirm_password = $_POST['confirm_password'];



  if(!password_verify(
    $current_password,
    $supervisor['password']
)){
    $error="Current password is incorrect.";
}

    elseif($new_password != $confirm_password){

        $error = "New passwords do not match.";

    }

    elseif(strlen($new_password) < 6){

        $error = "Password must be at least 6 characters.";

    }

    else{


        mysqli_query($conn,"
        $new_hash = password_hash(
    $new_password,
    PASSWORD_DEFAULT
);


UPDATE supervisors
SET password='$new_hash'
        WHERE supervisor_id='$supervisor_id'
        ");



        $success = "Password changed successfully.";

    }


}


?>


<!DOCTYPE html>
<html>

<head>

<title>Change Password</title>

<link rel="stylesheet" href="styles.css">

</head>


<body>


<div class="container">


<div class="main">


<div class="topbar">

<h1>
Change Password
</h1>

</div>



<div class="details-card">


<?php

if(isset($error)){

echo "<p style='color:red;'>$error</p>";

}


if(isset($success)){

echo "<p style='color:green;'>$success</p>";

}

?>



<form method="POST">


<div class="form-group">


<label>
Current Password
</label>


<input

type="password"

name="current_password"

required>


</div>




<div class="form-group">


<label>
New Password
</label>


<input

type="password"

name="new_password"

required>


</div>




<div class="form-group">


<label>
Confirm New Password
</label>


<input

type="password"

name="confirm_password"

required>


</div>




<button

type="submit"

name="change_password"

class="approve-btn">

🔑 Change Password

</button>



<a

href="supervisor_profile.php"

class="back-btn">

Cancel

</a>



</form>


</div>


</div>


</div>


</body>

</html>