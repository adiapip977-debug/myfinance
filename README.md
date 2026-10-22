# MYFINANCE — Personal Finance Tracker

Aplikasi web pengelolaan keuangan pribadi, pencatatan pemasukan, pengeluaran, saldo, anggaran bulanan, target tabungan, rekening dompet, dan laporan analitik keuangan lengkap.

Dibangun menggunakan **PHP Native**, **MySQL / MariaDB**, **PDO Prepared Statements**, **HTML5/CSS3/JavaScript**, dan **Chart.js** dengan desain visual modern berbasis tema Navy Gelap (`#111827`) dan Ungu (`#7C3AED`).

---

## 📁 Struktur Folder Proyek

```
myfinance/
├── index.php                 # Gerbang masuk & pengalihan otomatis
├── login.php                 # Autentikasi masuk pengguna
├── register.php              # Pendaftaran akun baru & inisialisasi saldo
├── logout.php                # Keluar aman & pembersihan sesi
├── dashboard.php             # Dashboard utama dengan 4 metrik & grafik
├── pemasukan.php             # Manajemen pencatatan pemasukan (income)
├── pengeluaran.php           # Manajemen pencatatan pengeluaran (expense)
├── transaksi.php             # Riwayat gabungan seluruh transaksi & filter
├── anggaran.php              # Manajemen anggaran bulanan & pemantauan batas
├── tabungan.php              # Manajemen target tabungan & alokasi setoran
├── rekening.php              # Rekening & dompet serta transfer internal
├── laporan.php               # Analitik keuangan, filter periode, cetak & ekspor
├── profil.php                # Profil, ganti password, ubah saldo awal
├── database.sql              # Skema lengkap MySQL & kategori default
├── setup_xampp.bat           # Skrip otomatis sinkronisasi ke C:\xampp\htdocs\myfinance
├── manifest.json             # Manifest Progressive Web App (PWA)
├── service-worker.js         # Service Worker PWA
├── config/
│   └── database.php          # Konfigurasi PDO, auto-create database & tabel
├── includes/
│   ├── auth.php              # Manajemen sesi & otentikasi aman
│   ├── csrf.php              # Proteksi Cross-Site Request Forgery (CSRF)
│   ├── functions.php         # Format Rupiah, kalkulasi finansial & helper icon
│   ├── header.php            # Head HTML, PWA meta, CSS & library Chart.js
│   ├── sidebar.php           # Navigasi desktop navy & bottom nav mobile
│   └── footer.php            # Penutup layout & skrip klien
├── actions/
│   ├── transaksi_action.php  # CRUD Transaksi (Pemasukan & Pengeluaran)
│   ├── transfer_action.php   # Transaksi Transfer Antar-Rekening
│   ├── anggaran_action.php   # Simpan, edit & hapus anggaran bulanan
│   ├── tabungan_action.php   # Target tabungan & kontribusi setoran
│   ├── rekening_action.php   # Tambah, ubah & hapus rekening/dompet
│   └── profil_action.php     # Perbarui profil pengguna & kata sandi
├── export/
│   └── export_csv.php        # Ekspor riwayat transaksi ke format CSV Excel
└── assets/
    ├── css/
    │   └── style.css         # Desain visual responsif sistem warna tetap
    ├── js/
    │   └── script.js         # Masking input Rupiah, modal dialog, auto alert
    └── icons/
        ├── icon-192.svg      # Ikon aplikasi PWA 192x192
        └── icon-512.svg      # Ikon aplikasi PWA 512x512
```

---

## 🚀 Panduan Instalasi dan Menjalankan Aplikasi

### Langkah 1: Membuka Folder Proyek di Visual Studio Code
1. Buka aplikasi **Visual Studio Code** di Windows 11.
2. Klik menu **File** > **Open Folder...** (atau tekan `Ctrl + K Ctrl + O`).
3. Arahkan ke folder proyek ini (`c:\Users\Asus\Desktop\KODING\KEUANGAN` atau `C:\xampp\htdocs\myfinance`).
4. Klik **Select Folder**.

### Langkah 2: Menyalin Proyek ke XAMPP (`C:\xampp\htdocs\myfinance`)
Agar dapat diakses melalui `http://localhost/myfinance/`:
- **Cara 1 (Otomatis):** Cukup klik dua kali (double-click) file `setup_xampp.bat` yang tersedia di folder proyek. Skrip akan langsung menyalin semua file ke `C:\xampp\htdocs\myfinance`.
- **Cara 2 (Manual):** Salin (Copy) seluruh isi folder proyek ini ke `C:\xampp\htdocs\myfinance`.

### Langkah 3: Menjalankan Apache dan MySQL di XAMPP
1. Buka aplikasi **XAMPP Control Panel** dari menu Start Windows 11.
2. Pada baris **Apache**, klik tombol **Start** hingga indikator berwarna hijau.
3. Pada baris **MySQL**, klik tombol **Start** hingga indikator berwarna hijau.

### Langkah 4: Membuka phpMyAdmin & Impor Database
Aplikasi MYFINANCE memiliki fitur **Auto-Creation Database** (otomatis membuat database dan tabel saat pertama kali dibuka). Namun, Anda juga dapat mengimpor file `database.sql` secara manual melalui phpMyAdmin:
1. Buka browser (Google Chrome / Microsoft Edge) dan ketik: `http://localhost/phpmyadmin/`.
2. Klik tab **Databases** (Basis Data) di menu atas, beri nama database `myfinance_db`, pilih `utf8mb4_unicode_ci`, lalu klik **Create**.
3. Pilih database `myfinance_db` di menu sebelah kiri.
4. Klik tab **Import** (Impor) di menu atas.
5. Klik tombol **Choose File**, pilih file `database.sql` dari folder proyek, lalu gulir ke bawah dan klik **Import**.

### Langkah 5: Memeriksa Konfigurasi Koneksi Database
File konfigurasi terletak di `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'myfinance_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```
Jika MySQL Anda menggunakan password kustom, cukup sesuaikan nilai `DB_PASS`.

### Langkah 6: Membuka Aplikasi Melalui Browser
Buka browser Anda dan akses tautan berikut:
```
http://localhost/myfinance/
```
*(Atau jika menggunakan server bawaan PHP dari terminal: `php -S localhost:8000` lalu buka `http://localhost:8000`)*

---

## 📖 Panduan Penggunaan Fitur

### 1. Registrasi Akun & Login
1. Klik **Daftar Akun Baru** pada halaman login (`register.php`).
2. Masukkan nama lengkap, username unik, email, kata sandi (minimal 6 karakter), serta saldo awal uang Anda saat ini.
3. Klik **Daftar & Masuk**.
4. Sistem akan otomatis membuatkan akun dompet bawaan (*Dompet Tunai*, *Rekening Bank*, *E-Wallet*) dan kategori standar.

### 2. Membaca Dashboard & Grafik
- **Total Saldo Saat Ini**: Dihitung otomatis dengan rumus: `Saldo Awal Pengguna + Total Pemasukan - Total Pengeluaran`.
- **Pemasukan Bulan Ini**: Akumulasi seluruh uang masuk pada bulan berjalan.
- **Pengeluaran Bulan Ini**: Akumulasi seluruh pengeluaran pada bulan berjalan beserta persentase terhadap pemasukan.
- **Arus Kas Bersih**: Nilai surplus atau defisit (`Pemasukan - Pengeluaran`).
- **Grafik Arus Kas Harian**: Grafik batang interaktif Chart.js yang membandingkan uang masuk dan keluar setiap tanggal.
- **Diagram Donat Kategori**: Visualisasi pos pengeluaran terbesar bulan ini.
- **Peringatan Anggaran**: Kartu peringatan otomatis jika ada pos anggaran yang telah mencapai $\ge 80\%$ atau terlampaui.

### 3. Menambah Pemasukan & Pengeluaran
- Klik tombol **Tambah Pemasukan** (hijau) atau **Tambah Pengeluaran** (merah).
- Masukkan nominal berupa angka biasa (misal: `1500000`). Sistem otomatis memformatnya menjadi `1.500.000` secara real-time.
- Pilih kategori, tanggal, rekening penerima/sumber dana, serta metode pembayaran.
- Klik **Simpan**. Saldo dan seluruh laporan akan langsung diperbarui seketika.

### 4. Mengatur Anggaran Bulanan
1. Buka menu **Anggaran Bulanan** (`anggaran.php`).
2. Klik tombol **Pasang Anggaran**.
3. Pilih kategori pengeluaran (misal: *Makanan dan minuman*), bulan, tahun, dan batas nominal (misal: `Rp1.000.000`).
4. Indikator progress bar akan berubah warna:
   - **Hijau**: Pemakaian di bawah 80%.
   - **Oranye**: Pemakaian mencapai 80%–99% (mendekati batas).
   - **Merah**: Pemakaian mencapai $\ge 100\%$ (anggaran jebol).

### 5. Mengelola Target Tabungan
1. Buka menu **Target Tabungan** (`tabungan.php`).
2. Klik **Buat Target Baru** (misal: *Dana Darurat* target `Rp10.000.000`).
3. Setiap kali menabung, klik tombol **Setor Dana Tabungan** untuk mencatat alokasi uang.
4. Sistem menghitung kekurangan dana secara presisi dan menandai target selesai jika telah mencapai 100%.

### 6. Rekening & Transfer Internal
1. Buka menu **Rekening & Dompet** (`rekening.php`).
2. Anda dapat menambah rekening baru (BCA, Mandiri, GoPay, OVO, dll).
3. Untuk memindahkan dana antar-rekening (misal: tarik tunai dari Bank ke Dompet Tunai), gunakan fitur **Transfer Antar-Rekening**.
4. Transfer internal **tidak akan dihitung sebagai pengeluaran atau pemasukan baru**, sehingga total saldo kekayaan Anda tetap akurat dan tidak terhitung ganda.

### 7. Mengekspor & Mencetak Laporan
1. Buka menu **Laporan Keuangan** (`laporan.php`).
2. Pilih filter periode: *Hari Ini*, *Minggu Ini*, *Bulan Ini*, *Bulan Lalu*, *Tahun Ini*, atau *Rentang Kustom*.
3. Klik **Ekspor CSV** untuk mengunduh laporan transaksi yang kompatibel dengan Microsoft Excel (sudah dilengkapi UTF-8 BOM).
4. Klik **Cetak Laporan** untuk mencetak dokumen fisik atau menyimpannya sebagai file PDF melalui dialog cetak browser.

### 8. Memasang Aplikasi di Layar Utama HP (PWA)
1. Hubungkan HP dan laptop Anda pada jaringan Wi-Fi yang sama.
2. Cek alamat IP laptop Anda melalui Command Prompt (`ipconfig`, misal: `192.168.1.10`).
3. Buka browser Google Chrome di HP Anda dan ketik: `http://192.168.1.10/myfinance/`.
4. Buka menu opsi browser (titik tiga di kanan atas) dan pilih **Tambahkan ke Layar Utama** (*Add to Home screen* / *Install App*).
5. Aplikasi MYFINANCE akan terpasang di layar utama HP Anda dengan ikon dan tampilan seluler yang responsif.

---

## 🛠️ Penyelesaian Masalah Umum (Troubleshooting)

1. **Error: "Koneksi Database Gagal" saat membuka website**
   - Buka XAMPP Control Panel dan pastikan modul **MySQL** sudah berjalan (indikator berwarna hijau).
   - Pastikan port 3306 tidak terblokir oleh aplikasi MySQL lain.

2. **Error: "404 Not Found" saat mengakses http://localhost/myfinance/**
   - Pastikan seluruh folder dan file telah disalin ke folder `C:\xampp\htdocs\myfinance`.
   - Periksa apakah nama folder di htdocs tepat bernama `myfinance` (huruf kecil).

3. **Apache XAMPP tidak mau berjalan (Error Port 80 / 443 in use)**
   - Buka XAMPP Control Panel, klik **Config** pada baris Apache > **httpd.conf**.
   - Cari baris `Listen 80`, ubah menjadi `Listen 8080`.
   - Simpan file lalu nyalakan Apache kembali. Buka website melalui `http://localhost:8080/myfinance/`.

---
*Dibuat secara profesional untuk kebutuhan manajemen finansial pribadi.*

