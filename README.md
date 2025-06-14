**SIPESMA**

Proyek ini merupakan Sistem Informasi Pendaftaran dan Kelulusan Seminar Mahasiswa dikhususkan untuk seminar KP. Proyek ini dibangun dengan menggunakan PHP dan MySQL. Tujuan proyek ini adalah mmembantu mahasiswa dan admin mempermudah proses pendaftaran dan kelulusan pada seminar kp dengan memanfaatkan stored procedure, trigger, transaction, dan stored function. Sistem ini juga dilengkapi mekanisme backup otomatis untuk menjaga keamanan data jika terjadi hal yang tidak diinginkan.

# 🐾 SIPESMA (Sistem Pendaftaran Seminar)

Proyek ini adalah sistem pendaftaran seminar kerja praktik yang dibangun menggunakan PHP dan MySQL. Tujuannya adalah untuk mengelola pendaftaran seminar mahasiswa, penjadwalan, penilaian, dan manajemen dosen penguji secara efisien dan terstruktur. Sistem ini memanfaatkan **stored procedure**, **trigger**, **transaction**, dan **stored function** untuk menjamin konsistensi data, keamanan proses, dan otomatisasi tugas-tugas penting. Fitur seperti validasi otomatis dan log perubahan memastikan integritas data dalam lingkungan multi-user.

**[Home](https://github.com/DYmazeh/Kelompok11_PDT_SIPESMA)**

## 📌 Detail Konsep

### ⚠️ Disclaimer
Peran **stored procedure**, **trigger**, **transaction**, dan **stored function** dalam SIPESMA dirancang khusus untuk kebutuhan sistem pendaftaran seminar. Implementasinya dapat bervariasi pada sistem lain, tergantung pada arsitektur dan kebutuhan spesifik.

---

## 🧠 Stored Procedure
**Stored procedure** berfungsi sebagai alur kerja standar (SOP) yang dieksekusi di lapisan database. Procedure ini memastikan konsistensi dan efisiensi dalam operasi seperti penambahan dosen, perhitungan nilai akhir, dan pengelolaan data seminar. Dengan menyimpan logika di database, sistem ini mendukung keandalan dalam lingkungan terdistribusi atau multi-user.

### Procedure
Beberapa **stored procedure** penting yang digunakan:

- **add_dosen(p_nama, p_keahlian, p_status)**: Menambahkan data dosen baru ke tabel `dosen`.
  ```php
  // File: pages/manajemen_dosen.php
  $pdo->prepare("CALL add_dosen(?, ?, ?)")->execute([$nama, $keahlian, $status]);


calculate_final_grade(p_id_pendaftaran): Menghitung rata-rata nilai dari dua dosen penguji dan menentukan status kelulusan.-- Called in after_hasil_seminar_insert trigger
CALL calculate_final_grade(NEW.id_pendaftaran);



Dengan menyimpan logika ini di database, SIPESMA menjaga integritas data terlepas dari cara aplikasi mengaksesnya, meminimalkan risiko kesalahan dari sisi aplikasi.

🚨 Trigger
Trigger bertindak sebagai mekanisme pengaman otomatis yang dijalankan sebelum atau sesudah operasi tertentu pada tabel. Trigger memastikan data tetap valid dan konsisten, seperti memvalidasi status pendaftaran atau memperbarui status dosen dan seminar secara otomatis.
Trigger
Trigger utama dalam sistem ini:

after_hasil_seminar_insert: Diaktifkan setelah nilai seminar dimasukkan ke tabel hasil_seminar. Trigger ini:
Mengubah status pendaftaran menjadi Selesai.
Memanggil calculate_final_grade untuk menghitung nilai akhir.
Memperbarui status ketersediaan dosen menjadi Sedia jika tidak ada tugas lain.
Menutup seminar (status Closed) jika tidak ada pendaftaran Menunggu atau Diterima.

-- File: seminar_kp.sql
SELECT js.id_dosen_penguji_1, js.id_dosen_penguji_2, p.id_seminar INTO v_dosen_penguji_1, v_dosen_penguji_2, v_seminar_id
FROM jadwal_seminar js
JOIN pendaftaran p ON js.id_pendaftaran = p.id
WHERE p.id = NEW.id_pendaftaran
LIMIT 1;

UPDATE pendaftaran
SET status = 'Selesai'
WHERE id = NEW.id_pendaftaran;

CALL calculate_final_grade(NEW.id_pendaftaran);



Peran Trigger:

Memastikan pendaftaran otomatis berstatus Selesai setelah penilaian.
Mencegah seminar tetap terbuka setelah semua pendaftaran selesai.
Mengelola status dosen untuk mencegah penugasan ganda.
Menjaga konsistensi data dengan validasi otomatis di database.

Trigger ini memastikan reliabilitas sistem, bahkan jika ada kesalahan atau kelalaian dari sisi aplikasi.

🔄 Transaction (Transaksi)
Transaction digunakan untuk memastikan bahwa serangkaian operasi database (misalnya, menambah jadwal seminar atau menerima pendaftaran) dijalankan secara atomik — semua berhasil atau semua dibatalkan. Ini mencegah perubahan data yang tidak lengkap, seperti pendaftaran yang diterima tetapi kuota seminar tidak diperbarui.
Contoh Implementasi

Menambah Jadwal Seminar:// File: pages/manajemen_seminar.php
try {
    $pdo->beginTransaction();
    // Validasi dosen dan seminar
    $pdo->prepare("INSERT INTO jadwal_seminar (id_seminar, tanggal, waktu, tempat, id_dosen_penguji_1, id_dosen_penguji_2, id_pendaftaran) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([
        $id_seminar, $tanggal, $waktu, $tempat, $id_dosen_penguji_1, $id_dosen_penguji_2, $id_pendaftaran
    ]);
    $pdo->prepare("UPDATE dosen SET status_ketersediaan = 'Tidak Sedia' WHERE id IN (?, ?)")->execute([$id_dosen_penguji_1, $id_dosen_penguji_2]);
    $pdo->commit();
    $success = "Jadwal seminar berhasil ditambahkan!";
} catch (Exception $e) {
    $pdo->rollBack();
    $error = "Gagal menambah jadwal: " . $e->getMessage();
}


Menerima Pendaftaran:// File: pages/manajemen_seminar.php
try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE pendaftaran SET status = ?, alasan_penolakan = ? WHERE id = ?")->execute([$status, $alasan_penolakan, $id_pendaftaran]);
    $pdo->prepare("INSERT INTO log_pendaftaran (id_pendaftaran, status_lama, status_baru, alasan_penolakan) VALUES (?, ?, ?, ?)")->execute([
        $id_pendaftaran, $status_lama, $status, $alasan_penolakan
    ]);
    $pdo->commit();
    $success = "Pengajuan berhasil diperbarui!";
} catch (Exception $e) {
    $pdo->rollBack();
    $error = "Gagal memperbarui pengajuan: " . $e->getMessage();
}



Manfaat Transaction:

Menjamin bahwa penjadwalan seminar dan pembaruan status dosen terjadi bersamaan.
Mencegah pendaftaran diterima jika kuota seminar sudah penuh.
Memastikan log perubahan selalu tercatat bersama perubahan status.


📺 Stored Function
Stored function digunakan untuk menghitung atau mengambil informasi tanpa mengubah data. Fungsi ini memusatkan logika perhitungan di database, memastikan konsistensi hasil di seluruh aplikasi.
Function
Fungsi utama dalam sistem:

categorize_grade_by_nim(p_nim): Menghitung kategori nilai (A, B, C, D, E) berdasarkan rata-rata nilai dosen untuk mahasiswa dengan NIM tertentu.-- File: seminar_kp.sql
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


categorize_all_grades(): Mengkategorikan nilai semua pendaftaran seminar.// File: pages/manajemen_seminar.php
$stmt = $pdo->query("SELECT categorize_all_grades()");
$result = $stmt->fetchColumn();



Manfaat Stored Function:

Memusatkan logika perhitungan kategori nilai di database.
Mengurangi duplikasi kode di aplikasi.
Memastikan konsistensi hasil kategori nilai di semua bagian sistem.


🔄 Backup Otomatis
SIPESMA dilengkapi dengan mekanisme backup otomatis menggunakan mysqldump dan task scheduler untuk menjaga keamanan data. Backup dilakukan secara berkala dan disimpan dengan nama file yang mencakup timestamp di direktori storage/backups.
Contoh Implementasi
// File: backup.php
<?php
require_once __DIR__ . '/config/db.php';

$date = date('Y-m-d_H-i-s');
$backupFile = __DIR__ . "/storage/backups/sipesma_backup_$date.sql";
$command = "\"C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe\" -u " . DB_USER . " -p" . DB_PASS . " " . DB_NAME . " > \"$backupFile\"";
exec($command);

Catatan:

Pastikan direktori storage/backups ada dan memiliki izin tulis:mkdir -p storage/backups
chmod -R u+w storage/backups


Sesuaikan path mysqldump sesuai lingkungan server Anda.


🧩 Relevansi Proyek dengan Pemrosesan Data Terdistribusi
SIPESMA dirancang dengan prinsip-prinsip pemrosesan data terdistribusi untuk mendukung keandalan dan skalabilitas:

Konsistensi: Stored procedure dan trigger memastikan semua operasi (pendaftaran, penjadwalan, penilaian) dijalankan dengan aturan terpusat di database.
Reliabilitas: Transaction menjamin bahwa operasi seperti penambahan jadwal atau penerimaan pendaftaran bersifat atomik, mencegah data tidak konsisten.
Integritas: Logika di database (via procedure dan function) memungkinkan sistem tetap valid meskipun diakses dari berbagai sumber (web, API, atau aplikasi lain).
Keamanan Data: Backup otomatis melindungi data dari kehilangan akibat kegagalan sistem.


🛠️ Cara Menjalankan Proyek

Clone Repository:
git clone https://github.com/DYmazeh/Kelompok11_PDT_SIPESMA.git
cd Kelompok11_PDT_SIPESMA


Konfigurasi Database:

Buat database MySQL:CREATE DATABASE seminar_kp;


Import skema database:mysql -u root -p seminar_kp < seminar_kp.sql


Sesuaikan konfigurasi di config/db.php:define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'seminar_kp');




Setup Server:

Gunakan server seperti Laragon, XAMPP, atau WAMP.
Tempatkan proyek di direktori web server (e.g., htdocs).
Pastikan direktori uploads/tanda_tangan ada:mkdir -p uploads/tanda_tangan
chmod -R u+w uploads/tanda_tangan




Jalankan Aplikasi:

Akses melalui browser: http://localhost/Kelompok11_PDT_SIPESMA
Login sebagai admin: Username: admin1, Password: admin123
Login sebagai mahasiswa: Username: 123456, Password: mhs123


Konfigurasi Backup:

Tambahkan backup.php ke task scheduler (e.g., cron di Linux atau Task Scheduler di Windows).
Contoh cron (Linux):0 0 * * * php /path/to/Kelompok11_PDT_SIPESMA/backup.php






📂 Struktur Direktori
Kelompok11_PDT_SIPESMA/
├── config/
│   └── db.php              # Konfigurasi koneksi database
├── pages/
│   ├── dashboard.php       # Halaman dashboard admin dan mahasiswa
│   ├── manajemen_dosen.php # Manajemen data dosen
│   ├── manajemen_seminar.php # Manajemen seminar dan pendaftaran
│   ├── pendaftaran.php     # Form pendaftaran seminar mahasiswa
│   └── hasil_seminar.php   # Tampilan hasil seminar
├── uploads/
│   └── tanda_tangan/       # Direktori unggahan tanda tangan
├── storage/
│   └── backups/            # Direktori file backup
├── backup.php              # Skrip backup otomatis
├── seminar_kp.sql          # Skema database
└── README.md               # Dokumentasi proyek


👥 Kontributor

DYmazeh - Koordinator Proyek
Kelompok 11 PDT - Pengembang Sistem


📜 Lisensi
Proyek ini dilisensikan di bawah MIT License.

SIPESMA adalah contoh implementasi sistem berbasis database dengan prinsip pemrosesan data terdistribusi. Kami berharap proyek ini dapat menjadi referensi bagi pengembangan sistem serupa. Kontribusi dan saran selalu diterima di repository ini!


