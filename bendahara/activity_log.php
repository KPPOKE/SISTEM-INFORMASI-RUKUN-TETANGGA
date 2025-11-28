<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkBendahara();

$user = getCurrentUser();
$user_id = $_SESSION['user_id'];

$limit = 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$action_filter = isset($_GET['action']) ? cleanInput($_GET['action']) : '';
$date_filter = isset($_GET['date']) ? cleanInput($_GET['date']) : '';

$where_clauses = ["al.id_user = $user_id"];

if ($action_filter) {
    $action_filter_safe = $conn->real_escape_string($action_filter);
    $where_clauses[] = "al.action LIKE '%$action_filter_safe%'";
}

if ($date_filter) {
    $date_filter_safe = $conn->real_escape_string($date_filter);
    $where_clauses[] = "DATE(al.created_at) = '$date_filter_safe'";
}

$where_sql = implode(' AND ', $where_clauses);

$total_query = "SELECT COUNT(*) as total FROM activity_log al WHERE $where_sql";
$total_result = $conn->query($total_query);
$total_records = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$query = "SELECT al.*, u.username 
          FROM activity_log al
          JOIN users u ON al.id_user = u.id_user
          WHERE $where_sql
          ORDER BY al.created_at DESC
          LIMIT $limit OFFSET $offset";
$activity_logs = $conn->query($query);

$actions_query = "SELECT DISTINCT action FROM activity_log WHERE id_user = $user_id ORDER BY action";
$actions_list = $conn->query($actions_query);
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
            --primary-color: #10b981;
            --secondary-color: #059669;
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
            margin-bottom: 20px;
        }
        .log-item {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }
        .log-item:hover {
            background-color: #f9fafb;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .log-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .badge-action {
            padding: 5px 10px;
            font-size: 0.75rem;
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
                        <i class="fas fa-wallet fa-3x"></i>
                    </div>
                    <h5>SISFO RT</h5>
                    <small>Panel Bendahara</small>
                </div>
                
                <hr class="bg-white">
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="iuran.php">
                        <i class="fas fa-money-bill-wave me-2"></i>Iuran
                    </a>
                    <a class="nav-link" href="pengeluaran.php">
                        <i class="fas fa-receipt me-2"></i>Pengeluaran
                    </a>
                    <a class="nav-link" href="laporan.php">
                        <i class="fas fa-file-invoice me-2"></i>Laporan
                    </a>
                    
                    <hr class="bg-white">
                    
                    <a class="nav-link active" href="activity_log.php">
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
                        <h2><i class="fas fa-history me-2"></i>Activity Log</h2>
                        <p class="text-muted mb-0">Riwayat aktivitas Anda sebagai Bendahara</p>
                    </div>
                </div>
                
                <!-- Filter -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Filter Aksi</label>
                                <select name="action" class="form-select" onchange="this.form.submit()">
                                    <option value="">Semua Aksi</option>
                                    <?php if ($actions_list && $actions_list->num_rows > 0): ?>
                                        <?php while($act = $actions_list->fetch_assoc()): ?>
                                            <option value="<?= e($act['action']) ?>" <?= $action_filter === $act['action'] ? 'selected' : '' ?>>
                                                <?= e($act['action']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Filter Tanggal</label>
                                <input type="date" name="date" class="form-control" value="<?= e($date_filter) ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <?php if ($action_filter || $date_filter): ?>
                                        <a href="activity_log.php" class="btn btn-secondary w-100">
                                            <i class="fas fa-times me-2"></i>Reset Filter
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Activity List -->
                <div class="card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Daftar Aktivitas</h5>
                            <span class="badge bg-primary"><?= $total_records ?> aktivitas</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($activity_logs && $activity_logs->num_rows > 0): ?>
                            <?php while($log = $activity_logs->fetch_assoc()): 
                                $icon = 'fa-circle';
                                $color = 'bg-secondary';
                                
                                if (stripos($log['action'], 'Tambah') !== false) {
                                    $icon = 'fa-plus';
                                    $color = 'bg-success';
                                } elseif (stripos($log['action'], 'Edit') !== false || stripos($log['action'], 'Update') !== false) {
                                    $icon = 'fa-edit';
                                    $color = 'bg-warning';
                                } elseif (stripos($log['action'], 'Hapus') !== false) {
                                    $icon = 'fa-trash';
                                    $color = 'bg-danger';
                                } elseif (stripos($log['action'], 'Verifikasi') !== false || stripos($log['action'], 'Validasi') !== false) {
                                    $icon = 'fa-check-circle';
                                    $color = 'bg-info';
                                } elseif (stripos($log['action'], 'Login') !== false) {
                                    $icon = 'fa-sign-in-alt';
                                    $color = 'bg-primary';
                                } elseif (stripos($log['action'], 'Export') !== false || stripos($log['action'], 'Cetak') !== false) {
                                    $icon = 'fa-file-download';
                                    $color = 'bg-dark';
                                }
                            ?>
                                <div class="log-item">
                                    <div class="d-flex align-items-start">
                                        <div class="log-icon <?= $color ?> text-white me-3">
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <span class="badge <?= $color ?> badge-action mb-1">
                                                        <?= e($log['action']) ?>
                                                    </span>
                                                    <p class="mb-1"><?= e($log['description'] ?? 'Tidak ada deskripsi') ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= formatTanggal($log['created_at']) ?> - 
                                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                                    </small>
                                                    <?php if (!empty($log['ip_address'])): ?>
                                                        <small class="text-muted ms-3">
                                                            <i class="fas fa-globe me-1"></i>
                                                            <?= e($log['ip_address']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada aktivitas yang tercatat</p>
                                <small class="text-muted">Aktivitas Anda sebagai bendahara akan muncul di sini</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer bg-white">
                            <nav>
                                <ul class="pagination justify-content-center mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= ($page - 1) ?><?= $action_filter ? '&action=' . urlencode($action_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?><?= $action_filter ? '&action=' . urlencode($action_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= ($page + 1) ?><?= $action_filter ? '&action=' . urlencode($action_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    Halaman <?= $page ?> dari <?= $total_pages ?> 
                                    (Total: <?= $total_records ?> aktivitas)
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>