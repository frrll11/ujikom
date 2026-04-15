<?php
// home.php - Dashboard untuk user biasa (tanpa tombol hapus)

session_start();

define('USERS_FILE', 'users.json');
define('TODOS_FILE', 'todos.json');
define('UPLOAD_DIR', 'uploads/');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

function getTodos() {
    if (file_exists(TODOS_FILE)) {
        $data = file_get_contents(TODOS_FILE);
        $result = json_decode($data, true);
        return is_array($result) ? $result : [];
    }
    return [];
}

function saveTodos($todos) {
    file_put_contents(TODOS_FILE, json_encode(array_values($todos), JSON_PRETTY_PRINT));
}

// Proteksi halaman
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') { header("Location: admin.php"); exit; }
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

$success = '';
$error   = '';

// ── Tipe file yang diizinkan ──────────────────────────────────
$allowedDocTypes = [
    'application/pdf',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$allowedDocExts  = ['pdf', 'xls', 'xlsx', 'doc', 'docx'];

$allowedImgTypes = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml','image/bmp','image/tiff'];
$allowedImgExts  = ['jpg','jpeg','png','gif','webp','svg','bmp','tiff','tif'];

$allowedTypes = array_merge($allowedDocTypes, $allowedImgTypes);
$allowedExts  = array_merge($allowedDocExts, $allowedImgExts);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['todo_action'])) {
    $action = $_POST['todo_action'];
    $todos  = getTodos();

    // ── TAMBAH TUGAS ─────────────────────────────────────────
    if ($action === 'add_todo') {
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'open';

        if (empty($title)) {
            $error = 'Judul tugas wajib diisi!';
        } else {
            $file_info = null;

            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $orig_name = $_FILES['attachment']['name'];
                $tmp_path  = $_FILES['attachment']['tmp_name'];
                $file_size = $_FILES['attachment']['size'];
                $finfo     = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $tmp_path);
                finfo_close($finfo);
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                $isImage = in_array($mime_type, $allowedImgTypes) || in_array($ext, $allowedImgExts);
                $isDoc   = in_array($mime_type, $allowedDocTypes) || in_array($ext, $allowedDocExts);

                if (!$isImage && !$isDoc) {
                    $error = 'File tidak didukung. Gunakan PDF, Excel, Word, JPG, PNG, GIF, WEBP, SVG, BMP, atau TIFF.';
                } elseif ($file_size > 10 * 1024 * 1024) {
                    $error = 'Ukuran file maksimal 10 MB.';
                } else {
                    $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
                    $dest      = UPLOAD_DIR . $safe_name;
                    if (move_uploaded_file($tmp_path, $dest)) {
                        $file_info = [
                            'original_name' => $orig_name,
                            'saved_name'    => $safe_name,
                            'path'          => $dest,
                            'size'          => $file_size,
                            'type'          => $mime_type,
                            'extension'     => $ext,
                            'is_image'      => $isImage,
                        ];
                    } else {
                        $error = 'Gagal menyimpan file. Coba lagi.';
                    }
                }
            }

            if (empty($error)) {
                $new_todo = [
                    'id'          => time() . rand(100, 999),
                    'user_id'     => $_SESSION['user_id'],
                    'title'       => $title,
                    'description' => $desc,
                    'status'      => $status,
                    'priority'    => 'medium',
                    'completed'   => ($status === 'done'),
                    'attachment'  => $file_info,
                    'created_at'  => date('Y-m-d H:i:s'),
                ];
                $todos[] = $new_todo;
                saveTodos($todos);
                $success = 'Tugas berhasil ditambahkan!';
            }
        }

    // ── UPDATE STATUS ─────────────────────────────────────────
    } elseif ($action === 'update_status') {
        $todoId    = $_POST['todo_id'];
        $newStatus = $_POST['new_status'];
        if (in_array($newStatus, ['open','in_progress','done'])) {
            foreach ($todos as &$todo) {
                if ($todo['id'] == $todoId && $todo['user_id'] == $_SESSION['user_id']) {
                    $todo['status']    = $newStatus;
                    $todo['completed'] = ($newStatus === 'done');
                    break;
                }
            }
            saveTodos($todos);
            $success = 'Status tugas diperbarui!';
        }
    }
}

$todos     = getTodos();
$userTodos = array_filter($todos, fn($todo) => isset($todo['user_id']) && $todo['user_id'] == $_SESSION['user_id']);

$totalTodos    = count($userTodos);
$openTodos     = count(array_filter($userTodos, fn($t) => ($t['status'] ?? 'open') === 'open'));
$progressTodos = count(array_filter($userTodos, fn($t) => ($t['status'] ?? '') === 'in_progress'));
$doneTodos     = count(array_filter($userTodos, fn($t) => ($t['status'] ?? '') === 'done'));

function statusLabel($s) { return match($s) { 'in_progress'=>'In Progress','done'=>'Done', default=>'Open' }; }
function statusClass($s) { return match($s) { 'in_progress'=>'status-progress','done'=>'status-done', default=>'status-open' }; }

function formatSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1024, 0) . ' KB';
}

function getFileChip($ext, $mime = '') {
    $ext = strtolower($ext);
    $imgExts = ['jpg','jpeg','png','gif','webp','svg','bmp','tiff','tif'];
    if (in_array($ext, $imgExts) || strpos($mime, 'image/') === 0) {
        return ['cls'=>'image','ico'=>'fa-file-image'];
    }
    return match($ext) {
        'pdf'        => ['cls'=>'pdf',  'ico'=>'fa-file-pdf'],
        'doc','docx' => ['cls'=>'word', 'ico'=>'fa-file-word'],
        'xls','xlsx' => ['cls'=>'excel','ico'=>'fa-file-excel'],
        default      => ['cls'=>'other','ico'=>'fa-file'],
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - TugasKu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        body { background:#f5f7fa; color:#333; min-height:100vh; }

        .navbar { background:#4a5568; color:white; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 15px rgba(0,0,0,0.1); }
        .logo { display:flex; align-items:center; gap:12px; }
        .logo h1 { font-size:22px; font-weight:600; }
        .user-info { display:flex; align-items:center; gap:15px; }
        .btn-logout { background:#e53e3e; color:white; border:none; padding:8px 20px; border-radius:6px; cursor:pointer; font-size:14px; transition:all 0.3s; display:flex; align-items:center; gap:8px; }
        .btn-logout:hover { background:#c53030; }

        .container { max-width:1100px; margin:0 auto; padding:30px; }

        .alert { padding:14px 18px; border-radius:8px; margin-bottom:22px; font-weight:500; font-size:14px; display:flex; align-items:center; gap:10px; }
        .alert-success { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; }
        .alert-error   { background:#ffebee; color:#c62828; border:1px solid #ffcdd2; }

        .welcome-section { background:white; border-radius:12px; padding:28px 30px; margin-bottom:28px; box-shadow:0 5px 20px rgba(0,0,0,0.07); }
        .welcome-section h2 { color:#4a5568; font-size:26px; margin-bottom:6px; }
        .welcome-section p  { color:#718096; font-size:15px; }

        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:18px; margin-bottom:28px; }
        .stat-card { background:white; border-radius:12px; padding:22px; box-shadow:0 5px 20px rgba(0,0,0,0.07); text-align:center; border-top:4px solid #4299e1; transition:transform 0.2s; }
        .stat-card:hover { transform:translateY(-4px); }
        .stat-card:nth-child(2) { border-top-color:#ed8936; }
        .stat-card:nth-child(3) { border-top-color:#667eea; }
        .stat-card:nth-child(4) { border-top-color:#48bb78; }
        .stat-card h3 { font-size:34px; color:#4299e1; margin-bottom:8px; }
        .stat-card:nth-child(2) h3 { color:#ed8936; }
        .stat-card:nth-child(3) h3 { color:#667eea; }
        .stat-card:nth-child(4) h3 { color:#48bb78; }
        .stat-card p { color:#718096; font-size:14px; }

        .card { background:white; border-radius:12px; padding:28px 30px; margin-bottom:28px; box-shadow:0 5px 20px rgba(0,0,0,0.07); }
        .card h3 { color:#4a5568; margin-bottom:22px; font-size:20px; display:flex; align-items:center; gap:10px; padding-bottom:14px; border-bottom:2px solid #e2e8f0; }

        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:13px; font-weight:600; color:#4a5568; margin-bottom:6px; }
        .form-control { width:100%; padding:12px 14px; border:2px solid #e2e8f0; border-radius:8px; font-size:15px; transition:border-color 0.2s; background:#f8f9fc; color:#333; }
        .form-control:focus { border-color:#667eea; outline:none; background:#fff; box-shadow:0 0 0 3px rgba(102,126,234,0.12); }
        textarea.form-control { resize:vertical; }

        .file-upload-area { border:2px dashed #e2e8f0; border-radius:8px; padding:30px 20px; text-align:center; cursor:pointer; transition:all 0.2s; background:#f8f9fc; }
        .file-upload-area:hover, .file-upload-area.drag-over { border-color:#667eea; background:#f0f0ff; }
        .file-upload-area i { font-size:36px; color:#a0aec0; margin-bottom:10px; display:block; }
        .file-upload-area p { color:#718096; font-size:14px; margin-bottom:4px; }
        .file-upload-area small { color:#a0aec0; font-size:12px; }
        #attachment { display:none; }
        #file-preview { margin-top:10px; padding:12px 16px; background:#f0fff4; border:1px solid #9ae6b4; border-radius:8px; font-size:13px; color:#276749; display:none; align-items:center; gap:10px; }
        #file-preview .remove-file { margin-left:auto; cursor:pointer; color:#e53e3e; font-size:18px; }
        #imgThumbPreview { max-height:130px; max-width:100%; border-radius:8px; margin-top:10px; display:none; object-fit:cover; border:2px solid #9ae6b4; }

        .btn-primary { background:#4299e1; color:white; border:none; padding:11px 24px; border-radius:8px; cursor:pointer; font-size:15px; font-weight:600; transition:all 0.3s; display:inline-flex; align-items:center; gap:8px; }
        .btn-primary:hover { background:#3182ce; transform:translateY(-1px); }

        .badge { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-open     { background:#ebf8ff; color:#2b6cb0; }
        .status-progress { background:#fffbeb; color:#b7791f; }
        .status-done     { background:#f0fff4; color:#276749; }

        .todo-item { border:1px solid #e2e8f0; border-radius:10px; padding:18px 20px; margin-bottom:14px; transition:all 0.2s; background:white; }
        .todo-item:hover { border-color:#667eea; box-shadow:0 3px 12px rgba(102,126,234,0.1); }
        .todo-item.is-done { background:#f0fff4; border-color:#9ae6b4; }
        .todo-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .todo-title { font-weight:600; color:#4a5568; font-size:17px; margin-bottom:6px; }
        .todo-title.done { text-decoration:line-through; color:#a0aec0; }
        .todo-description { color:#718096; font-size:14px; margin-bottom:8px; line-height:1.5; }
        .todo-meta { font-size:12px; color:#a0aec0; display:flex; align-items:center; gap:5px; }

        .todo-file { display:inline-flex; align-items:center; gap:8px; margin-top:10px; padding:8px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; text-decoration:none; color:#4a5568; background:#f8f9fc; transition:all 0.2s; font-weight:500; }
        .todo-file:hover { background:#eef0ff; border-color:#667eea; }
        .todo-file.pdf   { border-color:#feb2b2; background:#fff5f5; color:#c53030; }
        .todo-file.word  { border-color:#90cdf4; background:#ebf8ff; color:#2b6cb0; }
        .todo-file.excel { border-color:#9ae6b4; background:#f0fff4; color:#276749; }
        .todo-file.image { border-color:#d6bcfa; background:#faf5ff; color:#6b46c1; }
        .todo-file.other { border-color:#e2e8f0; background:#f7fafc; color:#4a5568; }

        .todo-img-thumb { width:60px; height:60px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0; cursor:pointer; margin-top:10px; transition:transform 0.2s; display:block; }
        .todo-img-thumb:hover { transform:scale(1.08); }

        .status-select { padding:7px 12px; border-radius:6px; border:1px solid #e2e8f0; font-size:13px; cursor:pointer; background:#f8f9fc; color:#4a5568; }
        .status-select:focus { outline:none; border-color:#667eea; }

        .todo-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:14px; padding-top:14px; border-top:1px solid #f0f0f0; }
        .btn-action { padding:7px 14px; border:none; border-radius:6px; cursor:pointer; font-size:13px; font-weight:500; transition:all 0.2s; display:inline-flex; align-items:center; gap:5px; }
        .btn-save   { background:#667eea; color:white; } .btn-save:hover   { background:#5a67d8; }
        .btn-preview { background:#4299e1; color:white; } .btn-preview:hover { background:#3182ce; }

        .no-todos { text-align:center; color:#a0aec0; padding:50px 20px; font-size:15px; }
        .no-todos i { font-size:44px; margin-bottom:14px; color:#cbd5e0; display:block; }

        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:white; border-radius:16px; width:92%; max-width:880px; max-height:92vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
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
            .stats { grid-template-columns:1fr 1fr; }
            .navbar { flex-direction:column; gap:12px; padding:15px; }
            .container { padding:16px; }
        }
        @media (max-width:480px) {
            .stats { grid-template-columns:1fr; }
            .todo-top { flex-direction:column; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo"><i class="fas fa-tasks"></i><h1>TugasKu</h1></div>
    <div class="user-info">
        <span><i class="fas fa-user-circle"></i> Halo, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>!</span>
        <button class="btn-logout" onclick="location.href='home.php?logout=true'">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</nav>

<div class="container">

    <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="welcome-section">
        <h2>Selamat Datang, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>!</h2>
        <p>Kelola tugas harian Anda dengan mudah dan efisien.</p>
    </div>

    <div class="stats">
        <div class="stat-card"><h3><?= $totalTodos ?></h3><p>Total Tugas</p></div>
        <div class="stat-card"><h3><?= $openTodos ?></h3><p>Open</p></div>
        <div class="stat-card"><h3><?= $progressTodos ?></h3><p>In Progress</p></div>
        <div class="stat-card"><h3><?= $doneTodos ?></h3><p>Done</p></div>
    </div>

    <div class="card">
        <h3><i class="fas fa-plus-circle"></i> Tambah Tugas Baru</h3>
        <form method="POST" action="home.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="todo_action" value="add_todo">
            <div class="form-group">
                <label>Judul Tugas <span style="color:#e53e3e">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="Masukkan judul tugas" required>
            </div>
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Deskripsi tugas (opsional)"></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="done">Done</option>
                </select>
            </div>
            <div class="form-group">
                <label>Lampiran File <small style="color:#a0aec0">(Dokumen atau Gambar — maks. 10 MB)</small></label>
                <div class="file-upload-area" id="dropzone" onclick="document.getElementById('attachment').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Klik atau seret file ke sini</p>
                    <small>PDF / Word / Excel &nbsp;|&nbsp; JPG / PNG / GIF / WEBP / SVG / BMP / TIFF • Maks 10 MB</small>
                </div>
                <input type="file" id="attachment" name="attachment"
                       accept=".pdf,.xls,.xlsx,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,.svg,.bmp,.tiff,.tif">
                <div id="file-preview">
                    <i class="fas fa-file-check"></i>
                    <span id="file-name">-</span>
                    <span class="remove-file" onclick="removeFile()"><i class="fas fa-times-circle"></i></span>
                </div>
                <img id="imgThumbPreview" alt="preview">
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Simpan Tugas</button>
        </form>
    </div>

    <div class="card">
        <h3><i class="fas fa-list-check"></i> Daftar Tugas Anda</h3>

        <?php if (empty($userTodos)): ?>
            <div class="no-todos">
                <i class="fas fa-clipboard-list"></i>
                <p>Belum ada tugas. Tambahkan tugas pertama Anda!</p>
            </div>
        <?php else: ?>
            <?php foreach ($userTodos as $todo):
                $status = $todo['status'] ?? 'open';
                $isDone = $status === 'done';
                $file   = $todo['attachment'] ?? null;
                $filePath = null;
                if ($file) {
                    $filePath = $file['path'] ?? (!empty($file['saved_name']) ? UPLOAD_DIR . $file['saved_name'] : null);
                }
                $fileExt   = $file ? strtolower($file['extension'] ?? pathinfo($file['original_name'] ?? '', PATHINFO_EXTENSION)) : '';
                $isImg     = $file['is_image'] ?? in_array($fileExt, ['jpg','jpeg','png','gif','webp','svg','bmp','tiff','tif']);
                $chip      = $file ? getFileChip($fileExt, $file['type'] ?? '') : [];
                $pathEsc   = htmlspecialchars($filePath ?? '', ENT_QUOTES);
                $nameEsc   = htmlspecialchars($file['original_name'] ?? '', ENT_QUOTES);
            ?>
            <div class="todo-item <?= $isDone ? 'is-done' : '' ?>">
                <div class="todo-top">
                    <div style="flex:1">
                        <div class="todo-title <?= $isDone ? 'done' : '' ?>"><?= htmlspecialchars($todo['title'] ?? '') ?></div>
                        <?php if (!empty($todo['description'])): ?>
                            <div class="todo-description"><?= nl2br(htmlspecialchars($todo['description'])) ?></div>
                        <?php endif; ?>
                        <div class="todo-meta"><i class="fas fa-clock"></i> <?= date('d M Y, H:i', strtotime($todo['created_at'] ?? 'now')) ?></div>

                        <?php if ($file && $filePath): ?>
                            <?php if ($isImg): ?>
                                <img src="<?= htmlspecialchars($filePath) ?>"
                                     class="todo-img-thumb"
                                     alt="<?= $nameEsc ?>"
                                     onclick="openModal('<?= $pathEsc ?>','<?= $fileExt ?>','<?= $nameEsc ?>',true)">
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($filePath) ?>"
                                   class="todo-file <?= $chip['cls'] ?>"
                                   target="_blank"
                                   download="<?= $nameEsc ?>">
                                    <i class="fas <?= $chip['ico'] ?>"></i>
                                    <?= htmlspecialchars($file['original_name'] ?? 'file') ?>
                                    <span style="opacity:0.6;font-weight:400">(<?= formatSize($file['size'] ?? 0) ?>)</span>
                                </a>
                            <?php endif; ?>
                            <button class="btn-action btn-preview" style="margin-top:8px;"
                                    onclick="openModal('<?= $pathEsc ?>','<?= $fileExt ?>','<?= $nameEsc ?>',<?= $isImg?'true':'false' ?>)">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="badge <?= statusClass($status) ?>">
                            <?php if ($status === 'open'): ?><i class="fas fa-circle-dot"></i>
                            <?php elseif ($status === 'in_progress'): ?><i class="fas fa-spinner"></i>
                            <?php else: ?><i class="fas fa-check-circle"></i><?php endif; ?>
                            <?= statusLabel($status) ?>
                        </span>
                    </div>
                </div>

                <div class="todo-actions">
                    <form method="POST" action="home.php" style="display:flex;align-items:center;gap:6px;">
                        <input type="hidden" name="todo_action" value="update_status">
                        <input type="hidden" name="todo_id" value="<?= $todo['id'] ?>">
                        <select name="new_status" class="status-select">
                            <option value="open"        <?= $status==='open'        ?'selected':'' ?>>Open</option>
                            <option value="in_progress" <?= $status==='in_progress' ?'selected':'' ?>>In Progress</option>
                            <option value="done"        <?= $status==='done'        ?'selected':'' ?>>Done</option>
                        </select>
                        <button type="submit" class="btn-action btn-save"><i class="fas fa-sync-alt"></i> Ubah Status</button>
                    </form>
                    <!-- TOMBOL HAPUS TELAH DIHAPUS -->
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="modalOverlay" onclick="handleOverlayClick(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-eye" id="modalIcon"></i> <span id="modalTitle">Preview File</span></h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="modalBody"></div>
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
const dropzone    = document.getElementById('dropzone');
const fileInput   = document.getElementById('attachment');
const filePreview = document.getElementById('file-preview');
const fileNameEl  = document.getElementById('file-name');
const imgThumb    = document.getElementById('imgThumbPreview');
const imgExts     = ['jpg','jpeg','png','gif','webp','svg','bmp','tiff','tif'];

fileInput.addEventListener('change', () => showPreview(fileInput.files[0]));

dropzone.addEventListener('dragover',  e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer(); dt.items.add(file);
        fileInput.files = dt.files;
        showPreview(file);
    }
});

function showPreview(file) {
    if (!file) return;
    const ext   = file.name.split('.').pop().toLowerCase();
    const isImg = imgExts.includes(ext) || file.type.startsWith('image/');
    const size  = file.size > 1048576 ? (file.size/1048576).toFixed(1)+' MB' : Math.round(file.size/1024)+' KB';

    fileNameEl.textContent     = file.name + ' (' + size + ')';
    filePreview.style.display  = 'flex';
    dropzone.style.display     = 'none';

    if (isImg) {
        const reader = new FileReader();
        reader.onload = e => { imgThumb.src = e.target.result; imgThumb.style.display = 'block'; };
        reader.readAsDataURL(file);
    } else {
        imgThumb.style.display = 'none';
    }
}

function removeFile() {
    fileInput.value = '';
    filePreview.style.display = 'none';
    dropzone.style.display    = 'block';
    fileNameEl.textContent    = '-';
    imgThumb.style.display    = 'none';
    imgThumb.src              = '';
}

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

    if (isImage) {
        modalIcon.className = 'fas fa-file-image';
        const img = document.createElement('img');
        img.src = path; img.alt = name;
        modalBody.appendChild(img);

    } else if (ext === 'pdf') {
        modalIcon.className = 'fas fa-file-pdf';
        modalBody.innerHTML = `<iframe src="${path}"></iframe>`;

    } else if (['doc','docx','xls','xlsx'].includes(ext)) {
        modalIcon.className = ext.includes('xls') ? 'fas fa-file-excel' : 'fas fa-file-word';
        const full = encodeURIComponent(window.location.origin + '/' + path);
        modalBody.innerHTML = `<iframe src="https://docs.google.com/gview?url=${full}&embedded=true"></iframe>`;

    } else {
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
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    modalOverlay.classList.remove('open');
    modalBody.innerHTML = '';
    document.body.style.overflow = '';
}

function handleOverlayClick(e) { if (e.target === modalOverlay) closeModal(); }

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>