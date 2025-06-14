<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_seminar'])) {
    $bulan_tahun = $_POST['bulan_tahun'] . '-01';
    $kuota = $_POST['kuota'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM seminar WHERE bulan_tahun = ?");
        $stmt->execute([$bulan_tahun]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Seminar untuk bulan ini sudah ada!");
        }
        $pdo->prepare("INSERT INTO seminar (bulan_tahun, kuota) VALUES (?, ?)")->execute([$bulan_tahun, $kuota]);
        $pdo->commit();
        $success = "Seminar berhasil ditambahkan!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal menambah seminar: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_jadwal'])) {
    $id_seminar = $_POST['id_seminar'];
    $hari = $_POST['hari'];
    $waktu = $_POST['waktu'];
    $tempat = $_POST['tempat'];
    $id_dosen_penguji_1 = $_POST['id_dosen_penguji_1'];
    $id_dosen_penguji_2 = $_POST['id_dosen_penguji_2'];
    $id_pendaftaran = $_POST['id_pendaftaran'] ?: null;
    try {
        $pdo->beginTransaction();
        if ($id_dosen_penguji_1 == $id_dosen_penguji_2) {
            throw new Exception("Dosen Penguji 1 dan Dosen Penguji 2 tidak boleh sama!");
        }
        // Validate dosen availability
        $stmt = $pdo->prepare("SELECT status_ketersediaan FROM dosen WHERE id IN (?, ?)");
        $stmt->execute([$id_dosen_penguji_1, $id_dosen_penguji_2]);
        $dosen_status = $stmt->fetchAll();
        foreach ($dosen_status as $status) {
            if ($status['status_ketersediaan'] == 'Tidak Sedia') {
                throw new Exception("Salah satu dosen tidak tersedia!");
            }
        }
        // Validate seminar
        $stmt = $pdo->prepare("SELECT bulan_tahun, status FROM seminar WHERE id = ?");
        $stmt->execute([$id_seminar]);
        $seminar = $stmt->fetch();
        if (!$seminar || $seminar['status'] == 'Closed') {
            throw new Exception("Seminar tidak ditemukan atau sudah ditutup!");
        }
        $bulan_tahun = date('Y-m', strtotime($seminar['bulan_tahun']));
        $tahun = date('Y', strtotime($bulan_tahun));
        $bulan = date('m', strtotime($bulan_tahun));
        if (!is_numeric($hari) || $hari < 1 || $hari > 31) {
            throw new Exception("Hari tidak valid! Masukkan angka antara 1-31.");
        }
        $max_hari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
        if ($hari > $max_hari) {
            throw new Exception("Hari $hari tidak valid untuk bulan " . date('F Y', strtotime($bulan_tahun)) . " (maksimum $max_hari hari).");
        }
        $tanggal = sprintf("%s-%02d-%02d", $tahun, $bulan, $hari);
        // Validate pendaftaran
        if ($id_pendaftaran) {
            $stmt = $pdo->prepare("SELECT status FROM pendaftaran WHERE id = ?");
            $stmt->execute([$id_pendaftaran]);
            $status = $stmt->fetchColumn();
            if ($status !== 'Diterima') {
                throw new Exception("Pendaftaran tidak valid atau belum diterima!");
            }
        }
        // Insert schedule
        $pdo->prepare("INSERT INTO jadwal_seminar (id_seminar, tanggal, waktu, tempat, id_dosen_penguji_1, id_dosen_penguji_2, id_pendaftaran) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([
            $id_seminar, $tanggal, $waktu, $tempat, $id_dosen_penguji_1, $id_dosen_penguji_2, $id_pendaftaran
        ]);
        // Update dosen status
        $pdo->prepare("UPDATE dosen SET status_ketersediaan = 'Tidak Sedia' WHERE id IN (?, ?)")->execute([$id_dosen_penguji_1, $id_dosen_penguji_2]);
        $pdo->commit();
        $success = "Jadwal seminar berhasil ditambahkan!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal menambah jadwal: " . $e->getMessage();
    }
}

if (isset($_GET['approve']) || isset($_GET['reject'])) {
    $id_pendaftaran = $_GET['approve'] ?? $_GET['reject'];
    $status = isset($_GET['approve']) ? 'Diterima' : 'Ditolak';
    $alasan_penolakan = $_POST['alasan_penolakan'] ?? NULL;
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id_seminar, status FROM pendaftaran WHERE id = ?");
        $stmt->execute([$id_pendaftaran]);
        $pendaftaran = $stmt->fetch();
        if (!$pendaftaran || $pendaftaran['status'] != 'Menunggu') {
            throw new Exception("Pendaftaran tidak valid atau sudah diproses!");
        }
        // Check seminar quota and status
        $stmt = $pdo->prepare("
            SELECT s.status, s.kuota - COUNT(p.id) AS remaining_quota
            FROM seminar s
            LEFT JOIN pendaftaran p ON s.id = p.id_seminar AND p.status IN ('Diterima', 'Selesai')
            WHERE s.id = ?
            GROUP BY s.id
        ");
        $stmt->execute([$pendaftaran['id_seminar']]);
        $seminar = $stmt->fetch();
        if ($seminar['status'] == 'Closed' || $seminar['remaining_quota'] <= 0) {
            throw new Exception("Seminar sudah ditutup atau kuota penuh!");
        }
        // Update pendaftaran status
        $stmt = $pdo->prepare("SELECT status FROM pendaftaran WHERE id = ?");
        $stmt->execute([$id_pendaftaran]);
        $status_lama = $stmt->fetchColumn();
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
}

if (isset($_POST['submit_nilai'])) {
    $id_pendaftaran = $_POST['id_pendaftaran'];
    $nilai_dosen_1 = $_POST['nilai_dosen_1'];
    $nilai_dosen_2 = $_POST['nilai_dosen_2'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO hasil_seminar (id_pendaftaran, nilai_dosen_1, nilai_dosen_2) VALUES (?, ?, ?)")->execute([
            $id_pendaftaran, $nilai_dosen_1, $nilai_dosen_2
        ]);
        $pdo->commit();
        $success = "Nilai seminar berhasil disimpan!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal menyimpan nilai: " . $e->getMessage();
    }
}

if (isset($_POST['categorize_grades'])) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->query("SELECT categorize_all_grades()");
        $result = $stmt->fetchColumn();
        $pdo->commit();
        $success = $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal menjalankan kategorisasi: " . $e->getMessage();
    }
}

// Fetch seminars with terisi count
$seminars = $pdo->query("
    SELECT s.id, s.bulan_tahun, s.kuota, s.status,
           COALESCE(COUNT(p.id), 0) AS terisi
    FROM seminar s
    LEFT JOIN pendaftaran p ON s.id = p.id_seminar AND p.status IN ('Diterima', 'Selesai')
    GROUP BY s.id
    ORDER BY s.bulan_tahun
")->fetchAll();

$jadwal_seminars = $pdo->query("
    SELECT js.id, s.bulan_tahun, js.tanggal, js.waktu, js.tempat,
           d1.nama AS dosen_penguji_1, d2.nama AS dosen_penguji_2,
           u.nama AS mahasiswa, p.nama_laporan
    FROM jadwal_seminar js
    JOIN seminar s ON js.id_seminar = s.id
    LEFT JOIN dosen d1 ON js.id_dosen_penguji_1 = d1.id
    LEFT JOIN dosen d2 ON js.id_dosen_penguji_2 = d2.id
    LEFT JOIN pendaftaran p ON js.id_pendaftaran = p.id
    LEFT JOIN users u ON p.id_user = u.id
    ORDER BY s.bulan_tahun, js.tanggal
")->fetchAll();

$pending = $pdo->query("
    SELECT p.id, u.nama AS mahasiswa, p.nama_laporan, p.created_at, s.bulan_tahun, p.status, p.alasan_penolakan
    FROM pendaftaran p
    JOIN users u ON p.id_user = u.id
    JOIN seminar s ON p.id_seminar = s.id
    WHERE p.status = 'Menunggu'
    ORDER BY p.created_at DESC
")->fetchAll();

$dosen = $pdo->query("SELECT id, nama, keahlian FROM dosen WHERE status_ketersediaan = 'Sedia' ORDER BY nama")->fetchAll();

$hasil_seminars = $pdo->query("
    SELECT p.id, p.nama_laporan, hs.nilai_dosen_1, hs.nilai_dosen_2, u.nama AS mahasiswa
    FROM pendaftaran p
    JOIN users u ON p.id_user = u.id
    LEFT JOIN hasil_seminar hs ON p.id = hs.id_pendaftaran
    WHERE p.status = 'Diterima' OR p.status = 'Selesai'
    ORDER BY u.nama
")->fetchAll();

$pendaftaran_diterima = $pdo->query("
    SELECT p.id, u.nama AS mahasiswa, p.nama_laporan
    FROM pendaftaran p
    JOIN users u ON p.id_user = u.id
    WHERE p.status = 'Diterima'
    ORDER BY u.nama
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Seminar - Seminar Kerja Praktik</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex">
    <div class="w-64 bg-gray-800 text-white h-screen p-4">
        <h2 class="text-xl font-bold mb-6">Seminar KP</h2>
        <nav>
            <a href="dashboard.php" class="block py-2 px-4 hover:bg-gray-700">Dashboard</a>
            <a href="manajemen_dosen.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Dosen</a>
            <a href="manajemen_seminar.php" class="block py-2 px-4 bg-blue-500 rounded">Manajemen Seminar</a>
            <a href="../logout.php" class="block py-2 px-4 hover:bg-gray-700">Logout</a>
        </nav>
    </div>
    <div class="flex-1 p-8">
        <h1 class="text-2xl font-bold mb-6">Manajemen Seminar</h1>
        <?php if ($success): ?>
            <p class="text-green-500 mb-4"><?php echo htmlspecialchars($success); ?></p>
        <?php elseif ($error): ?>
            <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Daftar Seminar dan Jadwal</h2>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2">Bulan</th>
                        <th class="p-2">Sisa Kuota</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Jadwal</th>
                        <th class="p-2">Tempat</th>
                        <th class="p-2">Dosen Penguji</th>
                        <th class="p-2">Laporan Diuji</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seminars as $s): ?>
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT js.tanggal, js.waktu, js.tempat,
                                   d1.nama AS dosen_penguji_1, d2.nama AS dosen_penguji_2,
                                   u.nama AS mahasiswa, p.nama_laporan
                            FROM jadwal_seminar js
                            LEFT JOIN dosen d1 ON js.id_dosen_penguji_1 = d1.id
                            LEFT JOIN dosen d2 ON js.id_dosen_penguji_2 = d2.id
                            LEFT JOIN pendaftaran p ON js.id_pendaftaran = p.id
                            LEFT JOIN users u ON p.id_user = u.id
                            WHERE js.id_seminar = ?
                            ORDER BY js.tanggal
                        ");
                        $stmt->execute([$s['id']]);
                        $jadwals = $stmt->fetchAll();
                        $rowspan = max(1, count($jadwals));
                        $first = true;
                        ?>
                        <?php if (empty($jadwals)): ?>
                            <tr>
                                <td class="p-2" rowspan="<?php echo $rowspan; ?>"><?php echo date('F Y', strtotime($s['bulan_tahun'])); ?></td>
                                <td class="p-2" rowspan="<?php echo $rowspan; ?>"><?php echo $s['kuota'] - $s['terisi']; ?></td>
                                <td class="p-2" rowspan="<?php echo $rowspan; ?>"><?php echo $s['status']; ?></td>
                                <td class="p-2" colspan="4">Belum ada jadwal</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jadwals as $j): ?>
                                <tr>
                                    <?php if ($first): ?>
                                        <td class="p-2" rowspan="<?php echo $rowspan; ?>"><?php echo date('F Y', strtotime($s['bulan_tahun'])); ?></td>
                                        <td class="p-2" rowspan="<?php echo $rowspan; ?>"><?php echo $s['kuota'] - $s['terisi']; ?></td>
                                        <td class="p-2" rowspan="<?php echo $rowspan; ?>"><?php echo $s['status']; ?></td>
                                        <?php $first = false; ?>
                                    <?php endif; ?>
                                    <td class="p-2"><?php echo date('d-m-Y', strtotime($j['tanggal'])) . ' ' . $j['waktu']; ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($j['tempat'] ?? 'Belum ditentukan'); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars(($j['dosen_penguji_1'] ?? 'Belum ditentukan') . ' & ' . ($j['dosen_penguji_2'] ?? 'Belum ditentukan')); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($j['mahasiswa'] ? ($j['mahasiswa'] . ' - ' . $j['nama_laporan']) : 'Belum dipilih'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Kelola Pengajuan Seminar</h2>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2">Mahasiswa</th>
                        <th class="p-2">Nama Laporan</th>
                        <th class="p-2">Tanggal Pengajuan</th>
                        <th class="p-2">Bulan Seminar</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Alasan Penolakan</th>
                        <th class="p-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                        <tr>
                            <td class="p-2"><?php echo htmlspecialchars($p['mahasiswa']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($p['nama_laporan']); ?></td>
                            <td class="p-2"><?php echo $p['created_at']; ?></td>
                            <td class="p-2"><?php echo date('F Y', strtotime($p['bulan_tahun'])); ?></td>
                            <td class="p-2"><?php echo $p['status']; ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($p['alasan_penolakan'] ?? '-'); ?></td>
                            <td class="p-2">
                                <?php if ($p['status'] == 'Menunggu'): ?>
                                    <a href="?approve=<?php echo $p['id']; ?>" class="text-green-500">Terima</a>
                                    <form method="POST" action="?reject=<?php echo $p['id']; ?>" class="inline">
                                        <input type="text" name="alasan_penolakan" class="ml-2 px-2 py-1 border rounded-lg" placeholder="Alasan penolakan" required>
                                        <button type="submit" class="text-red-500 ml-2">Tolak</button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Input Nilai Seminar</h2>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2">Mahasiswa</th>
                        <th class="p-2">Nama Laporan</th>
                        <th class="p-2">Nilai Dosen 1</th>
                        <th class="p-2">Nilai Dosen 2</th>
                        <th class="p-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hasil_seminars as $hs): ?>
                        <tr>
                            <td class="p-2"><?php echo htmlspecialchars($hs['mahasiswa']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($hs['nama_laporan']); ?></td>
                            <td class="p-2"><?php echo $hs['nilai_dosen_1'] ?? 'Belum dinilai'; ?></td>
                            <td class="p-2"><?php echo $hs['nilai_dosen_2'] ?? 'Belum dinilai'; ?></td>
                            <td class="p-2">
                                <?php if (!$hs['nilai_dosen_1'] && !$hs['nilai_dosen_2']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="id_pendaftaran" value="<?php echo $hs['id']; ?>">
                                        <input type="number" name="nilai_dosen_1" class="px-2 py-1 border rounded-lg" placeholder="Nilai Dosen 1" min="0" max="100" required>
                                        <input type="number" name="nilai_dosen_2" class="px-2 py-1 border rounded-lg" placeholder="Nilai Dosen 2" min="0" max="100" required>
                                        <button type="submit" name="submit_nilai" class="text-blue-500 ml-2">Simpan Nilai</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Kategorisasi Nilai</h2>
            <form method="POST">
                <input type="hidden" name="categorize_grades" value="1">
                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Jalankan Kategorisasi Nilai</button>
            </form>
        </div>
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Tambah Seminar</h2>
            <form method="POST">
                <input type="hidden" name="tambah_seminar" value="1">
                <div class="mb-4">
                    <label class="block text-gray-700">Bulan dan Tahun</label>
                    <input type="month" name="bulan_tahun" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Kuota</label>
                    <input type="number" name="kuota" class="w-full px-3 py-2 border rounded-lg" value="10" min="1" required>
                </div>
                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Tambah Seminar</button>
            </form>
        </div>
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Tambah Jadwal Seminar</h2>
            <form method="POST">
                <input type="hidden" name="tambah_jadwal" value="1">
                <div class="mb-4">
                    <label class="block text-gray-700">Seminar</label>
                    <select name="id_seminar" class="w-full px-3 py-2 border rounded-lg" required>
                        <option value="">Pilih Seminar</option>
                        <?php foreach ($seminars as $s): ?>
                            <?php if ($s['status'] == 'Open'): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo date('F Y', strtotime($s['bulan_tahun'])); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Hari</label>
                    <input type="number" name="hari" class="w-full px-3 py-2 border rounded-lg" min="1" max="31" placeholder="Masukkan hari (1-31)" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Waktu</label>
                    <input type="time" name="waktu" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Tempat</label>
                    <input type="text" name="tempat" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Dosen Penguji 1</label>
                    <select name="id_dosen_penguji_1" class="w-full px-3 py-2 border rounded-lg" required>
                        <option value="">Pilih Dosen</option>
                        <?php foreach ($dosen as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['nama'] . ' - ' . $d['keahlian']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Dosen Penguji 2</label>
                    <select name="id_dosen_penguji_2" class="w-full px-3 py-2 border rounded-lg" required>
                        <option value="">Pilih Dosen</option>
                        <?php foreach ($dosen as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['nama'] . ' - ' . $d['keahlian']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Laporan yang Diuji</label>
                    <select name="id_pendaftaran" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">Pilih Laporan</option>
                        <?php foreach ($pendaftaran_diterima as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo htmlspecialchars($p['mahasiswa'] . ' - ' . $p['nama_laporan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Tambah Jadwal</button>
            </form>
        </div>
    </div>
</body>
</html>