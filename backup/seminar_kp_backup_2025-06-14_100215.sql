-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: seminar_kp
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `dosen`
--

DROP TABLE IF EXISTS `dosen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dosen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `keahlian` varchar(100) DEFAULT NULL,
  `status_ketersediaan` enum('Sedia','Tidak Sedia') DEFAULT 'Sedia',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dosen`
--

LOCK TABLES `dosen` WRITE;
/*!40000 ALTER TABLE `dosen` DISABLE KEYS */;
INSERT INTO `dosen` VALUES (1,'Dr. Andi','Sistem Informasi','Sedia'),(2,'Prof. Budi','Jaringan Komputer','Sedia'),(3,'Dr. Citra','Data Science','Sedia'),(4,'Dr. Dewi','Kecerdasan Buatan','Sedia');
/*!40000 ALTER TABLE `dosen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hasil_seminar`
--

DROP TABLE IF EXISTS `hasil_seminar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_seminar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pendaftaran` int DEFAULT NULL,
  `nilai_dosen_1` int DEFAULT NULL,
  `nilai_dosen_2` int DEFAULT NULL,
  `status_hasil` enum('Revisi','Lulus','Tidak Lulus') DEFAULT 'Revisi',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_pendaftaran` (`id_pendaftaran`),
  CONSTRAINT `hasil_seminar_ibfk_1` FOREIGN KEY (`id_pendaftaran`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hasil_seminar`
--

LOCK TABLES `hasil_seminar` WRITE;
/*!40000 ALTER TABLE `hasil_seminar` DISABLE KEYS */;
/*!40000 ALTER TABLE `hasil_seminar` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_hasil_seminar_insert` AFTER INSERT ON `hasil_seminar` FOR EACH ROW BEGIN
    DECLARE v_dosen_penguji_1 INT;
    DECLARE v_dosen_penguji_2 INT;
    DECLARE v_seminar_id INT;
    DECLARE v_pending_registrations INT;
    DECLARE v_jadwal_exists INT;

    -- Check if jadwal_seminar record exists
    SELECT COUNT(*) INTO v_jadwal_exists
    FROM jadwal_seminar js
    WHERE js.id_pendaftaran = NEW.id_pendaftaran;

    IF v_jadwal_exists > 0 THEN
        -- Get dosen penguji IDs and seminar ID
        SELECT js.id_dosen_penguji_1, js.id_dosen_penguji_2, p.id_seminar INTO v_dosen_penguji_1, v_dosen_penguji_2, v_seminar_id
        FROM jadwal_seminar js
        JOIN pendaftaran p ON js.id_pendaftaran = p.id
        WHERE p.id = NEW.id_pendaftaran
        LIMIT 1;

        -- Update dosen availability if grades are provided
        IF NEW.nilai_dosen_1 IS NOT NULL AND NEW.nilai_dosen_2 IS NOT NULL THEN
            UPDATE dosen d
            SET status_ketersediaan = 'Sedia'
            WHERE id IN (v_dosen_penguji_1, v_dosen_penguji_2)
            AND NOT EXISTS (
                SELECT 1
                FROM jadwal_seminar js2
                JOIN pendaftaran p2 ON js2.id_pendaftaran = p2.id
                WHERE (js2.id_dosen_penguji_1 = d.id OR js2.id_dosen_penguji_2 = d.id)
                AND p2.status IN ('Menunggu', 'Diterima')
                AND p2.id != NEW.id_pendaftaran
            );
        END IF;
    ELSE
        -- Get seminar ID from pendaftaran if no jadwal
        SELECT id_seminar INTO v_seminar_id
        FROM pendaftaran
        WHERE id = NEW.id_pendaftaran
        LIMIT 1;
    END IF;

    -- Update pendaftaran status to 'Selesai'
    UPDATE pendaftaran
    SET status = 'Selesai'
    WHERE id = NEW.id_pendaftaran;

    -- Call stored procedure to calculate final grade
    CALL calculate_final_grade(NEW.id_pendaftaran);

    -- Check if seminar should be closed
    SELECT COUNT(*) INTO v_pending_registrations
    FROM pendaftaran
    WHERE id_seminar = v_seminar_id AND status IN ('Menunggu', 'Diterima');

    IF v_pending_registrations = 0 THEN
        UPDATE seminar
        SET status = 'Closed'
        WHERE id = v_seminar_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `jadwal_seminar`
--

DROP TABLE IF EXISTS `jadwal_seminar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jadwal_seminar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_seminar` int DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `waktu` time DEFAULT NULL,
  `tempat` varchar(100) DEFAULT NULL,
  `id_dosen_penguji_1` int DEFAULT NULL,
  `id_dosen_penguji_2` int DEFAULT NULL,
  `id_pendaftaran` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_seminar` (`id_seminar`),
  KEY `id_dosen_penguji_1` (`id_dosen_penguji_1`),
  KEY `id_dosen_penguji_2` (`id_dosen_penguji_2`),
  KEY `id_pendaftaran` (`id_pendaftaran`),
  CONSTRAINT `jadwal_seminar_ibfk_1` FOREIGN KEY (`id_seminar`) REFERENCES `seminar` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_seminar_ibfk_2` FOREIGN KEY (`id_dosen_penguji_1`) REFERENCES `dosen` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `jadwal_seminar_ibfk_3` FOREIGN KEY (`id_dosen_penguji_2`) REFERENCES `dosen` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `jadwal_seminar_ibfk_4` FOREIGN KEY (`id_pendaftaran`) REFERENCES `pendaftaran` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jadwal_seminar_chk_1` CHECK ((`id_dosen_penguji_1` <> `id_dosen_penguji_2`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jadwal_seminar`
--

LOCK TABLES `jadwal_seminar` WRITE;
/*!40000 ALTER TABLE `jadwal_seminar` DISABLE KEYS */;
/*!40000 ALTER TABLE `jadwal_seminar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_pendaftaran`
--

DROP TABLE IF EXISTS `log_pendaftaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_pendaftaran` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pendaftaran` int DEFAULT NULL,
  `status_lama` enum('Menunggu','Diterima','Ditolak','Selesai') DEFAULT NULL,
  `status_baru` enum('Menunggu','Diterima','Ditolak','Selesai') DEFAULT NULL,
  `alasan_penolakan` text,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_pendaftaran` (`id_pendaftaran`),
  CONSTRAINT `log_pendaftaran_ibfk_1` FOREIGN KEY (`id_pendaftaran`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_pendaftaran`
--

LOCK TABLES `log_pendaftaran` WRITE;
/*!40000 ALTER TABLE `log_pendaftaran` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_pendaftaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nilai_akhir`
--

DROP TABLE IF EXISTS `nilai_akhir`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nilai_akhir` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pendaftaran` int DEFAULT NULL,
  `rata_rata` decimal(5,2) DEFAULT NULL,
  `kategori` char(1) DEFAULT NULL,
  `status_kelulusan` enum('Lulus','Tidak Lulus') DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_pendaftaran` (`id_pendaftaran`),
  CONSTRAINT `nilai_akhir_ibfk_1` FOREIGN KEY (`id_pendaftaran`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nilai_akhir`
--

LOCK TABLES `nilai_akhir` WRITE;
/*!40000 ALTER TABLE `nilai_akhir` DISABLE KEYS */;
/*!40000 ALTER TABLE `nilai_akhir` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pendaftaran`
--

DROP TABLE IF EXISTS `pendaftaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pendaftaran` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_user` int DEFAULT NULL,
  `id_seminar` int DEFAULT NULL,
  `nama_laporan` varchar(255) NOT NULL,
  `tanda_tangan_dosen_pembimbing` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu','Diterima','Ditolak','Selesai') DEFAULT 'Menunggu',
  `alasan_penolakan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_user` (`id_user`),
  KEY `id_seminar` (`id_seminar`),
  CONSTRAINT `pendaftaran_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pendaftaran_ibfk_2` FOREIGN KEY (`id_seminar`) REFERENCES `seminar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pendaftaran`
--

LOCK TABLES `pendaftaran` WRITE;
/*!40000 ALTER TABLE `pendaftaran` DISABLE KEYS */;
/*!40000 ALTER TABLE `pendaftaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seminar`
--

DROP TABLE IF EXISTS `seminar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seminar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bulan_tahun` date NOT NULL,
  `kuota` int NOT NULL DEFAULT '10',
  `status` enum('Open','Closed') DEFAULT 'Open',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bulan_tahun` (`bulan_tahun`),
  KEY `idx_bulan_tahun` (`bulan_tahun`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seminar`
--

LOCK TABLES `seminar` WRITE;
/*!40000 ALTER TABLE `seminar` DISABLE KEYS */;
INSERT INTO `seminar` VALUES (1,'2025-07-01',10,'Open'),(2,'2025-08-01',10,'Open'),(3,'2025-09-01',10,'Open');
/*!40000 ALTER TABLE `seminar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('mahasiswa','admin') NOT NULL,
  `nama` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin1','0192023a7bbd73250516f069df18b500','admin','Admin Utama'),(2,'123456','39f55dd65ead9c938fa93a765983bff0','mahasiswa','Budi Santoso'),(3,'123457','39f55dd65ead9c938fa93a765983bff0','mahasiswa','Ani Wijaya');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-14 10:02:15
