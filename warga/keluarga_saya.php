<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
checkWarga();

$user = getCurrentUser();
$warga = getWargaByUserId($user['id_user']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'tambah') {
            $nik = cleanInput($_POST['nik']);
            $nama_lengkap = cleanInput($_POST['nama_lengkap']);
            $tempat_lahir = cleanInput($_POST['tempat_lahir']);
            $tanggal_lahir = cleanInput($_POST['tanggal_lahir']);
            $jenis_kelamin = cleanInput($_POST['jenis_kelamin']);
            $status_keluarga = cleanInput($_POST['status_keluarga']);
            $agama = cleanInput($_POST['agama']);
            $pendidikan = cleanInput($_POST['pendidikan']);
            $pekerjaan = cleanInput($_POST['pekerjaan']);
            $status_perkawinan = cleanInput($_POST['status_perkawinan']);
            
            $check = $conn->prepare("SELECT id_warga FROM warga WHERE nik = ?");
            $check->bind_param("s", $nik);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = "NIK sudah terdaftar!";
            } else {
                $query = "INSERT INTO warga (nik, no_kk, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, 
                          status_keluarga, agama, pendidikan, pekerjaan, status_perkawinan, alamat, rt, rw, latitude, longitude) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssssssssssssdd", 
                    $nik, $warga['no_kk'], $nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin,
                    $status_keluarga, $agama, $pendidikan, $pekerjaan, $status_perkawinan,
                    $warga['alamat'], $warga['rt'], $warga['rw'], $warga['latitude'], $warga['longitude']
                );
                
                if ($stmt->execute()) {
                    $success = "Anggota keluarga berhasil ditambahkan!";
                    
                    $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Tambah Anggota Keluarga', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_desc = "Menambahkan anggota keluarga: $nama_lengkap";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                    $log_stmt->execute();
                } else {
                    $error = "Gagal menambahkan anggota keluarga!";
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $id_warga = cleanInput($_POST['id_warga']);
            $nama_lengkap = cleanInput($_POST['nama_lengkap']);
            $tempat_lahir = cleanInput($_POST['tempat_lahir']);
            $tanggal_lahir = cleanInput($_POST['tanggal_lahir']);
            $jenis_kelamin = cleanInput($_POST['jenis_kelamin']);
            $status_keluarga = cleanInput($_POST['status_keluarga']);
            $agama = cleanInput($_POST['agama']);
            $pendidikan = cleanInput($_POST['pendidikan']);
            $pekerjaan = cleanInput($_POST['pekerjaan']);
            $status_perkawinan = cleanInput($_POST['status_perkawinan']);
            
            $check = $conn->prepare("SELECT id_warga FROM warga WHERE id_warga = ? AND no_kk = ?");
            $check->bind_param("is", $id_warga, $warga['no_kk']);
            $check->execute();
            
            if ($check->get_result()->num_rows === 0) {
                $error = "Data tidak ditemukan atau bukan anggota keluarga Anda!";
            } else {
                $query = "UPDATE warga SET nama_lengkap = ?, tempat_lahir = ?, tanggal_lahir = ?, 
                          jenis_kelamin = ?, status_keluarga = ?, agama = ?, pendidikan = ?, 
                          pekerjaan = ?, status_perkawinan = ? WHERE id_warga = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssssssi", 
                    $nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin,
                    $status_keluarga, $agama, $pendidikan, $pekerjaan, $status_perkawinan, $id_warga
                );
                
                if ($stmt->execute()) {
                    $success = "Data anggota keluarga berhasil diupdate!";
                    
                    $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Edit Anggota Keluarga', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_desc = "Mengedit data anggota keluarga: $nama_lengkap";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                    $log_stmt->execute();
                } else {
                    $error = "Gagal mengupdate data!";
                }
            }
        } elseif ($_POST['action'] === 'hapus') {
            $id_warga = cleanInput($_POST['id_warga']);
            
            $check = $conn->prepare("SELECT status_keluarga, nama_lengkap FROM warga WHERE id_warga = ? AND no_kk = ?");
            $check->bind_param("is", $id_warga, $warga['no_kk']);
            $check->execute();
            $data = $check->get_result()->fetch_assoc();
            
            if (!$data) {
                $error = "Data tidak ditemukan atau bukan anggota keluarga Anda!";
            } elseif ($data['status_keluarga'] === 'Kepala Keluarga') {
                $error = "Kepala Keluarga tidak dapat dihapus!";
            } else {
                $query = "DELETE FROM warga WHERE id_warga = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $id_warga);
                
                if ($stmt->execute()) {
                    $success = "Anggota keluarga berhasil dihapus!";
                    
                    $log_query = "INSERT INTO activity_log (id_user, action, description, ip_address) VALUES (?, 'Hapus Anggota Keluarga', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_desc = "Menghapus anggota keluarga: {$data['nama_lengkap']}";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iss", $user['id_user'], $log_desc, $ip);
                    $log_stmt->execute();
                } else {
                    $error = "Gagal menghapus data!";
                }
            }
        }
    }
}

$query = "SELECT * FROM warga WHERE no_kk = ? ORDER BY 
          CASE status_keluarga 
              WHEN 'Kepala Keluarga' THEN 1
              WHEN 'Istri' THEN 2
              WHEN 'Anak' THEN 3
              ELSE 4
          END, tanggal_lahir ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $warga['no_kk']);
$stmt->execute();
$keluarga_list = $stmt->get_result();

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
    <title>Keluarga Saya - SISFO RT</title>
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
        .member-card {
            transition: transform 0.3s;
        }
        .member-card:hover {
            transform: translateY(-5px);
        }
        .member-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
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
                    <a class="nav-link active" href="keluarga_saya.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-users me-2"></i>Keluarga Saya</h2>
                        <p class="text-muted mb-0">Kelola data anggota keluarga</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                        <i class="fas fa-plus me-2"></i>Tambah Anggota
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
                
                <!-- Info Kartu Keluarga -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="fas fa-id-card me-2"></i>Informasi Kartu Keluarga</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="150">No. KK</td>
                                        <td>: <strong><?php echo e($warga['no_kk']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Alamat</td>
                                        <td>: <?php echo e($warga['alamat']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>RT / RW</td>
                                        <td>: <?php echo e($warga['rt']); ?> / <?php echo e($warga['rw']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-users me-2"></i>Jumlah Anggota Keluarga</h5>
                                <h1 class="display-3 text-primary"><?php echo $keluarga_list->num_rows; ?></h1>
                                <p class="text-muted">Orang terdaftar dalam KK ini</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daftar Anggota Keluarga -->
                <div class="row">
                    <?php if ($keluarga_list->num_rows > 0): ?>
                        <?php while($anggota = $keluarga_list->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card member-card h-100">
                                    <div class="card-body text-center">
                                        <img src="../uploads/profile/<?php echo e($anggota['foto_profil']); ?>" 
                                             class="member-img mb-3" 
                                             onerror="this.src='../assets/img/default.jpg'" 
                                             alt="Foto">
                                        
                                        <h5><?php echo e($anggota['nama_lengkap']); ?></h5>
                                        
                                        <?php if ($anggota['status_keluarga'] === 'Kepala Keluarga'): ?>
                                            <span class="badge bg-primary mb-2">Kepala Keluarga</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary mb-2"><?php echo e($anggota['status_keluarga']); ?></span>
                                        <?php endif; ?>
                                        
                                        <hr>
                                        
                                        <div class="text-start">
                                            <small class="text-muted d-block mb-1">
                                                <i class="fas fa-id-card me-2"></i>NIK: <?php echo e($anggota['nik']); ?>
                                            </small>
                                            <small class="text-muted d-block mb-1">
                                                <i class="fas fa-birthday-cake me-2"></i>
                                                <?php echo e($anggota['tempat_lahir']); ?>, 
                                                <?php echo formatTanggal($anggota['tanggal_lahir']); ?>
                                            </small>
                                            <small class="text-muted d-block mb-1">
                                                <i class="fas fa-venus-mars me-2"></i><?php echo e($anggota['jenis_kelamin']); ?>
                                            </small>
                                            <small class="text-muted d-block mb-1">
                                                <i class="fas fa-briefcase me-2"></i><?php echo e($anggota['pekerjaan']); ?>
                                            </small>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-sm btn-info" onclick="detailAnggota(<?php echo $anggota['id_warga']; ?>)">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </button>
                                            <?php if ($anggota['status_keluarga'] !== 'Kepala Keluarga'): ?>
                                                <button class="btn btn-sm btn-warning" onclick="editAnggota(<?php echo $anggota['id_warga']; ?>)">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="hapusAnggota(<?php echo $anggota['id_warga']; ?>, '<?php echo e($anggota['nama_lengkap']); ?>')">
                                                    <i class="fas fa-trash me-1"></i>Hapus
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle fa-3x mb-3"></i>
                                <h5>Belum ada anggota keluarga</h5>
                                <p class="mb-0">Klik tombol "Tambah Anggota" untuk menambahkan data keluarga</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Tambah -->
    <div class="modal fade" id="tambahModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Anggota Keluarga</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="tambah">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIK <span class="text-danger">*</span></label>
                                <input type="text" name="nik" class="form-control" maxlength="16" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama_lengkap" class="form-control" required>
                            </div>
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
                                <label class="form-label">Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="form-select">
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Keluarga <span class="text-danger">*</span></label>
                                <select name="status_keluarga" class="form-select" required>
                                    <option value="Istri">Istri</option>
                                    <option value="Anak">Anak</option>
                                    <option value="Orang Tua">Orang Tua</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Agama</label>
                                <select name="agama" class="form-select">
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
                                <input type="text" name="pendidikan" class="form-control" placeholder="SD/SMP/SMA/S1/dll">
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
                                    <option value="Belum Kawin">Belum Kawin</option>
                                    <option value="Kawin">Kawin</option>
                                    <option value="Cerai Hidup">Cerai Hidup</option>
                                    <option value="Cerai Mati">Cerai Mati</option>
                                </select>
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
                    <h5 class="modal-title">Edit Anggota Keluarga</h5>
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
                    <h5 class="modal-title">Detail Anggota Keluarga</h5>
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
                        <input type="hidden" name="id_warga" id="hapus_id_warga">
                        <p>Apakah Anda yakin ingin menghapus anggota keluarga:</p>
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
        function detailAnggota(id) {
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
                        <div class="text-center mb-4">
                            <img src="../uploads/profile/${w.foto_profil}" class="rounded-circle mb-3" width="150" height="150" onerror="this.src='../assets/img/default.jpg'" alt="Foto">
                            <h4>${w.nama_lengkap}</h4>
                            <span class="badge bg-primary">${w.status_keluarga}</span>
                        </div>
                        <table class="table table-bordered">
                            <tr><th width="200">NIK</th><td>${w.nik}</td></tr>
                            <tr><th>No. KK</th><td>${w.no_kk}</td></tr>
                            <tr><th>Tempat, Tanggal Lahir</th><td>${w.tempat_lahir}, ${w.tanggal_lahir}</td></tr>
                            <tr><th>Jenis Kelamin</th><td>${w.jenis_kelamin}</td></tr>
                            <tr><th>Agama</th><td>${w.agama}</td></tr>
                            <tr><th>Pendidikan</th><td>${w.pendidikan}</td></tr>
                            <tr><th>Pekerjaan</th><td>${w.pekerjaan}</td></tr>
                            <tr><th>Status Perkawinan</th><td>${w.status_perkawinan}</td></tr>
                            <tr><th>Alamat</th><td>${w.alamat}</td></tr>
                            <tr><th>RT / RW</th><td>${w.rt} / ${w.rw}</td></tr>
                            <tr><th>No. Telepon</th><td>${w.no_telepon || '-'}</td></tr>
                        </table>
                    `;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                }
            });
        }
        
        function editAnggota(id) {
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
                                <label class="form-label">NIK (tidak bisa diubah)</label>
                                <input type="text" class="form-control" value="${w.nik}" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama_lengkap" class="form-control" value="${w.nama_lengkap}" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" class="form-control" value="${w.tempat_lahir}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="form-control" value="${w.tanggal_lahir}">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="form-select">
                                    <option value="Laki-laki" ${w.jenis_kelamin === 'Laki-laki' ? 'selected' : ''}>Laki-laki</option>
                                    <option value="Perempuan" ${w.jenis_kelamin === 'Perempuan' ? 'selected' : ''}>Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Keluarga</label>
                                <select name="status_keluarga" class="form-select">
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
                                <input type="text" name="pendidikan" class="form-control" value="${w.pendidikan}">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pekerjaan</label>
                                <input type="text" name="pekerjaan" class="form-control" value="${w.pekerjaan}">
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
                    `;
                    new bootstrap.Modal(document.getElementById('editModal')).show();
                }
            });
        }
        
        function hapusAnggota(id, nama) {
            document.getElementById('hapus_id_warga').value = id;
            document.getElementById('hapus_nama').textContent = nama;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        }
    </script>
</body>
</html>