<?php 
include 'config.php'; 

$id = $_GET['id'] ?? 0;
$product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id = $id"));

// Ambil ulasan untuk produk ini (dari tabel reviews yang terkait orders)
$reviews_query = "SELECT r.*, o.customer_name, o.order_date 
                  FROM reviews r 
                  JOIN orders o ON r.order_id = o.id 
                  WHERE o.id IN (
                      SELECT oi.order_id FROM order_items oi WHERE oi.product_name = '{$product['name']}'
                  ) ORDER BY r.created_at DESC";
$reviews = mysqli_query($conn, $reviews_query);

// Hitung rata-rata rating
$avg_rating = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(rating) as avg FROM reviews WHERE order_id IN (SELECT id FROM orders)"));
$avg_rating = $avg_rating['avg'] ?? 0;

// Logika tambah ke cart
if(isset($_POST['add_to_cart'])){
    $cart_item = ['id' => $product['id'], 'name' => $product['name'], 'price' => $product['price'], 'qty' => 1];
    $found = false;
    if(isset($_SESSION['cart'])){
        foreach($_SESSION['cart'] as $key => $item){
            if($item['id'] == $product['id']){
                $_SESSION['cart'][$key]['qty']++;
                $found = true;
                break;
            }
        }
    }
    if(!$found){
        $_SESSION['cart'][] = $cart_item;
    }
    echo "<script>alert('Produk ditambahkan ke keranjang!'); window.location='produk.php?id=$id';</script>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product['name'] ?> - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f5; color: #333; font-family: 'Segoe UI', sans-serif; padding-bottom: 80px; }
        
        .product-header { background: white; padding: 15px; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 100; }
        .product-header h2 { font-size: 1.1rem; margin: 0; }
        
        .product-image { width: 100%; height: 400px; object-fit: cover; background: #f0f0f0; }
        
        .product-info { background: white; padding: 20px; margin: 10px; border-radius: 8px; }
        .product-name { font-size: 1.3rem; font-weight: 700; margin-bottom: 10px; }
        .product-price { color: #ff6600; font-size: 1.5rem; font-weight: 700; margin-bottom: 15px; }
        .product-desc { color: #666; line-height: 1.6; margin-bottom: 20px; }
        
        .btn-buy { background: #ff6600; color: white; border: none; padding: 15px; border-radius: 8px; font-weight: 700; width: 100%; font-size: 1rem; }
        
        .tabs { background: white; padding: 0 15px; border-bottom: 1px solid #eee; display: flex; gap: 20px; overflow-x: auto; }
        .tab { padding: 15px 0; color: #888; font-weight: 600; cursor: pointer; border-bottom: 3px solid transparent; white-space: nowrap; }
        .tab.active { color: #ff6600; border-bottom-color: #ff6600; }
        
        .review-section { background: white; margin: 10px; padding: 20px; border-radius: 8px; }
        .review-item { padding: 20px 0; border-bottom: 1px solid #eee; }
        .review-item:last-child { border-bottom: none; }
        .reviewer-name { font-weight: 600; margin-bottom: 5px; display: flex; align-items: center; gap: 10px; }
        .stars { color: #ffc107; margin-bottom: 10px; }
        .review-text { color: #333; line-height: 1.5; margin-bottom: 10px; }
        .review-date { color: #888; font-size: 0.85rem; }
        .review-image { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; margin-top: 10px; }
        
        .rating-summary { background: white; margin: 10px; padding: 20px; border-radius: 8px; display: flex; align-items: center; gap: 20px; }
        .avg-rating { font-size: 3rem; font-weight: 700; color: #ff6600; }
        .rating-bars { flex: 1; }
        .rating-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
        .rating-bar .bar { flex: 1; height: 8px; background: #eee; border-radius: 4px; overflow: hidden; }
        .rating-bar .fill { height: 100%; background: #ffc107; }
        
        .bottom-nav { position: fixed; bottom: 0; width: 100%; background: white; border-top: 1px solid #eee; padding: 10px; display: flex; gap: 10px; }
    </style>
</head>
<body>

<div class="product-header">
    <a href="index.php" style="color: #333;"><i class="fas fa-arrow-left"></i></a>
    <h2><?= $product['name'] ?></h2>
</div>

<img src="<?= $product['image'] ?>" class="product-image" alt="<?= $product['name'] ?>">

<div class="product-info">
    <div class="product-name"><?= $product['name'] ?></div>
    <div class="product-price">Rp <?= number_format($product['price'], 0, ',', '.') ?></div>
    <div class="product-desc"><?= $product['description'] ?></div>
    
    <form method="POST">
        <button type="submit" name="add_to_cart" class="btn-buy">
            <i class="fas fa-shopping-cart"></i> Tambah ke Keranjang
        </button>
    </form>
</div>

<div class="tabs">
    <div class="tab active" onclick="showTab('ulasan')">Ulasan</div>
    <div class="tab" onclick="showTab('deskripsi')">Deskripsi</div>
    <div class="tab" onclick="showTab('rekomendasi')">Rekomendasi</div>
</div>

<div id="ulasan" class="tab-content">
    <div class="rating-summary">
        <div>
            <div class="avg-rating"><?= number_format($avg_rating, 1) ?></div>
            <div class="stars">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="<?= $i <= round($avg_rating) ? 'fas' : 'far' ?> fa-star"></i>
                <?php endfor; ?>
            </div>
            <div style="color: #888; font-size: 0.85rem;"><?= mysqli_num_rows($reviews) ?> ulasan</div>
        </div>
        <div class="rating-bars">
            <?php for($i=5; $i>=1; $i--): 
                $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reviews WHERE rating = $i"))['c'];
                $total = mysqli_num_rows($reviews) ?: 1;
                $percent = ($count / $total) * 100;
            ?>
            <div class="rating-bar">
                <span><?= $i ?>★</span>
                <div class="bar"><div class="fill" style="width: <?= $percent ?>%"></div></div>
                <span style="color: #888; font-size: 0.85rem;"><?= $count ?></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="review-section">
        <h4 style="margin-bottom: 20px;">Ulasan Produk</h4>
        <?php if(mysqli_num_rows($reviews) > 0): ?>
            <?php while($r = mysqli_fetch_assoc($reviews)): ?>
            <div class="review-item">
                <div class="reviewer-name">
                    <div style="width: 40px; height: 40px; background: #ff6600; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                        <?= strtoupper(substr($r['customer_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div><?= htmlspecialchars($r['customer_name']) ?></div>
                        <div class="stars">
                            <?php for($s=1; $s<=5; $s++): ?>
                                <i class="<?= $s <= $r['rating'] ? 'fas' : 'far' ?> fa-star" style="font-size: 0.9rem;"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <div class="review-text"><?= nl2br(htmlspecialchars($r['comment'])) ?></div>
                <div class="review-date"><?= date('d M Y', strtotime($r['created_at'])) ?></div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center; color: #888; padding: 40px;">Belum ada ulasan untuk produk ini</p>
        <?php endif; ?>
    </div>
</div>

<div id="deskripsi" class="tab-content" style="display: none;">
    <div class="review-section">
        <h4>Deskripsi Produk</h4>
        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
    </div>
</div>

<div id="rekomendasi" class="tab-content" style="display: none;">
    <div class="review-section">
        <h4>Produk Rekomendasi</h4>
        <p style="color: #888; text-align: center; padding: 40px;">Fitur coming soon</p>
    </div>
</div>

<div class="bottom-nav">
    <a href="index.php" style="padding: 10px; color: #666;"><i class="fas fa-home"></i></a>
    <a href="checkout.php" style="padding: 10px; color: #666;"><i class="fas fa-shopping-cart"></i></a>
    <button class="btn-buy" style="flex: 1;" onclick="window.location='checkout.php'">
        Beli Sekarang
    </button>
</div>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById(tabName).style.display = 'block';
    event.target.classList.add('active');
}
</script>

</body>
</html>