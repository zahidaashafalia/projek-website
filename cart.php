<?php
session_start();
include 'config.php';

// Inisialisasi cart jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ============================================================
// HANDLE ADD TO CART
// ============================================================
if (isset($_POST['add_to_cart'])) {

    $product_id   = (int)$_POST['product_id'];
    $product_name = trim($_POST['product_name'] ?? '');
    $price        = (float)$_POST['price'];         // harga dasar satuan
    $quantity     = max(1, (int)($_POST['quantity'] ?? 1));
    $variant      = trim($_POST['variant'] ?? '');

    // Decode topping & level
    $toppings   = isset($_POST['toppings'])     ? json_decode($_POST['toppings'],     true) : [];
    $spiceLevel = isset($_POST['spice_level'])  ? json_decode($_POST['spice_level'],  true) : null;

    // Hitung extra cost dari topping & level
    $extraCost = 0;
    if (!empty($toppings)) {
        foreach ($toppings as $t) {
            $extraCost += (float)($t['price'] ?? 0);
        }
    }
    if (!empty($spiceLevel)) {
        $extraCost += (float)($spiceLevel['price'] ?? 0);
    }

    $unitPrice  = $price + $extraCost;         // harga satuan sudah termasuk extra
    $totalPrice = $unitPrice * $quantity;

    // Ambil gambar dari database
    $q       = mysqli_query($conn, "SELECT image FROM products WHERE id=" . $product_id);
    $prodRow = mysqli_fetch_assoc($q);
    $image   = $prodRow['image'] ?? '';

    // Signature unik: produk + variant + topping + level
    $optionSignature = md5($product_id . $variant . json_encode($toppings) . json_encode($spiceLevel));

    // Cek apakah item dengan opsi sama sudah ada di cart
    $found = false;
    foreach ($_SESSION['cart'] as $key => $item) {
        if (
            $item['product_id'] == $product_id &&
            isset($item['option_signature']) &&
            $item['option_signature'] === $optionSignature
        ) {
            // Sudah ada → tambah qty & total
            $_SESSION['cart'][$key]['quantity']    += $quantity;
            $_SESSION['cart'][$key]['total_price']  = $unitPrice * $_SESSION['cart'][$key]['quantity'];
            $found = true;
            break;
        }
    }

    // Belum ada → tambah sebagai item baru
    if (!$found) {
        $_SESSION['cart'][] = [
            'product_id'       => $product_id,
            'name'             => $product_name,
            'price'            => $price,          // harga dasar (tanpa extra)
            'unit_price'       => $unitPrice,      // harga satuan (sudah + extra)
            'image'            => $image,
            'quantity'         => $quantity,
            'variant'          => $variant,
            'toppings'         => $toppings,
            'spice_level'      => $spiceLevel,
            'extra_cost'       => $extraCost,
            'total_price'      => $totalPrice,
            'option_signature' => $optionSignature,
            'added_at'         => date('Y-m-d H:i:s'),
        ];
    }

    $_SESSION['cart_count'] = count($_SESSION['cart']);

    // Beli Sekarang → langsung ke checkout
    if (isset($_POST['redirect']) && $_POST['redirect'] === 'checkout') {
        header("Location: checkout.php");
        exit;
    }

    header("Location: cart.php");
    exit;
}

// ============================================================
// HANDLE UPDATE CART (ubah qty)
// ============================================================
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $key => $qty) {
        $qty = (int)$qty;
        if ($qty > 0) {
            $_SESSION['cart'][$key]['quantity']    = $qty;
            $_SESSION['cart'][$key]['total_price'] = $_SESSION['cart'][$key]['unit_price'] * $qty;
        } else {
            unset($_SESSION['cart'][$key]);
        }
    }
    $_SESSION['cart']       = array_values($_SESSION['cart']);
    $_SESSION['cart_count'] = count($_SESSION['cart']);
    header("Location: cart.php");
    exit;
}

// ============================================================
// HANDLE REMOVE ITEM
// ============================================================
if (isset($_GET['remove'])) {
    $key = (int)$_GET['remove'];
    unset($_SESSION['cart'][$key]);
    $_SESSION['cart']       = array_values($_SESSION['cart']);
    $_SESSION['cart_count'] = count($_SESSION['cart']);
    header("Location: cart.php");
    exit;
}

// ============================================================
// HANDLE CLEAR CART
// ============================================================
if (isset($_GET['clear'])) {
    $_SESSION['cart']       = [];
    $_SESSION['cart_count'] = 0;
    header("Location: cart.php");
    exit;
}

// ============================================================
// HITUNG TOTAL KESELURUHAN
// ============================================================
$cart_items = $_SESSION['cart'] ?? [];
$total      = 0;
$totalItems = 0;
foreach ($cart_items as $item) {
    // Gunakan total_price yang sudah memperhitungkan extra + qty
    $total      += $item['total_price'] ?? ($item['unit_price'] ?? $item['price']) * $item['quantity'];
    $totalItems += $item['quantity'];
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
        :root {
            --primary:      #8B6F4E;
            --primary-dark: #6B5637;
            --bg-cream:     #FDF8F3;
        }
        body { background: var(--bg-cream); font-family: 'Segoe UI', sans-serif; }

        .cart-item {
            background: white;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .cart-item img {
            width: 80px; height: 80px;
            object-fit: cover;
            border-radius: 10px;
            flex-shrink: 0;
        }

        /* Badge topping */
        .topping-badge {
            display: inline-block;
            background: #fff3e0;
            color: #b85c00;
            border: 1px solid #f0c080;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 9px;
            margin: 2px 2px 0 0;
        }
        /* Badge level */
        .level-badge {
            display: inline-block;
            background: #fff0f0;
            color: #c0392b;
            border: 1px solid #f0b0b0;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 9px;
            margin: 2px 0 0 0;
        }
        .extra-cost-tag {
            font-size: 11px;
            color: #999;
        }

        .btn-primary  { background: var(--primary); border: none; }
        .btn-primary:hover { background: var(--primary-dark); }

        .summary-card {
            position: sticky;
            top: 20px;
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .qty-input {
            width: 65px;
            text-align: center;
            border-radius: 8px;
            border: 1.5px solid #ddd;
            padding: 4px 6px;
            font-size: 14px;
            font-weight: 600;
        }
        .qty-input:focus { outline: none; border-color: var(--primary); }

        .price-base   { font-size: 12px; color: #999; text-decoration: line-through; }
        .price-unit   { font-size: 14px; font-weight: 700; color: var(--primary); }
        .price-total  { font-size: 13px; color: #555; }
    </style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4 fw-bold">
        <i class="fas fa-shopping-cart me-2" style="color: var(--primary)"></i>Keranjang Belanja
    </h2>

    <?php if (empty($cart_items)): ?>
        <!-- CART KOSONG -->
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-3 d-block"></i>
            <p class="text-muted">Keranjang Anda masih kosong</p>
            <a href="index.php" class="btn btn-primary px-4">
                <i class="fas fa-utensils me-2"></i>Lihat Menu
            </a>
        </div>

    <?php else: ?>
        <form method="POST" action="cart.php">
            <div class="row g-4">

                <!-- ===== DAFTAR ITEM ===== -->
                <div class="col-lg-8">
                    <?php foreach ($cart_items as $key => $item):
                        $unitPrice  = $item['unit_price']  ?? $item['price'];
                        $totalPrice = $item['total_price'] ?? ($unitPrice * $item['quantity']);
                        $extraCost  = $item['extra_cost']  ?? 0;
                        $toppings   = $item['toppings']    ?? [];
                        $spiceLevel = $item['spice_level'] ?? null;
                        $hasExtra   = !empty($toppings) || !empty($spiceLevel);
                    ?>
                    <div class="cart-item d-flex align-items-start gap-3">

                        <!-- Gambar -->
                        <img src="uploads/<?= htmlspecialchars($item['image'] ?? '') ?>"
                             alt="<?= htmlspecialchars($item['name'] ?? '') ?>"
                             onerror="this.src='uploads/default.png'">

                        <!-- Info produk -->
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($item['name'] ?? '') ?></h6>

                            <?php if (!empty($item['variant'])): ?>
                                <small class="text-muted">Varian: <?= htmlspecialchars($item['variant']) ?></small><br>
                            <?php endif; ?>

                            <!-- Topping yang dipilih -->
                            <?php if (!empty($toppings)): ?>
                                <div class="mt-1">
                                    <small class="text-muted">Topping:</small><br>
                                    <?php foreach ($toppings as $t): ?>
                                        <span class="topping-badge">
                                            <?= htmlspecialchars($t['name'] ?? '') ?>
                                            +<?= number_format($t['price'] ?? 0, 0, ',', '.') ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Level pedas yang dipilih -->
                            <?php if (!empty($spiceLevel)): ?>
                                <div class="mt-1">
                                    <span class="level-badge">
                                        🌶️ <?= htmlspecialchars($spiceLevel['level_name'] ?? '') ?>
                                        <?php if (($spiceLevel['price'] ?? 0) > 0): ?>
                                            +<?= number_format($spiceLevel['price'], 0, ',', '.') ?>
                                        <?php else: ?>
                                            (Gratis)
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Harga -->
                            <div class="mt-2">
                                <?php if ($hasExtra && $extraCost > 0): ?>
                                    <div class="price-base">
                                        Rp <?= number_format($item['price'], 0, ',', '.') ?> + extra Rp <?= number_format($extraCost, 0, ',', '.') ?>
                                    </div>
                                <?php endif; ?>
                                <div class="price-unit">
                                    Rp <?= number_format($unitPrice, 0, ',', '.') ?> / porsi
                                </div>
                                <div class="price-total">
                                    Total: <strong>Rp <?= number_format($totalPrice, 0, ',', '.') ?></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Qty & hapus -->
                        <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                            <input type="number"
                                   name="quantity[<?= $key ?>]"
                                   value="<?= $item['quantity'] ?>"
                                   min="1" max="99"
                                   class="qty-input">
                            <a href="?remove=<?= $key ?>"
                               class="btn btn-sm btn-outline-danger rounded-pill px-3"
                               onclick="return confirm('Hapus item ini?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>

                    </div>
                    <?php endforeach; ?>

                    <!-- Tombol bawah daftar -->
                    <div class="d-flex justify-content-between mt-3 flex-wrap gap-2">
                        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-arrow-left me-2"></i>Lanjut Belanja
                        </a>
                        <div class="d-flex gap-2">
                            <button type="submit" name="update_cart" class="btn btn-warning rounded-pill px-4">
                                <i class="fas fa-sync-alt me-2"></i>Update
                            </button>
                            <a href="?clear=1" class="btn btn-outline-danger rounded-pill px-4"
                               onclick="return confirm('Kosongkan semua keranjang?')">
                                <i class="fas fa-trash me-2"></i>Kosongkan
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ===== RINGKASAN PESANAN ===== -->
                <div class="col-lg-4">
                    <div class="summary-card">
                        <h5 class="fw-bold mb-3">Ringkasan Pesanan</h5>

                        <div class="d-flex justify-content-between mb-2 text-muted">
                            <span>Total Item</span>
                            <span><?= $totalItems ?> porsi</span>
                        </div>

                        <?php
                        // Breakdown per produk
                        foreach ($cart_items as $item):
                            $unitPrice  = $item['unit_price']  ?? $item['price'];
                            $totalPrice = $item['total_price'] ?? ($unitPrice * $item['quantity']);
                        ?>
                        <div class="d-flex justify-content-between mb-1" style="font-size:13px">
                            <span class="text-truncate me-2" style="max-width:180px">
                                <?= htmlspecialchars($item['name'] ?? '') ?>
                                <span class="text-muted">×<?= $item['quantity'] ?></span>
                            </span>
                            <span>Rp <?= number_format($totalPrice, 0, ',', '.') ?></span>
                        </div>
                        <?php endforeach; ?>

                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total Harga</strong>
                            <strong style="color: var(--primary); font-size:17px">
                                Rp <?= number_format($total, 0, ',', '.') ?>
                            </strong>
                        </div>

                        <a href="checkout.php" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">
                            <i class="fas fa-credit-card me-2"></i>Checkout Sekarang
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary w-100 mt-2 rounded-pill">
                            <i class="fas fa-utensils me-2"></i>Tambah Menu Lagi
                        </a>
                    </div>
                </div>

            </div>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>