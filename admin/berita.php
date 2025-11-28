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
            $konten = cleanInput($_POST['konten']);
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
                $query = "INSERT INTO berita (judul, konten, gambar, id_user) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssi", $judul, $konten, $gambar, $user['id_user']);
                
                if ($stmt->execute()) {
                    $success = "Berita berhasil ditambahkan!";
                    
                    $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Tambah Berita', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_desc = "Menambahkan berita: $judul";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                    $log_stmt->execute();
                } else {
                    $error = "Gagal menambahkan berita!";
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $id_berita = cleanInput($_POST['id_berita']);
            $judul = cleanInput($_POST['judul']);
            $konten = cleanInput($_POST['konten']);
            
            $get_current = $conn->prepare("SELECT gambar FROM berita WHERE id_berita = ?");
            $get_current->bind_param("i", $id_berita);
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
                $query = "UPDATE berita SET judul = ?, konten = ?, gambar = ? WHERE id_berita = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssi", $judul, $konten, $gambar, $id_berita);
                
                if ($stmt->execute()) {
                    $success = "Berita berhasil diupdate!";
                    
                    $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Edit Berita', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_desc = "Mengedit berita: $judul";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                    $log_stmt->execute();
                } else {
                    $error = "Gagal mengupdate berita!";
                }
            }
        } elseif ($_POST['action'] === 'hapus') {
            $id_berita = cleanInput($_POST['id_berita']);
            
            $get_berita = $conn->prepare("SELECT judul, gambar FROM berita WHERE id_berita = ?");
            $get_berita->bind_param("i", $id_berita);
            $get_berita->execute();
            $berita_data = $get_berita->get_result()->fetch_assoc();
            
            $query = "DELETE FROM berita WHERE id_berita = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_berita);
            
            if ($stmt->execute()) {
                if ($berita_data['gambar']) {
                    $file_path = __DIR__ . "/../uploads/kegiatan/" . $berita_data['gambar'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                $success = "Berita berhasil dihapus!";
                
                $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Hapus Berita', ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_desc = "Menghapus berita: {$berita_data['judul']}";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                $log_stmt->execute();
            } else {
                $error = "Gagal menghapus berita!";
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
    $where .= " AND (b.judul LIKE ? OR b.konten LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$count_query = "SELECT COUNT(*) as total FROM berita b $where";
if ($params) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
}
$total_pages = ceil($total_records / $limit);

$query = "SELECT b.*, u.username,
          (SELECT COUNT(*) FROM komentar WHERE jenis_postingan = 'berita' AND id_postingan = b.id_berita) as jumlah_komentar
          FROM berita b
          JOIN users u ON b.id_user = u.id_user
          $where
          ORDER BY b.created_at DESC
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
$berita_list = $stmt->get_result();

$query = "SELECT COUNT(*) as total FROM surat WHERE status = 'pending'";
$surat_pending = $conn->query($query)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Berita - SISFO RT</title>
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
        .berita-img {
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
                    <a class="nav-link active" href="berita.php">
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
                        <h2><i class="fas fa-newspaper me-2"></i>Manajemen Berita</h2>
                        <p class="text-muted mb-0">Kelola berita RT</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                        <i class="fas fa-plus me-2"></i>Tambah Berita
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
                                <input type="text" name="search" class="form-control" placeholder="Cari berita..." value="<?php echo e($search); ?>">
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
                                        <th>Penulis</th>
                                        <th>Views</th>
                                        <th>Komentar</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($berita_list->num_rows > 0): ?>
                                        <?php $no = $offset + 1; ?>
                                        <?php while($berita = $berita_list->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td>
                                                    <?php if ($berita['gambar']): ?>
                                                        <img src="../uploads/kegiatan/<?php echo e($berita['gambar']); ?>" class="berita-img" alt="Gambar">
                                                    <?php else: ?>
                                                        <div class="berita-img bg-secondary d-flex align-items-center justify-content-center text-white">
                                                            <i class="fas fa-image fa-2x"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo e($berita['judul']); ?></strong><br>
                                                    <small class="text-muted"><?php echo e(substr($berita['konten'], 0, 100)); ?>...</small>
                                                </td>
                                                <td><?php echo e($berita['username']); ?></td>
                                                <td><i class="fas fa-eye me-1"></i><?php echo $berita['views']; ?></td>
                                                <td><i class="fas fa-comments me-1"></i><?php echo $berita['jumlah_komentar']; ?></td>
                                                <td><?php echo formatTanggal($berita['created_at']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="detailBerita(<?php echo $berita['id_berita']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="editBerita(<?php echo $berita['id_berita']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="hapusBerita(<?php echo $berita['id_berita']; ?>, '<?php echo e($berita['judul']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Tidak ada berita</td>
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
                    <h5 class="modal-title">Tambah Berita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="tambah">
                        
                        <div class="mb-3">
                            <label class="form-label">Judul Berita <span class="text-danger">*</span></label>
                            <input type="text" name="judul" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Konten <span class="text-danger">*</span></label>
                            <textarea name="konten" class="form-control" rows="8" required></textarea>
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
                    <h5 class="modal-title">Edit Berita</h5>
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
                    <h5 class="modal-title">Detail Berita</h5>
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
                        <input type="hidden" name="id_berita" id="hapus_id_berita">
                        <p>Apakah Anda yakin ingin menghapus berita:</p>
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
        function detailBerita(id) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_berita_detail&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const b = data.data;
                    document.getElementById('detailContent').innerHTML = `
                        ${b.gambar ? '<img src="../uploads/kegiatan/' + b.gambar + '" class="img-fluid mb-3" alt="Gambar">' : ''}
                        <h4>${b.judul}</h4>
                        <p class="text-muted">
                            <i class="fas fa-user me-2"></i>${b.username}
                            <i class="fas fa-calendar ms-3 me-2"></i>${b.created_at}
                            <i class="fas fa-eye ms-3 me-2"></i>${b.views} views
                        </p>
                        <hr>
                        <div style="white-space: pre-wrap;">${b.konten}</div>
                    `;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                }
            });
        }
        
        function editBerita(id) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_berita_detail&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const b = data.data;
                    document.getElementById('editFormContent').innerHTML = `
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id_berita" value="${b.id_berita}">
                        
                        <div class="mb-3">
                            <label class="form-label">Judul Berita <span class="text-danger">*</span></label>
                            <input type="text" name="judul" class="form-control" value="${b.judul}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Konten <span class="text-danger">*</span></label>
                            <textarea name="konten" class="form-control" rows="8" required>${b.konten}</textarea>
                        </div>
                        
                        ${b.gambar ? '<div class="mb-3"><img src="../uploads/kegiatan/' + b.gambar + '" class="img-thumbnail" style="max-width: 200px;"></div>' : ''}
                        
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
        
        function hapusBerita(id, judul) {
            document.getElementById('hapus_id_berita').value = id;
            document.getElementById('hapus_judul').textContent = judul;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        }
    </script>
</body>
</html>