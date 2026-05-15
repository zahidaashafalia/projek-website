<?php
session_start();
include 'config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

// 🔧 AUTO-MIGRATION: Buat tabel & kolom jika belum ada
function autoMigrate($conn){
    // Buat tabel categories jika belum ada
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Buat tabel products jika belum ada
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255),
        description TEXT,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        stock INT DEFAULT 0,
        category_id INT,
        category_name VARCHAR(100),
        image VARCHAR(255),
        is_available TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert kategori default
    mysqli_query($conn, "INSERT INTO categories (name, slug) VALUES 
        ('Makanan','makanan'),
        ('Minuman','minuman'),
        ('Dimsum','dimsum'),
        ('Mie','mie')
        ON DUPLICATE KEY UPDATE name=name");
}
autoMigrate($conn);

// Handle delete product
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM products WHERE id=$id"));
    if($product && !empty($product['image']) && file_exists('uploads/'.$product['image'])){
        unlink('uploads/'.$product['image']);
    }
    mysqli_query($conn, "DELETE FROM products WHERE id=$id");
    $_SESSION['success'] = "Produk berhasil dihapus";
    header("Location: products.php");
    exit;
}

// Handle add/edit product
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 'NULL';
    $category_name = !empty($_POST['category_name']) ? mysqli_real_escape_string($conn, $_POST['category_name']) : 'Uncategorized';
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $name)));
    
    if(isset($_POST['id']) && $_POST['id']){
        // UPDATE existing product
        $id = (int)$_POST['id'];
        $cat_sql = $category_id === 'NULL' ? "category_id = NULL" : "category_id = $category_id";
        $update_sql = "UPDATE products SET 
            name='$name', 
            slug='$slug', 
            description='$description', 
            price=$price, 
            stock=$stock, 
            $cat_sql,
            category_name='$category_name',
            is_available=$is_available";
        
        // Handle image upload
        if(!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0){
            $target_dir = "uploads/";
            if(!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $target_dir . $image;
            
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)){
                // Delete old image
                $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM products WHERE id=$id"));
                if(!empty($old['image']) && file_exists($target_dir.$old['image'])){
                    unlink($target_dir.$old['image']);
                }
                $update_sql .= ", image='$image'";
            }
        }
        
        $update_sql .= " WHERE id=$id";
        
        if(mysqli_query($conn, $update_sql)){
            // ✅ SIMPAN TOPPING YANG DIPILIH
if(isset($_POST['toppings']) && is_array($_POST['toppings'])){
    // Hapus topping lama dulu
    mysqli_query($conn, "DELETE FROM product_toppings WHERE product_id=$id");
    
    // Insert topping baru
    foreach($_POST['toppings'] as $topping_id){
        $tid = (int)$topping_id;
        mysqli_query($conn, "INSERT INTO product_toppings (product_id, topping_id) VALUES ($id, $tid)");
    }
}
            $_SESSION['success'] = "✅ Produk berhasil diupdate";
        } else {
            $_SESSION['error'] = "❌ Error: " . mysqli_error($conn);
        }
    } else {
        // INSERT new product
        $image = '';
        if(!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0){
            $target_dir = "uploads/";
            if(!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $target_dir . $image;
            
            if(!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)){
                $_SESSION['error'] = "❌ Gagal upload gambar";
                header("Location: products.php");
                exit;
            }
        }
        
        $cat_val = $category_id === 'NULL' ? 'NULL' : $category_id;
        $sql = "INSERT INTO products (name, slug, description, price, stock, category_id, category_name, image, is_available) 
                VALUES ('$name', '$slug', '$description', $price, $stock, $cat_val, '$category_name', '$image', $is_available)";
        
        if(mysqli_query($conn, $sql)){
            // ✅ SIMPAN TOPPING UNTUK PRODUK BARU
if(isset($_POST['toppings']) && is_array($_POST['toppings'])){
    $new_id = mysqli_insert_id($conn); // Ambil ID produk yang baru dibuat
    foreach($_POST['toppings'] as $topping_id){
        $tid = (int)$topping_id;
        mysqli_query($conn, "INSERT INTO product_toppings (product_id, topping_id) VALUES ($new_id, $tid)");
    }
}
            $_SESSION['success'] = "✅ Produk berhasil ditambahkan";
        } else {
            $_SESSION['error'] = "❌ Error: " . mysqli_error($conn);
        }
    }
    
    header("Location: products.php");
    exit;
}

// Get all products with category info
$products = mysqli_query($conn, "SELECT p.*, c.name as category_name 
                                 FROM products p 
                                 LEFT JOIN categories c ON p.category_id = c.id 
                                 ORDER BY p.created_at DESC");

// Get all categories
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #8B6F4E; --primary-dark: #6B5637; --bg-cream: #FDF8F3; --text-dark: #3D2914; --text-gray: #8B7355; --border: #E8DDD4; }
        body { background: #f5f5f5; font-family: 'Segoe UI', sans-serif; }
        .sidebar { width: 260px; background: white; min-height: 100vh; position: fixed; left: 0; border-right: 1px solid var(--border); z-index: 1000; }
        .sidebar-logo { font-size: 1.5rem; font-weight: 800; color: var(--primary); padding: 12px 16px; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { list-style: none; padding: 0 8px; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-gray); text-decoration: none; border-radius: 12px; transition: all 0.2s; font-weight: 500; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: var(--bg-cream); color: var(--primary); }
        .sidebar-menu i { width: 20px; text-align: center; }
        .main-content { margin-left: 260px; padding: 24px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: var(--primary-dark); }
        .table img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; background: #f8f8f8; }
        .badge-available { background: #D1FAE5; color: #065F46; padding: 6px 12px; border-radius: 20px; }
        .badge-unavailable { background: #FEE2E2; color: #B91C1C; padding: 6px 12px; border-radius: 20px; }
        @media (max-width: 768px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo"><i class="fas fa-pepper-hot"></i><span>Texcer Hot</span></div>
    <ul class="sidebar-menu">
    <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
    <li><a href="orders.php"><i class="fas fa-shopping-bag"></i><span>Pesanan</span></a></li>
    <li><a href="products.php"><i class="fas fa-utensils"></i><span>Produk</span></a></li>
    <li><a href="categories.php"><i class="fas fa-tags"></i><span>Kategori</span></a></li>
    <li><a href="customers.php" class="<?= ($currentPage ?? '') == 'customers.php' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
    
    <!-- ✅ MENU BARU: Kelola Gambar -->
    <li><a href="manage-images.php" class="<?= ($currentPage ?? '') == 'manage-images.php' ? 'active' : '' ?>"><i class="fas fa-images"></i><span>Kelola Gambar</span></a></li>
    
    <li><a href="settings.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
</ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-utensils me-2"></i>Kelola Produk</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
            <i class="fas fa-plus me-2"></i>Tambah Produk
        </button>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div style="overflow-x: auto;">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Gambar</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($products) > 0): ?>
                            <?php while($p = mysqli_fetch_assoc($products)): ?>
                            <tr>
                                <td>
                                    <?php if(!empty($p['image']) && file_exists('uploads/'.$p['image'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                    <?php else: ?>
                                        <div style="width:80px;height:80px;background:#eee;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#999;">
                                            <i class="fas fa-image fa-2x"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                <td><?= htmlspecialchars($p['category_name'] ?? $p['category_name'] ?? 'Uncategorized') ?></td>
                                <td>Rp<?= number_format($p['price']) ?></td>
                                <td><?= $p['stock'] ?></td>
                                <td>
                                    <span class="badge <?= ($p['is_available'] ?? 1) ? 'badge-available' : 'badge-unavailable' ?>">
                                        <?= ($p['is_available'] ?? 1) ? '✓ Tersedia' : '✗ Habis' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning me-1" onclick='editProduct(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus produk ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-box-open fa-3x mb-3"></i>
                                    <p>Belum ada produk. Klik tombol "Tambah Produk" untuk menambahkan.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal Product -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="product_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Produk *</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori</label>
                            <select name="category_id" id="category_id" class="form-select">
                                <option value="">Pilih Kategori</option>
                                <?php 
                                if($categories):
                                    mysqli_data_seek($categories, 0);
                                    while($c = mysqli_fetch_assoc($categories)): 
                                ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </select>
                            <small class="text-muted">Atau ketik manual:</small>
                            <input type="text" name="category_name" id="category_name" class="form-control mt-2" placeholder="Kategori baru">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Harga (Rp) *</label>
                            <input type="number" name="price" id="price" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stok *</label>
                            <input type="number" name="stock" id="stock" class="form-control" value="0" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gambar Produk</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/*">
                            <small class="text-muted">Format: JPG, PNG, max 5MB</small>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_available" id="is_available" class="form-check-input" checked>
                                <label class="form-check-label" for="is_available">Produk Tersedia</label>
                            </div>
                        </div>
                        <!-- ✅ PILIHAN TOPPING -->
<div class="col-12 mb-3">
    <label class="form-label fw-bold"><i class="fas fa-drumstick-bite me-1"></i>Topping Tersedia</label>
    <div class="row" id="topping-list">
        <?php
        // Ambil semua topping yang aktif
        $all_toppings = mysqli_query($conn, "SELECT * FROM toppings WHERE is_available=1 ORDER BY display_order");
        
        // Jika edit, ambil topping yang sudah dipilih untuk produk ini
        $selected_toppings = [];
        if(isset($p['id']) && $p['id']){
            $pt = mysqli_query($conn, "SELECT topping_id FROM product_toppings WHERE product_id={$p['id']}");
            $selected_toppings = array_column(mysqli_fetch_all($pt), 'topping_id');
        }
        
        while($topping = mysqli_fetch_assoc($all_toppings)):
        ?>
        <div class="col-md-4 col-sm-6 mb-2">
            <div class="form-check">
                <input type="checkbox" 
                       name="toppings[]" 
                       value="<?= $topping['id'] ?>" 
                       class="form-check-input"
                       id="topping_<?= $topping['id'] ?>"
                       <?= in_array($topping['id'], $selected_toppings) ? 'checked' : '' ?>>
                <label class="form-check-label d-flex justify-content-between w-100" for="topping_<?= $topping['id'] ?>">
                    <span><?= $topping['name'] ?></span>
                    <span class="text-primary fw-bold">+Rp<?= number_format($topping['price']) ?></span>
                </label>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <small class="text-muted">Centang topping yang ingin ditambahkan ke produk ini</small>
</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetForm(){
    document.getElementById('modalTitle').textContent = 'Tambah Produk';
    document.getElementById('product_id').value = '';
    document.querySelector('form').reset();
    document.getElementById('is_available').checked = true;
    document.getElementById('stock').value = '0';
}

function editProduct(p){
    // Mengisi form dengan data produk yang diklik
    document.getElementById('modalTitle').textContent = 'Edit Produk';
    document.getElementById('product_id').value = p.id;
    document.getElementById('name').value = p.name;
    document.getElementById('category_id').value = p.category_id || '';
    document.getElementById('category_name').value = p.category_name || '';
    document.getElementById('description').value = p.description || '';
    document.getElementById('price').value = p.price;
    document.getElementById('stock').value = p.stock;
    document.getElementById('is_available').checked = (p.is_available == 1);
    document.getElementById('image').value = ''; // Reset gambar agar tidak error upload
    
    // MEMUNCULKAN POPUP (Modal)
    var myModal = new bootstrap.Modal(document.getElementById('productModal'));
    myModal.show();
}
</script>
</body>
</html>