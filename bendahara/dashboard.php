<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkBendahara();

$user = getCurrentUser();

$stats = [];

$query = "SELECT COALESCE(SUM(ip.nominal), 0) as total 
          FROM iuran_pembayaran ipb
          JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
          WHERE ipb.status = 'valid'";
$stats['total_pemasukan'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COALESCE(SUM(nominal), 0) as total FROM pengeluaran";
$stats['total_pengeluaran'] = $conn->query($query)->fetch_assoc()['total'];

$stats['saldo_kas'] = $stats['total_pemasukan'] - $stats['total_pengeluaran'];

$query = "SELECT COUNT(*) as total FROM iuran_pembayaran WHERE status = 'pending'";
$stats['pembayaran_pending'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COALESCE(SUM(ip.nominal), 0) as total 
          FROM iuran_pembayaran ipb
          JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
          WHERE ipb.status = 'valid' AND MONTH(ipb.tanggal_bayar) = MONTH(CURRENT_DATE())
          AND YEAR(ipb.tanggal_bayar) = YEAR(CURRENT_DATE())";
$stats['pemasukan_bulan_ini'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COALESCE(SUM(nominal), 0) as total FROM pengeluaran 
          WHERE MONTH(tanggal_pengeluaran) = MONTH(CURRENT_DATE())
          AND YEAR(tanggal_pengeluaran) = YEAR(CURRENT_DATE())";
$stats['pengeluaran_bulan_ini'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM iuran_posting 
          WHERE deadline >= CURRENT_DATE() OR deadline IS NULL";
$stats['iuran_aktif'] = $conn->query($query)->fetch_assoc()['total'];

$query = "SELECT ipb.*, ip.judul, ip.nominal, w.nama_lengkap, w.no_telepon 
          FROM iuran_pembayaran ipb
          JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
          JOIN warga w ON ipb.id_warga = w.id_warga
          WHERE ipb.status = 'pending'
          ORDER BY ipb.tanggal_bayar DESC
          LIMIT 5";
$pembayaran_pending = $conn->query($query);

$query = "SELECT p.*, u.username 
          FROM pengeluaran p
          JOIN users u ON p.id_user = u.id_user
          ORDER BY p.tanggal_pengeluaran DESC
          LIMIT 5";
$pengeluaran_list = $conn->query($query);

$grafik_data = [];
for ($i = 5; $i >= 0; $i--) {
    $bulan = date('Y-m', strtotime("-$i months"));
    
    $query = "SELECT COALESCE(SUM(ip.nominal), 0) as total 
              FROM iuran_pembayaran ipb
              JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
              WHERE ipb.status = 'valid' AND DATE_FORMAT(ipb.tanggal_bayar, '%Y-%m') = '$bulan'";
    $pemasukan = $conn->query($query)->fetch_assoc()['total'];
    
    $query = "SELECT COALESCE(SUM(nominal), 0) as total FROM pengeluaran 
              WHERE DATE_FORMAT(tanggal_pengeluaran, '%Y-%m') = '$bulan'";
    $pengeluaran = $conn->query($query)->fetch_assoc()['total'];
    
    $grafik_data[] = [
        'bulan' => date('M Y', strtotime($bulan . '-01')),
        'pemasukan' => $pemasukan,
        'pengeluaran' => $pengeluaran
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bendahara - SISFO RT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <i class="fas fa-wallet fa-3x"></i>
                    </div>
                    <h5>SISFO RT</h5>
                    <small>Panel Bendahara</small>
                </div>
                
                <hr class="bg-white">
                
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="iuran.php">
                        <i class="fas fa-money-bill-wave me-2"></i>Iuran
                        <?php if ($stats['pembayaran_pending'] > 0): ?>
                            <span class="badge bg-danger"><?php echo $stats['pembayaran_pending']; ?></span>
                        <?php endif; ?>
                           <a class="nav-link" href="pemasukan.php">
    <i class="fas fa-hand-holding-usd me-2"></i>Pemasukan
                    </a>
                    </a>
                    <a class="nav-link" href="pengeluaran.php">
                        <i class="fas fa-receipt me-2"></i>Pengeluaran
                    </a>
                    <a class="nav-link" href="laporan.php">
                        <i class="fas fa-file-invoice me-2"></i>Laporan
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
                        <h2>Dashboard Bendahara</h2>
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
                    <div class="col-md-4">
                        <div class="stat-card bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Total Pemasukan</h6>
                                    <h4 class="mb-0"><?php echo formatRupiah($stats['total_pemasukan']); ?></h4>
                                    <small>Bulan ini: <?php echo formatRupiah($stats['pemasukan_bulan_ini']); ?></small>
                                </div>
                                <div>
                                    <i class="fas fa-arrow-down fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stat-card bg-danger text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Total Pengeluaran</h6>
                                    <h4 class="mb-0"><?php echo formatRupiah($stats['total_pengeluaran']); ?></h4>
                                    <small>Bulan ini: <?php echo formatRupiah($stats['pengeluaran_bulan_ini']); ?></small>
                                </div>
                                <div>
                                    <i class="fas fa-arrow-up fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stat-card bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Saldo Kas</h6>
                                    <h4 class="mb-0"><?php echo formatRupiah($stats['saldo_kas']); ?></h4>
                                    <small>Per hari ini</small>
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
                    <div class="col-md-6">
                        <div class="stat-card bg-white border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Pembayaran Pending</small>
                                    <h3 class="mb-0"><?php echo $stats['pembayaran_pending']; ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-clock fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="stat-card bg-white border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Iuran Aktif</small>
                                    <h3 class="mb-0"><?php echo $stats['iuran_aktif']; ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grafik -->
                <div class="row">
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Grafik Pemasukan vs Pengeluaran (6 Bulan Terakhir)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="grafikKeuangan" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Pembayaran Pending -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pembayaran Pending</h5>
                                    <a href="iuran.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($pembayaran_pending->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while($bayar = $pembayaran_pending->fetch_assoc()): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo e($bayar['nama_lengkap']); ?></h6>
                                                        <small class="text-muted d-block"><?php echo e($bayar['judul']); ?></small>
                                                        <small class="text-success fw-bold"><?php echo formatRupiah($bayar['nominal']); ?></small>
                                                    </div>
                                                    <small class="text-muted"><?php echo formatTanggal($bayar['tanggal_bayar']); ?></small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Tidak ada pembayaran pending</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pengeluaran Terbaru -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Pengeluaran Terbaru</h5>
                                    <a href="pengeluaran.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($pengeluaran_list->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while($pengeluaran = $pengeluaran_list->fetch_assoc()): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo e($pengeluaran['keterangan']); ?></h6>
                                                        <small class="text-muted"><?php echo e($pengeluaran['kategori']); ?></small>
                                                        <br>
                                                        <small class="text-danger fw-bold"><?php echo formatRupiah($pengeluaran['nominal']); ?></small>
                                                    </div>
                                                    <small class="text-muted"><?php echo formatTanggal($pengeluaran['tanggal_pengeluaran']); ?></small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Belum ada pengeluaran</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const grafikData = <?php echo json_encode($grafik_data); ?>;
        
        const ctx = document.getElementById('grafikKeuangan').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: grafikData.map(d => d.bulan),
                datasets: [
                    {
                        label: 'Pemasukan',
                        data: grafikData.map(d => d.pemasukan),
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Pengeluaran',
                        data: grafikData.map(d => d.pengeluaran),
                        backgroundColor: 'rgba(239, 68, 68, 0.7)',
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>