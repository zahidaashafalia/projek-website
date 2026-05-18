<?php
// add_to_cart.php
// Letakkan di: texcer2/add_to_cart.php
// Handles: Tambah ke keranjang (simpan ke session)

header('Content-Type: application/json');
session_start();

// Cek login user
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu', 'redirect' => 'login.php']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

$productId         = intval($_POST['product_id'] ?? 0);
$productName       = trim($_POST['product_name'] ?? '');
$qty               = max(1, intval($_POST['qty'] ?? 1));
$price             = floatval($_POST['price'] ?? 0);      // harga satuan (sudah termasuk extra)
$subtotal          = floatval($_POST['subtotal'] ?? 0);
$toppings          = trim($_POST['toppings'] ?? '');       // string nama topping
$level             = trim($_POST['level'] ?? '');
$extraPrice        = intval($_POST['extra_price'] ?? 0);
$selectedToppings  = json_decode($_POST['selected_toppings'] ?? '[]', true);
$selectedLevel     = json_decode($_POST['selected_level'] ?? 'null', true);

// Validasi
if (!$productId || !$productName || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

// Inisialisasi cart di session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Buat cart key unik berdasarkan produk + topping + level
// Supaya produk yang sama tapi beda topping dianggap item terpisah
$cartKey = $productId . '_' . md5($toppings . $level);

if (isset($_SESSION['cart'][$cartKey])) {
    // Sudah ada di cart: tambah qty dan subtotal
    $_SESSION['cart'][$cartKey]['qty']      += $qty;
    $_SESSION['cart'][$cartKey]['subtotal'] += $subtotal;
} else {
    // Tambah baru ke cart
    $_SESSION['cart'][$cartKey] = [
        'product_id'        => $productId,
        'product_name'      => $productName,
        'qty'               => $qty,
        'price'             => $price,
        'subtotal'          => $subtotal,
        'toppings'          => $toppings,
        'level'             => $level,
        'extra_price'       => $extraPrice,
        'selected_toppings' => $selectedToppings,
        'selected_level'    => $selectedLevel,
    ];
}

$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));

echo json_encode([
    'success'    => true,
    'message'    => $productName . ' berhasil ditambahkan ke keranjang!',
    'cart_count' => $cartCount,
    'redirect'   => null
]);
?>