<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
require_once '../includes/activity_log.php';

checkBendahara();

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_pemasukan') {
            $keterangan = cleanInput($_POST['keterangan']);
            $nominal = floatval($_POST['nominal']);
            $kategori = cleanInput($_POST['kategori']);
            $tanggal_pemasukan = cleanInput($_POST['tanggal_pemasukan']);
            
            $bukti_pemasukan = null;
            if (isset($_FILES['bukti_pemasukan']) && $_FILES['bukti_pemasukan']['error'] === 0) {
                $upload = uploadFile($_FILES['bukti_pemasukan'], 'pemasukan', ['jpg', 'jpeg', 'png', 'pdf']);
                if ($upload['status']) {
                    $bukti_pemasukan = $upload['filename'];
                }
            }
            
            $query = "INSERT INTO pemasukan (keterangan, nominal, kategori, tanggal_pemasukan, bukti_pemasukan, id_user) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sdsssi", $keterangan, $nominal, $kategori, $tanggal_pemasukan, $bukti_pemasukan, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'Tambah Pemasukan', "Menambahkan pemasukan: $keterangan - Nominal: " . formatRupiah($nominal));
                $_SESSION['success'] = 'Pemasukan berhasil ditambahkan';
            } else {
                $_SESSION['error'] = 'Gagal menambahkan pemasukan';
            }
            header("Location: pemasukan.php");
            exit;
        }
        
        elseif ($_POST['action'] === 'edit_pemasukan') {
            $id_pemasukan = intval($_POST['id_pemasukan']);
            $keterangan = cleanInput($_POST['keterangan']);
            $nominal = floatval($_POST['nominal']);
            $kategori = cleanInput($_POST['kategori']);
            $tanggal_pemasukan = cleanInput($_POST['tanggal_pemasukan']);
            
            $existing = $conn->query("SELECT bukti_pemasukan FROM pemasukan WHERE id_pemasukan = $id_pemasukan")->fetch_assoc();
            $bukti_pemasukan = $existing['bukti_pemasukan'];
            
            if (isset($_FILES['bukti_pemasukan']) && $_FILES['bukti_pemasukan']['error'] === 0) {
                $upload = uploadFile($_FILES['bukti_pemasukan'], 'pemasukan', ['jpg', 'jpeg', 'png', 'pdf']);
                if ($upload['status']) {
                    if ($bukti_pemasukan && file_exists("../uploads/pemasukan/$bukti_pemasukan")) {
                        unlink("../uploads/pemasukan/$bukti_pemasukan");
                    }
                    $bukti_pemasukan = $upload['filename'];
                }
            }
            
            $query = "UPDATE pemasukan SET keterangan = ?, nominal = ?, kategori = ?, tanggal_pemasukan = ?, bukti_pemasukan = ? 
                      WHERE id_pemasukan = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sdsssi", $keterangan, $nominal, $kategori, $tanggal_pemasukan, $bukti_pemasukan, $id_pemasukan);
            
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'Edit Pemasukan', "Mengedit pemasukan ID: $id_pemasukan");
                $_SESSION['success'] = 'Pemasukan berhasil diupdate';
            } else {
                $_SESSION['error'] = 'Gagal update pemasukan';
            }
            header("Location: pemasukan.php");
            exit;
        }
        
        elseif ($_POST['action'] === 'delete_pemasukan') {
            $id_pemasukan = intval($_POST['id_pemasukan']);
            
            $pemasukan = $conn->query("SELECT bukti_pemasukan FROM pemasukan WHERE id_pemasukan = $id_pemasukan")->fetch_assoc();
            
            $query = "DELETE FROM pemasukan WHERE id_pemasukan = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_pemasukan);
            
            if ($stmt->execute()) {
                if ($pemasukan['bukti_pemasukan'] && file_exists("../uploads/pemasukan/{$pemasukan['bukti_pemasukan']}")) {
                    unlink("../uploads/pemasukan/{$pemasukan['bukti_pemasukan']}");
                }
                logActivity($_SESSION['user_id'], 'Hapus Pemasukan', "Menghapus pemasukan ID: $id_pemasukan");
                $_SESSION['success'] = 'Pemasukan berhasil dihapus';
            } else {
                $_SESSION['error'] = 'Gagal menghapus pemasukan';
            }
            header("Location: pemasukan.php");
            exit;
        }
    }
}

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : '';

$query = "SELECT p.*, u.username 
          FROM pemasukan p
          JOIN users u ON p.id_user = u.id_user
          WHERE DATE_FORMAT(p.tanggal_pemasukan, '%Y-%m') = '$bulan'";

if ($kategori_filter) {
    $query .= " AND p.kategori = '$kategori_filter'";
}

$query .= " ORDER BY p.tanggal_pemasukan DESC, p.created_at DESC";
$pemasukan_list = $conn->query($query);

$total_query = "SELECT COALESCE(SUM(nominal), 0) as total FROM pemasukan 
                WHERE DATE_FORMAT(tanggal_pemasukan, '%Y-%m') = '$bulan'";
if ($kategori_filter) {
    $total_query .= " AND kategori = '$kategori_filter'";
}
$total_pemasukan_manual = $conn->query($total_query)->fetch_assoc()['total'];

$iuran_query = "SELECT COALESCE(SUM(ip.nominal), 0) as total 
                FROM iuran_pembayaran ipb
                JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
                WHERE ipb.status = 'valid' 
                AND DATE_FORMAT(ipb.verified_at, '%Y-%m') = '$bulan'";
$total_iuran = $conn->query($iuran_query)->fetch_assoc()['total'];

$total_pemasukan = $total_pemasukan_manual + $total_iuran;

$kategori_list = $conn->query("SELECT DISTINCT kategori FROM pemasukan ORDER BY kategori");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pemasukan - SISFO RT</title>
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
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            color: white;
        }
        .summary-box.pemasukan {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .summary-box.iuran {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
    </style>
</head>
<body>
     <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-4 no-print">
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
                    <a class="nav-link active" href="pemasukan.php">
                       <i class="fas fa-hand-holding-usd me-2"></i>Pemasukan
                    </a>
                    <a class="nav-link" href="pengeluaran.php">
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
                    <h2><i class="fas fa-arrow-down me-2"></i>Kelola Pemasukan</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahPemasukan">
                        <i class="fas fa-plus me-2"></i>Tambah Pemasukan
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
                
                <!-- Summary Boxes -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="summary-box pemasukan">
                            <h6 class="mb-1">Pemasukan Manual</h6>
                            <h3 class="mb-0"><?php echo formatRupiah($total_pemasukan_manual); ?></h3>
                            <small>Donasi, Saldo Awal, dll</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-box iuran">
                            <h6 class="mb-1">Pemasukan dari Iuran</h6>
                            <h3 class="mb-0"><?php echo formatRupiah($total_iuran); ?></h3>
                            <small>Iuran warga yang sudah valid</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-box" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <h6 class="mb-1">Total Pemasukan</h6>
                            <h2 class="mb-0"><?php echo formatRupiah($total_pemasukan); ?></h2>
                            <small>Periode: <?php echo date('F Y', strtotime($bulan . '-01')); ?></small>
                        </div>
                    </div>
                </div>
                
                <!-- Filter -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Filter Bulan</label>
                                <input type="month" name="bulan" class="form-control" value="<?php echo $bulan; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-5">
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
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <?php if ($kategori_filter): ?>
                                        <a href="?bulan=<?php echo $bulan; ?>" class="btn btn-secondary w-100">
                                            <i class="fas fa-times me-2"></i>Reset
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
                        <h5 class="mb-0">Daftar Pemasukan Manual</h5>
                        <small class="text-muted">Pemasukan selain dari iuran warga</small>
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
                                    if ($pemasukan_list->num_rows > 0):
                                        while($pemasukan = $pemasukan_list->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo formatTanggal($pemasukan['tanggal_pemasukan']); ?></td>
                                            <td><strong><?php echo e($pemasukan['keterangan']); ?></strong></td>
                                            <td><span class="badge bg-success"><?php echo e($pemasukan['kategori']); ?></span></td>
                                            <td><strong class="text-success"><?php echo formatRupiah($pemasukan['nominal']); ?></strong></td>
                                            <td>
                                                <?php if ($pemasukan['bukti_pemasukan']): ?>
                                                    <?php
                                                    $ext = pathinfo($pemasukan['bukti_pemasukan'], PATHINFO_EXTENSION);
                                                    if (in_array($ext, ['jpg', 'jpeg', 'png'])):
                                                    ?>
                                                        <img src="../uploads/pemasukan/<?php echo $pemasukan['bukti_pemasukan']; ?>" 
                                                             style="max-width: 100px; cursor: pointer;" 
                                                             onclick="showImage('../uploads/pemasukan/<?php echo $pemasukan['bukti_pemasukan']; ?>')"
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#modalViewImage">
                                                    <?php else: ?>
                                                        <a href="../uploads/pemasukan/<?php echo $pemasukan['bukti_pemasukan']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-file-pdf"></i> Lihat
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo e($pemasukan['username']); ?></small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editPemasukan(<?php echo htmlspecialchars(json_encode($pemasukan)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deletePemasukan(<?php echo $pemasukan['id_pemasukan']; ?>, '<?php echo e($pemasukan['keterangan']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Tidak ada data pemasukan manual</td>
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
    
    <!-- Modal Tambah Pemasukan -->
    <div class="modal fade" id="modalTambahPemasukan" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_pemasukan">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Pemasukan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Kategori *</label>
                            <select name="kategori" class="form-select" required>
                                <option value="">Pilih Kategori</option>
                                <option value="Saldo Awal">Saldo Awal</option>
                                <option value="Donasi">Donasi</option>
                                <option value="Bantuan">Bantuan</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan *</label>
                            <textarea name="keterangan" class="form-control" rows="2" required placeholder="Contoh: Donasi dari Pak RT untuk..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nominal *</label>
                            <input type="number" name="nominal" class="form-control" required placeholder="Contoh: 500000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Pemasukan *</label>
                            <input type="date" name="tanggal_pemasukan" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Bukti</label>
                            <input type="file" name="bukti_pemasukan" class="form-control" accept="image/*,.pdf">
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
    
    <!-- Modal Edit Pemasukan -->
    <div class="modal fade" id="modalEditPemasukan" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_pemasukan">
                    <input type="hidden" name="id_pemasukan" id="edit_id_pemasukan">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Pemasukan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Kategori *</label>
                            <select name="kategori" id="edit_kategori" class="form-select" required>
                                <option value="">Pilih Kategori</option>
                                <option value="Saldo Awal">Saldo Awal</option>
                                <option value="Donasi">Donasi</option>
                                <option value="Bantuan">Bantuan</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan *</label>
                            <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nominal *</label>
                            <input type="number" name="nominal" id="edit_nominal" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Pemasukan *</label>
                            <input type="date" name="tanggal_pemasukan" id="edit_tanggal" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Bukti Baru (kosongkan jika tidak diubah)</label>
                            <input type="file" name="bukti_pemasukan" class="form-control" accept="image/*,.pdf">
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
        <input type="hidden" name="action" value="delete_pemasukan">
        <input type="hidden" name="id_pemasukan" id="delete_id_pemasukan">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editPemasukan(data) {
            document.getElementById('edit_id_pemasukan').value = data.id_pemasukan;
            document.getElementById('edit_kategori').value = data.kategori;
            document.getElementById('edit_keterangan').value = data.keterangan;
            document.getElementById('edit_nominal').value = data.nominal;
            document.getElementById('edit_tanggal').value = data.tanggal_pemasukan;
            
            if (data.bukti_pemasukan) {
                document.getElementById('current_bukti').innerHTML = 'File saat ini: ' + data.bukti_pemasukan;
            } else {
                document.getElementById('current_bukti').innerHTML = '';
            }
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditPemasukan'));
            modal.show();
        }
        
        function deletePemasukan(id, keterangan) {
            if (confirm('Apakah Anda yakin ingin menghapus pemasukan: ' + keterangan + '?')) {
                document.getElementById('delete_id_pemasukan').value = id;
                document.getElementById('formDelete').submit();
            }
        }
        
        function showImage(src) {
            document.getElementById('viewImage').src = src;
        }
    </script>
</body>
</html>