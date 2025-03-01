-- MariaDB dump 10.19  Distrib 10.6.17-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: cashflow_transaksi
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
CREATE TABLE `hutang` (
  `id_hutang` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `kreditur` varchar(100) NOT NULL,
  `catatan` text NOT NULL,
  `jumlah` int(11) NOT NULL,
  `user` varchar(30) NOT NULL,
  `status` enum('pending','selesai') NOT NULL,
  PRIMARY KEY (`id_hutang`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hutang`
--

LOCK TABLES `hutang` WRITE;
/*!40000 ALTER TABLE `hutang` DISABLE KEYS */;
INSERT INTO `hutang` VALUES (8,'2025-01-11','fadlan','ok',90000,'32','pending'),(9,'2025-01-13','Bank Ganesha','Peminjaman untuk dana darurat\r\n',10000,'2','pending'),(10,'2025-01-13','ferdi','mixue',10000,'39','pending'),(13,'2025-01-15','myself','oke',15000,'40','pending'),(14,'2025-01-16','ok','tst',6555500,'41','pending'),(15,'2025-02-27','Sakila','peminjaman untuk beli kebutuhan di indomaret\r\n',50000,'52','pending');
/*!40000 ALTER TABLE `hutang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pemasukan`
--

DROP TABLE IF EXISTS `pemasukan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pemasukan` (
  `id_pemasukan` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `catatan` text NOT NULL,
  `jumlah` int(11) NOT NULL,
  `user` varchar(30) NOT NULL,
  `status` enum('pending','selesai') NOT NULL,
  PRIMARY KEY (`id_pemasukan`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pemasukan`
--

LOCK TABLES `pemasukan` WRITE;
/*!40000 ALTER TABLE `pemasukan` DISABLE KEYS */;
INSERT INTO `pemasukan` VALUES (11,'2025-01-15','gaji bulanan',1000000,'28','selesai'),(15,'2025-01-07','Restock gaji',200000,'27','selesai'),(17,'2025-01-09','test',100000,'28','pending'),(22,'2025-01-09','fsfs',121212,'30','selesai'),(28,'2025-01-11','ewfew',20000,'32','selesai'),(29,'2025-01-13','Uang kas',10000,'2','pending'),(31,'2025-01-13','oke',100000,'2','selesai'),(32,'2025-01-13','uang saku',500000,'39','selesai'),(33,'2024-11-14','jersey',150000,'39','selesai'),(34,'2025-01-13','uang kas',50000,'39','selesai'),(36,'2025-01-15','testing',400000,'40','pending'),(37,'2025-01-15','Gaji freelance',150000,'41','selesai'),(38,'2025-01-16','Nemu dikantong',1000000,'43','selesai'),(39,'2025-01-07','apani',1000000,'41','selesai'),(40,'2025-01-27','Gajian sampingan',50000000,'49','pending'),(41,'2025-01-25','duit',1000000,'50','selesai'),(42,'2025-01-29','duit',1000000,'51','selesai'),(43,'2025-02-27','Pemasukan hari ini uang jajan',20000,'52','selesai');
/*!40000 ALTER TABLE `pemasukan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengeluaran`
--

DROP TABLE IF EXISTS `pengeluaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengeluaran` (
  `id_pengeluaran` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `catatan` text NOT NULL,
  `jumlah` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `status` enum('pending','selesai') NOT NULL,
  PRIMARY KEY (`id_pengeluaran`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengeluaran`
--

LOCK TABLES `pengeluaran` WRITE;
/*!40000 ALTER TABLE `pengeluaran` DISABLE KEYS */;
INSERT INTO `pengeluaran` VALUES (22,'2025-01-11','oke',20000,32,'selesai'),(23,'2025-01-13','Kebutuhan bulanan',500000,2,'pending'),(24,'2025-01-13','makrab',180000,39,'selesai'),(25,'2025-01-15','Jajan mie goreng',15000,41,'selesai'),(26,'2025-01-16','okok',9000000,41,'pending'),(27,'2025-01-20','Jajan seblak',10000,49,'selesai'),(28,'2025-02-17','Beli perintilan rubicon',130000000,49,'pending'),(29,'2025-02-27','Pengeluaran untuk jajan di kantin',10000,52,'selesai');
/*!40000 ALTER TABLE `pengeluaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `piutang`
--

DROP TABLE IF EXISTS `piutang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `piutang` (
  `id_piutang` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `debitur` varchar(100) NOT NULL,
  `catatan` text NOT NULL,
  `jumlah` int(11) NOT NULL,
  `user` varchar(30) NOT NULL,
  `status` enum('pending','selesai') NOT NULL,
  PRIMARY KEY (`id_piutang`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `piutang`
--

LOCK TABLES `piutang` WRITE;
/*!40000 ALTER TABLE `piutang` DISABLE KEYS */;
INSERT INTO `piutang` VALUES (10,'2025-01-11','adad','ok',90000,'32','pending'),(11,'2025-01-13','bank bca','award winner',100000,'2','pending'),(12,'2025-01-13','tangguh','nasi bungkus',10000,'39','pending'),(14,'2025-01-15','tama','oke saja',100000,'40','pending'),(15,'2025-01-15','oooo','okkkkkkkkkkkkkk',800000,'40','pending'),(16,'2025-01-16','okk','okgtgyu',800000,'41','pending'),(17,'2025-01-31','select','omset',500000,'50','pending'),(18,'2025-02-27','Sakila','biaya operasional rumah sakit',100000,'52','pending');
/*!40000 ALTER TABLE `piutang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(20) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `no_telp` varchar(13) NOT NULL,
  `foto` varchar(255) NOT NULL DEFAULT 'default.png',
  `role` enum('admin','mahasiswa','dosen') NOT NULL,
  `create_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` enum('1') NOT NULL,
  PRIMARY KEY (`id_user`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'admin','Administrator','admin@gmail.com','12345','0823893244','1736232522_Js-removebg-preview.png','admin','2022-07-21 05:25:10','1'),(2,'Pak Ridwan','Pak Ridwan Arif Cahyono','Ridwan@gmail.com','12345','082389324','1736212833_Gajah.png','dosen','2022-07-21 05:25:10','1'),(3,'Pak Sandhika','Pak Sandhika Galih','Sandhika@gmail.com','12345','082295644497','1736212884_Information Technology 38.png','dosen','2022-07-21 05:25:10','1'),(52,'Kin123','Kevin Ibnu Najwan','kin123@gmail.com','123321','21839487263','1740621110_481a033e-bc58-472d-bb7f-374f32e671fd.jpg','dosen','2025-02-20 09:26:41','1'),(53,'tamu','tamu123','tamu@gmail.com','123','9899090990','default.png','dosen','2025-02-27 01:59:10','1');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'cashflow_transaksi'
--

--
-- Dumping routines for database 'cashflow_transaksi'
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
