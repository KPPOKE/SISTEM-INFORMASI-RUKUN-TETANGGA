<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
checkWarga();

$user = getCurrentUser();
$warga = getWargaByUserId($user['id_user']);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filter = isset($_GET['filter']) ? cleanInput($_GET['filter']) : 'all';

$where = "WHERE id_user = ?";
$params = [$user['id_user']];
$types = "i";

if ($filter !== 'all') {
    $where .= " AND action LIKE ?";
    $filter_param = "%$filter%";
    $params[] = $filter_param;
    $types .= "s";
}

$count_query = "SELECT COUNT(*) as total FROM activity_log $where";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$query = "SELECT * FROM activity_log $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$activity_list = $stmt->get_result();

$stats_query = "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN action LIKE '%Surat%' THEN 1 END) as surat,
                COUNT(CASE WHEN action LIKE '%Iuran%' THEN 1 END) as iuran,
                COUNT(CASE WHEN action LIKE '%Komentar%' THEN 1 END) as komentar,
                COUNT(CASE WHEN action LIKE '%Keluarga%' THEN 1 END) as keluarga
                FROM activity_log WHERE id_user = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user['id_user']);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

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
    <title>Aktivitas Saya - SISFO RT</title>
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        .activity-item {
            border-left: 3px solid var(--primary-color);
            transition: all 0.3s;
        }
        .activity-item:hover {
            background-color: #f8f9fa;
            border-left-color: var(--secondary-color);
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
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
                    <a class="nav-link" href="kegiatan.php">
                        <i class="fas fa-calendar-alt me-2"></i>Kegiatan RT
                    </a>
                    <a class="nav-link" href="iuran.php">
                        <i class="fas fa-wallet me-2"></i>Iuran
                    </a>
                    <a class="nav-link" href="tetangga.php">
                        <i class="fas fa-map-marked-alt me-2"></i>Tetangga
                    </a>
                    
                    <hr class="bg-white">
                    
                    <a class="nav-link active" href="activity_log.php">
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
                <div class="mb-4">
                    <h2><i class="fas fa-history me-2"></i>Aktivitas Saya</h2>
                    <p class="text-muted mb-0">Riwayat semua aktivitas Anda di sistem</p>
                </div>
                
                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-list fa-2x mb-2"></i>
                                <h3><?php echo $stats['total']; ?></h3>
                                <p class="mb-0">Total Aktivitas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-file-alt fa-2x mb-2"></i>
                                <h3><?php echo $stats['surat']; ?></h3>
                                <p class="mb-0">Surat</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-wallet fa-2x mb-2"></i>
                                <h3><?php echo $stats['iuran']; ?></h3>
                                <p class="mb-0">Iuran</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-comments fa-2x mb-2"></i>
                                <h3><?php echo $stats['komentar']; ?></h3>
                                <p class="mb-0">Komentar</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="btn-group" role="group">
                            <a href="?filter=all" class="btn btn-outline-primary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                <i class="fas fa-list me-1"></i>Semua
                            </a>
                            <a href="?filter=Surat" class="btn btn-outline-info <?php echo $filter === 'Surat' ? 'active' : ''; ?>">
                                <i class="fas fa-file-alt me-1"></i>Surat
                            </a>
                            <a href="?filter=Iuran" class="btn btn-outline-success <?php echo $filter === 'Iuran' ? 'active' : ''; ?>">
                                <i class="fas fa-wallet me-1"></i>Iuran
                            </a>
                            <a href="?filter=Komentar" class="btn btn-outline-warning <?php echo $filter === 'Komentar' ? 'active' : ''; ?>">
                                <i class="fas fa-comments me-1"></i>Komentar
                            </a>
                            <a href="?filter=Keluarga" class="btn btn-outline-secondary <?php echo $filter === 'Keluarga' ? 'active' : ''; ?>">
                                <i class="fas fa-users me-1"></i>Keluarga
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Timeline -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Riwayat Aktivitas</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($activity_list->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while($activity = $activity_list->fetch_assoc()): ?>
                                    <div class="list-group-item activity-item p-3">
                                        <div class="d-flex align-items-start">
                                            <div class="activity-icon bg-primary text-white me-3">
                                                <?php
                                                $icon = 'fa-circle';
                                                if (strpos($activity['action'], 'Surat') !== false) {
                                                    $icon = 'fa-file-alt';
                                                } elseif (strpos($activity['action'], 'Iuran') !== false) {
                                                    $icon = 'fa-wallet';
                                                } elseif (strpos($activity['action'], 'Komentar') !== false) {
                                                    $icon = 'fa-comments';
                                                } elseif (strpos($activity['action'], 'Keluarga') !== false) {
                                                    $icon = 'fa-users';
                                                } elseif (strpos($activity['action'], 'Login') !== false) {
                                                    $icon = 'fa-sign-in-alt';
                                                }
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo e($activity['action']); ?></h6>
                                                <p class="text-muted mb-1"><?php echo e($activity['description']); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo formatTanggal($activity['created_at']); ?> - 
                                                    <?php echo date('H:i:s', strtotime($activity['created_at'])); ?>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-network-wired me-1"></i>
                                                    IP: <?php echo e($activity['ip_address']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <hr>
                                <nav>
                                    <ul class="pagination justify-content-center mb-0">
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
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada aktivitas</h5>
                                <p class="text-muted">Aktivitas Anda akan muncul di sini</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>