<?php
session_start();
include 'config.php';

// Inisialisasi cart jika belum ada
if(!isset($_SESSION['cart'])){
    $_SESSION['cart'] = [];
}

// Handle Add to Cart
if(isset($_POST['add_to_cart'])){
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $variant = $_POST['variant'] ?? '';
    
    // Cek apakah produk sudah ada di cart
    $found = false;
    foreach($_SESSION['cart'] as $key => $item){
        if($item['product_id'] == $product_id && $item['variant'] == $variant){
            $_SESSION['cart'][$key]['quantity'] += $quantity;
            $found = true;
            break;
        }
    }
    
    // Jika belum ada, tambahkan baru
    if(!$found){
        // Ambil data produk dari database
        $query = mysqli_query($conn, "SELECT * FROM products WHERE id=$product_id");
        $product = mysqli_fetch_assoc($query);
        
        $_SESSION['cart'][] = [
            'product_id' => $product_id,
            'name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'],
            'quantity' => $quantity,
            'variant' => $variant
        ];
    }
    
    $_SESSION['cart_count'] = count($_SESSION['cart']);
        // 🔥 BELI SEKARANG: Redirect ke checkout
    if(isset($_POST['redirect']) && $_POST['redirect'] == 'checkout'){
        header("Location: checkout.php");
        exit;
    }
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// Handle Update Cart
if(isset($_POST['update_cart'])){
    foreach($_POST['quantity'] as $key => $qty){
        if($qty > 0){
            $_SESSION['cart'][$key]['quantity'] = (int)$qty;
        } else {
            unset($_SESSION['cart'][$key]);
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
    $_SESSION['cart_count'] = count($_SESSION['cart']);
    header("Location: cart.php");
    exit;
}

// Handle Remove Item
if(isset($_GET['remove'])){
    $key = (int)$_GET['remove'];
    unset($_SESSION['cart'][$key]);
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    $_SESSION['cart_count'] = count($_SESSION['cart']);
    header("Location: cart.php");
    exit;
}

// Handle Clear Cart
if(isset($_GET['clear'])){
    unset($_SESSION['cart']);
    $_SESSION['cart'] = [];
    $_SESSION['cart_count'] = 0;
    header("Location: cart.php");
    exit;
}

// Hitung total
$total = 0;
$cart_items = $_SESSION['cart'] ?? [];
foreach($cart_items as $item){
    $total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #8B6F4E; --primary-dark: #6B5637; --bg-cream: #FDF8F3; }
        body { background: var(--bg-cream); font-family: 'Segoe UI', sans-serif; }
        .cart-item { background: white; border-radius: 12px; padding: 16px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .cart-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: var(--primary-dark); }
    </style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-shopping-cart me-2"></i>Keranjang Belanja</h2>
    
    <?php if(empty($cart_items)): ?>
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
            <p class="text-muted">Keranjang Anda kosong</p>
            <a href="index.php" class="btn btn-primary">Mulai Belanja</a>
        </div>
    <?php else: ?>
        <form method="POST" action="cart.php">
            <div class="row">
                <div class="col-lg-8">
                    <?php foreach($cart_items as $key => $item): ?>
                    <div class="cart-item d-flex align-items-center gap-3">
                        <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                            <?php if($item['variant']): ?>
                                <small class="text-muted">Varian: <?= htmlspecialchars($item['variant']) ?></small>
                            <?php endif; ?>
                            <div class="text-primary fw-bold">Rp <?= number_format($item['price'], 0, ',', '.') ?></div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="number" name="quantity[<?= $key ?>]" value="<?= $item['quantity'] ?>" 
                                   min="1" class="form-control" style="width: 70px;">
                            <a href="?remove=<?= $key ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus item ini?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="d-flex justify-content-between mt-3">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Lanjut Belanja
                        </a>
                        <div>
                            <button type="submit" name="update_cart" class="btn btn-warning me-2">
                                <i class="fas fa-sync-alt me-2"></i>Update
                            </button>
                            <a href="?clear=1" class="btn btn-danger" onclick="return confirm('Kosongkan keranjang?')">
                                <i class="fas fa-trash me-2"></i>Kosongkan
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mt-4 mt-lg-0">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Ringkasan Pesanan</h5>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Item</span>
                                <span><?= array_sum(array_column($cart_items, 'quantity')) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Total Harga</strong>
                                <strong class="text-primary">Rp <?= number_format($total, 0, ',', '.') ?></strong>
                            </div>
                            <a href="checkout.php" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-credit-card me-2"></i>Checkout Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>