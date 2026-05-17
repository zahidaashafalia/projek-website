<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - Texcer Hot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #FDF8F3; color: #4A3728; }
        .navbar { background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 1rem 5%; }
        .nav-container { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .brand { font-size: 1.6rem; font-weight: 700; color: #8B6F47; text-decoration: none; }
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; min-height: 60vh; }
        h1 { color: #8B6F47; margin-bottom: 30px; }
        footer { background: #422816; color: #E8D5C4; padding: 2rem 5%; margin-top: 4rem; text-align: center; }
        footer strong { color: #D4A574; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="brand">Texcer Hot</a>
        </div>
    </nav>

    <div class="container">
        <h1><i class="fas fa-shopping-cart"></i> Keranjang Belanja</h1>
        <div class="cart-items"></div>
    </div>

    <footer>
        <p>&copy; 2020 - 2026 <strong>Texcer Hot</strong>. Berdiri sejak 2020.</p>
        <p>Didirikan oleh <strong>Ahmad Amrullah Baharuddin</strong>. All rights reserved.</p>
    </footer>

    <script>
        // Include cart functionality dari index.php atau copy di sini
        let cart = JSON.parse(localStorage.getItem('texcer_cart')) || [];
        
        function updateCartCount() { /* same as above */ }
        function removeFromCart(productId) { /* same as above */ }
        function updateCartItemQuantity(productId, quantity) { /* same as above */ }
        function renderCart() { /* same as above */ }
        function checkout() { window.location.href = 'pesanan.php'; }
        
        document.addEventListener('DOMContentLoaded', function() {
            renderCart();
            updateCartCount();
        });
    </script>
</body>
</html>