<?php
session_start();
include 'config.php';

// Cek login
if(!isset($_SESSION['user_phone'])){
    header("Location: auth/login.php");
    exit;
}

$phone = $_SESSION['user_phone'];
$orderId = $_GET['order_id'] ?? 0;
$productId = $_GET['product_id'] ?? 0;

// Cek apakah pesanan milik user dan sudah selesai
$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM orders 
    WHERE id = $orderId AND customer_phone = '$phone' AND status = 'Selesai'
"));

if(!$order){
    die("Pesanan tidak ditemukan atau belum selesai");
}

// Cek apakah sudah review
if(isProductReviewed($conn, $orderId, $productId)){
    die("Anda sudah mengulas produk ini");
}

// Ambil info produk
$product = null;
foreach($products as $p){
    if($p['id'] == $productId){
        $product = $p;
        break;
    }
}

if(!$product){
    die("Produk tidak ditemukan");
}

// Proses submit review
if(isset($_POST['submit_review'])){
    $rating = (int)$_POST['rating'];
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    
    // Handle upload gambar (optional)
    $images = [];
    if(isset($_FILES['images']) && $_FILES['images']['error'][0] == 0){
        $uploadDir = 'assets/images/reviews/';
        if(!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        
        foreach($_FILES['images']['tmp_name'] as $key => $tmpName){
            if($_FILES['images']['error'][$key] == 0){
                $fileName = time() . '_' . basename($_FILES['images']['name'][$key]);
                $targetPath = $uploadDir . $fileName;
                if(move_uploaded_file($tmpName, $targetPath)){
                    $images[] = $targetPath;
                }
            }
        }
    }
    
    $imagesJson = json_encode($images);
    
    // Insert review
    mysqli_query($conn, "
        INSERT INTO reviews (order_id, product_id, customer_phone, rating, comment, images)
        VALUES ($orderId, $productId, '$phone', $rating, '$comment', '$imagesJson')
    ");
    
    header("Location: riwayat.php?reviewed=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tulis Ulasan - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 600px; margin: 40px auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .product-info { display: flex; gap: 15px; margin-bottom: 30px; padding: 15px; background: #f9f9f9; border-radius: 12px; }
        .product-thumb { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; }
        .star-rating { display: flex; gap: 10px; font-size: 2rem; color: #ddd; cursor: pointer; }
        .star-rating i { transition: color 0.2s; }
        .star-rating i.active { color: #FFB800; }
        textarea { resize: none; border-radius: 8px; }
        .btn-submit { background: #8B6F4E; color: white; border: none; padding: 12px 40px; border-radius: 25px; font-weight: 600; }
        .btn-submit:hover { background: #6B5637; }
    </style>
</head>
<body>

<div class="container">
    <h3 class="mb-4">Tulis Ulasan</h3>
    
    <div class="product-info">
        <?php if(!empty($product['image'])): ?>
        <img src="<?= $product['image'] ?>" alt="<?= $product['name'] ?>" class="product-thumb">
        <?php endif; ?>
        <div>
            <h5><?= $product['name'] ?></h5>
            <p class="mb-0 text-muted">Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
        </div>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-4">
            <label class="form-label fw-bold">Rating</label>
            <div class="star-rating" id="starRating">
                <i class="fas fa-star" data-value="1"></i>
                <i class="fas fa-star" data-value="2"></i>
                <i class="fas fa-star" data-value="3"></i>
                <i class="fas fa-star" data-value="4"></i>
                <i class="fas fa-star" data-value="5"></i>
            </div>
            <input type="hidden" name="rating" id="ratingInput" required>
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Ulasan Anda</label>
            <textarea name="comment" class="form-control" rows="4" placeholder="Ceritakan pengalaman Anda..." required></textarea>
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Foto (opsional)</label>
            <input type="file" name="images[]" class="form-control" multiple accept="image/*">
            <small class="text-muted">Maksimal 4 foto</small>
        </div>
        
        <div class="d-flex gap-2">
            <a href="riwayat.php" class="btn btn-secondary">Batal</a>
            <button type="submit" name="submit_review" class="btn btn-submit">Kirim Ulasan</button>
        </div>
    </form>
</div>

<script>
// Star rating interaction
const stars = document.querySelectorAll('.star-rating i');
const ratingInput = document.getElementById('ratingInput');

stars.forEach(star => {
    star.addEventListener('click', function(){
        const value = parseInt(this.dataset.value);
        ratingInput.value = value;
        
        stars.forEach(s => {
            if(parseInt(s.dataset.value) <= value){
                s.classList.add('active');
            } else {
                s.classList.remove('active');
            }
        });
    });
    
    star.addEventListener('mouseover', function(){
        const value = parseInt(this.dataset.value);
        stars.forEach(s => {
            if(parseInt(s.dataset.value) <= value){
                s.style.color = '#FFB800';
            } else {
                s.style.color = '#ddd';
            }
        });
    });
});

document.querySelector('.star-rating').addEventListener('mouseleave', function(){
    const currentValue = parseInt(ratingInput.value) || 0;
    stars.forEach(s => {
        if(parseInt(s.dataset.value) <= currentValue){
            s.style.color = '#FFB800';
        } else {
            s.style.color = '#ddd';
        }
    });
});
</script>

</body>
</html>