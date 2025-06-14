
-- Create Database
CREATE DATABASE IF NOT EXISTS seminar_kp;
USE seminar_kp;

-- Disable foreign key checks temporarily for table truncation
SET FOREIGN_KEY_CHECKS = 0;

-- Table: users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('mahasiswa', 'admin') NOT NULL,
    nama VARCHAR(100) NOT NULL
);

-- Table: dosen
CREATE TABLE IF NOT EXISTS dosen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    keahlian VARCHAR(100),
    status_ketersediaan ENUM('Sedia', 'Tidak Sedia') DEFAULT 'Sedia'
);

-- Table: seminar
CREATE TABLE IF NOT EXISTS seminar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulan_tahun DATE NOT NULL,
    kuota INT NOT NULL DEFAULT 10,
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    CONSTRAINT unique_bulan_tahun UNIQUE (bulan_tahun)
);

-- Add index for optimization
CREATE INDEX idx_bulan_tahun ON seminar(bulan_tahun);

-- Table: pendaftaran
CREATE TABLE IF NOT EXISTS pendaftaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT,
    id_seminar INT,
    nama_laporan VARCHAR(255) NOT NULL,
    tanda_tangan_dosen_pembimbing VARCHAR(255),
    status ENUM('Menunggu', 'Diterima', 'Ditolak', 'Selesai') DEFAULT 'Menunggu',
    alasan_penolakan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (id_seminar) REFERENCES seminar(id) ON DELETE CASCADE
);

-- Table: jadwal_seminar
CREATE TABLE IF NOT EXISTS jadwal_seminar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_seminar INT,
    tanggal DATE,
    waktu TIME,
    tempat VARCHAR(100),
    id_dosen_penguji_1 INT,
    id_dosen_penguji_2 INT,
    id_pendaftaran INT,
    FOREIGN KEY (id_seminar) REFERENCES seminar(id) ON DELETE CASCADE,
    FOREIGN KEY (id_dosen_penguji_1) REFERENCES dosen(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_dosen_penguji_2) REFERENCES dosen(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_pendaftaran) REFERENCES pendaftaran(id) ON DELETE SET NULL,
    CHECK (id_dosen_penguji_1 != id_dosen_penguji_2)
);

-- Table: hasil_seminar
CREATE TABLE IF NOT EXISTS hasil_seminar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pendaftaran INT,
    nilai_dosen_1 INT,
    nilai_dosen_2 INT,
    status_hasil ENUM('Revisi', 'Lulus', 'Tidak Lulus') DEFAULT 'Revisi',
    FOREIGN KEY (id_pendaftaran) REFERENCES pendaftaran(id) ON DELETE CASCADE,
    UNIQUE (id_pendaftaran)
);

-- Table: nilai_akhir
CREATE TABLE IF NOT EXISTS nilai_akhir (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pendaftaran INT,
    rata_rata DECIMAL(5,2),
    kategori CHAR(1),
    status_kelulusan ENUM('Lulus', 'Tidak Lulus'),
    FOREIGN KEY (id_pendaftaran) REFERENCES pendaftaran(id) ON DELETE CASCADE,
    UNIQUE (id_pendaftaran)
);

-- Table: log_pendaftaran
CREATE TABLE IF NOT EXISTS log_pendaftaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pendaftaran INT,
    status_lama ENUM('Menunggu', 'Diterima', 'Ditolak', 'Selesai'),
    status_baru ENUM('Menunggu', 'Diterima', 'Ditolak', 'Selesai'),
    alasan_penolakan TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pendaftaran) REFERENCES pendaftaran(id) ON DELETE CASCADE
);

-- Clear tables
DELETE FROM nilai_akhir;
DELETE FROM hasil_seminar;
DELETE FROM log_pendaftaran;
DELETE FROM jadwal_seminar;
DELETE FROM pendaftaran;
DELETE FROM seminar;
DELETE FROM dosen;
DELETE FROM users;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Trigger: after_hasil_seminar_insert
DELIMITER //
DROP TRIGGER IF EXISTS after_hasil_seminar_insert//
CREATE TRIGGER after_hasil_seminar_insert
AFTER INSERT ON hasil_seminar
FOR EACH ROW
BEGIN
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
END//
DELIMITER ;

-- Stored Procedure: calculate_final_grade
DELIMITER //
DROP PROCEDURE IF EXISTS calculate_final_grade//
CREATE PROCEDURE calculate_final_grade(IN p_id_pendaftaran INT)
BEGIN
    DECLARE v_rata DECIMAL(5,2);
    DECLARE v_status ENUM('Lulus', 'Tidak Lulus');
    SELECT (nilai_dosen_1 + nilai_dosen_2) / 2 INTO v_rata
    FROM hasil_seminar
    WHERE id_pendaftaran = p_id_pendaftaran;
    SET v_status = IF(v_rata >= 65, 'Lulus', 'Tidak Lulus');
    INSERT INTO nilai_akhir (id_pendaftaran, rata_rata, status_kelulusan)
    VALUES (p_id_pendaftaran, v_rata, v_status)
    ON DUPLICATE KEY UPDATE 
        rata_rata = v_rata, 
        status_kelulusan = v_status;
END//
DELIMITER ;

-- Function: categorize_all_grades
DELIMITER //
DROP FUNCTION IF EXISTS categorize_all_grades//
CREATE FUNCTION categorize_all_grades()
RETURNS TEXT
DETERMINISTIC
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_id_pendaftaran INT;
    DECLARE v_rata DECIMAL(5,2);
    DECLARE v_kategori CHAR(1);
    DECLARE cur CURSOR FOR 
        SELECT id_pendaftaran, (nilai_dosen_1 + nilai_dosen_2) / 2 AS rata
        FROM hasil_seminar;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_id_pendaftaran, v_rata;
        IF done THEN
            LEAVE read_loop;
        END IF;
        SET v_kategori = CASE 
            WHEN v_rata >= 85 THEN 'A'
            WHEN v_rata >= 75 THEN 'B'
            WHEN v_rata >= 65 THEN 'C'
            WHEN v_rata >= 50 THEN 'D'
            ELSE 'E'
        END;
        UPDATE nilai_akhir
        SET kategori = v_kategori
        WHERE id_pendaftaran = v_id_pendaftaran;
    END LOOP;
    CLOSE cur;
    RETURN 'Kategorisasi selesai';
END//
DELIMITER ;

-- Function: categorize_grade_by_nim
DELIMITER //
DROP FUNCTION IF EXISTS categorize_grade_by_nim//
CREATE FUNCTION categorize_grade_by_nim(p_nim VARCHAR(50))
RETURNS CHAR(1)
DETERMINISTIC
BEGIN
    DECLARE v_rata DECIMAL(5,2);
    DECLARE v_kategori CHAR(1);
    SELECT (hs.nilai_dosen_1 + hs.nilai_dosen_2) / 2 INTO v_rata
    FROM hasil_seminar hs
    JOIN pendaftaran p ON hs.id_pendaftaran = p.id
    JOIN users u ON p.id_user = u.id
    WHERE u.username = p_nim
    LIMIT 1;
    SET v_kategori = CASE 
        WHEN v_rata >= 85 THEN 'A'
        WHEN v_rata >= 75 THEN 'B'
        WHEN v_rata >= 65 THEN 'C'
        WHEN v_rata >= 50 THEN 'D'
        ELSE 'E'
    END;
    UPDATE nilai_akhir
    SET kategori = v_kategori
    WHERE id_pendaftaran = (
        SELECT p.id
        FROM pendaftaran p
        JOIN users u ON p.id_user = u.id
        WHERE u.username = p_nim
        LIMIT 1
    );
    RETURN v_kategori;
END//
DELIMITER ;


-- Insert Sample Data
INSERT INTO users (username, password, role, nama)
VALUES 
    ('admin1', MD5('admin123'), 'admin', 'Admin Utama'),
    ('123456', MD5('mhs123'), 'mahasiswa', 'Budi Santoso'),
    ('123457', MD5('mhs123'), 'mahasiswa', 'Ani Wijaya');

INSERT INTO dosen (nama, keahlian, status_ketersediaan)
VALUES 
    ('Dr. Andi', 'Sistem Informasi', 'Sedia'),
    ('Prof. Budi', 'Jaringan Komputer', 'Sedia'),
    ('Dr. Citra', 'Data Science', 'Sedia'),
    ('Dr. Dewi', 'Kecerdasan Buatan', 'Sedia');

INSERT INTO seminar (bulan_tahun, kuota, status)
VALUES 
    ('2025-07-01', 10, 'Open'),
    ('2025-08-01', 10, 'Open'),
    ('2025-09-01', 10, 'Open');
