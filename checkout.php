<?php 
session_start(); // Pastikan session dimulai
include 'config.php'; 

// ==========================================
// KONFIGURASI NOMOR WHATSAPP ADMIN
// ==========================================
$ADMIN_WA_NUMBER = '62895618033060'; // Nomor Admin Texcer Hot

// Fungsi Helper untuk membersihkan nomor HP format Indonesia
function cleanPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        $phone = '62' . substr($phone, 1);
    }
    return $phone;
}

// Fungsi Kirim Notifikasi WhatsApp
function sendWhatsAppNotification($phone, $message) {
    $cleanPhone = cleanPhoneNumber($phone);
    $encodedMessage = urlencode($message);
    
    // OPSI 1: Menggunakan API Gateway (Contoh: Fonnte, Wablas)
    // Uncomment dan isi Token jika punya API agar notifikasi otomatis terkirim
    /*
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send', 
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => array(
            'target' => $cleanPhone,
            'message' => urldecode($encodedMessage),
        ),
        CURLOPT_HTTPHEADER => array(
            'Authorization: TOKEN_API_ANDA_DISINI' 
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
    */

    // OPSI 2: Fallback (Kosongkan jika tidak ada API, notifikasi hanya tercatat di log/server)
    return true;
}

// Fungsi Notifikasi Order Created
function notifyOrderCreated($conn, $phone, $order_id, $order_number, $total, $items, $address) {
    global $ADMIN_WA_NUMBER;

    // 1. Format Rincian Item
    $itemList = "";
    foreach($items as $item) {
        $variant = !empty($item['variant']) ? " ({$item['variant']})" : "";
        $itemList .= "- {$item['name']}{$variant} x{$item['qty']}\n";
    }

    // 2. Pesan untuk ADMIN
    $adminMessage = "🔔 *PESANAN BARU MASUK!*\n\n"
                  . "No Order: #{$order_number}\n"
                  . "Nama: {$items[0]['customer_name']}\n"
                  . "HP: {$phone}\n"
                  . "Alamat: {$address}\n\n"
                  . "*Rincian Pesanan:*\n"
                  . "{$itemList}\n"
                  . "*Total Bayar: Rp " . number_format($total, 0, ',', '.') . "*\n"
                  . "Metode: COD\n\n"
                  . "Mohon segera diproses.";
    
    sendWhatsAppNotification($ADMIN_WA_NUMBER, $adminMessage);

    // 3. Pesan untuk PEMBELI
    $buyerMessage = "Terima kasih! 🙏\n\n"
                  . "Pesanan Anda telah dibuat.\n"
                  . "No Order: #{$order_number}\n\n"
                  . "*Siapkan dana sebesar Rp " . number_format($total, 0, ',', '.') . "*\n"
                  . "untuk pembayaran COD saat pesanan diterima.\n\n"
                  . "Kami akan segera memproses pesanan Anda. Terima kasih telah berbelanja di Texcer Hot! 🌶️";
    
    sendWhatsAppNotification($phone, $buyerMessage);
}

$grand_total = 0;
$total_items = 0;

// 1. Logika Update Quantity, Hapus, & GANTI VARIAN
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
        } elseif($action == 'change_variant'){
            // Logika Ganti Varian
            $newVariant = isset($_GET['variant']) ? $_GET['variant'] : 'Regular';
            $_SESSION['cart'][$index]['variant'] = $newVariant;
        }
    }
    header("Location: checkout.php");
    exit;
}

// 2. Logika Checkout (HANYA COD)
if(isset($_POST['process_order'])){
    $name = $_POST['cust_name'];
    $phone = $_POST['cust_phone'];
    $_SESSION['user_phone'] = $phone;
    $address = $_POST['cust_address'];
    $city = $_POST['cust_city'];
    $province = $_POST['cust_province'];
    
    $payment_method = 'COD'; 
    
    $notes = $_POST['notes'];
    $total = 0;
    
    if(!empty($_SESSION['cart'])){
        foreach($_SESSION['cart'] as $item) $total += ((float)$item['price'] * (int)$item['qty']);
        
        $sql_order = "INSERT INTO orders (customer_name, customer_phone, customer_address, customer_city, customer_province, total_price, status, payment_method) 
                      VALUES ('$name', '$phone', '$address', '$city', '$province', '$total', 'Menunggu Konfirmasi', '$payment_method')";
        
        if(mysqli_query($conn, $sql_order)){
            $order_id = mysqli_insert_id($conn);
            $order_number = 'ORD' . str_pad($order_id, 10, '0', STR_PAD_LEFT);
            mysqli_query($conn, "UPDATE orders SET order_number = '$order_number' WHERE id = $order_id");
            
            $itemsForNotif = [];
            foreach($_SESSION['cart'] as $item){
                $sub = (float)$item['price'] * (int)$item['qty'];
                $variant = isset($item['variant']) ? $item['variant'] : 'Regular';
                $image   = isset($item['image']) ? $item['image'] : '';
                
                mysqli_query($conn, "INSERT INTO order_items (order_id, product_name, price, qty, subtotal, variant, image) 
                VALUES ('$order_id', '{$item['name']}', '{$item['price']}', '{$item['qty']}', '$sub', '$variant', '$image')");
                
                $itemsForNotif[] = [
                    'name' => $item['name'],
                    'variant' => $variant,
                    'qty' => $item['qty'],
                    'customer_name' => $name,
                    'customer_address' => $address
                ];
            }
            
            notifyOrderCreated($conn, $phone, $order_id, $order_number, $total, $itemsForNotif, $address);
            
            unset($_SESSION['cart']);
            header("Location: checkout.php?success=1&oid={$order_id}&total={$total}");
            exit;
        }
    }
}

if(isset($_GET['success']) && $_GET['success'] == '1'){
    $oid = $_GET['oid'] ?? '';
    $total = $_GET['total'] ?? 0;
    
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
    Swal.fire({
        icon: 'success',
        title: '🎉 Pesanan Berhasil!',
        text: 'Terima kasih! Pesanan No: {$oid} telah dibuat. Siapkan dana Rp " . number_format($total, 0, ',', '.') . " untuk COD.',
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

$totalNotifications = 0;
if(isset($_SESSION['user_phone'])){
    $ph = $_SESSION['user_phone'];
    $nq = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE customer_phone = '$ph' AND status IN ('Menunggu Konfirmasi', 'Dikirim')");
    $totalNotifications = mysqli_fetch_assoc($nq)['total'];
}

if(!empty($_SESSION['cart'])){
    foreach($_SESSION['cart'] as $item){
        $grand_total += ((float)$item['price'] * (int)$item['qty']);
        $total_items += (int)$item['qty'];
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
    --green: #26aa99;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    background: #f5f5f5;
    color: var(--text-dark);
    font-family: 'Segoe UI', system-ui, sans-serif;
    min-height: 100vh;
}

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

/* Page Header */
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

/* Main Layout */
.cart-layout {
    max-width: 900px;
    margin: 0 auto;
    padding: 12px 16px 120px;
}

/* Shop Group */
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

/* Cart Item */
.cart-item {
    padding: 14px 16px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    border-bottom: 1px solid #f5f0eb;
    transition: background .15s;
    position: relative; /* Untuk posisi tombol hapus jika perlu */
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

/* Style Baru untuk Gambar Klikable */
.item-image-wrapper {
    width: 110px;
    height: 110px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--bg-cream);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: transform .2s;
    position: relative;
}
.item-image-wrapper:hover { 
    transform: scale(1.03); 
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.item-image-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.zoom-hint {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0,0,0,0.6);
    color: white;
    width: 30px; height: 30px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    opacity: 0;
    transition: opacity 0.2s;
}
.item-image-wrapper:hover .zoom-hint { opacity: 1; }

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

/* Style Baru untuk Dropdown Varian */
.variant-select {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 0.8rem;
    color: var(--text-dark);
    margin-bottom: 8px;
    cursor: pointer;
    outline: none;
}
.variant-select:focus { border-color: var(--primary); }
.variant-select option { padding: 5px; }

/* Price row - Cleaned up */
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
    color: var(--primary);
}

.sold-count {
    font-size: 0.78rem;
    color: var(--text-gray);
    margin-bottom: 4px;
}

/* Qty Control */
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

/* Empty Cart */
.empty-cart {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    text-align: center !important;
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all .2s;
    margin: 0 auto;
}

.btn-shop:hover { background: var(--primary-dark); color: white; transform: translateY(-2px); }

/* Bottom Bar */
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

/* Modal Checkout */
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

/* Responsive */
@media (max-width: 992px) {
    .nav-menu { display: none; }
    .top-nav { padding: 14px 20px; }
    .page-header { top: 58px; }
}
@media (max-width: 600px) {
    .item-image-wrapper, .item-image-placeholder { width: 90px; height: 90px; }
    .checkout-btn { padding: 12px 20px; min-width: 100px; }
    .bottom-bar-right { gap: 12px; }
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
            <li><a href="riwayat.php">Pesanan</a></li>
        </ul>
        <div class="nav-icons">
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

<!-- Page Header -->
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

<!-- Main Content -->
<div class="cart-layout">

<?php if(!empty($_SESSION['cart'])): ?>

    <!-- ✅ VOUCHER STRIP DIHAPUS -->

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

        <!-- ✅ PROMO STRIP (BELI 3 DISKON) DIHAPUS -->

        <!-- Cart Items -->
        <?php foreach($_SESSION['cart'] as $index => $item):
            $itemPrice    = (float)$item['price'];
            $itemQty      = (int)$item['qty'];
            $subtotal     = $itemPrice * $itemQty;
            $currentVariant = isset($item['variant']) ? $item['variant'] : 'Regular';
            
            // Daftar opsi varian (Hardcoded untuk contoh, bisa dinamis dari DB jika perlu)
            $variants = ['Regular', 'Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5', 'Large', 'Medium', 'Paket Nasi'];
        ?>
        <div class="cart-item" id="item-<?= $index ?>">
            <input type="checkbox" class="item-check item-checkbox" checked
                onchange="updateTotal()" data-price="<?= $subtotal ?>">

            <!-- ✅ GAMBAR KLIKABLE UNTUK DETAIL -->
            <?php if(!empty($item['image'])): ?>
            <div class="item-image-wrapper" onclick="showDetailModal(<?= $index ?>)">
                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <div class="zoom-hint"><i class="fas fa-search-plus"></i></div>
            </div>
            <?php else: ?>
            <div class="item-image-placeholder" onclick="showDetailModal(<?= $index ?>)" style="cursor:pointer;">
                <i class="fas fa-utensils"></i>
            </div>
            <?php endif; ?>

            <div class="item-body">
                <?php if(in_array($item['name'], ['Ceker Mercon Tanpa Tulang','Pangsit Isi Ayam','Wonton Goreng/Rebus'])): ?>
                <span class="preorder-tag">Pre-order</span><br>
                <?php endif; ?>

                <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>

                <!-- ✅ DROPDOWN GANTI VARIAN -->
                <select class="variant-select" onchange="changeVariant(<?= $index ?>, this.value)">
                    <?php foreach($variants as $v): ?>
                        <option value="<?= $v ?>" <?= ($currentVariant == $v) ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="item-price-row">
                    <span class="item-price">Rp<?= number_format($itemPrice, 0, ',', '.') ?></span>
                </div>

                <div class="sold-count"><?= rand(5,50) ?> terjual kemarin</div>

                <!-- Qty Control -->
                <div class="qty-row">
                    <button class="qty-btn minus"
                        onclick="updateQty(<?= $index ?>, -1)"
                        title="Kurangi">−</button>
                    <div class="qty-value" id="qty-<?= $index ?>"><?= $itemQty ?></div>
                    <button class="qty-btn plus"
                        onclick="updateQty(<?= $index ?>, 1)"
                        title="Tambah">+</button>
                </div>
            </div>
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
        Mulai Pesan
        </a>
    </div>

<?php endif; ?>

</div>

<!-- Bottom Bar -->
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

<!-- Modal Checkout -->
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

                    <!-- Pembayaran (HANYA COD) -->
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
                            <span>Rp<?= number_format($grand_total, 0, ',', '.') ?></span>
                        </div>
                        <div class="total-row">
                            <span class="label">Ongkir</span>
                            <span class="value-green">Gratis</span>
                        </div>
                        <div class="total-row">
                            <span>Total Bayar</span>
                            <span class="value-big">Rp<?= number_format($grand_total, 0, ',', '.') ?></span>
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

<!-- ✅ MODAL DETAIL PRODUK BARU -->
<div class="modal fade" id="detailProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Detail Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <img id="modalDetailImg" src="" class="img-fluid rounded mb-3" style="max-height: 250px; object-fit: contain;">
                <h4 id="modalDetailName" class="mb-1"></h4>
                <p id="modalDetailVariant" class="text-muted small mb-2"></p>
                <h3 id="modalDetailPrice" class="text-primary fw-bold mb-3"></h3>
                <div class="d-flex justify-content-center align-items-center gap-3 mb-4">
                    <span class="fw-bold">Jumlah:</span>
                    <span id="modalDetailQty" class="badge bg-secondary fs-6"></span>
                </div>
                <hr>
                <button onclick="deleteFromModal()" class="btn btn-danger w-100">
                    <i class="fas fa-trash me-2"></i>Hapus Item Ini
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Variabel global untuk menyimpan index item yang sedang dilihat di modal
let currentDetailIndex = null;

// QTY UPDATE
function updateQty(index, change) {
    window.location.href = `?action=${change > 0 ? 'plus' : 'minus'}&index=${index}`;
}

// ✅ FUNGSI GANTI VARIAN
function changeVariant(index, newVariant) {
    window.location.href = `?action=change_variant&index=${index}&variant=${encodeURIComponent(newVariant)}`;
}

// ✅ FUNGSI TAMPILKAN MODAL DETAIL
function showDetailModal(index) {
    currentDetailIndex = index;
    
    // Ambil data dari elemen HTML yang sudah ada
    const itemRow = document.querySelectorAll('.cart-item')[index];
    const imgSrc = itemRow.querySelector('.item-image-wrapper img')?.src || itemRow.querySelector('.item-image-placeholder') ? '' : '';
    const name = itemRow.querySelector('.item-name').innerText;
    const variant = itemRow.querySelector('.variant-select').value;
    const price = itemRow.querySelector('.item-price').innerText;
    const qty = itemRow.querySelector('.qty-value').innerText;
    
    // Jika placeholder, gunakan gambar default atau kosong
    let finalImg = imgSrc;
    if(itemRow.querySelector('.item-image-placeholder')) {
        finalImg = 'assets/images/placeholder.png'; // Ganti dengan path placeholder Anda
    }

    document.getElementById('modalDetailImg').src = finalImg;
    document.getElementById('modalDetailName').innerText = name;
    document.getElementById('modalDetailVariant').innerText = "Varian: " + variant;
    document.getElementById('modalDetailPrice').innerText = price;
    document.getElementById('modalDetailQty').innerText = qty;
    
    const modal = new bootstrap.Modal(document.getElementById('detailProductModal'));
    modal.show();
}

// ✅ FUNGSI HAPUS DARI MODAL
function deleteFromModal() {
    if(currentDetailIndex !== null) {
        if(confirm('Yakin ingin menghapus item ini dari keranjang?')) {
            window.location.href = `?action=remove&index=${currentDetailIndex}`;
        }
    }
}

// SHOW CHECKOUT MODAL
function showCheckoutModal() {
    const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    modal.show();
}

// SELECT ADDRESS
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

// SELECT PAYMENT
function selectPayment(method, element) {
    document.querySelectorAll('.payment-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input[type="radio"]').checked = true;
}

// TOGGLE SHOP CHECKBOX
function toggleShop(shopCheck) {
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.checked = shopCheck.checked;
    });
    document.getElementById('selectAll').checked = shopCheck.checked;
    updateTotal();
}

// TOGGLE SELECT ALL
function toggleSelectAll() {
    const all = document.getElementById('selectAll').checked;
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = all);
    document.getElementById('shopCheck') && (document.getElementById('shopCheck').checked = all);
    updateTotal();
}

// UPDATE TOTAL
function updateTotal() {
    let total = 0;
    let count = 0;
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        if(cb.checked) {
            total += parseInt(cb.dataset.price) || 0;
            count++;
        }
    });
    document.getElementById('bottomTotal').innerText = 'Rp' + total.toLocaleString('id-ID');
    const btn = document.querySelector('.checkout-btn');
    if(btn) btn.textContent = count > 0 ? `Checkout (${count})` : 'Checkout';
    if(btn) btn.disabled = count === 0;
}

// INIT
document.addEventListener('DOMContentLoaded', () => {
    const defaultAddr = document.querySelector('.address-card.selected');
    if(defaultAddr) {
        const idx = Array.from(document.querySelectorAll('.address-card')).indexOf(defaultAddr);
        selectAddress(idx);
    }
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.addEventListener('change', updateTotal);
    });
});
</script>
</body>
</html>