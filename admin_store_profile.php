<?php
require_once 'config.php'; // File ini sudah memanggil session_start() di dalamnya

// HAPUS ATAU KOMENTARI BARIS INI KARENA SUDAH ADA DI CONFIG.PHP
// session_start(); 

// Cek apakah user adalah admin (sesuaikan dengan sistem login Anda)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$msg = '';
$msgType = 'success';

// ... sisa kode lainnya ...

// Ambil data profile saat ini
$profile_query = mysqli_query($conn, "SELECT * FROM store_profile WHERE id=1 LIMIT 1");
$profile = mysqli_fetch_assoc($profile_query);

// Jika belum ada data, buat default agar form tidak error
if (!$profile) {
    mysqli_query($conn, "INSERT INTO store_profile (id, store_name, tagline, description, logo_path, cover_path, address, whatsapp, email, instagram, tiktok, opening_hours, latitude, longitude, rating_avg, total_reviews, established_year) VALUES (1, 'Texcer Hot', 'TEMAN PEDASMU 🌶️', 'Pusat kuliner pedas...', 'assets/images/logo-texcer.png', 'assets/images/cover-texcer.jpg', 'Alamat Toko', '6281234567890', 'email@toko.com', 'instagram_toko', 'tiktok_toko', '11:00 - 20:00', '-6.5236', '110.7668', 4.8, 100, 2020)");
    $profile_query = mysqli_query($conn, "SELECT * FROM store_profile WHERE id=1 LIMIT 1");
    $profile = mysqli_fetch_assoc($profile_query);
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_name = mysqli_real_escape_string($conn, $_POST['store_name']);
    $tagline = mysqli_real_escape_string($conn, $_POST['tagline']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $whatsapp = mysqli_real_escape_string($conn, $_POST['whatsapp']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $instagram = mysqli_real_escape_string($conn, $_POST['instagram']);
    $tiktok = mysqli_real_escape_string($conn, $_POST['tiktok']);
    $opening_hours = mysqli_real_escape_string($conn, $_POST['opening_hours']);
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude']);
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude']);

    // Upload Logo
    $logo_path = $profile['logo_path'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $target_dir = "assets/images/";
        if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $new_filename = "logo_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
            // Hapus logo lama jika bukan default dan file ada
            if ($logo_path != 'assets/images/logo-texcer.png' && file_exists($logo_path)) {
                unlink($logo_path);
            }
            $logo_path = $target_file;
        }
    }

    // Upload Cover
    $cover_path = $profile['cover_path'];
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
        $target_dir = "assets/images/";
        if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $file_extension = pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION);
        $new_filename = "cover_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['cover']['tmp_name'], $target_file)) {
            // Hapus cover lama jika bukan default dan file ada
            if ($cover_path != 'assets/images/cover-texcer.jpg' && file_exists($cover_path)) {
                unlink($cover_path);
            }
            $cover_path = $target_file;
        }
    }

    // Update Database
    $sql = "UPDATE store_profile SET 
            store_name='$store_name', 
            tagline='$tagline', 
            description='$description', 
            logo_path='$logo_path', 
            cover_path='$cover_path', 
            address='$address', 
            whatsapp='$whatsapp', 
            email='$email', 
            instagram='$instagram', 
            tiktok='$tiktok', 
            opening_hours='$opening_hours', 
            latitude='$latitude', 
            longitude='$longitude' 
            WHERE id=1";

    if (mysqli_query($conn, $sql)) {
        $msg = "✅ Profil toko berhasil diperbarui!";
        $msgType = "success";
        // Refresh data untuk preview langsung
        $profile_query = mysqli_query($conn, "SELECT * FROM store_profile WHERE id=1 LIMIT 1");
        $profile = mysqli_fetch_assoc($profile_query);
    } else {
        $msg = "❌ Gagal memperbarui profil: " . mysqli_error($conn);
        $msgType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil Toko - Texcer Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* RESET & BASE */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f7f3ee; /* Warna background dashboard */
            color: #1a0f00;
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR STYLES (Sama seperti dashboard lain) */
        .sidebar {
            width: 250px;
            background: #3D2914; /* Coklat Tua */
            color: white;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100%;
            left: 0;
            top: 0;
            z-index: 100;
            transition: all 0.3s;
        }
        .sidebar-header {
            padding: 20px;
            font-size: 1.2rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            flex-grow: 1;
        }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        .sidebar-menu li a:hover, .sidebar-menu li a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 4px solid #D4A574; /* Aksen Emas */
        }
        .sidebar-menu li a i {
            width: 20px;
            text-align: center;
        }

        /* MAIN CONTENT WRAPPER */
        .main-content {
            margin-left: 250px;
            flex-grow: 1;
            padding: 28px;
        }

        /* CARD & FORM STYLES */
        .wrap { max-width: 1100px; margin: 0 auto; }
        
        h2.page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #3D2914;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            border: 1px solid #ede8e0;
        }

        .card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #8B6F4E; /* Coklat Primer */
            margin-bottom: 20px;
            border-bottom: 1px solid #f0ebe3;
            padding-bottom: 10px;
        }

        .form-group { margin-bottom: 16px; }
        
        .form-label {
            font-weight: 600;
            color: #3D2914;
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #E8DDD4;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            background: #fafaf8;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #8B6F4E;
            background: #fff;
        }

        textarea.form-control { resize: vertical; min-height: 100px; }

        .btn-primary {
            background: #8B6F4E;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover { background: #6B5637; }

        /* Image Preview Styles */
        .preview-container {
            margin-bottom: 10px;
            overflow: hidden;
            border-radius: 8px;
            border: 1px solid #ddd;
            background: #f9f9f9;
        }
        .preview-cover {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }
        .preview-img {
            max-width: 150px;
            max-height: 150px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        /* Responsive Sidebar */
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header span, .sidebar-menu li a span { display: none; }
            .main-content { margin-left: 70px; padding: 15px; }
            .sidebar-menu li a { justify-content: center; padding: 15px 0; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-pepper-hot"></i>
        <span>Texcer Admin</span>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
        <li><a href="orders.php"><i class="fas fa-shopping-bag"></i><span>Pesanan</span></a></li>
        <li><a href="products.php"><i class="fas fa-utensils"></i><span>Produk</span></a></li>
        <li><a href="categories.php"><i class="fas fa-tags"></i><span>Kategori</span></a></li>
        <li><a href="customers.php"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
        <li><a href="manage-images.php"><i class="fas fa-images"></i><span>Kelola Gambar</span></a></li>
        <li><a href="admin_topping.php"><i class="fas fa-pepper-hot"></i><span>Topping & Level</span></a></li>
        <li><a href="admin_store_profile.php" class="active"><i class="fas fa-store"></i><span>Profil Toko</span></a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
        <li style="margin-top: auto;"><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i><span>Lihat Toko</span></a></li>
    </ul>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="wrap">
        
        <h2 class="page-title">
            <i class="fas fa-pencil-alt" style="color: #8B6F4E;"></i> Edit Profil Toko
        </h2>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <!-- Bagian Foto -->
            <div class="card">
                <h3>📸 Foto Toko</h3>
                
                <div class="form-group">
                    <label class="form-label">Foto Cover (Banner Atas)</label>
                    <div class="preview-container">
                        <img src="<?= htmlspecialchars($profile['cover_path']) ?>" class="preview-cover" alt="Cover Preview" onerror="this.src='https://via.placeholder.com/1200x400?text=No+Cover'">
                    </div>
                    <input type="file" name="cover" class="form-control" accept="image/*">
                    <small style="color: #666; font-size: 0.8rem;">Rekomendasi ukuran: 1200x400 px</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Logo Toko</label>
                    <div class="preview-container" style="padding: 10px;">
                        <img src="<?= htmlspecialchars($profile['logo_path']) ?>" class="preview-img" alt="Logo Preview" onerror="this.src='https://via.placeholder.com/150?text=No+Logo'">
                    </div>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    <small style="color: #666; font-size: 0.8rem;">Rekomendasi ukuran: 200x200 px (Persegi)</small>
                </div>
            </div>

            <!-- Bagian Informasi Dasar -->
            <div class="card">
                <h3>ℹ️ Informasi Dasar</h3>
                
                <div class="form-group">
                    <label class="form-label">Nama Toko</label>
                    <input type="text" name="store_name" class="form-control" value="<?= htmlspecialchars($profile['store_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Tagline / Slogan</label>
                    <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars($profile['tagline']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi Toko</label>
                    <textarea name="description" class="form-control"><?= htmlspecialchars($profile['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Tahun Berdiri</label>
                    <input type="number" name="established_year" class="form-control" value="<?= htmlspecialchars($profile['established_year']) ?>">
                </div>
            </div>

            <!-- Bagian Lokasi & Kontak -->
            <div class="card">
                <h3>📍 Lokasi & Kontak</h3>
                
                <div class="form-group">
                    <label class="form-label">Alamat Lengkap</label>
                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($profile['address']) ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Nomor WhatsApp</label>
                        <input type="text" name="whatsapp" class="form-control" value="<?= htmlspecialchars($profile['whatsapp']) ?>" placeholder="6281234567890">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Jam Buka</label>
                    <input type="text" name="opening_hours" class="form-control" value="<?= htmlspecialchars($profile['opening_hours']) ?>" placeholder="Senin - Minggu: 11:00 - 20:00">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Latitude (Google Maps)</label>
                        <input type="text" name="latitude" class="form-control" value="<?= htmlspecialchars($profile['latitude']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude (Google Maps)</label>
                        <input type="text" name="longitude" class="form-control" value="<?= htmlspecialchars($profile['longitude']) ?>">
                    </div>
                </div>
            </div>

            <!-- Bagian Sosial Media -->
            <div class="card">
                <h3>📱 Sosial Media</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Username Instagram</label>
                        <input type="text" name="instagram" class="form-control" value="<?= htmlspecialchars($profile['instagram']) ?>" placeholder="texcer.hot">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username TikTok</label>
                        <input type="text" name="tiktok" class="form-control" value="<?= htmlspecialchars($profile['tiktok']) ?>" placeholder="texcer.hot">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
        </form>
    </div>
</div>

</body>
</html>