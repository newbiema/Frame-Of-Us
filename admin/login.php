<?php
session_start();
require_once __DIR__ . '/../db.php'; // expects $conn (mysqli)

// ---- Config ----
// Simple rate limit per session (3 attempts per minute)
$MAX_ATTEMPTS = 3; $WINDOW_SEC = 60;
if (!isset($_SESSION['login_attempts'])) { $_SESSION['login_attempts'] = []; }

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = false;

function tooManyAttempts(array &$bucket, int $max, int $window): bool {
  $t = time();
  // drop old
  $bucket = array_values(array_filter($bucket, fn($ts) => $t - $ts < $window));
  if (count($bucket) >= $max) return true;
  return false;
}

function recordAttempt(array &$bucket) {
  $bucket[] = time();
}

function isBcrypt(string $hash): bool { return str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF check
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(400);
    $error = 'Invalid security token.';
  } elseif (tooManyAttempts($_SESSION['login_attempts'], $MAX_ATTEMPTS, $WINDOW_SEC)) {
    http_response_code(429);
    $error = 'Terlalu banyak percobaan. Coba lagi sebentar.';
  } else {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
      $error = 'Username dan password wajib diisi.';
    } else {
      // Query user
      if ($stmt = $conn->prepare('SELECT id, username, password, role FROM admin WHERE username = ? LIMIT 1')) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $ok = false;
        $role = null;
        if ($user) {
          $dbHash = (string)$user['password'];
          $role = $user['role'] ?? 'admin';

          if (isBcrypt($dbHash)) {
            $ok = password_verify($password, $dbHash);
          } else {
            // Backward-compat MD5 -> verify & upgrade to bcrypt
            if (md5($password) === $dbHash) {
              $ok = true;
              $newHash = password_hash($password, PASSWORD_BCRYPT);
              if ($upd = $conn->prepare('UPDATE admin SET password = ? WHERE id = ?')) {
                $upd->bind_param('si', $newHash, $user['id']);
                $upd->execute();
                $upd->close();
              }
            }
          }
        }

        if ($ok && ($role === 'admin' || $role === null)) {
          session_regenerate_id(true);
          $_SESSION['login'] = true;
          $_SESSION['role'] = 'admin';
          $_SESSION['user_id'] = (int)$user['id'];
          $success = true;
        } else {
          recordAttempt($_SESSION['login_attempts']);
          $error = 'Username atau password salah.';
        }
      } else {
        $error = 'Koneksi database bermasalah.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login Admin - Pixel Frame</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">
  <style>
    :root { --pink:#ff9bb3; --purple:#b5a1ff; --blue:#9bd4ff; --yellow:#ffe08a; }
    body { font-family:'Short Stack', cursive; background-color:#fff5f7; image-rendering:pixelated; }
    .pixel-border { border:4px solid #000; box-shadow:8px 8px 0 rgba(0,0,0,0.2); position:relative; }
    .pixel-border:before { content:''; position:absolute; top:2px; left:2px; right:2px; bottom:2px; border:2px solid #fff; pointer-events:none; }
    .cute-btn { background:var(--pink); color:#fff; border:3px solid #000; padding:10px 20px; font-size:1rem; box-shadow:5px 5px 0 rgba(0,0,0,0.2); transition:.1s; font-family:'Press Start 2P', cursive; text-shadow:2px 2px 0 rgba(0,0,0,0.2); }
    .cute-btn:hover { transform:translate(2px,2px); box-shadow:3px 3px 0 rgba(0,0,0,0.2); }
    .cute-btn:active { transform:translate(4px,4px); box-shadow:none; }
    .title-font { font-family:'Press Start 2P', cursive; text-shadow:3px 3px 0 var(--purple); }
    .pixel-input { border:3px solid #000; box-shadow:4px 4px 0 rgba(0,0,0,0.1); }
    .pixel-input:focus { outline:none; box-shadow:2px 2px 0 rgba(0,0,0,0.1); transform:translate(2px,2px); }
    .pixel-cloud { position:absolute; background:#fff; border:3px solid #000; border-radius:50%; }
    .pixel-star { position:absolute; color:var(--yellow); text-shadow:2px 2px 0 rgba(0,0,0,0.2); animation: twinkle 2s infinite alternate; }
    @keyframes twinkle { from{opacity:.6; transform:scale(1);} to{opacity:1; transform:scale(1.2);} }
    .floating { animation: floating 3s ease-in-out infinite; }
    @keyframes floating { 0%{transform:translateY(0);} 50%{transform:translateY(-10px);} 100%{transform:translateY(0);} }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4" style="background: linear-gradient(to bottom right, var(--pink), var(--purple));">

<div class="pixel-cloud" style="width: 80px; height: 40px; top: 10%; left: 5%;"></div>
<div class="pixel-cloud" style="width: 60px; height: 30px; top: 15%; right: 10%;"></div>
<div class="pixel-star" style="top: 20%; left: 15%;">✦</div>
<div class="pixel-star" style="top: 25%; right: 20%;">✦</div>

  <div class="pixel-border bg-white p-8 rounded-xl w-full max-w-md relative z-10">
    <div class="flex justify-center mb-6">
      <div class="w-16 h-16 border-3 border-black rounded-full flex items-center justify-center floating">
        <i class="fas fa-user-shield text-3xl" style="color: var(--purple);"></i>
      </div>
    </div>

    <h2 class="text-2xl sm:text-3xl title-font text-center mb-8" style="color: var(--purple);">ADMIN LOGIN</h2>

    <form method="POST" class="space-y-6" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
      <div>
        <label class="block text-sm font-bold mb-2" style="color: var(--purple);">USERNAME</label>
        <div class="flex items-center pixel-input bg-white rounded-lg px-3 py-2">
          <i class="fas fa-user mr-3" style="color: var(--pink);"></i>
          <input type="text" name="username" required class="w-full bg-transparent focus:outline-none placeholder-gray-500" placeholder="Enter username">
        </div>
      </div>
      <div>
        <label class="block text-sm font-bold mb-2" style="color: var(--purple);">PASSWORD</label>
        <div class="flex items-center pixel-input bg-white rounded-lg px-3 py-2">
          <i class="fas fa-lock mr-3" style="color: var(--pink);"></i>
          <input type="password" name="password" required class="w-full bg-transparent focus:outline-none placeholder-gray-500" placeholder="Enter password">
        </div>
      </div>
      <button type="submit" class="cute-btn w-full py-3"><i class="fas fa-sign-in-alt mr-2"></i> LOGIN</button>
    </form>
  </div>

<?php if (!empty($error)): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      Swal.fire({ icon:'error', title:'LOGIN FAILED!', text: <?php echo json_encode($error); ?>, confirmButtonColor:'#ff9bb3', background:'#fff', confirmButtonText:'TRY AGAIN', customClass:{ title:'title-font', confirmButton:'pixel-border' } });
    });
  </script>
<?php endif; ?>

<?php if ($success): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      Swal.fire({ icon:'success', title:'ACCESS GRANTED!', text:'Welcome back Admin!', timer:1500, showConfirmButton:false, background:'#fff', customClass:{ title:'title-font' } }).then(()=>{ window.location.href='dashboard.php'; });
    });
  </script>
<?php endif; ?>

</body>
</html>
