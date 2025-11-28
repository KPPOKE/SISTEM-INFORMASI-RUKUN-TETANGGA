<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
checkWarga();

$user = getCurrentUser();
$warga = getWargaByUserId($user['id_user']);

$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$where = "WHERE w.status_keluarga = 'Kepala Keluarga' AND w.id_warga != ?";
$params = [$warga['id_warga']];
$types = "i";

if ($search) {
    $where .= " AND (w.nama_lengkap LIKE ? OR w.alamat LIKE ? OR w.no_kk LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query = "SELECT w.*, 
          (SELECT COUNT(*) FROM warga WHERE no_kk = w.no_kk) as jumlah_keluarga
          FROM warga w
          $where
          ORDER BY w.nama_lengkap ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tetangga_list = $stmt->get_result();

$query_map = "SELECT w.*, 
              (SELECT COUNT(*) FROM warga WHERE no_kk = w.no_kk) as jumlah_keluarga
              FROM warga w
              WHERE w.status_keluarga = 'Kepala Keluarga' 
              AND w.id_warga != ? 
              AND w.latitude IS NOT NULL 
              AND w.longitude IS NOT NULL";
$stmt_map = $conn->prepare($query_map);
$stmt_map->bind_param("i", $warga['id_warga']);
$stmt_map->execute();
$map_data = $stmt_map->get_result();

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
    <title>Data Tetangga - SISFO RT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        .tetangga-card {
            transition: transform 0.3s;
        }
        .tetangga-card:hover {
            transform: translateY(-5px);
        }
        #map {
            height: 500px;
            border-radius: 15px;
        }
        .tetangga-img {
            width: 80px;
            height: 80px;
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
                    <a class="nav-link" href="keluarga_saya.php">
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
                    <a class="nav-link active" href="tetangga.php">
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
                <div class="mb-4">
                    <h2><i class="fas fa-map-marked-alt me-2"></i>Data Tetangga</h2>
                    <p class="text-muted mb-0">Lihat data warga & peta lokasi RT</p>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#listTab" type="button">
                            <i class="fas fa-list me-2"></i>Daftar Tetangga
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mapTab" type="button" onclick="initMap()">
                            <i class="fas fa-map me-2"></i>Peta RT
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- List Tab -->
                    <div class="tab-pane fade show active" id="listTab">
                        <!-- Search -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-10">
                                        <input type="text" name="search" class="form-control" placeholder="Cari nama, alamat, atau No. KK..." value="<?php echo e($search); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Tetangga Cards -->
                        <div class="row">
                            <?php if ($tetangga_list->num_rows > 0): ?>
                                <?php while($tetangga = $tetangga_list->fetch_assoc()): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card tetangga-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <img src="../uploads/profile/<?php echo e($tetangga['foto_profil']); ?>" 
                                                         class="tetangga-img me-3" 
                                                         onerror="this.src='../assets/img/default.jpg'" 
                                                         alt="Foto">
                                                    <div>
                                                        <h5 class="mb-1"><?php echo e($tetangga['nama_lengkap']); ?></h5>
                                                        <span class="badge bg-primary">Kepala Keluarga</span>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted"><i class="fas fa-id-card me-2"></i>No. KK:</small>
                                                    <p class="mb-1"><?php echo e($tetangga['no_kk']); ?></p>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted"><i class="fas fa-home me-2"></i>Alamat:</small>
                                                    <p class="mb-1"><?php echo e($tetangga['alamat']); ?></p>
                                                    <small class="text-muted">RT <?php echo e($tetangga['rt']); ?> / RW <?php echo e($tetangga['rw']); ?></small>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted"><i class="fas fa-users me-2"></i>Anggota Keluarga:</small>
                                                    <p class="mb-1"><?php echo $tetangga['jumlah_keluarga']; ?> orang</p>
                                                </div>
                                                
                                                <?php if ($tetangga['no_telepon']): ?>
                                                    <div class="mb-2">
                                                        <small class="text-muted"><i class="fas fa-phone me-2"></i>Telepon:</small>
                                                        <p class="mb-1"><?php echo e($tetangga['no_telepon']); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <hr>
                                                
                                                <div class="d-grid">
                                                    <?php if ($tetangga['latitude'] && $tetangga['longitude']): ?>
                                                        <button class="btn btn-primary" onclick="showOnMap(<?php echo $tetangga['latitude']; ?>, <?php echo $tetangga['longitude']; ?>, '<?php echo e($tetangga['nama_lengkap']); ?>')">
                                                            <i class="fas fa-map-marker-alt me-2"></i>Lihat di Peta
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-secondary" disabled>
                                                            <i class="fas fa-map-marker-alt me-2"></i>Lokasi Belum Tersedia
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-body text-center py-5">
                                            <i class="fas fa-users fa-4x text-muted mb-3"></i>
                                            <h5 class="text-muted">Tidak ada data tetangga</h5>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Map Tab -->
                    <div class="tab-pane fade" id="mapTab">
                        <div class="card">
                            <div class="card-body">
                                <div id="map"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map = null;
        let markers = [];
        
        function initMap() {
            if (map) return;
            
            const defaultLat = -6.2088;
            const defaultLng = 106.8456;
            
            map = L.map('map').setView([defaultLat, defaultLng], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            const tetanggaData = <?php 
                $map_array = [];
                $map_data->data_seek(0);
                while($t = $map_data->fetch_assoc()) {
                    $map_array[] = [
                        'nama' => $t['nama_lengkap'],
                        'alamat' => $t['alamat'],
                        'lat' => (float)$t['latitude'],
                        'lng' => (float)$t['longitude'],
                        'keluarga' => $t['jumlah_keluarga']
                    ];
                }
                echo json_encode($map_array);
            ?>;
            
            if (tetanggaData.length > 0) {
                tetanggaData.forEach(data => {
                    const marker = L.marker([data.lat, data.lng]).addTo(map);
                    marker.bindPopup(`
                        <strong>${data.nama}</strong><br>
                        ${data.alamat}<br>
                        <small>Anggota Keluarga: ${data.keluarga} orang</small>
                    `);
                    markers.push(marker);
                });
                
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
            
            <?php if ($warga['latitude'] && $warga['longitude']): ?>
                const myMarker = L.marker([<?php echo $warga['latitude']; ?>, <?php echo $warga['longitude']; ?>], {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).addTo(map);
                myMarker.bindPopup('<strong>Lokasi Anda</strong><br><?php echo e($warga['alamat']); ?>').openPopup();
            <?php endif; ?>
        }
        
        function showOnMap(lat, lng, nama) {
            const mapTab = new bootstrap.Tab(document.querySelector('[data-bs-target="#mapTab"]'));
            mapTab.show();
            
            setTimeout(() => {
                if (!map) {
                    initMap();
                }
                
                map.setView([lat, lng], 18);
                
                markers.forEach(marker => {
                    if (marker.getLatLng().lat === lat && marker.getLatLng().lng === lng) {
                        marker.openPopup();
                    }
                });
            }, 100);
        }
    </script>
</body>
</html>