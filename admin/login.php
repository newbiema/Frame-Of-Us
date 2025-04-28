<?php 
session_start();
include '../db.php';

$error = ""; // Tambahkan variabel error awal

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Menggunakan MD5 untuk meng-hash password yang dimasukkan
    $hashed_password = md5($password);

    // Cek apakah username dan password cocok dengan data di database
    $result = $conn->query("SELECT * FROM admin WHERE username = '$username' AND password = '$hashed_password'");

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Jika user ditemukan dan adalah admin
        if ($user['role'] == 'admin') {
            $_SESSION['login'] = true;
            $_SESSION['role'] = 'admin'; // Simpan status sebagai admin
            header("Location: dashboard.php");
            exit;
        }
    } else {
        $error = "Username atau password salah."; // Simpan error
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- SweetAlert2 CDN -->
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-500 to-pink-500">

<div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-sm">
    <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">Login Admin</h2>

    <form method="POST" class="space-y-5">
        <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Username</label>
            <input type="text" name="username" required
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-400">
        </div>
        <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="password" required
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-400">
        </div>
        <button type="submit"
            class="w-full bg-pink-500 hover:bg-pink-600 text-white font-semibold py-2 rounded-lg transition duration-300">
            Login
        </button>
    </form>
</div>

<?php if (!empty($error)): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: '<?= $error ?>',
    });
</script>
<?php endif; ?>

</body>
</html>
