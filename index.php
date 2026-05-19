<?php include 'config.php';


// 🔥 AMBIL SETTINGS + STORE PROFILE DARI DATABASE 🔥
$settings = [];
$profile = [];

// Ambil settings
$settings_query = mysqli_query($conn, "SELECT * FROM settings WHERE id=1 LIMIT 1");
if($settings_query && mysqli_num_rows($settings_query) > 0){
    $settings = mysqli_fetch_assoc($settings_query);
}

// Ambil store profile
$profile_query = mysqli_query($conn, "SELECT * FROM store_profile WHERE id=1 LIMIT 1");
if($profile_query && mysqli_num_rows($profile_query) > 0){
    $profile = mysqli_fetch_assoc($profile_query);
} else {
    // Fallback jika tidak ada data profile
    $profile = [
        'store_name' => 'Texcer Hot',
        'tagline' => 'TEMAN PEDASMU 🌶️',
        'description' => 'Pusat kuliner pedas dan nikmat di Jepara.',
        'logo_path' => 'assets/images/logo-texcer.png',
        'cover_path' => 'assets/images/cover-texcer.jpg',
        'address' => 'Depan SMPN 1 Bangsri, Jepara',
        'whatsapp' => '6281934174198',
        'email' => 'info@texcerhot.com',
        'instagram' => 'texcer.hot',
        'tiktok' => 'texcer.hot',
        'opening_hours' => '11:00 - 20:00 WIB',
        'latitude' => -6.52364605,
        'longitude' => 110.76685962,
        'rating_avg' => 4.8,
        'total_reviews' => 100,
        'established_year' => 2020
    ];
}
// Contoh di admin.php saat update status
if(isset($_POST['update_status'])){
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $resi_number = $_POST['resi_number'] ?? '';
    // Ambil data pesanan
    $order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id = $order_id"));
    // Update status
    mysqli_query($conn, "UPDATE orders SET status = '$new_status', resi_number = '$resi_number' WHERE id = $order_id");
    // Buat notifikasi berdasarkan status
    switch($new_status){
        case 'Dikirim':
            notifyOrderShipped($conn, $order['customer_phone'], $order_id, $resi_number);
            if($order['payment_method'] == 'COD'){
                notifyCODPayment($conn, $order['customer_phone'], $order_id, $order['total_price'], $resi_number);
            }
            break;
        case 'Selesai':
            notifyOrderDelivered($conn, $order['customer_phone'], $order_id, $resi_number);
            break;
    }
    header("Location: admin.php?success=1");
    exit;
}

// Di index.php - Ganti seluruh blok if(isset($_POST['add_to_cart']))

if(isset($_POST['add_to_cart'])){
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    
    // Ambil varian dari input hidden (yang diisi oleh JavaScript omSubmit)
    // Jika kosong, beri default 'Regular'
    $variant = isset($_POST['variant']) && !empty($_POST['variant']) ? $_POST['variant'] : 'Regular';

    // Buat kunci unik untuk item keranjang: ID_PRODUK_VARIAN
    // Contoh: 5_Level2-Bakso
    $unique_key = $id . '_' . str_replace(' ', '', $variant);

    $cart_item = [
        'id' => $id, 
        'name' => $name, 
        'price' => $price, 
        'qty' => 1, 
        'variant' => $variant,
        'image' => $_POST['image'] ?? '', // Pastikan image juga dikirim jika ada
        'unique_key' => $unique_key
    ];

    $found = false;
    if(isset($_SESSION['cart'])){
        foreach($_SESSION['cart'] as $key => $item){
            // CEK BERDASARKAN UNIQUE KEY (ID + VARIAN)
            // Bukan cuma ID!
            if(isset($item['unique_key']) && $item['unique_key'] == $unique_key){
                $_SESSION['cart'][$key]['qty']++;
                $found = true;
                break;
            }
        }
    }

    if(!$found){
        // Tambahkan sebagai item BARU di array (akan muncul paling atas/terakhir tergantung loop)
        // Untuk muncul "di atasnya", kita bisa pakai array_unshift, tapi biasanya append lebih aman untuk urutan waktu
        $_SESSION['cart'][] = $cart_item;
    }

    // ✅ FIX: Jika "Beli Sekarang", langsung ke checkout
    if(isset($_POST['buy_now']) && $_POST['buy_now'] == '1'){
        header("Location: checkout.php");
    } else {
        header("Location: index.php?added=1");
    }
    exit;
}

// Ambil produk dari database
// Ambil produk dari database (AMBIL SEMUA, JANGAN DIFILTER)
$joinCat = "LEFT JOIN categories c ON p.category_id = c.id";
$selectCat = "c.name as category_name";
// Kita ambil semua produk, nanti kita cek statusnya di HTML
$result = mysqli_query($conn, "SELECT p.*, {$selectCat} FROM products p $joinCat ORDER BY p.created_at DESC");

$products = [];
while($row = mysqli_fetch_assoc($result)){
    // Cek kolom is_available atau stock
    $isAvailable = 1; // Default tersedia
    if(isset($row['is_available'])) {
        $isAvailable = (int)$row['is_available'];
    } elseif(isset($row['stock'])) {
        $isAvailable = ($row['stock'] > 0) ? 1 : 0;
    }

    $products[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'price' => $row['price'],
        'category' => $row['category_name'] ?? 'all',
        'image' => !empty($row['image']) ? 'uploads/' . $row['image'] : '',
        'description' => $row['description'] ?? '',
        'is_available' => $isAvailable, // <--- PENTING: Simpan status ketersediaan
        'has_variant' => false,
        'variants' => [],
        'toppings' => [], // Siapkan array kosong dulu
        'levels' => []    // Siapkan array kosong dulu
    ];
}
// 🔥 Ambil toppings & levels untuk setiap produk
foreach($products as $k => $p){
    $pid = (int)$p['id'];
    
    // Toppings
    $t_query = mysqli_query($conn, "
        SELECT t.id, t.name, t.price
        FROM product_toppings pt
        JOIN toppings t ON t.id = pt.topping_id
        WHERE pt.product_id = $pid AND t.is_active = 1
        ORDER BY t.name ASC
    ");
    $toppings = [];
    if($t_query) while($tr = mysqli_fetch_assoc($t_query)) $toppings[] = $tr;
    $products[$k]['toppings'] = $toppings;
    
    // Levels
    $l_query = mysqli_query($conn, "
        SELECT l.id, l.name, l.price
        FROM product_levels pl
        JOIN levels l ON l.id = pl.level_id
        WHERE pl.product_id = $pid AND l.is_active = 1
        ORDER BY l.sort_order ASC, l.id ASC
    ");
    $levels = [];
    if($l_query) while($lr = mysqli_fetch_assoc($l_query)) $levels[] = $lr;
    $products[$k]['levels'] = $levels;
}
$currentPage = basename($_SERVER['PHP_SELF']);

// Ambil hero images dari database
$heroImages = [];
$hero_query = mysqli_query($conn, "SELECT * FROM site_images WHERE section='hero' AND is_active=1 ORDER BY display_order");
while($img = mysqli_fetch_assoc($hero_query)){
    $heroImages[] = $img['image_path'];
}

// Fallback jika tidak ada gambar di database
if(empty($heroImages)){
    $heroImages = [
        'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=1200',
        'https://images.unsplash.com/photo-1612929633738-8fe44f7ec841?w=1200',
        'https://images.unsplash.com/photo-1555126634-323283cb0025?w=1200',
        'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1200'
    ];
}

// 🔥 AMBIL TESTIMONIAL DARI DATABASE 🔥
$videoTestimonials = [];
$testimonial_query = mysqli_query($conn, "SELECT * FROM site_images WHERE section='testimonial' AND is_active=1 ORDER BY display_order");

while($t = mysqli_fetch_assoc($testimonial_query)){
    // Generate data untuk tampilan
    $initial = strtoupper(substr($t['title'], 0, 1));
    
    $videoTestimonials[] = [
        'video' => $t['media_type'] === 'video' ? $t['video_url'] : '',
        'image' => $t['image_path'],
        'media_type' => $t['media_type'],
        'thumbnail' => $t['image_path'] ?: 'https://via.placeholder.com/400x300?text=No+Image',
        'user' => $t['title'],
        'initial' => $initial,
        'product' => 'Testimoni Pelanggan',
        'rating' => 5,
        'duration' => '0:30'
    ];
}

// Fallback jika tidak ada testimonial di database
if(empty($videoTestimonials)){
    $videoTestimonials = [
        [
            'video' => 'assets/videos/video1.mp4',
            'thumbnail' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=400',
            'user' => 'Andi Wijaya',
            'initial' => 'A',
            'product' => 'Mie Pedas Level 8',
            'rating' => 5,
            'duration' => '0:45'
        ],
        [
            'video' => 'assets/videos/video2.mp4',
            'thumbnail' => 'https://images.unsplash.com/photo-1612929633738-8fe44f7ec841?w=400',
            'user' => 'Siti Nurhaliza',
            'initial' => 'S',
            'product' => 'Mercon Ayam',
            'rating' => 5,
            'duration' => '1:20'
        ]
    ];
}

// Hitung total notifikasi (contoh: pesanan baru)
$totalNotifications = 0;
if(isset($_SESSION['user_phone'])){
    $phone = $_SESSION['user_phone'];
    $notifQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE customer_phone = '$phone' AND status IN ('Menunggu Konfirmasi', 'Dikirim')");
    $totalNotifications = mysqli_fetch_assoc($notifQuery)['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($settings['site_title'] ?? 'Texcer Hot') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    /* Efek Produk Tidak Tersedia */
.product-card.unavailable {
    opacity: 0.6; /* Membuat transparan/abu-abu */
    filter: grayscale(80%); /* Membuat gambar hitam putih */
    pointer-events: none; /* MENCEGAH KLIK pada area kartu */
    position: relative;
}

/* Overlay Tulisan Stok Habis */
.out-of-stock-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 20px; /* Sesuaikan dengan radius product-card */
}

.out-of-stock-overlay span {
    background: #dc3545; /* Warna Merah */
    color: white;
    padding: 8px 15px;
    font-weight: bold;
    font-size: 0.9rem;
    border-radius: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    transform: rotate(-5deg);
}

/* Aktifkan kembali klik hanya untuk tombol Favorit jika mau */
.product-card.unavailable .favorite-btn {
    pointer-events: auto; 
    z-index: 11;
}
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
    --pink-soft: #FAD4C0;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    background: var(--bg-cream);
    color: var(--text-dark);
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

/* Top Navigation */
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

.nav-menu a:hover, .nav-menu a.active {
    color: var(--primary);
}

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
}

.nav-icons a:hover {
    color: var(--primary);
}

.nav-icon-btn {
    background: none;
    border: none;
    color: var(--text-dark);
    font-size: 1.2rem;
    cursor: pointer;
    position: relative;
    transition: color 0.3s;
}

.nav-icon-btn:hover {
    color: var(--primary);
}

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
    font-weight: 600;
}

.notif-count {
    background: #dc3545;
}

/* ✅ HERO SECTION FULL SCREEN DENGAN AUTO SLIDE */
.hero-section {
    position: relative;
    height: 100vh;
    min-height: 600px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-slider {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.hero-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 1.5s ease-in-out;
}

.hero-slide.active {
    opacity: 1;
}

.hero-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hero-slide::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(61,41,20,0.7) 0%, rgba(250,212,192,0.5) 100%);
}

.hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 900px;
    padding: 0 40px;
}

.hero-title {
    font-size: 5rem;
    font-weight: 900;
    color: white;
    margin-bottom: 20px;
    letter-spacing: 3px;
    line-height: 1.1;
    text-shadow: 3px 3px 10px rgba(0,0,0,0.5);
    animation: fadeInUp 1s ease-out;
}

.hero-subtitle {
    font-size: 1.8rem;
    color: white;
    margin-bottom: 40px;
    font-style: italic;
    text-shadow: 2px 2px 5px rgba(0,0,0,0.3);
    animation: fadeInUp 1s ease-out 0.3s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hero-btn {
    background: var(--primary);
    color: white;
    border: none;
    padding: 20px 60px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 8px 25px rgba(139, 111, 78, 0.4);
    animation: fadeInUp 1s ease-out 0.6s both;
}

.hero-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(139, 111, 78, 0.6);
}

/* Hero Dots Navigation */
.hero-dots {
    position: absolute;
    bottom: 40px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 12px;
    z-index: 10;
}

.hero-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: rgba(255,255,255,0.5);
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid white;
}

.hero-dot.active {
    background: white;
    width: 40px;
    border-radius: 8px;
}

.hero-dot:hover {
    background: white;
}

/* Menu Section */
.menu-section {
    max-width: 1400px;
    margin: 80px auto;
    padding: 0 40px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.section-subtitle {
    font-size: 1.2rem;
    color: var(--text-gray);
    font-style: italic;
}

.my-basket-btn {
    background: var(--secondary);
    color: white;
    padding: 12px 30px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.my-basket-btn:hover {
    background: var(--primary);
    transform: translateY(-2px);
}

/* Category Tabs */
.category-tabs {
    display: flex;
    gap: 15px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}

.tab-btn {
    background: var(--bg-white);
    border: 2px solid var(--border);
    padding: 12px 28px;
    border-radius: 50px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
    color: var(--text-gray);
}

.tab-btn:hover, .tab-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.buy-modal-overlay{
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);
  z-index:9999;align-items:flex-end;justify-content:center;
}
.buy-modal-overlay.show{display:flex;}
.buy-modal{
  background:#fff;width:100%;max-width:480px;max-height:90vh;
  border-radius:18px 18px 0 0;display:flex;flex-direction:column;
  animation:slideUp .25s ease-out;
}
@keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
.buy-modal-close{
  position:absolute;right:16px;top:16px;background:#f3f3f3;border:none;
  width:32px;height:32px;border-radius:50%;font-size:20px;cursor:pointer;z-index:2;
}
.buy-modal-header{display:flex;gap:14px;padding:18px;border-bottom:1px solid #eee;}
.buy-modal-header img{width:80px;height:80px;border-radius:12px;object-fit:cover;background:#f5f5f5;}
.buy-modal-header h3{margin:0 0 6px;font-size:16px;color:#333;}
.bm-price{color:#8B5A2B;font-weight:700;font-size:18px;}
.buy-modal-body{padding:16px 18px;overflow-y:auto;flex:1;}
.bm-section{margin-bottom:18px;}
.bm-section h4{margin:0 0 10px;font-size:14px;color:#555;}
.bm-options{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.bm-option{
  border:1.5px solid #e5e5e5;border-radius:10px;padding:10px 12px;
  cursor:pointer;display:flex;justify-content:space-between;align-items:center;
  font-size:13px;transition:all .15s;background:#fff;
}
.bm-option:hover{border-color:#8B5A2B;}
.bm-option.selected{border-color:#8B5A2B;background:#fdf6ef;color:#8B5A2B;font-weight:600;}
.bm-option .price{font-size:12px;color:#999;}
.bm-option.selected .price{color:#8B5A2B;}
.bm-qty-section{display:flex;justify-content:space-between;align-items:center;}
.bm-qty{display:flex;align-items:center;gap:14px;}
.bm-qty button{
  width:32px;height:32px;border-radius:50%;border:1.5px solid #8B5A2B;
  background:#fff;color:#8B5A2B;font-size:18px;font-weight:700;cursor:pointer;
}
.bm-qty span{font-weight:700;font-size:16px;min-width:24px;text-align:center;}
#bmNote{width:100%;border:1.5px solid #e5e5e5;border-radius:10px;padding:10px;
  resize:none;font-family:inherit;font-size:13px;min-height:60px;}
.buy-modal-footer{
  display:flex;justify-content:space-between;align-items:center;
  padding:14px 18px;border-top:1px solid #eee;gap:12px;
}
.bm-total{color:#8B5A2B;font-weight:800;font-size:20px;}
.bm-checkout-btn{
  background:#8B5A2B;color:#fff;border:none;padding:14px 28px;
  border-radius:30px;font-weight:700;font-size:15px;cursor:pointer;flex-shrink:0;
}
.bm-checkout-btn:hover{background:#6e4621;}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
}

.product-card {
    background: var(--bg-white);
    border: 3px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s;
    cursor: pointer;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
    border-color: var(--secondary);
}

.product-image {
    height: 280px;
    background: var(--bg-cream);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.product-image i {
    font-size: 5rem;
    color: var(--primary);
    opacity: 0.3;
}

.favorite-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: white;
    border: none;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.3s;
    color: var(--text-gray);
    z-index: 10;
}

.favorite-btn i {
    font-size: 1.1rem;
    transition: all 0.3s;
}

.favorite-btn:hover {
    background: var(--accent);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(232, 180, 162, 0.4);
}

.favorite-btn.active {
    background: var(--accent);
    color: white;
}

.favorite-btn.active i {
    color: white;
}

.product-info {
    padding: 25px;
}

.product-name {
    font-weight: 700;
    font-size: 1.2rem;
    margin-bottom: 8px;
    color: var(--text-dark);
}

.product-desc {
    color: var(--text-gray);
    font-size: 0.9rem;
    margin-bottom: 15px;
    line-height: 1.5;
}

.product-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-price {
    color: var(--primary);
    font-weight: 800;
    font-size: 1.3rem;
}

.add-to-cart {
    background: var(--bg-cream);
    color: var(--primary);
    border: 2px solid var(--border);
    padding: 8px 20px;
    border-radius: 50px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.add-to-cart:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Modal Variant Selection */
.variant-option {
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 12px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.variant-option:hover {
    border-color: var(--secondary);
    background: var(--bg-cream);
}

.variant-option.selected {
    border-color: var(--primary);
    background: var(--bg-cream);
}

.variant-option input[type="radio"] {
    margin-right: 10px;
}

/* ✅ VIDEO TESTIMONIALS SECTION DENGAN AUTO SCROLL */
.video-testimonials-section {
    background: var(--bg-white);
    padding: 80px 40px;
    margin-top: 80px;
    overflow: hidden;
}

.testimonials-header {
    text-align: center;
    margin-bottom: 50px;
}

.testimonials-header h2 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 10px;
}

.testimonials-header p {
    color: var(--text-gray);
    font-size: 1.1rem;
}

.video-container {
    position: relative;
    max-width: 1400px;
    margin: 0 auto;
}

.video-grid {
    display: flex;
    gap: 25px;
    transition: transform 0.5s ease;
}

.video-card {
    min-width: 280px;
    background: var(--bg-white);
    border: 2px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
    cursor: pointer;
    flex-shrink: 0;
}

.video-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.15);
    border-color: var(--secondary);
}

.video-wrapper {
    position: relative;
    height: 320px;
    background: var(--bg-cream);
    overflow: hidden;
}

.video-wrapper video,
.video-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.video-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.video-wrapper.playing .video-overlay {
    background: rgba(0,0,0,0.1);
}

.video-play-btn {
    width: 70px;
    height: 70px;
    background: rgba(255,255,255,0.95);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.video-play-btn i {
    color: var(--primary);
    font-size: 1.8rem;
    margin-left: 5px;
}

.video-wrapper.playing .video-play-btn {
    background: var(--primary);
    transform: scale(0.9);
}

.video-wrapper.playing .video-play-btn i {
    color: white;
}

.video-duration {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(0,0,0,0.75);
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
}

.video-info {
    padding: 15px;
}

.video-user {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
}

.user-name {
    font-weight: 600;
    color: var(--text-dark);
    font-size: 0.95rem;
}

.video-product {
    color: var(--text-gray);
    font-size: 0.85rem;
    margin-bottom: 8px;
}

.video-rating {
    color: #ffa500;
    font-size: 0.9rem;
}

.video-rating i {
    margin-right: 2px;
}

/* Video Navigation Arrows */
.video-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    border: 2px solid var(--border);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    z-index: 10;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.video-nav:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.video-nav.prev { left: -25px; }
.video-nav.next { right: -25px; }

/* ✅ FOOTER */
.site-footer {
    background: #3D2914;
    color: rgba(255,255,255,0.75);
    padding: 56px 48px 0;
    margin-top: 80px;
}

.footer-content {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr 1.2fr;
    gap: 40px;
    padding-bottom: 48px;
    border-bottom: 0.5px solid rgba(255,255,255,0.12);
}

.footer-section h3 {
    color: #D4A574;
    font-size: 1.4rem;
    margin-bottom: 14px;
    font-weight: 700;
}

.footer-section p {
    color: rgba(255,255,255,0.65);
    line-height: 1.75;
    margin-bottom: 24px;
    font-size: 0.9rem;
}

.footer-section h4 {
    color: rgba(255,255,255,0.95);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    margin-bottom: 20px;
}

.footer-section a {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(255,255,255,0.62);
    text-decoration: none;
    margin-bottom: 13px;
    font-size: 0.875rem;
    transition: color 0.2s;
}

.footer-section a:hover {
    color: #D4A574;
    padding-left: 0;
}

.footer-section a svg,
.footer-section span svg {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    stroke: rgba(255,255,255,0.5);
    fill: none;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.footer-section .kontak-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    color: rgba(255,255,255,0.62);
    margin-bottom: 13px;
    font-size: 0.875rem;
}

.social-links {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.social-links a {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.1);
    border: 0.5px solid rgba(255,255,255,0.18);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0;
    padding: 0;
    transition: background 0.2s;
}

.social-links a:hover {
    background: rgba(212,165,116,0.3);
    color: white;
    padding-left: 0;
}

.social-links a svg {
    width: 18px;
    height: 18px;
    fill: white;
    stroke: none;
    display: block;
}

.footer-bottom {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px 0 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    text-align: center;
}

.footer-bottom p {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.4);
    line-height: 1.6;
}

.footer-bottom strong {
    color: #D4A574;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 992px) {
    .nav-menu { display: none; }
    .hero-title { font-size: 3rem; }
    .products-grid { grid-template-columns: repeat(2, 1fr); }
    .video-nav.prev { left: 10px; }
    .video-nav.next { right: 10px; }
}

@media (max-width: 768px) {
    .hero-title { font-size: 2.5rem; }
    .section-title { font-size: 2rem; }
    .products-grid { grid-template-columns: 1fr; }
    .video-grid { gap: 15px; }
    .video-card { min-width: 260px; }
}

@media (max-width: 480px) {
    .hero-title { font-size: 2rem; }
    .hero-subtitle { font-size: 1.2rem; }
    .video-card { min-width: 240px; }
}
/* Modal Product Detail - Enhanced */
#modalDetail .modal-content {
    border-radius: 24px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

#modalDetail .modal-body {
    padding: 0;
}

#modalDetail .col-md-6:first-child {
    background: linear-gradient(135deg, var(--bg-cream) 0%, var(--pink-soft) 100%);
    min-height: 500px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

#modalDetail .col-md-6:first-child img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

#modalDetail .col-md-6:first-child:hover img {
    transform: scale(1.05);
}

#modalDetail .col-md-6:last-child {
    padding: 50px 40px;
    background: white;
}

#modalDetail .modal-title {
    font-weight: 700;
    color: var(--text-dark);
    font-size: 2rem;
    line-height: 1.2;
}

#modalDetail #md-price {
    font-weight: 800;
    color: #E53E3E;
    font-size: 2.2rem;
    margin-bottom: 15px;
}

#modalDetail #md-desc {
    font-size: 1rem;
    line-height: 1.7;
    color: var(--text-gray);
}

#modalDetail .btn-close {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 10;
    background: white;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    opacity: 1;
    transition: all 0.3s;
}

#modalDetail .btn-close:hover {
    transform: rotate(90deg);
    background: var(--bg-cream);
}

#modalDetail button[type="submit"] {
    background: var(--primary);
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    padding: 16px;
    border-radius: 50px;
    border: none;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(139, 111, 78, 0.3);
}

#modalDetail button[type="submit"]:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(139, 111, 78, 0.4);
}

/* Responsive untuk modal */
@media (max-width: 768px) {
    #modalDetail .col-md-6:first-child {
        min-height: 300px;
    }
    
    #modalDetail .col-md-6:last-child {
        padding: 30px 20px;
    }
    
    #modalDetail .modal-title {
        font-size: 1.5rem;
    }
    
    #modalDetail #md-price {
        font-size: 1.8rem;
    }
}
/* Modal Fullscreen untuk mobile */
@media (max-width: 768px) {
    #modalDetail .modal-dialog {
        margin: 0;
        max-width: 100%;
        height: 100%;
    }
    #modalDetail .modal-content {
        height: 100%;
        border-radius: 0;
    }
    #modalDetail .modal-body {
        padding-bottom: 80px; /* Space for bottom bar */
    }
}

/* Hide scrollbar tapi tetap bisa scroll */
#modalDetail .modal-body::-webkit-scrollbar {
    width: 6px;
}
#modalDetail .modal-body::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 3px;
}

/* Smooth scrolling */
#modalDetail .modal-body {
    scroll-behavior: smooth;
}
</style>
</head>
<body>

<!-- Top Navigation -->
<nav class="top-nav">
    <div class="nav-container">
        <a href="index.php" class="logo">Texcer Hot</a>
        <ul class="nav-menu">
            <li><a href="index.php" class="active">Home</a></li>
            <li><a href="#menu">Menu</a></li>
            <li><a href="riwayat.php">Pesanan</a></li>
        </ul>
        <div class="nav-icons">
            
            <!-- Cart Icon -->
            <a href="checkout.php">
                <i class="fas fa-shopping-bag"></i>
                <?php if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                <span class="cart-count"><?= count($_SESSION['cart']) ?></span>
                <?php endif; ?>
            </a>
            
            <!-- User/Profile Icon -->
<?php if(isset($_SESSION['user_phone'])): ?>
    <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
    <a href="dashboard.php" title="Dashboard Admin"><i class="fas fa-tachometer-alt"></i></a>
    <?php else: ?>
    <a href="profile.php" title="Profil Saya"><i class="fas fa-user"></i></a>
    <?php endif; ?>
    <a href="auth/logout.php" title="Keluar" style="margin-left: 10px;"><i class="fas fa-sign-out-alt"></i></a>
<?php else: ?>
<a href="auth/login.php" title="Masuk"><i class="fas fa-sign-in-alt"></i></a>
<?php endif; ?>
        </div>
    </div>
</nav>

<!-- ✅ HERO SECTION FULL SCREEN DENGAN AUTO SLIDE -->
<section class="hero-section">
    <div class="hero-slider">
        <?php foreach($heroImages as $index => $image): ?>
        <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>">
            <img src="<?= $image ?>" alt="Hero Image <?= $index + 1 ?>">
        </div>
        <?php endforeach; ?>
    </div>

    <div class="hero-content">
        <h1 class="hero-title"><?= htmlspecialchars($settings['hero_title'] ?? 'ORDER MAKANAN') ?></h1>
<p class="hero-subtitle"><?= htmlspecialchars($settings['hero_subtitle'] ?? 'Welcome to Texcer Hot') ?></p>>
        <button class="hero-btn"><?= htmlspecialchars($settings['hero_button_text'] ?? 'Pesan Sekarang') ?></button>
    </div>

    <div class="hero-dots">
        <?php foreach($heroImages as $index => $image): ?>
        <div class="hero-dot <?= $index === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $index ?>)"></div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Menu Section -->
<section class="menu-section" id="menu">
    <div class="section-header">
        <div>
            <h2 class="section-title"><?= htmlspecialchars($settings['menu_section_title'] ?? 'Menu') ?></h2>
<p class="section-subtitle"><?= htmlspecialchars($settings['menu_section_subtitle'] ?? 'makanan pedas') ?></p>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="category-tabs">
        <button class="tab-btn active" data-filter="all">Semua</button>
        <button class="tab-btn" data-filter="mie">Mie</button>
        <button class="tab-btn" data-filter="mercon">Mercon</button>
        <button class="tab-btn" data-filter="dimsum">Dimsum</button>
        <button class="tab-btn" data-filter="minuman">Minuman</button>
    </div>

<!-- Products Grid -->
<div class="products-grid">
    <?php foreach($products as $p): 
        $category = strtolower($p['category'] ?? 'all');
        
        // Cek ketersediaan produk (default 1 jika kolom tidak ada)
        $isAvail = $p['is_available'] ?? 1; 
        
        // Tambahkan class 'unavailable' jika stok habis
        $classUnavailable = ($isAvail == 0) ? 'unavailable' : '';
    ?>
    
    <!-- PERBAIKAN: Kembalikan data-bs-toggle dan data-bs-target agar Modal Detail muncul -->
    <!-- Tambahkan class <?= $classUnavailable ?> untuk efek abu-abu -->
    <div class="product-card <?= $classUnavailable ?>"
         data-bs-toggle="modal"
         data-bs-target="#modalDetail"
         data-id="<?= $p['id'] ?>"
         data-name="<?= $p['name'] ?>"
         data-price="<?= $p['price'] ?>"
         data-desc="<?= $p['description'] ?>"
         data-img="<?= $p['image'] ?>"
         data-category="<?= $category ?>"
         data-has-variant="0"
         data-variants='[]'
         data-toppings='<?= htmlspecialchars(json_encode($p['toppings']), ENT_QUOTES) ?>'
         data-levels='<?= htmlspecialchars(json_encode($p['levels']), ENT_QUOTES) ?>'>

        <div class="product-image">
            <button class="favorite-btn" onclick="toggleFavorite(this, event)">
                <i class="far fa-heart"></i>
            </button>
            
            <!-- Overlay Stok Habis (Muncul jika is_available = 0) -->
            <?php if($isAvail == 0): ?>
                <div class="out-of-stock-overlay">
                    <span>Stok Habis</span>
                </div>
            <?php endif; ?>

            <?php if(!empty($p['image'])): ?>
                <img src="<?= $p['image'] ?>" alt="<?= $p['name'] ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                <i class="fas fa-utensils" style="display: none; font-size: 5rem; color: var(--primary); opacity: 0.3;"></i>
            <?php else: ?>
                <i class="fas fa-utensils"></i>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <h3 class="product-name"><?= $p['name'] ?></h3>
            <p class="product-desc"><?= $p['description'] ?></p>
            <div class="product-footer">
                <span class="product-price">Rp <?= number_format($p['price'], 0, ',', '.') ?></span>
                
                <?php if($isAvail == 1): ?>
                    <!-- Jika Tersedia: Tombol Aktif -->
                    <button 
                        onclick="openVariantFromCard(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', <?= $p['price'] ?>, '<?= $p['image'] ?>', '<?= strtolower($p['category']) ?>', '<?= htmlspecialchars(json_encode($p['levels']), ENT_QUOTES) ?>', '<?= htmlspecialchars(json_encode($p['toppings']), ENT_QUOTES) ?>')" 
                        style="background: var(--primary); color: white; border: none; padding: 6px 12px; border-radius: 15px; font-weight: 600; cursor: pointer; font-size: 0.8rem;">
                        <i class="fas fa-cart-plus"></i> Tambah
                    </button>
                <?php else: ?>
                    <!-- Jika Tidak Tersedia: Tombol Disabled/Grey -->
                    <button disabled style="background: #ccc; color: #666; border: none; padding: 6px 12px; border-radius: 15px; font-weight: 600; cursor: not-allowed; font-size: 0.8rem;">
                        Habis
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
    </div>

</section>

<!-- ✅ VIDEO TESTIMONIALS SECTION -->
<section class="video-testimonials-section">
    <div class="testimonials-header">
        <h2><?= htmlspecialchars($settings['testimonials_section_title'] ?? 'Testimoni Video') ?></h2>
        <p><?= htmlspecialchars($settings['testimonials_section_subtitle'] ?? 'Lihat apa kata mereka tentang Texcer Hot') ?></p>
    </div>

    <div class="video-container">
        <button class="video-nav prev" onclick="scrollVideos(-1)">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div class="video-grid" id="videoGrid">
            <?php foreach($videoTestimonials as $index => $video): ?>
            <div class="video-card" data-index="<?= $index ?>">
                <div class="video-wrapper" onclick="<?= !empty($video['video']) ? "toggleVideo(this, '".htmlspecialchars($video['video'])."')" : "void(0)" ?>">
                    
                    <?php if($video['media_type'] === 'video' && !empty($video['video'])): ?>
                        <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['user']) ?>" class="video-thumbnail">
                        <video src="<?= htmlspecialchars($video['video']) ?>" loop muted playsinline></video>
                        <div class="video-overlay">
                            <div class="video-play-btn"><i class="fas fa-play"></i></div>
                        </div>
                        <span class="video-duration"><i class="fas fa-video me-1"></i>Video</span>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['user']) ?>" class="video-thumbnail" style="cursor: default;">
                    <?php endif; ?>
                    
                </div>
                <div class="video-info">
                    <div class="video-user">
                        <div class="user-avatar" style="background: <?= $index % 3 == 0 ? 'var(--accent)' : ($index % 3 == 1 ? '#D4A574' : '#E8B4A2') ?>;"><?= $video['initial'] ?></div>
                        <span class="user-name"><?= htmlspecialchars($video['user']) ?></span>
                    </div>
                    <div class="video-product"><?= htmlspecialchars($video['product']) ?></div>
                    <div class="video-rating">
                        <?php for($i = 0; $i < ($video['rating'] ?? 5); $i++): ?>
                        <i class="fas fa-star"></i>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button class="video-nav next" onclick="scrollVideos(1)">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</section>


<!-- ✅ LOKASI KAMI (GOOGLE MAPS) -->
<section class="location-section">
    <div class="container">
        <div class="location-header">
           <h2><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($settings['location_section_title'] ?? 'Lokasi Kami') ?></h2>
<p><?= htmlspecialchars($settings['location_section_subtitle'] ?? 'Kunjungi Texcer Hot atau pesan online untuk pengiriman') ?></p>
        </div>

        <div class="location-grid">
            <!-- Google Maps -->
            <div class="map-container">
               <iframe 
               src="https://www.google.com/maps/embed?pb=!4v1778744256410!6m8!1m7!1sv34YmXRDNEqPWRvZUIdt9g!2m2!1d-6.523646052367316!2d110.7668596153108!3f59.65!4f0!5f0.7820865974627469"
               width="100%" 
               height="100%" 
               style="border:0; border-radius: 20px;" 
               allowfullscreen="" 
               loading="lazy" 
               referrerpolicy="no-referrer-when-downgrade">
               </iframe>
            </div>

            <!-- Info Lokasi -->
            <div class="location-info">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="info-content">
                        <h4>Texcer Hot</h4>
                        <p>Depan SMPN 1 Bangsri, Jepara<br>Jawa Tengah, Indonesia</p>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <h4>Jam Buka</h4>
                        <p>Senin - Minggu<br>11:00 - 20:00 WIB</p>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <div class="info-content">
                        <h4>Layanan Pengiriman</h4>
                        <p>GoFood, GrabFood, ShopeeFood<br>atau pesan langsung via WhatsApp</p>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="#" onclick="chatWhatsApp(<?= $product['id'] ?? 'null' ?>, '<?= addslashes($product['name'] ?? '') ?>'); return false;" class="btn-wa">
                    <i class="fab fa-whatsapp"></i> Chat WhatsApp
                    </a>


                    <a href="https://www.google.com/maps/place/Texcer+Hot+Cabang+Bangsri/@-6.523646,110.7668596,17z/data=!4m6!3m5!1s0x0:0x0!8m2!3d-6.523646052367316!4d110.7668596153108!16s%2Fg%2F11abc123xyz?entry=ttu" target="_blank" class="btn-directions">
                    <i class="fas fa-directions"></i> Petunjuk Arah
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>


<style>
/* Location Section Styles */
.location-section {
    background: linear-gradient(135deg, var(--bg-cream) 0%, var(--bg-white) 100%);
    padding: 80px 40px;
    border-top: 1px solid var(--border);
}

.location-header {
    text-align: center;
    margin-bottom: 50px;
}

.location-header h2 {
    font-size: 2.5rem;
    color: var(--text-dark);
    margin-bottom: 10px;
    font-weight: 700;
}

.location-header h2 i {
    color: var(--primary);
    margin-right: 10px;
}

.location-header p {
    color: var(--text-gray);
    font-size: 1.1rem;
}

.location-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
    max-width: 1400px;
    margin: 0 auto;
}

.map-container {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    height: 600px;
}

.map-container iframe {
    width: 100%;
    height: 100%;
}

.location-info {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.info-card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    display: flex;
    gap: 20px;
    align-items: flex-start;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.info-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
    font-size: 1.5rem;
}

.info-content h4 {
    margin: 0 0 8px 0;
    color: var(--text-dark);
    font-size: 1.1rem;
    font-weight: 700;
}

.info-content p {
    margin: 0;
    color: var(--text-gray);
    line-height: 1.6;
    font-size: 0.95rem;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.btn-wa, .btn-directions {
    flex: 1;
    padding: 15px 25px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-wa {
    background: #25D366;
    color: white;
}

.btn-wa:hover {
    background: #128C7E;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(37, 211, 102, 0.3);
}

.btn-directions {
    background: var(--primary);
    color: white;
}

.btn-directions:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(139, 111, 78, 0.3);
}

/* Responsive */
@media (max-width: 1024px) {
    .location-grid {
        grid-template-columns: 1fr;
    }
    
    .map-container {
        height: 400px;
    }
    
    .location-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
    
    .info-card {
        margin-bottom: 0;
    }
    
    .action-buttons {
        grid-column: 1 / -1;
    }
}

@media (max-width: 768px) {
    .location-section {
        padding: 50px 20px;
    }
    
    .location-header h2 {
        font-size: 1.8rem;
    }
    
    .location-info {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .info-card {
        flex-direction: column;
        text-align: center;
    }
    
    .info-icon {
        margin: 0 auto;
    }
}
</style>
<script>
// 🔥 GANTI MOCKDATA DENGAN AJAX CALL 🔥
// 🔥 TRACKING ORDER FUNCTION
function trackOrder() {
    const input = document.getElementById('trackingInput');
    if(!input) return; // Exit if element not found
    
    const query = input.value.trim();
    if(!query) {
        alert('Masukkan nomor pesanan');
        return;
    }
    
    fetch('api/track_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({query: query})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.order) {
            const order = data.order;
            // Update UI dengan data dari database
            const orderId = document.getElementById('orderId');
            const orderStatus = document.getElementById('orderStatus');
            const customerName = document.getElementById('customerName');
            const customerPhone = document.getElementById('customerPhone');
            const deliveryAddress = document.getElementById('deliveryAddress');
            const orderItems = document.getElementById('orderItems');
            const resiNumber = document.getElementById('resiNumber');
            
            if(orderId) orderId.textContent = order.id;
            if(orderStatus) {
                orderStatus.textContent = order.status;
                orderStatus.className = 'status-badge status-' + order.status.toLowerCase().replace(' ', '-');
            }
            if(customerName) customerName.textContent = order.customer_name;
            if(customerPhone) customerPhone.textContent = order.customer_phone;
            if(deliveryAddress) deliveryAddress.textContent = order.customer_address || '-';
            if(orderItems) orderItems.textContent = order.items || '-';
            if(resiNumber) resiNumber.textContent = order.resi_number || '-';
            
            // Update dates
            for (let i = 1; i <= 5; i++) {
                const dateEl = document.getElementById('date' + i);
                const dateKey = ['date_received', 'date_processed', 'date_shipped', 'date_delivered', 'date_completed'][i-1];
                if(dateEl) dateEl.textContent = order[dateKey] || '-';
            }
            
            const estimatedDate = document.getElementById('estimatedDate');
            if(estimatedDate) estimatedDate.textContent = order.estimated_delivery || '-';
            
            // Reset semua step
            for (let i = 1; i <= 5; i++) {
                const step = document.getElementById('step' + i);
                if(step) {
                    step.classList.remove('completed', 'active');
                }
            }
            
            // Set step yang sesuai
            const stepMap = {
                'Menunggu Konfirmasi': 1,
                'Diproses': 2,
                'Dikemas': 2,
                'Dikirim': 3,
                'Diantar': 4,
                'Selesai': 5,
                'Dibatalkan': 0
            };
            const currentStep = stepMap[order.status] || 1;
            
            for (let i = 1; i <= currentStep; i++) {
                const step = document.getElementById('step' + i);
                if(step) {
                    if (i < currentStep) {
                        step.classList.add('completed');
                    } else {
                        step.classList.add('active');
                    }
                }
            }
            
            const noResultDiv = document.getElementById('noResult');
            const resultDiv = document.getElementById('result');
            if(noResultDiv) noResultDiv.style.display = 'none';
            if(resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } else {
            const noResultDiv = document.getElementById('noResult');
            const resultDiv = document.getElementById('result');
            if(resultDiv) resultDiv.style.display = 'none';
            if(noResultDiv) noResultDiv.style.display = 'block';
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Terjadi kesalahan saat melacak pesanan');
    });
}

// Allow Enter key to trigger search
document.addEventListener('DOMContentLoaded', function() {
    const trackingInput = document.getElementById('trackingInput');
    if(trackingInput) {
        trackingInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                trackOrder();
            }
        });
    }
});
.then(res => res.json())
.then(data => {
    if (data.success && data.order) {
        const order = data.order;
        
        // Update UI dengan data dari database
        document.getElementById('orderId').textContent = order.id;
        document.getElementById('orderStatus').textContent = order.status;
        document.getElementById('orderStatus').className = 'status-badge status-' + order.status.toLowerCase().replace(' ', '-');
        document.getElementById('customerName').textContent = order.customer_name;
        document.getElementById('customerPhone').textContent = order.customer_phone;
        document.getElementById('deliveryAddress').textContent = order.customer_address || '-';
        document.getElementById('orderItems').textContent = order.items || '-';
        document.getElementById('resiNumber').textContent = order.resi_number || '-';
        document.getElementById('date1').textContent = order.date_received || '-';
        document.getElementById('date2').textContent = order.date_processed || '-';
        document.getElementById('date3').textContent = order.date_shipped || '-';
        document.getElementById('date4').textContent = order.date_delivered || '-';
        document.getElementById('date5').textContent = order.date_completed || '-';
        document.getElementById('estimatedDate').textContent = order.estimated_delivery || '-';
        
        // Reset semua step
        for (let i = 1; i <= 5; i++) {
            document.getElementById('step' + i).classList.remove('completed', 'active');
        }
        
        // Set step yang sesuai (1=received, 2=processed, 3=shipped, 4=delivered, 5=completed)
        const stepMap = {
            'Menunggu Konfirmasi': 1,
            'Diproses': 2,
            'Dikemas': 2,
            'Dikirim': 3,
            'Diantar': 4,
            'Selesai': 5,
            'Dibatalkan': 0
        };
        const currentStep = stepMap[order.status] || 1;
        
        for (let i = 1; i <= currentStep; i++) {
            if (i < currentStep) {
                document.getElementById('step' + i).classList.add('completed');
            } else {
                document.getElementById('step' + i).classList.add('active');
            }
        }
        
        noResultDiv.style.display = 'none';
        resultDiv.style.display = 'block';
        resultDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        // Order not found
        resultDiv.style.display = 'none';
        noResultDiv.style.display = 'block';
    }
})
.catch(err => {
    console.error('Error:', err);
    alert('Terjadi kesalahan saat melacak pesanan');
});

// Allow Enter key to trigger search
document.getElementById('trackingInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        trackOrder();
    }
});
// ============================================================
// FUNGSI TAMBAH KERANJANG LANGSUNG (TANPA MODAL VARIAN)
// ============================================================
function addToCartDirect(productId, productName, productPrice, productImage) {
    // Buat form dinamis untuk submit data
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'index.php';
    form.style.display = 'none';

    // Input hidden untuk add_to_cart
    const inputAddToCart = document.createElement('input');
    inputAddToCart.type = 'hidden';
    inputAddToCart.name = 'add_to_cart';
    inputAddToCart.value = '1';
    form.appendChild(inputAddToCart);

    // Input hidden untuk ID produk
    const inputId = document.createElement('input');
    inputId.type = 'hidden';
    inputId.name = 'id';
    inputId.value = productId;
    form.appendChild(inputId);

    // Input hidden untuk Nama produk
    const inputName = document.createElement('input');
    inputName.type = 'hidden';
    inputName.name = 'name';
    inputName.value = productName;
    form.appendChild(inputName);

    // Input hidden untuk Harga produk
    const inputPrice = document.createElement('input');
    inputPrice.type = 'hidden';
    inputPrice.name = 'price';
    inputPrice.value = productPrice;
    form.appendChild(inputPrice);

    // Input hidden untuk Varian (default 'Regular' untuk tambah langsung)
    const inputVariant = document.createElement('input');
    inputVariant.type = 'hidden';
    inputVariant.name = 'variant';
    inputVariant.value = 'Regular'; // Default varian untuk tambah langsung
    form.appendChild(inputVariant);

    // Input hidden untuk Gambar produk
    const inputImage = document.createElement('input');
    inputImage.type = 'hidden';
    inputImage.name = 'image';
    inputImage.value = productImage;
    form.appendChild(inputImage);

    // Input hidden untuk buy_now (0 karena ini tambah keranjang)
    const inputBuyNow = document.createElement('input');
    inputBuyNow.type = 'hidden';
    inputBuyNow.name = 'buy_now';
    inputBuyNow.value = '0';
    form.appendChild(inputBuyNow);

    // Tambahkan form ke body dan submit
    document.body.appendChild(form);
    form.submit();
}
// ============================================================
// FUNGSI BUKA MODAL VARIAN LANGSUNG DARI KARTU PRODUK
// ============================================================
function openVariantFromCard(id, name, price, image, category, levelsJson, toppingsJson) {
    // 1. Isi dmState dengan data dari kartu produk
    dmState.id       = id;
    dmState.name     = name;
    dmState.price    = parseInt(price) || 0;
    dmState.image    = image || 'assets/images/placeholder.png';
    dmState.category = category || '';
    dmState.levels   = JSON.parse(levelsJson || '[]');
    dmState.toppings = JSON.parse(toppingsJson || '[]');

    // 2. Langsung panggil dmOpenOrder dengan action 'cart'
    // Ini akan membuka modal #orderModal untuk pilih level/topping/suhu
    dmOpenOrder('cart');
}
</script>

<!-- ✅ FOOTER -->
<footer class="site-footer">
    <div class="footer-content">

        <!-- Kolom Brand -->
        <div class="footer-section">
            <h3>Texcer Hot</h3>
            <p><?= htmlspecialchars($settings['footer_about'] ?? 'Pesan makanan pedas favoritmu...') ?></p>
           <div class="social-links">

    <!-- Instagram — ganti username sesuai akun Texcer Hot -->
    <a href="https://www.instagram.com/texcer.hot" target="_blank" title="Instagram">
        <svg viewBox="0 0 24 24" fill="white" style="display:block;width:18px;height:18px;stroke:none;">
            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 1.366.062 2.633.336 3.608 1.311.975.975 1.249 2.242 1.311 3.608.058 1.266.07 1.646.07 4.85s-.012 3.584-.07 4.85c-.062 1.366-.336 2.633-1.311 3.608-.975.975-2.242 1.249-3.608 1.311-1.266.058-1.646.07-4.85.07s-3.584-.012-4.85-.07c-1.366-.062-2.633-.336-3.608-1.311-.975-.975-1.249-2.242-1.311-3.608C2.175 15.584 2.163 15.204 2.163 12s.012-3.584.07-4.85c.062-1.366.336-2.633 1.311-3.608.975-.975 2.242-1.249 3.608-1.311 1.266-.058 1.646-.07 4.85-.07zm0-2.163c-3.259 0-3.667.014-4.947.072-1.609.074-3.057.372-4.19 1.505C1.73 2.71 1.43 4.158 1.357 5.767 1.299 7.047 1.285 7.455 1.285 12s.014 4.953.072 6.233c.074 1.609.372 3.057 1.505 4.19 1.133 1.133 2.581 1.431 4.19 1.505 1.28.058 1.688.072 4.948.072s3.667-.014 4.947-.072c1.609-.074 3.057-.372 4.19-1.505 1.133-1.133 1.431-2.581 1.505-4.19.058-1.28.072-1.688.072-4.948s-.014-4.953-.072-6.233c-.074-1.609-.372-3.057-1.505-4.19C19.004.386 17.556.088 15.947.014 14.667-.044 14.259-.058 12-.058h.001zM12 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>
        </svg>
    </a>

    <!-- TikTok — ganti username sesuai akun Texcer Hot -->
    <a href="https://www.tiktok.com/@texcer.hot" target="_blank" title="TikTok">
        <svg viewBox="0 0 24 24" fill="white" style="display:block;width:18px;height:18px;stroke:none;">
            <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.32 6.32 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.76a4.85 4.85 0 0 1-1.01-.07z"/>
        </svg>
    </a>

    <!-- WhatsApp — nomor sudah sesuai +62 819-3417-4198 -->
    <a href="https://wa.me/6281934174198?text=Halo%20Texcer%20Hot%2C%20saya%20mau%20pesan!" target="_blank" title="WhatsApp">
        <svg viewBox="0 0 24 24" fill="white" style="display:block;width:18px;height:18px;stroke:none;">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/>
        </svg>
    </a>

</div>
        </div>

        <!-- Kolom Link Cepat -->
        <div class="footer-section">
            <h4>Link Cepat</h4>
            <a href="index.php">
                <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Home
            </a>
            <a href="#menu">
                <svg viewBox="0 0 24 24"><path d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Menu
            </a>
            <a href="riwayat.php">
                <svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Pesanan
            </a>
            <a href="checkout.php">
                <svg viewBox="0 0 24 24"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                Keranjang
            </a>
        </div>

<!-- Kolom Bantuan -->
<div class="footer-section">
    <h4>Bantuan</h4>
    <a href="faq.php">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
        FAQ
    </a>
    <a href="syarat.php">
        <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Syarat & Ketentuan
    </a>
    <a href="privasi.php">
        <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        Kebijakan Privasi
    </a>
    <a href="https://wa.me/6281934174198?text=Halo%20Texcer%20Hot%2C%20saya%20butuh%20bantuan" target="_blank">
        <svg viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        Hubungi Kami
    </a>
</div>

<!-- Kolom Kontak -->
<div class="footer-section">
    <h4>Kontak</h4>
    <a href="https://maps.google.com/?q=SMPN+1+Bangsri+Jepara" target="_blank">
        <svg viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <?= htmlspecialchars($settings['address'] ?? 'Depan SMPN 1 Bangsri, Jepara') ?>
    </a>
    <a href="https://wa.me/<?= htmlspecialchars($settings['whatsapp_number'] ?? '6281934174198') ?>">
        <svg viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        <?= htmlspecialchars($settings['whatsapp_number'] ?? '+62 819-3417-4198') ?>
    </a>
    <a href="mailto:<?= htmlspecialchars($settings['email'] ?? 'hello@texcerhot.com') ?>">
        <svg viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        <?= htmlspecialchars($settings['email'] ?? 'hello@texcerhot.com') ?>
    </a>
    <a href="#">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <?= htmlspecialchars($settings['opening_hours'] ?? '11:00 - 21:00 WIB') ?>
    </a>
</div>
</div>

 <div class="footer-bottom">
        <p>© 2020 – 2026 <strong>Texcer Hot</strong>. Berdiri sejak 2020.</p>
        <p>Didirikan oleh <strong>Ahmad Amrullah Baharuddin</strong> · All rights reserved.</p>
    </div>
</footer>
<!-- Modal Detail Produk -->
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-md-down">
    <div class="modal-content" 
     data-id="<?= $produk['id'] ?>" 
     data-price="<?= $produk['harga'] ?>" 
     style="border: none; border-radius: 0;">
            
            <!-- Header dengan back button -->
            <div class="modal-header" style="border-bottom: 1px solid #e0e0e0; padding: 12px 16px; position: sticky; top: 0; background: white; z-index: 100;">
                <button type="button" class="btn btn-link" data-bs-dismiss="modal" style="text-decoration: none; color: #333; padding: 0; font-size: 1.5rem;">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div style="flex: 1; margin: 0 12px;">
                    <input type="text" placeholder="Cari di Texcer Hot" style="width: 100%; padding: 8px 12px; border: 1px solid #e0e0e0; border-radius: 8px; background: #f5f5f5;">
                </div>
                
            </div>

            <div class="modal-body" style="padding: 0; overflow-y: auto; max-height: calc(100vh - 140px);">
                
                <!-- Product Image Slider -->
                <div style="position: relative; background: white;">
                    <img src="" id="md-img" class="img-fluid" 
                    style="width: 100%; max-height: 400px; object-fit: cover;"
                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22%3EGambar Tidak Tersedia%3C/text%3E%3C/svg%3E'">
                    <span style="position: absolute; bottom: 12px; right: 12px; background: rgba(0,0,0,0.6); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem;">1/1</span>
                </div>

                <!-- Product Name & Rating -->
                <div style="padding: 16px; border-bottom: 1px solid #f0f0f0;">
                    <h3 id="md-name" style="font-size: 1.1rem; font-weight: 600; margin-bottom: 8px; line-height: 1.4;"></h3>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem;">
                        <span style="color: #FFB800;">
                            <i class="fas fa-star"></i>
                            <strong style="color: #333;">4.6</strong>
                        </span>
                        <span style="color: #999;">(1,5 rb)</span>
                        <span style="color: #999;">|</span>
                        <span style="color: #999;">10rb+ terjual</span>
                       
                    </div>
                </div>
                    
                   <!-- ✅ DESKRIPSI PRODUK (PENGGANTI BAGIAN YANG DIHAPUS) -->
<div style="padding: 16px; border-bottom: 1px solid #f0f0f0;">
    <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 8px; color: var(--text-dark);">
        <i class="fas fa-info-circle me-2" style="color: var(--primary);"></i>Deskripsi Produk
    </h4>
    <p id="md-desc" style="font-size: 0.9rem; color: var(--text-gray); line-height: 1.6; margin: 0;">
        <!-- Isi deskripsi akan diisi otomatis oleh JavaScript saat modal dibuka -->
        Memuat deskripsi...
    </p>
</div>

                <!-- Store Info -->
                <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 2px solid var(--primary);">
                <?php 
                $logo_path = !empty($profile['logo_path']) ? $profile['logo_path'] : 'assets/images/logo-texcer.png';
                if(file_exists($logo_path)): 
                ?>
                <img src="<?= htmlspecialchars($logo_path) ?>" 
                 alt="Texcer Hot Logo" 
                 style="width:100%; height:100%; object-fit: cover;"
                 onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-store\' style=\'font-size: 1.5rem; color: var(--primary);\'></i>'">
                <?php else: ?>
                <i class="fas fa-store" style="font-size: 1.5rem; color: var(--primary);"></i>
                <?php endif; ?>
                </div>
                <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 1rem;">Texcer Hot</div>
                <div style="font-size: 0.85rem; color: #666;">
                <i class="fas fa-star" style="color: #ffd500;"></i> 3.6 &nbsp; 24.6K terjual
        </div>
    </div>
    <button onclick="showStoreProfile()" style="background: var(--primary); border: none; padding: 8px 16px; border-radius: 20px; font-weight: 600; color: white; cursor: pointer;">Kunjungi</button>
</div>

<!-- ✅ PROFIL TOKO DINAMIS (Diambil dari Database) -->
<div id="storeProfileContent" style="display: none;">
    <!-- Cover Foto Toko -->
    <div style="width: 100%; height: 200px; position: relative; overflow: hidden;">
        <img src="<?= !empty($profile['cover_path']) ? htmlspecialchars($profile['cover_path']) : 'https://via.placeholder.com/1200x400?text=Texcer+Hot' ?>" 
             style="width:100%; height:100%; object-fit: cover;">
        <div style="position: absolute; bottom: 0; left: 0; right: 0; padding: 20px; background: linear-gradient(transparent, rgba(0,0,0,0.7));">
            <h3 style="margin: 0; color: white; font-size: 1.5rem;"><?= htmlspecialchars($profile['store_name']) ?></h3>
            <p style="margin: 4px 0 0; color: rgba(255,255,255,0.9); font-size: 0.95rem;"><?= htmlspecialchars($profile['tagline']) ?></p>
        </div>
        <!-- Tombol Back -->
      
    </div>

    <div style="padding: 20px;">
        <!-- Logo & Info Utama -->
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #eee; overflow: hidden; border: 3px solid var(--primary); flex-shrink: 0;">
                <img src="<?= !empty($profile['logo_path']) ? htmlspecialchars($profile['logo_path']) : 'https://via.placeholder.com/70?text=TH' ?>" 
                     style="width:100%; height:100%; object-fit: cover;">
            </div>
            <div style="flex: 1;">
                <h3 style="margin: 0; font-size: 1.3rem; font-weight: 700;"><?= htmlspecialchars($profile['store_name']) ?></h3>
                <p style="margin: 4px 0; color: var(--text-gray); font-size: 0.9rem;">
                    <i class="fas fa-star" style="color: #ffd500;"></i> 
                    <strong><?= number_format($profile['rating_avg'], 1) ?></strong> 
                    (<?= number_format($profile['total_reviews']) ?>+ Rating)
                </p>
                <p style="margin: 2px 0; font-size: 0.85rem; color: #25D366;">
                    <i class="fas fa-circle" style="font-size: 8px; margin-right: 4px;"></i> Online • Merespons cepat
                </p>
            </div>
            <div style="text-align: right;">
                <span style="background: var(--accent); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                    Sejak <?= $profile['established_year'] ?>
                </span>
            </div>
        </div>

        <!-- Deskripsi Toko -->
        <div style="background: var(--bg-cream); padding: 15px; border-radius: 12px; margin-bottom: 20px;">
            <h5 style="margin: 0 0 8px; font-size: 1rem; font-weight: 700;">Tentang Kami</h5>
            <p style="margin: 0; font-size: 0.9rem; color: var(--text-gray); line-height: 1.6;">
                <?= nl2br(htmlspecialchars($profile['description'])) ?>
            </p>
        </div>

        <!-- Stats -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
            <div style="text-align: center; padding: 12px; background: white; border-radius: 10px; border: 1px solid var(--border);">
                <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary);">10rb+</div>
                <div style="font-size: 0.75rem; color: var(--text-gray);">Pesanan</div>
            </div>
            <div style="text-align: center; padding: 12px; background: white; border-radius: 10px; border: 1px solid var(--border);">
                <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary);"><?= number_format($profile['total_reviews']) ?>+</div>
                <div style="font-size: 0.75rem; color: var(--text-gray);">Ulasan</div>
            </div>
            <div style="text-align: center; padding: 12px; background: white; border-radius: 10px; border: 1px solid var(--border);">
                <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary);">4.8</div>
                <div style="font-size: 0.75rem; color: var(--text-gray);">Rating</div>
            </div>
        </div>

        <!-- Info Lokasi & Kontak -->
        <div style="margin-bottom: 20px;">
            <h5 style="font-size: 1rem; margin-bottom: 12px; font-weight: 700;">📍 Lokasi & Kontak</h5>
            
            <div style="display: flex; gap: 8px; margin-bottom: 10px; font-size: 0.9rem;">
                <i class="fas fa-map-marker-alt" style="width: 20px; color: var(--primary); margin-top: 3px;"></i>
                <span style="color: var(--text-gray);"><?= htmlspecialchars($profile['address']) ?></span>
            </div>
            
            <div style="display: flex; gap: 8px; margin-bottom: 10px; font-size: 0.9rem;">
                <i class="fas fa-clock" style="width: 20px; color: var(--primary); margin-top: 3px;"></i>
                <span style="color: var(--text-gray);"><?= htmlspecialchars($profile['opening_hours']) ?></span>
            </div>
            
            <div style="display: flex; gap: 8px; margin-bottom: 10px; font-size: 0.9rem;">
                <i class="fab fa-whatsapp" style="width: 20px; color: #25D366; margin-top: 3px;"></i>
                <a href="https://wa.me/<?= htmlspecialchars($profile['whatsapp']) ?>" target="_blank" style="color: var(--text-gray); text-decoration: none;">
                    +<?= htmlspecialchars($profile['whatsapp']) ?>
                </a>
            </div>
            
            <div style="display: flex; gap: 8px; margin-bottom: 10px; font-size: 0.9rem;">
                <i class="fas fa-envelope" style="width: 20px; color: var(--primary); margin-top: 3px;"></i>
                <a href="mailto:<?= htmlspecialchars($profile['email']) ?>" style="color: var(--text-gray); text-decoration: none;">
                    <?= htmlspecialchars($profile['email']) ?>
                </a>
            </div>
        </div>

        <!-- Social Media -->
        <div style="margin-bottom: 20px;">
            <h5 style="font-size: 1rem; margin-bottom: 12px; font-weight: 700;">🔗 Ikuti Kami</h5>
            <div style="display: flex; gap: 12px;">
                <a href="https://instagram.com/<?= htmlspecialchars($profile['instagram']) ?>" target="_blank" 
                   style="flex: 1; padding: 10px; background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color: white; text-decoration: none; text-align: center; border-radius: 10px; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <i class="fab fa-instagram"></i> Instagram
                </a>
                <a href="https://tiktok.com/@<?= htmlspecialchars($profile['tiktok']) ?>" target="_blank" 
                   style="flex: 1; padding: 10px; background: #000; color: white; text-decoration: none; text-align: center; border-radius: 10px; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <i class="fab fa-tiktok"></i> TikTok
                </a>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="https://wa.me/<?= htmlspecialchars($profile['whatsapp']) ?>?text=Halo%20Texcer%20Hot,%20saya%20mau%20pesan!" 
               target="_blank" 
               style="flex: 1; min-width: 140px; padding: 14px; background: #25D366; color: white; text-decoration: none; text-align: center; border-radius: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i> Chat WhatsApp
            </a>
            <a href="https://www.google.com/maps/dir/Current+Location/<?= $profile['latitude'] ?>,<?= $profile['longitude'] ?>" 
               target="_blank" 
               style="flex: 1; min-width: 140px; padding: 14px; background: var(--primary); color: white; text-decoration: none; text-align: center; border-radius: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fas fa-map-marked-alt"></i> Petunjuk Arah
            </a>
        </div>
    </div>
</div>

          <!-- Bottom Action Bar -->
<!-- ✅ FIX: Form Add to Cart & Beli Sekarang (dipindah ke luar modal-footer agar valid HTML) -->
<form method="POST" id="modalFormPesan" action="index.php">
    <input type="hidden" name="add_to_cart" value="1">
    <input type="hidden" name="id"      id="modal-product-id">
    <input type="hidden" name="name"    id="modal-product-name">
    <input type="hidden" name="price"   id="modal-product-price">
    <input type="hidden" name="variant" id="modal-product-variant" value="">
    <input type="hidden" name="image"   id="modal-product-image" value="">
    <input type="hidden" name="buy_now" id="modal-buy-now" value="0">
</form>

<div class="modal-footer" style="border-top: 1px solid #e0e0e0; padding: 12px 16px; background: white; position: sticky; bottom: 0; display: flex; align-items: center; gap: 8px;">

    <!-- ✅ FIX: Tombol Toko → buka profil toko di modal -->
    <button class="btn btn-link" style="text-decoration: none; color: #333; padding: 8px; display: flex; flex-direction: column; align-items: center;" onclick="showStoreProfile()">
        <i class="fas fa-store" style="font-size: 1.2rem;"></i>
        <span style="font-size: 0.75rem;">Toko</span>
    </button>

    <!-- ✅ FIX: Tombol Chat → WhatsApp dengan nama produk otomatis -->
    <button type="button" onclick="chatWhatsAppModal()" style="background: none; border: none; cursor: pointer; padding: 8px; display: flex; flex-direction: column; align-items: center; color: #333;">
        <i class="fas fa-comments" style="font-size: 1.2rem;"></i>
        <span style="font-size: 0.75rem; margin-top: 2px;">Chat</span>
    </button>



<!-- Tombol Beli Sekarang → buka modal pilih topping/level/suhu -->
<button type="button"
    onclick="dmOpenOrder('buy')"
    style="background: var(--primary); color: white; border: none; border-radius: 24px; padding: 10px 24px; font-weight: 600; cursor: pointer; flex: 1;">
    <div style="font-size: 0.95rem;">Beli sekarang</div>
    <div style="font-size: 0.8rem; opacity: 0.85;"><span id="md-price-bottom">Rp 0</span> | Pengiriman gratis</div>
</button>

</div>
        </div>
    </div>
</div>

<!-- Modal Variant Selection -->
<div class="modal fade" id="modalVariant" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header" style="border-bottom: 2px solid var(--border); background: var(--bg-cream); border-radius: 20px 20px 0 0;">
                <h5 class="modal-title" id="mv-title" style="font-weight: 700; color: var(--text-dark);">Pilih Varian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <div id="variant-options">
                    <!-- Variant options will be inserted here -->
                </div>
                <form method="POST" id="variantForm">
                    <input type="hidden" name="id" id="mv-id">
                    <input type="hidden" name="name" id="mv-name">
                    <input type="hidden" name="price" id="mv-price">
                    <input type="hidden" name="variant" id="mv-variant">
                    <button type="button" onclick="openToppingModal(<?= $product['id'] ?>)" style="background: none; border: none; cursor: pointer; padding: 8px;">
                    <i class="fas fa-shopping-cart"></i>
                    <div style="font-size: 0.75rem; margin-top: 2px;">Tambah Keranjang</div>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ✅ HERO SLIDER AUTO CHANGE
let currentHeroSlide = 0;
const heroSlides = document.querySelectorAll('.hero-slide');
const heroDots = document.querySelectorAll('.hero-dot');

function showHeroSlide(index) {
    heroSlides.forEach((slide, i) => {
        slide.classList.remove('active');
        heroDots[i].classList.remove('active');
    });
    heroSlides[index].classList.add('active');
    heroDots[index].classList.add('active');
    currentHeroSlide = index;
}

function goToSlide(index) {
    showHeroSlide(index);
    resetHeroInterval();
}

function nextHeroSlide() {
    let next = (currentHeroSlide + 1) % heroSlides.length;
    showHeroSlide(next);
}

let heroInterval = setInterval(nextHeroSlide, 5000); // Ganti setiap 5 detik

function resetHeroInterval() {
    clearInterval(heroInterval);
    heroInterval = setInterval(nextHeroSlide, 5000);
}

// Pause on hover
document.querySelector('.hero-section').addEventListener('mouseenter', () => {
    clearInterval(heroInterval);
});

document.querySelector('.hero-section').addEventListener('mouseleave', () => {
    heroInterval = setInterval(nextHeroSlide, 5000);
});

// Category Filter
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const filter = this.getAttribute('data-filter');
        const cards = document.querySelectorAll('.product-card');

        cards.forEach(card => {
            const category = card.getAttribute('data-category');
            if(filter === 'all' || category === filter) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// Favorite button
function toggleFavorite(btn, event) {
    event.stopPropagation();
    btn.classList.toggle('active');
    const icon = btn.querySelector('i');
    if(btn.classList.contains('active')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
    }
}

// Modal Detail - PERBAIKAN HARGA
var modal = document.getElementById('modalDetail');
modal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var imgSrc = button.getAttribute('data-img');
    
    // 1. Ambil Data
    dmState.id       = button.getAttribute('data-id');
    dmState.name     = button.getAttribute('data-name');
    dmState.price    = parseInt(button.getAttribute('data-price')) || 0; // Pastikan angka
    dmState.image    = imgSrc || 'assets/images/placeholder.png';
    dmState.category = button.getAttribute('data-category') || '';
    dmState.toppings = JSON.parse(button.getAttribute('data-toppings') || '[]');
    dmState.levels   = JSON.parse(button.getAttribute('data-levels') || '[]');

    // 2. Format Harga
    var formattedPrice = "Rp " + dmState.price.toLocaleString('id-ID');

    // 3. Update UI Gambar & Teks Dasar
    document.getElementById('md-img').src = dmState.image;
    document.getElementById('md-name').innerText = dmState.name;
    document.getElementById('md-desc').innerText = button.getAttribute('data-desc') || '';

    // 4. ✅ PENTING: Isi Harga di SEMUA Tempat agar tidak Rp 0
    // A. Di tengah modal (detail produk)
    var mdPriceEl = document.getElementById('md-price');
    if(mdPriceEl) mdPriceEl.innerText = formattedPrice;

    // B. Di tombol "Beli Sekarang" bagian bawah (INI YANG ANDA MAU)
    var mdPriceBottomEl = document.getElementById('md-price-bottom');
    if(mdPriceBottomEl) mdPriceBottomEl.innerText = formattedPrice;

    // C. Di banner Flash Sale ("Mulai Rp ...")
    var mdPriceFlashEl = document.getElementById('md-price-flash');
    if(mdPriceFlashEl) mdPriceFlashEl.innerText = formattedPrice;

    // D. Simulasi PayLater (opsional)
    var mdPaylaterEl = document.getElementById('md-paylater');
    if(mdPaylaterEl) mdPaylaterEl.innerText = Math.floor(dmState.price / 3).toLocaleString('id-ID');

    // 5. Isi Hidden Input Form
    document.getElementById('modal-product-id').value    = dmState.id;
    document.getElementById('modal-product-name').value  = dmState.name;
    document.getElementById('modal-product-price').value = dmState.price;
    document.getElementById('modal-buy-now').value       = '0'; 
    
    // Simpan nama untuk chat WhatsApp
    document.getElementById('modalFormPesan').dataset.productName = dmState.name;
});

// Variant Modal
const variantModal = new bootstrap.Modal(document.getElementById('modalVariant'));

function openVariantModal(id, name, hasVariant) {
    if(!hasVariant) {
        // Jika tidak ada varian, langsung submit
        document.getElementById('md-id').value = id;
        document.getElementById('md-form-name').value = name;
        // Find product price
        const product = <?= json_encode($products) ?>.find(p => p.id === id);
        document.getElementById('md-form-price').value = product.price;
        document.getElementById('modalFormPesan').submit();
    } else {
        // Show variant modal
        const product = <?= json_encode($products) ?>.find(p => p.id === id);
        document.getElementById('mv-title').innerText = 'Pilih Varian - ' + name;
        document.getElementById('mv-id').value = product.id;
        document.getElementById('mv-name').value = product.name;
        
        const variantOptions = document.getElementById('variant-options');
        variantOptions.innerHTML = '';
        
        product.variants.forEach((variant, index) => {
            const div = document.createElement('div');
            div.className = 'variant-option' + (index === 0 ? ' selected' : '');
            div.innerHTML = `
                <label style="cursor: pointer; display: flex; align-items: center; width: 100%; margin: 0;">
                    <input type="radio" name="variant_select" value="${variant.name}" data-price="${variant.price}" ${index === 0 ? 'checked' : ''} style="margin-right: 10px;">
                    <span style="flex: 1;">${variant.name}</span>
                    <span style="font-weight: 700; color: var(--primary);">Rp ${variant.price.toLocaleString('id-ID')}</span>
                </label>
            `;
            variantOptions.appendChild(div);
        });
        
        // Set default variant
        const defaultVariant = product.variants[0];
        document.getElementById('mv-price').value = defaultVariant.price;
        document.getElementById('mv-variant').value = defaultVariant.name;
        
        // Add event listeners
        document.querySelectorAll('.variant-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.variant-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                document.getElementById('mv-price').value = radio.dataset.price;
                document.getElementById('mv-variant').value = radio.value;
            });
        });
        
        variantModal.show();
    }
}

// ✅ VIDEO TESTIMONIAL AUTO SCROLL & PLAY
let videoScrollPosition = 0;
const videoGrid = document.getElementById('videoGrid');
const videoCards = document.querySelectorAll('.video-card');
const cardWidth = 305; // 280px + 25px gap

function scrollVideos(direction) {
    const maxScroll = videoGrid.scrollWidth - videoGrid.parentElement.offsetWidth;
    videoScrollPosition += direction * cardWidth;

    if(videoScrollPosition < 0) videoScrollPosition = 0;
    if(videoScrollPosition > maxScroll) videoScrollPosition = maxScroll;

    videoGrid.style.transform = `translateX(-${videoScrollPosition}px)`;
}

// Auto scroll videos
let videoAutoScroll = setInterval(() => {
    const maxScroll = videoGrid.scrollWidth - videoGrid.parentElement.offsetWidth;
    if(videoScrollPosition >= maxScroll) {
        videoScrollPosition = 0;
    } else {
        videoScrollPosition += cardWidth;
        if(videoScrollPosition > maxScroll) videoScrollPosition = maxScroll;
    }
    videoGrid.style.transform = `translateX(-${videoScrollPosition}px)`;
}, 4000); // Scroll setiap 4 detik

// Pause auto scroll on hover
videoGrid.addEventListener('mouseenter', () => {
    clearInterval(videoAutoScroll);
});

videoGrid.addEventListener('mouseleave', () => {
    videoAutoScroll = setInterval(() => {
        const maxScroll = videoGrid.scrollWidth - videoGrid.parentElement.offsetWidth;
        if(videoScrollPosition >= maxScroll) {
            videoScrollPosition = 0;
        } else {
            videoScrollPosition += cardWidth;
            if(videoScrollPosition > maxScroll) videoScrollPosition = maxScroll;
        }
        videoGrid.style.transform = `translateX(-${videoScrollPosition}px)`;
    }, 4000);
});

// ✅ VIDEO PLAY/PAUSE - SUARA HANYA KELUAR SAAT DIKLIK
function toggleVideo(wrapper, videoSrc) {
    const video = wrapper.querySelector('video');
    const thumbnail = wrapper.querySelector('.video-thumbnail');
    const isPlaying = !video.paused;

    // Pause all other videos
    document.querySelectorAll('.video-wrapper video').forEach(v => {
        if(v !== video) {
            v.pause();
            v.currentTime = 0;
            v.muted = true;
            v.parentElement.classList.remove('playing');
            v.parentElement.querySelector('.video-thumbnail').style.display = 'block';
        }
    });

    if(isPlaying) {
        // Pause video
        video.pause();
        video.muted = true;
        wrapper.classList.remove('playing');
        thumbnail.style.display = 'block';
    } else {
        // Play video with sound
        video.play();
        video.muted = false;
        wrapper.classList.add('playing');
        thumbnail.style.display = 'none';
    }
}

// Reset video when ended
document.querySelectorAll('.video-wrapper video').forEach(video => {
    video.addEventListener('ended', function() {
        this.pause();
        this.currentTime = 0;
        this.muted = true;
        this.parentElement.classList.remove('playing');
        this.parentElement.querySelector('.video-thumbnail').style.display = 'block';
    });
});

// Notification function
function showNotifications() {
    alert('Fitur notifikasi akan menampilkan pesanan Anda yang sedang diproses dan dikirim.');
    // Implementasi notifikasi yang lebih kompleks bisa ditambahkan di sini
}

// ✅ Fungsi Add to Cart dari Modal Detail
function addToCartFromModal() {
    const productId = document.getElementById('modal-product-id').value;
    const productName = document.getElementById('modal-product-name').value;
    const productPrice = document.getElementById('modal-product-price').value;
    
    if(!productId || !productName) {
        alert('Mohon pilih produk terlebih dahulu');
        return;
    }
    
    // Submit form
    document.getElementById('modalFormPesan').submit();
}

// ✅ Fungsi Buy Now dari Modal Detail
function buyNowFromModal() {
    const productId = document.getElementById('modal-product-id').value;
    const productName = document.getElementById('modal-product-name').value;
    const productPrice = document.getElementById('modal-product-price').value;
    
    if(!productId || !productName) {
        alert('Mohon pilih produk terlebih dahulu');
        return;
    }
    
    // Tambah ke cart dulu
    addToCartFromModal();
    
    // Redirect ke checkout
    setTimeout(() => {
        window.location.href = 'checkout.php';
    }, 500);
}

// ✅ Update hidden form values saat modal dibuka
// (sudah dipindah ke listener show.bs.modal di atas)

// Show success message if added to cart
<?php if(isset($_GET['added']) && $_GET['added'] == '1'): ?>
setTimeout(() => {
    alert('Produk berhasil ditambahkan ke keranjang!');
}, 500);
<?php endif; ?>

// Fungsi untuk tombol "Kunjungi" dan "Toko"
function visitStore() {
    // Redirect ke halaman toko/profile penjual
    window.open('https://wa.me/6281934174198?text=Halo%20Texcer%20Hot,%20saya%20tertarik%20dengan%20produk%20Anda', '_blank');
}

// Fungsi untuk tombol "Chat"
function openChat() {
    // Buka WhatsApp chat
    const phone = '6281934174198';
    const message = 'Halo Texcer Hot, saya mau tanya-tanya tentang produknya';
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
}

// ✅ FIX: Fungsi Chat dari modal - kirim nama produk ke WhatsApp
function chatWhatsAppModal() {
    const productName = document.getElementById('modalFormPesan').dataset.productName || 'produk ini';
    const phone = '6281934174198';
    const message = `Halo Texcer Hot 👋\n\nSaya tertarik dengan: *${productName}*\nMohon info lebih lanjut.\n\nTerima kasih! 🙏`;
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
}

// Fungsi untuk tombol keranjang
function addToCartFromModal() {
    const productId = document.getElementById('md-id').value;
    const productName = document.getElementById('md-form-name').value;
    const productPrice = document.getElementById('md-form-price').value;
    
    // Kirim ke server untuk add to cart
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${productId}&name=${encodeURIComponent(productName)}&price=${productPrice}&qty=1`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('Produk berhasil ditambahkan ke keranjang!');
            // Update cart count
            location.reload();
        } else {
            alert('Gagal menambahkan ke keranjang');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Fallback: redirect ke checkout dengan session
        window.location.href = 'checkout.php?added=1';
    });
}

// Fungsi untuk tombol "Beli Sekarang"
function buyNow() {
    const productId = document.getElementById('md-id').value;
    const productName = document.getElementById('md-form-name').value;
    const productPrice = document.getElementById('md-form-price').value;
    
    // Langsung ke checkout
    window.location.href = `checkout.php?buy_now=1&id=${productId}&name=${encodeURIComponent(productName)}&price=${productPrice}&qty=1`;
}

// Fungsi Tampilkan Profil Toko (Update)
// Simpan display asli setiap elemen sebelum disembunyikan
const _originalDisplayMap = new Map();

function showStoreProfile() {
    const modalBody = document.querySelector('#modalDetail .modal-body');
    const children = modalBody.children;
    
    // Simpan display asli & sembunyikan semua kecuali storeProfileContent
    for(let i = 0; i < children.length; i++){
        if(children[i].id !== 'storeProfileContent'){
            // Simpan computed display asli (bukan inline style, tapi yang aktual)
            const computedDisplay = window.getComputedStyle(children[i]).display;
            _originalDisplayMap.set(children[i], computedDisplay);
            children[i].style.display = 'none';
        }
    }
    
    // Tampilkan profil toko
    document.getElementById('storeProfileContent').style.display = 'block';
    modalBody.scrollTop = 0;
    
    // Update judul modal
    document.querySelector('#modalDetail .modal-title').innerText = 'Profil Toko';
}

// Fungsi Kembali ke Produk (Update)
function backToProduct() {
    const modalBody = document.querySelector('#modalDetail .modal-body');
    const children = modalBody.children;
    
    // Kembalikan display asli masing-masing elemen
    for(let i = 0; i < children.length; i++){
        if(children[i].id !== 'storeProfileContent'){
            const originalDisplay = _originalDisplayMap.get(children[i]);
            // Gunakan display asli jika tersimpan, fallback ke '' agar CSS inline dihapus
            children[i].style.display = originalDisplay || '';
        }
    }
    
    // Sembunyikan profil toko
    document.getElementById('storeProfileContent').style.display = 'none';
    
    // Kembalikan judul modal
    const productName = document.getElementById('md-name')?.innerText || 'Detail Produk';
    document.querySelector('#modalDetail .modal-title').innerText = productName;
    
    modalBody.scrollTop = 0;
}
// ==================== FITUR TEXCER HOT ====================

// 🔥 BELI SEKARANG
function buyNow(productId, variant = ''){
    console.log('Beli Sekarang - Product ID:', productId);
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cart.php';
    form.innerHTML = `
        <input type="hidden" name="add_to_cart" value="1">
        <input type="hidden" name="product_id" value="${productId}">
        <input type="hidden" name="quantity" value="1">
        <input type="hidden" name="variant" value="${variant}">
        <input type="hidden" name="redirect" value="checkout">
    `;
    document.body.appendChild(form);
    form.submit();
}

// 🛒 TAMBAH KE KERANJANG
function addToCart(productId, variant = ''){
    console.log('Add to Cart - Product ID:', productId);
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cart.php';
    form.innerHTML = `
        <input type="hidden" name="add_to_cart" value="1">
        <input type="hidden" name="product_id" value="${productId}">
        <input type="hidden" name="quantity" value="1">
        <input type="hidden" name="variant" value="${variant}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// 💬 CHAT WHATSAPP
function chatWhatsApp(productId, productName){
    console.log('Chat WhatsApp - Product:', productName);
    const phone = '6281934174198'; // Nomor WA kamu
    let message = 'Halo Texcer Hot 👋\n\n';
    message += `Saya tertarik dengan: *${productName}*\n`;
    message += `Mohon info lebih lanjut.\n\n`;
    message += `Terima kasih! 🙏`;
    
    const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
    window.open(url, '_blank');
}

// 🏪 INFO TOKO
function showStoreInfo(){
    console.log('Show Store Info');
    window.location.href = 'about.php';
}

// ==================== END FITUR ====================
</script>
<script>
// ========== CART FUNCTIONALITY ==========
let cart = JSON.parse(localStorage.getItem('texcer_cart')) || [];
let wishlist = JSON.parse(localStorage.getItem('texcer_wishlist')) || [];

// Update cart count
function updateCartCount() {
    const cartCount = document.querySelector('.cart-count');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    if (cartCount) {
        cartCount.textContent = totalItems;
        cartCount.style.display = totalItems > 0 ? 'flex' : 'none';
    }
}

// Add to cart
function addToCart(productId, productName, productPrice, productImage, quantity = 1) {
    const existingItem = cart.find(item => item.id === productId);
    
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({
            id: productId,
            name: productName,
            price: productPrice,
            image: productImage,
            quantity: quantity
        });
    }
    
    localStorage.setItem('texcer_cart', JSON.stringify(cart));
    updateCartCount();
    
    // Show notification
    showNotification('Produk ditambahkan ke keranjang!');
}

// Remove from cart
function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    localStorage.setItem('texcer_cart', JSON.stringify(cart));
    updateCartCount();
    renderCart();
    showNotification('Produk dihapus dari keranjang');
}

// Update cart item quantity
function updateCartItemQuantity(productId, quantity) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        if (quantity <= 0) {
            removeFromCart(productId);
        } else {
            item.quantity = quantity;
            localStorage.setItem('texcer_cart', JSON.stringify(cart));
            updateCartCount();
            renderCart();
        }
    }
}

// Render cart items
function renderCart() {
    const cartItems = document.querySelector('.cart-items');
    const cartTotal = document.querySelector('.cart-total');
    
    if (!cartItems) return;
    
    if (cart.length === 0) {
        cartItems.innerHTML = '<p style="text-align: center; padding: 40px; color: #888;">Keranjang kosong</p>';
        if (cartTotal) cartTotal.style.display = 'none';
        return;
    }
    
    let html = '';
    let total = 0;
    
    cart.forEach(item => {
        const subtotal = item.price * item.quantity;
        total += subtotal;
        html += `
            <div class="cart-item" style="display: flex; gap: 15px; padding: 15px; background: #fff; border-radius: 10px; margin-bottom: 10px;">
                <img src="${item.image}" alt="${item.name}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 5px 0; color: #4A3728;">${item.name}</h4>
                    <p style="margin: 0 0 10px 0; color: #8B6F47; font-weight: 600;">Rp ${item.price.toLocaleString('id-ID')}</p>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button onclick="updateCartItemQuantity('${item.id}', ${item.quantity - 1})" style="width: 28px; height: 28px; border: 1px solid #ddd; background: #fff; border-radius: 5px; cursor: pointer;">-</button>
                            <span style="font-weight: 600;">${item.quantity}</span>
                            <button onclick="updateCartItemQuantity('${item.id}', ${item.quantity + 1})" style="width: 28px; height: 28px; border: 1px solid #ddd; background: #fff; border-radius: 5px; cursor: pointer;">+</button>
                        </div>
                        <button onclick="removeFromCart('${item.id}')" style="background: none; border: none; color: #e74c3c; cursor: pointer;"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        `;
    });
    
    cartItems.innerHTML = html;
    
    if (cartTotal) {
        cartTotal.style.display = 'block';
        cartTotal.innerHTML = `
            <div style="border-top: 2px solid #eee; padding-top: 15px; margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; color: #4A3728;">
                    <span>Total</span>
                    <span>Rp ${total.toLocaleString('id-ID')}</span>
                </div>
                <button onclick="checkout()" style="width: 100%; padding: 15px; background: #8B6F47; color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; margin-top: 15px; cursor: pointer;">Checkout</button>
            </div>
        `;
    }
}

// Checkout
function checkout() {
    if (cart.length === 0) {
        showNotification('Keranjang masih kosong!');
        return;
    }
    window.location.href = 'pesanan.php';
}

// ========== WISHLIST FUNCTIONALITY ==========
function toggleWishlist(productId, productName, productPrice, productImage, btn) {
    const index = wishlist.findIndex(item => item.id === productId);
    
    if (index > -1) {
        wishlist.splice(index, 1);
        btn.classList.remove('active');
        showNotification('Dihapus dari disimpan');
    } else {
        wishlist.push({
            id: productId,
            name: productName,
            price: productPrice,
            image: productImage
        });
        btn.classList.add('active');
        showNotification('Produk disimpan');
    }
    
    localStorage.setItem('texcer_wishlist', JSON.stringify(wishlist));
    updateWishlistCount();
}

function updateWishlistCount() {
    const wishlistCount = document.querySelector('.wishlist-count');
    if (wishlistCount) {
        wishlistCount.textContent = wishlist.length;
    }
}

// ========== SHARE FUNCTIONALITY ==========
async function shareProduct(productId, productName, productPrice) {
    const shareData = {
        title: productName,
        text: `Cek menu ${productName} di Texcer Hot - Rp ${productPrice.toLocaleString('id-ID')}`,
        url: window.location.origin + '/texcer2/menu.php?id=' + productId
    };
    
    try {
        if (navigator.share) {
            await navigator.share(shareData);
            showNotification('Berhasil dibagikan!');
        } else {
            // Fallback: copy to clipboard
            await navigator.clipboard.writeText(shareData.url);
            showNotification('Link disalin ke clipboard!');
        }
    } catch (err) {
        if (err.name !== 'AbortError') {
            // Fallback: copy to clipboard
            await navigator.clipboard.writeText(shareData.url);
            showNotification('Link disalin ke clipboard!');
        }
    }
}

// ========== NOTIFICATION ==========
function showNotification(message) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #8B6F47;
        color: #fff;
        padding: 15px 25px;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
    .wishlist-btn.active {
        color: #e74c3c !important;
    }
    .wishlist-btn.active i::before {
        content: '\\f004';
    }
`;
document.head.appendChild(style);

    
    // Add click listeners to wishlist buttons
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const productCard = this.closest('.product-card') || this.closest('.modal-content');
            const productId = productCard.dataset.id || '1';
            const productName = productCard.querySelector('.product-name')?.textContent || 'Produk';
            const productPrice = parseInt(productCard.dataset.price) || 0;
            const productImage = productCard.querySelector('img')?.src || '';
            
            toggleWishlist(productId, productName, productPrice, productImage, this);
        });
    });
    
    // Add click listeners to share buttons
    document.querySelectorAll('.share-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const productCard = this.closest('.product-card') || this.closest('.modal-content');
            const productId = productCard.dataset.id || '1';
            const productName = productCard.querySelector('.product-name')?.textContent || 'Produk';
            const productPrice = parseInt(productCard.dataset.price) || 0;
            
            shareProduct(productId, productName, productPrice);
        });
    });
});
</script>
<script>
// Inisialisasi keranjang dari localStorage
let cart = JSON.parse(localStorage.getItem('texcer_cart')) || [];

// Update badge jumlah di navbar
function updateCartBadge() {
    const badge = document.querySelector('.cart-count');
    if (badge) {
        const total = cart.reduce((sum, item) => sum + item.quantity, 0);
        badge.textContent = total > 0 ? total : '0';
        badge.style.display = total > 0 ? 'flex' : 'none';
    }
}

// Tambah produk ke keranjang
function addToCart(id, name, price, image) {
    // Cek apakah produk sudah ada
    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ id, name, price, image, quantity: 1 });
    }
    
    // Simpan ke localStorage
    localStorage.setItem('texcer_cart', JSON.stringify(cart));
    updateCartBadge();
    
    // Notifikasi
    showNotification('✅ ' + name + ' ditambahkan ke keranjang!');
}

// Notifikasi popup
function showNotification(message) {
    const notif = document.createElement('div');
    notif.textContent = message;
    notif.style.cssText = `
        position: fixed; top: 80px; right: 20px;
        background: #2ecc71; color: #fff;
        padding: 12px 20px; border-radius: 8px;
        font-weight: 600; z-index: 9999;
        animation: fadeInOut 2.5s ease forwards;
    `;
    document.body.appendChild(notif);
    setTimeout(() => notif.remove(), 2500);
}

// CSS untuk animasi notifikasi
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateY(-20px); }
        10% { opacity: 1; transform: translateY(0); }
        80% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-20px); }
    }
`;
document.head.appendChild(style);
</script>

<script>

// Panggil fungsi ini dari tombol "Beli sekarang" di kartu produk
// Contoh: <button onclick="openBuyModal(123)">Beli sekarang</button>
function openBuyModal(productId){
  fetch('get_product_options.php?id=' + productId)
    .then(r => r.json())
    .then(data => {
      if(!data.success){ alert(data.message); return; }

      bmState.product = data.product;
      bmState.productId = data.product.id;
      bmState.basePrice = parseInt(data.product.harga);
      bmState.qty = 1;
      bmState.selectedLevel = null;
      bmState.selectedToppings = [];

      document.getElementById('bmProductName').textContent = data.product.nama;
      document.getElementById('bmProductImg').src = data.product.gambar || 'placeholder.jpg';
      document.getElementById('bmBasePrice').textContent = formatRp(bmState.basePrice);
      document.getElementById('bmQty').textContent = 1;
      document.getElementById('bmNote').value = '';

      // Render level
      const lvlBox = document.getElementById('bmLevels');
      lvlBox.innerHTML = '';
      if(data.levels.length === 0){
        document.getElementById('bmLevelSection').style.display = 'none';
      } else {
        document.getElementById('bmLevelSection').style.display = 'block';
        data.levels.forEach(lv => {
          const el = document.createElement('div');
          el.className = 'bm-option';
          el.dataset.id = lv.id;
          el.innerHTML = `<span>${lv.nama}</span><span class="price">${lv.harga > 0 ? '+'+formatRp(lv.harga) : 'Gratis'}</span>`;
          el.onclick = () => selectLevel(lv, el);
          lvlBox.appendChild(el);
        });
      }

      // Render topping
      const topBox = document.getElementById('bmToppings');
      topBox.innerHTML = '';
      if(data.toppings.length === 0){
        document.getElementById('bmToppingSection').style.display = 'none';
      } else {
        document.getElementById('bmToppingSection').style.display = 'block';
        data.toppings.forEach(tp => {
          const el = document.createElement('div');
          el.className = 'bm-option';
          el.dataset.id = tp.id;
          el.innerHTML = `<span>${tp.nama}</span><span class="price">+${formatRp(tp.harga)}</span>`;
          el.onclick = () => toggleTopping(tp, el);
          topBox.appendChild(el);
        });
      }

      updateTotal();
      document.getElementById('buyModal').classList.add('show');
    })
    .catch(err => alert('Gagal memuat data: ' + err));
}

function closeBuyModal(){
  document.getElementById('buyModal').classList.remove('show');
}

function selectLevel(lv, el){
  document.querySelectorAll('#bmLevels .bm-option').forEach(e => e.classList.remove('selected'));
  el.classList.add('selected');
  bmState.selectedLevel = lv;
  updateTotal();
}

function toggleTopping(tp, el){
  const idx = bmState.selectedToppings.findIndex(x => x.id === tp.id);
  if(idx >= 0){
    bmState.selectedToppings.splice(idx, 1);
    el.classList.remove('selected');
  } else {
    bmState.selectedToppings.push(tp);
    el.classList.add('selected');
  }
  updateTotal();
}

function changeQty(delta){
  bmState.qty = Math.max(1, bmState.qty + delta);
  document.getElementById('bmQty').textContent = bmState.qty;
  updateTotal();
}

function updateTotal(){
  let extra = 0;
  if(bmState.selectedLevel) extra += parseInt(bmState.selectedLevel.harga);
  bmState.selectedToppings.forEach(t => extra += parseInt(t.harga));
  const total = (bmState.basePrice + extra) * bmState.qty;
  document.getElementById('bmTotal').textContent = formatRp(total);
}

function formatRp(n){
  return 'Rp ' + Number(n).toLocaleString('id-ID');
}

function checkoutNow(){
  // Validasi: kalau ada level wajib dipilih
  const lvlSection = document.getElementById('bmLevelSection');
  if(lvlSection.style.display !== 'none' && !bmState.selectedLevel){
    alert('Silakan pilih level pedas terlebih dahulu');
    return;
  }

  const payload = {
    product_id: bmState.productId,
    qty: bmState.qty,
    level_id: bmState.selectedLevel ? bmState.selectedLevel.id : null,
    topping_ids: bmState.selectedToppings.map(t => t.id),
    note: document.getElementById('bmNote').value
  };

  fetch('checkout.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(res => {
    if(res.success){
      alert('Pesanan berhasil dibuat! No: ' + res.order_id);
      closeBuyModal();
      // window.location.href = 'pesanan.php?id=' + res.order_id;
    } else {
      alert('Gagal: ' + res.message);
    }
  });
}

// ============================================================
// STATE
// ============================================================
var dmState = {
  id: null, name: '', price: 0, image: '', category: '',
  action: 'cart', toppings: [], levels: []
};
var omState = {
  id: null, name: '', basePrice: 0, category: '',
  qty: 1, suhu: null, level: null, levelName: '',
  levelExtra: 0,
  toppings: {}, action: 'cart'
};

// ============================================================
// FORMAT RUPIAH
// ============================================================
function dmFmt(n) {
  return 'Rp ' + parseInt(n).toLocaleString('id-ID');
}

// ============================================================
// MODAL 1: DETAIL PRODUK
// ============================================================
function openDetailModal(id, name, price, desc, image, category) {
  dmState = { id:id, name:name, price:price, image:image, category:category, action:'cart' };

  document.getElementById('dmImg').src       = image || 'assets/images/placeholder.jpg';
  document.getElementById('dmName').textContent  = name;
  document.getElementById('dmDesc').textContent  = desc || '';
  document.getElementById('dmPrice').textContent = dmFmt(price);

  document.getElementById('detailModal').style.display = 'flex';
}

function closeDetailModal() {
  document.getElementById('detailModal').style.display = 'none';
}

// Tutup saat klik overlay
document.getElementById('detailModal').addEventListener('click', function(e) {
  if (e.target === this) closeDetailModal();
});

// ============================================================
// MODAL 1: DETAIL PRODUK -> ORDER MODAL (FINAL FIX - NO DELAY)
// Ganti fungsi dmOpenOrder yang lama dengan ini
// ============================================================
function dmOpenOrder(action) {
    // 1. PAKSA TUTUP MODAL BOOTSTRAP (#modalDetail) SECARA INSTAN
    var bsModalEl = document.getElementById('modalDetail');
    var bsModal = bootstrap.Modal.getInstance(bsModalEl);
    if (bsModal) {
        bsModal.hide();
    }
    // Hapus display block/flex yang mungkin tertinggal
    bsModalEl.style.display = 'none';
    bsModalEl.classList.remove('show');
    bsModalEl.setAttribute('aria-hidden', 'true');
    
    // 2. BERSIHKAN BACKDROP (LAYAR GELAP) BOOTSTRAP
    var backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';

    // 3. ISI STATE OMSTATE DENGAN DATA SEGAR DARI DMSTATE
    omState.action    = action;
    omState.id        = dmState.id;
    omState.name      = dmState.name;
    omState.basePrice = parseInt(dmState.price) || 0;
    omState.category  = dmState.category;
    omState.qty       = 1;
    omState.suhu      = null;
    omState.level     = null;
    omState.levelName = '';
    omState.levelPrice = 0;
    omState.toppings  = {};

    // Debugging: Cek data
    if (!omState.id || !omState.name) {
        console.error("Data produk kosong!", dmState);
        alert("Error: Data produk tidak valid. Silakan refresh halaman.");
        return;
    }

    // 4. UPDATE UI HEADER MODAL ORDER
    document.getElementById('omImg').src              = dmState.image || 'assets/images/placeholder.jpg';
    document.getElementById('omName').textContent      = dmState.name;
    document.getElementById('omBaseLabel').textContent = 'Harga dasar: ' + dmFmt(omState.basePrice);
    document.getElementById('omBasePrice').textContent = dmFmt(omState.basePrice);
    document.getElementById('omQty').textContent       = 1;
    document.getElementById('omNote').value            = '';
    document.getElementById('omBreakdown').innerHTML   = '';
    document.getElementById('omLevelWarn').style.display = 'none';
    document.getElementById('omSuhuWarn').style.display  = 'none';
    document.getElementById('omSubmitLabel').textContent =
        action === 'buy' ? '⚡ Beli Sekarang' : '🛒 Tambah ke Keranjang';

    // 5. RENDER SUHU (MINUMAN)
    var cat       = (dmState.category || '').toLowerCase();
    var isMinuman = cat === 'minuman';
    document.getElementById('omSuhuSection').style.display = isMinuman ? 'block' : 'none';
    document.querySelectorAll('#omSuhuSection [onclick]').forEach(function(el) {
        el.style.borderColor = '#e8e8e8';
        el.style.background  = '#fff';
        el.querySelector('div:last-child').style.color = '#1a1a1a';
    });
    omState.suhu = null;

    // 6. RENDER LEVEL PEDAS
    var levels = dmState.levels || [];
    document.getElementById('omLevelSection').style.display = levels.length > 0 ? 'block' : 'none';
    omState.level = null;
    
    if (levels.length > 0) {
        var lvIcons = ['🌶️','🌶️️','🌶️️🌶️','🔥🔥',''];
        var lvHtml  = '';
        levels.forEach(function(lv, i) {
            var extraLabel = lv.price > 0
                ? '<div style="font-size:10px;color:#c17d2a;margin-top:1px;">+' + dmFmt(lv.price) + '</div>'
                : '';
            lvHtml += '<div onclick="omPickLevel(' + lv.id + ',\'' + (lv.name ? lv.name.replace(/'/g,"\\'") : '') + '\',' + (lv.price||0) + ',this)"'
                + ' style="padding:8px 14px;border-radius:10px;border:1.5px solid #e8e8e8;cursor:pointer;text-align:center;min-width:80px;">'
                + '<div style="font-size:16px;">' + (lvIcons[i] || '🌶️') + '</div>'
                + '<div style="font-size:12px;font-weight:600;margin-top:2px;color:#1a1a1a;">' + (lv.name || 'Level '+(i+1)) + '</div>'
                + extraLabel
                + '</div>';
        });
        document.getElementById('omLevelList').innerHTML = lvHtml;
    } else {
        document.getElementById('omLevelList').innerHTML = '';
    }

    // 7. RENDER TOPPING
    var toppings = dmState.toppings || [];
    document.getElementById('omToppingSection').style.display = toppings.length > 0 ? 'block' : 'none';
    omState.toppings = {};
    
    if (toppings.length > 0) {
        omRenderToppings(toppings);
    } else {
        document.getElementById('omToppingList').innerHTML = '';
    }

    // 8. UPDATE HARGA
    omUpdatePrice();

    // 9. TAMPILKAN MODAL ORDER LANGSUNG (TANPA SETTIMEOUT)
    var orderModalEl = document.getElementById('orderModal');
    orderModalEl.style.display = 'flex';
}

function closeOrderModal() {
  document.getElementById('orderModal').style.display = 'none';
}

document.getElementById('orderModal').addEventListener('click', function(e) {
  if (e.target === this) closeOrderModal();
});

// ============================================================
// RENDER TOPPING
// ============================================================
function omRenderToppings(toppings) {
  var html = '';
  for (var i = 0; i < toppings.length; i++) {
    var t   = toppings[i];
    var sel = !!omState.toppings[t.id];
    html += '<div onclick="omToggleTopping(' + t.id + ', \'' + t.name + '\', ' + t.price + ', this)"'
          + ' style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-radius:10px;'
          + 'border:1.5px solid ' + (sel ? '#8B5A2B' : '#e8e8e8') + ';cursor:pointer;background:' + (sel ? '#FFF8EE' : '#fff') + ';">'
          + '<div style="display:flex;align-items:center;gap:10px;">'
          + '<div style="width:20px;height:20px;border-radius:5px;border:2px solid ' + (sel ? '#8B5A2B' : '#ccc') + ';background:' + (sel ? '#8B5A2B' : 'transparent') + ';display:flex;align-items:center;justify-content:center;font-size:12px;color:#fff;">'
          + (sel ? '✓' : '') + '</div>'
          + '<span style="font-size:13px;color:#1a1a1a;">' + t.name + '</span>'
          + '</div>'
          + '<span style="font-size:13px;font-weight:600;color:' + (sel ? '#8B5A2B' : '#888') + ';">+' + dmFmt(t.price) + '</span>'
          + '</div>';
  }
  // Simpan data topping ke state untuk toggle
  window._omToppingsData = toppings;
  document.getElementById('omToppingList').innerHTML = html;
}

// ============================================================
// INTERAKSI
// ============================================================
function omPickSuhu(suhu, el) {
  omState.suhu = suhu;
  document.getElementById('omSuhuWarn').style.display = 'none';
  document.querySelectorAll('#omSuhuSection [onclick]').forEach(function(e) {
    e.style.borderColor = '#e8e8e8';
    e.style.background  = '#fff';
    e.querySelector('div:last-child').style.color = '#1a1a1a';
  });
  el.style.borderColor = '#8B5A2B';
  el.style.background  = '#FFF8EE';
  el.querySelector('div:last-child').style.color = '#8B5A2B';
  omUpdatePrice();
}

function omPickLevel(id, name, price, el) {
  omState.level      = id;
  omState.levelName  = name;
  omState.levelPrice = parseInt(price) || 0;
  document.getElementById('omLevelWarn').style.display = 'none';
  document.querySelectorAll('#omLevelList [onclick]').forEach(function(e) {
    e.style.borderColor = '#e8e8e8';
    e.style.background  = '#fff';
    e.querySelector('div:last-child').style.color = '#1a1a1a';
  });
  el.style.borderColor = '#8B5A2B';
  el.style.background  = '#8B5A2B';
  el.querySelector('div:last-child').style.color = '#fff';
  omUpdatePrice();
}

function omToggleTopping(id, name, price, el) {
  omState.toppings[id] = omState.toppings[id] ? false : {id:id, name:name, price:price};
  if (window._omToppingsData) omRenderToppings(window._omToppingsData);
  omUpdatePrice();
}

function omChangeQty(d) {
  omState.qty = Math.max(1, omState.qty + d);
  document.getElementById('omQty').textContent = omState.qty;
  omUpdatePrice();
}

// ============================================================
// UPDATE HARGA
// ============================================================
function omUpdatePrice() {
  var base      = omState.basePrice;
  var topSum    = 0;
  var levelSum  = omState.levelPrice || 0;
  var breakdown = '';

  for (var id in omState.toppings) {
    if (omState.toppings[id]) {
      var t = omState.toppings[id];
      topSum += parseInt(t.price);
      breakdown += '<div style="display:flex;justify-content:space-between;margin-top:4px;">'
                 + '<span style="font-size:12px;color:#888;">+ ' + t.name + '</span>'
                 + '<span style="font-size:12px;color:#8B5A2B;">+' + dmFmt(t.price) + '</span>'
                 + '</div>';
    }
  }

  if (omState.suhu) {
    breakdown += '<div style="display:flex;justify-content:space-between;margin-top:4px;">'
               + '<span style="font-size:12px;color:#888;">Suhu: ' + omState.suhu + '</span>'
               + '<span style="font-size:12px;color:#888;">Gratis</span></div>';
  }

  if (omState.level) {
    var lvLabel = levelSum > 0 ? '+' + dmFmt(levelSum) : 'Gratis';
    breakdown += '<div style="display:flex;justify-content:space-between;margin-top:4px;">'
               + '<span style="font-size:12px;color:#888;">Level: ' + omState.levelName + '</span>'
               + '<span style="font-size:12px;color:' + (levelSum > 0 ? '#8B5A2B' : '#888') + ';">' + lvLabel + '</span></div>';
  }

  var perPortion = base + topSum + levelSum;
  var total      = perPortion * omState.qty;

  document.getElementById('omBreakdown').innerHTML    = breakdown;
  document.getElementById('omPerPortion').textContent = dmFmt(perPortion);
  document.getElementById('omTotal').textContent      = dmFmt(total);
}

// ============================================================
// SUBMIT ORDER (FINAL FIX: IMAGE & VARIANT UNIQUE)
// ============================================================
// ============================================================
// SUBMIT ORDER (FINAL FIX: IMAGE & VARIANT UNIQUE)
// Ganti seluruh fungsi omSubmit() yang lama dengan ini
// ============================================================
// ============================================================
// SUBMIT ORDER (FINAL FIX: VARIAN SESUAI PILIHAN USER - SUPPORT TOPPING AS VARIANT)
// Ganti seluruh fungsi omSubmit() di index.php dengan ini
// ============================================================
function omSubmit() {
    var cat       = (omState.category || '').toLowerCase();
    var isMinuman = cat === 'minuman';
    
    // 1. Validasi Suhu & Level
    if (isMinuman && !omState.suhu) {
        document.getElementById('omSuhuWarn').style.display = 'block';
        return;
    }
    var lvlSection = document.getElementById('omLevelSection');
    if (lvlSection.style.display !== 'none' && !omState.level) {
        document.getElementById('omLevelWarn').style.display = 'block';
        return;
    }

    // 2. Hitung Harga
    var topSum = 0;
    for (var id2 in omState.toppings) {
        if (omState.toppings[id2]) topSum += parseInt(omState.toppings[id2].price);
    }
    var pricePerItem = omState.basePrice + topSum + (omState.levelPrice || 0);
    
    // 3. ✅ PENTING: Tentukan Nama Varian Yang Benar
    var variantName = 'Regular'; // Default
    
    if (omState.levelName) {
        // Prioritas 1: Gunakan nama level (untuk Mie/Mercon)
        variantName = omState.levelName; 
    } else if (omState.suhu) {
        // Prioritas 2: Gunakan suhu (untuk Minuman)
        variantName = omState.suhu;
    } else {
        // Prioritas 3: Cek apakah ada topping yang merupakan varian utama
        // Untuk Ceker, varian utama adalah Large/Medium/Paket Nasi
        // Kita cek topping pertama yang dipilih dan lihat apakah namanya mengandung kata kunci
        var firstVariantTopping = null;
        for (var id in omState.toppings) {
            if (omState.toppings[id]) {
                var tName = omState.toppings[id].name.toLowerCase();
                // Cek apakah nama topping mengandung kata kunci varian
                if (tName.includes('large') || tName.includes('medium') || tName.includes('paket') || tName.includes('nasi')) {
                    firstVariantTopping = omState.toppings[id].name;
                    break; // Ambil yang pertama saja
                }
            }
        }
        
        if (firstVariantTopping) {
            variantName = firstVariantTopping;
        }
        // Jika tidak ada topping varian, tetap 'Regular'
    }

    console.log("Varian yang akan dikirim:", variantName);

    // 4. Isi Form Hidden
    var form = document.getElementById('modalFormPesan');
    
    document.getElementById('modal-product-id').value      = omState.id;
    document.getElementById('modal-product-name').value    = omState.name; 
    document.getElementById('modal-product-price').value   = pricePerItem; 
    document.getElementById('modal-product-variant').value = variantName;
    
    // Pastikan input hidden image ada
    var imgInput = document.getElementById('modal-product-image');
    if(imgInput) {
        var mdImgEl = document.getElementById('md-img');
        imgInput.value = (mdImgEl && mdImgEl.src) ? mdImgEl.src : '';
    }
    
    form.action = 'index.php';
    form.method = 'POST';
    
    // Logika Buy Now vs Add to Cart
    if (omState.action === 'buy') {
        var existingBuy = form.querySelector('input[name="buy_now"]');
        if(existingBuy) existingBuy.remove();
        var buyInput = document.createElement('input');
        buyInput.type = 'hidden';
        buyInput.name = 'buy_now';
        buyInput.value = '1';
        form.appendChild(buyInput);
    } else {
        var existingBuy = form.querySelector('input[name="buy_now"]');
        if(existingBuy) existingBuy.value = '0';
    }

    // Submit Form
    form.submit();
}
</script>

<?php
// modal_detail_lengkap.php
// Taruh sebelum </body> di index.php
// GANTIKAN <?php include 'modal_topping.php'; ?> dengan file ini
?>

<!-- ========================================
     MODAL 1: DETAIL PRODUK
     Muncul pertama saat kartu diklik
     ======================================== -->
<div id="detailModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9000; align-items:flex-end; justify-content:center;">
  <div style="background:#fff; border-radius:20px 20px 0 0; width:100%; max-width:480px; max-height:92vh; display:flex; flex-direction:column; animation:dmSlideUp .25s ease-out;">

    <!-- Gambar produk -->
    <div style="position:relative; height:220px; flex-shrink:0; overflow:hidden; border-radius:20px 20px 0 0;">
      
      <div style="position:absolute; inset:0; background:linear-gradient(to top, rgba(0,0,0,0.4), transparent);"></div>
      <button onclick="closeDetailModal()" style="position:absolute; top:14px; right:14px; width:34px; height:34px; border-radius:50%; border:none; background:rgba(255,255,255,0.9); font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center;">×</button>
    </div>

    <!-- Info produk -->
    <div style="padding:18px 20px; flex:1; overflow-y:auto;">
      
      <p id="dmDesc" style="margin:0 0 14px; font-size:13px; color:#888; line-height:1.6;"></p>
      <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-top:1px solid #f0f0f0;">
        <span style="font-size:13px; color:#888;">Harga</span>
        <span id="dmPrice" style="font-size:20px; font-weight:700; color:#8B5A2B;"></span>
      </div>
    </div>

    <!-- Tombol aksi -->
    <div style="padding:14px 20px; border-top:1px solid #f0f0f0; display:flex; gap:10px; flex-shrink:0;">
      <button onclick="dmOpenOrder('buy')" style="flex:2; padding:13px; background:#8B5A2B; color:#fff; border:none; border-radius:12px; font-size:14px; font-weight:600; cursor:pointer;">
        Beli Sekarang
      </button>
    </div>

  </div>
</div>

<!-- ========================================
     MODAL 2: PILIH TOPPING / LEVEL / SUHU
     Muncul setelah klik Keranjang/Beli
     ======================================== -->
<div id="orderModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:flex-end; justify-content:center;">
  <div style="background:#fff; border-radius:20px 20px 0 0; width:100%; max-width:480px; max-height:92vh; display:flex; flex-direction:column; animation:dmSlideUp .25s ease-out;">

    <!-- Header -->
    <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #f0f0f0; flex-shrink:0;">
      <div style="display:flex; align-items:center; gap:10px;">
        <img id="omImg" src="" alt="" style="width:44px; height:44px; border-radius:10px; object-fit:cover; background:#f5f5f5;">
        <div>
          <p id="omName" style="margin:0; font-size:15px; font-weight:600; color:#1a1a1a;"></p>
          <p id="omBaseLabel" style="margin:0; font-size:12px; color:#888;"></p>
        </div>
      </div>
      <button onclick="closeOrderModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#888;">×</button>
    </div>

    <!-- Body -->
    <div style="overflow-y:auto; padding:14px 18px; flex:1;">

      <!-- Ringkasan harga -->
      <div style="background:#FFF8EE; border:1px solid #F5DEB3; border-radius:10px; padding:10px 14px; margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; font-size:12px; color:#888; margin-bottom:4px;">
          <span>Harga dasar</span>
          <span id="omBasePrice">Rp 0</span>
        </div>
        <div id="omBreakdown"></div>
        <div style="border-top:1px dashed #F5DEB3; margin-top:8px; padding-top:8px; display:flex; justify-content:space-between;">
          <span style="font-size:13px; font-weight:600; color:#1a1a1a;">Total 1 porsi</span>
          <span id="omPerPortion" style="font-size:13px; font-weight:600; color:#8B5A2B;">Rp 0</span>
        </div>
      </div>

      <!-- SUHU (khusus minuman) -->
      <div id="omSuhuSection" style="display:none; margin-bottom:18px;">
        <div style="display:flex; align-items:center; gap:6px; margin-bottom:10px;">
          <span style="background:#E3F2FD; color:#1565C0; font-size:11px; font-weight:600; padding:2px 9px; border-radius:99px;">Wajib</span>
          <span style="font-size:14px; font-weight:600; color:#1a1a1a;">Suhu</span>
        </div>
        <div style="display:flex; gap:10px;">
          <div onclick="omPickSuhu('Es', this)" style="flex:1; padding:12px; border-radius:10px; border:1.5px solid #e8e8e8; cursor:pointer; text-align:center;">
            <div style="font-size:22px;">🧊</div>
            <div style="font-size:13px; font-weight:600; margin-top:4px;">Es</div>
          </div>
          <div onclick="omPickSuhu('Hangat', this)" style="flex:1; padding:12px; border-radius:10px; border:1.5px solid #e8e8e8; cursor:pointer; text-align:center;">
            <div style="font-size:22px;">☕</div>
            <div style="font-size:13px; font-weight:600; margin-top:4px;">Hangat</div>
          </div>
        </div>
        <p id="omSuhuWarn" style="display:none; color:#e53935; font-size:12px; margin:6px 0 0;">Pilih suhu dulu ya!</p>
      </div>

      <!-- LEVEL PEDAS (khusus mie & mercon) -->
      <div id="omLevelSection" style="display:none; margin-bottom:18px;">
        <div style="display:flex; align-items:center; gap:6px; margin-bottom:10px;">
          <span style="background:#FBE9E7; color:#BF360C; font-size:11px; font-weight:600; padding:2px 9px; border-radius:99px;">Wajib</span>
          <span style="font-size:14px; font-weight:600; color:#1a1a1a;">Level Pedas</span>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:8px;" id="omLevelList">
          <?php 
          $levels_fix = [
            ['id'=>1,'nama'=>'Level 1','icon'=>'🌶️'],
            ['id'=>2,'nama'=>'Level 2','icon'=>'🌶️🌶️'],
            ['id'=>3,'nama'=>'Level 3','icon'=>'🌶️🌶️🌶️'],
            ['id'=>4,'nama'=>'Level 4','icon'=>'🔥🔥'],
            ['id'=>5,'nama'=>'Level 5','icon'=>'💀'],
          ];
          foreach($levels_fix as $lv): ?>
          <div onclick="omPickLevel(<?= $lv['id'] ?>, '<?= $lv['nama'] ?>', this)"
            data-extra="<?= $lv['extra_price'] ?? 0 ?>"   
          
               style="padding:8px 14px; border-radius:10px; border:1.5px solid #e8e8e8; cursor:pointer; text-align:center; min-width:80px;">
            <div style="font-size:16px;"><?= $lv['icon'] ?></div>
            <div style="font-size:12px; font-weight:600; margin-top:2px; color:#1a1a1a;"><?= $lv['nama'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <p id="omLevelWarn" style="display:none; color:#e53935; font-size:12px; margin:6px 0 0;">Pilih level pedas dulu ya!</p>
      </div>

      <!-- TOPPING (mie, mercon, dimsum) -->
      <div id="omToppingSection" style="display:none; margin-bottom:18px;">
        <div style="display:flex; align-items:center; gap:6px; margin-bottom:10px;">
          <span style="background:#FFF3E0; color:#E65100; font-size:11px; font-weight:600; padding:2px 9px; border-radius:99px;">Pilihan</span>
          <span style="font-size:14px; font-weight:600; color:#1a1a1a;">Topping Tambahan</span>
        </div>
        <div id="omToppingList" style="display:flex; flex-direction:column; gap:8px;"></div>
      </div>

      <!-- CATATAN -->
      <div style="margin-bottom:16px;">
        <p style="margin:0 0 6px; font-size:13px; font-weight:600; color:#1a1a1a;">Catatan (opsional)</p>
        <textarea id="omNote" placeholder="Contoh: tidak pakai bawang..." style="width:100%; padding:10px; border:1.5px solid #e8e8e8; border-radius:10px; font-size:13px; resize:none; min-height:60px; font-family:inherit;"></textarea>
      </div>

      <!-- JUMLAH -->
      <div>
        <p style="margin:0 0 8px; font-size:14px; font-weight:600; color:#1a1a1a;">Jumlah</p>
        <div style="display:flex; align-items:center; gap:14px;">
          <button onclick="omChangeQty(-1)" style="width:36px; height:36px; border-radius:50%; border:1.5px solid #ddd; background:#fff; font-size:20px; cursor:pointer; line-height:1; color:#1a1a1a;">−</button>
          <span id="omQty" style="font-size:16px; font-weight:600; min-width:24px; text-align:center; color:#1a1a1a;">1</span>
          <button onclick="omChangeQty(1)" style="width:36px; height:36px; border-radius:50%; border:none; background:#8B5A2B; font-size:20px; cursor:pointer; line-height:1; color:#fff;">+</button>
        </div>
      </div>

    </div>

    <!-- Footer -->
    <div style="padding:12px 18px; border-top:1px solid #f0f0f0; flex-shrink:0;">
      <button id="omSubmitBtn" onclick="omSubmit()" style="width:100%; padding:14px; background:#8B5A2B; color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:space-between;">
        <span id="omSubmitLabel">Tambah ke Keranjang</span>
        <span id="omTotal">Rp 0</span>
      </button>
    </div>

  </div>
</div>

<style>
@keyframes dmSlideUp {
  from { transform: translateY(100%); }
  to   { transform: translateY(0); }
}

</style>

</body>
</html>