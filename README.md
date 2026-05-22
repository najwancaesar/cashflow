<!--
  README for CashFlow Control
  Tips:
  - Put screenshots inside /img and keep the filenames used below.
  - This README uses GitHub-flavored Markdown, emoji, shields.io badges, and simple HTML tables.
-->

<div align="center">

# 💸 CashFlow Control

### Personal finance dashboard built with PHP Native, MySQL, and TCPDF.

Kelola pemasukan, pengeluaran, multi-wallet, transfer saldo, budget kategori, Celengan Virtual, hingga laporan PDF/CSV dalam satu aplikasi web lokal yang ringan dan praktis.

<br>

![PHP](https://img.shields.io/badge/PHP-Native-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![TCPDF](https://img.shields.io/badge/TCPDF-PDF_Report-E34F26?style=for-the-badge&logo=adobeacrobatreader&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Local_Server-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)
![Responsive](https://img.shields.io/badge/Responsive-Mobile_Ready-14B8A6?style=for-the-badge)

<br>

[✨ Fitur](#-fitur-unggulan) •
[🖼️ Preview](#-preview-aplikasi) •
[⚙️ Instalasi](#️-panduan-instalasi) •
[🚀 Cara Pakai](#-cara-menggunakan) •
[📊 Laporan](#-laporan--export)

</div>

---

## 📌 Tentang Project

**CashFlow Control** adalah aplikasi web untuk membantu pengguna mencatat, memantau, dan mengelola arus kas pribadi secara lebih terstruktur.

Aplikasi ini awalnya dibuat sebagai project pembelajaran, lalu dikembangkan menjadi personal finance dashboard dengan fitur yang lebih lengkap seperti **Multi-Wallet**, **Transfer Wallet**, **Budget per Kategori**, **Celengan Virtual**, **Quick Add**, dan **Laporan PDF/CSV**.

> Cocok untuk penggunaan pribadi, pencatatan keuangan harian, monitoring saldo, budgeting bulanan, dan rekap laporan keuangan lokal menggunakan XAMPP/Laragon.

---

## ✨ Fitur Unggulan

<table>
  <tr>
    <td width="33%">
      <h3>💰 Transaksi Harian</h3>
      <p>Catat pemasukan dan pengeluaran dengan kategori, wallet, status transaksi, catatan, dan nominal terformat otomatis.</p>
    </td>
    <td width="33%">
      <h3>🏦 Multi-Wallet</h3>
      <p>Kelola Cash, Bank, E-Wallet, Tabungan, dan dompet lainnya dalam satu dashboard saldo.</p>
    </td>
    <td width="33%">
      <h3>🔁 Transfer Wallet</h3>
      <p>Pindahkan saldo antar wallet tanpa dianggap sebagai pemasukan atau pengeluaran.</p>
    </td>
  </tr>
  <tr>
    <td width="33%">
      <h3>🎯 Budget Kategori</h3>
      <p>Atur budget bulanan per kategori pengeluaran dengan status Aman, Warning, dan Over Budget.</p>
    </td>
    <td width="33%">
      <h3>🐷 Celengan Virtual</h3>
      <p>Buat target tabungan, setor dari wallet, tarik ke wallet, dan pantau progress tabungan.</p>
    </td>
    <td width="33%">
      <h3>📊 Laporan PDF/CSV</h3>
      <p>Buat laporan custom date range, preview, export PDF via TCPDF, dan export CSV.</p>
    </td>
  </tr>
</table>

---

## 🧩 Modul Aplikasi

| Modul | Deskripsi |
|---|---|
| 🔐 **Auth & Role** | Login, register, role admin/user, SweetAlert welcome, logout confirmation. |
| 🏠 **Dashboard User** | Ringkasan mingguan, saldo wallet, budget, insight, Celengan Virtual, Quick Add, transaksi terbaru. |
| 👥 **Dashboard Admin** | Monitoring user dan manajemen pengguna. |
| 💵 **Pemasukan** | Tambah/edit/hapus pemasukan, kategori, wallet tujuan, status pending/selesai. |
| 🧾 **Pengeluaran** | Tambah/edit/hapus pengeluaran, kategori, wallet sumber, status pending/selesai. |
| 🏷️ **Kategori & Budget** | Kelola kategori dan budget bulanan kategori pengeluaran. |
| 🏦 **Wallet** | Tambah/edit wallet, set default, aktif/nonaktif wallet. |
| 🔁 **Transfer Wallet** | Transfer saldo antar wallet, validasi saldo cukup, batal/hapus permanen sesuai status. |
| 🐷 **Celengan Virtual** | Target tabungan, setor/tarik via wallet, progress, riwayat mutasi, arsip. |
| 🤝 **Hutang & Piutang** | Pencatatan hutang/piutang dan status pelunasan. |
| 📄 **Laporan** | Pemasukan, pengeluaran, hutang, piutang, transfer, dan celengan dengan preview/PDF/CSV. |
| 👤 **Profile** | Edit profile, ganti password, upload foto profil tervalidasi. |

---

## 🖼️ Preview Aplikasi

> Simpan screenshot kamu ke folder `img/` dengan nama file berikut, atau ubah path gambar sesuai kebutuhan.

<table>
  <tr>
    <td align="center" width="50%">
      <img src="img/preview-dashboard-desktop.png" alt="Dashboard desktop preview" width="100%">
      <br>
      <b>🖥️ Dashboard Desktop</b>
      <br>
      <sub>Ringkasan wallet, budget, insight, quick add, dan transaksi terbaru.</sub>
    </td>
    <td align="center" width="50%">
      <img src="img/preview-dashboard-mobile.png" alt="Dashboard mobile preview" width="100%">
      <br>
      <b>📱 Dashboard Mobile</b>
      <br>
      <sub>Card transaksi terbaru dan navbar fixed untuk akses hamburger/profile.</sub>
    </td>
  </tr>
  <tr>
    <td align="center" width="50%">
      <img src="img/preview-wallet-transfer.png" alt="Wallet transfer preview" width="100%">
      <br>
      <b>🔁 Transfer Wallet</b>
      <br>
      <sub>Perpindahan saldo antar wallet tanpa mengubah laporan pemasukan/pengeluaran.</sub>
    </td>
    <td align="center" width="50%">
      <img src="img/preview-celengan-virtual.png" alt="Celengan virtual preview" width="100%">
      <br>
      <b>🐷 Celengan Virtual</b>
      <br>
      <sub>Target tabungan, progress, setor/tarik, dan riwayat mutasi.</sub>
    </td>
  </tr>
  <tr>
    <td align="center" colspan="2">
      <img src="img/preview-report-pdf.png" alt="Report PDF preview" width="80%">
      <br>
      <b>📄 Laporan PDF & CSV</b>
      <br>
      <sub>Preview laporan, custom date range, filter wallet, export PDF, dan export CSV.</sub>
    </td>
  </tr>
</table>

---

## 🛠️ Tech Stack

| Kategori | Teknologi |
|---|---|
| Backend | PHP Native |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript, jQuery |
| PDF Export | TCPDF |
| UI Interaction | SweetAlert, Bootstrap-style components, DataTables |
| Local Server | XAMPP / Laragon / WAMP / LAMP |
| Version Control | Git & GitHub |

---

## 📁 Struktur Folder

```text
cashflow/
├── assets/                 # CSS, JS, images, profile uploads, responsive fixes
├── bower_components/       # Frontend dependencies
├── css/                    # Stylesheet bawaan template
├── fonts/                  # Font assets
├── img/                    # Screenshot README dan image project
├── includes/               # Koneksi, navbar, sidebar, content routing, helpers
├── js/                     # JavaScript project/template
├── tcpdf/                  # Library TCPDF untuk export PDF
├── db_transaksi.sql        # Struktur database utama
├── index.php               # Entry point / landing
├── login.php               # Login handler/page
├── register.php            # Register user
├── main.php                # Layout utama setelah login
├── view_*.php              # Halaman tampilan modul
├── aksi_*.php              # Handler aksi CRUD / proses data
└── README.md               # Dokumentasi project
```

---

## ⚙️ Panduan Instalasi

### 1. Clone repository

```bash
git clone https://github.com/yourusername/cashflow.git
```

Atau download ZIP dari GitHub, lalu extract ke folder web server lokal.

### 2. Pindahkan ke folder local server

Untuk XAMPP:

```text
C:/xampp/htdocs/cashflow
```

Untuk Laragon:

```text
C:/laragon/www/cashflow
```

### 3. Jalankan Apache dan MySQL

Buka XAMPP Control Panel, lalu start:

```text
Apache
MySQL
```

### 4. Buat database

Buka phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Buat database baru, contoh:

```sql
CREATE DATABASE cashflow;
```

### 5. Import database

Import file:

```text
db_transaksi.sql
```

melalui menu **Import** di phpMyAdmin.

### 6. Konfigurasi koneksi database

Sesuaikan konfigurasi database pada file koneksi project, misalnya:

```text
includes/koneksi.php
```

Contoh konfigurasi umum:

```php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "cashflow";
```

> Nama variabel bisa berbeda tergantung struktur file koneksi di project. Intinya sesuaikan host, username, password, dan nama database.

### 7. Jalankan aplikasi

Buka browser:

```text
http://localhost/cashflow/
```

---

## 🚀 Cara Menggunakan

### 🧑‍💻 Untuk User

1. **Register atau login** ke aplikasi.
2. Buka menu **Wallet** dan pastikan memiliki minimal satu wallet aktif.
3. Buat kategori pemasukan dan pengeluaran jika diperlukan.
4. Catat transaksi melalui:
   - menu **Pemasukan**,
   - menu **Pengeluaran**,
   - atau **Quick Add** dari dashboard.
5. Atur budget kategori pengeluaran melalui menu **Kategori**.
6. Gunakan **Transfer Wallet** untuk memindahkan saldo antar wallet.
7. Gunakan **Celengan Virtual** untuk target tabungan.
8. Pantau ringkasan melalui dashboard.
9. Cetak atau export laporan melalui menu **Laporan**.

### 🛡️ Untuk Admin

1. Login sebagai admin.
2. Buka dashboard admin.
3. Kelola data pengguna.
4. Pantau status user dan aktivitas aplikasi secara umum.

---

## ⚡ Quick Add Dashboard

Dashboard user menyediakan tombol cepat untuk:

| Aksi | Fungsi |
|---|---|
| ➕ **Pemasukan** | Catat pemasukan langsung dari dashboard. |
| ➖ **Pengeluaran** | Catat pengeluaran tanpa masuk menu transaksi. |
| 🔁 **Transfer Wallet** | Pindahkan saldo antar wallet secara cepat. |
| 🐷 **Setor Celengan** | Setor dana ke Celengan Virtual dari wallet aktif. |

---

## 🏦 Rumus Saldo Wallet

Saldo wallet dihitung dari beberapa sumber:

```text
Saldo Akhir Wallet =
Saldo Awal
+ Pemasukan Selesai
- Pengeluaran Selesai
+ Transfer Masuk Selesai
- Transfer Keluar Selesai
- Setor Celengan
+ Tarik Celengan
```

Status `pending` dan `batal` tidak dihitung sebagai saldo aktual.

---

## 📊 Laporan & Export

Laporan mendukung custom date range lintas hari, bulan, dan tahun.

### Jenis laporan

- 💵 Pemasukan
- 🧾 Pengeluaran
- 🤝 Hutang
- 🤲 Piutang
- 🔁 Transfer Wallet
- 🐷 Celengan Virtual

### Output laporan

| Output | Deskripsi |
|---|---|
| 👁️ Preview | Menampilkan laporan langsung di browser. |
| 📄 PDF | Export laporan formal menggunakan TCPDF. |
| 📑 CSV | Export data agar bisa dibuka di spreadsheet. |

### Filter laporan

- Tanggal awal dan tanggal akhir.
- Kategori untuk pemasukan/pengeluaran.
- Wallet untuk laporan yang relevan.

---

## 🔐 Catatan Keamanan

Beberapa bagian aplikasi sudah dilengkapi hardening:

- Validasi session login.
- Role guard admin/user.
- Validasi ownership data user.
- Prepared statement pada query penting.
- CSRF protection untuk banyak aksi mutasi.
- Upload foto profil dengan validasi ekstensi, MIME type, dan ukuran.
- Aksi hapus/status penting memakai POST.
- SweetAlert untuk konfirmasi aksi.

> Tetap gunakan aplikasi ini di environment yang aman, terutama jika masih berjalan secara lokal menggunakan XAMPP.

---

## 📱 Mobile Experience

CashFlow Control sudah dipoles agar lebih nyaman di HP:

- Dashboard terbaru tampil sebagai card di mobile.
- Navbar hamburger/profile fixed saat scroll.
- Modal input dibuat lebih nyaman untuk layar kecil.
- Budget kategori menggunakan modal.
- Tabel besar tetap memakai responsive wrapper atau card khusus.

---

## 🧪 Checklist Testing Setelah Install

Gunakan checklist ini setelah setup:

- [ ] Aplikasi bisa dibuka di browser.
- [ ] Register user baru berhasil.
- [ ] User baru memiliki wallet default.
- [ ] Login user berhasil.
- [ ] Tambah pemasukan berhasil.
- [ ] Tambah pengeluaran berhasil.
- [ ] Quick Add dashboard berjalan.
- [ ] Transfer wallet memengaruhi saldo.
- [ ] Celengan Virtual bisa setor/tarik via wallet.
- [ ] Budget kategori bisa disimpan.
- [ ] Laporan preview tampil.
- [ ] Export PDF berhasil.
- [ ] Export CSV berhasil.
- [ ] Login admin tidak error.

---

## 🗺️ Roadmap

Beberapa pengembangan yang bisa dipertimbangkan:

- [ ] Backup & restore database.
- [ ] PWA Basic untuk hosting HTTPS.
- [ ] Filter dashboard berdasarkan wallet.
- [ ] Statistik/grafik yang lebih detail.
- [ ] Audit log aktivitas.
- [ ] Recurring transaction manual.
- [ ] Dokumentasi deployment hosting.

---

## 🤝 Kontribusi

Kontribusi sangat terbuka.

1. Fork repository.
2. Buat branch baru.

```bash
git checkout -b feature/nama-fitur
```

3. Commit perubahan.

```bash
git commit -m "Add new feature"
```

4. Push branch.

```bash
git push origin feature/nama-fitur
```

5. Buat Pull Request.

---

## 🧾 Lisensi

Project ini menggunakan lisensi **MIT**.  
Silakan gunakan, pelajari, dan modifikasi sesuai kebutuhan.

---

## 👨‍💻 Author

<div align="center">

**Najwan Caesar Firstiansyah**

[![Email](https://img.shields.io/badge/Email-najwan12311%40gmail.com-D14836?style=for-the-badge&logo=gmail&logoColor=white)](mailto:najwan12311@gmail.com)
[![GitHub](https://img.shields.io/badge/GitHub-najwancaesar-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/najwancaesar)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-Najwan_Caesar_Firstiansyah-0A66C2?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/najwan-caesar-firstiansyah-152814266/)

</div>

---

<div align="center">

### ⭐ CashFlow Control

Jika project ini bermanfaat, jangan lupa beri star di repository GitHub kamu.

**Built with passion using PHP Native + MySQL.**

</div>
