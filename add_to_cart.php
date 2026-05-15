<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if(isset($_POST['id'], $_POST['name'], $_POST['price'])) {
    $id = (int)$_POST['id'];
    $name = $_POST['name'];
    $price = (float)$_POST['price'];
    $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;
    
    $cart_item = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'qty' => $qty
    ];
    
    // Cek apakah item sudah ada di cart
    $found = false;
    if(isset($_SESSION['cart'])) {
        foreach($_SESSION['cart'] as $key => $item) {
            if($item['id'] == $id) {
                $_SESSION['cart'][$key]['qty'] += $qty;
                $found = true;
                break;
            }
        }
    }
    
    // Jika belum ada, tambahkan sebagai item baru
    if(!$found) {
        $_SESSION['cart'][] = $cart_item;
    }
    
    echo json_encode([
        'success' => true,
        'cart_count' => count($_SESSION['cart'])
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid data'
    ]);
}
?>