<?php
session_start();
include 'config.php';

// Cek admin
if(!isset($_SESSION['user_phone']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: auth/login.php");
    exit;
}

// Handle broadcast
if(isset($_POST['send_broadcast'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $target = $_POST['target'];
    
    if(createBroadcastNotification($conn, $title, $message, $target, $_SESSION['user_phone'])) {
        $success = "Notifikasi berhasil dikirim!";
    } else {
        $error = "Gagal mengirim notifikasi";
    }
}

// Handle toggle/delete
if(isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if($_GET['action'] === 'toggle') {
        $isActive = $_GET['status'] === '1' ? 0 : 1;
        updateAdminNotificationStatus($conn, $id, $isActive);
    } elseif($_GET['action'] === 'delete') {
        deleteAdminNotification($conn, $id);
    }
    header("Location: admin-notifications.php");
    exit;
}

$broadcasts = getActiveBroadcastNotifications($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Notifikasi - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container py-5">
        <h2 class="mb-4"><i class="fas fa-bullhorn"></i> Kelola Notifikasi</h2>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Form Broadcast -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-paper-plane"></i> Kirim Notifikasi Broadcast</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Judul</label>
                        <input type="text" name="title" class="form-control" required placeholder="Contoh: Promo Spesial Hari Ini!">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pesan</label>
                        <textarea name="message" class="form-control" rows="4" required placeholder="Tulis pesan notifikasi..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target</label>
                        <select name="target" class="form-select">
                            <option value="all">Semua User</option>
                            <option value="customer">Pelanggan Saja</option>
                            <option value="admin">Admin Saja</option>
                        </select>
                    </div>
                    <button type="submit" name="send_broadcast" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Kirim Notifikasi
                    </button>
                </form>
            </div>
        </div>
        
        <!-- List Broadcast Aktif -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Notifikasi Broadcast Aktif</h5>
            </div>
            <div class="card-body">
                <?php if($broadcasts && count($broadcasts) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Pesan</th>
                                    <th>Target</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($broadcasts as $b): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                                        <td><?= htmlspecialchars(substr($b['message'], 0, 100)) ?>...</td>
                                        <td><span class="badge bg-info"><?= ucfirst($b['target']) ?></span></td>
                                        <td><?= date('d/m/Y H:i', strtotime($b['created_at'])) ?></td>
                                        <td>
                                            <a href="?action=toggle&id=<?= $b['id'] ?>&status=<?= $b['is_active'] ?>" 
                                               class="btn btn-sm btn-<?= $b['is_active'] ? 'warning' : 'success' ?>">
                                                <i class="fas fa-<?= $b['is_active'] ? 'pause' : 'play' ?>"></i>
                                            </a>
                                            <a href="?action=delete&id=<?= $b['id'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Hapus notifikasi ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Belum ada notifikasi broadcast</p>
                <?php endif; ?>
            </div>
        </div>
        
        <a href="dashboard.php" class="btn btn-secondary mt-3">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>