<?php
session_start();

// Jika sudah login, redirect ke index
if(isset($_SESSION['user_phone'])){
    header("Location: ../index.php");
    exit;
}

include '../config.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if(empty($name) || empty($phone) || empty($password)){
        $error = "Semua field wajib diisi!";
    } elseif(strlen($phone) < 10 || strlen($phone) > 13){
        $error = "Nomor telepon tidak valid!";
    } elseif(strlen($password) < 6){
        $error = "Password minimal 6 karakter!";
    } elseif($password !== $confirm_password){
        $error = "Password tidak cocok!";
    } else {
        // Cek apakah nomor sudah terdaftar
        $check = mysqli_query($conn, "SELECT phone FROM users WHERE phone = '$phone'");
        if(mysqli_num_rows($check) > 0){
            $error = "Nomor telepon sudah terdaftar!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert ke database
            $query = "INSERT INTO users (name, phone, password, role) VALUES ('$name', '$phone', '$hashed_password', 'customer')";
            
            if(mysqli_query($conn, $query)){
                $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
                header("Location: login.php");
                exit;
            } else {
                $error = "Terjadi kesalahan: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #8B6F4E;
            --primary-dark: #6B5637;
            --bg-cream: #FDF8F3;
        }
        body {
            background: var(--bg-cream);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .auth-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: var(--primary);
            font-weight: 700;
            font-size: 2rem;
        }
        .form-label {
            font-weight: 600;
            color: #3D2914;
            font-size: 0.9rem;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #E8DDD4;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: none;
        }
        .btn-register {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 700;
            width: 100%;
            margin-top: 20px;
        }
        .btn-register:hover {
            background: var(--primary-dark);
        }
        .auth-link {
            text-align: center;
            margin-top: 20px;
            color: #8B7355;
        }
        .auth-link a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="logo">
            <h1>🌶️ Texcer Hot</h1>
            <p class="text-muted">Daftar akun baru</p>
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="name" class="form-control" placeholder="Masukkan nama lengkap" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Nomor Telepon</label>
                <input type="tel" name="phone" class="form-control" placeholder="08123456789" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                <small class="text-muted">Format: 08xxxxxxxxxx</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password" required>
            </div>

            <button type="submit" class="btn btn-register">
                <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
            </button>
        </form>

        <div class="auth-link">
            Sudah punya akun? <a href="login.php">Login di sini</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>