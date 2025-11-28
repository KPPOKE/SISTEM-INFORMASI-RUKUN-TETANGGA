<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkAdmin();

$id_surat = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id_surat) {
    header("Location: surat.php");
    exit;
}

$query = "SELECT s.*, w.nama_lengkap, w.nik, w.tempat_lahir, w.tanggal_lahir, 
          w.jenis_kelamin, w.agama, w.pekerjaan, w.status_perkawinan, w.alamat, w.rt, w.rw
          FROM surat s
          JOIN warga w ON s.id_warga = w.id_warga
          WHERE s.id_surat = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_surat);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: surat.php");
    exit;
}

$surat = $result->fetch_assoc();

$nomor_surat = sprintf("%03d/RT-%s/SK/%s", $id_surat, $surat['rt'], date('Y', strtotime($surat['created_at'])));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Surat - <?php echo e($surat['jenis_surat']); ?></title>
    <style>
        @media print {
            .no-print {
                display: none;
            }
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 20px;
            font-size: 14px;
        }
        
        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 40px;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        
        .header h2 {
            margin: 5px 0;
            font-size: 18px;
            font-weight: bold;
        }
        
        .header h3 {
            margin: 5px 0;
            font-size: 16px;
        }
        
        .header p {
            margin: 3px 0;
            font-size: 12px;
        }
        
        .nomor-surat {
            text-align: center;
            margin: 20px 0;
        }
        
        .judul-surat {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            text-decoration: underline;
            margin: 20px 0;
        }
        
        .nomor-surat-detail {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .isi-surat {
            text-align: justify;
            line-height: 1.8;
            margin: 20px 0;
        }
        
        .data-table {
            width: 100%;
            margin: 20px 0;
        }
        
        .data-table td {
            padding: 5px;
            vertical-align: top;
        }
        
        .data-table td:first-child {
            width: 200px;
        }
        
        .data-table td:nth-child(2) {
            width: 20px;
            text-align: center;
        }
        
        .ttd-section {
            margin-top: 50px;
        }
        
        .ttd-box {
            float: right;
            text-align: center;
            width: 250px;
        }
        
        .ttd-box p {
            margin: 5px 0;
        }
        
        .ttd-space {
            height: 80px;
        }
        
        .button-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="button-print no-print">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>
    
    <div class="container">
        <!-- Kop Surat -->
        <div class="header">
            <h2>RUKUN TETANGGA <?php echo e($surat['rt']); ?> / RUKUN WARGA <?php echo e($surat['rw']); ?></h2>
            <h3>KELURAHAN/DESA ..................</h3>
            <h3>KECAMATAN ..................</h3>
            <p>Alamat: ...........................................................................................</p>
        </div>
        
        <!-- Judul Surat -->
        <div class="judul-surat">
            <?php echo strtoupper(e($surat['jenis_surat'])); ?>
        </div>
        
        <div class="nomor-surat-detail">
            Nomor: <?php echo e($nomor_surat); ?>
        </div>
        
        <!-- Isi Surat -->
        <div class="isi-surat">
            <p>Yang bertanda tangan di bawah ini Ketua RT <?php echo e($surat['rt']); ?> / RW <?php echo e($surat['rw']); ?>, menerangkan bahwa:</p>
            
            <table class="data-table">
                <tr>
                    <td>Nama</td>
                    <td>:</td>
                    <td><strong><?php echo strtoupper(e($surat['nama_lengkap'])); ?></strong></td>
                </tr>
                <tr>
                    <td>NIK</td>
                    <td>:</td>
                    <td><?php echo e($surat['nik']); ?></td>
                </tr>
                <tr>
                    <td>Tempat / Tanggal Lahir</td>
                    <td>:</td>
                    <td><?php echo e($surat['tempat_lahir']); ?>, <?php echo formatTanggal($surat['tanggal_lahir']); ?></td>
                </tr>
                <tr>
                    <td>Jenis Kelamin</td>
                    <td>:</td>
                    <td><?php echo e($surat['jenis_kelamin']); ?></td>
                </tr>
                <tr>
                    <td>Agama</td>
                    <td>:</td>
                    <td><?php echo e($surat['agama']); ?></td>
                </tr>
                <tr>
                    <td>Pekerjaan</td>
                    <td>:</td>
                    <td><?php echo e($surat['pekerjaan']); ?></td>
                </tr>
                <tr>
                    <td>Status Perkawinan</td>
                    <td>:</td>
                    <td><?php echo e($surat['status_perkawinan']); ?></td>
                </tr>
                <tr>
                    <td>Alamat</td>
                    <td>:</td>
                    <td><?php echo e($surat['alamat']); ?>, RT <?php echo e($surat['rt']); ?> / RW <?php echo e($surat['rw']); ?></td>
                </tr>
            </table>
            
            <p>Adalah benar warga kami dan surat ini dibuat untuk keperluan:</p>
            <p style="margin-left: 40px;"><strong><?php echo strtoupper(e($surat['keperluan'])); ?></strong></p>
            
            <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>
        </div>
        
        <!-- Tanda Tangan -->
        <div class="ttd-section">
            <div class="ttd-box">
                <p>.................., <?php echo formatTanggal(date('Y-m-d')); ?></p>
                <p>Ketua RT <?php echo e($surat['rt']); ?> / RW <?php echo e($surat['rw']); ?></p>
                <div class="ttd-space"></div>
                <p><strong><u>( ............................. )</u></strong></p>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <?php if ($surat['catatan']): ?>
        <div style="margin-top: 30px; padding: 10px; background: #f8f9fa; border-left: 4px solid #667eea;">
            <strong>Catatan:</strong><br>
            <?php echo nl2br(e($surat['catatan'])); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</body>
</html>