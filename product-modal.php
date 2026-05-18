<?php
// ... kode sebelumnya ...

// ✅ Ambil topping yang tersedia untuk produk ini (SESUAI STRUKTUR DB KAMU)
$toppings = [];
$toppingQuery = mysqli_query($conn, "
    SELECT pt.*, t.name, t.price, t.is_available
    FROM product_toppings pt
    JOIN toppings t ON pt.topping_id = t.id
    WHERE pt.product_id = $product_id AND pt.is_available = 1 AND t.is_available = 1
    ORDER BY t.display_order ASC
");
while($row = mysqli_fetch_assoc($toppingQuery)){
    $toppings[] = $row;
}

// ✅ Ambil level pedas yang tersedia untuk produk ini (SESUAI STRUKTUR DB KAMU)
$spiceLevels = [];
$levelQuery = mysqli_query($conn, "
    SELECT pl.*, l.name as level_name, l.level_number, l.price, l.is_available
    FROM product_levels pl
    JOIN levels l ON pl.level_id = l.id
    WHERE pl.product_id = $product_id AND pl.is_available = 1 AND l.is_available = 1
    ORDER BY l.level_number ASC
");
while($row = mysqli_fetch_assoc($levelQuery)){
    $spiceLevels[] = $row;
}

// ... kode selanjutnya ...
?>

<div class="modal fade" id="productModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <img src="uploads/<?= htmlspecialchars($product['image']) ?>" 
                             class="img-fluid rounded-3" alt="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <span class="badge bg-warning text-dark mb-2"><?= htmlspecialchars($product['category_name']) ?></span>
                        <h3 class="mb-2"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="text-muted mb-3"><?= htmlspecialchars($product['description']) ?></p>
                        
                        <div class="mb-3">
                            <span class="text-primary fs-4 fw-bold">Rp <?= number_format($product['price'], 0, ',', '.') ?></span>
                        </div>
                        
                        <?php if(!empty($variants)): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Varian:</label>
                            <select class="form-select" id="variantSelect">
                                <option value="">Pilih Varian</option>
                                <?php foreach($variants as $variant): ?>
                                    <option value="<?= trim($variant) ?>"><?= trim($variant) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- 🍴 Pilihan Topping -->
<?php if(!empty($toppings)): ?>
<div class="mb-3">
    <label class="form-label fw-bold">🍴 Topping Tambahan:</label>
    <div class="row g-2" id="toppingOptions">
        <?php foreach($toppings as $topping): ?>
        <div class="col-6 col-md-4">
            <div class="form-check border rounded p-2">
                <input class="form-check-input topping-checkbox" type="checkbox" 
                       id="topping_<?= $topping['id'] ?>" 
                       value="<?= $topping['id'] ?>"
                       data-name="<?= htmlspecialchars($topping['topping_name']) ?>"
                       data-price="<?= $topping['price'] ?>">
                <label class="form-check-label w-100" for="topping_<?= $topping['id'] ?>">
                    <div class="small fw-bold"><?= htmlspecialchars($topping['topping_name']) ?></div>
                    <div class="text-primary small">+Rp <?= number_format($topping['price'], 0, ',', '.') ?></div>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 🌶️ Pilihan Level Pedas -->
<?php if(!empty($spiceLevels)): ?>
<div class="mb-3">
    <label class="form-label fw-bold">🌶️ Level Pedas:</label>
    <div class="row g-2" id="spiceLevelOptions">
        <?php foreach($spiceLevels as $level): ?>
        <div class="col-6 col-md-4">
            <div class="form-check border rounded p-2">
                <input class="form-check-input spice-radio" type="radio" 
                       name="spice_level" 
                       id="spice_<?= $level['id'] ?>" 
                       value="<?= $level['id'] ?>"
                       data-name="<?= htmlspecialchars($level['level_name']) ?>"
                       data-price="<?= $level['price'] ?>"
                       <?= $level['level_number'] == 0 ? 'checked' : '' ?>>
                <label class="form-check-label w-100" for="spice_<?= $level['id'] ?>">
                    <div class="small fw-bold"><?= htmlspecialchars($level['level_name']) ?></div>
                    <div class="<?= $level['price'] > 0 ? 'text-primary' : 'text-muted' ?> small">
                        <?= $level['price'] > 0 ? '+Rp '.number_format($level['price'], 0, ',', '.') : 'Gratis' ?>
                    </div>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 💰 Total Harga Dinamis -->
<?php if(!empty($toppings) || !empty($spiceLevels)): ?>
<div class="mb-3 p-2 bg-light rounded">
    <div class="d-flex justify-content-between">
        <span class="fw-bold">Total:</span>
        <span class="fw-bold text-primary" id="dynamicTotal">Rp <?= number_format($product['price'], 0, ',', '.') ?></span>
    </div>
    <small class="text-muted d-block" id="selectedOptions"></small>
</div>
<?php endif; ?>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Jumlah:</label>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-outline-secondary" onclick="updateQty(-1)">-</button>
                                <input type="number" id="quantity" value="1" min="1" class="form-control text-center" style="width: 70px;">
                                <button class="btn btn-outline-secondary" onclick="updateQty(1)">+</button>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <!-- Beli Sekarang -->
                            <button class="btn btn-primary btn-lg" onclick="buyNow()">
                                <i class="fas fa-shopping-bag me-2"></i>Beli Sekarang
                            </button>
                            
                            <!-- Tambah ke Keranjang -->
                            <button class="btn btn-outline-primary" onclick="addToCart()">
                                <i class="fas fa-cart-plus me-2"></i>Tambah ke Keranjang
                            </button>
                            
                            <!-- Chat WhatsApp -->
                            <a href="https://wa.me/6281234567890?text=<?= urlencode('Halo Texcer Hot, saya ingin bertanya tentang produk ' . $product['name']) ?>" 
                               target="_blank" class="btn btn-success">
                                <i class="fab fa-whatsapp me-2"></i>Chat WhatsApp
                            </a>
                            
                            <!-- Info Toko -->
                            <button class="btn btn-outline-secondary" onclick="showStoreInfo()">
                                <i class="fas fa-store me-2"></i>Info Toko
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Info Toko -->
<div class="modal fade" id="storeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Tentang Texcer Hot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="uploads/logo.png" alt="Texcer Hot" class="img-fluid mb-3" style="max-width: 150px;">
                <h4>Texcer Hot</h4>
                <p class="text-muted">Spesialis Mie Pedas & Dimsum Lezat</p>
                <div class="text-start">
                    <p><i class="fas fa-star text-warning me-2"></i>Rating: 4.5/5</p>
                    <p><i class="fas fa-clock me-2"></i>Buka: 10.00 - 22.00 WIB</p>
                    <p><i class="fas fa-map-marker-alt me-2"></i>Lokasi: Jl. Contoh No. 123</p>
                </div>
                <a href="about.php" class="btn btn-primary w-100">
                    <i class="fas fa-info-circle me-2"></i>Lihat Selengkapnya
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateQty(change){
    let qty = document.getElementById('quantity');
    let newVal = parseInt(qty.value) + change;
    if(newVal >= 1) qty.value = newVal;
}

function addToCart(){
    let productId = <?= $product_id ?>;
    let quantity = document.getElementById('quantity').value;
    let variant = document.getElementById('variantSelect') ? document.getElementById('variantSelect').value : '';
    
    // Buat form dan submit
    let form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cart.php';
    form.innerHTML = `
        <input type="hidden" name="add_to_cart" value="1">
        <input type="hidden" name="product_id" value="${productId}">
        <input type="hidden" name="quantity" value="${quantity}">
        <input type="hidden" name="variant" value="${variant}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function buyNow(){
    addToCart();
    // Redirect ke checkout setelah add to cart
    setTimeout(() => {
        window.location.href = 'checkout.php';
    }, 500);
}

function showStoreInfo(){
    let modal = new bootstrap.Modal(document.getElementById('storeModal'));
    modal.show();
}

function closeModal(){
    window.history.back();
}

// Format Rupiah helper
function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID').format(angka);
}

// Update quantity
function updateQty(change){
    let qty = document.getElementById('quantity');
    let newVal = parseInt(qty.value) + change;
    if(newVal >= 1) {
        qty.value = newVal;
        calculateTotal(); // ✅ Update total saat qty berubah
    }
}

// ✅ Hitung total harga dinamis
function calculateTotal() {
    let basePrice = <?= $product['price'] ?>;
    let total = basePrice;
    let selectedText = [];
    
    // Tambah harga topping yang dipilih
    document.querySelectorAll('.topping-checkbox:checked').forEach(cb => {
        let price = parseFloat(cb.dataset.price);
        let name = cb.dataset.name;
        total += price;
        selectedText.push(name);
    });
    
    // Tambah harga level pedas yang dipilih
    let selectedLevel = document.querySelector('.spice-radio:checked');
    if(selectedLevel) {
        let price = parseFloat(selectedLevel.dataset.price);
        let name = selectedLevel.dataset.name;
        total += price;
        if(name !== 'Tidak Pedas') selectedText.push(name);
    }
    
    // Kalikan dengan quantity
    let qty = parseInt(document.getElementById('quantity').value) || 1;
    total *= qty;
    
    // Update tampilan
    document.getElementById('dynamicTotal').textContent = 'Rp ' + formatRupiah(total);
    document.getElementById('selectedOptions').textContent = 
        selectedText.length > 0 ? 'Pilihan: ' + selectedText.join(', ') : '';
    
    return total;
}

// ✅ Tambahkan event listener ke semua input topping & level
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.topping-checkbox, .spice-radio').forEach(input => {
        input.addEventListener('change', calculateTotal);
    });
});

// ✅ Update addToCart agar kirim data topping & level
function addToCart(){
    let productId = <?= $product_id ?>;
    let quantity = document.getElementById('quantity').value;
    let variant = document.getElementById('variantSelect') ? document.getElementById('variantSelect').value : '';
    
    // ✅ Kumpulkan topping yang dipilih
    let selectedToppings = [];
    document.querySelectorAll('.topping-checkbox:checked').forEach(cb => {
        selectedToppings.push({
            id: cb.value,
            name: cb.dataset.name,
            price: cb.dataset.price
        });
    });
    
    // ✅ Kumpulkan level pedas yang dipilih
    let selectedSpice = null;
    let checkedSpice = document.querySelector('.spice-radio:checked');
    if(checkedSpice) {
        selectedSpice = {
            id: checkedSpice.value,
            name: checkedSpice.dataset.name,
            price: checkedSpice.dataset.price
        };
    }
    
    // Buat form dan submit
    let form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cart.php';
    form.innerHTML = `
        <input type="hidden" name="add_to_cart" value="1">
        <input type="hidden" name="product_id" value="${productId}">
        <input type="hidden" name="quantity" value="${quantity}">
        <input type="hidden" name="variant" value="${variant}">
        <input type="hidden" name="toppings" value='${JSON.stringify(selectedToppings)}'>
        <input type="hidden" name="spice_level" value='${JSON.stringify(selectedSpice)}'>
        <input type="hidden" name="total_price" value="${calculateTotal()}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function buyNow(){
    addToCart();
    setTimeout(() => {
        window.location.href = 'checkout.php';
    }, 500);
}

function showStoreInfo(){
    let modal = new bootstrap.Modal(document.getElementById('storeModal'));
    modal.show();
}

function closeModal(){
    // ✅ Ganti history.back() dengan hide modal yang lebih smooth
    let modalEl = document.getElementById('productModal');
    let modal = bootstrap.Modal.getInstance(modalEl);
    if(modal) modal.hide();
    else window.history.back();
}

</script>