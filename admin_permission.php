<?php

if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}

$admin_role = $_SESSION['admin_role'];

function superAdminOnly(){

    if($_SESSION['admin_role'] != "Super Admin"){

        die("
        <h2 style='color:red;text-align:center;margin-top:100px;'>
        Access Denied.<br>
        Only the Super Admin can access this page.
        </h2>
        ");

    }

}

?>