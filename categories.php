<?php
session_start();
include 'config.php';

// 🔧 CLEANUP: Hapus duplikat & kategori tidak diinginkan (mie, mercon, dimsum, minuman saja)
if(isset($_GET['cleanup'])){
    $keep_categories = ['mie', 'mercon', 'dimsum', 'minuman'];
    $all_cats = mysqli_query($conn, "SELECT * FROM categories ORDER BY id");
    $kept_ids = [];
    
    while($cat = mysqli_fetch_assoc($all_cats)){
        $cat_name_lower = strtolower(trim($cat['name']));
        
        if(in_array($cat_name_lower, $keep_categories)){
            if(!isset($kept_ids[$cat_name_lower])){
                $kept_ids[$cat_name_lower] = $cat['id'];
            } else {
                // Duplikat: pindahkan produk ke kategori pertama, lalu hapus
                $old_id = $cat['id'];
                $new_id = $kept_ids[$cat_name_lower];
                mysqli_query($conn, "UPDATE products SET category_id=$new_id WHERE category_id=$old_id");
                mysqli_query($conn, "DELETE FROM categories WHERE id=$old_id");
            }
        } else {
            // Kategori tidak diinginkan: hapus produk + kategori
            $cat_id = $cat['id'];
            mysqli_query($conn, "DELETE FROM products WHERE category_id=$cat_id");
            mysqli_query($conn, "DELETE FROM categories WHERE id=$cat_id");
        }
    }
    $_SESSION['success'] = "✅ Cleanup selesai! Hanya mie, mercon, dimsum, minuman yang tersisa.";
    header("Location: categories.php");
    exit;
}

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

// 🔧 Auto-migration: Buat tabel categories jika belum ada
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle delete category
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE category_id=$id");
    $row = mysqli_fetch_assoc($check);
    if($row['count'] > 0){
        $_SESSION['error'] = "❌ Kategori tidak bisa dihapus karena masih memiliki {$row['count']} produk";
    } else {
        mysqli_query($conn, "DELETE FROM categories WHERE id=$id");
        $_SESSION['success'] = "Kategori berhasil dihapus";
    }
    header("Location: categories.php");
    exit;
}

// Handle add/edit category
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $name)));
    
    // 🔧 CLEANUP: Cek duplikat sebelum simpan
    $check_dup = mysqli_query($conn, "SELECT id FROM categories WHERE LOWER(TRIM(name))='" . strtolower(trim($name)) . "'");
    
    if(isset($_POST['id']) && $_POST['id']){
        $id = (int)$_POST['id'];
        // Exclude current category from duplicate check
        $check_dup = mysqli_query($conn, "SELECT id FROM categories WHERE LOWER(TRIM(name))='" . strtolower(trim($name)) . "' AND id != $id");
        
        if(mysqli_num_rows($check_dup) > 0){
            $_SESSION['error'] = "❌ Kategori '$name' sudah ada!";
            header("Location: categories.php");
            exit;
        }
        
        mysqli_query($conn, "UPDATE categories SET name='$name', slug='$slug' WHERE id=$id");
        $_SESSION['success'] = "Kategori berhasil diupdate";
    } else {
        if(mysqli_num_rows($check_dup) > 0){
            $_SESSION['error'] = "❌ Kategori '$name' sudah ada!";
            header("Location: categories.php");
            exit;
        }
        mysqli_query($conn, "INSERT INTO categories (name, slug) VALUES ('$name', '$slug')");
        $_SESSION['success'] = "Kategori berhasil ditambahkan";
    }
    header("Location: categories.php");
    exit;
}

// Get all categories with product count
$categories = mysqli_query($conn, "SELECT c.*, COUNT(p.id) as product_count 
                                   FROM categories c 
                                   LEFT JOIN products p ON c.id = p.category_id 
                                   GROUP BY c.id 
                                   ORDER BY c.name");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Texcer Hot</title>
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
        .badge-count { background: var(--primary); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; }
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

<!-- Main Content -->
<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-tags me-2"></i>Kelola Kategori</h2>
        <!-- 🔧 CLEANUP: Tombol bersihkan kategori -->
        <div>
            <a href="?cleanup=1" class="btn btn-warning me-2" onclick="return confirm('⚠️ PERINGATAN:\n\n- Kategori selain mie, mercon, dimsum, minuman akan DIHAPUS\n- Produk di kategori terhapus akan ikut DIHAPUS\n- Duplikat kategori akan digabung\n\nLanjutkan?')">
                <i class="fas fa-broom me-2"></i>Bersihkan Kategori
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="resetForm()">
                <i class="fas fa-plus me-2"></i>Tambah Kategori
            </button>
        </div>
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
            <?php if(mysqli_num_rows($categories) > 0): ?>
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Kategori</th>
                            <th>Slug</th>
                            <th>Jumlah Produk</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                        <tr>
                            <td><strong><?= $cat['id'] ?></strong></td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                            <td><span class="badge-count"><?= $cat['product_count'] ?> produk</span></td>
                            <td><?= date('d/m/Y H:i', strtotime($cat['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick='editCategory(<?= json_encode($cat, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus kategori ini? Pastikan tidak ada produk yang menggunakan kategori ini.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-tags fa-3x mb-3 d-block"></i>
                    <p>Belum ada kategori. Klik tombol "Tambah Kategori" untuk membuat kategori pertama.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal Category -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id" id="category_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori *</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Contoh: Mie, Mercon, Dimsum, Minuman" required>
                        <small class="text-muted">Slug akan dibuat otomatis dari nama</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetForm(){
    document.getElementById('modalTitle').textContent = 'Tambah Kategori';
    document.getElementById('category_id').value = '';
    document.querySelector('form').reset();
}

function editCategory(cat){
    document.getElementById('modalTitle').textContent = 'Edit Kategori';
    document.getElementById('category_id').value = cat.id;
    document.getElementById('name').value = cat.name;
}
</script>
</body>
</html>