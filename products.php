<?php
session_start();
include 'config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

// Auto-migration
function autoMigrate($conn){
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
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
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS toppings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        is_available TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS levels (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        extra_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        display_order INT DEFAULT 0,
        is_available TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS product_toppings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        product_id INT NOT NULL,
        topping_id INT NOT NULL,
        UNIQUE KEY unique_product_topping (product_id, topping_id)
    )");
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS product_levels (
        id INT PRIMARY KEY AUTO_INCREMENT,
        product_id INT NOT NULL,
        level_id INT NOT NULL,
        UNIQUE KEY unique_product_level (product_id, level_id)
    )");
    mysqli_query($conn, "INSERT INTO categories (name, slug) VALUES
        ('Makanan','makanan'),('Minuman','minuman'),('Dimsum','dimsum'),('Mie','mie')
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
    mysqli_query($conn, "DELETE FROM product_toppings WHERE product_id=$id");
    mysqli_query($conn, "DELETE FROM product_levels WHERE product_id=$id");
    mysqli_query($conn, "DELETE FROM products WHERE id=$id");
    $_SESSION['success'] = "Produk berhasil dihapus";
    header("Location: products.php");
    exit;
}

// Handle add/edit product
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name            = mysqli_real_escape_string($conn, $_POST['name']);
    $description     = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $price           = (float)($_POST['price'] ?? 0);
    $stock           = (int)($_POST['stock'] ?? 0);
    $category_id     = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 'NULL';
    $category_name   = !empty($_POST['category_name']) ? mysqli_real_escape_string($conn, $_POST['category_name']) : 'Uncategorized';
    $is_available    = isset($_POST['is_available']) ? 1 : 0;
    $slug            = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $name)));

    if(isset($_POST['id']) && $_POST['id']){
        // UPDATE
        $id = (int)$_POST['id'];
        $cat_sql = $category_id === 'NULL' ? "category_id = NULL" : "category_id = $category_id";
        $update_sql = "UPDATE products SET
            name='$name', slug='$slug', description='$description',
            price=$price, stock=$stock, $cat_sql,
            category_name='$category_name', is_available=$is_available";

        if(!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0){
            $target_dir = "uploads/";
            if(!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $ext   = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = time() . '_' . uniqid() . '.' . $ext;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_dir.$image)){
                $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM products WHERE id=$id"));
                if(!empty($old['image']) && file_exists($target_dir.$old['image'])) unlink($target_dir.$old['image']);
                $update_sql .= ", image='$image'";
            }
        }
        $update_sql .= " WHERE id=$id";

        if(mysqli_query($conn, $update_sql)){
            // Simpan toppings
            mysqli_query($conn, "DELETE FROM product_toppings WHERE product_id=$id");
            if(!empty($_POST['toppings']) && is_array($_POST['toppings'])){
                foreach($_POST['toppings'] as $tid){
                    $tid = (int)$tid;
                    mysqli_query($conn, "INSERT IGNORE INTO product_toppings (product_id, topping_id) VALUES ($id, $tid)");
                }
            }
            // Simpan levels
            mysqli_query($conn, "DELETE FROM product_levels WHERE product_id=$id");
            if(!empty($_POST['levels']) && is_array($_POST['levels'])){
                foreach($_POST['levels'] as $lid){
                    $lid = (int)$lid;
                    mysqli_query($conn, "INSERT IGNORE INTO product_levels (product_id, level_id) VALUES ($id, $lid)");
                }
            }
            $_SESSION['success'] = "✅ Produk berhasil diupdate";
        } else {
            $_SESSION['error'] = "❌ Error: " . mysqli_error($conn);
        }
    } else {
        // INSERT
        $image = '';
        if(!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0){
            $target_dir = "uploads/";
            if(!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $ext   = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = time() . '_' . uniqid() . '.' . $ext;
            if(!move_uploaded_file($_FILES['image']['tmp_name'], $target_dir.$image)){
                $_SESSION['error'] = "❌ Gagal upload gambar";
                header("Location: products.php");
                exit;
            }
        }
        $cat_val = $category_id === 'NULL' ? 'NULL' : $category_id;
        $sql = "INSERT INTO products (name, slug, description, price, stock, category_id, category_name, image, is_available)
                VALUES ('$name','$slug','$description',$price,$stock,$cat_val,'$category_name','$image',$is_available)";
        if(mysqli_query($conn, $sql)){
            $new_id = mysqli_insert_id($conn);
            // Simpan toppings
            if(!empty($_POST['toppings']) && is_array($_POST['toppings'])){
                foreach($_POST['toppings'] as $tid){
                    $tid = (int)$tid;
                    mysqli_query($conn, "INSERT IGNORE INTO product_toppings (product_id, topping_id) VALUES ($new_id, $tid)");
                }
            }
            // Simpan levels
            if(!empty($_POST['levels']) && is_array($_POST['levels'])){
                foreach($_POST['levels'] as $lid){
                    $lid = (int)$lid;
                    mysqli_query($conn, "INSERT IGNORE INTO product_levels (product_id, level_id) VALUES ($new_id, $lid)");
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

// Ambil semua produk
$products = mysqli_query($conn, "SELECT p.*, c.name as cat_display
    FROM products p LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.created_at DESC");

// Ambil semua kategori
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");

// Ambil semua toppings
$all_toppings_q = mysqli_query($conn, "SELECT * FROM toppings ORDER BY display_order, name");
$all_toppings = [];
while($t = mysqli_fetch_assoc($all_toppings_q)) $all_toppings[] = $t;

// Ambil semua levels
$all_levels_q = mysqli_query($conn, "SELECT * FROM levels ORDER BY display_order, id");
$all_levels = [];
while($l = mysqli_fetch_assoc($all_levels_q)) $all_levels[] = $l;
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
        .table img { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; }
        .badge-available   { background: #D1FAE5; color: #065F46; padding: 5px 10px; border-radius: 20px; font-size: .8rem; }
        .badge-unavailable { background: #FEE2E2; color: #B91C1C; padding: 5px 10px; border-radius: 20px; font-size: .8rem; }
        /* Topping & Level grid inside modal */
        .option-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; }
        .option-item { border: 1.5px solid var(--border); border-radius: 10px; padding: 10px 12px; cursor: pointer; transition: all .2s; }
        .option-item:has(input:checked) { border-color: var(--primary); background: #FDF8F3; }
        .option-item label { cursor: pointer; display: flex; justify-content: space-between; align-items: center; margin: 0; gap: 8px; }
        .option-item input { flex-shrink: 0; accent-color: var(--primary); }
        .price-tag { font-size: .8rem; font-weight: 700; color: var(--primary); white-space: nowrap; }
        .section-label { font-size: .85rem; font-weight: 700; color: var(--text-gray); text-transform: uppercase; letter-spacing: .6px; margin: 16px 0 8px; }
        @media (max-width: 768px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

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
    
    <li><a href="admin_topping.php" class="<?= ($currentPage ?? '') == 'admin_topping.php' ? 'active' : '' ?>">
    <i class="fas fa-pepper-hot"></i><span>Topping & Level</span>
</a></li>
<li>
    <a href="admin_store_profile.php" class="<?= ($currentPage == 'admin_store_profile.php') ? 'active' : '' ?>">
        <i class="fas fa-store"></i>
        <span>Profil Toko</span>
    </a>
</li>
    <li><a href="settings.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
</ul>
</aside>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-utensils me-2"></i>Kelola Produk</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
            <i class="fas fa-plus me-2"></i>Tambah Produk
        </button>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
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
                            <th>Topping</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($products) > 0):
                            mysqli_data_seek($products, 0);
                            while($p = mysqli_fetch_assoc($products)):
                                // Hitung jumlah topping & level aktif untuk produk ini
                                $cnt_top = mysqli_fetch_assoc(mysqli_query($conn,
                                    "SELECT COUNT(*) as n FROM product_toppings WHERE product_id={$p['id']}"))['n'];
                                $cnt_lvl = mysqli_fetch_assoc(mysqli_query($conn,
                                    "SELECT COUNT(*) as n FROM product_levels WHERE product_id={$p['id']}"))['n'];
                        ?>
                        <tr>
                            <td>
                                <?php if(!empty($p['image']) && file_exists('uploads/'.$p['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="">
                                <?php else: ?>
                                    <div style="width:70px;height:70px;background:#eee;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td><?= htmlspecialchars($p['cat_display'] ?? $p['category_name'] ?? '-') ?></td>
                            <td>Rp<?= number_format($p['price']) ?></td>
                            <td><?= $p['stock'] ?></td>
                            <td>
                                <?php if($cnt_top > 0): ?>
                                    <span class="badge" style="background:#FEF3C7;color:#92400E;"><?= $cnt_top ?> topping</span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($cnt_lvl > 0): ?>
                                    <span class="badge" style="background:#FEE2E2;color:#991B1B;"><?= $cnt_lvl ?> level</span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= ($p['is_available'] ?? 1) ? 'badge-available' : 'badge-unavailable' ?>">
                                    <?= ($p['is_available'] ?? 1) ? '✓ Tersedia' : '✗ Habis' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1"
                                    onclick='editProduct(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Yakin ingin menghapus?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">
                            <i class="fas fa-box-open fa-3x mb-3 d-block"></i>Belum ada produk.
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- ============================================================
     MODAL TAMBAH / EDIT PRODUK
============================================================ -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="product_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <!-- Nama -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nama Produk *</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <!-- Kategori -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Kategori</label>
                            <select name="category_id" id="category_id" class="form-select">
                                <option value="">Pilih Kategori</option>
                                <?php if($categories): mysqli_data_seek($categories, 0);
                                      while($c = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                            <input type="text" name="category_name" id="category_name" class="form-control mt-2"
                                   placeholder="Atau ketik kategori baru…">
                        </div>
                        <!-- Deskripsi -->
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Deskripsi</label>
                            <textarea name="description" id="description" class="form-control" rows="2"></textarea>
                        </div>
                        <!-- Harga, Stok, Gambar -->
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Harga (Rp) *</label>
                            <input type="number" name="price" id="price" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Stok *</label>
                            <input type="number" name="stock" id="stock" class="form-control" value="0" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Gambar</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/*">
                            <small class="text-muted">JPG/PNG, maks 5MB</small>
                        </div>
                        <!-- Status -->
                        <div class="col-12 mb-4">
                            <div class="form-check">
                                <input type="checkbox" name="is_available" id="is_available" class="form-check-input" checked>
                                <label class="form-check-label" for="is_available">Produk Tersedia</label>
                            </div>
                        </div>

                        <div class="col-12"><hr></div>

                        <!-- ===== TOPPING SECTION ===== -->
                        <div class="col-12 mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-drumstick-bite" style="color:var(--primary)"></i>
                                <strong>Topping Tersedia untuk Produk Ini</strong>
                            </div>
                            <small class="text-muted">Centang topping yang bisa dipilih pelanggan saat memesan</small>
                        </div>

                        <?php if(empty($all_toppings)): ?>
                        <div class="col-12 mb-3">
                            <div class="alert alert-warning py-2">
                                Belum ada data topping. 
                                <a href="toppings.php" target="_blank">Tambah topping dulu →</a>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="col-12 mb-4">
                            <div class="option-grid" id="topping-grid">
                                <?php foreach($all_toppings as $t): ?>
                                <div class="option-item">
                                    <label>
                                        <input type="checkbox"
                                               name="toppings[]"
                                               value="<?= $t['id'] ?>"
                                               id="top_<?= $t['id'] ?>">
                                        <span><?= htmlspecialchars($t['name']) ?></span>
                                        <span class="price-tag">+Rp<?= number_format($t['price']) ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ===== LEVEL SECTION ===== -->
                        <div class="col-12 mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-fire" style="color:#e53e3e"></i>
                                <strong>Level Pedas Tersedia untuk Produk Ini</strong>
                            </div>
                            <small class="text-muted">Centang level yang bisa dipilih pelanggan. Kosongkan jika produk tidak ada pilihan level.</small>
                        </div>

                        <?php if(empty($all_levels)): ?>
                        <div class="col-12 mb-3">
                            <div class="alert alert-warning py-2">
                                Belum ada data level.
                                <a href="levels.php" target="_blank">Tambah level dulu →</a>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="col-12 mb-3">
                            <div class="option-grid" id="level-grid">
                                <?php foreach($all_levels as $l): ?>
                                <div class="option-item">
                                    <label>
                                        <input type="checkbox"
                                               name="levels[]"
                                               value="<?= $l['id'] ?>"
                                               id="lvl_<?= $l['id'] ?>">
                                        <span><?= htmlspecialchars($l['name']) ?></span>
                                        <?php if($l['extra_price'] > 0): ?>
                                        <span class="price-tag" style="color:#e53e3e;">+Rp<?= number_format($l['extra_price']) ?></span>
                                        <?php else: ?>
                                        <span class="price-tag" style="color:#888;">Gratis</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div><!-- end .row -->
                </div><!-- end modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Data untuk prefill saat edit
const allToppings = <?= json_encode($all_toppings) ?>;
const allLevels   = <?= json_encode($all_levels) ?>;

function resetForm(){
    document.getElementById('modalTitle').textContent = 'Tambah Produk';
    document.getElementById('product_id').value = '';
    document.querySelector('#productModal form').reset();
    document.getElementById('is_available').checked = true;
    document.getElementById('stock').value = '0';
    // Uncheck semua topping & level
    document.querySelectorAll('#topping-grid input[type=checkbox]').forEach(cb => cb.checked = false);
    document.querySelectorAll('#level-grid input[type=checkbox]').forEach(cb => cb.checked = false);
}

function editProduct(p){
    document.getElementById('modalTitle').textContent = 'Edit Produk: ' + p.name;
    document.getElementById('product_id').value   = p.id;
    document.getElementById('name').value          = p.name;
    document.getElementById('category_id').value   = p.category_id || '';
    document.getElementById('category_name').value = p.category_name || '';
    document.getElementById('description').value   = p.description || '';
    document.getElementById('price').value         = p.price;
    document.getElementById('stock').value         = p.stock;
    document.getElementById('is_available').checked = (p.is_available == 1);
    document.getElementById('image').value = '';

    // Uncheck semua dulu
    document.querySelectorAll('#topping-grid input[type=checkbox]').forEach(cb => cb.checked = false);
    document.querySelectorAll('#level-grid input[type=checkbox]').forEach(cb => cb.checked = false);

    // Ambil topping & level via AJAX agar sinkron dengan DB
    fetch('api/get_product_options.php?product_id=' + p.id)
        .then(r => r.json())
        .then(data => {
            (data.toppings || []).forEach(tid => {
                const cb = document.getElementById('top_' + tid);
                if(cb) cb.checked = true;
            });
            (data.levels || []).forEach(lid => {
                const cb = document.getElementById('lvl_' + lid);
                if(cb) cb.checked = true;
            });
        })
        .catch(() => {
            // Fallback: tidak ada prefill topping/level (masih bisa disimpan ulang)
        });

    new bootstrap.Modal(document.getElementById('productModal')).show();
}
</script>
</body>
</html>