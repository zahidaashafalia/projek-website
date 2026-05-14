<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi ke Database texcer1
$host = "localhost";
$user = "root";
$pass = ""; // Kosong jika pakai XAMPP default
$db   = "texcer1";
$port = 3307; 

$conn = mysqli_connect($host, $user, $pass, $db, $port);

// Cek koneksi
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Fungsi Format Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}
// Fungsi untuk mendapatkan ulasan produk
function getProductReviews($conn, $productId) {
    $productId = (int)$productId;
    $result = mysqli_query($conn, "
        SELECT r.*, u.name as customer_name 
        FROM reviews r
        LEFT JOIN users u ON r.customer_phone = u.phone
        WHERE r.product_id = $productId
        ORDER BY r.created_at DESC
    ");
    
    $reviews = [];
    while($row = mysqli_fetch_assoc($result)) {
        $reviews[] = $row;
    }
    return $reviews;
}

// Fungsi untuk mendapatkan rata-rata rating
function getProductRating($conn, $productId) {
    $productId = (int)$productId;
    $result = mysqli_query($conn, "
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
        FROM reviews
        WHERE product_id = $productId
    ");
    return mysqli_fetch_assoc($result);
}

// Cek apakah user sudah review produk ini
function isProductReviewed($conn, $orderId, $productId) {
    $orderId = (int)$orderId;
    $productId = (int)$productId;
    $result = mysqli_query($conn, "
        SELECT COUNT(*) as count FROM reviews 
        WHERE order_id = $orderId AND product_id = $productId
    ");
    $row = mysqli_fetch_assoc($result);
    return $row['count'] > 0;
}
// Tambahkan di bagian bawah config.php

// Fungsi cek login pembeli
function isCustomerLoggedIn() {
    return isset($_SESSION['user_phone']) && ($_SESSION['role'] ?? '') === 'customer';
}

// Fungsi cek login admin
function isAdminLoggedIn() {
    return isset($_SESSION['user_phone']) && ($_SESSION['role'] ?? '') === 'admin';
}

// Fungsi redirect jika belum login
function requireLogin($redirect = 'auth/login.php') {
    if(!isset($_SESSION['user_phone'])){
        header("Location: $redirect");
        exit;
    }
}

// Fungsi redirect jika bukan admin
function requireAdmin($redirect = 'auth/login.php') {
    if(!isAdminLoggedIn()){
        header("Location: $redirect");
        exit;
    }
}
?>