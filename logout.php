<?php
session_start();

if (isset($_SESSION['user_id'])) {
    require_once 'includes/db_connect.php';
    require_once 'includes/activity_log.php';
    logActivity($_SESSION['user_id'], 'Logout', 'User logout dari sistem');
}

session_destroy();

header("Location: login.php");
exit;
?>