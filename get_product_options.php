<?php
header('Content-Type: application/json');
require_once 'config.php';

// openBuyModal kirim ?id=, bukan ?product_id=
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$product_id) $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit;
}

// Ambil produk
$result  = mysqli_query($conn, "SELECT id, name as nama, price as harga, image as gambar FROM products WHERE id = $product_id LIMIT 1");
$product = mysqli_fetch_assoc($result);
if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    exit;
}

// Ambil topping
$result   = mysqli_query($conn, "
    SELECT t.id, t.name as nama, t.price as harga
    FROM toppings t
    INNER JOIN product_toppings pt ON t.id = pt.topping_id
    WHERE pt.product_id = $product_id AND t.is_active = 1
    ORDER BY t.name ASC
");
$toppings = [];
while ($row = mysqli_fetch_assoc($result)) $toppings[] = $row;

// Ambil level
$result = mysqli_query($conn, "
    SELECT l.id, l.name as nama, l.price as harga
    FROM levels l
    INNER JOIN product_levels pl ON l.id = pl.level_id
    WHERE pl.product_id = $product_id AND l.is_active = 1
    ORDER BY l.sort_order ASC
");
$levels = [];
while ($row = mysqli_fetch_assoc($result)) $levels[] = $row;

echo json_encode([
    'success'  => true,
    'product'  => $product,
    'toppings' => $toppings,
    'levels'   => $levels
]);