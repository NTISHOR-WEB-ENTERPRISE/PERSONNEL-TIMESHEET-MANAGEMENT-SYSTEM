<?php

session_start();


// Remove supervisor session data

unset($_SESSION['supervisor_id']);
unset($_SESSION['fullname']);


// Destroy session

session_destroy();


// Redirect to supervisor login

header("Location: supervisor_login.php");

exit();

?>