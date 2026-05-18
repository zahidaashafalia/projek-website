<?php
include '../config/database.php';

$productId = $_GET['product_id'] ?? 0;

// Handle save toppings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_toppings'])) {
    $availableToppings = $_POST['toppings'] ?? [];
    
    // Update all toppings availability
    $conn->query("UPDATE product_toppings SET is_available = 0 WHERE product_id = $productId");
    
    foreach ($availableToppings as $toppingId => $price) {
        $price = floatval($price);
        $conn->query("
            INSERT INTO product_toppings (product_id, topping_id, price, is_available)
            VALUES ($productId, $toppingId, $price, 1)
            ON DUPLICATE KEY UPDATE price = $price, is_available = 1
        ");
    }
    
    header('Location: product-toppings.php?product_id=' . $productId . '&success=1');
    exit;
}

// Handle save spice levels
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_levels'])) {
    $availableLevels = $_POST['levels'] ?? [];
    
    // Update all levels availability
    $conn->query("UPDATE product_spice_levels SET is_available = 0 WHERE product_id = $productId");
    
    foreach ($availableLevels as $levelData) {
        $levelNumber = intval($levelData['number']);
        $levelName = $conn->real_escape_string($levelData['name']);
        $price = floatval($levelData['price']);
        
        $conn->query("
            INSERT INTO product_spice_levels (product_id, level_number, level_name, price, is_available)
            VALUES ($productId, $levelNumber, '$levelName', $price, 1)
            ON DUPLICATE KEY UPDATE level_name = '$levelName', price = $price, is_available = 1
        ");
    }
    
    header('Location: product-toppings.php?product_id=' . $productId . '&success=2');
    exit;
}

// Get all master toppings
$masterToppings = $conn->query("SELECT * FROM master_toppings WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

// Get current product toppings
$currentToppings = $conn->query("SELECT * FROM product_toppings WHERE product_id = $productId")->fetch_all(MYSQLI_ASSOC);
$currentToppingsMap = array_column($currentToppings, null, 'topping_id');

// Get current spice levels
$currentLevels = $conn->query("SELECT * FROM product_spice_levels WHERE product_id = $productId ORDER BY level_number")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kelola Topping & Level - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>Kelola Topping & Level Pedas</h2>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Data berhasil disimpan!</div>
    <?php endif; ?>
    
    <form method="POST">
        <!-- Topping Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>🍴 Topping Tersedia untuk Produk Ini</h5>
                <small>Centang topping yang bisa dipilih pelanggan saat memesan</small>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($masterToppings as $topping): 
                        $currentTopping = $currentToppingsMap[$topping['id']] ?? null;
                    ?>
                    <div class="col-md-3 mb-2">
                        <div class="form-check border p-2 rounded">
                            <input class="form-check-input" type="checkbox" 
                                   name="toppings[<?= $topping['id'] ?>]" 
                                   id="topping_<?= $topping['id'] ?>"
                                   value="<?= $currentTopping ? $currentTopping['price'] : $topping['price'] ?>"
                                   <?= $currentTopping ? 'checked' : '' ?>>
                            <label class="form-check-label" for="topping_<?= $topping['id'] ?>">
                                <strong><?= $topping['name'] ?></strong>
                                <div class="text-primary">+Rp <?= number_format($topping['price'], 0, ',', '.') ?></div>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Spice Level Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>🌶️ Level Pedas Tersedia untuk Produk Ini</h5>
                <small>Centang level yang bisa dipilih pelanggan. Kosongkan jika produk tidak ada pilihan level.</small>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 mb-2">
                        <div class="form-check border p-2 rounded">
                            <input class="form-check-input" type="checkbox" 
                                   name="levels[0][number]" value="0" checked disabled>
                            <input type="hidden" name="levels[0][name]" value="Tidak Pedas">
                            <input type="hidden" name="levels[0][price]" value="0">
                            <label class="form-check-label">
                                <strong>Tidak Pedas</strong>
                                <div class="text-muted">Gratis</div>
                            </label>
                        </div>
                    </div>
                    
                    <?php 
                    $levelOptions = [
                        1 => 'Level 1 (Sedikit Pedas)',
                        3 => 'Level 3 (Sedang)',
                        5 => 'Level 5 (Pedas)',
                        7 => 'Level 7 (Sangat Pedas)',
                        10 => 'Level 10 (Extra Pedas)'
                    ];
                    
                    foreach ($levelOptions as $num => $name): 
                        $currentLevel = array_filter($currentLevels, fn($l) => $l['level_number'] == $num);
                        $currentLevel = reset($currentLevel);
                    ?>
                    <div class="col-md-2 mb-2">
                        <div class="form-check border p-2 rounded">
                            <input class="form-check-input" type="checkbox" 
                                   name="levels[<?= $num ?>][number]" 
                                   value="<?= $num ?>"
                                   id="level_<?= $num ?>"
                                   <?= $currentLevel ? 'checked' : '' ?>>
                            <input type="hidden" name="levels[<?= $num ?>][name]" value="<?= $name ?>">
                            <input type="number" name="levels[<?= $num ?>][price]" 
                                   class="form-control form-control-sm mt-1" 
                                   value="<?= $currentLevel ? $currentLevel['price'] : 0 ?>"
                                   placeholder="Harga tambahan">
                            <label class="form-check-label" for="level_<?= $num ?>">
                                <strong><?= $name ?></strong>
                                <div class="text-muted small">Rp <span class="level-price"><?= number_format($currentLevel ? $currentLevel['price'] : 0, 0, ',', '.') ?></span></div>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <button type="submit" name="save_toppings" class="btn btn-primary">💾 Simpan Topping</button>
            <button type="submit" name="save_levels" class="btn btn-success">💾 Simpan Level</button>
            <a href="products.php" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<script>
// Update price display
document.querySelectorAll('input[name*="[price]"]').forEach(input => {
    input.addEventListener('input', function() {
        const priceSpan = this.closest('.form-check').querySelector('.level-price');
        if (priceSpan) {
            priceSpan.textContent = new Intl.NumberFormat('id-ID').format(this.value);
        }
    });
});
</script>
</body>
</html>