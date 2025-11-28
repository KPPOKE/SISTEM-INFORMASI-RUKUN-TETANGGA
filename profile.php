<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/middleware.php';
require_once 'includes/activity_log.php';

checkAuth();

$user = getCurrentUser();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'update_password') {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Semua field harus diisi';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru tidak cocok';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password minimal 6 karakter';
        } elseif (!password_verify($old_password, $user['password'])) {
            $error = 'Password lama salah';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = ? WHERE id_user = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $hashed_password, $user['id_user']);
            
            if ($stmt->execute()) {
                logActivity($user['id_user'], 'Update Password', 'User mengubah password');
                $success = 'Password berhasil diubah';
            } else {
                $error = 'Gagal mengubah password';
            }
        }
    }
    
    if ($action === 'update_profile' && $user['role'] === 'warga') {
        $warga = getWargaByUserId($user['id_user']);
        
        $no_telepon = cleanInput($_POST['no_telepon']);
        $alamat = cleanInput($_POST['alamat']);
        
        $foto_profil = $warga['foto_profil'];
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === 0) {
            $upload = uploadFile($_FILES['foto_profil'], 'profile', ['jpg', 'jpeg', 'png']);
            if ($upload['status']) {
                if ($warga['foto_profil'] !== 'default.jpg') {
                    $old_file = __DIR__ . '/uploads/profile/' . $warga['foto_profil'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                $foto_profil = $upload['filename'];
            } else {
                $error = $upload['message'];
            }
        }
        
        if (empty($error)) {
            $query = "UPDATE warga SET no_telepon = ?, alamat = ?, foto_profil = ? WHERE id_user = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $no_telepon, $alamat, $foto_profil, $user['id_user']);
            
            if ($stmt->execute()) {
                logActivity($user['id_user'], 'Update Profile', 'User mengubah profil');
                $success = 'Profil berhasil diperbarui';
                $warga = getWargaByUserId($user['id_user']);
            } else {
                $error = 'Gagal memperbarui profil';
            }
        }
    }
}

$warga = null;
if ($user['role'] === 'warga') {
    $warga = getWargaByUserId($user['id_user']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SISFO RT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="profile-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-user-circle me-2"></i>Profile</h2>
                    <p class="mb-0">Kelola profile dan keamanan akun Anda</p>
                </div>
                <a href="index.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="row">
            <!-- Sidebar Info -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <?php if ($warga): ?>
                            <img src="uploads/profile/<?php echo e($warga['foto_profil']); ?>" 
                                 class="profile-img mb-3" 
                                 onerror="this.src='https://via.placeholder.com/120'">
                            <h4><?php echo e($warga['nama_lengkap']); ?></h4>
                            <p class="text-muted mb-1"><?php echo e($warga['status_keluarga']); ?></p>
                            <p class="text-muted"><i class="fas fa-id-card me-1"></i><?php echo e($warga['nik']); ?></p>
                        <?php else: ?>
                            <div class="profile-img bg-primary text-white d-flex align-items-center justify-content-center mb-3 mx-auto">
                                <i class="fas fa-user fa-3x"></i>
                            </div>
                            <h4><?php echo e($user['username']); ?></h4>
                        <?php endif; ?>
                        <hr>
                        <div class="text-start">
                            <p class="mb-2"><strong>Username:</strong><br><?php echo e($user['username']); ?></p>
                            <p class="mb-2"><strong>Role:</strong><br>
                                <span class="badge bg-primary"><?php echo strtoupper(e($user['role'])); ?></span>
                            </p>
                            <p class="mb-0"><strong>Bergabung:</strong><br><?php echo formatTanggal($user['created_at']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-8">
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
                
                <!-- Update Profile (hanya untuk warga) -->
                <?php if ($warga): ?>
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Update Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="mb-3">
                                <label class="form-label">Foto Profil</label>
                                <input type="file" class="form-control" name="foto_profil" accept="image/*">
                                <small class="text-muted">Format: JPG, JPEG, PNG. Max 5MB</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">No. Telepon</label>
                                <input type="text" class="form-control" name="no_telepon" 
                                       value="<?php echo e($warga['no_telepon']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-control" name="alamat" rows="3"><?php echo e($warga['alamat']); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Update Password -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i>Ubah Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div class="mb-3">
                                <label class="form-label">Password Lama</label>
                                <input type="password" class="form-control" name="old_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password Baru</label>
                                <input type="password" class="form-control" name="new_password" required>
                                <small class="text-muted">Minimal 6 karakter</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-lock me-2"></i>Ubah Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>