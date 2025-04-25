<?php
session_start();
include '../db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $description = $_POST['description']; // Menambahkan deskripsi
    $foto = $_FILES['foto']['name'];
    $tmp = $_FILES['foto']['tmp_name'];
    $path = "../uploads/" . $foto;

    // Periksa apakah file berhasil di-upload
    if (move_uploaded_file($tmp, $path)) {
        // Menyimpan data ke dalam database (termasuk deskripsi)
        $query = "INSERT INTO photos (title, description, filename) VALUES ('$title', '$description', '$foto')";
        if ($conn->query($query)) {
            echo "Upload berhasil. <a href='upload.php'>Upload lagi</a>";
        } else {
            echo "Gagal menyimpan data ke database.";
        }
    } else {
        echo "Gagal upload.";
    }
}
?>
