<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkAdmin();

$user = getCurrentUser();

$stats = [];

$query = "SELECT COUNT(*) as total FROM warga";
$stats['total_penduduk'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COUNT(DISTINCT no_kk) as total FROM warga";
$stats['total_kk'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM surat WHERE status = 'pending'";
$stats['surat_pending'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM berita";
$stats['total_berita'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM kegiatan";
$stats['total_kegiatan'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COALESCE(SUM(ip.nominal), 0) as total 
          FROM iuran_pembayaran ipb
          JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
          WHERE ipb.status = 'valid'";
$stats['total_iuran_masuk'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COALESCE(SUM(nominal), 0) as total FROM pengeluaran";
$stats['total_pengeluaran'] = $conn->query($query)->fetch_assoc()['total'];

$stats['saldo_kas'] = $stats['total_iuran_masuk'] - $stats['total_pengeluaran'];

$query = "SELECT s.*, w.nama_lengkap, w.no_telepon 
          FROM surat s
          JOIN warga w ON s.id_warga = w.id_warga
          WHERE s.status = 'pending'
          ORDER BY s.created_at DESC
          LIMIT 5";
$surat_pending = $conn->query($query);

$query = "SELECT * FROM berita ORDER BY created_at DESC LIMIT 5";
$berita_list = $conn->query($query);

$query = "SELECT * FROM kegiatan ORDER BY tanggal_kegiatan DESC LIMIT 5";
$kegiatan_list = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SISFO RT</title>
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
        .stat-card {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
                    <a class="nav-link active" href="dashboard.php">
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
                        <?php if ($stats['surat_pending'] > 0): ?>
                            <span class="badge bg-danger"><?php echo $stats['surat_pending']; ?></span>
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
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Dashboard Admin</h2>
                        <p class="text-muted mb-0">Selamat datang, <?php echo e($user['username']); ?></p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">
                            <i class="fas fa-calendar me-1"></i><?php echo formatTanggal(date('Y-m-d')); ?>
                        </small>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Total Penduduk</h6>
                                    <h2 class="mb-0"><?php echo $stats['total_penduduk']; ?></h2>
                                </div>
                                <div>
                                    <i class="fas fa-users fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Total KK</h6>
                                    <h2 class="mb-0"><?php echo $stats['total_kk']; ?></h2>
                                </div>
                                <div>
                                    <i class="fas fa-home fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Surat Pending</h6>
                                    <h2 class="mb-0"><?php echo $stats['surat_pending']; ?></h2>
                                </div>
                                <div>
                                    <i class="fas fa-file-alt fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Saldo Kas</h6>
                                    <h5 class="mb-0"><?php echo formatRupiah($stats['saldo_kas']); ?></h5>
                                </div>
                                <div>
                                    <i class="fas fa-wallet fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Secondary Stats -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card bg-white border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Total Berita</small>
                                    <h4 class="mb-0"><?php echo $stats['total_berita']; ?></h4>
                                </div>
                                <div>
                                    <i class="fas fa-newspaper fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-white border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Total Kegiatan</small>
                                    <h4 class="mb-0"><?php echo $stats['total_kegiatan']; ?></h4>
                                </div>
                                <div>
                                    <i class="fas fa-calendar-alt fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-white border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Iuran Masuk</small>
                                    <h6 class="mb-0"><?php echo formatRupiah($stats['total_iuran_masuk']); ?></h6>
                                </div>
                                <div>
                                    <i class="fas fa-arrow-down fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-white border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Pengeluaran</small>
                                    <h6 class="mb-0"><?php echo formatRupiah($stats['total_pengeluaran']); ?></h6>
                                </div>
                                <div>
                                    <i class="fas fa-arrow-up fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Surat Pending -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Surat Pending</h5>
                                    <a href="surat.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($surat_pending->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while($surat = $surat_pending->fetch_assoc()): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo e($surat['nama_lengkap']); ?></h6>
                                                        <small class="text-muted"><?php echo e($surat['jenis_surat']); ?></small>
                                                    </div>
                                                    <small class="text-muted"><?php echo formatTanggal($surat['created_at']); ?></small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Tidak ada surat pending</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Berita Terbaru -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-newspaper me-2"></i>Berita Terbaru</h5>
                                    <a href="berita.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($berita_list->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while($berita = $berita_list->fetch_assoc()): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo e($berita['judul']); ?></h6>
                                                        <small class="text-muted"><i class="fas fa-eye me-1"></i><?php echo $berita['views']; ?> views</small>
                                                    </div>
                                                    <small class="text-muted"><?php echo formatTanggal($berita['created_at']); ?></small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Belum ada berita</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>