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
    if ($_POST['action'] === 'ajukan') {
        $jenis_surat = cleanInput($_POST['jenis_surat']);
        $keperluan = cleanInput($_POST['keperluan']);
        $file_pendukung = null;
        
        if (isset($_FILES['file_pendukung']) && $_FILES['file_pendukung']['error'] === 0) {
            $upload_result = uploadFile($_FILES['file_pendukung'], 'surat', ['jpg', 'jpeg', 'png', 'pdf']);
            if ($upload_result['status']) {
                $file_pendukung = $upload_result['filename'];
            } else {
                $error = $upload_result['message'];
            }
        }
        
        if (!$error) {
            $query = "INSERT INTO surat (id_warga, jenis_surat, keperluan, file_pendukung, status) VALUES (?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isss", $warga['id_warga'], $jenis_surat, $keperluan, $file_pendukung);
            
            if ($stmt->execute()) {
                $success = "Pengajuan surat berhasil! Menunggu verifikasi admin.";
                
                $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Ajukan Surat', ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_desc = "Mengajukan surat: $jenis_surat";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                $log_stmt->execute();
            } else {
                $error = "Gagal mengajukan surat!";
            }
        }
    } elseif ($_POST['action'] === 'batal') {
        $id_surat = cleanInput($_POST['id_surat']);
        
        $check = $conn->prepare("SELECT id_surat, jenis_surat FROM surat WHERE id_surat = ? AND id_warga = ? AND status = 'pending'");
        $check->bind_param("ii", $id_surat, $warga['id_warga']);
        $check->execute();
        $surat_data = $check->get_result()->fetch_assoc();
        
        if (!$surat_data) {
            $error = "Surat tidak ditemukan atau tidak dapat dibatalkan!";
        } else {
            $query = "DELETE FROM surat WHERE id_surat = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_surat);
            
            if ($stmt->execute()) {
                $success = "Pengajuan surat berhasil dibatalkan!";
                
                $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Batalkan Surat', ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_desc = "Membatalkan pengajuan surat: {$surat_data['jenis_surat']}";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                $log_stmt->execute();
            } else {
                $error = "Gagal membatalkan pengajuan!";
            }
        }
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$filter = isset($_GET['filter']) ? cleanInput($_GET['filter']) : 'all';

$where = "WHERE id_warga = ?";
$params = [$warga['id_warga']];
$types = "i";

if ($filter !== 'all') {
    $where .= " AND status = ?";
    $params[] = $filter;
    $types .= "s";
}

$count_query = "SELECT COUNT(*) as total FROM surat $where";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$query = "SELECT * FROM surat $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$surat_list = $stmt->get_result();

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
    <title>Pengajuan Surat - SISFO RT</title>
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
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline-item {
            position: relative;
            padding-left: 40px;
            margin-bottom: 30px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 24px;
            height: 100%;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item:last-child::before {
            display: none;
        }
        .timeline-icon {
            position: absolute;
            left: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            border: 3px solid;
        }
        .timeline-icon.pending {
            border-color: #ffc107;
        }
        .timeline-icon.approved {
            border-color: #28a745;
        }
        .timeline-icon.rejected {
            border-color: #dc3545;
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
                    <a class="nav-link active" href="surat_ajuan.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-file-alt me-2"></i>Pengajuan Surat</h2>
                        <p class="text-muted mb-0">Ajukan surat online & tracking status</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajukanModal">
                        <i class="fas fa-plus me-2"></i>Ajukan Surat Baru
                    </button>
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
                
                <!-- Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="btn-group" role="group">
                            <a href="?filter=all" class="btn btn-outline-primary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                <i class="fas fa-list me-1"></i>Semua
                            </a>
                            <a href="?filter=pending" class="btn btn-outline-warning <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                                <i class="fas fa-clock me-1"></i>Pending
                            </a>
                            <a href="?filter=approved" class="btn btn-outline-success <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                                <i class="fas fa-check me-1"></i>Disetujui
                            </a>
                            <a href="?filter=rejected" class="btn btn-outline-danger <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                                <i class="fas fa-times me-1"></i>Ditolak
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Daftar Surat -->
                <?php if ($surat_list->num_rows > 0): ?>
                    <?php while($surat = $surat_list->fetch_assoc()): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5><?php echo e($surat['jenis_surat']); ?></h5>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-calendar me-2"></i>
                                            Diajukan: <?php echo formatTanggal($surat['created_at']); ?>
                                        </p>
                                        <p class="mb-2"><strong>Keperluan:</strong><br><?php echo e($surat['keperluan']); ?></p>
                                        
                                        <?php if ($surat['status'] === 'approved' && $surat['file_hasil']): ?>
                                            <a href="../uploads/surat/<?php echo e($surat['file_hasil']); ?>" class="btn btn-sm btn-success" target="_blank">
                                                <i class="fas fa-download me-1"></i>Download Surat
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($surat['status'] === 'rejected' && $surat['catatan']): ?>
                                            <div class="alert alert-danger mt-2 mb-0">
                                                <strong>Alasan Penolakan:</strong><br><?php echo e($surat['catatan']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php if ($surat['status'] === 'pending'): ?>
                                            <span class="badge bg-warning mb-2" style="font-size: 1rem;">
                                                <i class="fas fa-clock me-1"></i>Menunggu Verifikasi
                                            </span>
                                        <?php elseif ($surat['status'] === 'approved'): ?>
                                            <span class="badge bg-success mb-2" style="font-size: 1rem;">
                                                <i class="fas fa-check me-1"></i>Disetujui
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger mb-2" style="font-size: 1rem;">
                                                <i class="fas fa-times me-1"></i>Ditolak
                                            </span>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-info w-100 mb-2" onclick="detailSurat(<?php echo $surat['id_surat']; ?>)">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </button>
                                            <?php if ($surat['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-danger w-100" onclick="batalSurat(<?php echo $surat['id_surat']; ?>, '<?php echo e($surat['jenis_surat']); ?>')">
                                                    <i class="fas fa-times me-1"></i>Batalkan
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    
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
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Belum ada pengajuan surat</h5>
                            <p class="text-muted mb-3">Klik tombol "Ajukan Surat Baru" untuk membuat pengajuan</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajukanModal">
                                <i class="fas fa-plus me-2"></i>Ajukan Surat Baru
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Ajukan Surat -->
    <div class="modal fade" id="ajukanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajukan Surat Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ajukan">
                        
                        <div class="mb-3">
                            <label class="form-label">Jenis Surat <span class="text-danger">*</span></label>
                            <select name="jenis_surat" class="form-select" required>
                                <option value="">-- Pilih Jenis Surat --</option>
                                <option value="Surat Keterangan Domisili">Surat Keterangan Domisili</option>
                                <option value="Surat Keterangan Tidak Mampu">Surat Keterangan Tidak Mampu</option>
                                <option value="Surat Keterangan Usaha">Surat Keterangan Usaha</option>
                                <option value="Surat Pengantar KTP">Surat Pengantar KTP</option>
                                <option value="Surat Pengantar KK">Surat Pengantar KK</option>
                                <option value="Surat Keterangan Pindah">Surat Keterangan Pindah</option>
                                <option value="Surat Lainnya">Surat Lainnya</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Keperluan <span class="text-danger">*</span></label>
                            <textarea name="keperluan" class="form-control" rows="5" required placeholder="Jelaskan keperluan surat Anda..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">File Pendukung (opsional)</label>
                            <input type="file" name="file_pendukung" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="text-muted">Format: JPG, PNG, PDF. Max 5MB</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Informasi:</strong> Surat yang diajukan akan diverifikasi oleh admin RT. 
                            Anda akan menerima notifikasi jika surat sudah disetujui atau ditolak.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Ajukan Surat</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pengajuan Surat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
    
    <!-- Modal Batal -->
    <div class="modal fade" id="batalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Konfirmasi Pembatalan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="batal">
                        <input type="hidden" name="id_surat" id="batal_id_surat">
                        <p>Apakah Anda yakin ingin membatalkan pengajuan surat:</p>
                        <h5 class="text-danger" id="batal_jenis_surat"></h5>
                        <p class="text-muted mb-0">Pengajuan yang dibatalkan tidak dapat dikembalikan!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tidak</button>
                        <button type="submit" class="btn btn-danger">Ya, Batalkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function detailSurat(id) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_surat_detail&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const s = data.data;
                    let statusBadge = '';
                    if (s.status === 'pending') {
                        statusBadge = '<span class="badge bg-warning">Menunggu Verifikasi</span>';
                    } else if (s.status === 'approved') {
                        statusBadge = '<span class="badge bg-success">Disetujui</span>';
                    } else {
                        statusBadge = '<span class="badge bg-danger">Ditolak</span>';
                    }
                    
                    let content = `
                        <h5>${s.jenis_surat} ${statusBadge}</h5>
                        <hr>
                        <table class="table table-bordered">
                            <tr><th width="200">Tanggal Pengajuan</th><td>${s.created_at}</td></tr>
                            <tr><th>Keperluan</th><td>${s.keperluan}</td></tr>
                            ${s.file_pendukung ? '<tr><th>File Pendukung</th><td><a href="../uploads/surat/' + s.file_pendukung + '" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-download me-1"></i>Download</a></td></tr>' : ''}
                            ${s.status === 'approved' && s.file_hasil ? '<tr><th>Surat Hasil</th><td><a href="../uploads/surat/' + s.file_hasil + '" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-download me-1"></i>Download Surat</a></td></tr>' : ''}
                            ${s.status === 'rejected' && s.catatan ? '<tr><th>Alasan Penolakan</th><td class="text-danger">' + s.catatan + '</td></tr>' : ''}
                        </table>
                        
                        <h6 class="mt-4">Timeline Status</h6>
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-icon pending"></div>
                                <div class="timeline-content">
                                    <strong>Pengajuan Dibuat</strong>
                                    <p class="text-muted mb-0">${s.created_at}</p>
                                </div>
                            </div>
                    `;
                    
                    if (s.status === 'approved' || s.status === 'rejected') {
                        content += `
                            <div class="timeline-item">
                                <div class="timeline-icon ${s.status === 'approved' ? 'approved' : 'rejected'}"></div>
                                <div class="timeline-content">
                                    <strong>${s.status === 'approved' ? 'Disetujui' : 'Ditolak'}</strong>
                                    <p class="text-muted mb-0">${s.updated_at}</p>
                                </div>
                            </div>
                        `;
                    }
                    
                    content += '</div>';
                    
                    document.getElementById('detailContent').innerHTML = content;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                }
            });
        }
        
        function batalSurat(id, jenis) {
            document.getElementById('batal_id_surat').value = id;
            document.getElementById('batal_jenis_surat').textContent = jenis;
            new bootstrap.Modal(document.getElementById('batalModal')).show();
        }
    </script>
</body>
</html>