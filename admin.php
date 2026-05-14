<?php 
include 'config.php';
include 'functions.php';

// ✅ UPDATE STATUS + NOTIFIKASI (DIPERBAIKI TOTAL)
if(isset($_POST['update_status'])){
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    
    // 1. Ambil data pesanan
    $order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id = $order_id"));
    
    // 2. Update status di database
    mysqli_query($conn, "UPDATE orders SET status = '$new_status' WHERE id = $order_id");
    
    // 3. Siapkan data untuk notifikasi
    $phone = $order['customer_phone'] ?? '';
    $resi  = $order['resi_number'] ?? '-';
    $total = $order['total_price'] ?? 0;
    
    // 4. Insert Notifikasi (HANYA jika status Dikirim atau Selesai & phone ada)
    if(!empty($phone)){
        if($new_status == 'Dikirim'){
            $pesan = "Paket Anda dengan nomor resi {$resi} sudah dikirim dan akan diantarkan oleh kurir.";
            mysqli_query($conn, "INSERT INTO notifications (user_phone, order_id, title, message, type) VALUES ('$phone', '$order_id', 'Pesanan Dikirim', '$pesan', 'order')");
            
            // Jika bayar COD, tambah notif pembayaran
            if($order['payment_method'] == 'COD'){
                $pesan_cod = "Siapkan dana sebesar Rp " . number_format($total, 0, ',', '.') . " untuk membayar pesanan dengan nomor resi {$resi}.";
                mysqli_query($conn, "INSERT INTO notifications (user_phone, order_id, title, message, type) VALUES ('$phone', '$order_id', 'Pembayaran COD', '$pesan_cod', 'order')");
            }
        } 
        elseif($new_status == 'Selesai'){
            $pesan = "Paket dengan nomor resi {$resi} telah sampai. Terima kasih sudah berbelanja di Texcer Hot! 🎉";
            mysqli_query($conn, "INSERT INTO notifications (user_phone, order_id, title, message, type) VALUES ('$phone', '$order_id', 'Pesanan Selesai', '$pesan', 'order')");
        }
    } else {
        // Debug: jika phone kosong, notif tidak akan masuk
        echo "<script>alert('Status diupdate, tapi nomor HP customer kosong. Notifikasi tidak terkirim.'); window.location='admin.php';</script>";
        exit;
    }
    
    // 5. Redirect sukses
    echo "<script>alert('Status berhasil diupdate & notifikasi terkirim!'); window.location='admin.php';</script>";
    exit;
}

$orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC");

// Variabel untuk sidebar active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* === DESKTOP DARK THEME === */
        :root {
            --bg-dark: #121212;
            --bg-card: #1e1e1e;
            --bg-sidebar: #181818;
            --accent: #ff6b35;
            --accent-hover: #ff8555;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --border-color: #2a2a2a;
            --shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            background: var(--bg-dark); 
            color: var(--text-primary); 
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            min-height: 100vh;
        }

        /* === SIDEBAR === */
        .sidebar {
            width: 250px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            padding: 25px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
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
        .logo i { font-size: 1.8rem; }
        
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
            font-weight: 500;
            position: relative;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 107, 53, 0.15);
            color: var(--accent);
        }
        .nav-link i { width: 20px; text-align: center; }
        .cart-badge {
            position: absolute;
            top: 8px;
            right: 15px;
            background: var(--accent);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* === MAIN CONTENT === */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        /* === ADMIN HEADER === */
        .admin-header {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            padding: 25px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(255, 107, 53, 0.3);
        }
        .admin-header h2 { 
            color: white; 
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .admin-header p { 
            color: rgba(255,255,255,0.9); 
            font-size: 0.95rem;
        }
        
        /* === STATS === */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 14px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
        }
        .stat-number { 
            font-size: 2rem; 
            font-weight: 700; 
            color: var(--accent);
            line-height: 1;
        }
        .stat-label { 
            color: var(--text-secondary); 
            font-size: 0.85rem; 
            margin-top: 8px;
        }
        
        /* === ORDER CARD === */
        .order-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        .order-id {
            color: var(--accent);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        .order-customer {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending { background: #4a3800; color: #ffc107; }
        .status-diproses { background: #004a4a; color: #00e5ff; }
        .status-dikirim { background: #003366; color: #4dabf7; }
        .status-selesai { background: #004d00; color: #51cf66; }
        .status-dibatalkan { background: #4d0000; color: #ff6b6b; }
        
        /* Order Items */
        .order-items {
            margin-bottom: 20px;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            color: var(--text-secondary);
            font-size: 0.95rem;
            border-bottom: 1px dashed var(--border-color);
        }
        .item-row:last-child { border-bottom: none; }
        .item-name { color: var(--text-primary); }
        .item-price { color: var(--accent); font-weight: 600; }
        
        /* Order Footer */
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        .order-total {
            font-size: 1.1rem;
        }
        .order-total .label { color: var(--text-secondary); font-size: 0.9rem; }
        .order-total .amount { color: var(--accent); font-weight: 700; font-size: 1.3rem; }
        
        /* Status Form */
        .status-form {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .status-select {
            background: var(--bg-sidebar);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .status-select:focus {
            outline: none;
            border-color: var(--accent);
        }
        .btn-update {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-update:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
            color: var(--accent);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            .logo span, .nav-link span { display: none; }
            .nav-link { justify-content: center; padding: 15px; }
            .nav-link i { margin: 0; }
            .main-content { margin-left: 80px; }
        }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                display: flex;
                justify-content: space-around;
                padding: 15px;
            }
            .logo { display: none; }
            .nav-menu { display: flex; gap: 5px; }
            .nav-item { margin: 0; }
            .nav-link { padding: 10px 15px; flex-direction: column; gap: 5px; font-size: 0.75rem; }
            .nav-link i { font-size: 1.2rem; }
            .main-content { margin-left: 0; padding: 20px; }
            .order-header { flex-direction: column; gap: 10px; align-items: flex-start; }
            .order-footer { flex-direction: column; gap: 15px; align-items: flex-start; }
            .status-form { width: 100%; }
            .status-select { flex: 1; }
            .btn-update { width: 100%; justify-content: center; }
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
            <a href="index.php" class="nav-link <?= $currentPage == 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="riwayat.php" class="nav-link <?= $currentPage == 'riwayat.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i>
                <span>Order</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="checkout.php" class="nav-link <?= $currentPage == 'checkout.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
                <?php if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                    <span class="cart-badge"><?= count($_SESSION['cart']) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <!-- ✅ MENU NOTIFIKASI -->
        <li class="nav-item">
            <a href="notifikasi.php" class="nav-link <?= $currentPage == 'notifikasi.php' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i>
                <span>Notifikasi</span>
                <?php
                $user_phone = $_SESSION['user_phone'] ?? '';
                if(!empty($user_phone)){
                    $unread = mysqli_fetch_assoc(mysqli_query($conn, "
                        SELECT COUNT(*) as count FROM notifications 
                        WHERE user_phone = '$user_phone' AND is_read = 0
                    "))['count'];
                    if($unread > 0):
                ?>
                    <span class="cart-badge" style="background: #ff4757;"><?= $unread ?></span>
                <?php endif; } ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="admin.php" class="nav-link <?= $currentPage == 'admin.php' ? 'active' : '' ?>">
                <i class="fas fa-user-shield"></i>
                <span>Profile</span>
            </a>
        </li>
    </ul>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
    
    <!-- Admin Header -->
    <div class="admin-header">
        <h2><i class="fas fa-user-shield me-2"></i>Admin Panel</h2>
        <p>Kelola Pesanan Texcer Hot</p>
    </div>
    
    <!-- Stats -->
    <div class="stats-row">
        <?php
        $pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='Pending'"));
        $diproses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='Diproses'"));
        $dikirim = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='Dikirim'"));
        $selesai = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='Selesai'"));
        ?>
        <div class="stat-card">
            <div class="stat-number"><?= $pending['count'] ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $diproses['count'] ?></div>
            <div class="stat-label">Diproses</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $dikirim['count'] ?></div>
            <div class="stat-label">Dikirim</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $selesai['count'] ?></div>
            <div class="stat-label">Selesai</div>
        </div>
    </div>
    
    <!-- Orders List -->
    <h4 style="margin-bottom: 20px; font-weight: 600;">Daftar Pesanan</h4>
    
    <?php if(mysqli_num_rows($orders) > 0): ?>
        <?php while($o = mysqli_fetch_assoc($orders)): ?>
            <?php $items = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = {$o['id']}"); ?>
            
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <div class="order-id">Order #<?= str_pad($o['id'], 6, '0', STR_PAD_LEFT) ?></div>
                        <div class="order-customer">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($o['customer_name']) ?> 
                            • <i class="fas fa-clock me-1"></i><?= date('d M Y, H:i', strtotime($o['order_date'])) ?>
                        </div>
                    </div>
                    <span class="status-badge status-<?= strtolower($o['status']) ?>"><?= $o['status'] ?></span>
                </div>
                
                <div class="order-items">
                    <?php while($i = mysqli_fetch_assoc($items)): ?>
                    <div class="item-row">
                        <span class="item-name">• <?= htmlspecialchars($i['product_name']) ?> <small class="text-secondary">(x<?= $i['qty'] ?>)</small></span>
                        <span class="item-price">Rp <?= number_format($i['subtotal'], 0, ',', '.') ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="order-footer">
                    <div class="order-total">
                        <div class="label">Total Pesanan</div>
                        <div class="amount">Rp <?= number_format($o['total_price'], 0, ',', '.') ?></div>
                    </div>
                    
                    <form method="POST" class="status-form">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="status" class="status-select">
                            <option value="Pending" <?= $o['status']=='Pending'?'selected':'' ?>>Pending</option>
                            <option value="Diproses" <?= $o['status']=='Diproses'?'selected':'' ?>>Dimasak</option>
                            <option value="Dikirim" <?= $o['status']=='Dikirim'?'selected':'' ?>>Dikirim</option>
                            <option value="Selesai" <?= $o['status']=='Selesai'?'selected':'' ?>>Selesai</option>
                            <option value="Dibatalkan" <?= $o['status']=='Dibatalkan'?'selected':'' ?>>Dibatalkan</option>
                        </select>
                        <button type="submit" name="update_status" class="btn-update">
                            <i class="fas fa-check"></i> Update
                        </button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>Belum ada pesanan</h3>
            <p style="margin: 15px 0;">Pesanan akan muncul di sini ketika ada customer yang memesan</p>
        </div>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Active nav link based on current page
    document.addEventListener('DOMContentLoaded', function() {
        var currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
            if(link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    });
</script>

</body>
</html>