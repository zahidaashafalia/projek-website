<?php 
include 'config.php'; 

// Set phone session jika belum ada
if(!isset($_SESSION['user_phone']) && !empty($_GET['phone'])){
    $_SESSION['user_phone'] = $_GET['phone'];
}

$phone = $_SESSION['user_phone'] ?? '';

// --- LOGIKA CRUD ---
if(isset($_POST['save_address'])){
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone_in = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $province = mysqli_real_escape_string($conn, $_POST['province']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    if(empty($phone)){
        $_SESSION['user_phone'] = $phone_in;
        $phone = $phone_in;
    }
    
    if($is_default){
        mysqli_query($conn, "UPDATE addresses SET is_default = 0 WHERE customer_phone = '$phone'");
    }
    
    if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])){
        $id = $_POST['edit_id'];
        mysqli_query($conn, "UPDATE addresses SET customer_name='$name', full_address='$address', city='$city', province='$province', is_default='$is_default' WHERE id='$id' AND customer_phone='$phone'");
    } else {
        mysqli_query($conn, "INSERT INTO addresses (customer_name, customer_phone, full_address, city, province, is_default) VALUES ('$name', '$phone', '$address', '$city', '$province', '$is_default')");
    }
    header("Location: alamat.php");
    exit;
}

if(isset($_GET['delete'])){
    $id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM addresses WHERE id='$id' AND customer_phone='$phone'");
    header("Location: alamat.php");
    exit;
}

if(isset($_GET['set_default'])){
    $id = $_GET['set_default'];
    mysqli_query($conn, "UPDATE addresses SET is_default = 0 WHERE customer_phone = '$phone'");
    mysqli_query($conn, "UPDATE addresses SET is_default = 1 WHERE id='$id' AND customer_phone='$phone'");
    header("Location: alamat.php");
    exit;
}

// Ambil data alamat
$addresses = mysqli_query($conn, "SELECT * FROM addresses WHERE customer_phone = '$phone' ORDER BY is_default DESC, id DESC");

// Cek jika sedang edit
$edit_data = null;
if(isset($_GET['edit'])){
    $edit_id = $_GET['edit'];
    $edit_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM addresses WHERE id='$edit_id' AND customer_phone='$phone'"));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alamat Anda - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f8f9fa; color: #333; font-family: 'Segoe UI', sans-serif; padding-bottom: 20px; }
        
        .page-header { background: white; padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 15px; position: sticky; top: 0; z-index: 100; }
        .page-header h3 { color: #333; font-size: 1.2rem; margin: 0; font-weight: 700; }
        .back-btn { color: #333; font-size: 1.2rem; text-decoration: none; }
        
        .add-btn { display: flex; align-items: center; gap: 10px; padding: 15px 20px; background: white; border-bottom: 1px solid #eee; cursor: pointer; color: #666; text-decoration: none; }
        .add-btn:hover { background: #f5f5f5; }
        
        .addr-card { background: white; margin: 10px 15px; padding: 15px; border-radius: 8px; border: 1px solid #eee; position: relative; }
        .addr-card.default { border-left: 4px solid #ff6600; }
        
        .addr-name { font-weight: 700; font-size: 1rem; color: #333; display: flex; justify-content: space-between; align-items: center; }
        .addr-phone { color: #666; font-size: 0.9rem; margin: 5px 0; }
        .addr-detail { color: #555; font-size: 0.9rem; line-height: 1.4; margin-bottom: 8px; }
        .addr-city { color: #888; font-size: 0.85rem; }
        
        .badge-default { background: #fff3cd; color: #856404; font-size: 0.7rem; padding: 3px 8px; border-radius: 4px; margin-top: 8px; display: inline-block; }
        
        .action-btns { position: absolute; top: 15px; right: 15px; display: flex; gap: 10px; }
        .btn-edit { color: #ff6600; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .btn-delete { color: #dc3545; text-decoration: none; font-size: 0.9rem; }
        .btn-set-default { color: #28a745; text-decoration: none; font-size: 0.8rem; margin-top: 5px; display: inline-block; }
        
        .form-control { background: #f5f5f5; border: 1px solid #ddd; border-radius: 8px; padding: 10px; color: #333; width: 100%; margin-bottom: 10px; }
        .form-control:focus { outline: none; border-color: #ff6600; }
        .btn-save { background: #ff6600; color: white; border: none; border-radius: 8px; padding: 12px; font-weight: 700; width: 100%; cursor: pointer; }
        
        .empty-state { text-align: center; padding: 40px 20px; color: #888; }
    </style>
</head>
<body>

<div class="page-header">
    <a href="checkout.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
    <h3>Alamat Anda</h3>
</div>

<?php if(empty($phone)): ?>
    <div class="container mt-4">
        <div class="card p-4">
            <h5>Masukkan Nomor HP Anda</h5>
            <p class="text-muted small">Nomor HP akan digunakan untuk menyimpan alamat Anda.</p>
            <form method="GET">
                <input type="tel" name="phone" class="form-control" placeholder="Contoh: 08123456789" required>
                <button type="submit" class="btn-save mt-2">Lanjutkan</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <a href="#" class="add-btn" data-bs-toggle="modal" data-bs-target="#addressModal">
        <i class="fas fa-plus-circle"></i>
        <span>Tambah alamat</span>
        <i class="fas fa-chevron-right ms-auto"></i>
    </a>

    <?php if(mysqli_num_rows($addresses) > 0): ?>
        <?php while($addr = mysqli_fetch_assoc($addresses)): ?>
        <div class="addr-card <?= $addr['is_default'] ? 'default' : '' ?>">
            <div class="action-btns">
                <a href="?edit=<?= $addr['id'] ?>" class="btn-edit">Edit</a>
                <a href="?delete=<?= $addr['id'] ?>" class="btn-delete" onclick="return confirm('Hapus alamat ini?')"><i class="fas fa-trash"></i></a>
            </div>
            <div class="addr-name">
                <?= htmlspecialchars($addr['customer_name']) ?>
            </div>
            <div class="addr-phone">(+62)<?= substr($addr['customer_phone'], -10) ?></div>
            <div class="addr-detail"><?= nl2br(htmlspecialchars($addr['full_address'])) ?></div>
            <div class="addr-city"><?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['province']) ?></div>
            
            <?php if($addr['is_default']): ?>
                <span class="badge-default">Default</span>
            <?php else: ?>
                <a href="?set_default=<?= $addr['id'] ?>" class="btn-set-default">Jadikan Default</a>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-map-marker-alt fa-3x mb-3"></i>
            <p>Belum ada alamat tersimpan.</p>
        </div>
    <?php endif; ?>

    <!-- Modal Tambah/Edit Alamat -->
    <div class="modal fade" id="addressModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $edit_data ? 'Edit Alamat' : 'Tambah Alamat Baru' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <?php if($edit_data): ?>
                            <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
                            <input type="hidden" name="phone" value="<?= $edit_data['customer_phone'] ?>">
                        <?php else: ?>
                            <input type="tel" name="phone" class="form-control" placeholder="Nomor Telepon" value="<?= $phone ?>" required>
                        <?php endif; ?>
                        
                        <input type="text" name="name" class="form-control" placeholder="Nama Penerima" value="<?= $edit_data['customer_name'] ?? '' ?>" required>
                        <textarea name="address" class="form-control" placeholder="Alamat Lengkap (Jalan, RT/RW, Patokan)" rows="3" required><?= $edit_data['full_address'] ?? '' ?></textarea>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <input type="text" name="city" class="form-control" placeholder="Kota/Kabupaten" value="<?= $edit_data['city'] ?? '' ?>" required>
                            <input type="text" name="province" class="form-control" placeholder="Provinsi" value="<?= $edit_data['province'] ?? '' ?>" required>
                        </div>
                        
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_default" id="isDefault" <?= ($edit_data['is_default'] ?? false) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isDefault">Jadikan alamat utama</label>
                        </div>

                        <button type="submit" name="save_address" class="btn-save mt-3"><?= $edit_data ? 'Simpan Perubahan' : 'Simpan Alamat' ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto open modal if editing
    <?php if($edit_data): ?>
        document.addEventListener('DOMContentLoaded', () => {
            new bootstrap.Modal(document.getElementById('addressModal')).show();
        });
    <?php endif; ?>
</script>
</body>
</html>