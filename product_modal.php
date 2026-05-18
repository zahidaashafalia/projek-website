<!-- Modal Product Detail -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" class="img-fluid rounded mb-3" style="max-height: 200px;">
                <h4 id="modalTitle" class="mb-2"></h4>
                <p id="modalDesc" class="text-muted small"></p>
                <h5 class="text-primary mb-3">Rp <span id="modalPrice"></span></h5>
                
                <!-- Pilihan Topping -->
                <div class="mb-3">
                    <h6 class="fw-bold mb-2">🍴 Topping Tersedia</h6>
                    <div class="row g-2" id="toppingOptions">
                        <!-- Topping akan di-generate oleh JavaScript -->
                    </div>
                </div>
                
                <!-- Pilihan Level Pedas -->
                <div class="mb-3">
                    <h6 class="fw-bold mb-2">🌶️ Level Pedas</h6>
                    <div class="row g-2" id="spiceLevelOptions">
                        <!-- Level akan di-generate oleh JavaScript -->
                    </div>
                </div>
                
                <!-- Jumlah -->
                <div class="mb-3">
                    <h6 class="fw-bold mb-2">Jumlah</h6>
                    <div class="input-group" style="max-width: 150px;">
                        <button class="btn btn-outline-secondary" type="button" onclick="decrementQty()">-</button>
                        <input type="text" id="quantity" class="form-control text-center" value="1" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="incrementQty()">+</button>
                    </div>
                </div>
                
                <!-- Total Harga -->
                <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                    <span class="fw-bold">Total Harga:</span>
                    <span class="fw-bold text-primary">Rp <span id="totalPrice">0</span></span>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary flex-fill" onclick="addToCart()">
                    🛒 Tambah ke Keranjang
                </button>
                <button type="button" class="btn btn-primary flex-fill" onclick="buyNow()">
                    Beli Sekarang
                </button>
            </div>
        </div>
    </div>
</div>