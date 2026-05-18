<?php
session_start();
include 'config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

// Auto-migration: Buat tabel settings jika belum ada
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_title VARCHAR(255) DEFAULT 'Texcer Hot',
    site_description TEXT,
    hero_title VARCHAR(255) DEFAULT 'ORDER MAKANAN',
    hero_subtitle VARCHAR(255) DEFAULT 'Welcome to Texcer Hot',
    hero_button_text VARCHAR(100) DEFAULT 'Pesan Sekarang',
    menu_section_title VARCHAR(100) DEFAULT 'Menu',
    menu_section_subtitle VARCHAR(100) DEFAULT 'makanan pedas',
    testimonials_section_title VARCHAR(100) DEFAULT 'Testimoni Video',
    testimonials_section_subtitle VARCHAR(255) DEFAULT 'Lihat apa kata mereka tentang Texcer Hot',
    location_section_title VARCHAR(100) DEFAULT 'Lokasi Kami',
    location_section_subtitle VARCHAR(255) DEFAULT 'Kunjungi Texcer Hot atau pesan online untuk pengiriman',
    whatsapp_number VARCHAR(20) DEFAULT '6281234567890',
    email VARCHAR(100) DEFAULT 'info@texcerhot.com',
    address TEXT,
    opening_hours VARCHAR(100) DEFAULT '11:00 - 20:00 WIB',
    instagram_url VARCHAR(255),
    tiktok_url VARCHAR(255),
    footer_about TEXT,
    footer_copyright VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Insert default jika belum ada
mysqli_query($conn, "INSERT INTO settings (id, site_title, hero_title, hero_subtitle, whatsapp_number) 
                    VALUES (1, 'Texcer Hot', 'ORDER MAKANAN', 'Welcome to Texcer Hot', '6281234567890')
                    ON DUPLICATE KEY UPDATE id=id");

// Handle form submit
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $fields = [
        'site_title', 'site_description', 'hero_title', 'hero_subtitle', 
        'hero_button_text', 'menu_section_title', 'menu_section_subtitle',
        'testimonials_section_title', 'testimonials_section_subtitle',
        'location_section_title', 'location_section_subtitle',
        'whatsapp_number', 'email', 'address', 'opening_hours',
        'instagram_url', 'tiktok_url', 'footer_about', 'footer_copyright'
    ];
    
    $updates = [];
    foreach($fields as $field){
        $value = mysqli_real_escape_string($conn, $_POST[$field] ?? '');
        $updates[] = "$field='$value'";
    }
    
    $sql = "UPDATE settings SET " . implode(", ", $updates) . " WHERE id=1";
    
    if(mysqli_query($conn, $sql)){
        $_SESSION['success'] = "✅ Semua pengaturan berhasil disimpan!";
    } else {
        $_SESSION['error'] = "❌ Error: " . mysqli_error($conn);
    }
    
    header("Location: settings.php");
    exit;
}

// Get current settings
$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings WHERE id=1"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Website - Texcer Hot</title>
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
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: var(--primary-dark); }
        .section-title { font-size: 1.2rem; font-weight: 700; color: var(--primary); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border); }
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

    <li><a href="settings.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
</ul>
</aside>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cog me-2"></i>Pengaturan Website</h2>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <!-- General Settings -->
        <div class="card">
            <div class="card-body">
                <div class="section-title"><i class="fas fa-info-circle me-2"></i>Informasi Umum</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Judul Website</label>
                        <input type="text" name="site_title" class="form-control" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>">
                        <small class="text-muted">Judul di title browser</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Deskripsi Website</label>
                        <input type="text" name="site_description" class="form-control" value="<?= htmlspecialchars($settings['site_description'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="card">
            <div class="card-body">
                <div class="section-title"><i class="fas fa-image me-2"></i>Hero Section (Banner Utama)</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Judul Hero</label>
                        <input type="text" name="hero_title" class="form-control" value="<?= htmlspecialchars($settings['hero_title'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subjudul Hero</label>
                        <input type="text" name="hero_subtitle" class="form-control" value="<?= htmlspecialchars($settings['hero_subtitle'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teks Tombol</label>
                        <input type="text" name="hero_button_text" class="form-control" value="<?= htmlspecialchars($settings['hero_button_text'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Section -->
        <div class="card">
            <div class="card-body">
                <div class="section-title"><i class="fas fa-utensils me-2"></i>Menu Section</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Judul Section</label>
                        <input type="text" name="menu_section_title" class="form-control" value="<?= htmlspecialchars($settings['menu_section_title'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subjudul Section</label>
                        <input type="text" name="menu_section_subtitle" class="form-control" value="<?= htmlspecialchars($settings['menu_section_subtitle'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Testimonials Section -->
        <div class="card">
            <div class="card-body">
                <div class="section-title"><i class="fas fa-video me-2"></i>Testimoni Section</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Judul Section</label>
                        <input type="text" name="testimonials_section_title" class="form-control" value="<?= htmlspecialchars($settings['testimonials_section_title'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subjudul Section</label>
                        <input type="text" name="testimonials_section_subtitle" class="form-control" value="<?= htmlspecialchars($settings['testimonials_section_subtitle'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Section -->
        <div class="card">
            <div class="card-body">
                <div class="section-title"><i class="fas fa-map-marker-alt me-2"></i>Lokasi Section</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Judul Section</label>
                        <input type="text" name="location_section_title" class="form-control" value="<?= htmlspecialchars($settings['location_section_title'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subjudul Section</label>
                        <input type="text" name="location_section_subtitle" class="form-control" value="<?= htmlspecialchars($settings['location_section_subtitle'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="card">
            <div class="card-body">
                <div class="section-title"><i class="fas fa-address-card me-2"></i>Informasi Kontak</div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nomor WhatsApp</label>
                        <input type="text" name="whatsapp_number" class="form-control" value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '') ?>" placeholder="6281234567890">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($settings['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jam Buka</label>
                        <input type="text" name="opening_hours" class="form-control" value="<?= htmlspecialchars($settings['opening_hours'] ?? '') ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Media -->
        <div class="card">
            <div class="card-body">
                <div class="section-title"><i class="fas fa-share-alt me-2"></i>Social Media Links</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Instagram URL</label>
                        <input type="url" name="instagram_url" class="form-control" value="<?= htmlspecialchars($settings['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/username">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">TikTok URL</label>
                        <input type="url" name="tiktok_url" class="form-control" value="<?= htmlspecialchars($settings['tiktok_url'] ?? '') ?>" placeholder="https://tiktok.com/@username">
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="card">
            <div class="card-body">
                <div class="section-title"><i class="fas fa-layer-group me-2"></i>Footer</div>
                <div class="mb-3">
                    <label class="form-label">Tentang (Footer)</label>
                    <textarea name="footer_about" class="form-control" rows="3"><?= htmlspecialchars($settings['footer_about'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Copyright Text</label>
                    <input type="text" name="footer_copyright" class="form-control" value="<?= htmlspecialchars($settings['footer_copyright'] ?? '') ?>">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100">
            <i class="fas fa-save me-2"></i>Simpan Semua Pengaturan
        </button>
    </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>