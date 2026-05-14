<?php 
include 'config.php'; 
include 'functions.php';

$grand_total = 0;
$total_items = 0;

// 1. Logika Update Quantity & Hapus
if(isset($_GET['action'])){
    $index = $_GET['index'];
    $action = $_GET['action'];
    if(isset($_SESSION['cart'][$index])){
        if($action == 'plus'){
            $_SESSION['cart'][$index]['qty']++;
        } elseif($action == 'minus'){
            if($_SESSION['cart'][$index]['qty'] > 1){
                $_SESSION['cart'][$index]['qty']--;
            } else {
                unset($_SESSION['cart'][$index]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
            }
        } elseif($action == 'remove'){
            unset($_SESSION['cart'][$index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
    }
    header("Location: checkout.php");
    exit;
}

// 2. Upload QRIS Image
if(isset($_POST['upload_qris'])){
    if(isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] == 0){
        $target_dir = "assets/images/";
        $target_file = $target_dir . "qris_" . time() . ".jpg";
        if(move_uploaded_file($_FILES['qris_image']['tmp_name'], $target_file)){
            $_SESSION['qris_image'] = $target_file;
            echo "<script>alert('QRIS berhasil diupload!'); window.location='checkout.php';</script>";
        }
    }
}

// 3. Logika Checkout
if(isset($_POST['process_order'])){
    $name = $_POST['cust_name'];
    $phone = $_POST['cust_phone'];
    $_SESSION['user_phone'] = $phone;
    $address = $_POST['cust_address'];
    $city = $_POST['cust_city'];
    $province = $_POST['cust_province'];
    $payment_method = $_POST['payment_method'] ?? 'COD';
    $notes = $_POST['notes'];
    $total = 0;
    if(!empty($_SESSION['cart'])){
        foreach($_SESSION['cart'] as $item) $total += ($item['price'] * $item['qty']);
        $sql_order = "INSERT INTO orders (customer_name, customer_phone, customer_address, customer_city, customer_province, total_price, status, payment_method) 
                      VALUES ('$name', '$phone', '$address', '$city', '$province', '$total', 'Pending', '$payment_method')";
        if(mysqli_query($conn, $sql_order)){
            $order_id = mysqli_insert_id($conn);
            $order_number = 'ORD' . str_pad($order_id, 10, '0', STR_PAD_LEFT);
            mysqli_query($conn, "UPDATE orders SET order_number = '$order_number' WHERE id = $order_id");
            notifyOrderCreated($conn, $phone, $order_id, $order_number);
            foreach($_SESSION['cart'] as $item){
                $sub = $item['price'] * $item['qty'];
                mysqli_query($conn, "INSERT INTO order_items (order_id, product_name, price, qty, subtotal) VALUES ('$order_id', '{$item['name']}', '{$item['price']}', '{$item['qty']}', '$sub')");
            }
            unset($_SESSION['cart']);
            header("Location: checkout.php?success=1");
            exit;
        }
    }
}

if(isset($_GET['success']) && $_GET['success'] == '1'){
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
    Swal.fire({
        icon: 'success',
        title: '🎉 Pesanan Berhasil!',
        text: 'Selamat! Pesanan Anda telah berhasil dibuat.',
        confirmButtonColor: '#8B6F4E',
        confirmButtonText: 'Lihat Pesanan'
    }).then((result) => {
        if(result.isConfirmed) window.location = 'riwayat.php';
    });
    </script>";
}

$addr_phone = $_SESSION['user_phone'] ?? '';
$addresses = [];
if(!empty($addr_phone)){
    $res = mysqli_query($conn, "SELECT * FROM addresses WHERE customer_phone = '$addr_phone' ORDER BY is_default DESC, id DESC");
    while($row = mysqli_fetch_assoc($res)) $addresses[] = $row;
}
$default_addr = !empty($addresses) ? $addresses[0] : null;
$currentPage = basename($_SERVER['PHP_SELF']);

// Hitung notifikasi
$totalNotifications = 0;
if(isset($_SESSION['user_phone'])){
    $ph = $_SESSION['user_phone'];
    $nq = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE customer_phone = '$ph' AND status IN ('Menunggu Konfirmasi', 'Dikirim')");
    $totalNotifications = mysqli_fetch_assoc($nq)['total'];
}

if(!empty($_SESSION['cart'])){
    foreach($_SESSION['cart'] as $item){
        $grand_total += ($item['price'] * $item['qty']);
        $total_items += $item['qty'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Keranjang - Texcer Hot</title>
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
    --red: #EE4D2D;
    --red-light: #fff0ee;
    --green: #26aa99;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    background: #f5f5f5;
    color: var(--text-dark);
    font-family: 'Segoe UI', system-ui, sans-serif;
    min-height: 100vh;
}

/* ── TOP NAV (index.php style) ── */
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
    letter-spacing: 1px;
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
    font-size: 0.95rem;
    transition: color 0.3s;
}
.nav-menu a:hover, .nav-menu a.active { color: var(--primary); }
.nav-icons {
    display: flex;
    gap: 20px;
    align-items: center;
}
.nav-icons a {
    color: var(--text-dark);
    font-size: 1.2rem;
    position: relative;
    transition: color 0.3s;
    text-decoration: none;
}
.nav-icons a:hover { color: var(--primary); }
.nav-icon-btn {
    background: none;
    border: none;
    color: var(--text-dark);
    font-size: 1.2rem;
    cursor: pointer;
    position: relative;
    transition: color 0.3s;
}
.nav-icon-btn:hover { color: var(--primary); }
.cart-badge-nav, .notif-count {
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
    font-weight: 600;
}
.notif-count { background: #dc3545; }

/* ── PAGE HEADER ── */
.page-header {
    background: var(--bg-white);
    border-bottom: 1px solid var(--border);
    padding: 14px 0;
    position: sticky;
    top: 76px;
    z-index: 900;
}
.page-header-inner {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.page-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 8px;
}
.page-title-count {
    font-size: 0.9rem;
    color: var(--text-gray);
    font-weight: 400;
}
.page-location {
    font-size: 0.82rem;
    color: var(--text-gray);
    display: flex;
    align-items: center;
    gap: 5px;
}
.page-location i { color: var(--primary); font-size: 0.78rem; }
.page-edit { font-size: 0.88rem; color: var(--primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.page-edit:hover { color: var(--primary-dark); }

/* ── MAIN LAYOUT ── */
.cart-layout {
    max-width: 900px;
    margin: 0 auto;
    padding: 12px 16px 120px;
}

/* ── SHOP GROUP ── */
.shop-group {
    background: var(--bg-white);
    border-radius: 0;
    margin-bottom: 8px;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.shop-header {
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #f5f0eb;
}
.shop-check {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
    cursor: pointer;
    flex-shrink: 0;
}
.shop-power-badge {
    background: #FF9800;
    color: white;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 3px;
    letter-spacing: 0.3px;
}
.shop-name-link {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-dark);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
}
.shop-name-link:hover { color: var(--primary); }
.shop-name-link i { font-size: 0.75rem; color: var(--text-gray); }

/* Promo strip */
.promo-strip {
    padding: 8px 16px;
    background: #fff5f5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #fde8e3;
    cursor: pointer;
    transition: background .15s;
}
.promo-strip:hover { background: #fde8e3; }
.promo-strip-left {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 0.82rem;
    color: var(--primary);
    font-weight: 500;
}
.promo-strip i.tag { color: var(--primary); font-size: 0.85rem; }
.promo-strip i.chev { color: var(--text-gray); font-size: 0.78rem; }

/* ── CART ITEM ── */
.cart-item {
    padding: 14px 16px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    border-bottom: 1px solid #f5f0eb;
    transition: background .15s;
}
.cart-item:last-child { border-bottom: none; }
.cart-item:hover { background: #fdfaf7; }

.item-check {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
    cursor: pointer;
    flex-shrink: 0;
    margin-top: 30px;
}

.item-image {
    width: 110px;
    height: 110px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--bg-cream);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: transform .2s;
}
.item-image:hover { transform: scale(1.03); }
.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.item-image-placeholder {
    width: 110px;
    height: 110px;
    border-radius: 6px;
    flex-shrink: 0;
    background: var(--bg-cream);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    color: var(--primary);
    opacity: .4;
}

.item-body { flex: 1; min-width: 0; }

/* Pre-order badge */
.preorder-tag {
    display: inline-block;
    background: #222;
    color: white;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 3px;
    margin-bottom: 5px;
}

.item-name {
    font-size: 0.9rem;
    color: var(--text-dark);
    font-weight: 500;
    line-height: 1.4;
    margin-bottom: 6px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

/* Variant dropdown-style badge */
.variant-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f5f0eb;
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 4px 10px;
    font-size: 0.8rem;
    color: var(--text-dark);
    margin-bottom: 8px;
    cursor: pointer;
    transition: border-color .2s;
}
.variant-pill:hover { border-color: var(--primary); }
.variant-pill i { font-size: 0.72rem; color: var(--text-gray); }

/* Flash sale tag */
.flash-sale-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--red);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 4px;
    margin-bottom: 6px;
}
.flash-sale-countdown {
    background: rgba(255,255,255,0.25);
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 0.72rem;
    font-family: monospace;
    font-weight: 800;
}

/* Price row */
.item-price-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 4px;
}
.item-price {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--red);
}
.item-original-price {
    font-size: 0.82rem;
    color: #aaa;
    text-decoration: line-through;
}
.discount-badge {
    background: var(--red-light);
    color: var(--red);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 3px;
}

/* Sold count */
.sold-count {
    font-size: 0.78rem;
    color: var(--text-gray);
    margin-bottom: 4px;
}

/* Bonus line */
.bonus-line {
    font-size: 0.78rem;
    color: #c87533;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 8px;
}
.bonus-line i { font-size: 0.75rem; }

/* ── QTY CONTROL ── */
.qty-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0;
    margin-top: 8px;
}
.qty-btn {
    width: 30px;
    height: 30px;
    background: var(--bg-white);
    border: 1.5px solid var(--border);
    color: var(--text-dark);
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s;
    font-weight: 600;
}
.qty-btn.minus { border-radius: 6px 0 0 6px; }
.qty-btn.plus  { border-radius: 0 6px 6px 0; }
.qty-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.qty-value {
    width: 38px;
    height: 30px;
    border: 1.5px solid var(--border);
    border-left: none;
    border-right: none;
    text-align: center;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-dark);
    background: var(--bg-white);
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── VOUCHER STRIP ── */
.voucher-strip {
    background: var(--bg-white);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    cursor: pointer;
    transition: background .15s;
}
.voucher-strip:hover { background: var(--bg-cream); }
.voucher-left {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-dark);
}
.voucher-icon {
    width: 32px;
    height: 32px;
    background: var(--red);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.85rem;
}

/* ── EMPTY CART ── */
.empty-cart {
    text-align: center;
    padding: 80px 20px;
    background: var(--bg-white);
    margin: 20px 0;
    border-radius: 12px;
}
.empty-cart i { font-size: 5rem; color: var(--primary); opacity: .2; margin-bottom: 20px; }
.empty-cart h3 { font-size: 1.2rem; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
.empty-cart p  { color: var(--text-gray); margin-bottom: 24px; }
.btn-shop {
    background: var(--primary);
    color: white;
    border: none;
    padding: 12px 32px;
    border-radius: 25px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all .2s;
}
.btn-shop:hover { background: var(--primary-dark); color: white; transform: translateY(-2px); }

/* ── BOTTOM BAR ── */
.bottom-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--bg-white);
    border-top: 1.5px solid var(--border);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 999;
    box-shadow: 0 -4px 16px rgba(0,0,0,0.08);
}
.bottom-bar-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.select-all-check {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
    cursor: pointer;
}
.select-all-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-dark);
    cursor: pointer;
}
.bottom-bar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}
.bottom-total-label {
    font-size: 0.78rem;
    color: var(--text-gray);
    margin-bottom: 2px;
}
.bottom-total-value {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1;
}
.checkout-btn {
    background: var(--green);
    color: white;
    border: none;
    padding: 13px 30px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
    min-width: 120px;
}
.checkout-btn:hover { background: #1e9486; transform: translateY(-1px); }
.checkout-btn:disabled { background: #ccc; cursor: not-allowed; transform: none; }

/* ── MODAL CHECKOUT ── */
.modal-content {
    background: var(--bg-white);
    border: none;
    border-radius: 20px;
}
.modal-header {
    background: var(--primary);
    color: white;
    border-radius: 20px 20px 0 0;
    border-bottom: none;
    padding: 18px 24px;
}
.modal-title { color: white; font-weight: 700; font-size: 1.1rem; }
.btn-close-white { filter: invert(1); }
.modal-body { padding: 24px; background: #f5f5f5; }

.co-section {
    background: var(--bg-white);
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 14px;
}
.co-section-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.co-section-title i { color: var(--primary); }

.address-card {
    border: 2px solid var(--border);
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all .2s;
    position: relative;
}
.address-card:hover { border-color: var(--secondary); }
.address-card.selected { border-color: var(--primary); background: var(--bg-cream); }
.address-card .check-dot {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 18px;
    height: 18px;
    border: 2px solid var(--border);
    border-radius: 50%;
    transition: all .2s;
}
.address-card.selected .check-dot {
    border-color: var(--primary);
    background: var(--primary);
}
.address-card.selected .check-dot::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
}

.payment-card {
    border: 2px solid var(--border);
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all .2s;
}
.payment-card:hover { border-color: var(--secondary); }
.payment-card.selected { border-color: var(--primary); background: var(--bg-cream); }

.total-summary {
    background: var(--bg-white);
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 14px;
}
.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    font-size: 0.9rem;
}
.total-row:last-child { 
    margin-bottom: 0;
    padding-top: 12px;
    border-top: 1.5px solid var(--border);
    font-size: 1rem;
    font-weight: 700;
}
.total-row .label { color: var(--text-gray); }
.total-row .value-green { color: var(--green); font-weight: 600; }
.total-row .value-big { color: var(--primary); font-size: 1.2rem; font-weight: 800; }

.btn-submit-order {
    background: var(--primary);
    color: white;
    border: none;
    width: 100%;
    padding: 15px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all .2s;
    margin-top: 4px;
}
.btn-submit-order:hover { background: var(--primary-dark); transform: translateY(-2px); }

/* ── RESPONSIVE ── */
@media (max-width: 992px) {
    .nav-menu { display: none; }
    .top-nav { padding: 14px 20px; }
    .page-header { top: 58px; }
}
@media (max-width: 600px) {
    .item-image, .item-image-placeholder { width: 90px; height: 90px; }
    .checkout-btn { padding: 12px 20px; min-width: 100px; }
    .bottom-bar-right { gap: 12px; }
}
</style>
</head>
<body>

<!-- ── TOP NAV (index.php style) ── -->
<nav class="top-nav">
    <div class="nav-container">
        <a href="index.php" class="logo">Texcer Hot</a>
        <ul class="nav-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php#menu">Menu</a></li>
            <li><a href="riwayat.php">Pesanan</a></li>
        </ul>
        <div class="nav-icons">
            <button class="nav-icon-btn" onclick="alert('Fitur notifikasi akan segera hadir!')">
                <i class="fas fa-bell"></i>
                <?php if($totalNotifications > 0): ?>
                <span class="notif-count"><?= $totalNotifications ?></span>
                <?php endif; ?>
            </button>
            <a href="checkout.php">
                <i class="fas fa-shopping-bag"></i>
                <?php if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                <span class="cart-badge-nav"><?= count($_SESSION['cart']) ?></span>
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

<!-- ── PAGE HEADER ── -->
<div class="page-header">
    <div class="page-header-inner">
        <div>
            <div class="page-title">
                Keranjang
                <?php if($total_items > 0): ?>
                <span class="page-title-count">(<?= $total_items ?>)</span>
                <?php endif; ?>
            </div>
            <?php if($default_addr): ?>
            <div class="page-location">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars(substr($default_addr['full_address'], 0, 40)) ?>...
            </div>
            <?php endif; ?>
        </div>
        <a href="alamat.php" class="page-edit">Edit</a>
    </div>
</div>

<!-- ── MAIN CONTENT ── -->
<div class="cart-layout">

<?php if(!empty($_SESSION['cart'])): ?>

    <!-- Voucher strip -->
    <div class="voucher-strip" onclick="alert('Fitur voucher segera hadir!')">
        <div class="voucher-left">
            <div class="voucher-icon"><i class="fas fa-ticket-alt"></i></div>
            Semua voucher
        </div>
        <i class="fas fa-chevron-right" style="color: var(--text-gray); font-size: 0.8rem;"></i>
    </div>

    <!-- Shop Group -->
    <div class="shop-group">

        <!-- Shop Header -->
        <div class="shop-header">
            <input type="checkbox" class="shop-check" id="shopCheck" checked onchange="toggleShop(this)">
            <span class="shop-power-badge">Hot</span>
            <a href="index.php" class="shop-name-link">
                Texcer Hot <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <!-- Promo Strip -->
        <div class="promo-strip">
            <div class="promo-strip-left">
                <i class="fas fa-tag tag"></i>
                Beli 3, diskon 5%
            </div>
            <i class="fas fa-chevron-right chev"></i>
        </div>

        <!-- Cart Items -->
        <?php foreach($_SESSION['cart'] as $index => $item):
            $subtotal = $item['price'] * $item['qty'];
            $originalPrice = $item['price'] * 1.25; // simulasi harga asli
            $discount = 20;
        ?>
        <div class="cart-item" id="item-<?= $index ?>">
            <input type="checkbox" class="item-check item-checkbox" checked
                onchange="updateTotal()" data-price="<?= $subtotal ?>">

            <?php if(!empty($item['image'])): ?>
            <div class="item-image" onclick="window.location='index.php'">
                <img src="<?= htmlspecialchars($item['image']) ?>"
                     alt="<?= htmlspecialchars($item['name']) ?>"
                     onerror="this.parentElement.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:2rem;color:#8B6F4E;opacity:.4\'><i class=\'fas fa-utensils\'></i></div>'">
            </div>
            <?php else: ?>
            <div class="item-image-placeholder"><i class="fas fa-utensils"></i></div>
            <?php endif; ?>

            <div class="item-body">
                <?php if(in_array($item['name'], ['Ceker Mercon Tanpa Tulang','Pangsit Isi Ayam','Wonton Goreng/Rebus'])): ?>
                <span class="preorder-tag">Pre-order</span><br>
                <?php endif; ?>

                <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>

                <?php if(!empty($item['variant'])): ?>
                <div class="variant-pill">
                    <?= htmlspecialchars($item['variant']) ?>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <?php endif; ?>

                <!-- Flash sale (only for first few items for demo) -->
                <?php if($index < 2): ?>
                <div class="flash-sale-tag">
                    <i class="fas fa-bolt"></i>
                    Flash Sale
                    <span class="flash-sale-countdown" id="countdown-<?= $index ?>">--:--:--</span>
                </div>
                <?php endif; ?>

                <div class="item-price-row">
                    <span class="item-price">Rp<?= number_format($item['price'], 0, ',', '.') ?></span>
                    <span class="item-original-price">Rp<?= number_format($originalPrice, 0, ',', '.') ?></span>
                    <span class="discount-badge">-<?= $discount ?>%</span>
                </div>

                <div class="sold-count"><?= rand(5,50) ?> terjual kemarin</div>

                <div class="bonus-line">
                    <i class="fas fa-gift"></i>
                    Dapatkan diskon 8% dengan bonus
                </div>

                <!-- Qty Control -->
                <div class="qty-row">
                    <button class="qty-btn minus"
                        onclick="updateQty(<?= $index ?>, -1)"
                        title="Kurangi">−</button>
                    <div class="qty-value" id="qty-<?= $index ?>"><?= $item['qty'] ?></div>
                    <button class="qty-btn plus"
                        onclick="updateQty(<?= $index ?>, 1)"
                        title="Tambah">+</button>
                </div>
            </div>
        </div>

        <!-- Diskon voucher strip per item -->
        <div class="promo-strip" style="padding-left: 48px;">
            <div class="promo-strip-left" style="color: var(--text-gray);">
                <i class="fas fa-ticket-alt tag" style="color: var(--red);"></i>
                Diskon s.d. 4% dengan voucher
            </div>
            <i class="fas fa-chevron-right chev"></i>
        </div>

        <?php endforeach; ?>

    </div>

<?php else: ?>

    <!-- Empty Cart -->
    <div class="empty-cart">
        <i class="fas fa-shopping-bag"></i>
        <h3>Keranjang Kosong</h3>
        <p>Yuk, pesan makanan pedas favoritmu sekarang!</p>
        <a href="index.php" class="btn-shop">
            <i class="fas fa-fire me-2"></i>Mulai Pesan
        </a>
    </div>

<?php endif; ?>

</div>

<!-- ── BOTTOM BAR ── -->
<div class="bottom-bar">
    <div class="bottom-bar-left">
        <input type="checkbox" class="select-all-check" id="selectAll" checked onchange="toggleSelectAll()">
        <label for="selectAll" class="select-all-label">Semua</label>
    </div>
    <div class="bottom-bar-right">
        <div style="text-align: right;">
            <div class="bottom-total-label">Total</div>
            <div class="bottom-total-value" id="bottomTotal">
                Rp<?= number_format($grand_total, 0, ',', '.') ?>
            </div>
        </div>
        <?php if(!empty($_SESSION['cart'])): ?>
        <button class="checkout-btn" onclick="showCheckoutModal()">
            Checkout (<?= count($_SESSION['cart']) ?>)
        </button>
        <?php else: ?>
        <button class="checkout-btn" disabled>Checkout</button>
        <?php endif; ?>
    </div>
</div>

<!-- ── MODAL CHECKOUT ── -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Checkout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">

                    <!-- Alamat -->
                    <div class="co-section">
                        <div class="co-section-title"><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</div>
                        <a href="alamat.php" class="btn w-100 mb-3" style="border: 2px dashed var(--border); color: var(--primary); border-radius: 10px; font-weight: 600; padding: 10px;">
                            <i class="fas fa-plus-circle me-2"></i>Kelola Alamat
                        </a>
                        <?php if(!empty($addresses)): ?>
                            <?php foreach($addresses as $i => $addr): ?>
                            <div class="address-card <?= $addr['is_default'] ? 'selected' : '' ?>" onclick="selectAddress(<?= $i ?>)">
                                <div class="check-dot"></div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-dark);"><?= htmlspecialchars($addr['customer_name']) ?></div>
                                <div style="font-size: 0.82rem; color: var(--text-gray); margin-bottom: 4px;">(+62)<?= substr($addr['customer_phone'], -10) ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-dark);"><?= nl2br(htmlspecialchars($addr['full_address'])) ?></div>
                                <div style="font-size: 0.82rem; color: var(--text-gray);"><?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['province']) ?></div>
                                <?php if($addr['is_default']): ?>
                                <span style="background: var(--primary); color: white; font-size: 0.72rem; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 6px;">Default</span>
                                <?php endif; ?>
                                <a href="alamat.php?edit=<?= $addr['id'] ?>" style="position: absolute; top: 12px; right: 36px; color: var(--primary); font-size: 0.82rem; font-weight: 600;" onclick="event.stopPropagation()">Edit</a>
                                <input type="hidden" name="cust_name_<?= $i ?>" value="<?= htmlspecialchars($addr['customer_name']) ?>">
                                <input type="hidden" name="cust_phone_<?= $i ?>" value="<?= htmlspecialchars($addr['customer_phone']) ?>">
                                <input type="hidden" name="cust_address_<?= $i ?>" value="<?= htmlspecialchars($addr['full_address']) ?>">
                                <input type="hidden" name="cust_city_<?= $i ?>" value="<?= htmlspecialchars($addr['city']) ?>">
                                <input type="hidden" name="cust_province_<?= $i ?>" value="<?= htmlspecialchars($addr['province']) ?>">
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 24px; color: var(--text-gray); font-size: 0.88rem;">
                                <i class="fas fa-map-marker-alt fa-2x mb-2" style="color: var(--border);"></i>
                                <p>Belum ada alamat. <a href="alamat.php" style="color: var(--primary); font-weight: 600;">Tambahkan</a></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="cust_name" id="final_name">
                    <input type="hidden" name="cust_phone" id="final_phone">
                    <input type="hidden" name="cust_address" id="final_address">
                    <input type="hidden" name="cust_city" id="final_city">
                    <input type="hidden" name="cust_province" id="final_province">

                    <!-- Pembayaran -->
                    <div class="co-section">
                        <div class="co-section-title"><i class="fas fa-wallet"></i> Metode Pembayaran</div>
                        <div class="payment-card selected" onclick="selectPayment('cod', this)">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:46px;height:46px;background:linear-gradient(135deg,#26aa99,#55efc4);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.3rem;">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div style="font-weight:700;font-size:0.95rem;color:var(--text-dark);">COD (Bayar di Tempat)</div>
                                    <div style="font-size:0.8rem;color:var(--text-gray);">Bayar saat pesanan diterima</div>
                                </div>
                                <input type="radio" name="payment_method" value="COD" checked style="accent-color:var(--primary);width:18px;height:18px;">
                            </div>
                        </div>
                        <div class="payment-card" onclick="selectPayment('qris', this)">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:46px;height:46px;background:linear-gradient(135deg,#8B6F4E,#D4A574);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.3rem;">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div style="font-weight:700;font-size:0.95rem;color:var(--text-dark);">QRIS</div>
                                    <div style="font-size:0.8rem;color:var(--text-gray);">Scan QR Code untuk bayar</div>
                                </div>
                                <input type="radio" name="payment_method" value="QRIS" style="accent-color:var(--primary);width:18px;height:18px;">
                            </div>
                            <?php if(isset($_SESSION['qris_image'])): ?>
                            <div class="text-center mt-3">
                                <img src="<?= $_SESSION['qris_image'] ?>" alt="QRIS" style="max-width:180px;border:2px solid var(--primary);border-radius:8px;">
                            </div>
                            <?php else: ?>
                            <div class="text-center mt-3 p-3" style="border:2px dashed var(--border);border-radius:8px;cursor:pointer;" onclick="document.getElementById('qrisInput').click()">
                                <i class="fas fa-cloud-upload-alt fa-2x mb-2" style="color:var(--text-gray);"></i>
                                <div style="font-size:0.82rem;color:var(--text-gray);">Klik untuk upload QRIS</div>
                                <input type="file" id="qrisInput" name="qris_image" accept="image/*" style="display:none;" onchange="this.form.submit()">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Catatan -->
                    <div class="co-section">
                        <div class="co-section-title"><i class="fas fa-sticky-note"></i> Catatan</div>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Catatan pesanan (opsional)"
                            style="border:1.5px solid var(--border);border-radius:8px;font-size:0.9rem;color:var(--text-dark);resize:none;"></textarea>
                    </div>

                    <!-- Total -->
                    <div class="total-summary">
                        <div class="total-row">
                            <span class="label">Subtotal (<?= $total_items ?> item)</span>
                            <span>Rp<?= number_format($grand_total ?? 0, 0, ',', '.') ?></span>
                        </div>
                        <div class="total-row">
                            <span class="label">Ongkir</span>
                            <span class="value-green">Gratis</span>
                        </div>
                        <div class="total-row">
                            <span>Total Bayar</span>
                            <span class="value-big">Rp<?= number_format($grand_total ?? 0, 0, ',', '.') ?></span>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" name="process_order" class="btn-submit-order">
                        <i class="fas fa-check me-2"></i>Buat Pesanan
                    </button>

                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── QTY UPDATE ──
function updateQty(index, change) {
    window.location.href = `?action=${change > 0 ? 'plus' : 'minus'}&index=${index}`;
}

// ── SHOW CHECKOUT MODAL ──
function showCheckoutModal() {
    const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    modal.show();
}

// ── SELECT ADDRESS ──
function selectAddress(index) {
    document.querySelectorAll('.address-card').forEach(el => el.classList.remove('selected'));
    const cards = document.querySelectorAll('.address-card');
    if(cards[index]) {
        cards[index].classList.add('selected');
        document.getElementById('final_name').value    = document.querySelector(`input[name="cust_name_${index}"]`)?.value || '';
        document.getElementById('final_phone').value   = document.querySelector(`input[name="cust_phone_${index}"]`)?.value || '';
        document.getElementById('final_address').value = document.querySelector(`input[name="cust_address_${index}"]`)?.value || '';
        document.getElementById('final_city').value    = document.querySelector(`input[name="cust_city_${index}"]`)?.value || '';
        document.getElementById('final_province').value= document.querySelector(`input[name="cust_province_${index}"]`)?.value || '';
    }
}

// ── SELECT PAYMENT ──
function selectPayment(method, element) {
    document.querySelectorAll('.payment-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input[type="radio"]').checked = true;
}

// ── TOGGLE SHOP CHECKBOX ──
function toggleShop(shopCheck) {
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.checked = shopCheck.checked;
    });
    document.getElementById('selectAll').checked = shopCheck.checked;
    updateTotal();
}

// ── TOGGLE SELECT ALL ──
function toggleSelectAll() {
    const all = document.getElementById('selectAll').checked;
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = all);
    document.getElementById('shopCheck') && (document.getElementById('shopCheck').checked = all);
    updateTotal();
}

// ── UPDATE TOTAL ──
function updateTotal() {
    let total = 0;
    let count = 0;
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        if(cb.checked) {
            // Get subtotal from data-price attribute (set at render time)
            total += parseInt(cb.dataset.price) || 0;
            count++;
        }
    });
    document.getElementById('bottomTotal').innerText = 'Rp' + total.toLocaleString('id-ID');
    const btn = document.querySelector('.checkout-btn');
    if(btn) btn.textContent = count > 0 ? `Checkout (${count})` : 'Checkout';
    if(btn) btn.disabled = count === 0;
}

// ── FLASH SALE COUNTDOWN ──
function startCountdown() {
    // Set end time ~3 hours from now for demo
    const endTime = new Date().getTime() + (3 * 60 * 60 * 1000);
    setInterval(() => {
        const now = new Date().getTime();
        const diff = endTime - now;
        if(diff <= 0) return;
        const h = Math.floor(diff / (1000*60*60));
        const m = Math.floor((diff % (1000*60*60)) / (1000*60));
        const s = Math.floor((diff % (1000*60)) / 1000);
        const str = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        document.querySelectorAll('[id^="countdown-"]').forEach(el => el.textContent = str);
    }, 1000);
}

// ── INIT ──
document.addEventListener('DOMContentLoaded', () => {
    // Auto-select default address
    const defaultAddr = document.querySelector('.address-card.selected');
    if(defaultAddr) {
        const idx = Array.from(document.querySelectorAll('.address-card')).indexOf(defaultAddr);
        selectAddress(idx);
    }
    // Sync item checkboxes
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.addEventListener('change', updateTotal);
    });
    startCountdown();
});
</script>
</body>
</html>
