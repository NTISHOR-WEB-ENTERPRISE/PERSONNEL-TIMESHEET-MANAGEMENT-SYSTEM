<?php
$defaultPassword = "staff123"; // the plain password you want
$hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT); // bcrypt hash
echo $hashedPassword;
?>