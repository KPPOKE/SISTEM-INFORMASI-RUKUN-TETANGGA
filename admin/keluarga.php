<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkAdmin();

$user = getCurrentUser();

$query = "SELECT DISTINCT no_kk, 
          (SELECT nama_lengkap FROM warga WHERE no_kk = w.no_kk AND status_keluarga = 'Kepala Keluarga' LIMIT 1) as kepala_keluarga,
          (SELECT COUNT(*) FROM warga WHERE no_kk = w.no_kk) as jumlah_anggota
          FROM warga w
          ORDER BY no_kk";
$keluarga_list = $conn->query($query);

$query = "SELECT COUNT(*) as total FROM surat WHERE status = 'pending'";
$surat_pending = $conn->query($query)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Keluarga - SISFO RT</title>
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
            margin-bottom: 20px;
        }
        .keluarga-card {
            transition: transform 0.3s;
        }
        .keluarga-card:hover {
            transform: translateY(-5px);
        }
        .anggota-list {
            max-height: 300px;
            overflow-y: auto;
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
                    <a class="nav-link active" href="keluarga.php">
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
                        <h2><i class="fas fa-user-friends me-2"></i>Data Keluarga</h2>
                        <p class="text-muted mb-0">Kelola data kartu keluarga dan anggota keluarga</p>
                    </div>
                </div>
                
                <div class="row">
                    <?php if ($keluarga_list->num_rows > 0): ?>
                        <?php while($kk = $keluarga_list->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card keluarga-card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-id-card me-2"></i>KK: <?php echo e($kk['no_kk']); ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <small class="text-muted">Kepala Keluarga</small>
                                            <h6 class="mb-0"><?php echo e($kk['kepala_keluarga'] ?: 'Belum ada'); ?></h6>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-info">
                                                <i class="fas fa-users me-1"></i><?php echo $kk['jumlah_anggota']; ?> Anggota
                                            </span>
                                            <button class="btn btn-sm btn-primary" onclick="detailKeluarga('<?php echo e($kk['no_kk']); ?>')">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Belum ada data keluarga
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Detail Keluarga -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-users me-2"></i>Detail Keluarga</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Content loaded by AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function detailKeluarga(no_kk) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_keluarga_detail&no_kk=' + no_kk
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    let html = `
                        <div class="alert alert-info">
                            <h6 class="mb-0"><i class="fas fa-id-card me-2"></i>No. KK: <strong>${no_kk}</strong></h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>NIK</th>
                                        <th>Nama Lengkap</th>
                                        <th>JK</th>
                                        <th>TTL</th>
                                        <th>Status Keluarga</th>
                                        <th>Pekerjaan</th>
                                        <th>Username</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.data.forEach((w, index) => {
                        html += `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${w.nik}</td>
                                <td><strong>${w.nama_lengkap}</strong></td>
                                <td>${w.jenis_kelamin}</td>
                                <td>${w.tempat_lahir || '-'}, ${w.tanggal_lahir || '-'}</td>
                                <td><span class="badge ${w.status_keluarga === 'Kepala Keluarga' ? 'bg-primary' : 'bg-secondary'}">${w.status_keluarga}</span></td>
                                <td>${w.pekerjaan || '-'}</td>
                                <td>${w.username ? '<span class="badge bg-success">' + w.username + '</span>' : '<span class="badge bg-secondary">-</span>'}</td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="window.location.href='penduduk.php'">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    document.getElementById('detailContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                }
            });
        }
    </script>
</body>
</html>