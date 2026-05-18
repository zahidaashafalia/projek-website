<?php
// Hapus atau comment session_start() jika sudah ada di config.php
// session_start(); 
include 'config.php';


// Ambil data pesanan dari database
$orders = [];
if(isset($_SESSION['user_phone'])){
    $phone = $_SESSION['user_phone'];
    $result = mysqli_query($conn, "SELECT * FROM orders WHERE customer_phone = '$phone' ORDER BY id DESC");
    while($row = mysqli_fetch_assoc($result)){
        // Ambil items - coba dari kolom items (JSON) atau tabel order_items
        $items = [];
        
        // Coba ambil dari kolom items (JSON format)
        if(isset($row['items']) && !empty($row['items']) && $row['items'] !== 'NULL'){
            $decoded = json_decode($row['items'], true);
            if(is_array($decoded)){
                $items = $decoded;
            }
        }
        
        // Jika masih kosong, coba ambil dari tabel order_items
        if(empty($items)){
            $itemsResult = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = {$row['id']}");
            if($itemsResult){
                while($item = mysqli_fetch_assoc($itemsResult)){
                    $items[] = $item;
                }
            }
        }
        
        $row['items'] = $items;
        $orders[] = $row;
    }
}

// Debug: Lihat isi orders
// echo "<pre>"; print_r($orders); echo "</pre>"; exit;

// Demo data jika belum ada pesanan
if(empty($orders)){
    $orders = [
        [
            'id' => 1001,
            'store_name' => 'Texcer Hot',
            'status' => 'Selesai',
            'created_at' => '2025-05-10 11:51:00',
            'total_price' => 37000,
            'resi_number' => 'JNE123456',
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Mie Yamin', 
                    'variant' => '', 
                    'price' => 9000, 
                    'qty' => 2, 
                    'image' => 'assets/images/mie/yamin.png'
                ],
                [
                    'id' => 3,
                    'name' => 'Ceker Mercon Tanpa Tulang', 
                    'variant' => 'Medium (5 pcs)', 
                    'price' => 15000, 
                    'qty' => 1, 
                    'image' => 'assets/images/mercon/ceker_tnpa_tulang.jpg'
                ],
            ]
        ],
        [
            'id' => 1002,
            'store_name' => 'Texcer Hot',
            'status' => 'Menunggu Konfirmasi',
            'created_at' => '2025-05-12 18:57:00',
            'total_price' => 3000,
            'resi_number' => '',
            'items' => [
                [
                    'id' => 11,
                    'name' => 'Teh', 
                    'variant' => 'Es', 
                    'price' => 3000, 
                    'qty' => 1, 
                    'image' => 'assets/images/minuman/teh.jpg'
                ],
            ]
        ],
    ];
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

/* Bonus Banner */
.bonus-banner {
    background: #FFF8EE;
    padding: 11px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.bonus-badge {
    background: #FF9800;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 800;
}

/* Order Card */
.order-card {
    background: var(--bg-white);
    margin-bottom: 10px;
    border: 1px solid var(--border);
}
.order-header {
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid var(--border);
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
.product-thumb {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--border);
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
}

/* Footer */
.order-footer {
    padding: 12px 18px;
}
.order-total {
    text-align: right;
    margin-bottom: 12px;
    font-weight: 700;
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
}
.btn-cart-small {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: white;
    cursor: pointer;
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

    <!-- Bonus Banner -->
    <div class="bonus-banner">
        <div>
            <i class="fas fa-gift" style="color:#FF9800;"></i>
            <span>Dapatkan <span class="bonus-badge">bonus 35</span> dengan menulis ulasan.</span>
        </div>
        <i class="fas fa-chevron-right"></i>
    </div>

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
        $isSelesai = $status === 'Selesai';
        $isMenunggu = in_array($status, ['Menunggu Konfirmasi', 'Pending']);
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
                // Pastikan data item ada
                $itemName = $item['name'] ?? ($item['product_name'] ?? 'Produk');
                $itemVariant = $item['variant'] ?? '';
                $itemPrice = $item['price'] ?? 0;
                $itemQty = $item['qty'] ?? ($item['quantity'] ?? 1);
                $itemImage = $item['image'] ?? ($item['product_image'] ?? '');
                
                // Perbaiki path gambar
                $imagePath = '';
                if(!empty($itemImage)){
                    // Cek apakah path sudah benar
                    if(file_exists($itemImage)){
                        $imagePath = $itemImage;
                    } elseif(file_exists('texcer2/' . $itemImage)){
                        $imagePath = 'texcer2/' . $itemImage;
                    } else {
                        // Gunakan placeholder jika file tidak ada
                        $imagePath = '';
                    }
                }
            ?>
            <div class="product-row">
                <?php if(!empty($imagePath)): ?>
                <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($itemName) ?>" class="product-thumb">
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
            <i class="fas fa-box-open" style="font-size:2rem; margin-bottom:10px; opacity:0.5;"></i>
            <p>Detail produk tidak tersedia</p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="order-footer">
            <div class="order-total">
                Total: Rp<?= number_format($order['total_price'] ?? 0, 0, ',', '.') ?>
            </div>
            <div class="action-buttons">
    
 <!-- ✅ TOMBOL REVIEW BARU - 1 Tombol dengan Modal -->
<?php if($isSelesai && !empty($items)): ?>
    <?php 
    // Ambil item pertama untuk tombol review (1 tombol per order)
    $firstItem = $items[0];
    $itemId = $firstItem['id'] ?? $firstItem['product_id'] ?? 0;
    $itemName = $firstItem['name'] ?? $firstItem['product_name'] ?? 'Produk';
    
    // Cek apakah sudah direview
    $alreadyReviewed = false;
    if(function_exists('isProductReviewed')){
        $alreadyReviewed = isProductReviewed($conn, $order['id'], $itemId);
    }
    ?>
    
    <?php if(!$alreadyReviewed): ?>
    <button class="btn btn-sm btn-review" 
            style="background: #FFB800; color: white; border: none; border-radius: 20px; padding: 6px 16px; font-weight: 600;"
            onclick="openReviewModal(<?= $order['id'] ?>, <?= $itemId ?>, '<?= addslashes($itemName) ?>')">
        <i class="fas fa-star me-1"></i>Ulas
    </button>
    <?php else: ?>
    <span class="badge" style="background: #4CAF50; color: white; border-radius: 20px; padding: 6px 12px; font-size: 0.8rem;">
        <i class="fas fa-check me-1"></i>Terulas
    </span>
    <?php endif; ?>
<?php endif; ?>
    
    <button class="btn-cart-small" onclick="alert('Tambah ke keranjang')">
        <i class="fas fa-cart-plus"></i>
    </button>
    <button class="btn-buy-again" onclick="alert('Beli lagi')">
        <?= $isMenunggu ? 'Bayar Sekarang' : 'Beli lagi' ?>
    </button>
</div>
        </div>

    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- ✅ MODAL REVIEW - Tambah sebelum </body> -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="reviewForm" method="POST" action="submit_review.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">✨ Beri Ulasan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="r_order_id">
                    <input type="hidden" name="product_id" id="r_product_id">
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted">Produk</label>
                        <input type="text" class="form-control" id="r_product_name" readonly style="background: #f8f9fa;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted">Rating</label>
                        <div class="d-flex gap-1" id="starRating">
                            <?php for($i = 5; $i >= 1; $i--): ?>
                            <label style="cursor: pointer; font-size: 1.8rem;">
                                <input type="radio" name="rating" value="<?= $i ?>" class="d-none" id="star<?= $i ?>">
                                <span class="star" data-value="<?= $i ?>">☆</span>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted">Ulasan</label>
                        <textarea name="review_text" class="form-control" rows="3" placeholder="Ceritakan pengalamanmu..." required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted">Foto (Opsional)</label>
                        <input type="file" name="review_image" class="form-control" accept="image/*" id="r_image">
                        <small class="text-muted" style="font-size: 0.75rem;">JPG/PNG, maks 2MB</small>
                        <img id="imgPreview" class="mt-2 rounded" style="max-width: 150px; display: none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn" style="background: #FFB800; color: white; font-weight: 600;">
                        <i class="fas fa-paper-plane me-1"></i>Kirim Ulasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#starRating .star { transition: transform 0.1s; color: #ccc; }
#starRating .star:hover,
#starRating .star.active { color: #FFB800; transform: scale(1.2); }
#starRating input:checked ~ .star,
#starRating .star.selected { color: #FFB800; }
</style>

<script>
function openReviewModal(orderId, productId, productName) {
    document.getElementById('r_order_id').value = orderId;
    document.getElementById('r_product_id').value = productId;
    document.getElementById('r_product_name').value = productName;
    const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
    modal.show();
}

// Rating bintang interaktif
document.querySelectorAll('#starRating .star').forEach(star => {
    star.addEventListener('click', function() {
        const value = this.dataset.value;
        document.querySelectorAll('#starRating .star').forEach(s => {
            s.classList.toggle('selected', s.dataset.value <= value);
        });
        document.querySelector(`#starRating input[value="${value}"]`).checked = true;
    });
});

// Preview gambar
document.getElementById('r_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if(file && file.size <= 2 * 1024 * 1024) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imgPreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else if(file) {
        alert('❌ File terlalu besar! Maksimal 2MB');
        this.value = '';
    }
});
</script>
</body>
</html>