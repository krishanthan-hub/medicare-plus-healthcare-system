<?php
session_start();

$user_type = $_SESSION['user_type'] ?? '';

session_destroy();

if ($user_type === 'admin') {
    header("Location: ../admin/login.php");      // admin → admin login
} elseif ($user_type === 'doctor') {
    header("Location: ../index.php");             // doctor → home page
} else {
    header("Location: ../index.php");             // patient → home page
}
exit();
?>