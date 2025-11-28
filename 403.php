<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .error-container {
            text-align: center;
            color: white;
        }
        .error-code {
            font-size: 120px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-lock"></i>
            </div>
            <div class="error-code">403</div>
            <h2 class="mb-4">Akses Ditolak</h2>
            <p class="lead mb-4">Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.</p>
            <a href="index.php" class="btn btn-light btn-lg">
                <i class="fas fa-home me-2"></i>Kembali ke Dashboard
            </a>
        </div>
    </div>
</body>
</html>