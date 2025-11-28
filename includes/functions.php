<?php

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $conn;
    if (!isLoggedIn()) return null;
    
    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM users WHERE id_user = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getWargaByUserId($user_id) {
    global $conn;
    $query = "SELECT * FROM warga WHERE id_user = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['role'] === $role;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /sisfo_rt/login.php");
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: /sisfo_rt/403.php");
        exit;
    }
}

function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

function formatTanggal($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $split = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

function uploadFile($file, $folder, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
    $target_dir = __DIR__ . "/../uploads/" . $folder . "/";
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['status' => false, 'message' => 'Tipe file tidak diizinkan'];
    }
    
    if ($file["size"] > 5000000) {
        return ['status' => false, 'message' => 'Ukuran file terlalu besar (max 5MB)'];
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['status' => true, 'filename' => $new_filename];
    } else {
        return ['status' => false, 'message' => 'Gagal upload file'];
    }
}

function generateUsername($nik) {
    $last_6_digits = substr($nik, -6);
    return 'warga' . $last_6_digits;
}

function createKKAccount($nik, $nama_lengkap) {
    global $conn;
    
    $username = generateUsername($nik);
    $check_query = "SELECT id_user FROM users WHERE username = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['status' => false, 'message' => 'Akun untuk NIK ini sudah ada'];
    }
    
    $password = substr($nik, -6);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $insert_query = "INSERT INTO users (username, password, role) VALUES (?, ?, 'warga')";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("ss", $username, $hashed_password);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        return [
            'status' => true, 
            'user_id' => $user_id,
            'username' => $username,
            'password' => $password
        ];
    } else {
        return ['status' => false, 'message' => 'Gagal membuat akun'];
    }
}

function isKKExists($no_kk, $exclude_id = null) {
    global $conn;
    
    $query = "SELECT w.id_warga FROM warga w 
              INNER JOIN users u ON w.id_user = u.id_user 
              WHERE w.no_kk = ? AND w.status_keluarga = 'Kepala Keluarga' AND w.id_user IS NOT NULL";
    
    if ($exclude_id) {
        $query .= " AND w.id_warga != ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $no_kk, $exclude_id);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $no_kk);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}
?>