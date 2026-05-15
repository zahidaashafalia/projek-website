<?php
session_start();
$order_code = $_GET['order_code'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #8B6F4E; --success: #28a745; }
        body { background: #FDF8F3; font-family: 'Segoe UI', sans-serif; }
        .success-card { background: white; border-radius: 16px; padding: 40px; text-align: center; max-width: 500px; margin: 50px auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .success-icon { width: 100px; height: 100px; background: var(--success); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 50px; }
    </style>
</head>
<body>
<div class="container">
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h2 class="mb-3">Pesanan Berhasil!</h2>
        <p class="text-muted mb-3">Terima kasih telah memesan di Texcer Hot</p>
        <div class="alert alert-light">
            <strong>Kode Pesanan:</strong><br>
            <span class="fs-4 text-primary"><?= htmlspecialchars($order_code) ?></span>
        </div>
        <p class="text-muted">Pesanan Anda sedang diproses. Kami akan menghubungi Anda segera.</p>
        <div class="d-grid gap-2 mt-4">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Kembali ke Beranda
            </a>
            <a href="https://wa.me/6281234567890?text=<?= urlencode('Halo, saya ingin konfirmasi pesanan ' . $order_code) ?>" 
               target="_blank" class="btn btn-success">
                <i class="fab fa-whatsapp me-2"></i>Chat via WhatsApp
            </a>
        </div>
    </div>
</div>
</body>
</html>