<?php
session_start();
include 'config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Handle upload gambar/video
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])){
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $display_order = (int)$_POST['display_order'];
    $video_url = mysqli_real_escape_string($conn, $_POST['video_url'] ?? '');
    $media_type = !empty($video_url) ? 'video' : 'image';
    
    $upload_dir = "uploads/site-images/";
    if(!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $image_path = null;
    
    // Jika upload file gambar
    if(!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0){
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = time() . '_' . uniqid() . '.' . $file_ext;
        $image_path = $upload_dir . $file_name;
        
        if(!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)){
            $image_path = null;
        }
    }
    
    if($image_path || $video_url){
        mysqli_query($conn, "INSERT INTO site_images (`section`, `image_path`, `video_url`, `title`, `display_order`, `media_type`) 
                    VALUES ('$section', '$image_path', '$video_url', '$title', $display_order, '$media_type')");
        $_SESSION['success'] = "✅ Gambar/Video berhasil ditambahkan";
    } else {
        $_SESSION['error'] = "❌ Upload gambar atau masukkan URL video";
    }
    
    header("Location: manage-images.php");
    exit;
}

// Handle delete
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $img = mysqli_fetch_assoc(mysqli_query($conn, "SELECT `image_path` FROM site_images WHERE `id`=$id"));
    if($img && $img['image_path'] && file_exists($img['image_path'])){
        unlink($img['image_path']);
    }
    mysqli_query($conn, "DELETE FROM site_images WHERE id=$id");
    $_SESSION['success'] = "✅ Gambar/Video dihapus";
    header("Location: manage-images.php");
    exit;
}

// Handle Edit
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])){
    $edit_id = (int)$_POST['edit_id'];
    $edit_title = mysqli_real_escape_string($conn, $_POST['edit_title']);
    $edit_order = (int)$_POST['edit_display_order'];
    $edit_video_url = mysqli_real_escape_string($conn, $_POST['edit_video_url'] ?? '');
    
    $upload_dir = "uploads/site-images/";
    if(!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $update_fields = [];
    $update_fields[] = "`title` = '$edit_title'";
    $update_fields[] = "`display_order` = $edit_order";
    
    if(!empty($edit_video_url)){
        $update_fields[] = "`video_url` = '$edit_video_url'";
        $update_fields[] = "`media_type` = 'video'";
    }
    
    // Jika user upload gambar baru
    if(!empty($_FILES['edit_image']['name']) && $_FILES['edit_image']['error'] === 0){
        $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT `image_path` FROM site_images WHERE `id` = $edit_id"));
        if($old && $old['image_path'] && file_exists($old['image_path'])){
            unlink($old['image_path']);
        }
        
        $ext = pathinfo($_FILES['edit_image']['name'], PATHINFO_EXTENSION);
        $new_path = $upload_dir . time() . '_' . uniqid() . '.' . $ext;
        
        if(move_uploaded_file($_FILES['edit_image']['tmp_name'], $new_path)){
            $update_fields[] = "`image_path` = '$new_path'";
            $update_fields[] = "`media_type` = 'image'";
        }
    }
    
    $update_sql = "UPDATE site_images SET " . implode(", ", $update_fields) . " WHERE `id` = $edit_id";
    
    if(mysqli_query($conn, $update_sql)){
        $_SESSION['success'] = "✅ Gambar/Video berhasil diupdate";
    } else {
        $_SESSION['error'] = "❌ Error: " . mysqli_error($conn);
    }
    
    header("Location: manage-images.php");
    exit;
}

// Get all images
$hero_images = mysqli_query($conn, "SELECT * FROM site_images WHERE section='hero' ORDER BY display_order");
$testimonial_images = mysqli_query($conn, "SELECT * FROM site_images WHERE section='testimonial' ORDER BY display_order");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Gambar & Video - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #8B6F4E; --bg-cream: #FDF8F3; --text-dark: #3D2914; --border: #E8DDD4; }
        body { background: #f5f5f5; font-family: 'Segoe UI', sans-serif; }
        .sidebar { width: 260px; background: white; min-height: 100vh; position: fixed; left: 0; border-right: 1px solid var(--border); }
        .sidebar-logo { font-size: 1.5rem; font-weight: 800; color: var(--primary); padding: 12px 16px; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { list-style: none; padding: 0 8px; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-gray); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: var(--bg-cream); color: var(--primary); }
        .main-content { margin-left: 260px; padding: 24px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .btn-primary { background: var(--primary); border: none; }
        .image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .image-card { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: white; }
        .image-card img, .image-card video { width: 100%; height: 200px; object-fit: cover; }
        .image-card .card-body { padding: 15px; }
        .video-badge { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.8rem; }
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
        <li><a href="customers.php"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
        <li><a href="manage-images.php"><i class="fas fa-images"></i><span>Kelola Gambar</span></a></li>
        <li><a href="admin_topping.php"><i class="fas fa-pepper-hot"></i><span>Topping & Level</span></a></li>
        <li><a href="admin_store_profile.php" class="active"><i class="fas fa-store"></i><span>Profil Toko</span></a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
    </ul>
</aside>

<main class="main-content">
    <h2><i class="fas fa-images me-2"></i>Kelola Gambar & Video Website</h2>
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Tambah Gambar/Video Baru</h5>
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-2">
                        <select name="section" class="form-select" required>
                            <option value="hero">Hero Slider</option>
                            <option value="testimonial">Testimonial</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="title" class="form-control" placeholder="Judul" required>
                    </div>
                    <div class="col-md-1">
                        <input type="number" name="display_order" class="form-control" placeholder="Urutan" value="0">
                    </div>
                    <div class="col-md-3">
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Upload gambar</small>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="video_url" class="form-control" placeholder="URL Video (opsional)">
                        <small class="text-muted">Atau link video</small>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-upload"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Hero Images -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-image me-2"></i>Hero Slider</h5>
        </div>
        <div class="card-body">
            <div class="image-grid">
                <?php 
                mysqli_data_seek($hero_images, 0);
                while($img = mysqli_fetch_assoc($hero_images)): 
                ?>
                <div class="image-card" style="position: relative;">
                    <?php if($img['media_type'] === 'video'): ?>
                        <span class="video-badge"><i class="fas fa-video"></i> Video</span>
                        <video controls>
                            <source src="<?= $img['video_url'] ?>" type="video/mp4">
                        </video>
                    <?php else: ?>
                        <img src="<?= $img['image_path'] ?>" alt="<?= $img['title'] ?>">
                    <?php endif; ?>
                    <div class="card-body">
                        <h6><?= $img['title'] ?></h6>
                        <small class="text-muted">Urutan: <?= $img['display_order'] ?></small>
                        <div class="mt-2 d-flex gap-2">
                            <button class="btn btn-sm btn-warning flex-fill" 
                                    onclick='openEditModal(<?= $img['id'] ?>, "<?= addslashes($img['title']) ?>", <?= $img['display_order'] ?>, "<?= $img['image_path'] ?>", "<?= $img['video_url'] ?? '' ?>")'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="?delete=<?= $img['id'] ?>" class="btn btn-sm btn-danger flex-fill" onclick="return confirm('Hapus?')">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Testimonial Images/Videos -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-video me-2"></i>Testimonial (Video/Gambar)</h5>
        </div>
        <div class="card-body">
            <div class="image-grid">
                <?php while($img = mysqli_fetch_assoc($testimonial_images)): ?>
                <div class="image-card" style="position: relative;">
                    <?php if($img['media_type'] === 'video' || !empty($img['video_url'])): ?>
                        <span class="video-badge"><i class="fas fa-video"></i> Video</span>
                        <video controls>
                            <source src="<?= $img['video_url'] ?>" type="video/mp4">
                            Browser Anda tidak support video tag.
                        </video>
                    <?php else: ?>
                        <img src="<?= $img['image_path'] ?>" alt="<?= $img['title'] ?>">
                    <?php endif; ?>
                    <div class="card-body">
                        <h6><?= $img['title'] ?></h6>
                        <small class="text-muted">Urutan: <?= $img['display_order'] ?></small>
                        <div class="mt-2 d-flex gap-2">
                            <button class="btn btn-sm btn-warning flex-fill" 
                                    onclick='openEditModal(<?= $img['id'] ?>, "<?= addslashes($img['title']) ?>", <?= $img['display_order'] ?>, "<?= $img['image_path'] ?>", "<?= $img['video_url'] ?? '' ?>")'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="?delete=<?= $img['id'] ?>" class="btn btn-sm btn-danger flex-fill" onclick="return confirm('Hapus?')">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</main>

<!-- Modal Edit -->
<div class="modal fade" id="editImageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">✏️ Edit Gambar/Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Judul</label>
                        <input type="text" name="edit_title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Urutan Tampil</label>
                        <input type="number" name="edit_display_order" id="edit_display_order" class="form-control" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Preview Saat Ini</label>
                        <div class="mb-2" id="edit_current_container">
                            <img id="edit_current_preview" src="" style="width: 100%; height: 180px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <label class="form-label fw-semibold">Ganti Gambar (Opsional)</label>
                        <input type="file" name="edit_image" class="form-control" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL Video (Opsional)</label>
                        <input type="text" name="edit_video_url" id="edit_video_url" class="form-control" placeholder="https://example.com/video.mp4">
                        <small class="text-muted">Isi jika ingin menggunakan video. Kosongkan jika hanya gambar.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(id, title, order, imageSrc, videoUrl) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_display_order').value = order;
    document.getElementById('edit_video_url').value = videoUrl || '';
    
    const container = document.getElementById('edit_current_container');
    const preview = document.getElementById('edit_current_preview');
    
    if(videoUrl){
        container.innerHTML = `<video id="edit_current_preview" controls style="width: 100%; height: 180px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;"><source src="${videoUrl}" type="video/mp4"></video>`;
    } else {
        preview.src = imageSrc;
        preview.style.display = 'block';
    }
    
    const modalEl = document.getElementById('editImageModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
}
</script>
</body>
</html>