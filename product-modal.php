<?php
session_start();
include 'config.php';

// Inisialisasi cart
if(!isset($_SESSION['cart'])){
    $_SESSION['cart'] = [];
}

$product_id = (int)$_GET['id'];
$query = mysqli_query($conn, "SELECT p.*, c.name as category_name 
                              FROM products p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              WHERE p.id = $product_id");
$product = mysqli_fetch_assoc($query);

if(!$product){
    die("Produk tidak ditemukan");
}

// Ambil varian jika ada
$variants = [];
if($product['variants']){
    $variants = explode(',', $product['variants']);
}
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
</script>