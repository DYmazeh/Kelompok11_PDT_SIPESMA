<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $keahlian = $_POST['keahlian'];
    $status = $_POST['status_ketersediaan'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("CALL add_dosen(?, ?, ?)")->execute([$nama, $keahlian, $status]);
        $pdo->commit();
        $success = "Dosen berhasil ditambahkan!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal menambah dosen: " . $e->getMessage();
    }
}

// Fetch all dosen
$dosen = $pdo->query("SELECT id, nama, keahlian, status_ketersediaan FROM dosen ORDER BY nama")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Dosen - Seminar Kerja Praktik</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex">
    <div class="w-64 bg-gray-800 text-white h-screen p-4">
        <h2 class="text-xl font-bold mb-6">Seminar KP</h2>
        <nav>
            <a href="dashboard.php" class="block py-2 px-4 hover:bg-gray-700">Dashboard</a>
            <a href="manajemen_dosen.php" class="block py-2 px-4 bg-blue-500 rounded">Manajemen Dosen</a>
            <a href="manajemen_seminar.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Seminar</a>
            <a href="../logout.php" class="block py-2 px-4 hover:bg-gray-700">Logout</a>
        </nav>
    </div>
    <div class="flex-1 p-8">
        <h1 class="text-2xl font-bold mb-6">Manajemen Dosen</h1>
        <?php if ($success): ?>
            <p class="text-green-500 mb-4"><?php echo htmlspecialchars($success); ?></p>
        <?php elseif ($error): ?>
            <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" class="bg-white p-6 rounded-lg shadow mb-6">
            <div class="mb-4">
                <label class="block text-gray-700">Nama Dosen</label>
                <input type="text" name="nama" class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Keahlian</label>
                <input type="text" name="keahlian" class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Status Ketersediaan</label>
                <select name="status_ketersediaan" class="w-full px-3 py-2 border rounded-lg" required>
                    <option value="Sedia">Sedia</option>
                    <option value="Tidak Sedia">Tidak Sedia</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Tambah Dosen</button>
        </form>
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Daftar Dosen</h2>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2">Nama</th>
                        <th class="p-2">Keahlian</th>
                        <th class="p-2">Status Ketersediaan</th>
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
    </div>
</body>
</html>