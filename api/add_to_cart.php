<?php
/* api/add_to_cart.php
   Menerima POST dengan field:
     - product_id (int)
     - name       (string)
     - price      (int/float) — harga dasar produk
     - toppings   (JSON string array of {id, name, price})
     - level      (JSON string {id, name, extra_price})  atau kosong
     - redirect   (string) 'checkout' atau kosong

   Menyimpan ke $_SESSION['cart'] lalu mengembalikan JSON.
*/
session_start();
include '../config.php';
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Support both JSON body and form-encoded
if(!$data){
    $data = $_POST;
}

$product_id   = (int)($data['product_id'] ?? $data['id'] ?? 0);
$name         = trim($data['name'] ?? '');
$base_price   = (float)($data['price'] ?? 0);
$toppings_raw = $data['toppings'] ?? '[]';
$level_raw    = $data['level'] ?? null;
$redirect     = $data['redirect'] ?? '';

if(!$product_id || !$name){
    echo json_encode(['success'=>false,'message'=>'Data produk tidak lengkap']);
    exit;
}

$toppings = [];
if (is_array($toppings_raw)) {
    $toppings = $toppings_raw;
} elseif (is_string($toppings_raw) && !empty($toppings_raw)) {
    $decoded = json_decode($toppings_raw, true);
    if (is_array($decoded)) {
        $toppings = $decoded;
    }
}

// Parse level
$level = null;
if($level_raw){
    $level = is_array($level_raw) ? $level_raw : json_decode($level_raw, true);
}

// Hitung extra price
$extra_price = 0;
foreach($toppings as $t){
    $extra_price += (float)($t['price'] ?? 0);
}
if($level){
    $extra_price += (float)($level['extra_price'] ?? 0);
}

$total_item_price = $base_price + $extra_price;

// Buat cart key unik berdasarkan produk + topping + level
$topping_ids = array_map(fn($t)=>(int)($t['id']??0), $toppings);
sort($topping_ids);
$level_id    = $level ? (int)($level['id']??0) : 0;
$cart_key    = $product_id . '_' . implode('-', $topping_ids) . '_' . $level_id;

// Cek apakah kombinasi ini sudah ada di cart
if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$found = false;
foreach($_SESSION['cart'] as $k => $item){
    if(($item['cart_key'] ?? '') === $cart_key){
        $_SESSION['cart'][$k]['qty']++;
        $found = true;
        break;
    }
}

if(!$found){
    $_SESSION['cart'][] = [
        'cart_key'    => $cart_key,
        'id'          => $product_id,
        'name'        => $name,
        'base_price'  => $base_price,
        'price'       => $total_item_price,   // harga sudah termasuk extra
        'qty'          => 1,
        'toppings'     => $toppings,
        'level'        => $level,
        'extra_price'  => $extra_price,
    ];
}

$cart_count = array_sum(array_column($_SESSION['cart'], 'qty'));

echo json_encode([
    'success'    => true,
    'cart_count' => $cart_count,
    'redirect'   => $redirect === 'checkout' ? 'checkout.php' : null,
    'message'    => 'Produk berhasil ditambahkan ke keranjang'
]);