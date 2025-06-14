<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../index.php");
    exit;
}

$success = $error = '';
$seminars = $pdo->query("
    SELECT s.id, s.bulan_tahun, s.kuota, s.status,
           COALESCE(COUNT(p.id), 0) AS terisi
    FROM seminar s
    LEFT JOIN pendaftaran p ON s.id = p.id_seminar AND p.status IN ('Menunggu', 'Diterima')
    WHERE s.bulan_tahun >= CURDATE() AND s.status = 'Open'
    GROUP BY s.id
    ORDER BY s.bulan_tahun
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_seminar = $_POST['id_seminar'] ?? '';
    $nama_laporan = $_POST['nama_laporan'] ?? '';
    $tanda_tangan = $_FILES['tanda_tangan'] ?? null;

    if (!$id_seminar || !$nama_laporan || !$tanda_tangan || $tanda_tangan['error'] == UPLOAD_ERR_NO_FILE) {
        $error = "Semua kolom wajib diisi, termasuk tanda tangan dosen pembimbing.";
    } elseif ($tanda_tangan['size'] > 2 * 1024 * 1024) {
        $error = "Ukuran file tanda tangan maksimal 2MB.";
    } elseif (!in_array(mime_content_type($tanda_tangan['tmp_name']), ['image/jpeg', 'image/png'])) {
        $error = "File tanda tangan harus berformat JPG atau PNG.";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                SELECT s.status, s.kuota - COALESCE(COUNT(p.id), 0) AS remaining_quota
                FROM seminar s
                LEFT JOIN pendaftaran p ON s.id = p.id_seminar AND p.status IN ('Menunggu', 'Diterima')
                WHERE s.id = ?
                GROUP BY s.id
                FOR UPDATE
            ");
            $stmt->execute([$id_seminar]);
            $seminar = $stmt->fetch();
            if (!$seminar || $seminar['status'] == 'Closed' || $seminar['remaining_quota'] <= 0) {
                throw new Exception("Seminar tidak tersedia atau kuota penuh.");
            }
            $upload_dir = '../uploads/tanda_tangan/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_ext = pathinfo($tanda_tangan['name'], PATHINFO_EXTENSION);
            $file_name = 'ttd_' . time() . '_' . $_SESSION['user_id'] . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            if (!move_uploaded_file($tanda_tangan['tmp_name'], $file_path)) {
                throw new Exception("Gagal mengunggah file tanda tangan.");
            }
            $stmt = $pdo->prepare("INSERT INTO pendaftaran (id_user, id_seminar, nama_laporan, tanda_tangan_dosen_pembimbing, status) VALUES (?, ?, ?, ?, 'Menunggu')");
            $stmt->execute([$_SESSION['user_id'], $id_seminar, $nama_laporan, $file_path]);
            $pdo->commit();
            $success = "Pendaftaran seminar berhasil dikirim! Menunggu persetujuan admin.";
        } catch (Exception $e) {
            $pdo->rollBack();
            if (isset($file_path) && file_exists($file_path)) {
                unlink($file_path);
            }
            $error = "Gagal mendaftar: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Seminar - Seminar Kerja Praktik</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex">
    <div class="w-64 bg-gray-800 text-white h-screen p-4">
        <h2 class="text-xl font-bold mb-6">Seminar KP</h2>
        <nav>
            <a href="dashboard.php" class="block py-2 px-4 hover:bg-gray-700">Dashboard</a>
            <a href="pendaftaran.php" class="block py-2 px-4 bg-blue-500 rounded">Pendaftaran Seminar</a>
            <a href="hasil_seminar.php" class="block py-2 px-4 hover:bg-gray-700">Hasil Seminar</a>
            <a href="../logout.php" class="block py-2 px-4 hover:bg-gray-700">Logout</a>
        </nav>
    </div>
    <div class="flex-1 p-8">
        <h1 class="text-2xl font-bold mb-6">Pendaftaran Seminar</h1>
        <?php if ($success): ?>
            <p class="text-green-500 mb-4"><?php echo htmlspecialchars($success); ?></p>
        <?php elseif ($error): ?>
            <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Form Pendaftaran Seminar</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-gray-700">Seminar</label>
                    <select name="id_seminar" class="w-full px-3 py-2 border rounded-lg" required>
                        <option value="">Pilih Seminar</option>
                        <?php foreach ($seminars as $s): ?>
                            <?php if ($s['kuota'] - $s['terisi'] > 0): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo date('F Y', strtotime($s['bulan_tahun'])) . " (Sisa Kuota: " . ($s['kuota'] - $s['terisi']) . ")"; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Nama Laporan</label>
                    <input type="text" name="nama_laporan" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars($nama_laporan ?? ''); ?>" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Tanda Tangan Dosen Pembimbing (JPG/PNG, Maks 2MB)</label>
                    <input type="file" name="tanda_tangan" class="w-full px-3 py-2 border rounded-lg" accept="image/jpeg,image/png" required>
                </div>
                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Daftar Seminar</button>
            </form>
        </div>
    </div>
</body>
</html>