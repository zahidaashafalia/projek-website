<?php include 'config.php';
include 'functions.php';

// 🔥 AMBIL SETTINGS DARI DATABASE DENGAN ERROR HANDLING 🔥
$settings = [];
$settings_query = mysqli_query($conn, "SELECT * FROM settings WHERE id=1 LIMIT 1");
if($settings_query && mysqli_num_rows($settings_query) > 0){
    $settings = mysqli_fetch_assoc($settings_query);
} else {
    // Default values jika tidak ada data
    $settings = [
        'site_title' => 'Texcer Hott',
        'hero_title' => 'ORDER MAKANAN',
        'hero_subtitle' => 'Welcome to Texcer Hot',
        'hero_button_text' => 'Pesan Sekarang',
        'menu_section_title' => 'Menu',
        'menu_section_subtitle' => 'makanan pedas',
        'testimonials_section_title' => 'Testimoni',
        'testimonials_section_subtitle' => 'Lihat apa kata mereka tentang Texcer Hot',
        'location_section_title' => 'Lokasi Kami',
        'location_section_subtitle' => 'Kunjungi Texcer Hot atau pesan online untuk pengiriman',
        'whatsapp_number' => '6281234567890',
        'email' => 'info@texcerhot.com',
        'address' => 'Depan SMPN 1 Bangsri, Jepara',
        'opening_hours' => '11:00 - 20:00 WIB',
        'footer_about' => 'Pesan makanan pedas favoritmu sekarang!',
        'footer_copyright' => '© 2020 - 2026 Texcer Hot'
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

// Logika Tambah ke Cart (Dengan Variant)
if(isset($_POST['add_to_cart'])){
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $variant = $_POST['variant'] ?? '';
    $cart_item = ['id' => $id, 'name' => $name, 'price' => $price, 'qty' => 1, 'variant' => $variant];
    $found = false;
    if(isset($_SESSION['cart'])){
        foreach($_SESSION['cart'] as $key => $item){
            if($item['id'] == $id && $item['variant'] == $variant){
                $_SESSION['cart'][$key]['qty']++;
                $found = true;
                break;
            }
        }
    }
    if(!$found){
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
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'is_available'");
$has_available = mysqli_num_rows($check_col) > 0;

$where = $has_available ? "WHERE p.is_available = 1" : "";
$joinCat = "LEFT JOIN categories c ON p.category_id = c.id";
$selectCat = "c.name as category_name";

$result = mysqli_query($conn, "SELECT p.*, {$selectCat} FROM products p $joinCat $where ORDER BY p.created_at DESC");

$products = [];
while($row = mysqli_fetch_assoc($result)){
    $products[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'price' => $row['price'],
        'category' => $row['category_name'] ?? 'all',
        'image' => !empty($row['image']) ? 'uploads/' . $row['image'] : '',
        'description' => $row['description'] ?? '',
        'has_variant' => false,
        'variants' => []
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
        WHERE pt.product_id = $pid AND t.is_available = 1
        ORDER BY t.display_order
    ");
    $toppings = [];
    while($tr = mysqli_fetch_assoc($t_query)) $toppings[] = $tr;
    $products[$k]['toppings'] = $toppings;
    
    // Levels
    $l_query = mysqli_query($conn, "
        SELECT l.id, l.name, l.extra_price
        FROM product_levels pl
        JOIN levels l ON l.id = pl.level_id
        WHERE pl.product_id = $pid AND l.is_available = 1
        ORDER BY l.display_order
    ");
    $levels = [];
    while($lr = mysqli_fetch_assoc($l_query)) $levels[] = $lr;
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
            <!-- Notification Icon -->
            <button class="nav-icon-btn" onclick="showNotifications()">
                <i class="fas fa-bell"></i>
                <?php if($totalNotifications > 0): ?>
                <span class="notif-count"><?= $totalNotifications ?></span>
                <?php endif; ?>
            </button>
            
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
        ?>
        <div class="product-card" 
            data-bs-toggle="modal" 
            data-bs-target="#modalDetail"
            data-id="<?= $p['id'] ?>"
            data-name="<?= $p['name'] ?>"
            data-price="<?= $p['price'] ?>"
            data-desc="<?= $p['description'] ?>"
            data-img="<?= $p['image'] ?>"
            data-category="<?= $category ?>"
            data-has-variant="<?= $p['has_variant'] ? '1' : '0' ?>"
            data-variants='<?= $p['has_variant'] ? json_encode($p['variants']) : '[]' ?>'>

            <div class="product-image">
                <button class="favorite-btn" onclick="toggleFavorite(this, event)">
                    <i class="far fa-heart"></i>
                </button>
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
</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="text-align: center; margin-top: 50px;">
        <button class="my-basket-btn">More</button>
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
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div class="info-content">
                        <h4>Hubungi Kami</h4>
                        <p>+62 819-3417-4198<br>info@texcerhot.com</p>
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

                    <button type="button" onclick="showStoreInfo()" style="background: none; border: none; cursor: pointer; padding: 8px;">
    <i class="fas fa-store"></i>
    <div style="font-size: 0.75rem; margin-top: 2px;">Toko</div>
</button>

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
            <a href="#">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
                FAQ
            </a>
            <a href="#">
                <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Syarat & Ketentuan
            </a>
            <a href="#">
                <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Kebijakan Privasi
            </a>
            <a href="#">
                <svg viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Hubungi Kami
            </a>
        </div>

        <!-- Kolom Kontak -->
        <div class="footer-section">
            <h4>Kontak</h4>
            <a href="#">
                <svg viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <p><?= htmlspecialchars($settings['address'] ?? 'Depan SMPN 1 Bangsri, Jepara') ?></p>
            </a>
            <a href="#">
                <svg viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <p><?= htmlspecialchars($settings['whatsapp_number'] ?? '+62 819-3417-4198') ?></p>
            </a>
            <a href="#">
                <svg viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <p><?= htmlspecialchars($settings['email'] ?? 'info@texcerhot.com') ?></p>
            </a>
            <a href="#">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <p><?= htmlspecialchars($settings['opening_hours'] ?? '11:00 - 20:00 WIB') ?></p>
            </a>
        </div>

    </div>

    <div class="footer-bottom">
        <p><?= htmlspecialchars($settings['footer_copyright'] ?? '© 2020 - 2026 Texcer Hot') ?></p>
        <p>Didirikan oleh <strong>Ahmad Amrullah Baharuddin</strong> · All rights reserved.</p>
    </div>
</footer>

<!-- Modal Detail Produk -->
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content" style="border: none; border-radius: 0;">
            
            <!-- Header dengan back button -->
            <div class="modal-header" style="border-bottom: 1px solid #e0e0e0; padding: 12px 16px; position: sticky; top: 0; background: white; z-index: 100;">
                <button type="button" class="btn btn-link" data-bs-dismiss="modal" style="text-decoration: none; color: #333; padding: 0; font-size: 1.5rem;">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div style="flex: 1; margin: 0 12px;">
                    <input type="text" placeholder="Cari di Texcer Hot" style="width: 100%; padding: 8px 12px; border: 1px solid #e0e0e0; border-radius: 8px; background: #f5f5f5;">
                </div>
                <button class="btn btn-link" style="color: #333; padding: 0;">
                    <i class="fas fa-share-alt"></i>
                </button>
            </div>

            <div class="modal-body" style="padding: 0; overflow-y: auto; max-height: calc(100vh - 140px);">
                
                <!-- Product Image Slider -->
                <div style="position: relative; background: white;">
                    <img src="" id="md-img" class="img-fluid" 
                    style="width: 100%; max-height: 400px; object-fit: cover;"
                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22%3EGambar Tidak Tersedia%3C/text%3E%3C/svg%3E'">
                    <span style="position: absolute; bottom: 12px; right: 12px; background: rgba(0,0,0,0.6); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem;">1/1</span>
                </div>

<!-- Flash Sale Banner -->
<div style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); padding: 12px 16px; color: white;">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
            <span style="background: white; color: var(--primary); padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.85rem; margin-right: 8px;">-58%</span>
            <span style="font-weight: 600;">Mulai <span id="md-price-flash" style="font-size: 1.3rem; font-weight: 800;"></span></span>
        </div>
        <div style="text-align: right;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-bolt" style="color: #FFD700;"></i>
                <span style="font-weight: 700;">Flash Sale</span>
            </div>
            <div style="font-size: 0.8rem; opacity: 0.9;">Berakhir dalam 07:04:20</div>
        </div>
    </div>
</div>

                <!-- PayLater Info -->
                <div style="padding: 12px 16px; border-bottom: 1px solid #f0f0f0;">
                    <div style="font-size: 0.85rem; color: #666;">
                        <i class="fas fa-credit-card" style="color: #6d5236; margin-right: 6px;"></i>
                        PayLater dari Rp<span id="md-paylater">0</span>/bln | <span style="background: rgba(139,111,78,0.1); color: var(--primary); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">Aktifkan batas kredit hingga Rp50 JT</span>
                        <i class="fas fa-chevron-right" style="float: right; color: #999;"></i>
                    </div>
                </div>

                <!-- Discount Badge -->
                <div style="padding: 8px 16px; background: rgba(139,111,78,0.1);">
                <span style="background: rgba(139,111,78,0.2); color: var(--primary); padding: 2px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                <i class="fas fa-tag" style="margin-right: 4px;"></i>Diskon Rp15rb
                </span>
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
                        <i class="far fa-bookmark" style="margin-left: auto; color: #999; font-size: 1.2rem;"></i>
                    </div>
                </div>

                <!-- Shipping Info -->
                <div style="padding: 12px 16px; border-bottom: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 0.9rem;">
                       <i class="fas fa-truck" style="color: var(--primary); margin-top: 3px;"></i>
                        <div style="flex: 1;">
                            <span style="background: rgba(139,111,78,0.15); color: var(--primary); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">Pengiriman gratis</span>
                            <span style="color: #333; margin-left: 6px;">Dijamin tiba paling lambat pada 14-16 Mei</span>
                        </div>
                    </div>
                    <div style="margin-top: 8px; padding-left: 28px; font-size: 0.85rem; color: #666;">
                        Dapatkan voucher senilai min. Rp25K jika pesanan terlambat tiba
                        <i class="fas fa-info-circle" style="margin-left: 4px;"></i>
                    </div>
                    <div style="margin-top: 6px; padding-left: 28px; font-size: 0.85rem; color: #666;">
                        Ongkir: Rp20.500
                    </div>
                </div>

                <!-- Protection -->
                <div style="padding: 12px 16px; border-bottom: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #333;">
                        <i class="fas fa-shield-alt" style="color: #666;"></i>
                        <span>Proteksi Rusak Total | Bayar di tempat | Pengembalian Gratis</span>
                        <i class="fas fa-chevron-right" style="margin-left: auto; color: #999;"></i>
                    </div>
                </div>

                <!-- Variants -->
                <div style="padding: 12px 16px; border-bottom: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-th" style="color: #666;"></i>
                        <span style="flex: 1; font-size: 0.9rem;">4 opsi tersedia</span>
                        <i class="fas fa-chevron-right" style="color: #999;"></i>
                    </div>
                </div>

 <!-- Voucher & Promo -->
<div style="padding: 16px; border-bottom: 1px solid #f0f0f0;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
        <h4 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Voucher & Promo</h4>
        <i class="fas fa-chevron-right" style="color: #999;"></i>
    </div>
    <div style="display: flex; gap: 12px; overflow-x: auto;">
        <div style="background: rgba(139,111,78,0.05); border: 1px solid var(--primary); border-radius: 8px; padding: 12px; min-width: 280px; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-truck" style="color: var(--primary); font-size: 1.5rem;"></i>
                <div style="flex: 1;">
                    <div style="font-weight: 700; color: var(--primary); font-size: 1.1rem;">Diskon Rp30rb</div>
                    <div style="font-size: 0.8rem; color: #666;">untuk pesanan di atas Rp20rb</div>
                </div>
                <button style="background: var(--primary); border: 1px solid var(--primary); color: white; padding: 6px 16px; border-radius: 20px; font-weight: 600; font-size: 0.85rem;">Gunakan</button>
            </div>
        </div>
    </div>
</div>

                <!-- PayLater Section -->
                <div style="padding: 16px; border-bottom: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h4 style="font-size: 1.1rem; font-weight: 600; margin: 0 0 4px 0;">PayLater</h4>
                            <i class="fas fa-credit-card" style="color: var(--primary); margin-right: 6px;"></i>
                        </div>
                        <i class="fas fa-chevron-right" style="color: #999;"></i>
                    </div>
                </div>

                <!-- Reviews Section -->
                <div style="padding: 16px; border-bottom: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <h4 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Ulasan</h4>
                        <span style="color: #999; font-size: 0.9rem;">(28)</span>
                    </div>
                    
                    <!-- Review Item -->
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #666;">**</div>
                            <div style="color: #FFB800;">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 4px;">Varian: PUTIH</div>
                        <div style="font-size: 0.9rem; color: #333; line-height: 1.5;">Kapasitas:lumayan luas, uang sm make'up kecil masi bisa masuk hih...</div>
                        <div style="display: flex; gap: 8px; margin-top: 12px; overflow-x: auto;">
                            <img src="https://via.placeholder.com/80" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                            <img src="https://via.placeholder.com/80" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                            <img src="https://via.placeholder.com/80" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                            <div style="width: 80px; height: 80px; background: #333; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0;">+2</div>
                        </div>
                    </div>
                </div>

                <!-- Store Info -->
                <div style="padding: 16px; border-bottom: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-store" style="font-size: 1.5rem; color: #999;"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 1rem;">Texcer Hot</div>
                            <div style="font-size: 0.85rem; color: #666;">
                                <i class="fas fa-star" style="color: #ffd500;"></i> 3.6 &nbsp; 24.6K terjual
                            </div>
                        </div>
                        <button onclick="showStoreProfile()" style="background: var(--primary); border: none; padding: 8px 16px; border-radius: 20px; font-weight: 600; color: white; cursor: pointer;">Kunjungi</button>
                    </div>
                    <div style="margin-top: 12px; font-size: 0.85rem; color: #666;">
                        <span>100% merespons dalam 24j</span> &nbsp; 
                        <span>69% mengirim dalam 48j</span>
                    </div>
                </div>

                <!-- Komisi Section -->
                <div style="padding: 12px 16px; background: #FFF8E1; border-bottom: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-fire" style="color: #FF9800;"></i>
                        <span style="flex: 1; font-size: 0.9rem;">Bagikan untuk mendapatkan <strong style="color: #FF5858;">Rp5.000</strong> per penjualan</span>
                        <i class="fas fa-chevron-up" style="color: #999;"></i>
                    </div>
                    <div style="font-size: 0.85rem; color: #999; margin-top: 4px;">Komisi 5%</div>
                </div>
            </div>

            <!-- ✅ PROFIL TOKO (Awalnya disembunyikan) -->
<div id="storeProfileContent" style="display: none;">
    <!-- Cover Foto Toko -->
    <div style="width: 100%; height: 200px; background-color: #eee; position: relative;">
        <img src="assets/images/logo-texcer.png" style="width:100%; height:100%; object-fit: cover;">
        <!-- Tombol Back -->
        <button onclick="backToProduct()" style="position: absolute; top: 15px; left: 15px; background: white; border: none; width: 40px; height: 40px; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2); cursor: pointer; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-arrow-left" style="color: var(--primary);"></i>
        </button>
    </div>

    <div style="padding: 20px;">
        <!-- Logo & Nama Toko -->
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: #eee; overflow: hidden; border: 2px solid var(--primary);">
                <img src="assets/images/logo-texcer.png" style="width:100%; height:100%; object-fit: cover;">
            </div>
            <div>
                <h3 style="margin: 0; font-size: 1.3rem; font-weight: 700;">Texcer Hot</h3>
                <p style="margin: 2px 0 0; color: var(--text-gray); font-size: 0.85rem;">
                    <i class="fas fa-star" style="color: #ffd500;"></i> 4.8 (100+ Rating) &nbsp;|&nbsp; Online
                </p>
            </div>
        </div>

        <!-- Deskripsi Toko -->
        <div style="background: var(--bg-cream); padding: 15px; border-radius: 12px; margin-bottom: 20px;">
            <h5 style="margin: 0 0 8px; font-size: 1rem;">Tentang Toko</h5>
            <p style="margin: 0; font-size: 0.9rem; color: var(--text-gray); line-height: 1.5;">
                Texcer Hot Cabang Bangsri - Pusat kuliner pedas dan nikmat di Jepara. 
                Kami menyediakan Mie Yamin, Dimsum, dan Minuman segar. 
                Dibuka sejak 2020 dengan resep turun temurun.
            </p>
        </div>

        <!-- Info Lokasi -->
        <div style="margin-bottom: 20px;">
            <h5 style="font-size: 1rem; margin-bottom: 10px;">Lokasi & Jam Buka</h5>
            <p style="font-size: 0.9rem; margin: 5px 0; color: var(--text-gray);">
                <i class="fas fa-map-marker-alt" style="width: 20px; color: var(--primary);"></i> Depan SMPN 1 Bangsri, Jepara
            </p>
            <p style="font-size: 0.9rem; margin: 5px 0; color: var(--text-gray);">
                <i class="fas fa-clock" style="width: 20px; color: var(--primary);"></i> Buka: 11.00 - 20.00 WIB
            </p>
        </div>

        <!-- Tombol Aksi -->
        <div style="display: flex; gap: 10px;">
            <a href="https://wa.me/6281934174198" target="_blank" style="flex: 1; padding: 12px; background: #25D366; color: white; text-decoration: none; text-align: center; border-radius: 20px; font-weight: 600;">
                <i class="fab fa-whatsapp"></i> Chat Admin
            </a>
            <a href="https://www.google.com/maps/dir/Current+Location/-6.523646052367316,110.7668596153108" target="_blank" style="flex: 1; padding: 12px; background: var(--primary); color: white; text-decoration: none; text-align: center; border-radius: 20px; font-weight: 600;">
                <i class="fas fa-map"></i> Petunjuk Arah
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

    <!-- ✅ FIX: Tombol Keranjang → submit form, buy_now=0 -->
    <button type="submit" form="modalFormPesan"
        onclick="document.getElementById('modal-buy-now').value='0'"
        style="background: #f0f0f0; border: none; border-radius: 50%; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; margin: 0 4px; cursor: pointer; flex-shrink: 0;">
        <i class="fas fa-shopping-cart" style="color: var(--primary); font-size: 1.3rem;"></i>
    </button>

    <!-- ✅ FIX: Tombol Beli Sekarang → submit form, buy_now=1 -->
    <button type="submit" form="modalFormPesan"
        onclick="document.getElementById('modal-buy-now').value='1'"
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
                    <button type="button" onclick="addToCart(<?= $product['id'] ?>, document.getElementById('variantSelect')?.value || '')" style="background: none; border: none; cursor: pointer; padding: 8px;">
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

// Modal Detail
var modal = document.getElementById('modalDetail');
modal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var imgSrc = button.getAttribute('data-img');
    
    // Set image dengan fallback
    document.getElementById('md-img').src = imgSrc || 'assets/images/placeholder.png';
    document.getElementById('md-name').innerText = button.getAttribute('data-name');
    document.getElementById('md-price').innerText = "Rp " + parseInt(button.getAttribute('data-price')).toLocaleString('id-ID');
    document.getElementById('md-desc').innerText = button.getAttribute('data-desc') || '';

    // ✅ FIX: isi harga di tombol Beli Sekarang
    document.getElementById('md-price-bottom').innerText = "Rp " + parseInt(button.getAttribute('data-price')).toLocaleString('id-ID');

    // ✅ FIX: isi semua hidden input form supaya Keranjang & Beli Sekarang berfungsi
    document.getElementById('modal-product-id').value    = button.getAttribute('data-id');
    document.getElementById('modal-product-name').value  = button.getAttribute('data-name');
    document.getElementById('modal-product-price').value = button.getAttribute('data-price');
    document.getElementById('modal-buy-now').value       = '0'; // reset tiap buka modal

    // ✅ FIX: simpan nama produk untuk fungsi chat WhatsApp modal
    document.getElementById('modalFormPesan').dataset.productName = button.getAttribute('data-name');
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

// Fungsi Tampilkan Profil Toko
function showStoreProfile() {
    // Sembunyikan semua isi modal body (detail produk)
    var modalBody = document.querySelector('#modalDetail .modal-body');
    var children = modalBody.children;
    for(var i = 0; i < children.length; i++){
        if(children[i].id !== 'storeProfileContent'){
            children[i].style.display = 'none';
        }
    }
    // Tampilkan profil toko
    document.getElementById('storeProfileContent').style.display = 'block';
    modalBody.scrollTop = 0;
}

// Fungsi Kembali ke Produk
function backToProduct() {
    // Sembunyikan profil toko
    document.getElementById('storeProfileContent').style.display = 'none';
    
    // Tampilkan kembali semua isi modal body
    var modalBody = document.querySelector('#modalDetail .modal-body');
    var children = modalBody.children;
    for(var i = 0; i < children.length; i++){
        if(children[i].id !== 'storeProfileContent'){
            children[i].style.display = 'block';
        }
    }
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

</body>
</html>