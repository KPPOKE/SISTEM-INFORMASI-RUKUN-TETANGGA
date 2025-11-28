<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/activity_log.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'add_komentar') {
    $id_postingan = intval($_POST['id_postingan']);
    $jenis_postingan = cleanInput($_POST['jenis_postingan']);
    $komentar = cleanInput($_POST['komentar']);
    $id_parent = isset($_POST['id_parent']) ? intval($_POST['id_parent']) : null;
    
    if (empty($komentar)) {
        echo json_encode(['status' => 'error', 'message' => 'Komentar tidak boleh kosong']);
        exit;
    }
    
    $query = "INSERT INTO komentar (id_user, jenis_postingan, id_postingan, id_parent, komentar) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isiss", $_SESSION['user_id'], $jenis_postingan, $id_postingan, $id_parent, $komentar);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Tambah Komentar', "Menambahkan komentar di $jenis_postingan ID $id_postingan");
        echo json_encode(['status' => 'success', 'message' => 'Komentar berhasil ditambahkan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan komentar']);
    }
}

elseif ($action === 'delete_komentar') {
    $id_komentar = intval($_POST['id_komentar']);
    
    $check_query = "SELECT id_user FROM komentar WHERE id_komentar = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $id_komentar);
    $stmt->execute();
    $result = $stmt->get_result();
    $komentar = $result->fetch_assoc();
    
    if (!$komentar) {
        echo json_encode(['status' => 'error', 'message' => 'Komentar tidak ditemukan']);
        exit;
    }
    
    if ($komentar['id_user'] != $_SESSION['user_id'] && $_SESSION['role'] != 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki izin']);
        exit;
    }
    
    $delete_query = "DELETE FROM komentar WHERE id_komentar = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $id_komentar);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Hapus Komentar', "Menghapus komentar ID $id_komentar");
        echo json_encode(['status' => 'success', 'message' => 'Komentar berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus komentar']);
    }
}

elseif ($action === 'get_berita_detail') {
    $id = intval($_POST['id']);
    
    $query = "SELECT b.*, u.username 
              FROM berita b
              JOIN users u ON b.id_user = u.id_user
              WHERE b.id_berita = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $berita = $result->fetch_assoc();
        $berita['created_at'] = formatTanggal($berita['created_at']);
        echo json_encode(['status' => true, 'data' => $berita]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Berita tidak ditemukan']);
    }
}

elseif ($action === 'get_kegiatan_detail') {
    $id = intval($_POST['id']);
    
    $query = "SELECT k.*, u.username 
              FROM kegiatan k
              JOIN users u ON k.id_user = u.id_user
              WHERE k.id_kegiatan = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $kegiatan = $result->fetch_assoc();
        $kegiatan['tanggal_kegiatan_raw'] = $kegiatan['tanggal_kegiatan'];
        $kegiatan['tanggal_kegiatan'] = date('d/m/Y H:i', strtotime($kegiatan['tanggal_kegiatan']));
        $kegiatan['created_at'] = formatTanggal($kegiatan['created_at']);
        echo json_encode(['status' => true, 'data' => $kegiatan]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Kegiatan tidak ditemukan']);
    }
}

elseif ($action === 'get_surat_detail') {
    $id = intval($_POST['id']);
    
    $query = "SELECT s.*, w.nama_lengkap, w.nik, w.no_telepon, w.alamat 
              FROM surat s
              JOIN warga w ON s.id_warga = w.id_warga
              WHERE s.id_surat = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $surat = $result->fetch_assoc();
        $surat['created_at'] = formatTanggal($surat['created_at']);
        $surat['updated_at'] = formatTanggal($surat['updated_at']);
        echo json_encode(['status' => true, 'data' => $surat]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Surat tidak ditemukan']);
    }
}

elseif ($action === 'get_warga_detail') {
    $id = intval($_POST['id']);
    
    $query = "SELECT w.*, u.username 
              FROM warga w
              LEFT JOIN users u ON w.id_user = u.id_user
              WHERE w.id_warga = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $warga = $result->fetch_assoc();
        echo json_encode(['status' => true, 'data' => $warga]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Data warga tidak ditemukan']);
    }
}

elseif ($action === 'get_keluarga_detail') {
    $no_kk = cleanInput($_POST['no_kk']);
    
    $query = "SELECT w.*, u.username 
              FROM warga w
              LEFT JOIN users u ON w.id_user = u.id_user
              WHERE w.no_kk = ?
              ORDER BY 
                CASE w.status_keluarga
                    WHEN 'Kepala Keluarga' THEN 1
                    WHEN 'Istri' THEN 2
                    WHEN 'Anak' THEN 3
                    WHEN 'Orang Tua' THEN 4
                    ELSE 5
                END, w.tanggal_lahir ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $no_kk);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $anggota = [];
    while ($row = $result->fetch_assoc()) {
        $anggota[] = $row;
    }
    
    echo json_encode(['status' => true, 'data' => $anggota]);
}

elseif ($action === 'check_nik') {
    $nik = cleanInput($_POST['nik']);
    $exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;
    
    $query = "SELECT id_warga FROM warga WHERE nik = ?";
    if ($exclude_id > 0) {
        $query .= " AND id_warga != ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $nik, $exclude_id);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $nik);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'exists', 'message' => 'NIK sudah terdaftar']);
    } else {
        echo json_encode(['status' => 'available', 'message' => 'NIK tersedia']);
    }
}

elseif ($action === 'check_kk') {
    $no_kk = cleanInput($_POST['no_kk']);
    $exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;
    
    if (isKKExists($no_kk, $exclude_id)) {
        echo json_encode(['status' => 'exists', 'message' => 'No. KK ini sudah memiliki Kepala Keluarga']);
    } else {
        echo json_encode(['status' => 'available', 'message' => 'No. KK tersedia']);
    }
}

elseif ($action === 'get_anggota_keluarga') {
    $no_kk = cleanInput($_POST['no_kk']);
    
    $query = "SELECT * FROM warga WHERE no_kk = ? ORDER BY 
              CASE status_keluarga
                WHEN 'Kepala Keluarga' THEN 1
                WHEN 'Istri' THEN 2
                WHEN 'Anak' THEN 3
                ELSE 4
              END, tanggal_lahir ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $no_kk);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $anggota = [];
    while ($row = $result->fetch_assoc()) {
        $anggota[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'data' => $anggota]);
}

elseif ($action === 'get_iuran_detail') {
    $id = intval($_POST['id']);
    
    $query = "SELECT * FROM iuran_posting WHERE id_iuran_posting = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $iuran = $result->fetch_assoc();
        $iuran['nominal_format'] = formatRupiah($iuran['nominal']);
        $iuran['deadline_format'] = formatTanggal($iuran['deadline']);
        echo json_encode(['status' => true, 'data' => $iuran]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Data tidak ditemukan']);
    }
}

elseif ($action === 'get_status_tetangga_iuran') {
    $id_iuran = intval($_POST['id_iuran']);
    
    $query = "SELECT judul, nominal, periode FROM iuran_posting WHERE id_iuran_posting = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_iuran);
    $stmt->execute();
    $iuran = $stmt->get_result()->fetch_assoc();
    
    if (!$iuran) {
        echo json_encode(['status' => false, 'message' => 'Data iuran tidak ditemukan']);
        exit;
    }
    
    $query = "SELECT w.nama_lengkap, w.alamat, 
              COALESCE(ib.status, 'belum_bayar') as status
              FROM warga w
              LEFT JOIN iuran_pembayaran ib ON w.id_warga = ib.id_warga AND ib.id_iuran_posting = ?
              WHERE w.status_keluarga = 'Kepala Keluarga'
              ORDER BY w.nama_lengkap";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_iuran);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tetangga = [];
    $sudah_bayar = 0;
    $belum_bayar = 0;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'valid') {
            $sudah_bayar++;
        } else {
            $belum_bayar++;
        }
        $tetangga[] = $row;
    }
    
    echo json_encode([
        'status' => true,
        'iuran_info' => $iuran['judul'] . ' - ' . $iuran['periode'] . ' (' . formatRupiah($iuran['nominal']) . ')',
        'tetangga' => $tetangga,
        'sudah_bayar' => $sudah_bayar,
        'belum_bayar' => $belum_bayar,
        'total_warga' => count($tetangga)
    ]);
}

elseif ($action === 'get_status_pembayaran_warga') {
    if ($_SESSION['role'] !== 'bendahara') {
        echo json_encode(['status' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $id_iuran = intval($_POST['id_iuran']);
    
    $query = "SELECT judul, nominal, periode FROM iuran_posting WHERE id_iuran_posting = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_iuran);
    $stmt->execute();
    $iuran = $stmt->get_result()->fetch_assoc();
    
    if (!$iuran) {
        echo json_encode(['status' => false, 'message' => 'Data iuran tidak ditemukan']);
        exit;
    }
    
    $query = "SELECT w.id_warga, w.nama_lengkap, w.no_kk, w.alamat, 
              COALESCE(ib.status, 'belum_bayar') as status,
              ib.verified_at
              FROM warga w
              LEFT JOIN iuran_pembayaran ib ON w.id_warga = ib.id_warga AND ib.id_iuran_posting = ?
              WHERE w.status_keluarga = 'Kepala Keluarga'
              ORDER BY w.nama_lengkap";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_iuran);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $warga = [];
    $sudah_bayar = 0;
    $belum_bayar = 0;
    $pending = 0;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'valid') {
            $sudah_bayar++;
            $row['verified_at'] = formatTanggal($row['verified_at']);
        } elseif ($row['status'] === 'pending') {
            $pending++;
        } else {
            $belum_bayar++;
        }
        $warga[] = $row;
    }
    
    echo json_encode([
        'status' => true,
        'iuran_info' => $iuran['judul'] . ' - ' . $iuran['periode'] . ' (' . formatRupiah($iuran['nominal']) . ')',
        'warga' => $warga,
        'sudah_bayar' => $sudah_bayar,
        'belum_bayar' => $belum_bayar,
        'pending' => $pending,
        'total_warga' => count($warga)
    ]);
}

elseif ($action === 'tandai_lunas_manual') {
    if ($_SESSION['role'] !== 'bendahara') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $id_iuran = intval($_POST['id_iuran']);
    $id_warga = intval($_POST['id_warga']);
    $catatan = isset($_POST['catatan']) ? cleanInput($_POST['catatan']) : 'Pembayaran tunai ke bendahara';
    
    if ($id_iuran <= 0 || $id_warga <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak valid']);
        exit;
    }
    
    $check_query = "SELECT id_pembayaran, status FROM iuran_pembayaran WHERE id_iuran_posting = ? AND id_warga = ?";
    $stmt_check = $conn->prepare($check_query);
    $stmt_check->bind_param("ii", $id_iuran, $id_warga);
    $stmt_check->execute();
    $existing = $stmt_check->get_result()->fetch_assoc();
    
    if ($existing) {
        if ($existing['status'] === 'valid') {
            echo json_encode(['status' => 'error', 'message' => 'Warga ini sudah lunas untuk iuran ini']);
            exit;
        } else {
            $query = "UPDATE iuran_pembayaran 
                      SET status = 'valid', catatan = ?, verified_at = NOW(), verified_by = ?
                      WHERE id_pembayaran = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sii", $catatan, $_SESSION['user_id'], $existing['id_pembayaran']);
        }
    } else {
        $bukti_manual = 'manual_' . time() . '.txt';
        
        $query = "INSERT INTO iuran_pembayaran (id_iuran_posting, id_warga, bukti_transfer, status, catatan, verified_at, verified_by) 
                  VALUES (?, ?, ?, 'valid', ?, NOW(), ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iissi", $id_iuran, $id_warga, $bukti_manual, $catatan, $_SESSION['user_id']);
    }
    
    if ($stmt->execute()) {
        $query_warga = "SELECT nama_lengkap FROM warga WHERE id_warga = ?";
        $stmt_warga = $conn->prepare($query_warga);
        $stmt_warga->bind_param("i", $id_warga);
        $stmt_warga->execute();
        $warga_data = $stmt_warga->get_result()->fetch_assoc();
        
        logActivity($_SESSION['user_id'], 'Tandai Lunas Manual', "Menandai lunas manual pembayaran iuran untuk warga: {$warga_data['nama_lengkap']} (ID Iuran: $id_iuran)");
        
        echo json_encode(['status' => 'success', 'message' => 'Berhasil menandai sebagai lunas']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menandai lunas: ' . $conn->error]);
    }
}

elseif ($action === 'verifikasi_iuran') {
    if ($_SESSION['role'] !== 'bendahara') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $id_pembayaran = intval($_POST['id_pembayaran']);
    $status = cleanInput($_POST['status']);
    $catatan = isset($_POST['catatan']) ? cleanInput($_POST['catatan']) : null;
    
    $query = "UPDATE iuran_pembayaran 
              SET status = ?, catatan = ?, verified_at = NOW(), verified_by = ?
              WHERE id_pembayaran = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssii", $status, $catatan, $_SESSION['user_id'], $id_pembayaran);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Verifikasi Iuran', "Verifikasi pembayaran ID $id_pembayaran dengan status $status");
        echo json_encode(['status' => 'success', 'message' => 'Pembayaran berhasil diverifikasi']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal verifikasi pembayaran']);
    }
}

elseif ($action === 'update_status_surat') {
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $id_surat = intval($_POST['id_surat']);
    $status = cleanInput($_POST['status']);
    $catatan = isset($_POST['catatan']) ? cleanInput($_POST['catatan']) : null;
    
    $query = "UPDATE surat SET status = ?, catatan = ? WHERE id_surat = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $status, $catatan, $id_surat);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Update Status Surat', "Mengubah status surat ID $id_surat menjadi $status");
        echo json_encode(['status' => 'success', 'message' => 'Status surat berhasil diupdate']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal update status surat']);
    }
}

elseif ($action === 'get_admin_stats') {
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $total_penduduk = $conn->query("SELECT COUNT(*) as total FROM warga")->fetch_assoc()['total'];
    
    $total_kk = $conn->query("SELECT COUNT(DISTINCT no_kk) as total FROM warga")->fetch_assoc()['total'];
    
    $surat_pending = $conn->query("SELECT COUNT(*) as total FROM surat WHERE status = 'pending'")->fetch_assoc()['total'];
    
    $total_berita = $conn->query("SELECT COUNT(*) as total FROM berita")->fetch_assoc()['total'];
    
    $total_kegiatan = $conn->query("SELECT COUNT(*) as total FROM kegiatan")->fetch_assoc()['total'];
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_penduduk' => $total_penduduk,
            'total_kk' => $total_kk,
            'surat_pending' => $surat_pending,
            'total_berita' => $total_berita,
            'total_kegiatan' => $total_kegiatan
        ]
    ]);
}

elseif ($action === 'get_bendahara_stats') {
    if ($_SESSION['role'] !== 'bendahara') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $total_pemasukan = $conn->query("SELECT COALESCE(SUM(ip.nominal), 0) as total 
                                      FROM iuran_pembayaran ipb
                                      JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
                                      WHERE ipb.status = 'valid'")->fetch_assoc()['total'];
    
    $total_pengeluaran = $conn->query("SELECT COALESCE(SUM(nominal), 0) as total FROM pengeluaran")->fetch_assoc()['total'];
    
    $saldo_kas = $total_pemasukan - $total_pengeluaran;
    
    $pembayaran_pending = $conn->query("SELECT COUNT(*) as total FROM iuran_pembayaran WHERE status = 'pending'")->fetch_assoc()['total'];
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_pemasukan' => $total_pemasukan,
            'total_pengeluaran' => $total_pengeluaran,
            'saldo_kas' => $saldo_kas,
            'pembayaran_pending' => $pembayaran_pending
        ]
    ]);
}

else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>