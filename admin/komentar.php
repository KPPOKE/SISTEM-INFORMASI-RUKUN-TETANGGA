<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkAdmin();

$user = getCurrentUser();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'hapus') {
            $id_komentar = cleanInput($_POST['id_komentar']);
            
            $get_komentar = $conn->prepare("SELECT k.komentar, COALESCE(w.nama_lengkap, u.username) as nama
                                            FROM komentar k
                                            LEFT JOIN users u ON k.id_user = u.id_user
                                            LEFT JOIN warga w ON u.id_user = w.id_user
                                            WHERE k.id_komentar = ?");
            $get_komentar->bind_param("i", $id_komentar);
            $get_komentar->execute();
            $komentar_data = $get_komentar->get_result()->fetch_assoc();
            
            $query = "DELETE FROM komentar WHERE id_komentar = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_komentar);
            
            if ($stmt->execute()) {
                $success = "Komentar berhasil dihapus!";
                
                $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Hapus Komentar', ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_desc = "Menghapus komentar dari: {$komentar_data['nama']}";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                $log_stmt->execute();
            } else {
                $error = "Gagal menghapus komentar!";
            }
        }
        
        if ($_POST['action'] === 'balas') {
            $id_parent = intval($_POST['id_parent']);
            $komentar_text = cleanInput($_POST['komentar']);
            $jenis_postingan = cleanInput($_POST['jenis_postingan']);
            $id_postingan = intval($_POST['id_postingan']);
            
            if (empty($komentar_text)) {
                $error = "Balasan tidak boleh kosong!";
            } else {
                $query = "INSERT INTO komentar (id_user, jenis_postingan, id_postingan, id_parent, komentar) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("isiss", $user['id_user'], $jenis_postingan, $id_postingan, $id_parent, $komentar_text);
                
                if ($stmt->execute()) {
                    $success = "Balasan berhasil dikirim!";
                    
                    $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Balas Komentar', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_desc = "Membalas komentar ID: $id_parent di $jenis_postingan";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                    $log_stmt->execute();
                } else {
                    $error = "Gagal mengirim balasan!";
                }
            }
        }
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$filter_jenis = isset($_GET['jenis']) ? cleanInput($_GET['jenis']) : '';

$where = "WHERE k.id_parent IS NULL";
$params = [];
$types = "";

if ($search) {
    $where .= " AND (k.komentar LIKE ? OR w.nama_lengkap LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($filter_jenis && in_array($filter_jenis, ['berita', 'kegiatan'])) {
    $where .= " AND k.jenis_postingan = ?";
    $params[] = $filter_jenis;
    $types .= "s";
}

$count_query = "SELECT COUNT(*) as total 
                FROM komentar k
                LEFT JOIN users u ON k.id_user = u.id_user
                LEFT JOIN warga w ON u.id_user = w.id_user
                $where";
if ($params) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
}
$total_pages = ceil($total_records / $limit);

$query = "SELECT k.*, 
          u.username, u.role, w.nama_lengkap, w.foto_profil,
          CASE 
            WHEN k.jenis_postingan = 'berita' THEN (SELECT judul FROM berita WHERE id_berita = k.id_postingan)
            WHEN k.jenis_postingan = 'kegiatan' THEN (SELECT judul FROM kegiatan WHERE id_kegiatan = k.id_postingan)
          END as judul_postingan,
          (SELECT COUNT(*) FROM komentar WHERE id_parent = k.id_komentar) as jumlah_balasan
          FROM komentar k
          LEFT JOIN users u ON k.id_user = u.id_user
          LEFT JOIN warga w ON u.id_user = w.id_user
          $where
          ORDER BY k.created_at DESC
          LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (count($params) > 2) {
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$komentar_list = $stmt->get_result();

$stats = [];
$stats['total_komentar'] = $conn->query("SELECT COUNT(*) as total FROM komentar")->fetch_assoc()['total'];
$stats['komentar_berita'] = $conn->query("SELECT COUNT(*) as total FROM komentar WHERE jenis_postingan = 'berita'")->fetch_assoc()['total'];
$stats['komentar_kegiatan'] = $conn->query("SELECT COUNT(*) as total FROM komentar WHERE jenis_postingan = 'kegiatan'")->fetch_assoc()['total'];
$stats['komentar_hari_ini'] = $conn->query("SELECT COUNT(*) as total FROM komentar WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$surat_pending = $conn->query("SELECT COUNT(*) as total FROM surat WHERE status = 'pending'")->fetch_assoc()['total'];

function getReplies($conn, $parent_id) {
    $query = "SELECT k.*, u.username, u.role, w.nama_lengkap, w.foto_profil
              FROM komentar k
              LEFT JOIN users u ON k.id_user = u.id_user
              LEFT JOIN warga w ON u.id_user = w.id_user
              WHERE k.id_parent = ?
              ORDER BY k.created_at ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    return $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderasi Komentar - SISFO RT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .komentar-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
        }
        .komentar-reply {
            margin-left: 40px;
            margin-top: 10px;
            padding-left: 15px;
            border-left: 3px solid var(--primary-color);
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .user-avatar-small {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        .komentar-badge {
            font-size: 0.75rem;
        }
        .reply-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .badge-admin {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-4">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-home fa-3x"></i>
                    </div>
                    <h5>SISFO RT</h5>
                    <small>Panel Admin</small>
                </div>
                
                <hr class="bg-white">
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="penduduk.php">
                        <i class="fas fa-users me-2"></i>Data Penduduk
                    </a>
                    <a class="nav-link" href="keluarga.php">
                        <i class="fas fa-user-friends me-2"></i>Data Keluarga
                    </a>
                    <a class="nav-link" href="surat.php">
                        <i class="fas fa-file-alt me-2"></i>Surat
                        <?php if ($surat_pending > 0): ?>
                            <span class="badge bg-danger"><?php echo $surat_pending; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link" href="keuangan.php">
                        <i class="fas fa-wallet me-2"></i>Keuangan
                    </a>
                    <a class="nav-link" href="berita.php">
                        <i class="fas fa-newspaper me-2"></i>Berita
                    </a>
                    <a class="nav-link" href="kegiatan.php">
                        <i class="fas fa-calendar-alt me-2"></i>Kegiatan
                    </a>
                    <a class="nav-link active" href="komentar.php">
                        <i class="fas fa-comments me-2"></i>Komentar
                    </a>
                    
                    <hr class="bg-white">
                    <a class="nav-link" href="activity_log.php">
                        <i class="fas fa-history me-2"></i>Activity Log
                    </a>
                    <a class="nav-link" href="../profile.php">
                        <i class="fas fa-user-cog me-2"></i>Profile
                    </a>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-comments me-2"></i>Moderasi Komentar</h2>
                        <p class="text-muted mb-0">Kelola, balas, dan moderasi komentar warga</p>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Total Komentar</h6>
                                    <h2 class="mb-0"><?php echo $stats['total_komentar']; ?></h2>
                                </div>
                                <div>
                                    <i class="fas fa-comments fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Komentar Berita</h6>
                                    <h2 class="mb-0"><?php echo $stats['komentar_berita']; ?></h2>
                                </div>
                                <div>
                                    <i class="fas fa-newspaper fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Komentar Kegiatan</h6>
                                    <h2 class="mb-0"><?php echo $stats['komentar_kegiatan']; ?></h2>
                                </div>
                                <div>
                                    <i class="fas fa-calendar-alt fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Hari Ini</h6>
                                    <h2 class="mb-0"><?php echo $stats['komentar_hari_ini']; ?></h2>
                                </div>
                                <div>
                                    <i class="fas fa-calendar-day fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter & Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select name="jenis" class="form-select">
                                    <option value="">Semua Jenis</option>
                                    <option value="berita" <?php echo $filter_jenis === 'berita' ? 'selected' : ''; ?>>Berita</option>
                                    <option value="kegiatan" <?php echo $filter_jenis === 'kegiatan' ? 'selected' : ''; ?>>Kegiatan</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="search" class="form-control" placeholder="Cari komentar atau nama..." value="<?php echo e($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Cari</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Komentar List -->
                <div class="card">
                    <div class="card-body">
                        <?php if ($komentar_list->num_rows > 0): ?>
                            <?php while($komentar = $komentar_list->fetch_assoc()): ?>
                                <div class="komentar-item">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <?php if ($komentar['foto_profil'] && $komentar['foto_profil'] !== 'default.jpg'): ?>
                                                <img src="../uploads/profile/<?php echo e($komentar['foto_profil']); ?>" class="user-avatar" alt="Avatar">
                                            <?php else: ?>
                                                <div class="user-avatar bg-secondary d-flex align-items-center justify-content-center text-white">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <strong><?php echo e($komentar['nama_lengkap'] ?: $komentar['username']); ?></strong>
                                                    <?php if ($komentar['role'] === 'admin'): ?>
                                                        <span class="badge badge-admin komentar-badge ms-2">
                                                            <i class="fas fa-shield-alt me-1"></i>Admin
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="badge komentar-badge ms-2 <?php echo $komentar['jenis_postingan'] === 'berita' ? 'bg-info' : 'bg-success'; ?>">
                                                        <i class="fas fa-<?php echo $komentar['jenis_postingan'] === 'berita' ? 'newspaper' : 'calendar-alt'; ?> me-1"></i>
                                                        <?php echo ucfirst($komentar['jenis_postingan']); ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo formatTanggal($komentar['created_at']); ?> • 
                                                        <?php echo date('H:i', strtotime($komentar['created_at'])); ?> WIB
                                                    </small>
                                                </div>
                                                <div>
                                                    <button class="btn btn-sm btn-primary me-2" onclick="toggleReplyForm(<?php echo $komentar['id_komentar']; ?>)">
                                                        <i class="fas fa-reply"></i> Balas
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="hapusKomentar(<?php echo $komentar['id_komentar']; ?>, '<?php echo e($komentar['nama_lengkap'] ?: $komentar['username']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <span class="text-muted small">
                                                    <i class="fas fa-<?php echo $komentar['jenis_postingan'] === 'berita' ? 'newspaper' : 'calendar-alt'; ?> me-1"></i>
                                                    <?php echo e($komentar['judul_postingan']); ?>
                                                </span>
                                            </div>
                                            
                                            <p class="mb-2"><?php echo nl2br(e($komentar['komentar'])); ?></p>
                                            
                                            <?php if ($komentar['jumlah_balasan'] > 0): ?>
                                                <small class="text-primary">
                                                    <i class="fas fa-reply me-1"></i><?php echo $komentar['jumlah_balasan']; ?> balasan
                                                </small>
                                            <?php endif; ?>
                                            
                                            <!-- Reply Form -->
                                            <div id="replyForm<?php echo $komentar['id_komentar']; ?>" class="reply-form" style="display:none;">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="balas">
                                                    <input type="hidden" name="id_parent" value="<?php echo $komentar['id_komentar']; ?>">
                                                    <input type="hidden" name="jenis_postingan" value="<?php echo $komentar['jenis_postingan']; ?>">
                                                    <input type="hidden" name="id_postingan" value="<?php echo $komentar['id_postingan']; ?>">
                                                    <div class="mb-2">
                                                        <textarea name="komentar" class="form-control" rows="2" placeholder="Tulis balasan Anda sebagai admin..." required></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-paper-plane me-1"></i>Kirim Balasan
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleReplyForm(<?php echo $komentar['id_komentar']; ?>)">
                                                        Batal
                                                    </button>
                                                </form>
                                            </div>
                                            
                                            <!-- Nested Replies -->
                                            <?php 
                                            $replies = getReplies($conn, $komentar['id_komentar']);
                                            if ($replies->num_rows > 0): 
                                            ?>
                                                <div class="komentar-reply">
                                                    <?php while($reply = $replies->fetch_assoc()): ?>
                                                        <div class="d-flex mb-3">
                                                            <div class="me-2">
                                                                <?php if ($reply['foto_profil'] && $reply['foto_profil'] !== 'default.jpg'): ?>
                                                                    <img src="../uploads/profile/<?php echo e($reply['foto_profil']); ?>" class="user-avatar-small" alt="Avatar">
                                                                <?php else: ?>
                                                                    <div class="user-avatar-small bg-secondary d-flex align-items-center justify-content-center text-white">
                                                                        <i class="fas fa-user"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <div>
                                                                        <strong class="small"><?php echo e($reply['nama_lengkap'] ?: $reply['username']); ?></strong>
                                                                        <?php if ($reply['role'] === 'admin'): ?>
                                                                            <span class="badge badge-admin" style="font-size: 0.65rem;">
                                                                                <i class="fas fa-shield-alt me-1"></i>Admin
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            <?php echo formatTanggal($reply['created_at']); ?> • <?php echo date('H:i', strtotime($reply['created_at'])); ?>
                                                                        </small>
                                                                    </div>
                                                                    <button class="btn btn-sm btn-outline-danger" onclick="hapusKomentar(<?php echo $reply['id_komentar']; ?>, '<?php echo e($reply['nama_lengkap'] ?: $reply['username']); ?>')">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                                <p class="mt-1 mb-0 small"><?php echo nl2br(e($reply['komentar'])); ?></p>
                                                            </div>
                                                        </div>
                                                    <?php endwhile; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Tidak ada komentar</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <hr>
                            <nav>
                                <ul class="pagination justify-content-center mb-0">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_jenis ? '&jenis=' . urlencode($filter_jenis) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Hapus -->
    <div class="modal fade" id="hapusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id_komentar" id="hapus_id_komentar">
                        <p>Apakah Anda yakin ingin menghapus komentar dari:</p>
                        <h5 class="text-danger" id="hapus_nama"></h5>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Perhatian:</strong> Menghapus komentar induk akan menghapus semua balasan!
                        </div>
                        <p class="text-muted mb-0">Data yang dihapus tidak dapat dikembalikan!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function hapusKomentar(id, nama) {
            document.getElementById('hapus_id_komentar').value = id;
            document.getElementById('hapus_nama').textContent = nama;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        }
        
        function toggleReplyForm(id) {
            const form = document.getElementById('replyForm' + id);
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</body>
</html>