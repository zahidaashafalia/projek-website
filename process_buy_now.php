<!-- Modal Beli Sekarang -->
<div id="buyModal" class="buy-modal-overlay">
  <div class="buy-modal">
    <button class="buy-modal-close" onclick="closeBuyModal()">&times;</button>

    <!-- Header produk -->
    <div class="buy-modal-header">
      <img id="bmProductImg" src="" alt="">
      <div>
        <h3 id="bmProductName">Loading...</h3>
        <div class="bm-price" id="bmBasePrice">Rp 0</div>
      </div>
    </div>

    <div class="buy-modal-body">
      <!-- Level pedas -->
      <div class="bm-section" id="bmLevelSection">
        <h4>🌶️ Pilih Level Pedas</h4>
        <div class="bm-options" id="bmLevels"></div>
      </div>

      <!-- Topping -->
      <div class="bm-section" id="bmToppingSection">
        <h4>🍤 Pilih Topping (boleh lebih dari 1)</h4>
        <div class="bm-options" id="bmToppings"></div>
      </div>

      <!-- Catatan -->
      <div class="bm-section">
        <h4>📝 Catatan (opsional)</h4>
        <textarea id="bmNote" placeholder="Contoh: tanpa bawang, pisah kuah..."></textarea>
      </div>

      <!-- Jumlah -->
      <div class="bm-section bm-qty-section">
        <h4>Jumlah</h4>
        <div class="bm-qty">
          <button onclick="changeQty(-1)">−</button>
          <span id="bmQty">1</span>
          <button onclick="changeQty(1)">+</button>
        </div>
      </div>
    </div>

    <!-- Footer total + tombol -->
    <div class="buy-modal-footer">
      <div>
        <small>Total Harga</small>
        <div class="bm-total" id="bmTotal">Rp 0</div>
      </div>
      <button class="bm-checkout-btn" onclick="checkoutNow()">Beli Sekarang</button>
    </div>
  </div>
</div>
