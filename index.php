<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/middleware.php';

requireLogin();

$role = $_SESSION['role'];

switch ($role) {
    case 'admin':
        header("Location: admin/dashboard.php");
        break;
    case 'warga':
        header("Location: warga/dashboard.php");
        break;
    case 'bendahara':
        header("Location: bendahara/dashboard.php");
        break;
    default:
        header("Location: logout.php");
        break;
}
exit;
?>