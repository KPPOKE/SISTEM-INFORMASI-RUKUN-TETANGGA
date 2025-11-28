<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
require_once '../includes/activity_log.php';

checkBendahara();

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_pengeluaran') {
            $keterangan = cleanInput($_POST['keterangan']);
            $nominal = floatval($_POST['nominal']);
            $kategori = cleanInput($_POST['kategori']);
            $tanggal_pengeluaran = cleanInput($_POST['tanggal_pengeluaran']);
            
            $bukti_pengeluaran = null;
            if (isset($_FILES['bukti_pengeluaran']) && $_FILES['bukti_pengeluaran']['error'] === 0) {
                $upload = uploadFile($_FILES['bukti_pengeluaran'], 'pengeluaran', ['jpg', 'jpeg', 'png', 'pdf']);
                if ($upload['status']) {
                    $bukti_pengeluaran = $upload['filename'];
                }
            }
            
            $query = "INSERT INTO pengeluaran (keterangan, nominal, kategori, tanggal_pengeluaran, bukti_pengeluaran, id_user) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sdsssi", $keterangan, $nominal, $kategori, $tanggal_pengeluaran, $bukti_pengeluaran, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'Tambah Pengeluaran', "Menambahkan pengeluaran: $keterangan - Nominal: " . formatRupiah($nominal));
                $_SESSION['success'] = 'Pengeluaran berhasil ditambahkan';
            } else {
                $_SESSION['error'] = 'Gagal menambahkan pengeluaran';
            }
            header("Location: pengeluaran.php");
            exit;
        }
        
        elseif ($_POST['action'] === 'edit_pengeluaran') {
            $id_pengeluaran = intval($_POST['id_pengeluaran']);
            $keterangan = cleanInput($_POST['keterangan']);
            $nominal = floatval($_POST['nominal']);
            $kategori = cleanInput($_POST['kategori']);
            $tanggal_pengeluaran = cleanInput($_POST['tanggal_pengeluaran']);
            
            $existing = $conn->query("SELECT bukti_pengeluaran FROM pengeluaran WHERE id_pengeluaran = $id_pengeluaran")->fetch_assoc();
            $bukti_pengeluaran = $existing['bukti_pengeluaran'];
            
            if (isset($_FILES['bukti_pengeluaran']) && $_FILES['bukti_pengeluaran']['error'] === 0) {
                $upload = uploadFile($_FILES['bukti_pengeluaran'], 'pengeluaran', ['jpg', 'jpeg', 'png', 'pdf']);
                if ($upload['status']) {
                    if ($bukti_pengeluaran && file_exists("../uploads/pengeluaran/$bukti_pengeluaran")) {
                        unlink("../uploads/pengeluaran/$bukti_pengeluaran");
                    }
                    $bukti_pengeluaran = $upload['filename'];
                }
            }
            
            $query = "UPDATE pengeluaran SET keterangan = ?, nominal = ?, kategori = ?, tanggal_pengeluaran = ?, bukti_pengeluaran = ? 
                      WHERE id_pengeluaran = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sdsssi", $keterangan, $nominal, $kategori, $tanggal_pengeluaran, $bukti_pengeluaran, $id_pengeluaran);
            
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'Edit Pengeluaran', "Mengedit pengeluaran ID: $id_pengeluaran");
                $_SESSION['success'] = 'Pengeluaran berhasil diupdate';
            } else {
                $_SESSION['error'] = 'Gagal update pengeluaran';
            }
            header("Location: pengeluaran.php");
            exit;
        }
        
        elseif ($_POST['action'] === 'delete_pengeluaran') {
            $id_pengeluaran = intval($_POST['id_pengeluaran']);
            
            $pengeluaran = $conn->query("SELECT bukti_pengeluaran FROM pengeluaran WHERE id_pengeluaran = $id_pengeluaran")->fetch_assoc();
            
            $query = "DELETE FROM pengeluaran WHERE id_pengeluaran = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_pengeluaran);
            
            if ($stmt->execute()) {
                if ($pengeluaran['bukti_pengeluaran'] && file_exists("../uploads/pengeluaran/{$pengeluaran['bukti_pengeluaran']}")) {
                    unlink("../uploads/pengeluaran/{$pengeluaran['bukti_pengeluaran']}");
                }
                logActivity($_SESSION['user_id'], 'Hapus Pengeluaran', "Menghapus pengeluaran ID: $id_pengeluaran");
                $_SESSION['success'] = 'Pengeluaran berhasil dihapus';
            } else {
                $_SESSION['error'] = 'Gagal menghapus pengeluaran';
            }
            header("Location: pengeluaran.php");
            exit;
        }
    }
}

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : '';

$query = "SELECT p.*, u.username 
          FROM pengeluaran p
          JOIN users u ON p.id_user = u.id_user
          WHERE DATE_FORMAT(p.tanggal_pengeluaran, '%Y-%m') = '$bulan'";

if ($kategori_filter) {
    $query .= " AND p.kategori = '$kategori_filter'";
}

$query .= " ORDER BY p.tanggal_pengeluaran DESC, p.created_at DESC";
$pengeluaran_list = $conn->query($query);

$total_query = "SELECT COALESCE(SUM(nominal), 0) as total FROM pengeluaran 
                WHERE DATE_FORMAT(tanggal_pengeluaran, '%Y-%m') = '$bulan'";
if ($kategori_filter) {
    $total_query .= " AND kategori = '$kategori_filter'";
}
$total_pengeluaran = $conn->query($total_query)->fetch_assoc()['total'];

$kategori_list = $conn->query("SELECT DISTINCT kategori FROM pengeluaran ORDER BY kategori");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengeluaran - SISFO RT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .summary-box {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
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
                        <i class="fas fa-wallet fa-3x"></i>
                    </div>
                    <h5>SISFO RT</h5>
                    <small>Panel Bendahara</small>
                </div>
                
                <hr class="bg-white">
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="iuran.php">
                        <i class="fas fa-money-bill-wave me-2"></i>Iuran
                    </a>
                    <a class="nav-link" href="pemasukan.php">
                       <i class="fas fa-hand-holding-usd me-2"></i>Pemasukan
                    </a>
                    <a class="nav-link active" href="pengeluaran.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-receipt me-2"></i>Kelola Pengeluaran</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahPengeluaran">
                        <i class="fas fa-plus me-2"></i>Tambah Pengeluaran
                    </button>
                </div>
                
                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Summary Box -->
                <div class="summary-box">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="mb-1">Total Pengeluaran</h6>
                            <h2 class="mb-0"><?php echo formatRupiah($total_pengeluaran); ?></h2>
                            <small>Periode: <?php echo date('F Y', strtotime($bulan . '-01')); ?></small>
                        </div>
                        <div class="col-md-4 text-end">
                            <i class="fas fa-arrow-up fa-4x opacity-50"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Filter -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Filter Bulan</label>
                                <input type="month" name="bulan" class="form-control" value="<?php echo $bulan; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Filter Kategori</label>
                                <select name="kategori" class="form-select" onchange="this.form.submit()">
                                    <option value="">Semua Kategori</option>
                                    <?php while($kat = $kategori_list->fetch_assoc()): ?>
                                        <option value="<?php echo e($kat['kategori']); ?>" <?php echo $kategori_filter === $kat['kategori'] ? 'selected' : ''; ?>>
                                            <?php echo e($kat['kategori']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <?php if ($kategori_filter): ?>
                                        <a href="?bulan=<?php echo $bulan; ?>" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i>Reset Filter
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Data Table -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Pengeluaran</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Keterangan</th>
                                        <th>Kategori</th>
                                        <th>Nominal</th>
                                        <th>Bukti</th>
                                        <th>Dicatat Oleh</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    if ($pengeluaran_list->num_rows > 0):
                                        while($pengeluaran = $pengeluaran_list->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo formatTanggal($pengeluaran['tanggal_pengeluaran']); ?></td>
                                            <td><strong><?php echo e($pengeluaran['keterangan']); ?></strong></td>
                                            <td><span class="badge bg-secondary"><?php echo e($pengeluaran['kategori']); ?></span></td>
                                            <td><strong class="text-danger"><?php echo formatRupiah($pengeluaran['nominal']); ?></strong></td>
                                            <td>
                                                <?php if ($pengeluaran['bukti_pengeluaran']): ?>
                                                    <?php
                                                    $ext = pathinfo($pengeluaran['bukti_pengeluaran'], PATHINFO_EXTENSION);
                                                    if (in_array($ext, ['jpg', 'jpeg', 'png'])):
                                                    ?>
                                                        <img src="../uploads/pengeluaran/<?php echo $pengeluaran['bukti_pengeluaran']; ?>" 
                                                             style="max-width: 100px; cursor: pointer;" 
                                                             onclick="showImage('../uploads/pengeluaran/<?php echo $pengeluaran['bukti_pengeluaran']; ?>')"
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#modalViewImage">
                                                    <?php else: ?>
                                                        <a href="../uploads/pengeluaran/<?php echo $pengeluaran['bukti_pengeluaran']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-file-pdf"></i> Lihat
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo e($pengeluaran['username']); ?></small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editPengeluaran(<?php echo htmlspecialchars(json_encode($pengeluaran)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deletePengeluaran(<?php echo $pengeluaran['id_pengeluaran']; ?>, '<?php echo e($pengeluaran['keterangan']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Tidak ada data pengeluaran</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Tambah Pengeluaran -->
    <div class="modal fade" id="modalTambahPengeluaran" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_pengeluaran">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Pengeluaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Keterangan *</label>
                            <input type="text" name="keterangan" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nominal *</label>
                            <input type="number" name="nominal" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori *</label>
                            <input type="text" name="kategori" class="form-control" list="kategoriList" required>
                            <datalist id="kategoriList">
                                <?php 
                                $conn->query("SELECT DISTINCT kategori FROM pengeluaran ORDER BY kategori");
                                $kategori_list2 = $conn->query("SELECT DISTINCT kategori FROM pengeluaran ORDER BY kategori");
                                while($kat = $kategori_list2->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo e($kat['kategori']); ?>">
                                <?php endwhile; ?>
                            </datalist>
                            <small class="text-muted">Contoh: Kebersihan, Keamanan, Listrik, dll.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Pengeluaran *</label>
                            <input type="date" name="tanggal_pengeluaran" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Bukti</label>
                            <input type="file" name="bukti_pengeluaran" class="form-control" accept="image/*,.pdf">
                            <small class="text-muted">Format: JPG, PNG, atau PDF (Max 5MB)</small>
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
    
    <!-- Modal Edit Pengeluaran -->
    <div class="modal fade" id="modalEditPengeluaran" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_pengeluaran">
                    <input type="hidden" name="id_pengeluaran" id="edit_id_pengeluaran">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Pengeluaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Keterangan *</label>
                            <input type="text" name="keterangan" id="edit_keterangan" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nominal *</label>
                            <input type="number" name="nominal" id="edit_nominal" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori *</label>
                            <input type="text" name="kategori" id="edit_kategori" class="form-control" list="kategoriList" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Pengeluaran *</label>
                            <input type="date" name="tanggal_pengeluaran" id="edit_tanggal" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Bukti Baru (kosongkan jika tidak diubah)</label>
                            <input type="file" name="bukti_pengeluaran" class="form-control" accept="image/*,.pdf">
                            <small class="text-muted" id="current_bukti"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal View Image -->
    <div class="modal fade" id="modalViewImage" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lihat Bukti</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="viewImage" src="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form Delete (hidden) -->
    <form id="formDelete" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_pengeluaran">
        <input type="hidden" name="id_pengeluaran" id="delete_id_pengeluaran">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editPengeluaran(data) {
            document.getElementById('edit_id_pengeluaran').value = data.id_pengeluaran;
            document.getElementById('edit_keterangan').value = data.keterangan;
            document.getElementById('edit_nominal').value = data.nominal;
            document.getElementById('edit_kategori').value = data.kategori;
            document.getElementById('edit_tanggal').value = data.tanggal_pengeluaran;
            document.getElementById('current_bukti').textContent = data.bukti_pengeluaran ? 'Bukti saat ini: ' + data.bukti_pengeluaran : '';
            
            new bootstrap.Modal(document.getElementById('modalEditPengeluaran')).show();
        }
        
        function deletePengeluaran(id, keterangan) {
            if (confirm('Hapus pengeluaran "' + keterangan + '"?')) {
                document.getElementById('delete_id_pengeluaran').value = id;
                document.getElementById('formDelete').submit();
            }
        }
        
        function showImage(src) {
            document.getElementById('viewImage').src = src;
        }
    </script>
</body>
</html>