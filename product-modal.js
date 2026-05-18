let currentProduct = {};
let selectedToppings = [];
let selectedSpiceLevel = null;
let quantity = 1;

// Format Rupiah
function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID').format(angka);
}

// Buka modal produk
function openProductModal(productId) {
    fetch(`get_product_detail.php?id=${productId}`)
        .then(response => response.json())
        .then(data => {
            currentProduct = data;
            selectedToppings = [];
            selectedSpiceLevel = null;
            quantity = 1;
            
            // Isi data produk
            document.getElementById('modalImage').src = data.image;
            document.getElementById('modalTitle').textContent = data.name;
            document.getElementById('modalDesc').textContent = data.description;
            document.getElementById('modalPrice').textContent = formatRupiah(data.price);
            document.getElementById('quantity').value = quantity;
            
            // Render topping options
            renderToppings(data.toppings);
            
            // Render spice level options
            renderSpiceLevels(data.spiceLevels);
            
            // Update total
            updateTotalPrice();
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('productModal'));
            modal.show();
        });
}

// Render pilihan topping
function renderToppings(toppings) {
    const container = document.getElementById('toppingOptions');
    container.innerHTML = '';
    
    toppings.forEach(topping => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4';
        col.innerHTML = `
            <div class="form-check border rounded p-2 ${topping.is_available ? '' : 'opacity-50'}">
                <input class="form-check-input" type="checkbox" 
                       id="topping_${topping.id}" 
                       value="${topping.id}"
                       data-price="${topping.price}"
                       data-name="${topping.name}"
                       ${!topping.is_available ? 'disabled' : ''}
                       onchange="toggleTopping(this)">
                <label class="form-check-label w-100" for="topping_${topping.id}">
                    <div class="small fw-bold">${topping.name}</div>
                    <div class="text-primary small">+Rp ${formatRupiah(topping.price)}</div>
                </label>
            </div>
        `;
        container.appendChild(col);
    });
}

// Render pilihan level pedas
function renderSpiceLevels(levels) {
    const container = document.getElementById('spiceLevelOptions');
    container.innerHTML = '';
    
    levels.forEach(level => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4';
        col.innerHTML = `
            <div class="form-check border rounded p-2 ${level.is_available ? '' : 'opacity-50'}">
                <input class="form-check-input" type="radio" 
                       name="spiceLevel" 
                       id="spice_${level.id}" 
                       value="${level.id}"
                       data-price="${level.price}"
                       data-name="${level.level_name}"
                       ${!level.is_available ? 'disabled' : ''}
                       onchange="selectSpiceLevel(this)">
                <label class="form-check-label w-100" for="spice_${level.id}">
                    <div class="small fw-bold">${level.level_name}</div>
                    ${level.price > 0 ? `<div class="text-primary small">+Rp ${formatRupiah(level.price)}</div>` : '<div class="text-muted small">Gratis</div>'}
                </label>
            </div>
        `;
        container.appendChild(col);
    });
}

// Toggle topping
function toggleTopping(checkbox) {
    const toppingId = parseInt(checkbox.value);
    const toppingName = checkbox.dataset.name;
    const toppingPrice = parseFloat(checkbox.dataset.price);
    
    if (checkbox.checked) {
        selectedToppings.push({
            id: toppingId,
            name: toppingName,
            price: toppingPrice
        });
    } else {
        selectedToppings = selectedToppings.filter(t => t.id !== toppingId);
    }
    
    updateTotalPrice();
}

// Select spice level
function selectSpiceLevel(radio) {
    const levelId = parseInt(radio.value);
    const levelName = radio.dataset.name;
    const levelPrice = parseFloat(radio.dataset.price);
    
    selectedSpiceLevel = {
        id: levelId,
        name: levelName,
        price: levelPrice
    };
    
    updateTotalPrice();
}

// Update total harga
function updateTotalPrice() {
    let total = parseFloat(currentProduct.price);
    
    // Tambah harga topping
    selectedToppings.forEach(topping => {
        total += topping.price;
    });
    
    // Tambah harga level pedas
    if (selectedSpiceLevel) {
        total += selectedSpiceLevel.price;
    }
    
    // Kalikan dengan quantity
    total *= quantity;
    
    document.getElementById('totalPrice').textContent = formatRupiah(total);
}

// Increment/Decrement quantity
function incrementQty() {
    quantity++;
    document.getElementById('quantity').value = quantity;
    updateTotalPrice();
}

function decrementQty() {
    if (quantity > 1) {
        quantity--;
        document.getElementById('quantity').value = quantity;
        updateTotalPrice();
    }
}

// Add to cart
function addToCart() {
    const orderData = {
        product_id: currentProduct.id,
        product_name: currentProduct.name,
        base_price: currentProduct.price,
        quantity: quantity,
        toppings: selectedToppings,
        spice_level: selectedSpiceLevel,
        total_price: parseFloat(document.getElementById('totalPrice').textContent.replace(/\./g, ''))
    };
    
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(orderData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Produk berhasil ditambahkan ke keranjang!');
            bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
            updateCartCount();
        } else {
            alert('Terjadi kesalahan: ' + data.message);
        }
    });
}

// Buy now
function buyNow() {
    addToCart().then(() => {
        window.location.href = 'checkout.php';
    });
}

// Update cart count
function updateCartCount() {
    fetch('get_cart_count.php')
        .then(response => response.json())
        .then(data => {
            document.querySelector('.cart-count').textContent = data.count;
        });
}