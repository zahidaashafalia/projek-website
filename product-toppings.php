
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Topping & Level - Texcer Hot Admin</title>
    <!-- Menggunakan Font Plus Jakarta Sans seperti dashboard -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #8B6F4E;
            --primary-dark: #6B5637;
            --secondary: #D4A574;
            --accent: #E8B4A2;
            --bg-cream: #FDF8F3;
            --bg-white: #FFFFFF;
            --text-dark: #3D2914;
            --text-gray: #8B7355;
            --border: #E8DDD4;
            --success: #28a745;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-cream);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 16px;
        }

        /* Header Style */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .page-title h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .page-title p {
            font-size: 0.9rem;
            color: var(--text-gray);
        }
        .btn-back {
            background: white;
            border: 1px solid var(--border);
            color: var(--text-dark);
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: var(--bg-cream);
            border-color: var(--primary);
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .alert-success {
            background-color: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #c8e6c9;
        }

        /* Card Style */
        .card {
            background: var(--bg-white);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 24px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background-color: #fff;
        }
        .card-header h5 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .card-header small {
            color: var(--text-gray);
            font-size: 0.85rem;
        }
        .card-body {
            padding: 20px;
        }

        /* Grid Layout for Items */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }

        /* Custom Checkbox Item */
        .item-check {
            position: relative;
            cursor: pointer;
        }
        .item-check input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        .checkmark {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .item-info {
            display: flex;
            flex-direction: column;
        }
        .item-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-dark);
        }
        .item-price {
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 700;
            margin-top: 2px;
        }
        .check-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 10px;
            transition: all 0.2s;
        }

        /* Checked State */
        .item-check input:checked ~ .checkmark {
            border-color: var(--primary);
            background-color: #FFF8F0; /* Light cream highlight */
        }
        .item-check input:checked ~ .checkmark .check-icon {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        /* Input Harga Tambahan di dalam Card */
        .price-input-group {
            margin-top: 8px;
        }
        .price-input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.85rem;
            color: var(--text-dark);
            background: #fff;
        }
        .price-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        .btn-secondary {
            background-color: white;
            color: var(--text-dark);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover {
            background-color: var(--bg-cream);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .items-grid {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <div class="page-title">
            <h2><i class="fas fa-pepper-hot" style="color: var(--primary); margin-right: 8px;"></i> Kelola Topping & Level</h2>
            <p>Sesuaikan pilihan tambahan untuk produk ini</p>
        </div>
        <a href="products.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Produk</a>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Data berhasil disimpan!
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <!-- Topping Section -->
        <div class="card">
            <div class="card-header">
                <h5>🍴 Topping Tersedia</h5>
                <small>Centang topping yang ingin ditampilkan saat pelanggan memesan produk ini.</small>
            </div>
            <div class="card-body">
                <div class="items-grid">
                    <?php foreach ($masterToppings as $topping): 
                        $currentTopping = $currentToppingsMap[$topping['id']] ?? null;
                        $isChecked = $currentTopping ? 'checked' : '';
                        $currentPrice = $currentTopping ? $currentTopping['price'] : $topping['price'];
                    ?>
                    <label class="item-check">
                        <input type="checkbox" 
                               name="toppings[<?= $topping['id'] ?>]" 
                               value="<?= $currentPrice ?>"
                               <?= $isChecked ?>>
                        <div class="checkmark">
                            <div class="item-info">
                                <span class="item-name"><?= $topping['name'] ?></span>
                                <span class="item-price">+Rp <?= number_format($topping['price'], 0, ',', '.') ?></span>
                            </div>
                            <div class="check-icon"><i class="fas fa-check"></i></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                    
                    <?php if(empty($masterToppings)): ?>
                        <p style="color: var(--text-gray); font-style: italic;">Belum ada data topping di Master Topping.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Spice Level Section -->
        <div class="card">
            <div class="card-header">
                <h5>🌶️ Level Pedas</h5>
                <small>Pilih level pedas yang tersedia. Anda bisa mengatur harga tambahan per level.</small>
            </div>
            <div class="card-body">
                <div class="items-grid">
                    <!-- Default: Tidak Pedas (Selalu Ada) -->
                    <div class="item-check" style="cursor: default;">
                        <div class="checkmark" style="background: #f9f9f9; border-color: #eee;">
                            <div class="item-info">
                                <span class="item-name">Tidak Pedas</span>
                                <span class="item-price" style="color: #888;">Gratis</span>
                            </div>
                            <i class="fas fa-lock" style="color: #ccc;"></i>
                        </div>
                    </div>
                    
                    <?php 
                    // Definisi Level Sesuai Permintaan Sebelumnya (1-5, tapi kita sesuaikan dengan DB lama jika ada)
                    // Jika Anda ingin strict 1-5, ubah array di bawah ini.
                    // Saat ini saya gunakan array dari kode lama Anda agar tidak error jika data lama masih ada.
                    $levelOptions = [
                        1 => 'Level 1 (Sedikit)',
                        2 => 'Level 2 (Sedang)',
                        3 => 'Level 3 (Pedas)',
                        4 => 'Level 4 (Sangat Pedas)',
                        5 => 'Level 5 (Extra Pedas)'
                    ];
                    
                    foreach ($levelOptions as $num => $name): 
                        $currentLevel = array_filter($currentLevels, fn($l) => $l['level_number'] == $num);
                        $currentLevel = reset($currentLevel);
                        $isChecked = $currentLevel ? 'checked' : '';
                        $currentPrice = $currentLevel ? $currentLevel['price'] : 0;
                    ?>
                    <label class="item-check">
                        <input type="checkbox" 
                               name="levels[<?= $num ?>][number]" 
                               value="<?= $num ?>"
                               id="level_<?= $num ?>"
                               <?= $isChecked ?>>
                        <div class="checkmark">
                            <div class="item-info" style="flex: 1;">
                                <span class="item-name"><?= $name ?></span>
                                <div class="price-input-group">
                                    <input type="number" 
                                           name="levels[<?= $num ?>][price]" 
                                           class="price-input" 
                                           value="<?= $currentPrice ?>"
                                           placeholder="Harga tambah">
                                </div>
                            </div>
                            <div class="check-icon"><i class="fas fa-check"></i></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <button type="submit" name="save_toppings" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Topping
            </button>
            <button type="submit" name="save_levels" class="btn btn-primary" style="background-color: var(--secondary);">
                <i class="fas fa-save"></i> Simpan Level
            </button>
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