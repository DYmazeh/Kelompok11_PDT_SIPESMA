<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

if ($role == 'mahasiswa') {
    $stmt = $pdo->prepare("
        SELECT p.id, p.status, p.nama_laporan, p.tanda_tangan_dosen_pembimbing, p.alasan_penolakan,
               js.tanggal, js.waktu, js.tempat, d1.nama AS dosen_penguji_1, d2.nama AS dosen_penguji_2,
               na.rata_rata, na.kategori, na.status_kelulusan, s.bulan_tahun
        FROM pendaftaran p
        JOIN seminar s ON p.id_seminar = s.id
        LEFT JOIN jadwal_seminar js ON js.id_pendaftaran = p.id
        LEFT JOIN dosen d1 ON js.id_dosen_penguji_1 = d1.id
        LEFT JOIN dosen d2 ON js.id_dosen_penguji_2 = d2.id
        LEFT JOIN nilai_akhir na ON p.id = na.id_pendaftaran
        WHERE p.id_user = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pendaftaran = $stmt->fetchAll();
} elseif ($role == 'admin') {
    $pending = $pdo->query("
        SELECT p.id, u.nama AS mahasiswa, p.nama_laporan, p.created_at, s.bulan_tahun
        FROM pendaftaran p
        JOIN users u ON p.id_user = u.id
        JOIN seminar s ON p.id_seminar = s.id
        WHERE p.status = 'Menunggu'
        ORDER BY p.created_at
    ")->fetchAll();
    $seminars = $pdo->query("
        SELECT s.id, s.bulan_tahun, s.kuota,
               COUNT(p.id) AS terisi
        FROM seminar s
        LEFT JOIN pendaftaran p ON s.id = p.id_seminar AND p.status = 'Diterima'
        GROUP BY s.id
        ORDER BY s.bulan_tahun
    ")->fetchAll();
    $dosen = $pdo->query("
        SELECT id, nama, keahlian, status_ketersediaan
        FROM dosen
        ORDER BY nama
    ")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Seminar Kerja Praktik</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex">
    <div class="w-64 bg-gray-800 text-white h-screen p-4">
        <h2 class="text-xl font-bold mb-6">Seminar KP</h2>
        <nav>
            <a href="dashboard.php" class="block py-2 px-4 bg-blue-500 rounded">Dashboard</a>
            <?php if ($role == 'mahasiswa'): ?>
                <a href="pendaftaran.php" class="block py-2 px-4 hover:bg-gray-700">Pendaftaran Seminar</a>
                <a href="hasil_seminar.php" class="block py-2 px-4 hover:bg-gray-700">Hasil Seminar</a>
            <?php else: ?>
                <a href="manajemen_dosen.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Dosen</a>
                <a href="manajemen_seminar.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Seminar</a>
            <?php endif; ?>
            <a href="../logout.php" class="block py-2 px-4 hover:bg-gray-700">Logout</a>
        </nav>
    </div>
    <div class="flex-1 p-8">
        <h1 class="text-2xl font-bold mb-6">Selamat Datang, <?php echo htmlspecialchars($nama); ?></h1>
        <?php if ($role == 'mahasiswa'): ?>
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-4">Status Pendaftaran Seminar</h2>
                <?php if (empty($pendaftaran)): ?>
                    <p class="text-gray-700">Anda belum mendaftar.</p>
                <?php else: ?>
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-200">
                                <th class="p-2">Nama Laporan</th>
                                <th class="p-2">Status</th>
                                <th class="p-2">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendaftaran as $p): ?>
                                <tr>
                                    <td class="p-2"><?php echo htmlspecialchars($p['nama_laporan']); ?></td>
                                    <td class="p-2"><?php echo $p['status']; ?></td>
                                    <td class="p-2">
                                        <?php if ($p['status'] == 'Menunggu'): ?>
                                            Menunggu keputusan admin.
                                        <?php elseif ($p['status'] == 'Diterima'): ?>
                                            <div>
                                                <p><strong>Bulan:</strong> <?php echo date('F Y', strtotime($p['bulan_tahun'])); ?></p>
                                                <p><strong>Tanggal:</strong> <?php echo $p['tanggal'] ?? 'Belum ditentukan'; ?></p>
                                                <p><strong>Waktu:</strong> <?php echo $p['waktu'] ?? 'Belum ditentukan'; ?></p>
                                                <p><strong>Tempat:</strong> <?php echo htmlspecialchars($p['tempat'] ?? 'Belum ditentukan'); ?></p>
                                                <p><strong>Dosen Penguji 1:</strong> <?php echo htmlspecialchars($p['dosen_penguji_1'] ?? 'Belum ditentukan'); ?></p>
                                                <p><strong>Dosen Penguji 2:</strong> <?php echo htmlspecialchars($p['dosen_penguji_2'] ?? 'Belum ditentukan'); ?></p>
                                                <p><strong>Tanda Tangan Dosen Pembimbing:</strong> <?php echo htmlspecialchars($p['tanda_tangan_dosen_pembimbing'] ?? 'Belum diunggah'); ?></p>
                                            </div>
                                        <?php elseif ($p['status'] == 'Ditolak'): ?>
                                            <p><strong>Alasan Penolakan:</strong> <?php echo htmlspecialchars($p['alasan_penolakan'] ?? 'Tidak ada alasan'); ?></p>
                                        <?php elseif ($p['status'] == 'Selesai'): ?>
                                            <div>
                                                <p><strong>Rata-rata Nilai:</strong> <?php echo $p['rata_rata'] ?? 'Belum dinilai'; ?></p>
                                                <p><strong>Kategori Nilai:</strong> <?php echo $p['kategori'] ?? 'Belum dikategorikan'; ?></p>
                                                <p><strong>Status Kelulusan:</strong> <?php echo $p['status_kelulusan'] ?? 'Belum ditentukan'; ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <h2 class="text-xl font-semibold mb-4">Pengajuan Seminar Pending</h2>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="p-2">Mahasiswa</th>
                            <th class="p-2">Nama Laporan</th>
                            <th class="p-2">Tanggal Pengajuan</th>
                            <th class="p-2">Bulan Seminar</th>
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
                                <td class="p-2">
                                    <a href="manajemen_seminar.php?approve=<?php echo $p['id']; ?>" class="text-green-500">Terima</a>
                                    <a href="manajemen_seminar.php?reject=<?php echo $p['id']; ?>" class="text-red-500 ml-2">Tolak</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <h2 class="text-xl font-semibold mb-4">Daftar Seminar</h2>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="p-2">Bulan</th>
                            <th class="p-2">Kuota Tersisa</th>
                            <th class="p-2">Jadwal Tersedia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seminars as $s): ?>
                            <tr>
                                <td class="p-2"><?php echo date('F Y', strtotime($s['bulan_tahun'])); ?></td>
                                <td class="p-2"><?php echo $s['kuota'] - $s['terisi']; ?></td>
                                <td class="p-2">
                                    <?php
                                    $stmt = $pdo->prepare("
                                        SELECT js.tanggal, js.waktu, js.tempat,
                                               d1.nama AS dosen_1, d2.nama AS dosen_2
                                        FROM jadwal_seminar js
                                        LEFT JOIN dosen d1 ON js.id_dosen_penguji_1 = d1.id
                                        LEFT JOIN dosen d2 ON js.id_dosen_penguji_2 = d2.id
                                        WHERE js.id_seminar = ?
                                        ORDER BY js.tanggal
                                    ");
                                    $stmt->execute([$s['id']]);
                                    $jadwals = $stmt->fetchAll();
                                    if (empty($jadwals)) {
                                        echo 'Belum ada jadwal';
                                    } else {
                                        echo '<ul>';
                                        foreach ($jadwals as $j) {
                                            echo '<li>' . date('d-m-Y', strtotime($j['tanggal'])) . ' ' . $j['waktu'] . ' (' . htmlspecialchars($j['tempat']) . ', Dosen: ' . htmlspecialchars($j['dosen_1'] . ' & ' . $j['dosen_2']) . ')</li>';
                                        }
                                        echo '</ul>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-4">Daftar Dosen</h2>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="p-2">Nama</th>
                            <th class="p-2">Keahlian</th>
                            <th class="p-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dosen as $d): ?>
                            <tr>
                                <td class="p-2"><?php echo htmlspecialchars($d['nama']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($d['keahlian']); ?></td>
                                <td class="p-2"><?php echo $d['status_ketersediaan']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>