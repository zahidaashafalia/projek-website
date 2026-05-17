<?php
include '../config.php';
include '../functions.php';
checkAdmin(); // Pastikan hanya admin yang bisa akses

// Handle update profile
if(isset($_POST['update_profile'])){
    $store_name = mysqli_real_escape_string($conn, $_POST['store_name']);
    $tagline = mysqli_real_escape_string($conn, $_POST['tagline']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $whatsapp = mysqli_real_escape_string($conn, $_POST['whatsapp']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $instagram = mysqli_real_escape_string($conn, $_POST['instagram']);
    $tiktok = mysqli_real_escape_string($conn, $_POST['tiktok']);
    $opening_hours = mysqli_real_escape_string($conn, $_POST['opening_hours']);
    
    // Upload logo & cover (opsional)
    $logo_path = $_POST['current_logo'];
    $cover_path = $_POST['current_cover'];
    
    if(!empty($_FILES['logo']['name'])){
        $logo_name = 'logo_'.time().'_'.preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['logo']['name']);
        move_uploaded_file($_FILES['logo']['tmp_name'], '../uploads/'.$logo_name);
        $logo_path = 'uploads/'.$logo_name;
    }
    
    if(!empty($_FILES['cover']['name'])){
        $cover_name = 'cover_'.time().'_'.preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['cover']['name']);
        move_uploaded_file($_FILES['cover']['tmp_name'], '../uploads/'.$cover_name);
        $cover_path = 'uploads/'.$cover_name;
    }
    
    mysqli_query($conn, "UPDATE store_profile SET 
        store_name = '$store_name',
        tagline = '$tagline',
        description = '$description',
        address = '$address',
        whatsapp = '$whatsapp',
        email = '$email',
        instagram = '$instagram',
        tiktok = '$tiktok',
        opening_hours = '$opening_hours',
        logo_path = '$logo_path',
        cover_path = '$cover_path'
        WHERE id = 1
    ");
    
    header("Location: profile.php?success=1");
    exit;
}

// Ambil data profil
$profile = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM store_profile WHERE id=1"));
?>
<!-- Form HTML untuk edit profil (singkat) -->
<form method="POST" enctype="multipart/form-data">
    <input type="text" name="store_name" value="<?= htmlspecialchars($profile['store_name']) ?>" placeholder="Nama Toko">
    <input type="text" name="tagline" value="<?= htmlspecialchars($profile['tagline']) ?>" placeholder="Tagline">
    <textarea name="description"><?= htmlspecialchars($profile['description']) ?></textarea>
    <!-- Tambahkan field lainnya... -->
    <input type="file" name="logo">
    <input type="file" name="cover">
    <input type="hidden" name="current_logo" value="<?= $profile['logo_path'] ?>">
    <input type="hidden" name="current_cover" value="<?= $profile['cover_path'] ?>">
    <button type="submit" name="update_profile">Simpan Perubahan</button>
</form>