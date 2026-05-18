<?php
// process_order.php
// Letakkan di: texcer2/process_order.php
// Handles: Beli Sekarang (langsung order)
// Untuk add_to_cart.php, logika sama tapi simpan ke session/cart

header('Content-Type: application/json');
session_start();

require_once 'config.php'; // sesuaikan path koneksi DB

// Cek login user
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu', 'redirect' => 'login.php']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

$userId      = $_SESSION['user_id'];
$productId   = intval($_POST['product_id'] ?? 0);
$productName = trim($_POST['product_name'] ?? '');
$qty         = max(1, intval($_POST['qty'] ?? 1));
$price       = floatval($_POST['price'] ?? 0);
$subtotal    = floatval($_POST['subtotal'] ?? 0);
$toppings    = trim($_POST['toppings'] ?? '');
$level       = trim($_POST['level'] ?? '');
$extraPrice  = intval($_POST['extra_price'] ?? 0);

// Validasi
if (!$productId || !$productName || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Data pesanan tidak valid']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Buat order baru
    // Sesuaikan query ini dengan struktur tabel orders kamu
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, status, total_price, created_at)
        VALUES (?, 'pending', ?, NOW())
    ");
    $stmt->execute([$userId, $subtotal]);
    $orderId = $pdo->lastInsertId();

    // 2. Simpan order item dengan topping dan level
    $stmt = $pdo->prepare("
        INSERT INTO order_items 
        (order_id, product_name, qty, price, subtotal, toppings, level, extra_price)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $productName,
        $qty,
        $price,
        $subtotal,
        $toppings ?: null,
        $level ?: null,
        $extraPrice
    ]);

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'message'  => 'Pesanan berhasil dibuat!',
        'order_id' => $orderId,
        'redirect' => 'order_detail.php?id=' . $orderId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal membuat pesanan: ' . $e->getMessage()]);
}
?>