<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
checkWarga();

$user = getCurrentUser();
$warga = getWargaByUserId($user['id_user']);

$single_kegiatan = null;
if (isset($_GET['id'])) {
    $id_kegiatan = (int)$_GET['id'];
    $query = "SELECT k.*, u.username FROM kegiatan k 
              JOIN users u ON k.id_user = u.id_user 
              WHERE k.id_kegiatan = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_kegiatan);
    $stmt->execute();
    $single_kegiatan = $stmt->get_result()->fetch_assoc();
    
    if ($single_kegiatan) {
        $update_views = $conn->prepare("UPDATE kegiatan SET views = views + 1 WHERE id_kegiatan = ?");
        $update_views->bind_param("i", $id_kegiatan);
        $update_views->execute();
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 9;
$offset = ($page - 1) * $limit;
$filter = isset($_GET['filter']) ? cleanInput($_GET['filter']) : 'all';

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($filter === 'mendatang') {
    $where .= " AND k.tanggal_kegiatan >= NOW()";
} elseif ($filter === 'selesai') {
    $where .= " AND k.tanggal_kegiatan < NOW()";
}

$count_query = "SELECT COUNT(*) as total FROM kegiatan k $where";
if ($params) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
}
$total_pages = ceil($total_records / $limit);

if (!$single_kegiatan) {
    $query = "SELECT k.*, u.username,
              (SELECT COUNT(*) FROM komentar WHERE jenis_postingan = 'kegiatan' AND id_postingan = k.id_kegiatan) as jumlah_komentar
              FROM kegiatan k
              JOIN users u ON k.id_user = u.id_user
              $where
              ORDER BY k.tanggal_kegiatan DESC
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
    $kegiatan_list = $stmt->get_result();
}

$komentar_list = null;
if ($single_kegiatan) {
    $query = "SELECT k.*, w.nama_lengkap, w.foto_profil 
              FROM komentar k
              JOIN users u ON k.id_user = u.id_user
              JOIN warga w ON u.id_user = w.id_user
              WHERE k.jenis_postingan = 'kegiatan' AND k.id_postingan = ? AND k.id_parent IS NULL
              ORDER BY k.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_kegiatan);
    $stmt->execute();
    $komentar_list = $stmt->get_result();
}

$query = "SELECT COUNT(*) as total FROM surat WHERE id_warga = ? AND status = 'pending'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $warga['id_warga']);
$stmt->execute();
$surat_pending = $stmt->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $single_kegiatan ? e($single_kegiatan['judul']) : 'Kegiatan RT'; ?> - SISFO RT</title>
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
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .kegiatan-img {
            height: 200px;
            object-fit: cover;
            border-radius: 15px 15px 0 0;
        }
        .kegiatan-detail-img {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 15px;
        }
        .badge-status {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.9rem;
            padding: 8px 15px;
        }
        .komentar-item {
            border-left: 3px solid var(--primary-color);
            padding-left: 15px;
            margin-bottom: 20px;
        }
        .komentar-reply {
            margin-left: 40px;
            border-left: 2px solid #dee2e6;
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
                    <small>Portal Warga</small>
                </div>
                
                <hr class="bg-white">
                
                <div class="text-center mb-3">
                    <img src="../uploads/profile/<?php echo e($warga['foto_profil']); ?>" 
                         class="rounded-circle mb-2" width="80" height="80" 
                         onerror="this.src='../assets/img/default.jpg'" alt="Foto">
                    <h6><?php echo e($warga['nama_lengkap']); ?></h6>
                    <small><?php echo e($warga['no_kk']); ?></small>
                </div>
                
                <hr class="bg-white">
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="keluarga_saya.php">
                        <i class="fas fa-users me-2"></i>Keluarga Saya
                    </a>
                    <a class="nav-link" href="surat_ajuan.php">
                        <i class="fas fa-file-alt me-2"></i>Pengajuan Surat
                        <?php if ($surat_pending > 0): ?>
                            <span class="badge bg-warning"><?php echo $surat_pending; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link" href="berita.php">
                        <i class="fas fa-newspaper me-2"></i>Berita RT
                    </a>
                    <a class="nav-link active" href="kegiatan.php">
                        <i class="fas fa-calendar-alt me-2"></i>Kegiatan RT
                    </a>
                    <a class="nav-link" href="iuran.php">
                        <i class="fas fa-wallet me-2"></i>Iuran
                    </a>
                    <a class="nav-link" href="tetangga.php">
                        <i class="fas fa-map-marked-alt me-2"></i>Tetangga
                    </a>
                    
                    <hr class="bg-white">
                    
                    <a class="nav-link" href="activity_log.php">
                        <i class="fas fa-history me-2"></i>Aktivitas Saya
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
                <?php if ($single_kegiatan): ?>
                    <!-- Single Kegiatan View -->
                    <div class="mb-3">
                        <a href="kegiatan.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar Kegiatan
                        </a>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-body p-4 position-relative">
                            <?php
                            $is_mendatang = strtotime($single_kegiatan['tanggal_kegiatan']) >= time();
                            ?>
                            <span class="badge-status badge <?php echo $is_mendatang ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $is_mendatang ? 'Akan Datang' : 'Sudah Selesai'; ?>
                            </span>
                            
                            <?php if ($single_kegiatan['gambar']): ?>
                                <img src="../uploads/kegiatan/<?php echo e($single_kegiatan['gambar']); ?>" 
                                     class="kegiatan-detail-img mb-4" alt="Gambar">
                            <?php endif; ?>
                            
                            <h2><?php echo e($single_kegiatan['judul']); ?></h2>
                            
                            <div class="text-muted mb-3">
                                <i class="fas fa-calendar me-2"></i><?php echo formatTanggal($single_kegiatan['tanggal_kegiatan']); ?>
                                <i class="fas fa-clock ms-3 me-2"></i><?php echo date('H:i', strtotime($single_kegiatan['tanggal_kegiatan'])); ?> WIB
                            </div>
                            
                            <div class="text-muted mb-4">
                                <i class="fas fa-map-marker-alt me-2"></i><?php echo e($single_kegiatan['lokasi']); ?>
                                <i class="fas fa-eye ms-3 me-2"></i><?php echo $single_kegiatan['views']; ?> views
                            </div>
                            
                            <hr>
                            
                            <div style="white-space: pre-wrap; line-height: 1.8;">
                                <?php echo e($single_kegiatan['deskripsi']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Komentar Section -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-comments me-2"></i>Komentar
                                <?php if ($komentar_list): ?>
                                    <span class="badge bg-primary"><?php echo $komentar_list->num_rows; ?></span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Form Komentar -->
                            <form id="formKomentar" class="mb-4">
                                <input type="hidden" name="action" value="tambah_komentar">
                                <input type="hidden" name="jenis_postingan" value="kegiatan">
                                <input type="hidden" name="id_postingan" value="<?php echo $single_kegiatan['id_kegiatan']; ?>">
                                <div class="mb-3">
                                    <textarea name="komentar" class="form-control" rows="3" placeholder="Tulis komentar Anda..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Kirim Komentar
                                </button>
                            </form>
                            
                            <hr>
                            
                            <!-- Daftar Komentar -->
                            <div id="komentarList">
                                <?php if ($komentar_list && $komentar_list->num_rows > 0): ?>
                                    <?php while($komentar = $komentar_list->fetch_assoc()): ?>
                                        <div class="komentar-item">
                                            <div class="d-flex align-items-start mb-2">
                                                <img src="../uploads/profile/<?php echo e($komentar['foto_profil']); ?>" 
                                                     class="rounded-circle me-3" width="40" height="40" 
                                                     onerror="this.src='../assets/img/default.jpg'" alt="Foto">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo e($komentar['nama_lengkap']); ?></h6>
                                                    <small class="text-muted"><?php echo formatTanggal($komentar['created_at']); ?></small>
                                                    <p class="mt-2 mb-2"><?php echo e($komentar['komentar']); ?></p>
                                                    <div>
                                                        <button class="btn btn-sm btn-link p-0 me-3" onclick="showReplyForm(<?php echo $komentar['id_komentar']; ?>)">
                                                            <i class="fas fa-reply me-1"></i>Balas
                                                        </button>
                                                        <?php if ($komentar['id_user'] == $user['id_user']): ?>
                                                            <button class="btn btn-sm btn-link text-danger p-0" onclick="hapusKomentar(<?php echo $komentar['id_komentar']; ?>)">
                                                                <i class="fas fa-trash me-1"></i>Hapus
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Form Balas -->
                                                    <div id="replyForm<?php echo $komentar['id_komentar']; ?>" class="mt-3" style="display:none;">
                                                        <form class="formBalasKomentar">
                                                            <input type="hidden" name="action" value="tambah_komentar">
                                                            <input type="hidden" name="jenis_postingan" value="kegiatan">
                                                            <input type="hidden" name="id_postingan" value="<?php echo $single_kegiatan['id_kegiatan']; ?>">
                                                            <input type="hidden" name="id_parent" value="<?php echo $komentar['id_komentar']; ?>">
                                                            <div class="mb-2">
                                                                <textarea name="komentar" class="form-control" rows="2" placeholder="Tulis balasan..." required></textarea>
                                                            </div>
                                                            <button type="submit" class="btn btn-sm btn-primary">Kirim</button>
                                                            <button type="button" class="btn btn-sm btn-secondary" onclick="hideReplyForm(<?php echo $komentar['id_komentar']; ?>)">Batal</button>
                                                        </form>
                                                    </div>
                                                    
                                                    <!-- Balasan Komentar -->
                                                    <?php
                                                    $query_reply = "SELECT k.*, w.nama_lengkap, w.foto_profil 
                                                                   FROM komentar k
                                                                   JOIN users u ON k.id_user = u.id_user
                                                                   JOIN warga w ON u.id_user = w.id_user
                                                                   WHERE k.id_parent = ?
                                                                   ORDER BY k.created_at ASC";
                                                    $stmt_reply = $conn->prepare($query_reply);
                                                    $stmt_reply->bind_param("i", $komentar['id_komentar']);
                                                    $stmt_reply->execute();
                                                    $reply_list = $stmt_reply->get_result();
                                                    ?>
                                                    
                                                    <?php if ($reply_list->num_rows > 0): ?>
                                                        <div class="komentar-reply mt-3">
                                                            <?php while($reply = $reply_list->fetch_assoc()): ?>
                                                                <div class="d-flex align-items-start mb-3">
                                                                    <img src="../uploads/profile/<?php echo e($reply['foto_profil']); ?>" 
                                                                         class="rounded-circle me-3" width="30" height="30" 
                                                                         onerror="this.src='../assets/img/default.jpg'" alt="Foto">
                                                                    <div class="flex-grow-1">
                                                                        <h6 class="mb-0" style="font-size: 0.9rem;"><?php echo e($reply['nama_lengkap']); ?></h6>
                                                                        <small class="text-muted"><?php echo formatTanggal($reply['created_at']); ?></small>
                                                                        <p class="mt-1 mb-1" style="font-size: 0.9rem;"><?php echo e($reply['komentar']); ?></p>
                                                                        <?php if ($reply['id_user'] == $user['id_user']): ?>
                                                                            <button class="btn btn-sm btn-link text-danger p-0" onclick="hapusKomentar(<?php echo $reply['id_komentar']; ?>)">
                                                                                <i class="fas fa-trash me-1"></i>Hapus
                                                                            </button>
                                                                        <?php endif; ?>
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
                                    <p class="text-center text-muted">Belum ada komentar. Jadilah yang pertama berkomentar!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Kegiatan List View -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2><i class="fas fa-calendar-alt me-2"></i>Kegiatan RT</h2>
                            <p class="text-muted mb-0">Jadwal & informasi kegiatan RT</p>
                        </div>
                    </div>
                    
                    <!-- Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="btn-group" role="group">
                                <a href="?filter=all" class="btn btn-outline-primary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                    <i class="fas fa-list me-1"></i>Semua
                                </a>
                                <a href="?filter=mendatang" class="btn btn-outline-success <?php echo $filter === 'mendatang' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-check me-1"></i>Akan Datang
                                </a>
                                <a href="?filter=selesai" class="btn btn-outline-secondary <?php echo $filter === 'selesai' ? 'active' : ''; ?>">
                                    <i class="fas fa-check-circle me-1"></i>Sudah Selesai
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kegiatan Grid -->
                    <div class="row">
                        <?php if ($kegiatan_list->num_rows > 0): ?>
                            <?php while($kegiatan = $kegiatan_list->fetch_assoc()): ?>
                                <?php $is_mendatang = strtotime($kegiatan['tanggal_kegiatan']) >= time(); ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100 position-relative">
                                        <span class="badge-status badge <?php echo $is_mendatang ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $is_mendatang ? 'Akan Datang' : 'Selesai'; ?>
                                        </span>
                                        
                                        <?php if ($kegiatan['gambar']): ?>
                                            <img src="../uploads/kegiatan/<?php echo e($kegiatan['gambar']); ?>" 
                                                 class="kegiatan-img" alt="Gambar">
                                        <?php else: ?>
                                            <div class="kegiatan-img bg-secondary d-flex align-items-center justify-content-center">
                                                <i class="fas fa-calendar-alt fa-4x text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5><?php echo e($kegiatan['judul']); ?></h5>
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo formatTanggal($kegiatan['tanggal_kegiatan']); ?>
                                            </p>
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('H:i', strtotime($kegiatan['tanggal_kegiatan'])); ?> WIB
                                            </p>
                                            <p class="text-muted small mb-3">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo e($kegiatan['lokasi']); ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-eye me-1"></i><?php echo $kegiatan['views']; ?>
                                                    <i class="fas fa-comments ms-2 me-1"></i><?php echo $kegiatan['jumlah_komentar']; ?>
                                                </small>
                                            </div>
                                            <a href="kegiatan.php?id=<?php echo $kegiatan['id_kegiatan']; ?>" class="btn btn-primary w-100">
                                                Lihat Detail
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <i class="fas fa-calendar-alt fa-4x text-muted mb-3"></i>
                                        <h5 class="text-muted">Belum ada kegiatan</h5>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('formKomentar')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('komentar_action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    location.reload();
                } else {
                    alert(data.message || 'Gagal mengirim komentar');
                }
            });
        });
        
        document.querySelectorAll('.formBalasKomentar').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('komentar_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        location.reload();
                    } else {
                        alert(data.message || 'Gagal mengirim balasan');
                    }
                });
            });
        });
        
        function showReplyForm(id) {
            document.getElementById('replyForm' + id).style.display = 'block';
        }
        
        function hideReplyForm(id) {
            document.getElementById('replyForm' + id).style.display = 'none';
        }
        
        function hapusKomentar(id) {
            if (confirm('Apakah Anda yakin ingin menghapus komentar ini?')) {
                fetch('komentar_action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=hapus_komentar&id_komentar=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        location.reload();
                    } else {
                        alert(data.message || 'Gagal menghapus komentar');
                    }
                });
            }
        }
    </script>
</body>
</html>