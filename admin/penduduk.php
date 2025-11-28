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
            $nik = cleanInput($_POST['nik']);
            $no_kk = cleanInput($_POST['no_kk']);
            $nama_lengkap = cleanInput($_POST['nama_lengkap']);
            $tempat_lahir = cleanInput($_POST['tempat_lahir']);
            $tanggal_lahir = cleanInput($_POST['tanggal_lahir']);
            $jenis_kelamin = cleanInput($_POST['jenis_kelamin']);
            $status_keluarga = cleanInput($_POST['status_keluarga']);
            $agama = cleanInput($_POST['agama']);
            $pendidikan = cleanInput($_POST['pendidikan']);
            $pekerjaan = cleanInput($_POST['pekerjaan']);
            $status_perkawinan = cleanInput($_POST['status_perkawinan']);
            $alamat = cleanInput($_POST['alamat']);
            $rt = cleanInput($_POST['rt']);
            $rw = cleanInput($_POST['rw']);
            $latitude = !empty($_POST['latitude']) ? cleanInput($_POST['latitude']) : null;
            $longitude = !empty($_POST['longitude']) ? cleanInput($_POST['longitude']) : null;
            $no_telepon = cleanInput($_POST['no_telepon']);
            
            $check_nik = $conn->prepare("SELECT id_warga FROM warga WHERE nik = ?");
            $check_nik->bind_param("s", $nik);
            $check_nik->execute();
            if ($check_nik->get_result()->num_rows > 0) {
                $error = "NIK sudah terdaftar!";
            } else {
                $id_user = null;
                $account_info = '';
                
                if ($status_keluarga === 'Kepala Keluarga') {
                    if (isKKExists($no_kk)) {
                        $error = "No. KK sudah memiliki Kepala Keluarga dengan akun!";
                    } else {
                        $account_result = createKKAccount($nik, $nama_lengkap);
                        if ($account_result['status']) {
                            $id_user = $account_result['user_id'];
                            $account_info = "<br><strong>Akun berhasil dibuat:</strong><br>Username: {$account_result['username']}<br>Password: {$account_result['password']}";
                        } else {
                            $error = $account_result['message'];
                        }
                    }
                }
                
                if (!$error) {
                    $query = "INSERT INTO warga (id_user, nik, no_kk, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, status_keluarga, agama, pendidikan, pekerjaan, status_perkawinan, alamat, rt, rw, latitude, longitude, no_telepon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("issssssssssssssdds", $id_user, $nik, $no_kk, $nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $status_keluarga, $agama, $pendidikan, $pekerjaan, $status_perkawinan, $alamat, $rt, $rw, $latitude, $longitude, $no_telepon);
                    
                    if ($stmt->execute()) {
                        $success = "Data penduduk berhasil ditambahkan!" . $account_info;
                        
                        $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Tambah Penduduk', ?, ?)";
                        $log_stmt = $conn->prepare($log_query);
                        $log_desc = "Menambahkan penduduk: $nama_lengkap (NIK: $nik)";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                        $log_stmt->execute();
                    } else {
                        $error = "Gagal menambahkan data!";
                    }
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $id_warga = cleanInput($_POST['id_warga']);
            $nik = cleanInput($_POST['nik']);
            $no_kk = cleanInput($_POST['no_kk']);
            $nama_lengkap = cleanInput($_POST['nama_lengkap']);
            $tempat_lahir = cleanInput($_POST['tempat_lahir']);
            $tanggal_lahir = cleanInput($_POST['tanggal_lahir']);
            $jenis_kelamin = cleanInput($_POST['jenis_kelamin']);
            $status_keluarga = cleanInput($_POST['status_keluarga']);
            $agama = cleanInput($_POST['agama']);
            $pendidikan = cleanInput($_POST['pendidikan']);
            $pekerjaan = cleanInput($_POST['pekerjaan']);
            $status_perkawinan = cleanInput($_POST['status_perkawinan']);
            $alamat = cleanInput($_POST['alamat']);
            $rt = cleanInput($_POST['rt']);
            $rw = cleanInput($_POST['rw']);
            $latitude = !empty($_POST['latitude']) ? cleanInput($_POST['latitude']) : null;
            $longitude = !empty($_POST['longitude']) ? cleanInput($_POST['longitude']) : null;
            $no_telepon = cleanInput($_POST['no_telepon']);
            
            $check_nik = $conn->prepare("SELECT id_warga FROM warga WHERE nik = ? AND id_warga != ?");
            $check_nik->bind_param("si", $nik, $id_warga);
            $check_nik->execute();
            if ($check_nik->get_result()->num_rows > 0) {
                $error = "NIK sudah terdaftar!";
            } else {
                $get_current = $conn->prepare("SELECT id_user, status_keluarga FROM warga WHERE id_warga = ?");
                $get_current->bind_param("i", $id_warga);
                $get_current->execute();
                $current_data = $get_current->get_result()->fetch_assoc();
                
                $id_user = $current_data['id_user'];
                $account_info = '';
                
                if ($status_keluarga === 'Kepala Keluarga' && $current_data['status_keluarga'] !== 'Kepala Keluarga') {
                    if (isKKExists($no_kk, $id_warga)) {
                        $error = "No. KK sudah memiliki Kepala Keluarga dengan akun!";
                    } else {
                        if (!$id_user) {
                            $account_result = createKKAccount($nik, $nama_lengkap);
                            if ($account_result['status']) {
                                $id_user = $account_result['user_id'];
                                $account_info = "<br><strong>Akun berhasil dibuat:</strong><br>Username: {$account_result['username']}<br>Password: {$account_result['password']}";
                            } else {
                                $error = $account_result['message'];
                            }
                        }
                    }
                }
                
                if (!$error) {
                    $query = "UPDATE warga SET id_user = ?, nik = ?, no_kk = ?, nama_lengkap = ?, tempat_lahir = ?, tanggal_lahir = ?, jenis_kelamin = ?, status_keluarga = ?, agama = ?, pendidikan = ?, pekerjaan = ?, status_perkawinan = ?, alamat = ?, rt = ?, rw = ?, latitude = ?, longitude = ?, no_telepon = ? WHERE id_warga = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("issssssssssssssddsi", $id_user, $nik, $no_kk, $nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $status_keluarga, $agama, $pendidikan, $pekerjaan, $status_perkawinan, $alamat, $rt, $rw, $latitude, $longitude, $no_telepon, $id_warga);
                    
                    if ($stmt->execute()) {
                        $success = "Data penduduk berhasil diupdate!" . $account_info;
                        
                        $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Edit Penduduk', ?, ?)";
                        $log_stmt = $conn->prepare($log_query);
                        $log_desc = "Mengedit penduduk: $nama_lengkap (NIK: $nik)";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                        $log_stmt->execute();
                    } else {
                        $error = "Gagal mengupdate data!";
                    }
                }
            }
        } elseif ($_POST['action'] === 'hapus') {
            $id_warga = cleanInput($_POST['id_warga']);
            
            $get_warga = $conn->prepare("SELECT nama_lengkap, nik, id_user FROM warga WHERE id_warga = ?");
            $get_warga->bind_param("i", $id_warga);
            $get_warga->execute();
            $warga_data = $get_warga->get_result()->fetch_assoc();
            
            $query = "DELETE FROM warga WHERE id_warga = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_warga);
            
            if ($stmt->execute()) {
                if ($warga_data['id_user']) {
                    $delete_user = $conn->prepare("DELETE FROM users WHERE id_user = ?");
                    $delete_user->bind_param("i", $warga_data['id_user']);
                    $delete_user->execute();
                }
                
                $success = "Data penduduk berhasil dihapus!";
                
                $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Hapus Penduduk', ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_desc = "Menghapus penduduk: {$warga_data['nama_lengkap']} (NIK: {$warga_data['nik']})";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                $log_stmt->execute();
            } else {
                $error = "Gagal menghapus data!";
            }
        }
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? cleanInput($_GET['status']) : '';

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $where .= " AND (nama_lengkap LIKE ? OR nik LIKE ? OR no_kk LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($filter_status) {
    $where .= " AND status_keluarga = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$count_query = "SELECT COUNT(*) as total FROM warga $where";
if ($params) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
}
$total_pages = ceil($total_records / $limit);

$query = "SELECT w.*, u.username FROM warga w 
          LEFT JOIN users u ON w.id_user = u.id_user 
          $where 
          ORDER BY w.created_at DESC 
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
$warga_list = $stmt->get_result();

$query = "SELECT COUNT(*) as total FROM surat WHERE status = 'pending'";
$surat_pending = $conn->query($query)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Penduduk - SISFO RT</title>
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
        .table-responsive {
            border-radius: 10px;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
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
                    <a class="nav-link active" href="penduduk.php">
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
                        <h2><i class="fas fa-users me-2"></i>Data Penduduk</h2>
                        <p class="text-muted mb-0">Kelola data penduduk RT</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                        <i class="fas fa-plus me-2"></i>Tambah Penduduk
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
                
                <!-- Filter & Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK, atau No. KK..." value="<?php echo e($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <select name="status" class="form-select">
                                    <option value="">Semua Status Keluarga</option>
                                    <option value="Kepala Keluarga" <?php echo $filter_status === 'Kepala Keluarga' ? 'selected' : ''; ?>>Kepala Keluarga</option>
                                    <option value="Istri" <?php echo $filter_status === 'Istri' ? 'selected' : ''; ?>>Istri</option>
                                    <option value="Anak" <?php echo $filter_status === 'Anak' ? 'selected' : ''; ?>>Anak</option>
                                    <option value="Orang Tua" <?php echo $filter_status === 'Orang Tua' ? 'selected' : ''; ?>>Orang Tua</option>
                                    <option value="Lainnya" <?php echo $filter_status === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
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
                                        <th>NIK</th>
                                        <th>No. KK</th>
                                        <th>Nama Lengkap</th>
                                        <th>JK</th>
                                        <th>Status Keluarga</th>
                                        <th>Pekerjaan</th>
                                        <th>Username</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($warga_list->num_rows > 0): ?>
                                        <?php $no = $offset + 1; ?>
                                        <?php while($warga = $warga_list->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo e($warga['nik']); ?></td>
                                                <td><?php echo e($warga['no_kk']); ?></td>
                                                <td>
                                                    <strong><?php echo e($warga['nama_lengkap']); ?></strong><br>
                                                    <small class="text-muted"><?php echo e($warga['alamat']); ?></small>
                                                </td>
                                                <td><?php echo e($warga['jenis_kelamin']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $warga['status_keluarga'] === 'Kepala Keluarga' ? 'bg-primary' : 'bg-secondary'; ?>">
                                                        <?php echo e($warga['status_keluarga']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo e($warga['pekerjaan']); ?></td>
                                                <td>
                                                    <?php if ($warga['username']): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i><?php echo e($warga['username']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="detailWarga(<?php echo $warga['id_warga']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="editWarga(<?php echo $warga['id_warga']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="hapusWarga(<?php echo $warga['id_warga']; ?>, '<?php echo e($warga['nama_lengkap']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">Tidak ada data penduduk</td>
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
    
    <!-- Modal Tambah -->
    <div class="modal fade" id="tambahModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Data Penduduk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="tambah">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIK <span class="text-danger">*</span></label>
                                <input type="text" name="nik" class="form-control" required maxlength="16" pattern="[0-9]{16}">
                                <small class="text-muted">16 digit angka</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">No. KK <span class="text-danger">*</span></label>
                                <input type="text" name="no_kk" class="form-control" required maxlength="16" pattern="[0-9]{16}">
                                <small class="text-muted">16 digit angka</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select name="jenis_kelamin" class="form-select" required>
                                    <option value="">Pilih</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Keluarga <span class="text-danger">*</span></label>
                                <select name="status_keluarga" class="form-select" required>
                                    <option value="">Pilih</option>
                                    <option value="Kepala Keluarga">Kepala Keluarga</option>
                                    <option value="Istri">Istri</option>
                                    <option value="Anak">Anak</option>
                                    <option value="Orang Tua">Orang Tua</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                                <small class="text-muted">Akun otomatis dibuat untuk Kepala Keluarga</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Agama</label>
                                <select name="agama" class="form-select">
                                    <option value="">Pilih</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Kristen">Kristen</option>
                                    <option value="Katolik">Katolik</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Buddha">Buddha</option>
                                    <option value="Konghucu">Konghucu</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pendidikan</label>
                                <input type="text" name="pendidikan" class="form-control" placeholder="SD, SMP, SMA, S1, dll">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pekerjaan</label>
                                <input type="text" name="pekerjaan" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Perkawinan</label>
                                <select name="status_perkawinan" class="form-select">
                                    <option value="">Pilih</option>
                                    <option value="Belum Kawin">Belum Kawin</option>
                                    <option value="Kawin">Kawin</option>
                                    <option value="Cerai Hidup">Cerai Hidup</option>
                                    <option value="Cerai Mati">Cerai Mati</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">RT</label>
                                <input type="text" name="rt" class="form-control" maxlength="5">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">RW</label>
                                <input type="text" name="rw" class="form-control" maxlength="5">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">No. Telepon</label>
                                <input type="text" name="no_telepon" class="form-control" maxlength="15">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" class="form-control" placeholder="-6.2088">
                                <small class="text-muted">Untuk peta lokasi (opsional)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" class="form-control" placeholder="106.8456">
                                <small class="text-muted">Untuk peta lokasi (opsional)</small>
                            </div>
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
                    <h5 class="modal-title">Edit Data Penduduk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
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
                    <h5 class="modal-title">Detail Penduduk</h5>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id_warga" id="hapus_id_warga">
                        <p>Apakah Anda yakin ingin menghapus data penduduk:</p>
                        <h5 class="text-danger" id="hapus_nama"></h5>
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
        function detailWarga(id) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_warga_detail&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const w = data.data;
                    document.getElementById('detailContent').innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr><th>NIK</th><td>${w.nik}</td></tr>
                                    <tr><th>No. KK</th><td>${w.no_kk}</td></tr>
                                    <tr><th>Nama Lengkap</th><td><strong>${w.nama_lengkap}</strong></td></tr>
                                    <tr><th>Tempat, Tanggal Lahir</th><td>${w.tempat_lahir || '-'}, ${w.tanggal_lahir || '-'}</td></tr>
                                    <tr><th>Jenis Kelamin</th><td>${w.jenis_kelamin}</td></tr>
                                    <tr><th>Status Keluarga</th><td><span class="badge ${w.status_keluarga === 'Kepala Keluarga' ? 'bg-primary' : 'bg-secondary'}">${w.status_keluarga}</span></td></tr>
                                    <tr><th>Agama</th><td>${w.agama || '-'}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr><th>Pendidikan</th><td>${w.pendidikan || '-'}</td></tr>
                                    <tr><th>Pekerjaan</th><td>${w.pekerjaan || '-'}</td></tr>
                                    <tr><th>Status Perkawinan</th><td>${w.status_perkawinan || '-'}</td></tr>
                                    <tr><th>Alamat</th><td>${w.alamat || '-'}</td></tr>
                                    <tr><th>RT / RW</th><td>${w.rt || '-'} / ${w.rw || '-'}</td></tr>
                                    <tr><th>No. Telepon</th><td>${w.no_telepon || '-'}</td></tr>
                                    <tr><th>Username</th><td>${w.username ? '<span class="badge bg-success">' + w.username + '</span>' : '<span class="badge bg-secondary">Tidak ada akun</span>'}</td></tr>
                                </table>
                            </div>
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                }
            });
        }
        
        function editWarga(id) {
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_warga_detail&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const w = data.data;
                    document.getElementById('editFormContent').innerHTML = `
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id_warga" value="${w.id_warga}">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIK <span class="text-danger">*</span></label>
                                <input type="text" name="nik" class="form-control" required maxlength="16" value="${w.nik}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">No. KK <span class="text-danger">*</span></label>
                                <input type="text" name="no_kk" class="form-control" required maxlength="16" value="${w.no_kk}">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" class="form-control" required value="${w.nama_lengkap}">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" class="form-control" value="${w.tempat_lahir || ''}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="form-control" value="${w.tanggal_lahir || ''}">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select name="jenis_kelamin" class="form-select" required>
                                    <option value="Laki-laki" ${w.jenis_kelamin === 'Laki-laki' ? 'selected' : ''}>Laki-laki</option>
                                    <option value="Perempuan" ${w.jenis_kelamin === 'Perempuan' ? 'selected' : ''}>Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Keluarga <span class="text-danger">*</span></label>
                                <select name="status_keluarga" class="form-select" required>
                                    <option value="Kepala Keluarga" ${w.status_keluarga === 'Kepala Keluarga' ? 'selected' : ''}>Kepala Keluarga</option>
                                    <option value="Istri" ${w.status_keluarga === 'Istri' ? 'selected' : ''}>Istri</option>
                                    <option value="Anak" ${w.status_keluarga === 'Anak' ? 'selected' : ''}>Anak</option>
                                    <option value="Orang Tua" ${w.status_keluarga === 'Orang Tua' ? 'selected' : ''}>Orang Tua</option>
                                    <option value="Lainnya" ${w.status_keluarga === 'Lainnya' ? 'selected' : ''}>Lainnya</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Agama</label>
                                <select name="agama" class="form-select">
                                    <option value="Islam" ${w.agama === 'Islam' ? 'selected' : ''}>Islam</option>
                                    <option value="Kristen" ${w.agama === 'Kristen' ? 'selected' : ''}>Kristen</option>
                                    <option value="Katolik" ${w.agama === 'Katolik' ? 'selected' : ''}>Katolik</option>
                                    <option value="Hindu" ${w.agama === 'Hindu' ? 'selected' : ''}>Hindu</option>
                                    <option value="Buddha" ${w.agama === 'Buddha' ? 'selected' : ''}>Buddha</option>
                                    <option value="Konghucu" ${w.agama === 'Konghucu' ? 'selected' : ''}>Konghucu</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pendidikan</label>
                                <input type="text" name="pendidikan" class="form-control" value="${w.pendidikan || ''}">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pekerjaan</label>
                                <input type="text" name="pekerjaan" class="form-control" value="${w.pekerjaan || ''}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Perkawinan</label>
                                <select name="status_perkawinan" class="form-select">
                                    <option value="Belum Kawin" ${w.status_perkawinan === 'Belum Kawin' ? 'selected' : ''}>Belum Kawin</option>
                                    <option value="Kawin" ${w.status_perkawinan === 'Kawin' ? 'selected' : ''}>Kawin</option>
                                    <option value="Cerai Hidup" ${w.status_perkawinan === 'Cerai Hidup' ? 'selected' : ''}>Cerai Hidup</option>
                                    <option value="Cerai Mati" ${w.status_perkawinan === 'Cerai Mati' ? 'selected' : ''}>Cerai Mati</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="2">${w.alamat || ''}</textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">RT</label>
                                <input type="text" name="rt" class="form-control" value="${w.rt || ''}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">RW</label>
                                <input type="text" name="rw" class="form-control" value="${w.rw || ''}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">No. Telepon</label>
                                <input type="text" name="no_telepon" class="form-control" value="${w.no_telepon || ''}">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" class="form-control" value="${w.latitude || ''}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" class="form-control" value="${w.longitude || ''}">
                            </div>
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('editModal')).show();
                }
            });
        }
        
        function hapusWarga(id, nama) {
            document.getElementById('hapus_id_warga').value = id;
            document.getElementById('hapus_nama').textContent = nama;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        }
    </script>
</body>
</html>