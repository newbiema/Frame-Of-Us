<?php 
session_start();
include '../db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $hashed_password = md5($password);

    $result = $conn->query("SELECT * FROM admin WHERE username = '$username' AND password = '$hashed_password'");

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['role'] == 'admin') {
            $_SESSION['login'] = true;
            $_SESSION['role'] = 'admin';
            header("Location: dashboard.php");
            exit;
        }
    } else {
        $error = "Username atau password salah.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    body {
        font-family: 'Poppins', sans-serif;
    }
    .glass {
        background: rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 bg-gradient-to-br from-purple-600 via-pink-500 to-red-400">

  <div class="glass p-8 sm:p-10 rounded-3xl shadow-2xl w-full max-w-md transition-all duration-500 ease-in-out">
    <div class="flex justify-center mb-4 animate-bounce">
      <i class="fas fa-user-shield text-white text-4xl"></i>
    </div>

    <h2 class="text-2xl sm:text-3xl font-bold text-center text-white mb-8">Login Admin</h2>

    <form method="POST" class="space-y-6">
      <div>
        <label class="block text-sm text-white mb-1 font-semibold">Username</label>
        <div class="flex items-center bg-white/80 rounded-xl px-3 py-2 border border-gray-300">
          <i class="fas fa-user text-pink-500 mr-3"></i>
          <input type="text" name="username" required class="w-full bg-transparent focus:outline-none text-sm sm:text-base" placeholder="Masukkan username">
        </div>
      </div>
      <div>
        <label class="block text-sm text-white mb-1 font-semibold">Password</label>
        <div class="flex items-center bg-white/80 rounded-xl px-3 py-2 border border-gray-300">
          <i class="fas fa-lock text-pink-500 mr-3"></i>
          <input type="password" name="password" required class="w-full bg-transparent focus:outline-none text-sm sm:text-base" placeholder="Masukkan password">
        </div>
      </div>
      <button type="submit" class="w-full bg-pink-600 hover:bg-pink-700 text-white font-semibold py-2 rounded-xl shadow-lg transition-all duration-300 transform hover:scale-105">
        <i class="fas fa-sign-in-alt mr-2"></i> Login
      </button>
    </form>
  </div>

  <?php if (!empty($error)): ?>
  <script>
    Swal.fire({
      icon: 'error',
      title: 'Gagal Login!',
      text: '<?= $error ?>',
      confirmButtonColor: '#e11d48',
    });
  </script>
  <?php endif; ?>

</body>
</html>
