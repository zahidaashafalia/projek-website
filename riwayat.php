<?php
session_start(); // Pastikan session dimulai
include 'config.php';

// Cek login
if(!isset($_SESSION['user_phone'])){
    header("Location: auth/login.php");
    exit;
}

$phone = $_SESSION['user_phone'];

// ==========================================
// LOGIKA PEMBATALAN PESANAN
// ==========================================
if(isset($_GET['cancel_order'])){
    $order_id_to_cancel = (int)$_GET['cancel_order'];
    
    $stmt = mysqli_prepare($conn, "SELECT id, status FROM orders WHERE id = ? AND customer_phone = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "is", $order_id_to_cancel, $phone);
    mysqli_stmt_execute($stmt);
    $result_check = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result_check) > 0){
        $order_data = mysqli_fetch_assoc($result_check);
        
        if(in_array($order_data['status'], ['Menunggu Konfirmasi', 'Pending'])){
            $stmt_update = mysqli_prepare($conn, "UPDATE orders SET status = 'Dibatalkan' WHERE id = ?");
            mysqli_stmt_bind_param($stmt_update, "i", $order_id_to_cancel);
            mysqli_stmt_execute($stmt_update);
            
            echo "<script>alert('Pesanan berhasil dibatalkan.'); window.location.href='riwayat.php';</script>";
            exit;
        } else {
            echo "<script>alert('Pesanan dengan status \"" . htmlspecialchars($order_data['status']) . "\" tidak dapat dibatalkan.'); window.location.href='riwayat.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Pesanan tidak ditemukan atau bukan milik Anda.'); window.location.href='riwayat.php';</script>";
        exit;
    }
}

// ==========================================
// PENGAMBILAN DATA PESANAN & GAMBAR TERBARU
// ==========================================
$orders = [];
$stmt_orders = mysqli_prepare($conn, "SELECT * FROM orders WHERE customer_phone = ? ORDER BY id DESC");
mysqli_stmt_bind_param($stmt_orders, "s", $phone);
mysqli_stmt_execute($stmt_orders);
$result_orders = mysqli_stmt_get_result($stmt_orders);

while($row = mysqli_fetch_assoc($result_orders)){
    $items = [];
    
    // PENTING: Query ini mengambil gambar TERBARU dari tabel 'products'
    // Jadi jika Admin ganti gambar di DB, di sini otomatis ikut berubah.
    $stmt_items = mysqli_prepare($conn, "
        SELECT oi.*, p.image as product_image 
        FROM order_items oi 
        LEFT JOIN products p ON p.name = oi.product_name 
        WHERE oi.order_id = ?
    ");
    mysqli_stmt_bind_param($stmt_items, "i", $row['id']);
    mysqli_stmt_execute($stmt_items);
    $itemsResult = mysqli_stmt_get_result($stmt_items);
    
    if($itemsResult){
while($item = mysqli_fetch_assoc($itemsResult)){
    $imgFromDB = $item['product_image'] ?? '';
    $finalPath = '';
    $debugMsg = '';

    if(empty($imgFromDB)){
        $debugMsg = "⚠️ DB Kosong";
    } 
    elseif (filter_var($imgFromDB, FILTER_VALIDATE_URL)) {
        $finalPath = $imgFromDB;
    } 
    else {
        $fileName = basename($imgFromDB);
        
        // Coba semua kemungkinan lokasi folder
        $possiblePaths = [
            'uploads/site-images/',
            'uploads/',
            'assets/images/',
            'images/',
            ''
        ];
        
        $found = false;
        foreach($possiblePaths as $folder){
            $fullPath = __DIR__ . '/' . $folder . $fileName;
            if(file_exists($fullPath)){
                $finalPath = $folder . $fileName;
                $found = true;
                break;
            }
        }
        
        if(!$found){
            $debugMsg = "❌ File '$fileName' tidak ditemukan di mana pun! Cek folder uploads/, assets/images/, dll.";
            $finalPath = '';
        }
    }
    
    $item['final_image_path'] = $finalPath;
    $item['debug_msg'] = $debugMsg;
    $items[] = $item;
}
    }
    
    $row['items'] = $items;
    $orders[] = $row;
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
        'Selesai'               => ['label' => 'Pesanan Selesai',         'color' => '#4CAF50', 'icon' => 'fa-check-circle',   'badge' => '#4CAF50'],
        'Dikirim'               => ['label' => 'Sedang Dikirim',          'color' => '#2196F3', 'icon' => 'fa-shipping-fast',  'badge' => '#2196F3'],
        'Menunggu Konfirmasi'   => ['label' => 'Menunggu Konfirmasi',     'color' => '#FF9800', 'icon' => 'fa-clock',          'badge' => '#FF9800'],
        'Diproses'              => ['label' => 'Pesanan Diproses',        'color' => '#9C27B0', 'icon' => 'fa-cog',            'badge' => '#9C27B0'],
        'Dibatalkan'            => ['label' => 'Pesanan Dibatalkan',      'color' => '#F44336', 'icon' => 'fa-times-circle',   'badge' => '#F44336'],
        'Menunggu Pembayaran'   => ['label' => 'Perlu Dibayar',           'color' => '#FF5722', 'icon' => 'fa-money-bill',     'badge' => '#FF5722'],
        'Pending'               => ['label' => 'Pending',                 'color' => '#FF9800', 'icon' => 'fa-clock',          'badge' => '#FF9800'],
        default                 => ['label' => $status ?? 'Unknown',      'color' => '#9E9E9E', 'icon' => 'fa-question-circle','badge' => '#9E9E9E'],
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
    --green: #26aa99;
    --red: #dc3545;
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
    margin-bottom: 15px;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}
.order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.order-header {
    padding: 12px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border);
    background: #fafafa;
}
.store-info {
    display: flex;
    align-items: center;
}
.store-name {
    font-weight: 700;
    color: var(--text-dark);
    font-size: 1rem;
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
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Product List in Card */
.product-list-in-card {
    padding: 0;
}
.product-item-in-card {
    padding: 12px 18px;
    display: flex;
    gap: 12px;
    border-bottom: 1px solid var(--border);
}
.product-item-in-card:last-child {
    border-bottom: none;
}
.product-thumb {
    width: 60px;
    height: 60px;
    border-radius: 6px;
    object-fit: cover;
    border: 1px solid var(--border);
    background: #eee;
}
.product-thumb-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 6px;
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
    margin-bottom: 2px;
    font-size: 0.9rem;
}
.product-variant {
    font-size: 0.75rem;
    color: var(--text-gray);
    margin-bottom: 4px;
}
.product-price-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    color: var(--text-dark);
}

/* Footer */
.order-footer {
    padding: 12px 18px;
    background: #fdfdfd;
    border-top: 1px solid var(--border);
}
.order-total {
    text-align: right;
    margin-bottom: 12px;
    font-weight: 700;
    font-size: 1rem;
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
    font-size: 0.9rem;
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

/* Modal Detail Style */
.modal-detail-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #eee;
    text-align: center;
}
.modal-status-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 5px;
}
.modal-status-desc {
    color: #666;
    font-size: 0.95rem;
}
.modal-section {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
}
.modal-section h6 {
    font-weight: 700;
    margin-bottom: 10px;
    color: var(--text-dark);
}
.modal-address {
    font-size: 0.95rem;
    line-height: 1.5;
}
.modal-product-item {
    display: flex;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px dashed #eee;
}
.modal-product-item:last-child {
    border-bottom: none;
}
.modal-product-info {
    flex: 1;
}
.modal-product-name {
    font-weight: 600;
    margin-bottom: 5px;
}
.modal-product-variant {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 5px;
}
.modal-product-price {
    font-weight: 700;
    color: var(--primary);
}
.modal-summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.95rem;
}
.modal-summary-total {
    font-weight: 800;
    font-size: 1.1rem;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #ddd;
}
.modal-info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9rem;
}
.modal-info-label {
    color: #666;
}
.modal-info-value {
    font-weight: 600;
    text-align: right;
}
.btn-cancel-order {
    background: #fff;
    color: var(--red);
    border: 1px solid var(--red);
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    width: 100%;
    margin-top: 10px;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}
.btn-cancel-order:hover {
    background: var(--red);
    color: white;
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
        $storeName = $order['store_name'] ?? $order['store'] ?? 'Texcer Hot';
        $items = $order['items'] ?? [];
        $status = $order['status'] ?? '';
        
        $paymentMethod = $order['payment_method'] ?? 'COD'; 
        
        $isSelesai = $status === 'Selesai';
        $isMenunggu = in_array($status, ['Menunggu Konfirmasi', 'Pending']);
        
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
    <!-- Order Card with Click Event -->
    <div class="order-card" onclick="showOrderDetail(<?= htmlspecialchars(json_encode($order), ENT_QUOTES) ?>)">

        <!-- Header -->
        <div class="order-header">
            <div class="store-info">
                <span class="mall-badge">Hot</span>
                <span class="store-name"><?= htmlspecialchars($storeName) ?></span>
            </div>
            <span class="status-badge" style="background: <?= $cfg['badge'] ?>18; color: <?= $cfg['badge'] ?>;">
                <?= htmlspecialchars($cfg['label']) ?>
            </span>
        </div>

        <!-- Product List -->
        <div class="product-list-in-card">
            <?php if(is_array($items) && !empty($items)): ?>
                <?php foreach($items as $item): ?>
                    <?php 
                    $itemName = $item['name'] ?? ($item['product_name'] ?? 'Produk');
                    $itemVariant = $item['variant'] ?? '';
                    $itemPrice = $item['price'] ?? 0;
                    $itemQty = $item['qty'] ?? ($item['quantity'] ?? 1);
                    
                    // Ambil path gambar yang sudah diproses PHP
                    $imagePath = $item['final_image_path'] ?? '';
                    ?>
                    <div class="product-item-in-card">
                        <?php if(!empty($imagePath)): ?>
                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($itemName) ?>" class="product-thumb" onerror="this.onerror=null; this.src='assets/images/placeholder.png';">
                        <?php else: ?>
                        <div class="product-thumb-placeholder">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <?php endif; ?>
                        
<div class="product-detail">
    <div class="product-name"><?= htmlspecialchars($itemName) ?></div>
    
    <?php 
    // LOGIKA PEMISAHAN LEVEL DAN TOPPING DARI VARIAN
    $levelText = '';
    $toppingText = '';
    $variantRaw = $item['variant'] ?? '';

    if (!empty($variantRaw)) {
        // Cek apakah ada kata "Level" di varian
        if (stripos($variantRaw, 'level') !== false) {
            // Jika ada Level, biasanya formatnya: "Level X (Pedas) + Topping A, Topping B"
            $parts = explode('+', $variantRaw);
            
            // Bagian pertama biasanya Level
            $levelText = trim($parts[0]); 
            
            // Sisa bagian adalah Topping (jika ada)
            if (isset($parts[1])) {
                $toppingText = trim($parts[1]);
            }
        } else {
            // Jika tidak ada kata Level, anggap semua sebagai Topping atau Varian Lain
            $toppingText = $variantRaw;
        }
    }
    ?>

    <!-- Tampilkan Badge Level Pedas -->
    <?php if (!empty($levelText)): ?>
    <div class="product-level-badge" style="display: inline-flex; align-items: center; gap: 4px; background: #fff0ee; border: 1px solid #ffd0c0; color: #ff6b00; font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; margin-bottom: 4px;">
        <i class="fas fa-pepper-hot"></i> <?= htmlspecialchars($levelText) ?>
    </div>
    <?php endif; ?>

    <!-- Tampilkan Badge Topping -->
    <?php if (!empty($toppingText)): ?>
    <div class="product-topping-badge" style="display: inline-flex; align-items: center; gap: 4px; background: #f5f0eb; border: 1px solid var(--border); color: var(--text-dark); font-size: 0.75rem; font-weight: 500; padding: 2px 8px; border-radius: 4px; margin-bottom: 4px;">
        <i class="fas fa-plus-circle"></i> <?= htmlspecialchars($toppingText) ?>
    </div>
    <?php endif; ?>

    <!-- Jika tidak ada varian sama sekali, tampilkan Regular -->
    <?php if (empty($variantRaw)): ?>
    <div class="product-variant" style="font-size: 0.75rem; color: var(--text-gray); margin-bottom: 4px;">Regular</div>
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
        </div>

        <!-- Footer -->
        <div class="order-footer">
            <div class="order-total">
                Total: Rp<?= number_format($order['total_price'] ?? 0, 0, ',', '.') ?>
                <?php if($paymentMethod === 'COD'): ?>
                <span style="font-size:0.8rem; color:#666; font-weight:normal; margin-left:8px;">(COD)</span>
                <?php endif; ?>
            </div>
            <div class="action-buttons" onclick="event.stopPropagation()"> 
    
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

<!-- ✅ MODAL DETAIL PESANAN -->
<div class="modal fade" id="orderDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Detail Pesanan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0" id="modalBodyContent">
                <!-- Konten akan diisi oleh JavaScript -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showOrderDetail(orderData) {
    const modalBody = document.getElementById('modalBodyContent');
    const orderDate = new Date(orderData.created_at);
    const formattedDate = orderDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) + ' ' + orderDate.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

    let itemsHtml = '';
    orderData.items.forEach(item => {
        let productName = item.name || item.product_name || 'Produk';
        
        // Di modal, kita TIDAK menampilkan gambar agar lebih bersih (sesuai request sebelumnya)
        // Jika ingin menampilkan gambar di modal juga, uncomment baris img di bawah
        
        itemsHtml += `
        <div class="modal-product-item">
            <div class="modal-product-info">
                <div class="modal-product-name">${productName}</div>
                ${item.variant ? `<div class="modal-product-variant">Varian: ${item.variant}</div>` : ''}
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="modal-product-price">Rp${parseInt(item.price).toLocaleString('id-ID')}</div>
                    <div class="text-muted">x${item.qty}</div>
                </div>
            </div>
        </div>
        `;
    });

    const subtotal = parseInt(orderData.total_price);
    const shipping = 0;
    const total = subtotal + shipping;

    let cancelBtnHtml = '';
    if(orderData.status === 'Menunggu Konfirmasi' || orderData.status === 'Pending') {
        cancelBtnHtml = `<a href="?cancel_order=${orderData.id}" class="btn-cancel-order" onclick="return confirm('Yakin ingin membatalkan pesanan ini?')">Batalkan Pesanan</a>`;
    }

    modalBody.innerHTML = `
        <div class="modal-detail-header text-center">
            <div class="mb-2">
                <i class="fas ${getStatusIcon(orderData.status)} fa-3x" style="color: ${getStatusColor(orderData.status)}"></i>
            </div>
            <div class="modal-status-title" style="color: ${getStatusColor(orderData.status)}">${orderData.status}</div>
            <div class="modal-status-desc">${getStatusDesc(orderData.status)}</div>
        </div>

        <div class="modal-section">
            <h6><i class="fas fa-map-marker-alt text-danger me-2"></i>Alamat Pengiriman</h6>
            <div class="modal-address">
                <strong>${orderData.customer_name}</strong> (${orderData.customer_phone})<br>
                ${orderData.customer_address}, ${orderData.customer_city}, ${orderData.customer_province}
            </div>
        </div>

        <div class="modal-section">
            <h6><i class="fas fa-box me-2"></i>Produk (${orderData.items.length} item)</h6>
            ${itemsHtml}
        </div>

        <div class="modal-section">
            <h6><i class="fas fa-file-invoice-dollar me-2"></i>Rincian Pembayaran</h6>
            <div class="modal-summary-row">
                <span class="text-muted">Subtotal Produk</span>
                <span>Rp${subtotal.toLocaleString('id-ID')}</span>
            </div>
            <div class="modal-summary-row">
                <span class="text-muted">Biaya Pengiriman</span>
                <span class="text-success">Gratis</span>
            </div>
            <div class="modal-summary-row modal-summary-total">
                <span>Total Pesanan</span>
                <span style="color: var(--primary)">Rp${total.toLocaleString('id-ID')}</span>
            </div>
            <div class="mt-2 small text-muted">
                Metode Pembayaran: <span class="fw-bold text-dark">${orderData.payment_method || 'COD'}</span>
            </div>
        </div>

        <div class="modal-section">
            <h6><i class="fas fa-info-circle me-2"></i>Informasi Pesanan</h6>
            <div class="modal-info-row">
                <span class="modal-info-label">Nomor Pesanan</span>
                <span class="modal-info-value">#${orderData.id.toString().padStart(4, '0')}</span>
            </div>
            <div class="modal-info-row">
                <span class="modal-info-label">Tanggal Pemesanan</span>
                <span class="modal-info-value">${formattedDate}</span>
            </div>
            ${orderData.resi_number ? `
            <div class="modal-info-row">
                <span class="modal-info-label">No. Resi</span>
                <span class="modal-info-value">${orderData.resi_number}</span>
            </div>
            ` : ''}
        </div>

        <div class="modal-section border-0 pb-0">
            ${cancelBtnHtml}
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    modal.show();
}

function getStatusIcon(status) {
    switch(status) {
        case 'Selesai': return 'fa-check-circle';
        case 'Dikirim': return 'fa-shipping-fast';
        case 'Menunggu Konfirmasi': return 'fa-clock';
        case 'Dibatalkan': return 'fa-times-circle';
        default: return 'fa-info-circle';
    }
}

function getStatusColor(status) {
    switch(status) {
        case 'Selesai': return '#4CAF50';
        case 'Dikirim': return '#2196F3';
        case 'Menunggu Konfirmasi': return '#FF9800';
        case 'Dibatalkan': return '#F44336';
        default: return '#9E9E9E';
    }
}

function getStatusDesc(status) {
    switch(status) {
        case 'Selesai': return 'Pesanan Anda telah diterima.';
        case 'Dikirim': return 'Pesanan sedang dalam perjalanan.';
        case 'Menunggu Konfirmasi': return 'Pesanan Anda menunggu konfirmasi penjual.';
        case 'Dibatalkan': return 'Pesanan ini telah dibatalkan.';
        default: return 'Status pesanan sedang diproses.';
    }
}
</script>

</body>
</html>