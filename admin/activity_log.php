<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkAdmin();

$user = getCurrentUser();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$filter_action = isset($_GET['action_filter']) ? cleanInput($_GET['action_filter']) : '';
$filter_date = isset($_GET['date']) ? cleanInput($_GET['date']) : '';

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $where .= " AND (u.username LIKE ? OR w.nama_lengkap LIKE ? OR al.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($filter_action) {
    $where .= " AND al.action = ?";
    $params[] = $filter_action;
    $types .= "s";
}

if ($filter_date) {
    $where .= " AND DATE(al.created_at) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

$count_query = "SELECT COUNT(*) as total 
                FROM activity_log al
                LEFT JOIN users u ON al.id_user = u.id_user
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

$query = "SELECT al.*, 
          u.username, u.role,
          w.nama_lengkap
          FROM activity_log al
          LEFT JOIN users u ON al.id_user = u.id_user
          LEFT JOIN warga w ON u.id_user = w.id_user
          $where
          ORDER BY al.created_at DESC
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
$log_list = $stmt->get_result();

$actions_query = "SELECT DISTINCT action FROM activity_log ORDER BY action ASC";
$actions_result = $conn->query($actions_query);

$stats = [];

$stats['total_log'] = $conn->query("SELECT COUNT(*) as total FROM activity_log")->fetch_assoc()['total'];

$stats['log_hari_ini'] = $conn->query("SELECT COUNT(*) as total FROM activity_log WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];

$stats['log_minggu_ini'] = $conn->query("SELECT COUNT(*) as total FROM activity_log WHERE YEARWEEK(created_at) = YEARWEEK(NOW())")->fetch_assoc()['total'];

$stats['log_bulan_ini'] = $conn->query("SELECT COUNT(*) as total FROM activity_log WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM surat WHERE status = 'pending'";
$surat_pending = $conn->query($query)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - SISFO RT</title>
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
        .log-item {
            border-left: 4px solid #dee2e6;
            padding: 15px;
            margin-bottom: 10px;
            background: #fff;
            border-radius: 8px;
        }
        .log-item.admin {
            border-left-color: #667eea;
        }
        .log-item.bendahara {
            border-left-color: #28a745;
        }
        .log-item.warga {
            border-left-color: #17a2b8;
        }
        .role-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
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
                    <a class="nav-link" href="komentar.php">
                        <i class="fas fa-comments me-2"></i>Komentar
                    </a>
                    <a class="nav-link active" href="activity_log.php">
                        <i class="fas fa-history me-2"></i>Activity Log
                    </a>
                    
                    <hr class="bg-white">
                    
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
                        <h2><i class="fas fa-history me-2"></i>Activity Log</h2>
                        <p class="text-muted mb-0">Riwayat aktivitas sistem</p>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Total Log</h6>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_log']); ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-database fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Hari Ini</h6>
                                    <h3 class="mb-0"><?php echo $stats['log_hari_ini']; ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-calendar-day fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Minggu Ini</h6>
                                    <h3 class="mb-0"><?php echo $stats['log_minggu_ini']; ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-calendar-week fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Bulan Ini</h6>
                                    <h3 class="mb-0"><?php echo $stats['log_bulan_ini']; ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-calendar-alt fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter & Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="action_filter" class="form-select">
                                    <option value="">Semua Aksi</option>
                                    <?php while($action_row = $actions_result->fetch_assoc()): ?>
                                        <option value="<?php echo e($action_row['action']); ?>" <?php echo $filter_action === $action_row['action'] ? 'selected' : ''; ?>>
                                            <?php echo e($action_row['action']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="date" class="form-control" value="<?php echo e($filter_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Cari user atau deskripsi..." value="<?php echo e($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Cari</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Activity Log List -->
                <div class="card">
                    <div class="card-body">
                        <?php if ($log_list->num_rows > 0): ?>
                            <?php while($log = $log_list->fetch_assoc()): ?>
                                <div class="log-item <?php echo e($log['role']); ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <strong><?php echo e($log['nama_lengkap'] ?: $log['username']); ?></strong>
                                                <span class="badge role-badge ms-2 <?php 
                                                    if ($log['role'] === 'admin') echo 'bg-primary';
                                                    elseif ($log['role'] === 'bendahara') echo 'bg-success';
                                                    else echo 'bg-info';
                                                ?>">
                                                    <?php echo strtoupper(e($log['role'])); ?>
                                                </span>
                                                <span class="badge bg-secondary role-badge ms-2">
                                                    <?php echo e($log['action']); ?>
                                                </span>
                                            </div>
                                            <p class="mb-1"><?php echo e($log['description']); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i><?php echo formatTanggal($log['created_at']); ?> • 
                                                <?php echo date('H:i:s', strtotime($log['created_at'])); ?> WIB
                                                <i class="fas fa-network-wired ms-3 me-1"></i><?php echo e($log['ip_address']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Tidak ada activity log</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <hr>
                            <nav>
                                <ul class="pagination justify-content-center mb-0">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_action ? '&action_filter=' . urlencode($filter_action) : ''; ?><?php echo $filter_date ? '&date=' . urlencode($filter_date) : ''; ?>">
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>