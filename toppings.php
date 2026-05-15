<?php
session_start();
include 'config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

// Handle Add/Edit/Delete Topping
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(isset($_POST['add_topping'])){
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $price = (float)$_POST['price'];
        $display_order = (int)$_POST['display_order'];
        
        $upload_dir = "uploads/toppings/";
        if(!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $image = '';
        if(!empty($_FILES['image']['name'])){
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = $upload_dir . time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $image);
        }
        
        mysqli_query($conn, "INSERT INTO toppings (name, price, image, display_order) 
                            VALUES ('$name', $price, '$image', $display_order)");
    }
    
    if(isset($_POST['update_topping'])){
        $id = (int)$_POST['id'];
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $price = (float)$_POST['price'];
        $display_order = (int)$_POST['display_order'];
        
        $update_sql = "UPDATE toppings SET name='$name', price=$price, display_order=$display_order WHERE id=$id";
        
        if(!empty($_FILES['image']['name'])){
            $upload_dir = "uploads/toppings/";
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = $upload_dir . time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $image);
            $update_sql .= ", image='$image'";
        }
        
        mysqli_query($conn, $update_sql);
    }
    
    if(isset($_GET['delete'])){
        $id = (int)$_GET['delete'];
        mysqli_query($conn, "DELETE FROM toppings WHERE id=$id");
    }
}

$toppings = mysqli_query($conn, "SELECT * FROM toppings ORDER BY display_order");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Topping - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container mt-5">
    <h2><i class="fas fa-drumstick-bite"></i> Kelola Topping</h2>
    
    <!-- Form Add Topping -->
    <div class="card mt-4">
        <div class="card-body">
            <h5>Tambah Topping</h5>
            <form method="POST" enctype="multipart/form-data" class="row">
                <div class="col-md-3">
                    <input type="text" name="name" class="form-control" placeholder="Nama Topping" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="price" class="form-control" placeholder="Harga" value="0">
                </div>
                <div class="col-md-2">
                    <input type="number" name="display_order" class="form-control" placeholder="Urutan" value="0">
                </div>
                <div class="col-md-3">
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_topping" class="btn btn-primary w-100">
                        <i class="fas fa-plus"></i> Tambah
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- List Toppings -->
    <div class="card mt-4">
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Gambar</th>
                        <th>Nama</th>
                        <th>Harga</th>
                        <th>Urutan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($t = mysqli_fetch_assoc($toppings)): ?>
                    <tr>
                        <td>
                            <?php if($t['image']): ?>
                                <img src="<?= $t['image'] ?>" width="50" height="50" style="object-fit: cover;">
                            <?php endif; ?>
                        </td>
                        <td><?= $t['name'] ?></td>
                        <td>Rp<?= number_format($t['price']) ?></td>
                        <td><?= $t['display_order'] ?></td>
                        <td>
                            <a href="?delete=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>