-- Database: sisfo_rt
CREATE DATABASE IF NOT EXISTS sisfo_rt;
USE sisfo_rt;

-- ========================================
-- TABEL USERS (untuk login)
-- ========================================
CREATE TABLE users (
    id_user INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'warga', 'bendahara') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ========================================
-- TABEL WARGA (Data Penduduk)
-- ========================================
CREATE TABLE warga (
    id_warga INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NULL COMMENT 'Hanya untuk Kepala Keluarga',
    nik VARCHAR(16) UNIQUE NOT NULL,
    no_kk VARCHAR(16) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    tempat_lahir VARCHAR(50),
    tanggal_lahir DATE,
    jenis_kelamin ENUM('Laki-laki', 'Perempuan'),
    status_keluarga ENUM('Kepala Keluarga', 'Istri', 'Anak', 'Orang Tua', 'Lainnya'),
    agama ENUM('Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'),
    pendidikan VARCHAR(50),
    pekerjaan VARCHAR(50),
    status_perkawinan ENUM('Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'),
    alamat TEXT,
    rt VARCHAR(5),
    rw VARCHAR(5),
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    no_telepon VARCHAR(15),
    foto_profil VARCHAR(255) DEFAULT 'default.jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE SET NULL,
    INDEX idx_no_kk (no_kk),
    INDEX idx_status_keluarga (status_keluarga)
);

-- ========================================
-- TABEL BERITA
-- ========================================
CREATE TABLE berita (
    id_berita INT PRIMARY KEY AUTO_INCREMENT,
    judul VARCHAR(200) NOT NULL,
    konten TEXT NOT NULL,
    gambar VARCHAR(255),
    id_user INT NOT NULL,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);

-- ========================================
-- TABEL KEGIATAN
-- ========================================
CREATE TABLE kegiatan (
    id_kegiatan INT PRIMARY KEY AUTO_INCREMENT,
    judul VARCHAR(200) NOT NULL,
    deskripsi TEXT NOT NULL,
    tanggal_kegiatan DATETIME NOT NULL,
    lokasi VARCHAR(200),
    gambar VARCHAR(255),
    id_user INT NOT NULL,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);

-- ========================================
-- TABEL KOMENTAR (untuk berita dan kegiatan)
-- ========================================
CREATE TABLE komentar (
    id_komentar INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    jenis_postingan ENUM('berita', 'kegiatan') NOT NULL,
    id_postingan INT NOT NULL,
    id_parent INT NULL COMMENT 'Untuk nested comment',
    komentar TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_parent) REFERENCES komentar(id_komentar) ON DELETE CASCADE,
    INDEX idx_postingan (jenis_postingan, id_postingan),
    INDEX idx_parent (id_parent)
);

-- ========================================
-- TABEL SURAT
-- ========================================
CREATE TABLE surat (
    id_surat INT PRIMARY KEY AUTO_INCREMENT,
    id_warga INT NOT NULL,
    jenis_surat VARCHAR(100) NOT NULL,
    keperluan TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    catatan TEXT NULL,
    file_pendukung VARCHAR(255),
    file_hasil VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_warga) REFERENCES warga(id_warga) ON DELETE CASCADE,
    INDEX idx_status (status)
);

-- ========================================
-- TABEL IURAN POSTING (dari bendahara)
-- ========================================
CREATE TABLE iuran_posting (
    id_iuran_posting INT PRIMARY KEY AUTO_INCREMENT,
    judul VARCHAR(200) NOT NULL,
    deskripsi TEXT,
    nominal DECIMAL(12, 2) NOT NULL,
    periode VARCHAR(20) NOT NULL,
    qris_image VARCHAR(255),
    deadline DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ========================================
-- TABEL PEMBAYARAN IURAN (dari warga)
-- ========================================
CREATE TABLE iuran_pembayaran (
    id_pembayaran INT PRIMARY KEY AUTO_INCREMENT,
    id_iuran_posting INT NOT NULL,
    id_warga INT NOT NULL,
    bukti_transfer VARCHAR(255) NOT NULL,
    tanggal_bayar DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'valid', 'rejected') DEFAULT 'pending',
    catatan TEXT NULL,
    verified_at DATETIME NULL,
    verified_by INT NULL,
    FOREIGN KEY (id_iuran_posting) REFERENCES iuran_posting(id_iuran_posting) ON DELETE CASCADE,
    FOREIGN KEY (id_warga) REFERENCES warga(id_warga) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id_user) ON DELETE SET NULL,
    INDEX idx_status (status)
);

-- ========================================
-- TABEL PEMASUKAN (BARU - untuk pemasukan manual)
-- ========================================
CREATE TABLE pemasukan (
    id_pemasukan INT PRIMARY KEY AUTO_INCREMENT,
    kategori VARCHAR(50) NOT NULL COMMENT 'Saldo Awal, Donasi, Bantuan, Lainnya',
    keterangan VARCHAR(200) NOT NULL,
    nominal DECIMAL(12, 2) NOT NULL,
    tanggal_pemasukan DATE NOT NULL,
    bukti_pemasukan VARCHAR(255),
    id_user INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    INDEX idx_kategori (kategori),
    INDEX idx_tanggal (tanggal_pemasukan)
);

-- ========================================
-- TABEL PENGELUARAN
-- ========================================
CREATE TABLE pengeluaran (
    id_pengeluaran INT PRIMARY KEY AUTO_INCREMENT,
    keterangan VARCHAR(200) NOT NULL,
    nominal DECIMAL(12, 2) NOT NULL,
    kategori VARCHAR(50),
    tanggal_pengeluaran DATE NOT NULL,
    bukti_pengeluaran VARCHAR(255),
    id_user INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);

-- ========================================
-- TABEL ACTIVITY LOG
-- ========================================
CREATE TABLE activity_log (
    id_log INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    INDEX idx_user (id_user),
    INDEX idx_created (created_at)
);

-- ========================================
-- INSERT DEFAULT DATA
-- ========================================

-- Insert default admin & bendahara
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('bendahara', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'bendahara');
-- Password default: password

-- Insert sample warga (1 KK dengan 3 anggota keluarga)
INSERT INTO warga (nik, no_kk, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, status_keluarga, agama, pekerjaan, alamat, rt, rw, latitude, longitude, no_telepon) VALUES
('3273010101800001', '3273010101180001', 'Budi Santoso', 'Jakarta', '1980-01-01', 'Laki-laki', 'Kepala Keluarga', 'Islam', 'Wiraswasta', 'Jl. Melati No. 10', '001', '001', -6.2088, 106.8456, '081234567890'),
('3273010102800002', '3273010101180001', 'Siti Aminah', 'Bandung', '1982-02-15', 'Perempuan', 'Istri', 'Islam', 'Ibu Rumah Tangga', 'Jl. Melati No. 10', '001', '001', -6.2088, 106.8456, '081234567891'),
('3273010103050003', '3273010101180001', 'Ahmad Santoso', 'Jakarta', '2005-05-10', 'Laki-laki', 'Anak', 'Islam', 'Pelajar', 'Jl. Melati No. 10', '001', '001', -6.2088, 106.8456, '081234567892');

-- Create akun untuk Kepala Keluarga (username: warga800001, password: 800001)
INSERT INTO users (username, password, role) VALUES
('warga800001', '$2y$10$8tZ0YqLzTxKXqKiP5mZWOuZJT5qP9VzYh.vXqLxK5mZWOuZJT5qP9', 'warga');

-- Update id_user untuk Kepala Keluarga
UPDATE warga SET id_user = 3 WHERE nik = '3273010101800001';

-- Insert sample berita
INSERT INTO berita (judul, konten, id_user) VALUES
('Pengumuman Gotong Royong', 'Akan diadakan gotong royong membersihkan lingkungan RT pada hari Minggu, 15 Desember 2024 pukul 07.00 WIB. Diharapkan semua warga dapat berpartisipasi.', 1);

-- Insert sample kegiatan
INSERT INTO kegiatan (judul, deskripsi, tanggal_kegiatan, lokasi, id_user) VALUES
('Senam Sehat Bersama', 'Kegiatan senam sehat bersama setiap hari Minggu pagi di lapangan RT.', '2024-12-15 07:00:00', 'Lapangan RT 001', 1);

-- Insert sample iuran
INSERT INTO iuran_posting (judul, deskripsi, nominal, periode, deadline) VALUES
('Iuran Kebersihan November 2024', 'Iuran kebersihan untuk bulan November 2024', 50000.00, '2024-11', '2024-11-30');

-- Insert sample pemasukan (Saldo Awal)
INSERT INTO pemasukan (kategori, keterangan, nominal, tanggal_pemasukan, id_user) VALUES
('Saldo Awal', 'Saldo awal kas RT per 1 Januari 2024', 5000000.00, '2024-01-01', 2),
('Donasi', 'Donasi dari warga untuk perayaan 17 Agustus', 2000000.00, '2024-08-10', 2);

-- Insert sample pengeluaran
INSERT INTO pengeluaran (keterangan, nominal, kategori, tanggal_pengeluaran, id_user) VALUES
('Pembayaran listrik RT', 150000.00, 'Listrik', '2024-11-05', 2),
('Pembelian peralatan kebersihan', 250000.00, 'Kebersihan', '2024-11-10', 2); 