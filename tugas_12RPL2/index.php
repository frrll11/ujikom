<?php

session_start(); // Memulai session untuk menyimpan data login user

// Konstanta file penyimpanan data
define('USERS_FILE', 'users.json');
define('TODOS_FILE', 'todos.json');

// ============================================
// FUNGSI UTAMA
// ============================================

// Mengambil semua data user dari file JSON
function getUsers() {
    if (file_exists(USERS_FILE)) {
        $data = file_get_contents(USERS_FILE);
        $result = json_decode($data, true);
        
        if (!is_array($result)) return [];
        
        // Menambahkan default value jika ada field yang kosong
        foreach ($result as &$user) {
            if (!isset($user['role']))       $user['role']       = 'user';
            if (!isset($user['id']))         $user['id']         = uniqid();
            if (!isset($user['name']))       $user['name']       = 'User ' . $user['id'];
            if (!isset($user['email']))      $user['email']      = 'user' . $user['id'] . '@example.com';
            if (!isset($user['created_at'])) $user['created_at'] = date('Y-m-d H:i:s');
        }
        return $result;
    }
    return [];
}

// Menyimpan data user ke file JSON
function saveUsers($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

// Mencari user berdasarkan email
function findUserByEmail($email) {
    $users = getUsers();
    foreach ($users as $user) {
        if (isset($user['email']) && $user['email'] === $email) {
            return $user;
        }
    }
    return null;
}

// Membuat data default jika file kosong
function initializeDefaultData() {
    $users = getUsers();
    
    // Buat user default jika belum ada
    if (empty($users) || !is_array($users)) {
        $defaultUsers = [
            [
                'id'         => 1,
                'name'       => 'Admin Farel',
                'email'      => 'farel@example.com',
                'password'   => password_hash('123456', PASSWORD_DEFAULT), // Hash password
                'role'       => 'admin',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'id'         => 2,
                'name'       => 'User Biasa',
                'email'      => 'user@example.com',
                'password'   => password_hash('user123', PASSWORD_DEFAULT),
                'role'       => 'user',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        saveUsers($defaultUsers);
    }
    
    // Buat file todos kosong jika belum ada
    if (!file_exists(TODOS_FILE)) {
        file_put_contents(TODOS_FILE, json_encode([]));
    }
}

initializeDefaultData();

// ============================================
// LOGIN HANDLER
// ============================================

$error   = '';
$success = '';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: home.php");
    }
    exit;
}

// Proses logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Tampilkan pesan sukses setelah register
if (isset($_GET['registered'])) {
    $success = "Akun berhasil dibuat! Silakan login.";
}

// Proses form login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi!";
    } else {
        $user = findUserByEmail($email);
        
        // Verifikasi password dengan hash yang tersimpan
        if ($user && isset($user['password']) && password_verify($password, $user['password'])) {
            // Simpan data user ke session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = isset($user['role']) ? $user['role'] : 'user';
            
            // Redirect berdasarkan role
            if ($_SESSION['user_role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: home.php");
            }
            exit;
        } else {
            $error = "Email atau password salah!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TugasKu - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS styling untuk tampilan login */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            min-height: 100vh;
        }

        .auth-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .auth-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-header h1 {
            color: #4a5568;
            margin-bottom: 10px;
            font-size: 32px;
        }

        .auth-header p {
            color: #718096;
            font-size: 16px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fc;
            color: #333;
        }

        .form-control:focus {
            border-color: #4299e1;
            background: #fff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(66,153,225,0.2);
        }

        .btn-auth {
            width: 100%;
            padding: 14px;
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-auth:hover {
            background: #3182ce;
            transform: translateY(-2px);
        }

        .auth-link {
            text-align: center;
            margin-top: 25px;
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

        .demo-info {
            background: #ebf8ff;
            border: 1px solid #bee3f8;
            border-radius: 8px;
            padding: 20px;
            margin-top: 25px;
        }

        .demo-info h4 {
            color: #2c5282;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .demo-info li {
            margin-bottom: 5px;
            color: #4a5568;
        }

        @media (max-width: 480px) {
            .auth-box { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">

            <!-- Tampilkan pesan error jika ada -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Tampilkan pesan sukses jika ada -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="auth-header">
                <h1><i class="fas fa-tasks"></i> TugasKu</h1>
                <p>Silakan login untuk mengakses aplikasi</p>
            </div>

            <!-- Form Login -->
            <form method="POST" action="index.php">
                <div class="form-group">
                    <input type="email" name="email" class="form-control"
                           placeholder="Email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <input type="password" name="password" class="form-control"
                           placeholder="Password" required>
                </div>

                <button type="submit" name="login" class="btn-auth">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>

                <div class="auth-link">
                    Belum punya akun? <a href="register.php">Daftar di sini</a>
                </div>
            </form>

            <!-- Informasi akun demo -->
            <div class="demo-info">
                <h4><i class="fas fa-info-circle"></i> Demo Account:</h4>
                <ul>
                    <li><strong>Admin:</strong> farel@example.com / 123456</li>
                    <li><strong>User:</strong> user@example.com / user123</li>
                </ul>
            </div>

        </div>
    </div>
</body>
</html>