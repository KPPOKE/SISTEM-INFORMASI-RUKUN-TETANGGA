<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'tambah_komentar') {
    $jenis_postingan = cleanInput($_POST['jenis_postingan']);
    $id_postingan = intval($_POST['id_postingan']);
    $komentar = cleanInput($_POST['komentar']);
    $id_parent = isset($_POST['id_parent']) && $_POST['id_parent'] ? intval($_POST['id_parent']) : null;
    
    if (empty($komentar)) {
        echo json_encode(['status' => false, 'message' => 'Komentar tidak boleh kosong']);
        exit;
    }
    
    if (!in_array($jenis_postingan, ['berita', 'kegiatan'])) {
        echo json_encode(['status' => false, 'message' => 'Jenis postingan tidak valid']);
        exit;
    }
    
    $query = "INSERT INTO komentar (id_user, jenis_postingan, id_postingan, id_parent, komentar) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isiss", $_SESSION['user_id'], $jenis_postingan, $id_postingan, $id_parent, $komentar);
    
    if ($stmt->execute()) {
        $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $action_text = $id_parent ? 'Balas Komentar' : 'Tambah Komentar';
        $description = $id_parent ? "Membalas komentar di $jenis_postingan ID $id_postingan" : "Menambahkan komentar di $jenis_postingan ID $id_postingan";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("isss", $_SESSION['user_id'], $action_text, $description, $ip);
        $log_stmt->execute();
        
        echo json_encode(['status' => true, 'message' => $id_parent ? 'Balasan berhasil dikirim' : 'Komentar berhasil ditambahkan']);
    } else {
        echo json_encode(['status' => false, 'message' => 'Gagal mengirim komentar']);
    }
}

elseif ($action === 'hapus_komentar') {
    $id_komentar = intval($_POST['id_komentar']);
    
    $check_query = "SELECT k.id_user, k.jenis_postingan, k.id_postingan 
                    FROM komentar k WHERE k.id_komentar = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $id_komentar);
    $stmt->execute();
    $result = $stmt->get_result();
    $komentar = $result->fetch_assoc();
    
    if (!$komentar) {
        echo json_encode(['status' => false, 'message' => 'Komentar tidak ditemukan']);
        exit;
    }
    
    if ($komentar['id_user'] != $_SESSION['user_id'] && $_SESSION['role'] != 'admin') {
        echo json_encode(['status' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini']);
        exit;
    }
    
    $delete_query = "DELETE FROM komentar WHERE id_komentar = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $id_komentar);
    
    if ($stmt->execute()) {
        $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Hapus Komentar', ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Menghapus komentar ID $id_komentar di {$komentar['jenis_postingan']} ID {$komentar['id_postingan']}";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
        $log_stmt->execute();
        
        echo json_encode(['status' => true, 'message' => 'Komentar berhasil dihapus']);
    } else {
        echo json_encode(['status' => false, 'message' => 'Gagal menghapus komentar']);
    }
}

elseif ($action === 'get_komentar') {
    $jenis_postingan = cleanInput($_POST['jenis_postingan']);
    $id_postingan = intval($_POST['id_postingan']);
    
    $query = "SELECT k.*, 
              u.username, u.role, w.nama_lengkap, w.foto_profil
              FROM komentar k
              LEFT JOIN users u ON k.id_user = u.id_user
              LEFT JOIN warga w ON u.id_user = w.id_user
              WHERE k.jenis_postingan = ? AND k.id_postingan = ? AND k.id_parent IS NULL
              ORDER BY k.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $jenis_postingan, $id_postingan);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $komentar_list = [];
    while ($row = $result->fetch_assoc()) {
        $reply_query = "SELECT k.*, 
                        u.username, u.role, w.nama_lengkap, w.foto_profil
                        FROM komentar k
                        LEFT JOIN users u ON k.id_user = u.id_user
                        LEFT JOIN warga w ON u.id_user = w.id_user
                        WHERE k.id_parent = ?
                        ORDER BY k.created_at ASC";
        $reply_stmt = $conn->prepare($reply_query);
        $reply_stmt->bind_param("i", $row['id_komentar']);
        $reply_stmt->execute();
        $reply_result = $reply_stmt->get_result();
        
        $replies = [];
        while ($reply = $reply_result->fetch_assoc()) {
            $replies[] = $reply;
        }
        
        $row['replies'] = $replies;
        $komentar_list[] = $row;
    }
    
    echo json_encode(['status' => true, 'data' => $komentar_list]);
}

else {
    echo json_encode(['status' => false, 'message' => 'Invalid action']);
}
?>