<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

function checkAuth() {
    requireLogin();
}

function checkAdmin() {
    requireRole('admin');
}

function checkWarga() {
    requireRole('warga');
}

function checkBendahara() {
    requireRole('bendahara');
}

function checkRoles($roles = []) {
    requireLogin();
    
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: /sisfo_rt/403.php");
        exit;
    }
}
?>