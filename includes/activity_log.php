<?php
function logActivity($user_id, $action, $description = '') {
    global $conn;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
    return $stmt->execute();
}

function getActivityLogByUser($user_id, $limit = 10) {
    global $conn;
    $query = "SELECT al.*, u.username 
              FROM activity_log al
              LEFT JOIN users u ON al.id_user = u.id_user
              WHERE al.id_user = ?
              ORDER BY al.created_at DESC
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

function getAllActivityLog($limit = 50) {
    global $conn;
    $query = "SELECT al.*, u.username 
              FROM activity_log al
              LEFT JOIN users u ON al.id_user = u.id_user
              ORDER BY al.created_at DESC
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

function getActivityLogByAction($action, $limit = 50) {
    global $conn;
    $query = "SELECT al.*, u.username 
              FROM activity_log al
              LEFT JOIN users u ON al.id_user = u.id_user
              WHERE al.action = ?
              ORDER BY al.created_at DESC
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $action, $limit);
    $stmt->execute();
    return $stmt->get_result();
}
?>