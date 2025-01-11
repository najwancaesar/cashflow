-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 05 Jan 2025 pada 06.35
-- Versi server: 10.4.25-MariaDB
-- Versi PHP: 7.4.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_transaksi`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `hutang`
--

CREATE TABLE `hutang` (
  `id_hutang` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `kreditur` varchar(100) NOT NULL,
  `catatan` text NOT NULL,
  `jumlah` int(11) NOT NULL,
  `user` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `hutang`
--

INSERT INTO `hutang` (`id_hutang`, `tanggal`, `kreditur`, `catatan`, `jumlah`, `user`) VALUES
(4, '2025-01-23', 'GACORTOTO888', 'Kalah di 5x lipat', 12000000, '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pemasukan`
--

CREATE TABLE `pemasukan` (
  `id_pemasukan` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `catatan` text NOT NULL,
  `jumlah` int(11) NOT NULL,
  `user` varchar(30) NOT NULL,
  `status` enum('pending','selesai') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `pemasukan`
--

INSERT INTO `pemasukan` (`id_pemasukan`, `tanggal`, `catatan`, `jumlah`, `user`, `status`) VALUES
(11, '2025-01-15', 'gaji bulanan', 1000000, '1', 'selesai'),
(12, '2025-01-05', 'rejeki hari ini', 70000, '1', 'selesai'),
(13, '2025-01-05', 'asd', 200000, '1', 'selesai');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id_pengeluaran` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `catatan` text NOT NULL,
  `jumlah` int(11) NOT NULL,
  `user` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `pengeluaran`
--

INSERT INTO `pengeluaran` (`id_pengeluaran`, `tanggal`, `catatan`, `jumlah`, `user`) VALUES
(5, '2025-01-18', 'pengeluaran bulanan', 500000, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `piutang`
--

CREATE TABLE `piutang` (
  `id_piutang` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `debitur` varchar(100) NOT NULL,
  `catatan` text NOT NULL,
  `jumlah` int(11) NOT NULL,
  `user` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `piutang`
--

INSERT INTO `piutang` (`id_piutang`, `tanggal`, `debitur`, `catatan`, `jumlah`, `user`) VALUES
(5, '2025-01-04', 'AZKA', 'kalah main slot', 2000000, '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user`
--

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL,
  `username` varchar(20) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `no_telp` varchar(13) NOT NULL,
  `foto` varchar(255) NOT NULL,
  `role` enum('admin','mahasiswa','dosen') NOT NULL,
  `create_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` enum('0','1') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `user`
--

INSERT INTO `user` (`id_user`, `username`, `nama`, `email`, `password`, `no_telp`, `foto`, `role`, `create_at`, `is_active`) VALUES
(1, 'admin', 'adminnya w', 'admin@gmail.com', '12345', '0823893244', '1658384572_images.jpg', 'admin', '2022-07-21 05:25:10', '1'),
(2, 'popo', 'popo', 'popo@gmail.com', '12345', '082389324', 'team-5.jpg', 'mahasiswa', '2022-07-21 05:25:10', '1'),
(3, 'gobi', 'gobi', 'gobi@gmail.com', '12345', '082295644497', '1658383819_images.jpg', 'dosen', '2022-07-21 05:25:10', '1'),
(10, 'iben', 'ibnu', 'm.ibnuatho455@gmail.com', '12345', '8929301', '', 'mahasiswa', '2025-01-05 02:25:37', '1'),
(11, 'keping', 'kevin', 'kepin@gmail.com', '12345', 'no_telp', '', 'mahasiswa', '2025-01-05 02:44:47', '1'),
(16, 'najwan', 'najawan', 'najwan@gmail.com', '12345', '912401', '', 'dosen', '2025-01-05 04:16:19', '1');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `hutang`
--
ALTER TABLE `hutang`
  ADD PRIMARY KEY (`id_hutang`);

--
-- Indeks untuk tabel `pemasukan`
--
ALTER TABLE `pemasukan`
  ADD PRIMARY KEY (`id_pemasukan`);

--
-- Indeks untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id_pengeluaran`);

--
-- Indeks untuk tabel `piutang`
--
ALTER TABLE `piutang`
  ADD PRIMARY KEY (`id_piutang`);

--
-- Indeks untuk tabel `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `hutang`
--
ALTER TABLE `hutang`
  MODIFY `id_hutang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `pemasukan`
--
ALTER TABLE `pemasukan`
  MODIFY `id_pemasukan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id_pengeluaran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `piutang`
--
ALTER TABLE `piutang`
  MODIFY `id_piutang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
