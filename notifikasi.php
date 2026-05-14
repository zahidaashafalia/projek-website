<?php 
include 'config.php'; 

$user_phone = $_SESSION['user_phone'] ?? '';

// Tandai semua notifikasi sudah dibaca
if(isset($_GET['mark_read'])){
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_phone = '$user_phone'");
    header("Location: notifikasi.php");
    exit;
}

// Hapus notifikasi
if(isset($_GET['delete'])){
    $notif_id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM notifications WHERE id = $notif_id AND user_phone = '$user_phone'");
    header("Location: notifikasi.php");
    exit;
}

// Ambil semua notifikasi
$notifications = mysqli_query($conn, "
    SELECT n.*, o.order_number 
    FROM notifications n 
    LEFT JOIN orders o ON n.order_id = o.id 
    WHERE n.user_phone = '$user_phone' 
    ORDER BY n.created_at DESC
");

// Hitung notifikasi belum dibaca
$unread_count = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE user_phone = '$user_phone' AND is_read = 0
"))['count'];

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #121212;
            --bg-card: #1e1e1e;
            --bg-sidebar: #181818;
            --accent: #ff6b35;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --border-color: #2a2a2a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            background: var(--bg-dark); 
            color: var(--text-primary); 
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            padding: 25px 20px;
            position: fixed;
            height: 100vh;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 8px; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 12px;
            transition: 0.3s;
            position: relative;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 107, 53, 0.15);
            color: var(--accent);
        }
        .badge-notif {
            position: absolute;
            right: 15px;
            background: #ff4757;
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent);
        }

        .notif-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--accent);
            transition: 0.3s;
            position: relative;
        }
        .notif-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.2);
        }
        .notif-card.unread {
            background: rgba(255, 107, 53, 0.1);
            border-left-color: #ff4757;
        }
        .notif-card.read {
            opacity: 0.7;
        }

        .notif-icon {
            width: 45px;
            height: 45px;
            background: var(--bg-sidebar);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            color: var(--accent);
        }

        .notif-content {
            flex: 1;
        }
        .notif-title {
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        .notif-message {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .notif-time {
            color: var(--accent);
            font-size: 0.85rem;
            margin-top: 8px;
        }
        .notif-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-small {
            padding: 5px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }
        .btn-read {
            background: var(--accent);
            color: white;
        }
        .btn-delete {
            background: #ff4757;
            color: white;
        }

        .btn-mark-all {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="logo">
        <i class="fas fa-fire"></i>
        <span>Texcer Hot</span>
    </div>
    
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="index.php" class="nav-link">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="riwayat.php" class="nav-link">
                <i class="fas fa-history"></i>
                <span>Order</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="checkout.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="notifikasi.php" class="nav-link active">
                <i class="fas fa-bell"></i>
                <span>Notifikasi</span>
                <?php if($unread_count > 0): ?>
                    <span class="badge-notif"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="admin.php" class="nav-link">
                <i class="fas fa-user-shield"></i>
                <span>Profile</span>
            </a>
        </li>
    </ul>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
    
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-bell me-2"></i>Pembaruan Pesanan
        </h1>
        <?php if($unread_count > 0): ?>
            <a href="?mark_read=1" class="btn-mark-all">
                <i class="fas fa-check-double me-2"></i>Tandai Sudah Dibaca
            </a>
        <?php endif; ?>
    </div>

    <?php if(mysqli_num_rows($notifications) > 0): ?>
        <?php while($notif = mysqli_fetch_assoc($notifications)): ?>
        <div class="notif-card <?= $notif['is_read'] ? 'read' : 'unread' ?>">
            <div style="display: flex; align-items: flex-start;">
                <div class="notif-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                    <div class="notif-message"><?= nl2br(htmlspecialchars($notif['message'])) ?></div>
                    <div class="notif-time">
                        <i class="far fa-clock me-1"></i>
                        <?= date('d M Y, H:i', strtotime($notif['created_at'])) ?>
                    </div>
                    <?php if(!$notif['is_read']): ?>
                    <div class="notif-actions">
                        <a href="notifikasi.php?mark_read=<?= $notif['id'] ?>" class="btn-small btn-read">
                            <i class="fas fa-check me-1"></i>Sudah Dibaca
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="?delete=<?= $notif['id'] ?>" class="btn-small btn-delete" onclick="return confirm('Hapus notifikasi ini?')">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h3>Belum Ada Notifikasi</h3>
            <p>Notifikasi akan muncul ketika ada pembaruan pesanan</p>
        </div>
    <?php endif; ?>

</main>

</body>
</html>