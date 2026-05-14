<?php
session_start();
include 'config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

// 🔧 Auto-migration: Buat tabel settings jika belum ada
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_title VARCHAR(255) DEFAULT 'Texcer Hot',
    site_description TEXT,
    hero_title VARCHAR(255) DEFAULT 'Nikmati Makanan Lezat & Hangat',
    hero_subtitle TEXT DEFAULT 'Pesanan mudah, cepat, dan praktis',
    whatsapp_number VARCHAR(20) DEFAULT '6281234567890',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Insert default jika belum ada
mysqli_query($conn, "INSERT INTO settings (id, site_title, hero_title, hero_subtitle, whatsapp_number) 
                    VALUES (1, 'Texcer Hot', 'Nikmati Makanan Lezat & Hangat', 'Pesanan mudah, cepat, dan praktis', '6281234567890')
                    ON DUPLICATE KEY UPDATE id=id");

// Handle form submit
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $site_title = mysqli_real_escape_string($conn, $_POST['site_title']);
    $hero_title = mysqli_real_escape_string($conn, $_POST['hero_title']);
    $hero_subtitle = mysqli_real_escape_string($conn, $_POST['hero_subtitle']);
    $whatsapp_number = mysqli_real_escape_string($conn, $_POST['whatsapp_number']);
    
    mysqli_query($conn, "UPDATE settings SET 
        site_title='$site_title',
        hero_title='$hero_title',
        hero_subtitle='$hero_subtitle',
        whatsapp_number='$whatsapp_number'
        WHERE id=1");
    
    $_SESSION['success'] = "✅ Pengaturan berhasil disimpan";
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
    <title>Pengaturan - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #8B6F4E;
            --primary-dark: #6B5637;
            --secondary: #D4A574;
            --bg-cream: #FDF8F3;
            --bg-white: #FFFFFF;
            --text-dark: #3D2914;
            --text-gray: #8B7355;
            --border: #E8DDD4;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f5f5f5;
            color: var(--text-dark);
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--bg-white);
            min-height: 100vh;
            padding: 24px 16px;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid var(--border);
            z-index: 1000;
        }
        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            padding: 12px 16px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-menu { list-style: none; padding: 0 8px; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-gray);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s;
            font-weight: 500;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: var(--bg-cream);
            color: var(--primary);
        }
        .sidebar-menu i { width: 20px; text-align: center; }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 24px;
        }
        
        /* Card & Form */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            background: var(--bg-white);
        }
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(139, 111, 78, 0.15);
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 10px;
            padding: 10px 24px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .page-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        .page-header h2 {
            margin: 0;
            color: var(--text-dark);
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <i class="fas fa-pepper-hot"></i>
        <span>Texcer Hot</span>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
        <li><a href="orders.php"><i class="fas fa-shopping-bag"></i><span>Pesanan</span></a></li>
        <li><a href="products.php"><i class="fas fa-utensils"></i><span>Produk</span></a></li>
        <li><a href="categories.php"><i class="fas fa-tags"></i><span>Kategori</span></a></li>
        <li><a href="customers.php"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
        <li><a href="settings.php" class="active"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
    </ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <i class="fas fa-cog fa-2x" style="color: var(--primary);"></i>
        <h2>Pengaturan Website</h2>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);">
            <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>

    <div class="card">
        <div class="card-body p-4">
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">Judul Website</label>
                    <input type="text" name="site_title" class="form-control" value="<?= htmlspecialchars($settings['site_title']) ?>" required>
                    <small class="text-muted">Judul yang muncul di title browser</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Judul Hero (Halaman Depan)</label>
                    <input type="text" name="hero_title" class="form-control" value="<?= htmlspecialchars($settings['hero_title']) ?>" required>
                    <small class="text-muted">Judul besar di halaman utama</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Subjudul Hero</label>
                    <textarea name="hero_subtitle" class="form-control" rows="3"><?= htmlspecialchars($settings['hero_subtitle']) ?></textarea>
                    <small class="text-muted">Deskripsi di bawah judul hero</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Nomor WhatsApp (Format: 628xxx)</label>
                    <input type="text" name="whatsapp_number" class="form-control" value="<?= htmlspecialchars($settings['whatsapp_number']) ?>" placeholder="6281234567890">
                    <small class="text-muted">Untuk link WhatsApp otomatis</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Simpan Pengaturan
                </button>
            </form>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>