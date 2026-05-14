<?php
include '../config.php';

// Jika sudah login, redirect sesuai role
if(isset($_SESSION['user_phone'])){
    if($_SESSION['role'] === 'admin'){
        header("Location: ../dashboard.php");
    } else {
        header("Location: ../index.php");
    }
    exit;
}

$error = '';

// Proses login
if(isset($_POST['login'])){
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    
    // Cek tabel users (pembeli)
    $result = mysqli_query($conn, "SELECT * FROM users WHERE phone = '$phone'");
    
    if($result && mysqli_num_rows($result) > 0){
        $user = mysqli_fetch_assoc($result);
        // Verifikasi password (gunakan password_verify jika pakai password_hash)
        if($password === $user['password']){ // ⚠️ Untuk production, gunakan password_hash() & password_verify()
            $_SESSION['user_phone'] = $user['phone'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = 'customer';
            $_SESSION['user_id'] = $user['id'];
            
            header("Location: ../index.php");
            exit;
        } else {
            $error = 'Password salah!';
        }
    } else {
        // Cek tabel admin
        $adminResult = mysqli_query($conn, "SELECT * FROM admins WHERE phone = '$phone' OR email = '$phone'");
        
        if($adminResult && mysqli_num_rows($adminResult) > 0){
            $admin = mysqli_fetch_assoc($adminResult);
            if($password === $admin['password']){ // ⚠️ Gunakan password_verify untuk production
                $_SESSION['user_phone'] = $admin['phone'];
                $_SESSION['user_name'] = $admin['name'];
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_id'] = $admin['id'];
                
                header("Location: ../dashboard.php");
                exit;
            } else {
                $error = 'Password salah!';
            }
        } else {
            $error = 'Nomor HP / Email tidak terdaftar!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #8B6F4E;
            --primary-dark: #6B5637;
            --bg-cream: #FDF8F3;
            --text-dark: #3D2914;
        }
        body {
            background: var(--bg-cream);
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 30px;
        }
        .logo i { margin-right: 8px; }
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
            border: 2px solid #E8DDD4;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 111, 78, 0.15);
        }
        .btn-login {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 111, 78, 0.4);
        }
        .error-alert {
            background: #FFF0F0;
            color: #DC2626;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .divider {
            text-align: center;
            color: #999;
            margin: 24px 0;
            position: relative;
        }
        .divider::before, .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #E8DDD4;
        }
        .divider::before { left: 0; }
        .divider::after { right: 0; }
        .admin-note {
            background: #F0F7FF;
            color: #1E40AF;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-top: 20px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .admin-note i { margin-top: 3px; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .back-link:hover { color: var(--primary); }
    </style>
</head>
<body>

<div class="login-card">
    <a href="../index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Kembali ke Beranda
    </a>
    
    <div class="logo">
        <i class="fas fa-pepper-hot"></i> Texcer Hot
    </div>
    
    <?php if($error): ?>
    <div class="error-alert">
        <i class="fas fa-exclamation-circle"></i>
        <?= $error ?>
    </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="mb-4">
            <label class="form-label">Nomor HP / Email</label>
            <input type="text" name="phone" class="form-control" placeholder="0812-3456-7890" required autofocus>
        </div>
        
        <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        
        <button type="submit" name="login" class="btn-login">
            <i class="fas fa-sign-in-alt me-2"></i> Masuk
        </button>
    </form>
    
    <div class="divider">atau</div>
    
    <div style="text-align: center;">
        <span style="color: #666; font-size: 0.9rem;">Belum punya akun? </span>
        <a href="register.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">Daftar sekarang</a>
    </div>
    
    <div class="admin-note">
        <i class="fas fa-shield-alt"></i>
        <div>
            <strong>Admin?</strong><br>
            Gunakan nomor/email admin untuk masuk ke dashboard.
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>