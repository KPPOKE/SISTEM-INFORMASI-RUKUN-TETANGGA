<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkAdmin();

$user = getCurrentUser();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'approve') {
            $id_surat = cleanInput($_POST['id_surat']);
            $catatan = cleanInput($_POST['catatan']);
            
            $query = "UPDATE surat SET status = 'approved', catatan = ?, updated_at = NOW() WHERE id_surat = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $catatan, $id_surat);
            
            if ($stmt->execute()) {
                $success = "Surat berhasil disetujui!";
                
                $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Approve Surat', ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_desc = "Menyetujui surat ID: $id_surat";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                $log_stmt->execute();
            } else {
                $error = "Gagal menyetujui surat!";
            }
        } elseif ($_POST['action'] === 'reject') {
            $id_surat = cleanInput($_POST['id_surat']);
            $catatan = cleanInput($_POST['catatan']);
            
            if (empty($catatan)) {
                $error = "Catatan penolakan harus diisi!";
            } else {
                $query = "UPDATE surat SET status = 'rejected', catatan = ?, updated_at = NOW() WHERE id_surat = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("si", $catatan, $id_surat);
                
                if ($stmt->execute()) {
                    $success = "Surat berhasil ditolak!";
                    
                    $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Reject Surat', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_desc = "Menolak surat ID: $id_surat";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                    $log_stmt->execute();
                } else {
                    $error = "Gagal menolak surat!";
                }
            }
        } elseif ($_POST['action'] === 'hapus') {
            $id_surat = cleanInput($_POST['id_surat']);
            
            $get_file = $conn->prepare("SELECT file_pendukung FROM surat WHERE id_surat = ?");
            $get_file->bind_param("i", $id_surat);
            $get_file->execute();
            $file_data = $get_file->get_result()->fetch_assoc();
            
            $query = "DELETE FROM surat WHERE id_surat = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_surat);
            
            if ($stmt->execute()) {
                if ($file_data['file_pendukung']) {
                    $file_path = __DIR__ . "/../uploads/surat/" . $file_data['file_pendukung'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                $success = "Surat berhasil dihapus!";
                
                $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Hapus Surat', ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_desc = "Menghapus surat ID: $id_surat";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                $log_stmt->execute();
            } else {
                $error = "Gagal menghapus surat!";
            }
        }
    }
}

$filter_status = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($filter_status) {
    $where .= " AND s.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($search) {
    $where .= " AND (w.nama_lengkap LIKE ? OR s.jenis_surat LIKE ? OR w.nik LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$count_query = "SELECT COUNT(*) as total FROM surat s 
                JOIN warga w ON s.id_warga = w.id_warga 
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

$query = "SELECT s.*, w.nama_lengkap, w.nik, w.no_telepon, w.alamat 
          FROM surat s
          JOIN warga w ON s.id_warga = w.id_warga
          $where
          ORDER BY s.created_at DESC
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
$surat_list = $stmt->get_result();

$stats = [];
$stats['pending'] = $conn->query("SELECT COUNT(*) as total FROM surat WHERE status = 'pending'")->fetch_assoc()['total'];
$stats['approved'] = $conn->query("SELECT COUNT(*) as total FROM surat WHERE status = 'approved'")->fetch_assoc()['total'];
$stats['rejected'] = $conn->query("SELECT COUNT(*) as total FROM surat WHERE status = 'rejected'")->fetch_assoc()['total'];

$surat_pending = $stats['pending'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Surat - SISFO RT</title>
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
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
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
                    <a class="nav-link active" href="surat.php">
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
                    <a class="nav-link" href="activity_log.php">
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
                        <h2><i class="fas fa-file-alt me-2"></i>Manajemen Surat</h2>
                        <p class="text-muted mb-0">Kelola pengajuan surat warga</p>
                    </div>
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
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card bg-warning text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Pending</h6>
                                    <h3 class="mb-0"><?php echo $stats['pending']; ?></h3>
                                </div>
                                <i class="fas fa-clock fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Disetujui</h6>
                                    <h3 class="mb-0"><?php echo $stats['approved']; ?></h3>
                                </div>
                                <i class="fas fa-check-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-danger text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Ditolak</h6>
                                    <h3 class="mb-0"><?php echo $stats['rejected']; ?></h3>
                                </div>
                                <i class="fas fa-times-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter & Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <input type="text" name="search" class="form-control" placeholder="Cari nama, jenis surat, atau NIK..." value="<?php echo e($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <select name="status" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Disetujui</option>
                                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Cari</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Data Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal Ajuan</th>
                                        <th>NIK</th>
                                        <th>Nama Pemohon</th>
                                        <th>Jenis Surat</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($surat_list->num_rows > 0): ?>
                                        <?php $no = $offset + 1; ?>
                                        <?php while($surat = $surat_list->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo formatTanggal($surat['created_at']); ?></td>
                                                <td><?php echo e($surat['nik']); ?></td>
                                                <td>
                                                    <strong><?php echo e($surat['nama_lengkap']); ?></strong><br>
                                                    <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo e($surat['no_telepon']); ?></small>
                                                </td>
                                                <td><?php echo e($surat['jenis_surat']); ?></td>
                                                <td>
                                                    <?php if ($surat['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($surat['status'] === 'approved'): ?>
                                                        <span class="badge bg-success">Disetujui</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Ditolak</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="detailSurat(<?php echo $surat['id_surat']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($surat['status'] === 'approved'): ?>
                                                        <a href="cetak_surat.php?id=<?php echo $surat['id_surat']; ?>" target="_blank" class="btn btn-sm btn-primary" title="Cetak Surat">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($surat['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-success" onclick="approveSurat(<?php echo $surat['id_surat']; ?>)" title="Setujui">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="rejectSurat(<?php echo $surat['id_surat']; ?>)" title="Tolak">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-danger" onclick="hapusSurat(<?php echo $surat['id_surat']; ?>)" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Tidak ada data surat</td>
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
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_status ? '&status=' . urlencode($filter_status) : ''; ?>">
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
    
    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Surat</h5>
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
    
    <!-- Modal Approve -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Setujui Surat</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id_surat" id="approve_id_surat">
                        <p>Apakah Anda yakin ingin menyetujui surat ini?</p>
                        <div class="mb-3">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea name="catatan" class="form-control" rows="3" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Ya, Setujui</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Reject -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Surat</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id_surat" id="reject_id_surat">
                        <p>Apakah Anda yakin ingin menolak surat ini?</p>
                        <div class="mb-3">
                            <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                            <textarea name="catatan" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Ya, Tolak</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Hapus -->
    <div class="modal fade" id="hapusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id_surat" id="hapus_id_surat">
                        <p>Apakah Anda yakin ingin menghapus surat ini?</p>
                        <p class="text-muted mb-0">Data yang dihapus tidak dapat dikembalikan!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Ya, Hapus</button>
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
                    if (s.status === 'pending') statusBadge = '<span class="badge bg-warning">Pending</span>';
                    else if (s.status === 'approved') statusBadge = '<span class="badge bg-success">Disetujui</span>';
                    else statusBadge = '<span class="badge bg-danger">Ditolak</span>';
                    
                    document.getElementById('detailContent').innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr><th>NIK</th><td>${s.nik}</td></tr>
                                    <tr><th>Nama Pemohon</th><td><strong>${s.nama_lengkap}</strong></td></tr>
                                    <tr><th>Alamat</th><td>${s.alamat}</td></tr>
                                    <tr><th>No. Telepon</th><td>${s.no_telepon}</td></tr>
                                    <tr><th>Jenis Surat</th><td>${s.jenis_surat}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr><th>Tanggal Ajuan</th><td>${s.created_at}</td></tr>
                                    <tr><th>Status</th><td>${statusBadge}</td></tr>
                                    <tr><th>File Pendukung</th><td>${s.file_pendukung ? '<a href="../uploads/surat/' + s.file_pendukung + '" target="_blank" class="btn btn-sm btn-primary">Lihat File</a>' : '-'}</td></tr>
                                </table>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <h6>Keperluan:</h6>
                            <p class="text-muted">${s.keperluan}</p>
                        </div>
                        ${s.catatan ? '<div class="alert alert-info"><strong>Catatan:</strong><br>' + s.catatan + '</div>' : ''}
                    `;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                }
            });
        }
        
        function approveSurat(id) {
            document.getElementById('approve_id_surat').value = id;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }
        
        function rejectSurat(id) {
            document.getElementById('reject_id_surat').value = id;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
        
        function hapusSurat(id) {
            document.getElementById('hapus_id_surat').value = id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        }
    </script>
</body>
</html>