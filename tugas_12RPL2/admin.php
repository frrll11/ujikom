<?php
session_start();

define('USERS_FILE', 'users.json');
define('TODOS_FILE', 'todos.json');
define('UPLOADS_DIR', 'uploads/');

if (!file_exists(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0777, true);
}

function getUsers() {
    if (file_exists(USERS_FILE)) {
        $data   = file_get_contents(USERS_FILE);
        $result = json_decode($data, true);
        if (!is_array($result)) return [];
        foreach ($result as &$user) {
            if (!isset($user['role']))       $user['role']       = 'user';
            if (!isset($user['id']))         $user['id']         = uniqid();
            if (!isset($user['name']))       $user['name']       = 'User';
            if (!isset($user['email']))      $user['email']      = '';
            if (!isset($user['created_at'])) $user['created_at'] = date('Y-m-d H:i:s');
        }
        return $result;
    }
    return [];
}

function getTodos() {
    if (file_exists(TODOS_FILE)) {
        $data   = file_get_contents(TODOS_FILE);
        $result = json_decode($data, true);
        return is_array($result) ? $result : [];
    }
    return [];
}

function saveTodos($todos) {
    file_put_contents(TODOS_FILE, json_encode(array_values($todos), JSON_PRETTY_PRINT));
}

// ── Tipe file yang diizinkan ──────────────────────────────────
// Dokumen
$allowedDocTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];
$allowedDocExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

// Gambar
$allowedImgTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp', 'image/tiff'];
$allowedImgExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'tif'];

// Gabungan semua tipe & ekstensi
$allowedTypes = array_merge($allowedDocTypes, $allowedImgTypes);
$allowedExts  = array_merge($allowedDocExts, $allowedImgExts);

// Proteksi halaman
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { header("Location: home.php"); exit; }
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_todo_action'])) {
    $action = $_POST['admin_todo_action'];
    $todos  = getTodos();

    // ── TAMBAH TUGAS ─────────────────────────────────────────
    if ($action === 'add_todo_admin') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status'] ?? 'open';
        $user_id     = $_POST['user_id'] ?? $_SESSION['user_id'];

        if (empty($title)) {
            $error = 'Judul tugas tidak boleh kosong!';
        } else {
            $fileInfo = null;

            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $fileSize = $_FILES['attachment']['size'];
                $fileTmp  = $_FILES['attachment']['tmp_name'];
                $fileName = $_FILES['attachment']['name'];
                $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $fileMime = mime_content_type($fileTmp);

                // Tentukan kategori file: gambar atau dokumen
                $isImage = in_array($fileMime, $allowedImgTypes) || in_array($fileExt, $allowedImgExts);
                $isDoc   = in_array($fileMime, $allowedDocTypes) || in_array($fileExt, $allowedDocExts);

                if ($fileSize > 10 * 1024 * 1024) { // Gambar boleh sampai 10 MB
                    $error = 'Ukuran file maksimal 10 MB!';
                } elseif (!$isImage && !$isDoc) {
                    $error = 'Format tidak didukung! Gunakan PDF, Word, Excel, JPG, PNG, GIF, WEBP, SVG, BMP, atau TIFF.';
                } else {
                    $newFileName = uniqid('file_') . '.' . $fileExt;
                    $destination = UPLOADS_DIR . $newFileName;
                    if (move_uploaded_file($fileTmp, $destination)) {
                        $fileInfo = [
                            'original_name' => $fileName,
                            'saved_name'    => $newFileName,
                            'path'          => $destination,
                            'size'          => $fileSize,
                            'type'          => $fileMime,
                            'extension'     => $fileExt,
                            'is_image'      => $isImage, // flag untuk tampilan
                        ];
                    } else {
                        $error = 'Gagal mengupload file!';
                    }
                }
            }

            if (empty($error)) {
                $newId   = !empty($todos) ? (max(array_column($todos, 'id')) + 1) : 1;
                $newTodo = [
                    'id'          => $newId,
                    'user_id'     => $user_id,
                    'title'       => $title,
                    'description' => $description,
                    'status'      => $status,
                    'priority'    => $_POST['priority'] ?? 'medium',
                    'completed'   => ($status === 'done'),
                    'attachment'  => $fileInfo,
                    'created_at'  => date('Y-m-d H:i:s'),
                ];
                $todos[] = $newTodo;
                saveTodos($todos);
                $success = 'Tugas berhasil ditambahkan!';
            }
        }

    // ── TOGGLE STATUS ─────────────────────────────────────────
    } elseif ($action === 'toggle_todo_admin') {
        $todoId = $_POST['todo_id'];
        foreach ($todos as &$todo) {
            if ($todo['id'] == $todoId) {
                $todo['completed'] = !$todo['completed'];
                $todo['status']    = $todo['completed'] ? 'done' : 'open';
                break;
            }
        }
        saveTodos($todos);
        $success = 'Status tugas diperbarui!';

    // ── HAPUS TUGAS ───────────────────────────────────────────
    } elseif ($action === 'delete_todo_admin') {
        $todoId = $_POST['todo_id'];
        foreach ($todos as $todo) {
            if ($todo['id'] == $todoId && isset($todo['attachment']['path'])) {
                if (file_exists($todo['attachment']['path'])) unlink($todo['attachment']['path']);
            }
        }
        $todos = array_filter($todos, fn($t) => $t['id'] != $todoId);
        saveTodos($todos);
        $success = 'Tugas berhasil dihapus!';
    }

    $todos = getTodos();
}

$users = getUsers();
$todos = getTodos();

$stats = [
    'total_users'     => count($users),
    'total_todos'     => count($todos),
    'completed_todos' => count(array_filter($todos, fn($t) => isset($t['completed']) && $t['completed'])),
    'today_todos'     => count(array_filter($todos, fn($t) =>
        date('Y-m-d', strtotime($t['created_at'] ?? 'now')) == date('Y-m-d')
    )),
];

$recentTodos = array_reverse(array_slice($todos, -5, 5, true));
$section     = $_GET['section'] ?? 'dashboard';

// Helper: ikon & warna chip file
function getFileChip($ext, $mime = '') {
    $ext = strtolower($ext);
    $imgExts = ['jpg','jpeg','png','gif','webp','svg','bmp','tiff','tif'];
    if (in_array($ext, $imgExts) || strpos($mime, 'image/') === 0) {
        return ['cls' => 'image', 'ico' => 'fa-file-image', 'label' => strtoupper($ext)];
    }
    return match($ext) {
        'pdf'         => ['cls' => 'pdf',   'ico' => 'fa-file-pdf',   'label' => 'PDF'],
        'doc','docx'  => ['cls' => 'word',  'ico' => 'fa-file-word',  'label' => strtoupper($ext)],
        'xls','xlsx'  => ['cls' => 'excel', 'ico' => 'fa-file-excel', 'label' => strtoupper($ext)],
        default       => ['cls' => 'other', 'ico' => 'fa-file',       'label' => strtoupper($ext)],
    };
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TugasKu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        body { background:#f5f7fa; color:#333; min-height:100vh; }

        .admin-container { display:flex; min-height:100vh; }

        /* Sidebar */
        .sidebar { width:250px; background:#2d3748; color:white; padding:20px 0; position:fixed; height:100vh; box-shadow:3px 0 15px rgba(0,0,0,0.1); z-index:100; }
        .sidebar-logo { text-align:center; padding:20px; border-bottom:1px solid #4a5568; margin-bottom:20px; }
        .sidebar-logo h1 { font-size:22px; margin-bottom:8px; }
        .sidebar-logo .role { background:#4299e1; padding:4px 14px; border-radius:20px; font-size:12px; font-weight:600; }
        .nav-menu { list-style:none; padding:0 15px; }
        .nav-item { margin-bottom:5px; }
        .nav-link { display:flex; align-items:center; padding:12px 15px; color:#cbd5e0; text-decoration:none; border-radius:8px; transition:all 0.3s; gap:12px; }
        .nav-link:hover, .nav-link.active { background:#4a5568; color:white; }

        /* Main */
        .main-content { margin-left:250px; flex:1; padding:25px; }
        .admin-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; padding-bottom:20px; border-bottom:2px solid #e2e8f0; }
        .admin-header h2 { color:#2d3748; font-size:26px; }
        .user-info { display:flex; align-items:center; gap:15px; font-size:15px; color:#4a5568; }
        .btn-logout { background:#e53e3e; color:white; border:none; padding:9px 20px; border-radius:8px; cursor:pointer; font-size:14px; display:flex; align-items:center; gap:8px; transition:background 0.3s; }
        .btn-logout:hover { background:#c53030; }

        /* Alert */
        .alert { padding:14px 20px; border-radius:8px; margin-bottom:20px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .alert-success { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; }
        .alert-error   { background:#ffebee; color:#c62828; border:1px solid #ffcdd2; }

        /* Stats */
        .admin-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background:white; border-radius:12px; padding:25px; box-shadow:0 3px 15px rgba(0,0,0,0.07); display:flex; align-items:center; gap:20px; border-left:5px solid #4299e1; }
        .stat-card:nth-child(2) { border-left-color:#48bb78; }
        .stat-card:nth-child(3) { border-left-color:#ed8936; }
        .stat-card:nth-child(4) { border-left-color:#9f7aea; }
        .stat-icon { width:55px; height:55px; border-radius:12px; background:rgba(66,153,225,0.1); display:flex; align-items:center; justify-content:center; font-size:26px; color:#4299e1; flex-shrink:0; }
        .stat-card:nth-child(2) .stat-icon { background:rgba(72,187,120,0.1); color:#48bb78; }
        .stat-card:nth-child(3) .stat-icon { background:rgba(237,137,54,0.1); color:#ed8936; }
        .stat-card:nth-child(4) .stat-icon { background:rgba(159,122,234,0.1); color:#9f7aea; }
        .stat-info h3 { font-size:30px; color:#2d3748; }
        .stat-info p  { font-size:13px; color:#718096; }

        /* Content box */
        .content-box { background:white; border-radius:12px; padding:28px; margin-bottom:25px; box-shadow:0 3px 15px rgba(0,0,0,0.07); }
        .content-box h3 { color:#2d3748; font-size:20px; margin-bottom:22px; padding-bottom:14px; border-bottom:2px solid #e2e8f0; display:flex; align-items:center; gap:10px; }

        /* Form */
        .form-label { display:block; font-weight:600; color:#4a5568; margin-bottom:6px; font-size:15px; }
        .form-label .required { color:#e53e3e; }
        .form-label .hint { font-weight:400; color:#a0aec0; font-size:13px; margin-left:6px; }
        .form-group { margin-bottom:20px; }
        .form-control { width:100%; padding:13px 16px; border:2px solid #e2e8f0; border-radius:10px; font-size:15px; transition:all 0.3s; background:#f8fafc; color:#333; }
        .form-control:focus { border-color:#4299e1; background:#fff; outline:none; box-shadow:0 0 0 3px rgba(66,153,225,0.15); }
        textarea.form-control { resize:vertical; }

        /* Upload area */
        .upload-area { border:2px dashed #cbd5e0; border-radius:10px; padding:40px 20px; text-align:center; cursor:pointer; transition:all 0.3s; background:#f8fafc; position:relative; }
        .upload-area:hover, .upload-area.dragover { border-color:#4299e1; background:#ebf8ff; }
        .upload-area input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
        .upload-icon { font-size:40px; color:#a0aec0; margin-bottom:12px; }
        .upload-area p { color:#718096; font-size:15px; margin-bottom:4px; }
        .upload-area small { color:#a0aec0; font-size:13px; }
        .file-preview { display:none; align-items:center; gap:12px; padding:14px 16px; background:#f0fff4; border:2px solid #9ae6b4; border-radius:10px; margin-top:12px; }
        .file-preview.show { display:flex; }
        .file-preview-icon { font-size:28px; }
        .file-preview-info { flex:1; }
        .file-preview-name { font-weight:600; color:#2d3748; font-size:14px; }
        .file-preview-size { font-size:12px; color:#718096; }
        .file-preview-remove { color:#e53e3e; cursor:pointer; font-size:18px; }

        /* Tombol gambar thumbnail kecil di dalam upload area */
        .img-thumb-preview { max-height:120px; max-width:100%; border-radius:8px; margin-top:10px; display:none; object-fit:cover; border:2px solid #9ae6b4; }

        /* Buttons */
        .btn-primary { background:#4299e1; color:white; border:none; padding:12px 26px; border-radius:10px; cursor:pointer; font-size:15px; font-weight:600; display:inline-flex; align-items:center; gap:8px; transition:all 0.3s; }
        .btn-primary:hover { background:#3182ce; transform:translateY(-1px); }
        .btn-sm { padding:5px 10px; font-size:12px; border-radius:6px; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:4px; }
        .btn-danger  { background:#f56565; color:white; } .btn-danger:hover  { background:#e53e3e; }
        .btn-success { background:#48bb78; color:white; } .btn-success:hover { background:#38a169; }
        .btn-info    { background:#4299e1; color:white; } .btn-info:hover    { background:#3182ce; }

        /* Table */
        .table { width:100%; border-collapse:collapse; }
        .table th, .table td { padding:13px 15px; text-align:left; border-bottom:1px solid #e2e8f0; font-size:14px; }
        .table th { background:#f7fafc; color:#4a5568; font-weight:600; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; }
        .table tr:hover td { background:#f7fafc; }
        .table td:last-child { white-space:nowrap; }

        /* Badge */
        .badge { padding:5px 11px; border-radius:20px; font-size:12px; font-weight:600; white-space:nowrap; }
        .badge-success { background:#c6f6d5; color:#22543d; }
        .badge-warning { background:#feebc8; color:#c05621; }
        .badge-info    { background:#bee3f8; color:#2c5282; }
        .badge-open    { background:#bee3f8; color:#2c5282; }
        .badge-done    { background:#c6f6d5; color:#22543d; }
        .badge-pending { background:#feebc8; color:#c05621; }
        .badge-admin   { background:#e9d8fd; color:#553c9a; }
        .badge-user    { background:#c6f6d5; color:#22543d; }

        /* File chip */
        .file-chip { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none; transition:opacity 0.2s; }
        .file-chip:hover { opacity:0.8; }
        .file-chip.pdf   { background:#fff5f5; color:#c53030; border:1px solid #feb2b2; }
        .file-chip.word  { background:#ebf8ff; color:#2b6cb0; border:1px solid #90cdf4; }
        .file-chip.excel { background:#f0fff4; color:#276749; border:1px solid #9ae6b4; }
        .file-chip.image { background:#faf5ff; color:#6b46c1; border:1px solid #d6bcfa; } /* ungu untuk gambar */
        .file-chip.other { background:#f7fafc; color:#4a5568; border:1px solid #e2e8f0; }
        .no-file { color:#a0aec0; font-size:13px; }

        /* Thumbnail kecil di tabel */
        .img-thumb { width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer; transition:transform 0.2s; }
        .img-thumb:hover { transform:scale(1.1); }

        /* ── Modal Preview ─────────────────────────────────── */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:white; border-radius:16px; width:90%; max-width:860px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
        .modal-header { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:2px solid #e2e8f0; }
        .modal-header h3 { font-size:17px; color:#2d3748; display:flex; align-items:center; gap:10px; }
        .modal-close { font-size:26px; cursor:pointer; color:#718096; line-height:1; transition:color 0.2s; }
        .modal-close:hover { color:#e53e3e; }
        .modal-body { flex:1; overflow:auto; padding:20px; display:flex; align-items:center; justify-content:center; background:#f7fafc; min-height:300px; }
        .modal-body img { max-width:100%; max-height:65vh; border-radius:10px; object-fit:contain; box-shadow:0 4px 20px rgba(0,0,0,0.15); }
        .modal-body iframe { width:100%; height:65vh; border:none; border-radius:8px; }
        .modal-footer { padding:14px 24px; border-top:1px solid #e2e8f0; display:flex; gap:10px; justify-content:flex-end; }
        .modal-no-preview { text-align:center; color:#718096; }
        .modal-no-preview i { font-size:52px; color:#cbd5e0; display:block; margin-bottom:12px; }

        @media (max-width:768px) {
            .sidebar { width:100%; height:auto; position:relative; }
            .main-content { margin-left:0; padding:15px; }
            .admin-stats { grid-template-columns:1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="admin-container">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <h1><i class="fas fa-tasks"></i> TugasKu</h1>
            <p style="color:#a0aec0;font-size:13px;margin-bottom:8px;">Admin Panel</p>
            <span class="role">Administrator</span>
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="admin.php" class="nav-link <?= $section==='dashboard'?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="admin.php?section=todos" class="nav-link <?= $section==='todos'?'active':'' ?>"><i class="fas fa-list-check"></i> Kelola Tugas</a>
            </li>
            <li class="nav-item">
                <a href="admin.php?section=users" class="nav-link <?= $section==='users'?'active':'' ?>"><i class="fas fa-users"></i> Kelola User</a>
            </li>
        </ul>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="admin-header">
            <h2>
                <?= match($section) {
                    'todos' => '<i class="fas fa-list-check"></i> Kelola Tugas',
                    'users' => '<i class="fas fa-users"></i> Kelola User',
                    default => '<i class="fas fa-chart-line"></i> Dashboard Admin',
                } ?>
            </h2>
            <div class="user-info">
                <span><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                <button class="btn-logout" onclick="location.href='admin.php?logout=true'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ======== DASHBOARD ======== -->
        <?php if ($section === 'dashboard'): ?>

            <div class="admin-stats">
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= $stats['total_users'] ?></h3><p>Total User</p></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-tasks"></i></div><div class="stat-info"><h3><?= $stats['total_todos'] ?></h3><p>Total Tugas</p></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?= $stats['completed_todos'] ?></h3><p>Tugas Selesai</p></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-day"></i></div><div class="stat-info"><h3><?= $stats['today_todos'] ?></h3><p>Tugas Hari Ini</p></div></div>
            </div>

            <!-- Tabel tugas terbaru -->
            <div class="content-box">
                <h3><i class="fas fa-history"></i> Tugas Terbaru</h3>
                <table class="table">
                    <thead><tr><th>ID</th><th>Judul</th><th>User</th><th>Status</th><th>Lampiran</th><th>Tanggal</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentTodos)): ?>
                            <tr><td colspan="6" style="text-align:center;padding:30px;color:#a0aec0">Belum ada tugas</td></tr>
                        <?php else: foreach ($recentTodos as $todo):
                            $userName = 'Unknown';
                            foreach ($users as $u) { if (isset($u['id']) && $u['id'] == ($todo['user_id'] ?? 0)) { $userName = $u['name']; break; } }
                            $status = $todo['status'] ?? ($todo['completed'] ? 'done' : 'open');
                            $badgeClass = match($status) { 'done'=>'badge-done','pending'=>'badge-pending', default=>'badge-open' };
                            $statusText = match($status) { 'done'=>'Selesai','pending'=>'Pending', default=>'Open' };
                        ?>
                        <tr>
                            <td>#<?= $todo['id'] ?></td>
                            <td><?= htmlspecialchars($todo['title']) ?></td>
                            <td><?= htmlspecialchars($userName) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $statusText ?></span></td>
                            <td>
                                <?php if (!empty($todo['attachment'])):
                                    $att  = $todo['attachment'];
                                    $ext  = $att['extension'] ?? strtolower(pathinfo($att['original_name'], PATHINFO_EXTENSION));
                                    $chip = getFileChip($ext, $att['type'] ?? '');
                                    $isImg = $att['is_image'] ?? in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp']);
                                ?>
                                    <?php if ($isImg): ?>
                                        <!-- Thumbnail langsung tampil untuk gambar -->
                                        <img src="<?= htmlspecialchars($att['path']) ?>"
                                             class="img-thumb"
                                             alt="<?= htmlspecialchars($att['original_name']) ?>"
                                             onclick="openModal('<?= htmlspecialchars($att['path'], ENT_QUOTES) ?>', '<?= $ext ?>', '<?= htmlspecialchars($att['original_name'], ENT_QUOTES) ?>', true)">
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($att['path']) ?>" target="_blank" class="file-chip <?= $chip['cls'] ?>">
                                            <i class="fas <?= $chip['ico'] ?>"></i> <?= $chip['label'] ?>
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="no-file">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($todo['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tabel user terdaftar -->
            <div class="content-box">
                <h3><i class="fas fa-users"></i> User Terdaftar</h3>
                <table class="table">
                    <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Tanggal Daftar</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $user): $r = $user['role'] ?? 'user'; ?>
                        <tr>
                            <td>#<?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><span class="badge badge-<?= $r ?>"><?= $r==='admin'?'Administrator':'User' ?></span></td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <!-- ======== KELOLA TUGAS ======== -->
        <?php elseif ($section === 'todos'): ?>

            <!-- Form tambah tugas -->
            <div class="content-box">
                <h3><i class="fas fa-plus-circle"></i> Tambah Tugas Baru</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="admin_todo_action" value="add_todo_admin">
                    <div class="form-group">
                        <label class="form-label">Judul Tugas <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label class="form-label">Assign ke User</label>
                            <select name="user_id" class="form-control">
                                <option value="">— Pilih User —</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prioritas</label>
                            <select name="priority" class="form-control">
                                <option value="low">Rendah</option>
                                <option value="medium" selected>Sedang</option>
                                <option value="high">Tinggi</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Lampiran File
                            <span class="hint">(PDF, Word, Excel, JPG, PNG, GIF, WEBP, SVG, BMP, TIFF — maks. 10 MB)</span>
                        </label>
                        <div class="upload-area" id="uploadArea">
                            <!-- Input file menerima dokumen & semua gambar umum -->
                            <input type="file" name="attachment" id="fileInput"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.svg,.bmp,.tiff,.tif"
                                   onchange="previewFile(this)">
                            <div id="uploadPlaceholder">
                                <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                <p>Klik atau seret file ke sini</p>
                                <small>Dokumen (PDF/Word/Excel) atau Gambar (JPG/PNG/GIF/WEBP/SVG/BMP/TIFF) • Maks 10 MB</small>
                            </div>
                        </div>
                        <!-- Preview nama file (semua tipe) -->
                        <div class="file-preview" id="filePreview">
                            <span class="file-preview-icon" id="previewIcon"></span>
                            <div class="file-preview-info">
                                <div class="file-preview-name" id="previewName"></div>
                                <div class="file-preview-size" id="previewSize"></div>
                            </div>
                            <span class="file-preview-remove" onclick="removeFile()"><i class="fas fa-times-circle"></i></span>
                        </div>
                        <!-- Thumbnail gambar langsung tampil sebelum upload -->
                        <img id="imgThumbPreview" class="img-thumb-preview" alt="preview gambar">
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Simpan Tugas</button>
                </form>
            </div>

            <!-- Tabel semua tugas -->
            <div class="content-box">
                <h3><i class="fas fa-list"></i> Semua Tugas</h3>
                <table class="table">
                    <thead>
                        <tr><th>ID</th><th>Judul</th><th>User</th><th>Prioritas</th><th>Status</th><th>Lampiran</th><th>Tanggal</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($todos)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:30px;color:#a0aec0">Belum ada tugas</td></tr>
                    <?php else: foreach ($todos as $todo):
                        $userName = 'Unknown';
                        foreach ($users as $u) { if (isset($u['id']) && $u['id'] == ($todo['user_id'] ?? 0)) { $userName = $u['name']; break; } }
                        $priority  = $todo['priority'] ?? 'medium';
                        $status    = $todo['status']   ?? ($todo['completed'] ? 'done' : 'open');
                        $completed = $todo['completed'] ?? false;
                        $pClass    = match($priority) { 'high'=>'badge-warning','low'=>'badge-success', default=>'badge-info' };
                        $pText     = match($priority) { 'high'=>'Tinggi','low'=>'Rendah', default=>'Sedang' };
                        $sClass    = match($status)   { 'done'=>'badge-done','pending'=>'badge-pending', default=>'badge-open' };
                        $sText     = match($status)   { 'done'=>'Selesai','pending'=>'Pending', default=>'Open' };
                    ?>
                    <tr>
                        <td>#<?= $todo['id'] ?></td>
                        <td><?= htmlspecialchars($todo['title']) ?></td>
                        <td><?= htmlspecialchars($userName) ?></td>
                        <td><span class="badge <?= $pClass ?>"><?= $pText ?></span></td>
                        <td><span class="badge <?= $sClass ?>"><?= $sText ?></span></td>
                        <td>
                            <?php if (!empty($todo['attachment'])):
                                $att   = $todo['attachment'];
                                $ext   = $att['extension'] ?? strtolower(pathinfo($att['original_name'], PATHINFO_EXTENSION));
                                $chip  = getFileChip($ext, $att['type'] ?? '');
                                $isImg = $att['is_image'] ?? in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp','tiff','tif']);
                                $pathEsc = htmlspecialchars($att['path'], ENT_QUOTES);
                                $nameEsc = htmlspecialchars($att['original_name'], ENT_QUOTES);
                            ?>
                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                    <?php if ($isImg): ?>
                                        <!-- Thumbnail kecil gambar, klik untuk buka modal -->
                                        <img src="<?= htmlspecialchars($att['path']) ?>"
                                             class="img-thumb"
                                             alt="<?= $nameEsc ?>"
                                             onclick="openModal('<?= $pathEsc ?>','<?= $ext ?>','<?= $nameEsc ?>',true)">
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($att['path']) ?>" download class="file-chip <?= $chip['cls'] ?>">
                                            <i class="fas <?= $chip['ico'] ?>"></i> <?= htmlspecialchars($att['original_name']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <!-- Tombol preview untuk semua tipe file -->
                                    <button class="btn-info btn-sm"
                                            onclick="openModal('<?= $pathEsc ?>','<?= $ext ?>','<?= $nameEsc ?>',<?= $isImg?'true':'false' ?>)">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                </div>
                            <?php else: ?>
                                <span class="no-file">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($todo['created_at'])) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="todo_id" value="<?= $todo['id'] ?>">
                                <input type="hidden" name="admin_todo_action" value="toggle_todo_admin">
                                <button type="submit" class="btn-success btn-sm"><?= $completed ? '<i class="fas fa-undo"></i>' : '<i class="fas fa-check"></i>' ?></button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="todo_id" value="<?= $todo['id'] ?>">
                                <input type="hidden" name="admin_todo_action" value="delete_todo_admin">
                                <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus tugas ini?')"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <!-- ======== KELOLA USER ======== -->
        <?php elseif ($section === 'users'): ?>
            <div class="content-box">
                <h3><i class="fas fa-users"></i> Daftar User</h3>
                <table class="table">
                    <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Tanggal Daftar</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $user): $r = $user['role'] ?? 'user'; ?>
                        <tr>
                            <td>#<?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><span class="badge badge-<?= $r ?>"><?= $r==='admin'?'Administrator':'User' ?></span></td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="content-box">
                <h3><i class="fas fa-info-circle"></i> Informasi</h3>
                <p style="color:#4a5568;line-height:1.7;">
                    Total ada <strong><?= count($users) ?> user</strong> terdaftar dalam sistem.<br>
                    Admin dapat mengelola semua tugas melalui menu <strong>Kelola Tugas</strong>.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======== MODAL PREVIEW FILE ======== -->
<div class="modal-overlay" id="modalOverlay" onclick="handleOverlayClick(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-eye" id="modalIcon"></i> <span id="modalTitle">Preview File</span></h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Konten preview diisi oleh JavaScript -->
        </div>
        <div class="modal-footer">
            <a id="modalDownload" href="#" download class="btn-primary" style="text-decoration:none;padding:10px 20px;font-size:14px;">
                <i class="fas fa-download"></i> Download
            </a>
            <button onclick="closeModal()" style="padding:10px 20px;border-radius:8px;border:1px solid #e2e8f0;background:white;cursor:pointer;font-size:14px;">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
// ── Upload area drag & drop ───────────────────────────────────
const uploadArea  = document.getElementById('uploadArea');
const fileInput   = document.getElementById('fileInput');
const filePreview = document.getElementById('filePreview');
const placeholder = document.getElementById('uploadPlaceholder');
const imgThumb    = document.getElementById('imgThumbPreview');

const imgExts = ['jpg','jpeg','png','gif','webp','svg','bmp','tiff','tif'];

['dragenter','dragover'].forEach(e => {
    uploadArea?.addEventListener(e, ev => { ev.preventDefault(); uploadArea.classList.add('dragover'); });
});
['dragleave','drop'].forEach(e => {
    uploadArea?.addEventListener(e, ev => { ev.preventDefault(); uploadArea.classList.remove('dragover'); });
});
uploadArea?.addEventListener('drop', ev => {
    const files = ev.dataTransfer.files;
    if (files.length) {
        const dt = new DataTransfer(); dt.items.add(files[0]);
        fileInput.files = dt.files;
        previewFile(fileInput);
    }
});

function previewFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const ext  = file.name.split('.').pop().toLowerCase();
    const isImg = imgExts.includes(ext) || file.type.startsWith('image/');

    const icons = {
        pdf:'📄', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊',
        jpg:'🖼️', jpeg:'🖼️', png:'🖼️', gif:'🎞️', webp:'🖼️',
        svg:'🎨', bmp:'🖼️', tiff:'🖼️', tif:'🖼️',
    };

    document.getElementById('previewIcon').textContent = icons[ext] || '📎';
    document.getElementById('previewName').textContent = file.name;
    document.getElementById('previewSize').textContent = formatBytes(file.size);

    placeholder.style.display = 'none';
    filePreview.classList.add('show');

    // Untuk gambar: tampilkan thumbnail langsung dari memori (sebelum upload)
    if (isImg) {
        const reader = new FileReader();
        reader.onload = e => {
            imgThumb.src = e.target.result;
            imgThumb.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        imgThumb.style.display = 'none';
    }
}

function removeFile() {
    fileInput.value = '';
    filePreview.classList.remove('show');
    placeholder.style.display = 'block';
    imgThumb.style.display = 'none';
    imgThumb.src = '';
}

function formatBytes(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024)    return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
}

// ── Modal Preview ─────────────────────────────────────────────
const modalOverlay = document.getElementById('modalOverlay');
const modalBody    = document.getElementById('modalBody');
const modalTitle   = document.getElementById('modalTitle');
const modalIcon    = document.getElementById('modalIcon');
const modalDL      = document.getElementById('modalDownload');

function openModal(path, ext, name, isImage) {
    modalTitle.textContent = name;
    modalDL.href     = path;
    modalDL.download = name;
    modalBody.innerHTML = '';

    const docExts  = ['doc','docx','xls','xlsx'];
    const pdfExts  = ['pdf'];

    if (isImage) {
        // Gambar: tampilkan langsung sebagai <img>
        modalIcon.className = 'fas fa-file-image';
        const img = document.createElement('img');
        img.src = path;
        img.alt = name;
        modalBody.appendChild(img);

    } else if (pdfExts.includes(ext)) {
        // PDF: buka dalam iframe langsung
        modalIcon.className = 'fas fa-file-pdf';
        modalBody.innerHTML = `<iframe src="${path}"></iframe>`;

    } else if (docExts.includes(ext)) {
        // Word/Excel: gunakan Google Docs Viewer (butuh internet)
        modalIcon.className = ext.includes('xls') ? 'fas fa-file-excel' : 'fas fa-file-word';
        const fullUrl = encodeURIComponent(window.location.origin + '/' + path);
        modalBody.innerHTML = `<iframe src="https://docs.google.com/gview?url=${fullUrl}&embedded=true"></iframe>`;

    } else {
        // Tipe lain: tampilkan pesan tidak bisa preview
        modalIcon.className = 'fas fa-file';
        modalBody.innerHTML = `
            <div class="modal-no-preview">
                <i class="fas fa-file-circle-question"></i>
                <p style="margin-bottom:12px">Preview tidak tersedia untuk tipe file ini.</p>
                <a href="${path}" download="${name}" class="btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:14px;">
                    <i class="fas fa-download"></i> Download File
                </a>
            </div>`;
    }

    modalOverlay.classList.add('open');
    document.body.style.overflow = 'hidden'; // Kunci scroll halaman saat modal terbuka
}

function closeModal() {
    modalOverlay.classList.remove('open');
    modalBody.innerHTML = '';
    document.body.style.overflow = '';
}

// Klik di luar modal box → tutup modal
function handleOverlayClick(e) {
    if (e.target === modalOverlay) closeModal();
}

// Tekan Escape → tutup modal
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>