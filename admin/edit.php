<?php
include '../db.php';
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id']); // casting agar aman dari SQL injection
$photo = $conn->query("SELECT * FROM photos WHERE id = $id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc = $conn->real_escape_string($_POST['description']);

    if ($_FILES['new_photo']['name']) {
        $filename = $_FILES['new_photo']['name'];
        $tmp = $_FILES['new_photo']['tmp_name'];
        $path = "../uploads/" . basename($filename);

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($tmp);

        if (in_array($file_type, $allowed_types)) {
            move_uploaded_file($tmp, $path);
            $conn->query("UPDATE photos SET filename='$filename', description='$desc' WHERE id=$id");
        } else {
            $error = "Format file tidak didukung.";
        }
    } else {
        $conn->query("UPDATE photos SET description='$desc' WHERE id=$id");
    }

    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Foto</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-lg mx-auto bg-white p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4 text-blue-600">Edit Foto</h1>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
                <textarea name="description" required class="w-full border border-gray-300 rounded p-2 mt-1"><?php echo htmlspecialchars($photo['description']); ?></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Foto Saat Ini</label>
                <img src="../uploads/<?php echo $photo['filename']; ?>" class="w-full h-48 object-cover rounded border mt-1" alt="Current Photo">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Ganti Foto (Opsional)</label>
                <input type="file" name="new_photo" accept="image/*" class="w-full border border-gray-300 rounded p-2 mt-1">
            </div>

            <div class="flex justify-between items-center">
                <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition" type="submit">Simpan</button>
                <a href="dashboard.php" class="text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</body>
</html>
