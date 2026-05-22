# CashFlow Control

**CashFlow Control** adalah aplikasi web berbasis **PHP Native + MySQL** untuk mencatat, memantau, dan mengelola arus kas pribadi. Aplikasi ini dirancang untuk penggunaan harian melalui local server seperti **XAMPP/Laragon**, dengan fitur pencatatan transaksi, multi-wallet, budget kategori, transfer antar wallet, Celengan Virtual, hingga laporan PDF/CSV.

> Project ini dikembangkan sebagai aplikasi personal finance dashboard yang ringan, praktis, dan mudah dijalankan di lingkungan lokal.

---

## Daftar Isi

- [Tentang Project](#tentang-project)
- [Fitur Utama](#fitur-utama)
- [Preview Aplikasi](#preview-aplikasi)
- [Teknologi yang Digunakan](#teknologi-yang-digunakan)
- [Struktur Folder](#struktur-folder)
- [Panduan Instalasi](#panduan-instalasi)
- [Cara Menggunakan Aplikasi](#cara-menggunakan-aplikasi)
- [Catatan Database](#catatan-database)
- [Tips Penggunaan Harian](#tips-penggunaan-harian)
- [Catatan Keamanan](#catatan-keamanan)
- [Roadmap Pengembangan](#roadmap-pengembangan)
- [Kontribusi](#kontribusi)
- [Lisensi](#lisensi)
- [Kontak](#kontak)

---

## Tentang Project

CashFlow Control dibuat untuk membantu pengguna mencatat dan memahami kondisi keuangan pribadi secara lebih terstruktur. Aplikasi ini tidak hanya mencatat pemasukan dan pengeluaran, tetapi juga mendukung pengelolaan beberapa sumber dana seperti cash, rekening bank, e-wallet, transfer antar wallet, target tabungan, budget bulanan per kategori, serta laporan keuangan.

Aplikasi ini cocok untuk:

- mencatat pemasukan dan pengeluaran harian,
- memantau saldo dari beberapa wallet,
- mengatur budget kategori pengeluaran,
- mencatat perpindahan saldo antar wallet,
- membuat target tabungan atau Celengan Virtual,
- memantau hutang dan piutang,
- membuat laporan keuangan berdasarkan periode tertentu.

---

## Fitur Utama

### Autentikasi dan Role

- Login dan register user.
- Role **admin** dan **user**.
- Dashboard berbeda untuk admin dan user.
- Manajemen pengguna untuk admin.
- SweetAlert welcome setelah login.
- Logout dengan konfirmasi.

### Dashboard User

- Ringkasan pemasukan minggu ini.
- Ringkasan pengeluaran minggu ini.
- Jumlah transaksi minggu ini.
- Saldo wallet aktif.
- Insight bulan ini.
- Ringkasan budget bulan ini.
- Ringkasan Celengan Virtual.
- Quick Add untuk input cepat:
  - Pemasukan,
  - Pengeluaran,
  - Transfer Wallet,
  - Setor Celengan.
- Tampilan mobile dashboard menggunakan card agar lebih responsif.
- Navbar mobile/tablet fixed agar hamburger dan profile tetap mudah diakses saat scroll.

### Pemasukan dan Pengeluaran

- Tambah, edit, hapus, dan ubah status transaksi.
- Status transaksi:
  - `pending`,
  - `selesai`.
- Kategori transaksi.
- Integrasi wallet pada pemasukan dan pengeluaran.
- Validasi ownership data user.
- Proteksi CSRF untuk aksi penting.
- Format nominal otomatis pada input.

### Kategori dan Budget

- Kelola kategori pemasukan dan pengeluaran.
- Budget bulanan khusus kategori pengeluaran.
- Modal untuk atur budget kategori.
- Progress pemakaian budget.
- Status budget:
  - Aman,
  - Warning,
  - Over Budget.
- Dashboard budget summary:
  - total budget,
  - terpakai,
  - sisa budget,
  - persentase pemakaian,
  - kategori warning/over budget.

### Multi-Wallet

- Kelola beberapa wallet/dompet:
  - Cash,
  - Bank,
  - E-Wallet,
  - Tabungan,
  - Lainnya.
- Set default wallet.
- Aktif/nonaktif wallet.
- Saldo wallet dihitung dari:
  - saldo awal,
  - pemasukan selesai,
  - pengeluaran selesai,
  - transfer masuk/keluar,
  - setor/tarik Celengan Virtual.

### Transfer Wallet

- Transfer saldo antar wallet.
- Contoh:
  - Cash ke Bank BCA,
  - DANA ke ShopeePay,
  - Bank ke Cash.
- Transfer tidak dihitung sebagai pemasukan atau pengeluaran.
- Status transfer:
  - pending,
  - selesai,
  - batal.
- Validasi saldo cukup untuk transfer selesai.
- Transfer selesai dapat dibatalkan.
- Transfer pending/batal dapat dihapus permanen.

### Celengan Virtual

- Membuat target tabungan personal.
- Contoh:
  - Dana Darurat,
  - Beli Laptop,
  - Liburan,
  - Servis Motor.
- Setor ke celengan dari wallet.
- Tarik dari celengan ke wallet.
- Progress target tabungan.
- Riwayat mutasi celengan.
- Status:
  - aktif,
  - selesai,
  - arsip.
- Setor/tarik memengaruhi saldo wallet.

### Hutang dan Piutang

- Pencatatan hutang.
- Pencatatan piutang.
- Status pelunasan.
- Cocok untuk memantau kewajiban dan tagihan personal.

### Laporan

- Laporan berdasarkan custom date range.
- Mendukung range lintas hari, bulan, dan tahun.
- Jenis laporan:
  - Pemasukan,
  - Pengeluaran,
  - Hutang,
  - Piutang,
  - Transfer Wallet,
  - Celengan Virtual.
- Filter wallet untuk laporan yang relevan.
- Filter kategori untuk pemasukan/pengeluaran.
- Preview laporan.
- Export PDF menggunakan TCPDF.
- Export CSV.
- Header dan footer laporan dibuat lebih formal.

### Profile dan Upload Foto

- Edit profile.
- Ubah password dengan validasi server-side.
- Upload foto profil dengan validasi:
  - ekstensi,
  - MIME type,
  - ukuran file,
  - nama file random.

---

## Preview Aplikasi

Simpan screenshot aplikasi pada folder `img/`, lalu sesuaikan nama file dengan daftar di bawah ini.

| Preview | Deskripsi | Path Gambar |
|---|---|---|
| Dashboard Desktop | Tampilan dashboard utama versi desktop, berisi ringkasan cashflow, wallet, budget, quick add, dan insight. | `img/preview-dashboard-desktop.png` |
| Dashboard Mobile | Tampilan dashboard versi mobile dengan card transaksi terbaru dan navbar fixed. | `img/preview-dashboard-mobile.png` |
| Wallet dan Transfer | Tampilan modul wallet serta transfer antar wallet. | `img/preview-wallet-transfer.png` |
| Celengan Virtual | Tampilan target tabungan, progress, setor/tarik, dan riwayat mutasi celengan. | `img/preview-celengan-virtual.png` |
| Laporan PDF | Tampilan preview atau hasil export laporan PDF/CSV. | `img/preview-report-pdf.png` |

### Contoh Penempatan Gambar

```md
![Dashboard Desktop](img/preview-dashboard-desktop.png)
![Dashboard Mobile](img/preview-dashboard-mobile.png)
![Wallet dan Transfer](img/preview-wallet-transfer.png)
![Celengan Virtual](img/preview-celengan-virtual.png)
![Laporan PDF](img/preview-report-pdf.png)
```

> Jika nama file screenshot berbeda, ubah path gambar pada README sesuai nama file yang kamu gunakan.

---

## Teknologi yang Digunakan

### Backend

- PHP Native
- MySQL / MariaDB

### Frontend

- HTML
- CSS
- JavaScript
- jQuery
- Bootstrap / template admin existing
- SweetAlert
- DataTables

### Library dan Tools

- TCPDF untuk export PDF
- XAMPP / Laragon / LAMP / WAMP
- phpMyAdmin
- Git dan GitHub

---

## Struktur Folder

```text
cashflow/
├── assets/
│   ├── css/                    # Styling aplikasi dan responsive CSS
│   ├── img/                    # Asset gambar aplikasi
│   └── js/                     # JavaScript custom
├── bower_components/           # Dependency frontend bawaan template
├── includes/                   # Sidebar, navbar, routing content, helper
├── tcpdf/                      # Library TCPDF untuk export PDF
├── img/                        # Folder screenshot README / dokumentasi
├── db_transaksi.sql            # Struktur database aplikasi
├── index.php                   # Entry point / redirect awal
├── login.php                   # Halaman dan proses login
├── register.php                # Halaman dan proses register
├── main.php                    # Layout utama setelah login
├── view_home.php               # Dashboard
├── view_kategori.php           # Halaman kategori dan budget
├── view_pemasukan.php          # Halaman pemasukan
├── view_pengeluaran.php        # Halaman pengeluaran
├── view_wallet.php             # Halaman wallet/dompet
├── view_transfer_wallet.php    # Halaman transfer antar wallet
├── view_saving_goal.php        # Halaman Celengan Virtual
├── view_laporan.php            # Form dan preview laporan
├── aksi_*.php                  # Handler aksi CRUD dan transaksi
└── README.md                   # Dokumentasi project
```

---

## Panduan Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/username/cashflow.git
```

Masuk ke folder project:

```bash
cd cashflow
```

> Ganti `username/cashflow` sesuai URL repository kamu.

### 2. Pindahkan Project ke Web Server Lokal

Jika menggunakan XAMPP, letakkan folder project di:

```text
C:/xampp/htdocs/cashflow
```

Jika menggunakan Laragon, letakkan di folder:

```text
C:/laragon/www/cashflow
```

### 3. Jalankan Apache dan MySQL

Buka XAMPP Control Panel atau Laragon, lalu jalankan:

- Apache
- MySQL

### 4. Buat Database

Buka phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Buat database baru, misalnya:

```sql
CREATE DATABASE cashflow;
```

### 5. Import Database

Import file:

```text
db_transaksi.sql
```

melalui menu **Import** di phpMyAdmin.

Pastikan tabel penting seperti berikut sudah terbentuk:

- `user`
- `kategori`
- `pemasukan`
- `pengeluaran`
- `wallet`
- `transfer_wallet`
- `saving_goal`
- `saving_goal_mutasi`
- tabel hutang/piutang sesuai struktur project

### 6. Konfigurasi Koneksi Database

Sesuaikan konfigurasi koneksi database pada file koneksi project.

Contoh umum:

```php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "cashflow";
```

> Nama file koneksi bisa berbeda tergantung struktur project. Cek file koneksi yang digunakan oleh aplikasi, lalu sesuaikan nama database, username, dan password MySQL kamu.

### 7. Jalankan Aplikasi

Buka browser:

```text
http://localhost/cashflow/
```

Atau jika folder project memiliki nama berbeda:

```text
http://localhost/nama-folder-project/
```

---

## Cara Menggunakan Aplikasi

### 1. Register dan Login

1. Buka halaman aplikasi.
2. Buat akun melalui halaman register.
3. Login menggunakan akun yang sudah dibuat.
4. Setelah login, user akan diarahkan ke dashboard.

### 2. Setup Wallet

1. Buka menu **Wallet**.
2. Tambahkan wallet seperti:
   - Cash,
   - Bank BCA,
   - DANA,
   - ShopeePay,
   - Tabungan.
3. Tentukan salah satu wallet sebagai default.
4. Wallet default akan otomatis dipakai sebagai pilihan awal di beberapa form transaksi.

### 3. Setup Kategori

1. Buka menu **Kategori**.
2. Tambahkan kategori pemasukan, misalnya:
   - Gaji,
   - Bonus,
   - Freelance.
3. Tambahkan kategori pengeluaran, misalnya:
   - Makan & Minum,
   - Transportasi,
   - Internet,
   - Belanja.
4. Untuk kategori pengeluaran, atur budget bulanan jika diperlukan.

### 4. Catat Pemasukan

1. Buka menu **Pemasukan** atau gunakan **Quick Add** di dashboard.
2. Pilih tanggal.
3. Pilih kategori pemasukan.
4. Pilih wallet tujuan.
5. Masukkan nominal dan catatan.
6. Simpan transaksi.

### 5. Catat Pengeluaran

1. Buka menu **Pengeluaran** atau gunakan **Quick Add** di dashboard.
2. Pilih tanggal.
3. Pilih kategori pengeluaran.
4. Pilih wallet sumber.
5. Masukkan nominal dan catatan.
6. Simpan transaksi.
7. Jika kategori memiliki budget, dashboard akan menghitung progress pemakaian budget.

### 6. Transfer Antar Wallet

1. Buka menu **Transfer Wallet**.
2. Pilih wallet asal.
3. Pilih wallet tujuan.
4. Masukkan nominal transfer.
5. Pilih status transfer.
6. Simpan.
7. Jika status `selesai`, saldo wallet asal akan berkurang dan wallet tujuan akan bertambah.

### 7. Gunakan Celengan Virtual

1. Buka menu **Celengan Virtual**.
2. Buat celengan baru, misalnya **Dana Darurat**.
3. Tentukan target nominal.
4. Setor saldo dari wallet sumber.
5. Tarik saldo ke wallet tujuan jika diperlukan.
6. Pantau progress target melalui halaman Celengan Virtual atau dashboard.

### 8. Kelola Hutang dan Piutang

1. Buka menu hutang atau piutang.
2. Tambahkan data sesuai kebutuhan.
3. Perbarui status jika sudah lunas atau selesai.

### 9. Buat Laporan

1. Buka menu **Laporan**.
2. Pilih jenis laporan:
   - Pemasukan,
   - Pengeluaran,
   - Hutang,
   - Piutang,
   - Transfer Wallet,
   - Celengan Virtual.
3. Pilih tanggal awal dan tanggal akhir.
4. Gunakan filter wallet atau kategori jika diperlukan.
5. Tampilkan preview.
6. Export laporan ke PDF atau CSV.

---

## Catatan Database

File `db_transaksi.sql` berisi struktur database yang diperlukan oleh aplikasi. Jika kamu menambahkan fitur baru, pastikan struktur tabel pada file SQL ikut diperbarui agar project bisa di-import ulang dari awal.

Beberapa tabel penting:

| Tabel | Fungsi |
|---|---|
| `user` | Data akun user dan admin |
| `kategori` | Kategori pemasukan/pengeluaran |
| `pemasukan` | Data transaksi pemasukan |
| `pengeluaran` | Data transaksi pengeluaran |
| `wallet` | Data wallet/dompet user |
| `transfer_wallet` | Data transfer antar wallet |
| `saving_goal` | Data Celengan Virtual |
| `saving_goal_mutasi` | Riwayat setor/tarik Celengan Virtual |

---

## Tips Penggunaan Harian

- Gunakan **Quick Add** di dashboard untuk mencatat transaksi lebih cepat.
- Gunakan status `pending` jika transaksi belum benar-benar terjadi.
- Gunakan status `selesai` untuk transaksi yang sudah final.
- Atur budget hanya pada kategori pengeluaran yang ingin dikontrol.
- Gunakan transfer wallet untuk perpindahan saldo antar sumber dana.
- Gunakan Celengan Virtual untuk target tabungan.
- Export laporan PDF/CSV secara berkala untuk backup data keuangan.

---

## Catatan Keamanan

Aplikasi ini dirancang untuk penggunaan lokal/personal. Jika ingin digunakan di hosting publik, pertimbangkan hal berikut:

- Gunakan HTTPS.
- Ganti credential database default.
- Batasi akses file sensitif.
- Pastikan upload file tetap tervalidasi.
- Jangan expose folder backup/database ke publik.
- Review ulang konfigurasi error reporting pada mode production.
- Lakukan backup database secara berkala.

---

## Roadmap Pengembangan

Beberapa ide pengembangan yang bisa dipertimbangkan:

- Backup dan restore database.
- Filter dashboard berdasarkan wallet.
- PWA Basic untuk Add to Home Screen.
- Recurring transaction untuk transaksi rutin.
- Laporan analitik lanjutan.
- Export laporan gabungan.
- UI polish untuk mobile experience.

---

## Kontribusi

Kontribusi, saran, dan issue sangat terbuka.

Langkah kontribusi umum:

1. Fork repository.
2. Buat branch baru.
3. Lakukan perubahan.
4. Commit perubahan.
5. Buat pull request.

---

## Lisensi

Project ini bersifat open-source dan dapat digunakan untuk pembelajaran maupun pengembangan personal. Jika repository menggunakan lisensi khusus, sesuaikan bagian ini dengan file `LICENSE` yang tersedia.

---

## Kontak

Jika ada pertanyaan, saran, atau ingin berdiskusi tentang project ini:

- **Email:** [najwan12311@gmail.com](mailto:najwan12311@gmail.com)
- **GitHub:** [github.com/najwancaesar](https://github.com/najwancaesar)
- **LinkedIn:** [linkedin.com/in/najwan-caesar-firstiansyah](https://www.linkedin.com/in/najwan-caesar-firstiansyah-152814266/)

---

Dibuat dan dikembangkan sebagai project pembelajaran sekaligus aplikasi personal finance untuk membantu mengontrol arus kas harian.
