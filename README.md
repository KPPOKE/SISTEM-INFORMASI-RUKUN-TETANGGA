# SISFO RT - Sistem Informasi Rukun Tetangga

Aplikasi web untuk manajemen administrasi dan informasi Rukun Tetangga (RT) berbasis PHP dan MySQL.

## Deskripsi

SISFO RT adalah sistem informasi yang dirancang untuk memudahkan pengelolaan administrasi RT, termasuk manajemen data penduduk, keuangan, surat menyurat, dan informasi kegiatan RT.

## Fitur Utama

### 1. Manajemen Pengguna
- Sistem login dengan 3 role: Admin, Bendahara, dan Warga
- Autentikasi berbasis session dengan password hashing (Bcrypt)
- Activity logging untuk tracking aktivitas pengguna

### 2. Manajemen Kependudukan
- Data lengkap penduduk (NIK, KK, biodata, dll)
- Relasi keluarga (Kepala Keluarga, Istri, Anak, dll)
- Pembuatan akun otomatis untuk Kepala Keluarga
- Pemetaan lokasi dengan koordinat GPS

### 3. Informasi & Komunikasi
- Publikasi berita RT
- Pengumuman kegiatan RT
- Sistem komentar untuk interaksi warga
- View counter untuk berita dan kegiatan

### 4. Layanan Surat
- Pengajuan surat pengantar online
- Tracking status surat (Pending, Approved, Rejected)
- Upload file pendukung
- Download surat hasil

### 5. Manajemen Keuangan
- Posting iuran bulanan oleh bendahara
- Upload bukti pembayaran oleh warga
- Verifikasi pembayaran dengan status tracking
- Pencatatan pemasukan manual (donasi, saldo awal, dll)
- Pencatatan pengeluaran
- Laporan keuangan lengkap
- Dashboard statistik keuangan

### 6. Dashboard & Laporan
- Dashboard khusus untuk setiap role
- Statistik real-time
- Laporan keuangan yang dapat dicetak
- Filter data berdasarkan periode

## Teknologi yang Digunakan

### Backend
- PHP 7.4+
- MySQL 5.7+
- Session-based authentication

### Frontend
- HTML5
- CSS3
- JavaScript (Vanilla)
- Bootstrap 5.3.0
- Font Awesome 6.4.0

### Server
- Apache (XAMPP/LAMP/WAMP)
- PHP MySQLi Extension

## Persyaratan Sistem

- PHP >= 7.4
- MySQL >= 5.7
- Apache Web Server
- PHP Extensions:
  - mysqli
  - session
  - fileinfo
  - gd (untuk upload gambar)

## Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/username/sisfo-rt.git
cd sisfo-rt
```

### 2. Setup Database

1. Buat database baru di MySQL:
```sql
CREATE DATABASE sisfo_rt;
```

2. Import file database:
```bash
mysql -u root -p sisfo_rt < db.sql
```

Atau melalui phpMyAdmin:
- Buka phpMyAdmin
- Pilih database `sisfo_rt`
- Import file `db.sql`

### 3. Konfigurasi Database

Edit file `includes/db_connect.php`:

```php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'sisfo_rt';
```

Sesuaikan dengan konfigurasi database Anda.

### 4. Setup Folder Uploads

Pastikan folder `uploads/` memiliki permission write:

```bash
chmod -R 755 uploads/
```

Atau di Windows, pastikan folder tidak read-only.

### 5. Akses Aplikasi

Buka browser dan akses:
```
http://localhost/sisfo_rt
```

## Akun Default

### Admin
- Username: `admin`
- Password: `password`

### Bendahara
- Username: `bendahara`
- Password: `password`

## Struktur Database

### Tabel Utama

- `users` - Data pengguna dan autentikasi
- `warga` - Data penduduk lengkap
- `berita` - Publikasi berita RT
- `kegiatan` - Informasi kegiatan RT
- `komentar` - Komentar pada berita/kegiatan
- `surat` - Pengajuan surat
- `iuran_posting` - Posting iuran oleh bendahara
- `iuran_pembayaran` - Pembayaran iuran oleh warga
- `pemasukan` - Pemasukan manual (donasi, dll)
- `pengeluaran` - Pengeluaran RT
- `activity_log` - Log aktivitas pengguna

## Keamanan


## Penggunaan

### Sebagai Admin

1. Login dengan akun admin
2. Kelola data penduduk melalui menu "Data Penduduk"
3. Verifikasi pengajuan surat di menu "Surat"
4. Monitoring keuangan di menu "Keuangan RT"
5. Publikasi berita dan kegiatan

### Sebagai Bendahara

1. Login dengan akun bendahara
2. Posting iuran bulanan di menu "Iuran"
3. Verifikasi pembayaran warga
4. Input pemasukan manual (donasi, dll)
5. Catat pengeluaran RT
6. Generate laporan keuangan

### Sebagai Warga

1. Login dengan akun yang diberikan (username: warga + 6 digit terakhir NIK)
2. Lihat informasi berita dan kegiatan
3. Ajukan surat pengantar online
4. Upload bukti pembayaran iuran
5. Lihat data keluarga

## Troubleshooting

### Error: "Connection failed"
- Periksa konfigurasi database di `includes/db_connect.php`
- Pastikan MySQL service berjalan
- Periksa username dan password database

### Error: "Failed to upload file"
- Periksa permission folder `uploads/`
- Pastikan ukuran file tidak melebihi 5MB
- Periksa tipe file yang diupload

### Halaman blank/white screen
- Enable error reporting di PHP
- Periksa PHP error log
- Pastikan semua file include tersedia

### Session tidak tersimpan
- Periksa konfigurasi session di `php.ini`
- Pastikan folder session memiliki permission write
- Clear browser cache dan cookies

## Kontribusi

Kontribusi sangat diterima. Silakan buat Pull Request dengan deskripsi perubahan yang jelas.

### Cara Berkontribusi

1. Fork repository ini
2. Buat branch baru (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

## Lisensi

Project ini dibuat untuk keperluan edukasi dan pengembangan sistem informasi RT.

## Kontak

Untuk pertanyaan atau saran, silakan buat issue di repository ini.

## Changelog

### Version 1.0.0
- Initial release
- Fitur manajemen kependudukan
- Fitur keuangan dan iuran
- Fitur surat menyurat
- Fitur berita dan kegiatan
- Dashboard untuk 3 role pengguna
