<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.nama_laporan, hs.nilai_dosen_1, hs.nilai_dosen_2, na.rata_rata, na.kategori, na.status_kelulusan
    FROM pendaftaran p
    LEFT JOIN hasil_seminar hs ON p.id = hs.id_pendaftaran
    LEFT JOIN nilai_akhir na ON p.id = na.id_pendaftaran
    WHERE p.id_user = ?
");
$stmt->execute([$_SESSION['user_id']]);
$hasil_seminars = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Seminar - Seminar Kerja Praktik</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex">
    <div class="w-64 bg-gray-800 text-white h-screen p-4">
        <h2 class="text-xl font-bold mb-6">Seminar KP</h2>
        <nav>
            <a href="dashboard.php" class="block py-2 px-4 hover:bg-gray-700">Dashboard</a>
            <a href="pendaftaran.php" class="block py-2 px-4 hover:bg-gray-700">Pendaftaran Seminar</a>
            <a href="hasil_seminar.php" class="block py-2 px-4 bg-blue-500 rounded">Hasil Seminar</a>
            <a href="../logout.php" class="block py-2 px-4 hover:bg-gray-700">Logout</a>
        </nav>
    </div>
    <div class="flex-1 p-8">
        <h1 class="text-2xl font-bold mb-6">Hasil Seminar</h1>
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Daftar Hasil Seminar</h2>
            <?php if (empty($hasil_seminars)): ?>
                <p class="text-gray-700">Belum ada hasil seminar.</p>
            <?php else: ?>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="p-2">Nama Laporan</th>
                            <th class="p-2">Nilai Dosen 1</th>
                            <th class="p-2">Nilai Dosen 2</th>
                            <th class="p-2">Rata-rata</th>
                            <th class="p-2">Kategori</th>
                            <th class="p-2">Status Kelulusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hasil_seminars as $h): ?>
                            <tr>
                                <td class="p-2"><?php echo htmlspecialchars($h['nama_laporan']); ?></td>
                                <td class="p-2"><?php echo $h['nilai_dosen_1'] ?? 'Belum dinilai'; ?></td>
                                <td class="p-2"><?php echo $h['nilai_dosen_2'] ?? 'Belum dinilai'; ?></td>
                                <td class="p-2"><?php echo $h['rata_rata'] ?? 'Belum dinilai'; ?></td>
                                <td class="p-2"><?php echo $h['kategori'] ?? 'Belum dikategorikan'; ?></td>
                                <td class="p-2"><?php echo $h['status_kelulusan'] ?? 'Belum ditentukan'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>