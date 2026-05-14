<?php
session_start();
include 'config.php';

// Cek apakah admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])){
    $order_id = (int)$_POST['order_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $resi_number = isset($_POST['resi_number']) ? mysqli_real_escape_string($conn, trim($_POST['resi_number'])) : '';
    
    // Build query
    $query = "UPDATE orders SET status = '$new_status'";
    
    // Tambah resi jika status = Dikirim
    if($new_status === 'Dikirim' && !empty($resi_number)){
        $query .= ", resi_number = '$resi_number'";
    }
    
    $query .= " WHERE id = $order_id";
    
    if(mysqli_query($conn, $query)){
        $_SESSION['success'] = "✅ Status pesanan #$order_id berhasil diubah menjadi <strong>$new_status</strong>";
    } else {
        $_SESSION['error'] = "❌ Gagal update: " . mysqli_error($conn);
    }
}

header("Location: dashboard.php");
exit;
?>