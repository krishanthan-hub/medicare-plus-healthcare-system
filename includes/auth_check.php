<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: /medicare_plus/admin_portal/login.php");
    exit();
}
?>