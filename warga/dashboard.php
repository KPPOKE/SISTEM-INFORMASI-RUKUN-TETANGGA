<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
checkWarga();

$user = getCurrentUser();
$warga = getWargaByUserId($user['id_user']);

$query = "SELECT COUNT(*) as total FROM warga WHERE no_kk = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $warga['no_kk']);
$stmt->execute();
$total_keluarga = $stmt->get_result()->fetch_assoc()['total'];

$query = "SELECT * FROM berita ORDER BY created_at DESC LIMIT 3";
$berita_terbaru = $conn->query($query);

$query = "SELECT * FROM kegiatan WHERE tanggal_kegiatan >= NOW() ORDER BY tanggal_kegiatan ASC LIMIT 3";
$kegiatan_mendatang = $conn->query($query);

$query = "SELECT ip.*, 
          CASE 
              WHEN ib.id_pembayaran IS NOT NULL AND ib.status = 'valid' THEN 'lunas'
              WHEN ib.id_pembayaran IS NOT NULL AND ib.status = 'pending' THEN 'pending'
              WHEN ib.id_pembayaran IS NOT NULL AND ib.status = 'rejected' THEN 'rejected'
              ELSE 'belum_bayar'
          END as status_bayar,
          ib.tanggal_bayar
          FROM iuran_posting ip
          LEFT JOIN iuran_pembayaran ib ON ip.id_iuran_posting = ib.id_iuran_posting AND ib.id_warga = ?
          ORDER BY ip.created_at DESC
          LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $warga['id_warga']);
$stmt->execute();
$iuran_list = $stmt->get_result();

$query = "SELECT COUNT(*) as total FROM surat WHERE id_warga = ? AND status = 'pending'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $warga['id_warga']);
$stmt->execute();
$surat_pending = $stmt->get_result()->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM warga WHERE status_keluarga = 'Kepala Keluarga' AND id_warga != ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $warga['id_warga']);
$stmt->execute();
$total_tetangga = $stmt->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Warga - SISFO RT</title>
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
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        .berita-card img {
            height: 200px;
            object-fit: cover;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
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
                    <a class="nav-link active" href="dashboard.php">
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
                <div class="mb-4">
                    <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard Warga</h2>
                    <p class="text-muted mb-0">Selamat datang, <?php echo e($warga['nama_lengkap']); ?>!</p>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-3x mb-3"></i>
                                <h3><?php echo $total_keluarga; ?></h3>
                                <p class="mb-0">Anggota Keluarga</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-file-alt fa-3x mb-3"></i>
                                <h3><?php echo $surat_pending; ?></h3>
                                <p class="mb-0">Surat Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-newspaper fa-3x mb-3"></i>
                                <h3><?php echo $berita_terbaru->num_rows; ?></h3>
                                <p class="mb-0">Berita Terbaru</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-home fa-3x mb-3"></i>
                                <h3><?php echo $total_tetangga; ?></h3>
                                <p class="mb-0">Tetangga</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Berita Terbaru -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-newspaper me-2"></i>Berita Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($berita_terbaru->num_rows > 0): ?>
                                    <?php while($berita = $berita_terbaru->fetch_assoc()): ?>
                                        <div class="mb-3 pb-3 border-bottom">
                                            <h6><?php echo e($berita['judul']); ?></h6>
                                            <p class="text-muted small mb-2">
                                                <?php echo e(substr($berita['konten'], 0, 100)); ?>...
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo formatTanggal($berita['created_at']); ?>
                                                </small>
                                                <a href="berita.php?id=<?php echo $berita['id_berita']; ?>" class="btn btn-sm btn-primary">
                                                    Baca Selengkapnya
                                                </a>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                    <a href="berita.php" class="btn btn-outline-primary w-100">Lihat Semua Berita</a>
                                <?php else: ?>
                                    <p class="text-center text-muted mb-0">Belum ada berita</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kegiatan Mendatang -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Kegiatan Mendatang</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($kegiatan_mendatang->num_rows > 0): ?>
                                    <?php while($kegiatan = $kegiatan_mendatang->fetch_assoc()): ?>
                                        <div class="mb-3 pb-3 border-bottom">
                                            <h6><?php echo e($kegiatan['judul']); ?></h6>
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo e($kegiatan['lokasi']); ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo formatTanggal($kegiatan['tanggal_kegiatan']); ?>
                                                </small>
                                                <a href="kegiatan.php?id=<?php echo $kegiatan['id_kegiatan']; ?>" class="btn btn-sm btn-primary">
                                                    Detail
                                                </a>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                    <a href="kegiatan.php" class="btn btn-outline-primary w-100">Lihat Semua Kegiatan</a>
                                <?php else: ?>
                                    <p class="text-center text-muted mb-0">Belum ada kegiatan mendatang</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Iuran -->
                    <div class="col-lg-12 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Status Iuran Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Periode</th>
                                                <th>Keterangan</th>
                                                <th>Nominal</th>
                                                <th>Deadline</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($iuran_list->num_rows > 0): ?>
                                                <?php while($iuran = $iuran_list->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo e($iuran['periode']); ?></td>
                                                        <td><?php echo e($iuran['judul']); ?></td>
                                                        <td><?php echo formatRupiah($iuran['nominal']); ?></td>
                                                        <td><?php echo formatTanggal($iuran['deadline']); ?></td>
                                                        <td>
                                                            <?php if ($iuran['status_bayar'] === 'lunas'): ?>
                                                                <span class="badge bg-success">Lunas</span>
                                                            <?php elseif ($iuran['status_bayar'] === 'pending'): ?>
                                                                <span class="badge bg-warning">Menunggu Verifikasi</span>
                                                            <?php elseif ($iuran['status_bayar'] === 'rejected'): ?>
                                                                <span class="badge bg-danger">Ditolak</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Belum Bayar</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($iuran['status_bayar'] === 'belum_bayar' || $iuran['status_bayar'] === 'rejected'): ?>
                                                                <a href="iuran.php?id=<?php echo $iuran['id_iuran_posting']; ?>" class="btn btn-sm btn-primary">
                                                                    Bayar Sekarang
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="iuran.php" class="btn btn-sm btn-info">
                                                                    Lihat Detail
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">Belum ada data iuran</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="iuran.php" class="btn btn-outline-primary w-100">Lihat Semua Iuran</a>
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