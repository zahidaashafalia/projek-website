<?php
/* api/get_product_options.php
   Kembalikan topping_id[] dan level_id[] yang aktif untuk suatu produk.
   Dipanggil dari products.php admin saat edit produk.
*/
session_start();
include '../config.php';

header('Content-Type: application/json');

$product_id = (int)($_GET['product_id'] ?? 0);
if(!$product_id){ echo json_encode(['toppings'=>[],'levels'=>[]]); exit; }

$tops = [];
$r = mysqli_query($conn, "SELECT topping_id FROM product_toppings WHERE product_id=$product_id");
while($row = mysqli_fetch_assoc($r)) $tops[] = (int)$row['topping_id'];

$lvls = [];
$r = mysqli_query($conn, "SELECT level_id FROM product_levels WHERE product_id=$product_id");
while($row = mysqli_fetch_assoc($r)) $lvls[] = (int)$row['level_id'];

echo json_encode(['toppings' => $tops, 'levels' => $lvls]);