<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

checkBendahara();

$user = getCurrentUser();

$bulan_awal = isset($_GET['bulan_awal']) ? $_GET['bulan_awal'] : date('Y-m', strtotime('-1 month'));
$bulan_akhir = isset($_GET['bulan_akhir']) ? $_GET['bulan_akhir'] : date('Y-m');

$tanggal_awal = $bulan_awal . '-01';
$tanggal_akhir = date('Y-m-t', strtotime($bulan_akhir . '-01'));

$query_pemasukan = "SELECT ipb.*, ip.judul, ip.nominal, ip.periode, w.nama_lengkap, w.nik
                    FROM iuran_pembayaran ipb
                    JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
                    JOIN warga w ON ipb.id_warga = w.id_warga
                    WHERE ipb.status = 'valid' 
                    AND DATE(ipb.tanggal_bayar) BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
                    ORDER BY ipb.tanggal_bayar ASC";
$pemasukan_list = $conn->query($query_pemasukan);

$query_pengeluaran = "SELECT p.*, u.username
                      FROM pengeluaran p
                      JOIN users u ON p.id_user = u.id_user
                      WHERE DATE(p.tanggal_pengeluaran) BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
                      ORDER BY p.tanggal_pengeluaran ASC";
$pengeluaran_list = $conn->query($query_pengeluaran);

$total_pemasukan = 0;
$total_pengeluaran = 0;

$pemasukan_data = [];
while ($row = $pemasukan_list->fetch_assoc()) {
    $pemasukan_data[] = $row;
    $total_pemasukan += $row['nominal'];
}

$pengeluaran_data = [];
while ($row = $pengeluaran_list->fetch_assoc()) {
    $pengeluaran_data[] = $row;
    $total_pengeluaran += $row['nominal'];
}

$saldo = $total_pemasukan - $total_pengeluaran;

$query_saldo_awal = "SELECT 
                     (SELECT COALESCE(SUM(ip.nominal), 0) 
                      FROM iuran_pembayaran ipb
                      JOIN iuran_posting ip ON ipb.id_iuran_posting = ip.id_iuran_posting
                      WHERE ipb.status = 'valid' AND DATE(ipb.tanggal_bayar) < '$tanggal_awal') -
                     (SELECT COALESCE(SUM(nominal), 0) 
                      FROM pengeluaran 
                      WHERE DATE(tanggal_pengeluaran) < '$tanggal_awal') as saldo_awal";
$saldo_awal = $conn->query($query_saldo_awal)->fetch_assoc()['saldo_awal'];
$saldo_akhir = $saldo_awal + $total_pemasukan - $total_pengeluaran;

if (isset($_GET['export'])) {
    if ($_GET['export'] === 'pdf') {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Laporan Keuangan</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 11px; }
                h2, h3 { text-align: center; margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { border: 1px solid #000; padding: 5px; text-align: left; }
                th { background-color: #f0f0f0; font-weight: bold; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .total-row { background-color: #e0e0e0; font-weight: bold; }
                .footer { margin-top: 40px; text-align: right; }
            </style>
        </head>
        <body>
            <h2>LAPORAN KEUANGAN RT</h2>
            <h3>Periode: <?php echo formatTanggal($tanggal_awal); ?> s/d <?php echo formatTanggal($tanggal_akhir); ?></h3>
            <p style="text-align:center;">Dicetak pada: <?php echo formatTanggal(date('Y-m-d')); ?></p>
            
            <h3 style="text-align:left; margin-top:20px;">RINCIAN PEMASUKAN</h3>
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="12%">Tanggal</th>
                        <th width="15%">NIK</th>
                        <th width="23%">Nama</th>
                        <th width="20%">Judul Iuran</th>
                        <th width="10%">Periode</th>
                        <th width="15%" class="text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($pemasukan_data as $row): 
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_bayar'])); ?></td>
                            <td><?php echo e($row['nik']); ?></td>
                            <td><?php echo e($row['nama_lengkap']); ?></td>
                            <td><?php echo e($row['judul']); ?></td>
                            <td><?php echo e($row['periode']); ?></td>
                            <td class="text-right"><?php echo formatRupiah($row['nominal']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="6" class="text-right">TOTAL PEMASUKAN</td>
                        <td class="text-right"><?php echo formatRupiah($total_pemasukan); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h3 style="text-align:left; margin-top:30px;">RINCIAN PENGELUARAN</h3>
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="12%">Tanggal</th>
                        <th width="40%">Keterangan</th>
                        <th width="18%">Kategori</th>
                        <th width="15%" class="text-right">Nominal</th>
                        <th width="10%">User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($pengeluaran_data as $row): 
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pengeluaran'])); ?></td>
                            <td><?php echo e($row['keterangan']); ?></td>
                            <td><?php echo e($row['kategori']); ?></td>
                            <td class="text-right"><?php echo formatRupiah($row['nominal']); ?></td>
                            <td><?php echo e($row['username']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="4" class="text-right">TOTAL PENGELUARAN</td>
                        <td class="text-right"><?php echo formatRupiah($total_pengeluaran); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            
            <h3 style="text-align:left; margin-top:30px;">RINGKASAN KEUANGAN</h3>
            <table style="width:60%; margin: 0 auto;">
                <tr>
                    <th width="60%">Saldo Awal Periode</th>
                    <td width="40%" class="text-right"><?php echo formatRupiah($saldo_awal); ?></td>
                </tr>
                <tr style="background-color:#d4edda;">
                    <th>Total Pemasukan</th>
                    <td class="text-right"><?php echo formatRupiah($total_pemasukan); ?></td>
                </tr>
                <tr style="background-color:#f8d7da;">
                    <th>Total Pengeluaran</th>
                    <td class="text-right"><?php echo formatRupiah($total_pengeluaran); ?></td>
                </tr>
                <tr>
                    <th>Surplus / (Defisit)</th>
                    <td class="text-right"><?php echo formatRupiah($saldo); ?></td>
                </tr>
                <tr class="total-row">
                    <th>Saldo Akhir Periode</th>
                    <td class="text-right" style="font-size:14px;"><?php echo formatRupiah($saldo_akhir); ?></td>
                </tr>
            </table>
            
            <div class="footer">
                <p>Jakarta, <?php echo formatTanggal(date('Y-m-d')); ?></p>
                <p>Bendahara RT</p>
                <br><br><br>
                <p><strong><u><?php echo e($user['username']); ?></u></strong></p>
            </div>
            
            <script>
                window.onload = function() { 
                    window.print(); 
                    window.onafterprint = function() {
                        window.close();
                    }
                }
            </script>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        echo $html;
        exit;
    }
    
    if ($_GET['export'] === 'excel') {
        ob_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Laporan_Keuangan_' . $bulan_awal . '_' . $bulan_akhir . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['LAPORAN KEUANGAN RT']);
        fputcsv($output, ['Periode: ' . formatTanggal($tanggal_awal) . ' s/d ' . formatTanggal($tanggal_akhir)]);
        fputcsv($output, []);
        
        fputcsv($output, ['PEMASUKAN']);
        fputcsv($output, ['Tanggal', 'NIK', 'Nama', 'Judul Iuran', 'Periode', 'Nominal']);
        foreach ($pemasukan_data as $row) {
            fputcsv($output, [
                date('d/m/Y', strtotime($row['tanggal_bayar'])),
                $row['nik'],
                $row['nama_lengkap'],
                $row['judul'],
                $row['periode'],
                $row['nominal']
            ]);
        }
        fputcsv($output, ['', '', '', '', 'TOTAL PEMASUKAN', $total_pemasukan]);
        fputcsv($output, []);
        
        fputcsv($output, ['PENGELUARAN']);
        fputcsv($output, ['Tanggal', 'Keterangan', 'Kategori', 'Nominal', 'Dicatat Oleh']);
        foreach ($pengeluaran_data as $row) {
            fputcsv($output, [
                date('d/m/Y', strtotime($row['tanggal_pengeluaran'])),
                $row['keterangan'],
                $row['kategori'],
                $row['nominal'],
                $row['username']
            ]);
        }
        fputcsv($output, ['', '', 'TOTAL PENGELUARAN', $total_pengeluaran]);
        fputcsv($output, []);
        
        fputcsv($output, ['RINGKASAN']);
        fputcsv($output, ['Saldo Awal', $saldo_awal]);
        fputcsv($output, ['Total Pemasukan', $total_pemasukan]);
        fputcsv($output, ['Total Pengeluaran', $total_pengeluaran]);
        fputcsv($output, ['Saldo Akhir', $saldo_akhir]);
        
        fclose($output);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - SISFO RT</title>
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
        .summary-card {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .sidebar {
                display: none !important;
            }
            .col-md-9 {
                width: 100% !important;
                max-width: 100% !important;
            }
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
                    <a class="nav-link" href="pemasukan.php">
                       <i class="fas fa-hand-holding-usd me-2"></i>Pemasukan
                    </a>
                    <a class="nav-link" href="pengeluaran.php">
                        <i class="fas fa-receipt me-2"></i>Pengeluaran
                    </a>
                    <a class="nav-link active" href="laporan.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h2><i class="fas fa-file-invoice me-2"></i>Laporan Keuangan</h2>
                    <div>
                        <button onclick="window.print()" class="btn btn-secondary me-2">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                        <div class="btn-group">
                            <a href="?bulan_awal=<?php echo $bulan_awal; ?>&bulan_akhir=<?php echo $bulan_akhir; ?>&export=pdf" 
                               class="btn btn-danger" target="_blank">
                                <i class="fas fa-file-pdf me-2"></i>Export PDF
                            </a>
                            <a href="?bulan_awal=<?php echo $bulan_awal; ?>&bulan_akhir=<?php echo $bulan_akhir; ?>&export=excel" class="btn btn-success">
                                <i class="fas fa-file-excel me-2"></i>Export Excel
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Filter -->
                <div class="card no-print">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Bulan Awal</label>
                                <input type="month" name="bulan_awal" class="form-control" value="<?php echo $bulan_awal; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bulan Akhir</label>
                                <input type="month" name="bulan_akhir" class="form-control" value="<?php echo $bulan_akhir; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Tampilkan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Header Laporan -->
                <div class="text-center mb-4">
                    <h3>LAPORAN KEUANGAN RT</h3>
                    <p class="mb-0">Periode: <strong><?php echo formatTanggal($tanggal_awal); ?> s/d <?php echo formatTanggal($tanggal_akhir); ?></strong></p>
                    <small class="text-muted">Dicetak pada: <?php echo formatTanggal(date('Y-m-d')); ?></small>
                </div>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="summary-card bg-info text-white">
                            <small>Saldo Awal</small>
                            <h5 class="mb-0"><?php echo formatRupiah($saldo_awal); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-card bg-success text-white">
                            <small>Total Pemasukan</small>
                            <h5 class="mb-0"><?php echo formatRupiah($total_pemasukan); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-card bg-danger text-white">
                            <small>Total Pengeluaran</small>
                            <h5 class="mb-0"><?php echo formatRupiah($total_pengeluaran); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-card bg-primary text-white">
                            <small>Saldo Akhir</small>
                            <h5 class="mb-0"><?php echo formatRupiah($saldo_akhir); ?></h5>
                        </div>
                    </div>
                </div>
                
                <!-- Pemasukan -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-arrow-down me-2"></i>Rincian Pemasukan</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="12%">Tanggal</th>
                                        <th width="15%">NIK</th>
                                        <th width="20%">Nama</th>
                                        <th width="23%">Judul Iuran</th>
                                        <th width="10%">Periode</th>
                                        <th width="15%" class="text-end">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    if (count($pemasukan_data) > 0):
                                        foreach ($pemasukan_data as $row): 
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_bayar'])); ?></td>
                                            <td><?php echo e($row['nik']); ?></td>
                                            <td><?php echo e($row['nama_lengkap']); ?></td>
                                            <td><?php echo e($row['judul']); ?></td>
                                            <td><?php echo e($row['periode']); ?></td>
                                            <td class="text-end"><?php echo formatRupiah($row['nominal']); ?></td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Tidak ada data pemasukan</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-success">
                                        <th colspan="6" class="text-end">TOTAL PEMASUKAN</th>
                                        <th class="text-end"><?php echo formatRupiah($total_pemasukan); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Pengeluaran -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Rincian Pengeluaran</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="12%">Tanggal</th>
                                        <th width="35%">Keterangan</th>
                                        <th width="18%">Kategori</th>
                                        <th width="15%" class="text-end">Nominal</th>
                                        <th width="15%">Dicatat Oleh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    if (count($pengeluaran_data) > 0):
                                        foreach ($pengeluaran_data as $row): 
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pengeluaran'])); ?></td>
                                            <td><?php echo e($row['keterangan']); ?></td>
                                            <td><?php echo e($row['kategori']); ?></td>
                                            <td class="text-end"><?php echo formatRupiah($row['nominal']); ?></td>
                                            <td><?php echo e($row['username']); ?></td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Tidak ada data pengeluaran</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-danger">
                                        <th colspan="4" class="text-end">TOTAL PENGELUARAN</th>
                                        <th class="text-end"><?php echo formatRupiah($total_pengeluaran); ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Summary -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Ringkasan Keuangan</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="50%">Saldo Awal Periode</th>
                                <td class="text-end"><strong><?php echo formatRupiah($saldo_awal); ?></strong></td>
                            </tr>
                            <tr class="table-success">
                                <th>Total Pemasukan</th>
                                <td class="text-end"><strong><?php echo formatRupiah($total_pemasukan); ?></strong></td>
                            </tr>
                            <tr class="table-danger">
                                <th>Total Pengeluaran</th>
                                <td class="text-end"><strong><?php echo formatRupiah($total_pengeluaran); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Surplus / (Defisit)</th>
                                <td class="text-end">
                                    <strong class="<?php echo $saldo >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatRupiah($saldo); ?>
                                    </strong>
                                </td>
                            </tr>
                            <tr class="table-primary">
                                <th>Saldo Akhir Periode</th>
                                <td class="text-end">
                                    <h5 class="mb-0"><strong><?php echo formatRupiah($saldo_akhir); ?></strong></h5>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="text-end mt-4">
                    <p class="mb-1">Jakarta, <?php echo formatTanggal(date('Y-m-d')); ?></p>
                    <p class="mb-5">Bendahara RT</p>
                    <p class="mb-0"><strong><u><?php echo e($user['username']); ?></u></strong></p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>