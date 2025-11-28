<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkAdmin();

$user = getCurrentUser();

$bulan = isset($_GET['bulan']) ? cleanInput($_GET['bulan']) : date('Y-m');

$stats = [];

$query = "SELECT COALESCE(SUM(ip.nominal), 0) as total 
          FROM iuran_pembayaran ipb
          JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
          WHERE ipb.status = 'valid'";
$stats['total_iuran_masuk'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COALESCE(SUM(nominal), 0) as total FROM pemasukan";
$stats['total_pemasukan_manual'] = $conn->query($query)->fetch_assoc()['total'];

$stats['total_pemasukan'] = $stats['total_iuran_masuk'] + $stats['total_pemasukan_manual'];

$query = "SELECT COALESCE(SUM(nominal), 0) as total FROM pengeluaran";
$stats['total_pengeluaran'] = $conn->query($query)->fetch_assoc()['total'];

$stats['saldo_kas'] = $stats['total_pemasukan'] - $stats['total_pengeluaran'];

$query = "SELECT COALESCE(SUM(ip.nominal), 0) as total 
          FROM iuran_pembayaran ipb
          JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
          WHERE ipb.status = 'valid' AND DATE_FORMAT(ipb.tanggal_bayar, '%Y-%m') = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $bulan);
$stmt->execute();
$stats['iuran_bulan_ini'] = $stmt->get_result()->fetch_assoc()['total'];

$query = "SELECT COALESCE(SUM(nominal), 0) as total 
          FROM pemasukan 
          WHERE DATE_FORMAT(tanggal_pemasukan, '%Y-%m') = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $bulan);
$stmt->execute();
$stats['pemasukan_manual_bulan_ini'] = $stmt->get_result()->fetch_assoc()['total'];

$stats['total_pemasukan_bulan_ini'] = $stats['iuran_bulan_ini'] + $stats['pemasukan_manual_bulan_ini'];

$query = "SELECT COALESCE(SUM(nominal), 0) as total 
          FROM pengeluaran 
          WHERE DATE_FORMAT(tanggal_pengeluaran, '%Y-%m') = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $bulan);
$stmt->execute();
$stats['pengeluaran_bulan_ini'] = $stmt->get_result()->fetch_assoc()['total'];

$query = "SELECT ip.*, 
          COUNT(ipb.id_pembayaran) as jumlah_bayar,
          COALESCE(SUM(CASE WHEN ipb.status = 'valid' THEN ip.nominal ELSE 0 END), 0) as total_terkumpul
          FROM iuran_posting ip
          LEFT JOIN iuran_pembayaran ipb ON ip.id_iuran_posting = ipb.id_iuran_posting
          GROUP BY ip.id_iuran_posting
          ORDER BY ip.created_at DESC
          LIMIT 5";
$iuran_list = $conn->query($query);

$query = "SELECT p.*, u.username 
          FROM pengeluaran p
          JOIN users u ON p.id_user = u.id_user
          WHERE DATE_FORMAT(p.tanggal_pengeluaran, '%Y-%m') = ?
          ORDER BY p.tanggal_pengeluaran DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $bulan);
$stmt->execute();
$pengeluaran_list = $stmt->get_result();

$query = "SELECT 'iuran' as tipe, ipb.tanggal_bayar as tanggal, w.nama_lengkap as nama, ip.nominal, ipb.status
          FROM iuran_pembayaran ipb
          JOIN warga w ON ipb.id_warga = w.id_warga
          JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
          WHERE DATE_FORMAT(ipb.tanggal_bayar, '%Y-%m') = ?
          UNION ALL
          SELECT 'pemasukan' as tipe, p.tanggal_pemasukan as tanggal, p.keterangan as nama, p.nominal, 'valid' as status
          FROM pemasukan p
          WHERE DATE_FORMAT(p.tanggal_pemasukan, '%Y-%m') = ?
          UNION ALL
          SELECT 'pengeluaran' as tipe, p.tanggal_pengeluaran as tanggal, p.keterangan as nama, p.nominal, 'valid' as status
          FROM pengeluaran p
          WHERE DATE_FORMAT(p.tanggal_pengeluaran, '%Y-%m') = ?
          ORDER BY tanggal DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $bulan, $bulan, $bulan);
$stmt->execute();
$recent_transactions = $stmt->get_result();

$query = "SELECT COUNT(*) as total FROM surat WHERE status = 'pending'";
$surat_pending = $conn->query($query)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan RT - SISFO RT</title>
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .transaction-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .transaction-item:last-child {
            border-bottom: none;
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
                    <a class="nav-link active" href="keuangan.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-wallet me-2"></i>Keuangan RT</h2>
                        <p class="text-muted mb-0">Lihat laporan keuangan RT</p>
                    </div>
                    <div>
                        <a href="../bendahara/laporan.php" class="btn btn-primary">
                            <i class="fas fa-file-pdf me-2"></i>Cetak Laporan
                        </a>
                    </div>
                </div>
                
                <!-- Filter Bulan -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Filter Bulan</label>
                                <input type="month" name="bulan" class="form-control" value="<?php echo e($bulan); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Total Pemasukan</h6>
                                    <h5 class="mb-0"><?php echo formatRupiah($stats['total_pemasukan']); ?></h5>
                                </div>
                                <div>
                                    <i class="fas fa-arrow-down fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-danger text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Total Pengeluaran</h6>
                                    <h5 class="mb-0"><?php echo formatRupiah($stats['total_pengeluaran']); ?></h5>
                                </div>
                                <div>
                                    <i class="fas fa-arrow-up fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Saldo Kas RT</h6>
                                    <h5 class="mb-0"><?php echo formatRupiah($stats['saldo_kas']); ?></h5>
                                </div>
                                <div>
                                    <i class="fas fa-wallet fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Bulan Ini</h6>
                                    <h5 class="mb-0"><?php echo formatRupiah($stats['total_pemasukan_bulan_ini'] - $stats['pengeluaran_bulan_ini']); ?></h5>
                                </div>
                                <div>
                                    <i class="fas fa-calendar fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Recent Transactions -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Transaksi Terbaru</h5>
                                <small class="text-muted">Bulan: <?php echo date('F Y', strtotime($bulan . '-01')); ?></small>
                            </div>
                            <div class="card-body">
                                <?php if ($recent_transactions->num_rows > 0): ?>
                                    <div class="transaction-list">
                                        <?php while($trx = $recent_transactions->fetch_assoc()): ?>
                                            <div class="transaction-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <?php if ($trx['tipe'] === 'iuran'): ?>
                                                            <i class="fas fa-arrow-down text-success me-2"></i>
                                                            <strong><?php echo e($trx['nama']); ?></strong>
                                                            <br><small class="text-muted">Iuran - <?php echo formatTanggal($trx['tanggal']); ?></small>
                                                        <?php elseif ($trx['tipe'] === 'pemasukan'): ?>
                                                            <i class="fas fa-arrow-down text-success me-2"></i>
                                                            <strong><?php echo e($trx['nama']); ?></strong>
                                                            <br><small class="text-muted">Pemasukan Manual - <?php echo formatTanggal($trx['tanggal']); ?></small>
                                                        <?php else: ?>
                                                            <i class="fas fa-arrow-up text-danger me-2"></i>
                                                            <strong><?php echo e($trx['nama']); ?></strong>
                                                            <br><small class="text-muted">Pengeluaran - <?php echo formatTanggal($trx['tanggal']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-end">
                                                        <strong class="<?php echo ($trx['tipe'] === 'iuran' || $trx['tipe'] === 'pemasukan') ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo ($trx['tipe'] === 'iuran' || $trx['tipe'] === 'pemasukan') ? '+' : '-'; ?><?php echo formatRupiah($trx['nominal']); ?>
                                                        </strong>
                                                        <?php if ($trx['tipe'] === 'iuran'): ?>
                                                            <br>
                                                            <?php if ($trx['status'] === 'valid'): ?>
                                                                <span class="badge bg-success">Valid</span>
                                                            <?php elseif ($trx['status'] === 'pending'): ?>
                                                                <span class="badge bg-warning">Pending</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Ditolak</span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Tidak ada transaksi bulan ini</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Iuran Posting -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Iuran Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($iuran_list->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while($iuran = $iuran_list->fetch_assoc()): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo e($iuran['judul']); ?></h6>
                                                        <small class="text-muted">
                                                            Periode: <?php echo e($iuran['periode']); ?><br>
                                                            Nominal: <?php echo formatRupiah($iuran['nominal']); ?><br>
                                                            Terkumpul: <?php echo formatRupiah($iuran['total_terkumpul']); ?> (<?php echo $iuran['jumlah_bayar']; ?> pembayaran)
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Belum ada iuran</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pengeluaran Bulan Ini -->
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Pengeluaran Bulan Ini</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Keterangan</th>
                                        <th>Kategori</th>
                                        <th>Nominal</th>
                                        <th>Dicatat Oleh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($pengeluaran_list->num_rows > 0): ?>
                                        <?php $no = 1; ?>
                                        <?php while($p = $pengeluaran_list->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo formatTanggal($p['tanggal_pengeluaran']); ?></td>
                                                <td><?php echo e($p['keterangan']); ?></td>
                                                <td>
                                                    <?php if ($p['kategori']): ?>
                                                        <span class="badge bg-secondary"><?php echo e($p['kategori']); ?></span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong class="text-danger"><?php echo formatRupiah($p['nominal']); ?></strong></td>
                                                <td><?php echo e($p['username']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Tidak ada pengeluaran bulan ini</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Info -->
                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Catatan:</strong> Untuk mengelola iuran dan pengeluaran, silakan akses menu Bendahara atau hubungi bendahara RT.
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>