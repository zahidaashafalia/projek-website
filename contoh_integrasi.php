<?php
// CONTOH INTEGRASI di halaman produk kamu
// Tambahkan onclick="openToppingModal(<?= $product['id'] ?>)"
// ke tombol keranjang dan beli sekarang yang sudah ada
?>

<!-- 
==============================================
CARA INTEGRASI KE HALAMAN PRODUK KAMU
==============================================

1. Include modal_topping.php sebelum </body>:
   <?php include 'modal_topping.php'; ?>

2. Ubah tombol keranjang / beli sekarang kamu:
   Tambahkan: onclick="openToppingModal(ID_PRODUK)"

CONTOH:
-->

<!-- Tombol di kartu produk (loop) -->
<?php
// Ini contoh loop produk kamu — sesuaikan dengan kode yang sudah ada
$products = []; // ambil dari DB seperti biasa
foreach ($products as $product):
?>
  <div class="product-card">
    <img src="uploads/<?= $product['image'] ?>" alt="">
    <p><?= htmlspecialchars($product['name']) ?></p>
    <p>Rp <?= number_format($product['price'], 0, ',', '.') ?></p>

    <!-- TOMBOL KERANJANG — tambahkan onclick ini -->
    <button onclick="openToppingModal(<?= $product['id'] ?>)">
      🛒 Keranjang
    </button>

    <!-- TOMBOL BELI SEKARANG — tambahkan onclick ini -->
    <button onclick="openToppingModal(<?= $product['id'] ?>)">
      ⚡ Beli Sekarang
    </button>
  </div>
<?php endforeach; ?>


<!-- Di halaman detail produk (single product) -->
<?php
// $product sudah di-fetch dari DB
?>
<button onclick="openToppingModal(<?= $product['id'] ?>)">
  🛒 Tambah ke Keranjang
</button>

<button onclick="openToppingModal(<?= $product['id'] ?>)">
  ⚡ Beli Sekarang
</button>

<!-- Include modal SEBELUM </body> -->
<?php include 'modal_topping.php'; ?>