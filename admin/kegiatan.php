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
        if ($_POST['action'] === 'tambah') {
            $judul = cleanInput($_POST['judul']);
            $deskripsi = cleanInput($_POST['deskripsi']);
            $tanggal_kegiatan = cleanInput($_POST['tanggal_kegiatan']);
            $lokasi = cleanInput($_POST['lokasi']);
            $gambar = null;
            
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
                $upload_result = uploadFile($_FILES['gambar'], 'kegiatan', ['jpg', 'jpeg', 'png']);
                if ($upload_result['status']) {
                    $gambar = $upload_result['filename'];
                } else {
                    $error = $upload_result['message'];
                }
            }
            
            if (!$error) {
                $query = "INSERT INTO kegiatan (judul, deskripsi, tanggal_kegiatan, lokasi, gambar, id_user) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssi", $judul, $deskripsi, $tanggal_kegiatan, $lokasi, $gambar, $user['id_user']);
                
                if ($stmt->execute()) {
                    $success = "Kegiatan berhasil ditambahkan!";
                    
                    $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Tambah Kegiatan', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_desc = "Menambahkan kegiatan: $judul";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                    $log_stmt->execute();
                } else {
                    $error = "Gagal menambahkan kegiatan!";
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $id_kegiatan = cleanInput($_POST['id_kegiatan']);
            $judul = cleanInput($_POST['judul']);
            $deskripsi = cleanInput($_POST['deskripsi']);
            $tanggal_kegiatan = cleanInput($_POST['tanggal_kegiatan']);
            $lokasi = cleanInput($_POST['lokasi']);
            
            $get_current = $conn->prepare("SELECT gambar FROM kegiatan WHERE id_kegiatan = ?");
            $get_current->bind_param("i", $id_kegiatan);
            $get_current->execute();
            $current_data = $get_current->get_result()->fetch_assoc();
            $gambar = $current_data['gambar'];
            
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
                $upload_result = uploadFile($_FILES['gambar'], 'kegiatan', ['jpg', 'jpeg', 'png']);
                if ($upload_result['status']) {
                    if ($gambar) {
                        $old_file = __DIR__ . "/../uploads/kegiatan/" . $gambar;
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    $gambar = $upload_result['filename'];
                } else {
                    $error = $upload_result['message'];
                }
            }
            
            if (!$error) {
                $query = "UPDATE kegiatan SET judul = ?, deskripsi = ?, tanggal_kegiatan = ?, lokasi = ?, gambar = ? WHERE id_kegiatan = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssi", $judul, $deskripsi, $tanggal_kegiatan, $lokasi, $gambar, $id_kegiatan);
                
                if ($stmt->execute()) {
                    $success = "Kegiatan berhasil diupdate!";
                    
                    $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Edit Kegiatan', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_desc = "Mengedit kegiatan: $judul";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                    $log_stmt->execute();
                } else {
                    $error = "Gagal mengupdate kegiatan!";
                }
            }
        } elseif ($_POST['action'] === 'hapus') {
            $id_kegiatan = cleanInput($_POST['id_kegiatan']);
            
            $get_kegiatan = $conn->prepare("SELECT judul, gambar FROM kegiatan WHERE id_kegiatan = ?");
            $get_kegiatan->bind_param("i", $id_kegiatan);
            $get_kegiatan->execute();
            $kegiatan_data = $get_kegiatan->get_result()->fetch_assoc();
            
            $query = "DELETE FROM kegiatan WHERE id_kegiatan = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_kegiatan);
            
            if ($stmt->execute()) {
                if ($kegiatan_data['gambar']) {
                    $file_path = __DIR__ . "/../uploads/kegiatan/" . $kegiatan_data['gambar'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                $success = "Kegiatan berhasil dihapus!";
                
                $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Hapus Kegiatan', ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_desc = "Menghapus kegiatan: {$kegiatan_data['judul']}";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                $log_stmt->execute();
            } else {
                $error = "Gagal menghapus kegiatan!";
            }
        }
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $where .= " AND (k.judul LIKE ? OR k.deskripsi LIKE ? OR k.lokasi LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$count_query = "SELECT COUNT(*) as total FROM kegiatan k $where";
if ($params) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
}
$total_pages = ceil($total_records / $limit);

$query = "SELECT k.*, u.username,
          (SELECT COUNT(*) FROM komentar WHERE jenis_postingan = 'kegiatan' AND id_postingan = k.id_kegiatan) as jumlah_komentar
          FROM kegiatan k
          JOIN users u ON k.id_user = u.id_user
          $where
          ORDER BY k.tanggal_kegiatan DESC
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
$kegiatan_list = $stmt->get_result();

$query = "SELECT COUNT(*) as total FROM surat WHERE status = 'pending'";
$surat_pending = $conn->query($query)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kegiatan - SISFO RT</title>
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
        .kegiatan-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
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
                    <a class="nav-link active" href="kegiatan.php">
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
                        <h2><i class="fas fa-calendar-alt me-2"></i>Manajemen Kegiatan</h2>
                        <p class="text-muted mb-0">Kelola kegiatan RT</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                        <i class="fas fa-plus me-2"></i>Tambah Kegiatan
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
                
                <!-- Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-9">
                                <input type="text" name="search" class="form-control" placeholder="Cari kegiatan..." value="<?php echo e($search); ?>">
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
                                        <th>Gambar</th>
                                        <th>Judul</th>
                                        <th>Tanggal Kegiatan</th>
                                        <th>Lokasi</th>
                                        <th>Views</th>
                                        <th>Komentar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($kegiatan_list->num_rows > 0): ?>
                                        <?php $no = $offset + 1; ?>
                                        <?php while($kegiatan = $kegiatan_list->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td>
                                                    <?php if ($kegiatan['gambar']): ?>
                                                        <img src="../uploads/kegiatan/<?php echo e($kegiatan['gambar']); ?>" class="kegiatan-img" alt="Gambar">
                                                    <?php else: ?>
                                                        <div class="kegiatan-img bg-secondary d-flex align-items-center justify-content-center text-white">
                                                            <i class="fas fa-image fa-2x"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo e($kegiatan['judul']); ?></strong><br>
                                                    <small class="text-muted"><?php echo e(substr($kegiatan['deskripsi'], 0, 80)); ?>...</small>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($kegiatan['tanggal_kegiatan'])); ?></td>
                                                <td><?php echo e($kegiatan['lokasi']); ?></td>
                                                <td><i class="fas fa-eye me-1"></i><?php echo $kegiatan['views']; ?></td>
                                                <td><i class="fas fa-comments me-1"></i><?php echo $kegiatan['jumlah_komentar']; ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="detailKegiatan(<?php echo $kegiatan['id_kegiatan']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="editKegiatan(<?php echo $kegiatan['id_kegiatan']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="hapusKegiatan(<?php echo $kegiatan['id_kegiatan']; ?>, '<?php echo e($kegiatan['judul']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Tidak ada kegiatan</td>
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
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
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
    
    <!-- Modal Tambah -->
    <div class="modal fade" id="tambahModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kegiatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="tambah">
                        
                        <div class="mb-3">
                            <label class="form-label">Judul Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" name="judul" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal & Waktu <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="tanggal_kegiatan" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Lokasi <span class="text-danger">*</span></label>
                                    <input type="text" name="lokasi" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Deskripsi <span class="text-danger">*</span></label>
                            <textarea name="deskripsi" class="form-control" rows="8" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Gambar (opsional)</label>
                            <input type="file" name="gambar" class="form-control" accept="image/*">
                            <small class="text-muted">Format: JPG, JPEG, PNG. Max 5MB</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Kegiatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body" id="editFormContent">
                        <!-- Content loaded by AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Update</button>
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
                    <h5 class="modal-title">Detail Kegiatan</h5>
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
                        <input type="hidden" name="id_kegiatan" id="hapus_id_kegiatan">
                        <p>Apakah Anda yakin ingin menghapus kegiatan:</p>
                        <h5 class="text-danger" id="hapus_judul"></h5>
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
        function detailKegiatan(id) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_kegiatan_detail&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const k = data.data;
                    document.getElementById('detailContent').innerHTML = `
                        ${k.gambar ? '<img src="../uploads/kegiatan/' + k.gambar + '" class="img-fluid mb-3" alt="Gambar">' : ''}
                        <h4>${k.judul}</h4>
                        <p class="text-muted">
                            <i class="fas fa-user me-2"></i>${k.username}
                            <i class="fas fa-calendar ms-3 me-2"></i>${k.tanggal_kegiatan}
                            <i class="fas fa-map-marker-alt ms-3 me-2"></i>${k.lokasi}
                        </p>
                        <p class="text-muted">
                            <i class="fas fa-eye me-2"></i>${k.views} views
                            <i class="fas fa-clock ms-3 me-2"></i>Dibuat: ${k.created_at}
                        </p>
                        <hr>
                        <div style="white-space: pre-wrap;">${k.deskripsi}</div>
                    `;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                }
            });
        }
        
        function editKegiatan(id) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_kegiatan_detail&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const k = data.data;
                    const tanggal = k.tanggal_kegiatan_raw.replace(' ', 'T');
                    document.getElementById('editFormContent').innerHTML = `
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id_kegiatan" value="${k.id_kegiatan}">
                        
                        <div class="mb-3">
                            <label class="form-label">Judul Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" name="judul" class="form-control" value="${k.judul}" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal & Waktu <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="tanggal_kegiatan" class="form-control" value="${tanggal}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Lokasi <span class="text-danger">*</span></label>
                                    <input type="text" name="lokasi" class="form-control" value="${k.lokasi}" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Deskripsi <span class="text-danger">*</span></label>
                            <textarea name="deskripsi" class="form-control" rows="8" required>${k.deskripsi}</textarea>
                        </div>
                        
                        ${k.gambar ? '<div class="mb-3"><img src="../uploads/kegiatan/' + k.gambar + '" class="img-thumbnail" style="max-width: 200px;"></div>' : ''}
                        
                        <div class="mb-3">
                            <label class="form-label">Ganti Gambar (opsional)</label>
                            <input type="file" name="gambar" class="form-control" accept="image/*">
                            <small class="text-muted">Kosongkan jika tidak ingin mengganti gambar</small>
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('editModal')).show();
                }
            });
        }
        
        function hapusKegiatan(id, judul) {
            document.getElementById('hapus_id_kegiatan').value = id;
            document.getElementById('hapus_judul').textContent = judul;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        }
    </script>
</body>
</html>