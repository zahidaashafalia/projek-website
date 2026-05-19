<?php
include 'config.php';

// Ambil data pesanan dari database
$orders = [];
if(isset($_SESSION['user_phone'])){
    $phone = $_SESSION['user_phone'];
    // Pastikan kolom payment_method ada di tabel orders
    $result = mysqli_query($conn, "SELECT * FROM orders WHERE customer_phone = '$phone' ORDER BY id DESC");
    while($row = mysqli_fetch_assoc($result)){
        $items = [];
        
        // Coba ambil dari tabel order_items (Lebih stabil daripada JSON)
        $itemsResult = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = {$row['id']}");
        if($itemsResult){
            while($item = mysqli_fetch_assoc($itemsResult)){
                $items[] = $item;
            }
        }
        
        $row['items'] = $items;
        $orders[] = $row;
    }
}

$currentPage = 'riwayat.php';
$activeFilter = $_GET['filter'] ?? 'semua';

// Filter orders
$filteredOrders = array_filter($orders, function($o) use ($activeFilter) {
    $status = $o['status'] ?? '';
    if($activeFilter === 'semua') return true;
    if($activeFilter === 'perlu_dibayar') return in_array($status, ['Menunggu Pembayaran', 'Pending', 'Menunggu Konfirmasi']);
    if($activeFilter === 'dikirim') return $status === 'Dikirim';
    if($activeFilter === 'diterima') return $status === 'Selesai';
    if($activeFilter === 'dibatalkan') return $status === 'Dibatalkan';
    return true;
});

// Status config
function getStatusConfig($status) {
    return match($status){
        'Selesai'               => ['label' => 'Pesanan selesai',         'color' => '#4CAF50', 'icon' => 'fa-check-circle',   'badge' => '#4CAF50'],
        'Dikirim'               => ['label' => 'Sedang dikirim',          'color' => '#2196F3', 'icon' => 'fa-shipping-fast',  'badge' => '#2196F3'],
        'Menunggu Konfirmasi'   => ['label' => 'Menunggu konfirmasi',     'color' => '#FF9800', 'icon' => 'fa-clock',          'badge' => '#FF9800'],
        'Diproses'              => ['label' => 'Pesanan diproses',        'color' => '#9C27B0', 'icon' => 'fa-cog',            'badge' => '#9C27B0'],
        'Dibatalkan'            => ['label' => 'Pesanan dibatalkan',      'color' => '#F44336', 'icon' => 'fa-times-circle',   'badge' => '#F44336'],
        'Menunggu Pembayaran'   => ['label' => 'Perlu dibayar',           'color' => '#FF5722', 'icon' => 'fa-money-bill',     'badge' => '#FF5722'],
        'Pending'               => ['label' => 'Pending',                 'color' => '#FF9800', 'icon' => 'fa-clock',          'badge' => '#FF9800'],
        default                 => ['label' => $status ?? 'Unknown',      'color' => '#9E9E9E', 'icon' => 'fa-question-circle','badge' => '#9E9E9E'],
    };
}

function getStatusMessage($status, $resi) {
    return match($status){
        'Selesai'             => 'Paket Anda telah diterima.',
        'Dikirim'             => 'Paket sedang dalam perjalanan.' . ($resi ? ' No. Resi: ' . $resi : ''),
        'Menunggu Konfirmasi' => 'Pesanan Anda menunggu konfirmasi.',
        'Diproses'            => 'Pesanan Anda sedang diproses.',
        'Dibatalkan'          => 'Pengantaran paket Anda dibatalkan.',
        'Menunggu Pembayaran' => 'Silakan lakukan pembayaran.',
        'Pending'             => 'Pesanan sedang diproses.',
        default               => 'Status pesanan tidak diketahui.',
    };
}

function formatTime($datetime) {
    if(empty($datetime) || $datetime === '0000-00-00 00:00:00') return '--:-- --';
    $ts = strtotime($datetime);
    return $ts ? date('h.i A', $ts) : '--:-- --';
}

// Hitung notifikasi
$totalNotifications = 0;
if(isset($_SESSION['user_phone']) && isset($conn)){
    $phone = $_SESSION['user_phone'];
    $notifQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE customer_phone = '$phone' AND status IN ('Menunggu Konfirmasi', 'Dikirim')");
    if($notifQuery){
        $result = mysqli_fetch_assoc($notifQuery);
        $totalNotifications = $result['total'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Pesanan - Texcer Hot</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root {
    --primary: #8B6F4E;
    --primary-dark: #6B5637;
    --secondary: #D4A574;
    --accent: #E8B4A2;
    --bg-cream: #FDF8F3;
    --bg-white: #FFFFFF;
    --text-dark: #3D2914;
    --text-gray: #8B7355;
    --border: #E8DDD4;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { background: #f5f5f5; color: var(--text-dark); font-family: 'Segoe UI', system-ui, sans-serif; }

/* Top Nav */
.top-nav {
    background: var(--bg-white);
    padding: 20px 40px;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.nav-container {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.logo {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
    text-decoration: none;
}
.nav-menu {
    display: flex;
    gap: 40px;
    list-style: none;
}
.nav-menu a {
    color: var(--text-gray);
    text-decoration: none;
    font-weight: 500;
}
.nav-menu a.active { color: var(--primary); }
.nav-icons { display: flex; gap: 20px; align-items: center; }
.nav-icons a { color: var(--text-dark); font-size: 1.2rem; position: relative; }
.cart-count, .notif-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--accent);
    color: white;
    font-size: 0.7rem;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.notif-count { background: #dc3545; }

/* Filter Tabs */
.filter-tabs {
    background: var(--bg-white);
    border-bottom: 1px solid var(--border);
    overflow-x: auto;
}
.tabs-inner {
    max-width: 800px;
    margin: 0 auto;
    display: flex;
}
.tab-link {
    padding: 13px 18px;
    color: var(--text-gray);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    white-space: nowrap;
}
.tab-link.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

/* Main Content */
.main-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 14px 0 40px;
}

/* Order Card */
.order-card {
    background: var(--bg-white);
    margin-bottom: 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}
.order-header {
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid var(--border);
    background: #fafafa;
}
.store-name {
    font-weight: 700;
    color: var(--text-dark);
}
.mall-badge {
    background: var(--primary);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.68rem;
    margin-right: 8px;
}
.status-badge {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.82rem;
    font-weight: 600;
}

/* Tracking */
.tracking-row {
    padding: 10px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--border);
}
.tracking-icon {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.tracking-text { flex: 1; }
.tracking-time { font-weight: 700; font-size: 0.82rem; }
.tracking-desc { font-size: 0.8rem; color: var(--text-gray); }

/* Product */
.product-row {
    padding: 12px 18px;
    display: flex;
    gap: 12px;
    border-bottom: 1px solid var(--border);
}
.product-row:last-child { border-bottom: none; }
.product-thumb {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--border);
    background: #eee;
}
.product-thumb-placeholder {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-cream);
    color: var(--primary);
}
.product-detail { flex: 1; }
.product-name {
    font-weight: 500;
    margin-bottom: 4px;
    font-size: 0.95rem;
}
.product-variant {
    font-size: 0.78rem;
    color: var(--text-gray);
    margin-bottom: 5px;
}
.product-price-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.88rem;
    color: var(--text-dark);
}

/* Footer */
.order-footer {
    padding: 12px 18px;
    background: #fdfdfd;
}
.order-total {
    text-align: right;
    margin-bottom: 12px;
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--primary);
}
.action-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.btn-buy-again {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 22px;
    border-radius: 20px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-buy-again:hover {
    background: var(--primary-dark);
}
.btn-buy-again:disabled {
    background: #ccc;
    cursor: not-allowed;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}
.empty-state i {
    font-size: 4rem;
    opacity: 0.25;
    margin-bottom: 16px;
    color: var(--primary);
}
</style>
</head>
<body>

<!-- Top Nav -->
<nav class="top-nav">
    <div class="nav-container">
        <a href="index.php" class="logo">Texcer Hot</a>
        <ul class="nav-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php#menu">Menu</a></li>
            <li><a href="riwayat.php" class="active">Pesanan</a></li>
        </ul>
        <div class="nav-icons">
            <button class="nav-icon-btn">
                <i class="fas fa-bell"></i>
                <?php if($totalNotifications > 0): ?>
                <span class="notif-count"><?= $totalNotifications ?></span>
                <?php endif; ?>
            </button>
            <a href="checkout.php">
                <i class="fas fa-shopping-bag"></i>
                <?php if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                <span class="cart-count"><?= count($_SESSION['cart']) ?></span>
                <?php endif; ?>
            </a>
            <?php if(isset($_SESSION['user_phone'])): ?>
            <a href="profile.php"><i class="fas fa-user"></i></a>
            <?php else: ?>
            <a href="auth/login.php"><i class="fas fa-sign-in-alt"></i></a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Filter Tabs -->
<div class="filter-tabs">
    <div class="tabs-inner">
        <?php
        $tabs = [
            'semua' => 'Semua',
            'perlu_dibayar' => 'Perlu dibayar',
            'dikirim' => 'Untuk dikirim',
            'diterima' => 'Akan diterima',
            'dibatalkan' => 'Dibatalkan',
        ];
        foreach($tabs as $key => $label):
        ?>
        <a href="?filter=<?= $key ?>" class="tab-link <?= $activeFilter === $key ? 'active' : '' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">

    <!-- ✅ BONUS BANNER DIHAPUS SESUAI PERMINTAAN -->

    <?php if(empty($filteredOrders)): ?>
    <div class="empty-state">
        <i class="fas fa-receipt"></i>
        <h3>Belum ada pesanan</h3>
        <p>Yuk, pesan makanan pedas favoritmu sekarang!</p>
        <a href="index.php" class="btn-buy-again">Mulai Pesan</a>
    </div>
    <?php else: ?>

    <?php foreach($filteredOrders as $order):
        $cfg = getStatusConfig($order['status'] ?? '');
        $timeStr = formatTime($order['created_at'] ?? '');
        $trackMsg = getStatusMessage($order['status'] ?? '', $order['resi_number'] ?? '');
        $storeName = $order['store_name'] ?? $order['store'] ?? 'Texcer Hot';
        $items = $order['items'] ?? [];
        $status = $order['status'] ?? '';
        
        // ✅ Ambil Metode Pembayaran
        $paymentMethod = $order['payment_method'] ?? 'COD'; 
        
        $isSelesai = $status === 'Selesai';
        $isMenunggu = in_array($status, ['Menunggu Konfirmasi', 'Pending']);
        
        // Tentukan Label & Action Tombol
        $btnText = 'Beli lagi';
        $btnAction = 'window.location.href=\'index.php\'';
        $showPayButton = false;

        if ($isMenunggu) {
            if ($paymentMethod === 'COD') {
                $btnText = 'Menunggu Konfirmasi'; 
                $btnAction = 'void(0)';
                $showPayButton = false; 
            } else {
                $btnText = 'Bayar Sekarang';
                $btnAction = "window.location.href='checkout.php?pay_order={$order['id']}'";
                $showPayButton = true;
            }
        }
    ?>
    <div class="order-card">

        <!-- Header -->
        <div class="order-header">
            <div class="store-name">
                <span class="mall-badge">Hot</span>
                <?= htmlspecialchars($storeName) ?>
            </div>
            <span class="status-badge" style="background: <?= $cfg['badge'] ?>18; color: <?= $cfg['badge'] ?>;">
                <?= htmlspecialchars($cfg['label']) ?>
            </span>
        </div>

        <!-- Tracking -->
        <div class="tracking-row">
            <div class="tracking-icon" style="background: <?= $cfg['badge'] ?>18;">
                <i class="fas <?= $cfg['icon'] ?>" style="color: <?= $cfg['badge'] ?>;"></i>
            </div>
            <div class="tracking-text">
                <div class="tracking-time">
                    <?= $timeStr ?> <?= $isSelesai ? 'Diterima' : ($status === 'Dibatalkan' ? 'Dibatalkan' : 'Menunggu konfirmasi') ?>
                </div>
                <div class="tracking-desc"><?= htmlspecialchars($trackMsg) ?></div>
            </div>
            <i class="fas fa-chevron-right" style="color:#ccc;"></i>
        </div>

        <!-- Products -->
        <?php if(is_array($items) && !empty($items)): ?>
            <?php foreach($items as $item): 
                $itemName = $item['name'] ?? ($item['product_name'] ?? 'Produk');
                $itemVariant = $item['variant'] ?? '';
                $itemPrice = $item['price'] ?? 0;
                $itemQty = $item['qty'] ?? ($item['quantity'] ?? 1);
                $itemImage = $item['image'] ?? ($item['product_image'] ?? '');
                
                // ✅ PERBAIKAN PATH GAMBAR AGAR MUNCUL
                $imagePath = '';
                if(!empty($itemImage)){
                    if(filter_var($itemImage, FILTER_VALIDATE_URL)){
                        $imagePath = $itemImage;
                    } elseif(file_exists($itemImage)){
                        $imagePath = $itemImage;
                    } elseif(file_exists('uploads/' . $itemImage)){
                        $imagePath = 'uploads/' . $itemImage;
                    } elseif(file_exists('texcer2/uploads/' . $itemImage)){
                        $imagePath = 'texcer2/uploads/' . $itemImage;
                    } 
                }
            ?>
            <div class="product-row">
                <?php if(!empty($imagePath)): ?>
                <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($itemName) ?>" class="product-thumb" onerror="this.src='assets/images/placeholder.png'">
                <?php else: ?>
                <div class="product-thumb-placeholder">
                    <i class="fas fa-utensils"></i>
                </div>
                <?php endif; ?>
                
                <div class="product-detail">
                    <div class="product-name"><?= htmlspecialchars($itemName) ?></div>
                    <?php if(!empty($itemVariant)): ?>
                    <div class="product-variant"><?= htmlspecialchars($itemVariant) ?></div>
                    <?php endif; ?>
                    <div class="product-price-row">
                        <span>Rp<?= number_format($itemPrice, 0, ',', '.') ?></span>
                        <span>x<?= $itemQty ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div style="padding:20px; text-align:center; color:var(--text-gray);">
            <p>Detail produk tidak tersedia</p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="order-footer">
            <div class="order-total">
                Total: Rp<?= number_format($order['total_price'] ?? 0, 0, ',', '.') ?>
                <?php if($paymentMethod === 'COD'): ?>
                <span style="font-size:0.8rem; color:#666; font-weight:normal; margin-left:8px;">(COD)</span>
                <?php endif; ?>
            </div>
            <div class="action-buttons">
    
                <!-- ✅ TOMBOL REVIEW & KERANJANG DIHAPUS SESUAI PERMINTAAN -->
                
                <!-- ✅ LOGIKA TOMBOL UTAMA (COD vs NON-COD) -->
                <?php if($showPayButton): ?>
                <button class="btn-buy-again" onclick="<?= $btnAction ?>">
                    <?= $btnText ?>
                </button>
                <?php elseif($isMenunggu && $paymentMethod === 'COD'): ?>
                <button class="btn-buy-again" disabled>
                    Menunggu Konfirmasi
                </button>
                <?php else: ?>
                <button class="btn-buy-again" onclick="<?= $btnAction ?>">
                    <?= $btnText ?>
                </button>
                <?php endif; ?>

            </div>
        </div>

    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- ✅ MODAL REVIEW & SCRIPT JS REVIEW DIHAPUS TOTAL -->

</body>
</html>