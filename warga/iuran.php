<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
checkWarga();

$user = getCurrentUser();
$warga = getWargaByUserId($user['id_user']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'bayar') {
        $id_iuran_posting = (int)$_POST['id_iuran_posting'];
        
        $check = $conn->prepare("SELECT id_pembayaran FROM iuran_pembayaran WHERE id_iuran_posting = ? AND id_warga = ?");
        $check->bind_param("ii", $id_iuran_posting, $warga['id_warga']);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "Anda sudah mengajukan pembayaran untuk iuran ini!";
        } else {
            if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === 0) {
                $upload_result = uploadFile($_FILES['bukti_transfer'], 'iuran', ['jpg', 'jpeg', 'png']);
                
                if ($upload_result['status']) {
                    $bukti_transfer = $upload_result['filename'];
                    
                    $query = "INSERT INTO iuran_pembayaran (id_iuran_posting, id_warga, bukti_transfer, status) VALUES (?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iis", $id_iuran_posting, $warga['id_warga'], $bukti_transfer);
                    
                    if ($stmt->execute()) {
                        $success = "Bukti pembayaran berhasil diupload! Menunggu verifikasi bendahara.";
                        
                        $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Bayar Iuran', ?, ?)";
                        $log_stmt = $conn->prepare($log_query);
                        $log_desc = "Upload bukti pembayaran iuran";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                        $log_stmt->execute();
                    } else {
                        $error = "Gagal menyimpan pembayaran!";
                    }
                } else {
                    $error = $upload_result['message'];
                }
            } else {
                $error = "Harap upload bukti transfer!";
            }
        }
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$query = "SELECT ip.*, 
          CASE 
              WHEN ib.id_pembayaran IS NOT NULL AND ib.status = 'valid' THEN 'lunas'
              WHEN ib.id_pembayaran IS NOT NULL AND ib.status = 'pending' THEN 'pending'
              WHEN ib.id_pembayaran IS NOT NULL AND ib.status = 'rejected' THEN 'rejected'
              ELSE 'belum_bayar'
          END as status_bayar,
          ib.id_pembayaran,
          ib.bukti_transfer,
          ib.tanggal_bayar,
          ib.catatan,
          ib.verified_at
          FROM iuran_posting ip
          LEFT JOIN iuran_pembayaran ib ON ip.id_iuran_posting = ib.id_iuran_posting AND ib.id_warga = ?
          ORDER BY ip.created_at DESC
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $warga['id_warga'], $limit, $offset);
$stmt->execute();
$iuran_list = $stmt->get_result();

$count_query = "SELECT COUNT(*) as total FROM iuran_posting";
$total_records = $conn->query($count_query)->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$query = "SELECT 
          COUNT(CASE WHEN ib.status = 'valid' THEN 1 END) as lunas,
          COUNT(CASE WHEN ib.status = 'pending' THEN 1 END) as pending,
          COUNT(CASE WHEN ib.id_pembayaran IS NULL OR ib.status = 'rejected' THEN 1 END) as belum_bayar
          FROM iuran_posting ip
          LEFT JOIN iuran_pembayaran ib ON ip.id_iuran_posting = ib.id_iuran_posting AND ib.id_warga = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $warga['id_warga']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

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
    <title>Iuran - SISFO RT</title>
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
        .qris-img {
            max-width: 300px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 10px;
            background: white;
        }
        .tetangga-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .tetangga-item:last-child {
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
                    <small>Portal Warga</small>
                </div>
                
                <hr class="bg-white">
                
                <div class="text-center mb-3">
                    <img src="../uploads/profile/<?php echo e($warga['foto_profil']); ?>" 
                         class="rounded-circle mb-2" 
                         style="width: 80px; height: 80px; object-fit: cover; border: 3px solid rgba(255,255,255,0.3);" 
                         alt="<?php echo e($warga['nama_lengkap']); ?>">
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
                    <a class="nav-link active" href="iuran.php">
                        <i class="fas fa-wallet me-2"></i>Iuran
                    </a>
                    <a class="nav-link" href="tetangga.php">
                        <i class="fas fa-map-marked-alt me-2"></i>Tetangga
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
                <div class="mb-4">
                    <h2><i class="fas fa-wallet me-2"></i>Iuran RT</h2>
                    <p class="text-muted mb-0">Kelola pembayaran iuran Anda</p>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistik -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <h3><?php echo $stats['lunas']; ?></h3>
                                <p class="mb-0">Iuran Lunas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-3x mb-3"></i>
                                <h3><?php echo $stats['pending']; ?></h3>
                                <p class="mb-0">Menunggu Verifikasi</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                                <h3><?php echo $stats['belum_bayar']; ?></h3>
                                <p class="mb-0">Belum Dibayar</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daftar Iuran -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Iuran Saya</h5>
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
                                                <td>
                                                    <strong><?php echo e($iuran['judul']); ?></strong><br>
                                                    <small class="text-muted"><?php echo e($iuran['deskripsi']); ?></small>
                                                </td>
                                                <td><strong><?php echo formatRupiah($iuran['nominal']); ?></strong></td>
                                                <td><?php echo formatTanggal($iuran['deadline']); ?></td>
                                                <td>
                                                    <?php if ($iuran['status_bayar'] === 'lunas'): ?>
                                                        <span class="badge bg-success">Lunas</span><br>
                                                        <small class="text-muted">Verified: <?php echo formatTanggal($iuran['verified_at']); ?></small>
                                                    <?php elseif ($iuran['status_bayar'] === 'pending'): ?>
                                                        <span class="badge bg-warning">Menunggu Verifikasi</span><br>
                                                        <small class="text-muted">Bayar: <?php echo formatTanggal($iuran['tanggal_bayar']); ?></small>
                                                    <?php elseif ($iuran['status_bayar'] === 'rejected'): ?>
                                                        <span class="badge bg-danger">Ditolak</span><br>
                                                        <small class="text-danger"><?php echo e($iuran['catatan']); ?></small>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Belum Bayar</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($iuran['status_bayar'] === 'belum_bayar' || $iuran['status_bayar'] === 'rejected'): ?>
                                                        <button class="btn btn-sm btn-primary" onclick="bayarIuran(<?php echo $iuran['id_iuran_posting']; ?>)">
                                                            <i class="fas fa-money-bill me-1"></i>Bayar
                                                        </button>
                                                    <?php elseif ($iuran['status_bayar'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-info" onclick="lihatBukti('<?php echo e($iuran['bukti_transfer']); ?>')">
                                                            <i class="fas fa-eye me-1"></i>Lihat Bukti
                                                        </button>
                                                    <?php elseif ($iuran['status_bayar'] === 'lunas'): ?>
                                                        <button class="btn btn-sm btn-success" onclick="lihatBukti('<?php echo e($iuran['bukti_transfer']); ?>')">
                                                            <i class="fas fa-check me-1"></i>Bukti Bayar
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-secondary" onclick="lihatStatusTetangga(<?php echo $iuran['id_iuran_posting']; ?>)">
                                                        <i class="fas fa-users me-1"></i>Tetangga
                                                    </button>
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
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>">
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
    
    <!-- Modal Bayar Iuran -->
    <div class="modal fade" id="bayarModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bayar Iuran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body" id="bayarContent">
                        <!-- Content loaded by AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Upload Bukti Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Lihat Bukti -->
    <div class="modal fade" id="buktiModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bukti Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="buktiBayarImg" src="" class="img-fluid" alt="Bukti Bayar">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Status Tetangga -->
    <div class="modal fade" id="tetanggaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Status Pembayaran Tetangga</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="tetanggaContent">
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
        function bayarIuran(id) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_iuran_detail&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const i = data.data;
                    document.getElementById('bayarContent').innerHTML = `
                        <input type="hidden" name="action" value="bayar">
                        <input type="hidden" name="id_iuran_posting" value="${i.id_iuran_posting}">
                        
                        <div class="alert alert-info">
                            <h5>${i.judul}</h5>
                            <p class="mb-0">${i.deskripsi}</p>
                            <hr>
                            <h3 class="text-primary">${i.nominal_format}</h3>
                            <small>Deadline: ${i.deadline_format}</small>
                        </div>
                        
                        ${i.qris_image ? `
                            <div class="text-center mb-4">
                                <h6>Scan QRIS untuk pembayaran:</h6>
                                <img src="../uploads/iuran/${i.qris_image}" class="qris-img" alt="QRIS">
                            </div>
                        ` : ''}
                        
                        <div class="mb-3">
                            <label class="form-label">Upload Bukti Transfer / Screenshot <span class="text-danger">*</span></label>
                            <input type="file" name="bukti_transfer" class="form-control" accept="image/*" required>
                            <small class="text-muted">Format: JPG, PNG. Max 5MB</small>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            Pastikan bukti transfer jelas dan terbaca. Pembayaran akan diverifikasi oleh bendahara.
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('bayarModal')).show();
                }
            });
        }
        
        function lihatBukti(filename) {
            document.getElementById('buktiBayarImg').src = '../uploads/iuran/' + filename;
            new bootstrap.Modal(document.getElementById('buktiModal')).show();
        }
        
        function lihatStatusTetangga(id_iuran) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_status_tetangga_iuran&id_iuran=' + id_iuran
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    let html = '<div class="alert alert-info"><strong>' + data.iuran_info + '</strong></div>';
                    
                    if (data.tetangga && data.tetangga.length > 0) {
                        html += '<div class="row"><div class="col-md-6">';
                        html += '<h6 class="text-success"><i class="fas fa-check-circle me-2"></i>Sudah Bayar (' + data.sudah_bayar + ')</h6>';
                        html += '<div class="tetangga-list">';
                        
                        let sudahBayar = data.tetangga.filter(t => t.status === 'valid');
                        if (sudahBayar.length > 0) {
                            sudahBayar.forEach(t => {
                                html += `
                                    <div class="tetangga-item">
                                        <div>
                                            <strong>${t.nama_lengkap}</strong><br>
                                            <small class="text-muted">${t.alamat}</small>
                                        </div>
                                        <span class="badge bg-success">Lunas</span>
                                    </div>
                                `;
                            });
                        } else {
                            html += '<p class="text-muted text-center py-3">Belum ada yang bayar</p>';
                        }
                        
                        html += '</div></div><div class="col-md-6">';
                        html += '<h6 class="text-danger"><i class="fas fa-times-circle me-2"></i>Belum Bayar (' + data.belum_bayar + ')</h6>';
                        html += '<div class="tetangga-list">';
                        
                        let belumBayar = data.tetangga.filter(t => t.status !== 'valid');
                        if (belumBayar.length > 0) {
                            belumBayar.forEach(t => {
                                let badgeClass = 'secondary';
                                let badgeText = 'Belum Bayar';
                                if (t.status === 'pending') {
                                    badgeClass = 'warning';
                                    badgeText = 'Pending';
                                }
                                html += `
                                    <div class="tetangga-item">
                                        <div>
                                            <strong>${t.nama_lengkap}</strong><br>
                                            <small class="text-muted">${t.alamat}</small>
                                        </div>
                                        <span class="badge bg-${badgeClass}">${badgeText}</span>
                                    </div>
                                `;
                            });
                        }
                        
                        html += '</div></div></div>';
                        
                        html += '<hr><div class="text-center"><p class="mb-0">Total: <strong>' + data.total_warga + ' KK</strong> | Sudah Bayar: <strong class="text-success">' + data.sudah_bayar + '</strong> | Belum Bayar: <strong class="text-danger">' + data.belum_bayar + '</strong></p></div>';
                    } else {
                        html += '<p class="text-center text-muted">Tidak ada data tetangga</p>';
                    }
                    
                    document.getElementById('tetanggaContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('tetanggaModal')).show();
                }
            });
        }
    </script>
</body>
</html>