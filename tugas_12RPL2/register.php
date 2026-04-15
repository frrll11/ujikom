<?php
session_start(); // Memulai session untuk cek login

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    header("Location: " . (($_SESSION['user_role'] ?? '') === 'admin' ? 'admin.php' : 'home.php'));
    exit;
}

// ============================================
// FUNGSI MANAJEMEN USER (sama dengan index.php)
// ============================================
define('USERS_FILE', 'users.json');

// Mengambil semua data user dari file JSON
function getUsers() {
    if (file_exists(USERS_FILE)) {
        $data   = file_get_contents(USERS_FILE);
        $result = json_decode($data, true);
        return is_array($result) ? $result : [];
    }
    return [];
}

// Menyimpan data user ke file JSON
function saveUsers($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

// Cek apakah email sudah terdaftar
function emailExists($email) {
    foreach (getUsers() as $user) {
        if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) return true;
    }
    return false;
}

// ============================================
// PROSES REGISTRASI
// ============================================
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = trim($_POST['nama']     ?? '');
    $email     = trim($_POST['email']    ?? '');
    $password  = $_POST['password']      ?? '';
    $konfirmasi = $_POST['konfirmasi']   ?? '';

    // Validasi input
    if (empty($nama))                                                $errors[] = 'Nama lengkap wajib diisi.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Alamat email tidak valid.';
    if (strlen($password) < 6)                                       $errors[] = 'Password minimal 6 karakter.';
    if ($password !== $konfirmasi)                                    $errors[] = 'Konfirmasi password tidak cocok.';
    if (empty($errors) && emailExists($email))                        $errors[] = 'Email sudah terdaftar. Gunakan email lain.';

    // Jika lolos validasi, simpan user baru
    if (empty($errors)) {
        $users   = getUsers();
        $users[] = [
            'id'         => count($users) + 1,
            'name'       => $nama,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT), // Hash password
            'role'       => 'user', // Default role user
            'created_at' => date('Y-m-d H:i:s')
        ];
        saveUsers($users);

        // Redirect ke halaman login dengan notifikasi sukses
        header("Location: index.php?registered=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TugasKu - Daftar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS styling untuk form registrasi */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .auth-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 440px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .auth-header h1 {
            color: #4a5568;
            font-size: 30px;
            margin-bottom: 8px;
        }

        .auth-header p {
            color: #718096;
            font-size: 15px;
        }

        /* Alert error styling */
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error ul {
            padding-left: 18px;
        }

        .alert-error ul li {
            margin-bottom: 3px;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            background: #f8f9fc;
            color: #333;
            transition: border-color 0.2s, background 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }

        .form-control.is-error {
            border-color: #e53e3e;
        }

        /* Wrapper untuk input password dengan toggle */
        .pw-wrapper {
            position: relative;
        }

        .pw-wrapper .form-control {
            padding-right: 46px;
        }

        .pw-toggle {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #a0aec0;
            font-size: 15px;
            padding: 0;
            line-height: 1;
        }

        .pw-toggle:hover {
            color: #667eea;
        }

        /* Tombol daftar */
        .btn-auth {
            width: 100%;
            padding: 13px;
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 6px;
        }

        .btn-auth:hover {
            background: #3182ce;
            transform: translateY(-2px);
        }

        .btn-auth:active {
            transform: translateY(0);
        }

        /* Link ke halaman login */
        .auth-link {
            text-align: center;
            margin-top: 22px;
            color: #718096;
            font-size: 15px;
        }

        .auth-link a {
            color: #4299e1;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .auth-box { padding: 30px 18px; }
        }
    </style>
</head>
<body>

<div class="auth-box">

    <div class="auth-header">
        <h1><i class="fas fa-tasks"></i> TugasKu</h1>
        <p>Buat akun baru untuk mulai menggunakan aplikasi</p>
    </div>

    <!-- Tampilkan semua pesan error -->
    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Form Registrasi -->
    <form method="POST" action="register.php" novalidate>

        <!-- Nama Lengkap -->
        <div class="form-group">
            <label for="nama">Nama Lengkap</label>
            <input
                type="text"
                id="nama"
                name="nama"
                class="form-control <?= in_array('Nama lengkap wajib diisi.', $errors) ? 'is-error' : '' ?>"
                placeholder="Masukkan nama lengkap"
                value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                autocomplete="name"
            >
        </div>

        <!-- Email -->
        <div class="form-group">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                placeholder="Masukkan alamat email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                autocomplete="email"
            >
        </div>

        <!-- Kata Sandi dengan toggle visibility -->
        <div class="form-group">
            <label for="password">Kata Sandi</label>
            <div class="pw-wrapper">
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Minimal 6 karakter"
                    autocomplete="new-password"
                >
                <button type="button" class="pw-toggle" onclick="togglePw('password', this)" aria-label="Tampilkan password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <!-- Konfirmasi Kata Sandi dengan toggle visibility -->
        <div class="form-group">
            <label for="konfirmasi">Konfirmasi Kata Sandi</label>
            <div class="pw-wrapper">
                <input
                    type="password"
                    id="konfirmasi"
                    name="konfirmasi"
                    class="form-control"
                    placeholder="Ulangi kata sandi"
                    autocomplete="new-password"
                >
                <button type="button" class="pw-toggle" onclick="togglePw('konfirmasi', this)" aria-label="Tampilkan konfirmasi">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-auth">
            <i class="fas fa-user-plus"></i> Daftar Sekarang
        </button>

    </form>

    <div class="auth-link">
        Sudah punya akun? <a href="index.php">Masuk di sini</a>
    </div>

</div>

<!-- JavaScript untuk toggle visibility password -->
<script>
    function togglePw(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon  = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>
</body>
</html>