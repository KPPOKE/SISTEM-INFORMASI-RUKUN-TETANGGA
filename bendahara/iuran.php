<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
require_once '../includes/activity_log.php';
checkBendahara();

$user = getCurrentUser();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_iuran') {
            $judul = cleanInput($_POST['judul']);
            $deskripsi = cleanInput($_POST['deskripsi']);
            $nominal = floatval($_POST['nominal']);
            $periode = cleanInput($_POST['periode']);
            $deadline = !empty($_POST['deadline']) ? cleanInput($_POST['deadline']) : null;
            
            if (empty($judul) || $nominal <= 0 || empty($periode)) {
                $error = 'Judul, nominal, dan periode harus diisi dengan benar';
            } else {
                $qris_image = null;
                if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === 0) {
                    $upload = uploadFile($_FILES['qris_image'], 'iuran', ['jpg', 'jpeg', 'png']);
                    if ($upload['status']) {
                        $qris_image = $upload['filename'];
                    } else {
                        $error = $upload['message'];
                    }
                }
                
                if (empty($error)) {
                    $query = "INSERT INTO iuran_posting (judul, deskripsi, nominal, periode, qris_image, deadline) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssdsss", $judul, $deskripsi, $nominal, $periode, $qris_image, $deadline);
                    
                    if ($stmt->execute()) {
                        logActivity($_SESSION['user_id'], 'Tambah Iuran', "Menambahkan iuran: $judul - Periode: $periode");
                        $success = 'Iuran berhasil ditambahkan';
                    } else {
                        $error = 'Gagal menambahkan iuran: ' . $conn->error;
                    }
                }
            }
        }
        
        elseif ($_POST['action'] === 'edit_iuran') {
            $id_iuran = intval($_POST['id_iuran']);
            $judul = cleanInput($_POST['judul']);
            $deskripsi = cleanInput($_POST['deskripsi']);
            $nominal = floatval($_POST['nominal']);
            $periode = cleanInput($_POST['periode']);
            $deadline = !empty($_POST['deadline']) ? cleanInput($_POST['deadline']) : null;
            
            if (empty($judul) || $nominal <= 0 || empty($periode) || $id_iuran <= 0) {
                $error = 'Data tidak valid';
            } else {
                $query_existing = "SELECT qris_image FROM iuran_posting WHERE id_iuran_posting = ?";
                $stmt_existing = $conn->prepare($query_existing);
                $stmt_existing->bind_param("i", $id_iuran);
                $stmt_existing->execute();
                $result = $stmt_existing->get_result();
                
                if ($result->num_rows > 0) {
                    $existing = $result->fetch_assoc();
                    $qris_image = $existing['qris_image'];
                    
                    if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === 0) {
                        $upload = uploadFile($_FILES['qris_image'], 'iuran', ['jpg', 'jpeg', 'png']);
                        if ($upload['status']) {
                            if ($qris_image && file_exists("../uploads/iuran/$qris_image")) {
                                unlink("../uploads/iuran/$qris_image");
                            }
                            $qris_image = $upload['filename'];
                        } else {
                            $error = $upload['message'];
                        }
                    }
                    
                    if (empty($error)) {
                        $query = "UPDATE iuran_posting SET judul = ?, deskripsi = ?, nominal = ?, periode = ?, qris_image = ?, deadline = ? 
                                  WHERE id_iuran_posting = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("ssdsssi", $judul, $deskripsi, $nominal, $periode, $qris_image, $deadline, $id_iuran);
                        
                        if ($stmt->execute()) {
                            logActivity($_SESSION['user_id'], 'Edit Iuran', "Mengedit iuran ID: $id_iuran");
                            $success = 'Iuran berhasil diupdate';
                        } else {
                            $error = 'Gagal update iuran: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Data iuran tidak ditemukan';
                }
            }
        }
        
        elseif ($_POST['action'] === 'delete_iuran') {
            $id_iuran = intval($_POST['id_iuran']);
            
            if ($id_iuran <= 0) {
                $error = 'ID tidak valid';
            } else {
                $check_query = "SELECT COUNT(*) as total FROM iuran_pembayaran WHERE id_iuran_posting = ?";
                $stmt_check = $conn->prepare($check_query);
                $stmt_check->bind_param("i", $id_iuran);
                $stmt_check->execute();
                $check_result = $stmt_check->get_result()->fetch_assoc();
                
                if ($check_result['total'] > 0) {
                    $error = 'Tidak dapat menghapus iuran yang sudah memiliki pembayaran';
                } else {
                    $query_get = "SELECT qris_image FROM iuran_posting WHERE id_iuran_posting = ?";
                    $stmt_get = $conn->prepare($query_get);
                    $stmt_get->bind_param("i", $id_iuran);
                    $stmt_get->execute();
                    $result = $stmt_get->get_result();
                    
                    if ($result->num_rows > 0) {
                        $iuran = $result->fetch_assoc();
                        
                        $query = "DELETE FROM iuran_posting WHERE id_iuran_posting = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $id_iuran);
                        
                        if ($stmt->execute()) {
                            if ($iuran['qris_image'] && file_exists("../uploads/iuran/{$iuran['qris_image']}")) {
                                unlink("../uploads/iuran/{$iuran['qris_image']}");
                            }
                            logActivity($_SESSION['user_id'], 'Hapus Iuran', "Menghapus iuran ID: $id_iuran");
                            $success = 'Iuran berhasil dihapus';
                        } else {
                            $error = 'Gagal menghapus iuran: ' . $conn->error;
                        }
                    } else {
                        $error = 'Data iuran tidak ditemukan';
                    }
                }
            }
        }
        
        elseif ($_POST['action'] === 'verifikasi') {
            $id_pembayaran = intval($_POST['id_pembayaran']);
            $status = cleanInput($_POST['status']);
            $catatan = isset($_POST['catatan']) ? cleanInput($_POST['catatan']) : null;
            
            if ($id_pembayaran <= 0 || !in_array($status, ['valid', 'rejected'])) {
                $error = 'Data verifikasi tidak valid';
            } else {
                $query = "UPDATE iuran_pembayaran 
                          SET status = ?, catatan = ?, verified_at = NOW(), verified_by = ?
                          WHERE id_pembayaran = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssii", $status, $catatan, $_SESSION['user_id'], $id_pembayaran);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        logActivity($_SESSION['user_id'], 'Verifikasi Pembayaran', "Verifikasi pembayaran ID: $id_pembayaran - Status: $status");
                        $success = 'Pembayaran berhasil diverifikasi';
                    } else {
                        $error = 'Data pembayaran tidak ditemukan atau sudah diverifikasi';
                    }
                } else {
                    $error = 'Gagal verifikasi pembayaran: ' . $conn->error;
                }
            }
        }
    }
}

$active_tab = isset($_GET['tab']) ? cleanInput($_GET['tab']) : 'posting';
if (!in_array($active_tab, ['posting', 'verifikasi', 'status'])) {
    $active_tab = 'posting';
}

$query_iuran = "SELECT * FROM iuran_posting ORDER BY created_at DESC";
$iuran_list = $conn->query($query_iuran);

$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : 'pending';
if (!in_array($status_filter, ['pending', 'valid', 'rejected'])) {
    $status_filter = 'pending';
}

$query_pembayaran = "SELECT ipb.*, ip.judul, ip.nominal, w.nama_lengkap, w.no_telepon, w.alamat,
                      u.username as verified_by_name
                      FROM iuran_pembayaran ipb
                      JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
                      JOIN warga w ON ipb.id_warga = w.id_warga
                      LEFT JOIN users u ON ipb.verified_by = u.id_user
                      WHERE ipb.status = ?
                      ORDER BY ipb.tanggal_bayar DESC";
$stmt_pembayaran = $conn->prepare($query_pembayaran);
$stmt_pembayaran->bind_param("s", $status_filter);
$stmt_pembayaran->execute();
$pembayaran_list = $stmt_pembayaran->get_result();

$query_iuran_dropdown = "SELECT id_iuran_posting, judul, periode FROM iuran_posting ORDER BY created_at DESC LIMIT 10";
$iuran_dropdown = $conn->query($query_iuran_dropdown);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Iuran - SISFO RT</title>
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
        .badge-status {
            padding: 8px 15px;
            font-size: 0.85rem;
        }
        .bukti-transfer {
            max-width: 200px;
            cursor: pointer;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        .bukti-transfer:hover {
            transform: scale(1.05);
        }
        .warga-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .warga-item:last-child {
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
                    <a class="nav-link active" href="iuran.php">
                        <i class="fas fa-money-bill-wave me-2"></i>Iuran
                    </a>
                    <a class="nav-link" href="pemasukan.php">
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
                    <h2><i class="fas fa-money-bill-wave me-2"></i>Kelola Iuran</h2>
                </div>
                
                <!-- Alert Messages -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'posting' ? 'active' : ''; ?>" href="?tab=posting">
                            <i class="fas fa-list me-2"></i>Posting Iuran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'verifikasi' ? 'active' : ''; ?>" href="?tab=verifikasi">
                            <i class="fas fa-check-circle me-2"></i>Verifikasi Pembayaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'status' ? 'active' : ''; ?>" href="?tab=status">
                            <i class="fas fa-users me-2"></i>Status Pembayaran Warga
                        </a>
                    </li>
                </ul>
                
                <!-- TAB 1: POSTING IURAN -->
                <?php if ($active_tab === 'posting'): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Daftar Iuran</h5>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahIuran">
                                    <i class="fas fa-plus me-2"></i>Tambah Iuran
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($iuran_list->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Judul</th>
                                                <th>Nominal</th>
                                                <th>Periode</th>
                                                <th>Deadline</th>
                                                <th>QRIS</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            $iuran_list->data_seek(0);
                                            while($iuran = $iuran_list->fetch_assoc()): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td>
                                                        <strong><?php echo e($iuran['judul']); ?></strong>
                                                        <?php if ($iuran['deskripsi']): ?>
                                                            <br><small class="text-muted"><?php echo e($iuran['deskripsi']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong class="text-success"><?php echo formatRupiah($iuran['nominal']); ?></strong></td>
                                                    <td><?php echo e($iuran['periode']); ?></td>
                                                    <td>
                                                        <?php if ($iuran['deadline']): ?>
                                                            <?php echo formatTanggal($iuran['deadline']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($iuran['qris_image']): ?>
                                                            <img src="../uploads/iuran/<?php echo e($iuran['qris_image']); ?>" 
                                                                 class="bukti-transfer" 
                                                                 data-bs-toggle="modal" 
                                                                 data-bs-target="#modalViewImage"
                                                                 onclick="showImage('../uploads/iuran/<?php echo e($iuran['qris_image']); ?>')"
                                                                 alt="QRIS">
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning mb-1" onclick='editIuran(<?php echo json_encode($iuran); ?>)'>
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteIuran(<?php echo $iuran['id_iuran_posting']; ?>, '<?php echo addslashes($iuran['judul']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada data iuran</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- TAB 2: VERIFIKASI PEMBAYARAN -->
                <?php if ($active_tab === 'verifikasi'): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Verifikasi Pembayaran</h5>
                                <div class="btn-group">
                                    <a href="?tab=verifikasi&status=pending" class="btn btn-sm btn-outline-warning <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                                        Pending
                                    </a>
                                    <a href="?tab=verifikasi&status=valid" class="btn btn-sm btn-outline-success <?php echo $status_filter === 'valid' ? 'active' : ''; ?>">
                                        Valid
                                    </a>
                                    <a href="?tab=verifikasi&status=rejected" class="btn btn-sm btn-outline-danger <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                                        Rejected
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($pembayaran_list->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Warga</th>
                                                <th>Iuran</th>
                                                <th>Nominal</th>
                                                <th>Tanggal Bayar</th>
                                                <th>Bukti</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            while($bayar = $pembayaran_list->fetch_assoc()): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td>
                                                        <strong><?php echo e($bayar['nama_lengkap']); ?></strong>
                                                        <br><small class="text-muted"><?php echo e($bayar['no_telepon']); ?></small>
                                                    </td>
                                                    <td><?php echo e($bayar['judul']); ?></td>
                                                    <td><strong class="text-success"><?php echo formatRupiah($bayar['nominal']); ?></strong></td>
                                                    <td><?php echo formatTanggal($bayar['tanggal_bayar']); ?></td>
                                                    <td>
                                                        <img src="../uploads/iuran/<?php echo e($bayar['bukti_transfer']); ?>" 
                                                             class="bukti-transfer" 
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#modalViewImage"
                                                             onclick="showImage('../uploads/iuran/<?php echo e($bayar['bukti_transfer']); ?>')"
                                                             alt="Bukti Transfer">
                                                    </td>
                                                    <td>
                                                        <?php if ($bayar['status'] === 'pending'): ?>
                                                            <span class="badge bg-warning text-dark badge-status">Pending</span>
                                                        <?php elseif ($bayar['status'] === 'valid'): ?>
                                                            <span class="badge bg-success badge-status">Valid</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger badge-status">Rejected</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($bayar['status'] === 'pending'): ?>
                                                            <button class="btn btn-sm btn-success mb-1" onclick="verifikasi(<?php echo $bayar['id_pembayaran']; ?>, 'valid')">
                                                                <i class="fas fa-check"></i> Valid
                                                            </button>
                                                            <button class="btn btn-sm btn-danger" onclick="verifikasi(<?php echo $bayar['id_pembayaran']; ?>, 'rejected')">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        <?php else: ?>
                                                            <small class="text-muted">
                                                                Oleh: <?php echo e($bayar['verified_by_name']); ?>
                                                                <br><?php echo formatTanggal($bayar['verified_at']); ?>
                                                                <?php if ($bayar['catatan']): ?>
                                                                    <br><em>"<?php echo e($bayar['catatan']); ?>"</em>
                                                                <?php endif; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada pembayaran dengan status <?php echo $status_filter; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- TAB 3: STATUS PEMBAYARAN WARGA (BARU) -->
                <?php if ($active_tab === 'status'): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Status Pembayaran Warga</h5>
                            <small class="text-muted">Lihat status pembayaran semua warga untuk iuran tertentu. Anda bisa tandai lunas manual untuk pembayaran tunai.</small>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label class="form-label">Pilih Periode Iuran:</label>
                                <select class="form-select" id="selectIuran" onchange="loadStatusWarga(this.value)">
                                    <option value="">-- Pilih Iuran --</option>
                                    <?php while($iuran = $iuran_dropdown->fetch_assoc()): ?>
                                        <option value="<?php echo $iuran['id_iuran_posting']; ?>">
                                            <?php echo e($iuran['judul']); ?> - <?php echo e($iuran['periode']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div id="statusWargaContent">
                                <div class="text-center py-5">
                                    <i class="fas fa-arrow-up fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Pilih periode iuran untuk melihat status pembayaran warga</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Tambah Iuran -->
    <div class="modal fade" id="modalTambahIuran" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="formTambahIuran">
                    <input type="hidden" name="action" value="add_iuran">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Iuran Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Judul Iuran *</label>
                            <input type="text" name="judul" class="form-control" required maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nominal *</label>
                            <input type="number" name="nominal" class="form-control" required min="0" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Periode (YYYY-MM) *</label>
                            <input type="month" name="periode" class="form-control" required value="<?php echo date('Y-m'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deadline</label>
                            <input type="date" name="deadline" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload QRIS</label>
                            <input type="file" name="qris_image" class="form-control" accept="image/*">
                            <small class="text-muted">Format: JPG, JPEG, PNG. Max 5MB</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Iuran -->
    <div class="modal fade" id="modalEditIuran" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="formEditIuran">
                    <input type="hidden" name="action" value="edit_iuran">
                    <input type="hidden" name="id_iuran" id="edit_id_iuran">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Iuran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Judul Iuran *</label>
                            <input type="text" name="judul" id="edit_judul" class="form-control" required maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nominal *</label>
                            <input type="number" name="nominal" id="edit_nominal" class="form-control" required min="1" step="1000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Periode (YYYY-MM) *</label>
                            <input type="month" name="periode" id="edit_periode" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deadline</label>
                            <input type="date" name="deadline" id="edit_deadline" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload QRIS Baru (opsional)</label>
                            <input type="file" name="qris_image" class="form-control" accept="image/*">
                            <small class="text-muted">Biarkan kosong jika tidak ingin mengganti.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Lihat Gambar -->
    <div class="modal fade" id="modalViewImage" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <img id="previewImage" src="" class="img-fluid rounded">
            </div>
        </div>
    </div>
    
    <!-- Modal Tandai Lunas Manual -->
    <div class="modal fade" id="modalTandaiLunas" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Tandai Lunas (Pembayaran Tunai)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="lunas_id_iuran">
                    <input type="hidden" id="lunas_id_warga">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Anda akan menandai pembayaran sebagai <strong>LUNAS</strong> untuk:
                    </div>
                    
                    <h6 id="lunas_nama_warga" class="mb-3"></h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea id="lunas_catatan" class="form-control" rows="3" placeholder="Contoh: Bayar tunai ke bendahara tanggal 23 November 2024"></textarea>
                        <small class="text-muted">Catatan akan tersimpan sebagai bukti pembayaran tunai</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" onclick="konfirmasiTandaiLunas()">
                        <i class="fas fa-check me-2"></i>Tandai Lunas
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showImage(src) {
            document.getElementById('previewImage').src = src;
        }
        
        function editIuran(data) {
            document.getElementById("edit_id_iuran").value = data.id_iuran_posting;
            document.getElementById("edit_judul").value = data.judul;
            document.getElementById("edit_deskripsi").value = data.deskripsi;
            document.getElementById("edit_nominal").value = data.nominal;
            document.getElementById("edit_periode").value = data.periode;
            document.getElementById("edit_deadline").value = data.deadline;
            new bootstrap.Modal(document.getElementById("modalEditIuran")).show();
        }
        
        function deleteIuran(id, judul) {
            if (confirm("Yakin ingin menghapus iuran:\n" + judul + " ?")) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_iuran">
                    <input type="hidden" name="id_iuran" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function verifikasi(id, status) {
            let catatan = "";
            if (status === "rejected") {
                catatan = prompt("Masukkan alasan penolakan (opsional):") || "";
            }
            const form = document.createElement("form");
            form.method = "POST";
            form.innerHTML = `
                <input type="hidden" name="action" value="verifikasi">
                <input type="hidden" name="id_pembayaran" value="${id}">
                <input type="hidden" name="status" value="${status}">
                <input type="hidden" name="catatan" value="${catatan}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function loadStatusWarga(idIuran) {
            if (!idIuran) {
                document.getElementById('statusWargaContent').innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-arrow-up fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Pilih periode iuran untuk melihat status pembayaran warga</p>
                    </div>
                `;
                return;
            }
            
            document.getElementById('statusWargaContent').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-3">Memuat data...</p>
                </div>
            `;
            
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_status_pembayaran_warga&id_iuran=' + idIuran
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    let html = '<div class="alert alert-info"><strong>' + data.iuran_info + '</strong></div>';
                    html += '<div class="row mb-3">';
                    html += '<div class="col-md-4"><div class="card bg-success text-white"><div class="card-body text-center"><h3>' + data.sudah_bayar + '</h3><p class="mb-0">Sudah Bayar</p></div></div></div>';
                    html += '<div class="col-md-4"><div class="card bg-warning text-white"><div class="card-body text-center"><h3>' + data.pending + '</h3><p class="mb-0">Pending</p></div></div></div>';
                    html += '<div class="col-md-4"><div class="card bg-danger text-white"><div class="card-body text-center"><h3>' + data.belum_bayar + '</h3><p class="mb-0">Belum Bayar</p></div></div></div>';
                    html += '</div>';
                    
                    if (data.warga && data.warga.length > 0) {
                        html += '<div class="table-responsive">';
                        html += '<table class="table table-hover">';
                        html += '<thead><tr><th>No</th><th>Nama KK</th><th>Alamat</th><th>Status</th><th>Aksi</th></tr></thead>';
                        html += '<tbody>';
                        
                        let no = 1;
                        data.warga.forEach(w => {
                            let badgeClass = 'secondary';
                            let badgeText = 'Belum Bayar';
                            let aksi = '';
                            
                            if (w.status === 'valid') {
                                badgeClass = 'success';
                                badgeText = 'Lunas';
                                aksi = '<small class="text-muted">Verified: ' + w.verified_at + '</small>';
                            } else if (w.status === 'pending') {
                                badgeClass = 'warning';
                                badgeText = 'Pending Verifikasi';
                                aksi = '<a href="?tab=verifikasi&status=pending" class="btn btn-sm btn-warning"><i class="fas fa-eye me-1"></i>Lihat</a>';
                            } else {
                                aksi = '<button class="btn btn-sm btn-success" onclick="tandaiLunas(' + idIuran + ', ' + w.id_warga + ', \'' + w.nama_lengkap.replace(/'/g, "\\'") + '\')"><i class="fas fa-check me-1"></i>Tandai Lunas</button>';
                            }
                            
                            html += `
                                <tr>
                                    <td>${no++}</td>
                                    <td><strong>${w.nama_lengkap}</strong><br><small class="text-muted">${w.no_kk}</small></td>
                                    <td>${w.alamat}</td>
                                    <td><span class="badge bg-${badgeClass}">${badgeText}</span></td>
                                    <td>${aksi}</td>
                                </tr>
                            `;
                        });
                        
                        html += '</tbody></table></div>';
                    } else {
                        html += '<p class="text-center text-muted py-5">Tidak ada data warga</p>';
                    }
                    
                    document.getElementById('statusWargaContent').innerHTML = html;
                } else {
                    document.getElementById('statusWargaContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>${data.message || 'Gagal memuat data'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('statusWargaContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Terjadi kesalahan: ${error.message}
                    </div>
                `;
            });
        }
        
        function tandaiLunas(idIuran, idWarga, namaWarga) {
            document.getElementById('lunas_id_iuran').value = idIuran;
            document.getElementById('lunas_id_warga').value = idWarga;
            document.getElementById('lunas_nama_warga').textContent = namaWarga;
            document.getElementById('lunas_catatan').value = '';
            new bootstrap.Modal(document.getElementById('modalTandaiLunas')).show();
        }
        
        function konfirmasiTandaiLunas() {
            const idIuran = document.getElementById('lunas_id_iuran').value;
            const idWarga = document.getElementById('lunas_id_warga').value;
            const catatan = document.getElementById('lunas_catatan').value;
            
            fetch('../includes/ajax_handlers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=tandai_lunas_manual&id_iuran=' + idIuran + '&id_warga=' + idWarga + '&catatan=' + encodeURIComponent(catatan)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('modalTandaiLunas')).hide();
                    loadStatusWarga(idIuran);
                } else {
                    alert(data.message || 'Gagal menandai lunas');
                }
            })
            .catch(error => {
                alert('Terjadi kesalahan: ' + error.message);
            });
        }
    </script>
</body>
</html>