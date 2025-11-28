<?php
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

$query = "SELECT 
            w.id_warga,
            w.no_kk,
            w.nama_lengkap,
            w.status_keluarga,
            w.alamat,
            w.rt,
            w.rw,
            w.no_telepon,
            w.latitude,
            w.longitude,
            w.foto_profil
          FROM warga w
          WHERE w.latitude IS NOT NULL 
          AND w.longitude IS NOT NULL
          AND w.status_keluarga = 'Kepala Keluarga'
          ORDER BY w.nama_lengkap ASC";

$result = $conn->query($query);

$locations = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $count_query = "SELECT COUNT(*) as total FROM warga WHERE no_kk = ?";
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param("s", $row['no_kk']);
        $stmt->execute();
        $count_result = $stmt->get_result();
        $count = $count_result->fetch_assoc()['total'];
        
        $locations[] = [
            'id' => $row['id_warga'],
            'no_kk' => $row['no_kk'],
            'nama' => $row['nama_lengkap'],
            'alamat' => $row['alamat'],
            'rt' => $row['rt'],
            'rw' => $row['rw'],
            'no_telepon' => $row['no_telepon'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'foto_profil' => $row['foto_profil'],
            'jumlah_anggota' => $count
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'total' => count($locations),
    'data' => $locations
], JSON_PRETTY_PRINT);
?>