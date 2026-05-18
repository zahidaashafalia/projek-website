<?php
session_start();
include 'config/database.php';

$productId = $_GET['id'] ?? 0;

// Get product info
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

// Get available toppings for this product
$stmt = $conn->prepare("
    SELECT pt.*, mt.name, mt.price as base_price
    FROM product_toppings pt
    JOIN master_toppings mt ON pt.topping_id = mt.id
    WHERE pt.product_id = ? AND pt.is_available = 1
");
$stmt->bind_param("i", $productId);
$stmt->execute();
$toppings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available spice levels for this product
$stmt = $conn->prepare("
    SELECT * FROM product_spice_levels
    WHERE product_id = ? AND is_available = 1
    ORDER BY level_number ASC
");
$stmt->bind_param("i", $productId);
$stmt->execute();
$spiceLevels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$response = [
    'id' => $product['id'],
    'name' => $product['name'],
    'description' => $product['description'],
    'price' => $product['price'],
    'image' => $product['image'],
    'toppings' => $toppings,
    'spiceLevels' => $spiceLevels
];

header('Content-Type: application/json');
echo json_encode($response);
?>