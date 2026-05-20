-- MariaDB dump 10.19  Distrib 10.6.17-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: cashflow
-- ------------------------------------------------------
-- Server version	10.6.17-MariaDB-cll-lve
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;

/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;

/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;

/*!40101 SET NAMES utf8mb4 */;

/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;

/*!40103 SET TIME_ZONE='+00:00' */;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;

/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `hutang`
--
DROP TABLE IF EXISTS `hutang`;

/*!40101 SET @saved_cs_client     = @@character_set_client */;

/*!40101 SET character_set_client = utf8 */;

CREATE TABLE
  `hutang` (
    `id_hutang` int (11) NOT NULL AUTO_INCREMENT,
    `tanggal` date NOT NULL,
    `tanggal_jatuh_tempo` date DEFAULT NULL COMMENT 'Tanggal jatuh tempo hutang',
    `kreditur` varchar(100) NOT NULL,
    `catatan` text NOT NULL,
    `jumlah` int (11) NOT NULL,
    `user` int (11) NOT NULL,
    `status` enum ('pending', 'selesai') NOT NULL,
    PRIMARY KEY (`id_hutang`)
  ) ENGINE = InnoDB AUTO_INCREMENT = 16 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hutang`
--
LOCK TABLES `hutang` WRITE;

/*!40000 ALTER TABLE `hutang` DISABLE KEYS */;

INSERT INTO
  `hutang` (
    `id_hutang`,
    `tanggal`,
    `kreditur`,
    `catatan`,
    `jumlah`,
    `user`,
    `status`
  )
VALUES
  (
    8,
    '2025-01-11',
    'fadlan',
    'ok',
    90000,
    32,
    'pending'
  ),
  (
    9,
    '2025-01-13',
    'Bank Ganesha',
    'Peminjaman untuk dana darurat\r\n',
    10000,
    2,
    'pending'
  ),
  (
    10,
    '2025-01-13',
    'ferdi',
    'mixue',
    10000,
    39,
    'pending'
  ),
  (
    13,
    '2025-01-15',
    'myself',
    'oke',
    15000,
    40,
    'pending'
  ),
  (
    14,
    '2025-01-16',
    'ok',
    'tst',
    6555500,
    41,
    'pending'
  ),
  (
    15,
    '2025-02-27',
    'Sakila',
    'peminjaman untuk beli kebutuhan di indomaret\r\n',
    50000,
    52,
    'pending'
  );

/*!40000 ALTER TABLE `hutang` ENABLE KEYS */;

UNLOCK TABLES;

--
-- Table structure for table `kategori`
--
DROP TABLE IF EXISTS `kategori`;

/*!40101 SET @saved_cs_client     = @@character_set_client */;

/*!40101 SET character_set_client = utf8 */;

CREATE TABLE
  `kategori` (
    `id_kategori` int (11) NOT NULL AUTO_INCREMENT,
    `user_id` int (11) NOT NULL,
    `nama_kategori` varchar(100) NOT NULL,
    `tipe_kategori` enum ('pemasukan', 'pengeluaran') NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id_kategori`),
    KEY `idx_kategori_user` (`user_id`),
    KEY `idx_kategori_tipe` (`tipe_kategori`)
  ) ENGINE = InnoDB AUTO_INCREMENT = 61 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategori`
--
LOCK TABLES `kategori` WRITE;

/*!40000 ALTER TABLE `kategori` DISABLE KEYS */;

INSERT INTO
  `kategori` (
    `id_kategori`,
    `user_id`,
    `nama_kategori`,
    `tipe_kategori`,
    `created_at`
  )
VALUES
  (1, 2, 'Gaji', 'pemasukan', '2025-02-27 02:00:00'),
  (2, 2, 'Bonus', 'pemasukan', '2025-02-27 02:00:00'),
  (
    3,
    2,
    'Freelance',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    4,
    2,
    'Investasi',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    5,
    2,
    'Hadiah',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    6,
    2,
    'Lain-lain',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    7,
    2,
    'Kebutuhan Hidup',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    8,
    2,
    'Makan & Minum',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    9,
    2,
    'Transportasi',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    10,
    2,
    'Tagihan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    11,
    2,
    'Hiburan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    12,
    2,
    'Investasi',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    13,
    2,
    'Kesehatan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    14,
    2,
    'Pendidikan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    15,
    2,
    'Lain-lain',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (16, 3, 'Gaji', 'pemasukan', '2025-02-27 02:00:00'),
  (
    17,
    3,
    'Bonus',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    18,
    3,
    'Freelance',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    19,
    3,
    'Investasi',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    20,
    3,
    'Hadiah',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    21,
    3,
    'Lain-lain',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    22,
    3,
    'Kebutuhan Hidup',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    23,
    3,
    'Makan & Minum',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    24,
    3,
    'Transportasi',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    25,
    3,
    'Tagihan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    26,
    3,
    'Hiburan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    27,
    3,
    'Investasi',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    28,
    3,
    'Kesehatan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    29,
    3,
    'Pendidikan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    30,
    3,
    'Lain-lain',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    31,
    52,
    'Gaji',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    32,
    52,
    'Bonus',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    33,
    52,
    'Freelance',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    34,
    52,
    'Investasi',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    35,
    52,
    'Hadiah',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    36,
    52,
    'Lain-lain',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    37,
    52,
    'Kebutuhan Hidup',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    38,
    52,
    'Makan & Minum',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    39,
    52,
    'Transportasi',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    40,
    52,
    'Tagihan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    41,
    52,
    'Hiburan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    42,
    52,
    'Investasi',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    43,
    52,
    'Kesehatan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    44,
    52,
    'Pendidikan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    45,
    52,
    'Lain-lain',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    46,
    53,
    'Gaji',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    47,
    53,
    'Bonus',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    48,
    53,
    'Freelance',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    49,
    53,
    'Investasi',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    50,
    53,
    'Hadiah',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    51,
    53,
    'Lain-lain',
    'pemasukan',
    '2025-02-27 02:00:00'
  ),
  (
    52,
    53,
    'Kebutuhan Hidup',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    53,
    53,
    'Makan & Minum',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    54,
    53,
    'Transportasi',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    55,
    53,
    'Tagihan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    56,
    53,
    'Hiburan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    57,
    53,
    'Investasi',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    58,
    53,
    'Kesehatan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    59,
    53,
    'Pendidikan',
    'pengeluaran',
    '2025-02-27 02:00:00'
  ),
  (
    60,
    53,
    'Lain-lain',
    'pengeluaran',
    '2025-02-27 02:00:00'
  );

/*!40000 ALTER TABLE `kategori` ENABLE KEYS */;

UNLOCK TABLES;

--
-- Table structure for table `budget_kategori`
--


DROP TABLE IF EXISTS `budget_kategori`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;

CREATE TABLE `budget_kategori` (
  `id_budget` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `id_kategori` int(11) NOT NULL,
  `bulan` tinyint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `nominal_budget` bigint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_budget`),
  UNIQUE KEY `uniq_budget_periode` (`user_id`, `id_kategori`, `bulan`, `tahun`),
  KEY `idx_budget_user_periode` (`user_id`, `bulan`, `tahun`),
  KEY `idx_budget_kategori` (`id_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budget_kategori`
--

LOCK TABLES `budget_kategori` WRITE;
/*!40000 ALTER TABLE `budget_kategori` DISABLE KEYS */;
/*!40000 ALTER TABLE `budget_kategori` ENABLE KEYS */;
UNLOCK TABLES;


--
-- Table structure for table `pemasukan`
--
DROP TABLE IF EXISTS `pemasukan`;

/*!40101 SET @saved_cs_client     = @@character_set_client */;

/*!40101 SET character_set_client = utf8 */;

CREATE TABLE
  `pemasukan` (
    `id_pemasukan` int (11) NOT NULL AUTO_INCREMENT,
    `tanggal` date NOT NULL,
    `catatan` text NOT NULL,
    `jumlah` int (11) NOT NULL,
    `user` int (11) NOT NULL,
    `id_kategori` int (11) DEFAULT NULL,
    `id_wallet` int (11) DEFAULT NULL,
    `status` enum ('pending', 'selesai') NOT NULL,
    PRIMARY KEY (`id_pemasukan`),
    KEY `idx_pemasukan_user` (`user`),
    KEY `idx_pemasukan_kategori` (`id_kategori`),
    KEY `idx_pemasukan_wallet` (`id_wallet`)
  ) ENGINE = InnoDB AUTO_INCREMENT = 44 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pemasukan`
--
LOCK TABLES `pemasukan` WRITE;

/*!40000 ALTER TABLE `pemasukan` DISABLE KEYS */;

INSERT INTO
  `pemasukan` (
    `id_pemasukan`,
    `tanggal`,
    `catatan`,
    `jumlah`,
    `user`,
    `status`
  )
VALUES
  (
    11,
    '2025-01-15',
    'gaji bulanan',
    1000000,
    '28',
    'selesai'
  ),
  (
    15,
    '2025-01-07',
    'Restock gaji',
    200000,
    '27',
    'selesai'
  ),
  (17, '2025-01-09', 'test', 100000, '28', 'pending'),
  (22, '2025-01-09', 'fsfs', 121212, '30', 'selesai'),
  (28, '2025-01-11', 'ewfew', 20000, '32', 'selesai'),
  (
    29,
    '2025-01-13',
    'Uang kas',
    10000,
    '2',
    'pending'
  ),
  (31, '2025-01-13', 'oke', 100000, '2', 'selesai'),
  (
    32,
    '2025-01-13',
    'uang saku',
    500000,
    '39',
    'selesai'
  ),
  (
    33,
    '2024-11-14',
    'jersey',
    150000,
    '39',
    'selesai'
  ),
  (
    34,
    '2025-01-13',
    'uang kas',
    50000,
    '39',
    'selesai'
  ),
  (
    36,
    '2025-01-15',
    'testing',
    400000,
    '40',
    'pending'
  ),
  (
    37,
    '2025-01-15',
    'Gaji freelance',
    150000,
    '41',
    'selesai'
  ),
  (
    38,
    '2025-01-16',
    'Nemu dikantong',
    1000000,
    '43',
    'selesai'
  ),
  (
    39,
    '2025-01-07',
    'apani',
    1000000,
    '41',
    'selesai'
  ),
  (
    40,
    '2025-01-27',
    'Gajian sampingan',
    50000000,
    '49',
    'pending'
  ),
  (
    41,
    '2025-01-25',
    'duit',
    1000000,
    '50',
    'selesai'
  ),
  (
    42,
    '2025-01-29',
    'duit',
    1000000,
    '51',
    'selesai'
  ),
  (
    43,
    '2025-02-27',
    'Pemasukan hari ini uang jajan',
    20000,
    '52',
    'selesai'
  );

/*!40000 ALTER TABLE `pemasukan` ENABLE KEYS */;

UNLOCK TABLES;

--
-- Table structure for table `pengeluaran`
--
DROP TABLE IF EXISTS `pengeluaran`;

/*!40101 SET @saved_cs_client     = @@character_set_client */;

/*!40101 SET character_set_client = utf8 */;

CREATE TABLE
  `pengeluaran` (
    `id_pengeluaran` int (11) NOT NULL AUTO_INCREMENT,
    `tanggal` date NOT NULL,
    `catatan` text NOT NULL,
    `jumlah` int (11) NOT NULL,
    `user` int (11) NOT NULL,
    `id_kategori` int (11) DEFAULT NULL,
    `id_wallet` int (11) DEFAULT NULL,
    `status` enum ('pending', 'selesai') NOT NULL,
    PRIMARY KEY (`id_pengeluaran`),
    KEY `idx_pengeluaran_user` (`user`),
    KEY `idx_pengeluaran_kategori` (`id_kategori`),
    KEY `idx_pengeluaran_wallet` (`id_wallet`)
  ) ENGINE = InnoDB AUTO_INCREMENT = 30 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengeluaran`
--
LOCK TABLES `pengeluaran` WRITE;

/*!40000 ALTER TABLE `pengeluaran` DISABLE KEYS */;

INSERT INTO
  `pengeluaran` (
    `id_pengeluaran`,
    `tanggal`,
    `catatan`,
    `jumlah`,
    `user`,
    `status`
  )
VALUES
  (22, '2025-01-11', 'oke', 20000, 32, 'selesai'),
  (
    23,
    '2025-01-13',
    'Kebutuhan bulanan',
    500000,
    2,
    'pending'
  ),
  (24, '2025-01-13', 'makrab', 180000, 39, 'selesai'),
  (
    25,
    '2025-01-15',
    'Jajan mie goreng',
    15000,
    41,
    'selesai'
  ),
  (26, '2025-01-16', 'okok', 9000000, 41, 'pending'),
  (
    27,
    '2025-01-20',
    'Jajan seblak',
    10000,
    49,
    'selesai'
  ),
  (
    28,
    '2025-02-17',
    'Beli perintilan rubicon',
    130000000,
    49,
    'pending'
  ),
  (
    29,
    '2025-02-27',
    'Pengeluaran untuk jajan di kantin',
    10000,
    52,
    'selesai'
  );

/*!40000 ALTER TABLE `pengeluaran` ENABLE KEYS */;

UNLOCK TABLES;

--
-- Table structure for table `piutang`
--
DROP TABLE IF EXISTS `piutang`;

/*!40101 SET @saved_cs_client     = @@character_set_client */;

/*!40101 SET character_set_client = utf8 */;

CREATE TABLE
  `piutang` (
    `id_piutang` int (11) NOT NULL AUTO_INCREMENT,
    `tanggal` date NOT NULL,
    `tanggal_jatuh_tempo` date DEFAULT NULL COMMENT 'Tanggal jatuh tempo piutang',
    `debitur` varchar(100) NOT NULL,
    `catatan` text NOT NULL,
    `jumlah` int (11) NOT NULL,
    `user` int (11) NOT NULL,
    `status` enum ('pending', 'selesai') NOT NULL,
    PRIMARY KEY (`id_piutang`)
  ) ENGINE = InnoDB AUTO_INCREMENT = 19 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `piutang`
--
LOCK TABLES `piutang` WRITE;

/*!40000 ALTER TABLE `piutang` DISABLE KEYS */;

INSERT INTO
  `piutang` (
    `id_piutang`,
    `tanggal`,
    `debitur`,
    `catatan`,
    `jumlah`,
    `user`,
    `status`
  )
VALUES
  (
    10,
    '2025-01-11',
    'adad',
    'ok',
    90000,
    32,
    'pending'
  ),
  (
    11,
    '2025-01-13',
    'bank bca',
    'award winner',
    100000,
    2,
    'pending'
  ),
  (
    12,
    '2025-01-13',
    'tangguh',
    'nasi bungkus',
    10000,
    39,
    'pending'
  ),
  (
    14,
    '2025-01-15',
    'tama',
    'oke saja',
    100000,
    40,
    'pending'
  ),
  (
    15,
    '2025-01-15',
    'oooo',
    'okkkkkkkkkkkkkk',
    800000,
    40,
    'pending'
  ),
  (
    16,
    '2025-01-16',
    'okk',
    'okgtgyu',
    800000,
    41,
    'pending'
  ),
  (
    17,
    '2025-01-31',
    'select',
    'omset',
    500000,
    50,
    'pending'
  ),
  (
    18,
    '2025-02-27',
    'Sakila',
    'biaya operasional rumah sakit',
    100000,
    52,
    'pending'
  );

/*!40000 ALTER TABLE `piutang` ENABLE KEYS */;

UNLOCK TABLES;

--
-- Table structure for table `user`
--
DROP TABLE IF EXISTS `user`;

/*!40101 SET @saved_cs_client     = @@character_set_client */;

/*!40101 SET character_set_client = utf8 */;

CREATE TABLE
  `user` (
    `id_user` int (11) NOT NULL AUTO_INCREMENT,
    `username` varchar(20) NOT NULL,
    `nama` varchar(50) NOT NULL,
    `email` varchar(50) NOT NULL,
    `password` varchar(255) NOT NULL,
    `no_telp` varchar(13) NOT NULL,
    `foto` varchar(255) NOT NULL DEFAULT 'default.png',
    `role` enum ('admin', 'user') NOT NULL DEFAULT 'user',
    `create_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `last_login_at` datetime DEFAULT NULL,
    `last_profile_update_at` datetime DEFAULT NULL,
    `is_active` enum ('0', '1') NOT NULL DEFAULT '1',
    PRIMARY KEY (`id_user`)
  ) ENGINE = InnoDB AUTO_INCREMENT = 54 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--
LOCK TABLES `user` WRITE;

/*!40000 ALTER TABLE `user` DISABLE KEYS */;

INSERT INTO
  `user` (
    `id_user`,
    `username`,
    `nama`,
    `email`,
    `password`,
    `no_telp`,
    `foto`,
    `role`,
    `create_at`,
    `is_active`
  )
VALUES
  (
    1,
    'admin',
    'Administrator',
    'admin@gmail.com',
    '$2y$10$76Aszr64wHj9Hqdf5hwj1eL6wBdaz2GYVC2NANjhzx6dmBmRjjQ8e',
    '0823893244',
    '1736232522_Js-removebg-preview.png',
    'admin',
    '2022-07-21 05:25:10',
    '1'
  ),
  (
    2,
    'Pak Ridwan',
    'Pak Ridwan Arif Cahyono',
    'Ridwan@gmail.com',
    '$2y$10$76Aszr64wHj9Hqdf5hwj1eL6wBdaz2GYVC2NANjhzx6dmBmRjjQ8e',
    '082389324',
    '1736212833_Gajah.png',
    'user',
    '2022-07-21 05:25:10',
    '1'
  ),
  (
    3,
    'Pak Sandhika',
    'Pak Sandhika Galih',
    'Sandhika@gmail.com',
    '$2y$10$76Aszr64wHj9Hqdf5hwj1eL6wBdaz2GYVC2NANjhzx6dmBmRjjQ8e',
    '082295644497',
    '1736212884_Information Technology 38.png',
    'user',
    '2022-07-21 05:25:10',
    '1'
  ),
  (
    52,
    'Kin123',
    'Kevin Ibnu Najwan',
    'kin123@gmail.com',
    '$2y$10$RiaOK08NnUOl0.ZL16EFNOGbV/yaqSt.4GWgl0SMZINMwnkIIdDKm',
    '21839487263',
    '1740621110_481a033e-bc58-472d-bb7f-374f32e671fd.jpg',
    'user',
    '2025-02-20 09:26:41',
    '1'
  ),
  (
    53,
    'tamu',
    'tamu123',
    'tamu@gmail.com',
    '$2y$10$q3yj59LqG2R6ueUmuc868e0VwD3trfOqhCY4TmhOnrh81dPPYBJsq',
    '9899090990',
    'default.png',
    'user',
    '2025-02-27 01:59:10',
    '1'
  );

/*!40000 ALTER TABLE `user` ENABLE KEYS */;

UNLOCK TABLES;

--
-- Table structure for table `wallet`
--

DROP TABLE IF EXISTS `wallet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;

CREATE TABLE `wallet` (
  `id_wallet` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nama_wallet` varchar(100) NOT NULL,
  `tipe_wallet` enum('cash','bank','e_wallet','tabungan','lainnya') NOT NULL DEFAULT 'lainnya',
  `saldo_awal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_wallet`),
  KEY `idx_wallet_user` (`user_id`),
  KEY `idx_wallet_user_active` (`user_id`, `is_active`),
  UNIQUE KEY `uniq_wallet_user_name` (`user_id`, `nama_wallet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet`
--

INSERT INTO `wallet` (
  `user_id`,
  `nama_wallet`,
  `tipe_wallet`,
  `saldo_awal`,
  `is_default`,
  `is_active`
)
SELECT
  u.`id_user`,
  'Dompet Utama',
  'cash',
  0.00,
  1,
  1
FROM `user` u
WHERE u.`role` = 'user'
  AND NOT EXISTS (
    SELECT 1
    FROM `wallet` w
    WHERE w.`user_id` = u.`id_user`
  );

UPDATE `pemasukan` p
JOIN `wallet` w
  ON w.`user_id` = p.`user`
 AND w.`is_default` = 1
SET p.`id_wallet` = w.`id_wallet`
WHERE p.`id_wallet` IS NULL;

UPDATE `pengeluaran` p
JOIN `wallet` w
  ON w.`user_id` = p.`user`
 AND w.`is_default` = 1
SET p.`id_wallet` = w.`id_wallet`
WHERE p.`id_wallet` IS NULL;

--
-- Table structure for table `transfer_wallet`
--

CREATE TABLE IF NOT EXISTS `transfer_wallet` (
  `id_transfer` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `wallet_asal_id` int(11) NOT NULL,
  `wallet_tujuan_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jumlah` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `status` enum('pending','selesai','batal') NOT NULL DEFAULT 'selesai',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_transfer`),
  KEY `idx_transfer_user` (`user_id`),
  KEY `idx_transfer_tanggal` (`tanggal`),
  KEY `idx_transfer_wallet_asal` (`wallet_asal_id`),
  KEY `idx_transfer_wallet_tujuan` (`wallet_tujuan_id`),
  KEY `idx_transfer_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Dumping events for database 'cashflow'
--
--
-- Dumping routines for database 'cashflow'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;

/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;

/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;

/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;

/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-03-01 15:37:52
