<?php
// submit_review.php - FIX SESSION ISSUE
// ⚠️ session_start() HARUS di baris paling atas, sebelum apa pun!

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';

// Cek login - pastikan session user_phone ada
if(!isset($_SESSION['user_phone'])){
    // Debug: catat ke log jika perlu
    // error_log("Review submit: No session - phone: " . print_r($_SESSION, true));
    
    $_SESSION['review_error'] = "Silakan login terlebih dahulu.";
    header("Location: auth/login.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)$_POST['order_id'];
    $product_id = (int)$_POST['product_id'];
    $rating = (int)$_POST['rating'];
    $review_text = mysqli_real_escape_string($conn, trim($_POST['review_text']));
    $customer_phone = $_SESSION['user_phone'];
    $review_image = null;
    
    // 🔥 Handle upload gambar
    if(isset($_FILES['review_image']) && $_FILES['review_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['review_image']['name'], PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed) && $_FILES['review_image']['size'] <= 2 * 1024 * 1024) {
            $new_name = 'rev_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $upload_dir = 'uploads/reviews/';
            
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_name;
            
            if(move_uploaded_file($_FILES['review_image']['tmp_name'], $upload_path)) {
                $review_image = $upload_path;
            }
        }
    }
    
    // ✅ Insert ke database dengan prepared statement
    $stmt = $conn->prepare("INSERT INTO reviews (order_id, product_id, customer_phone, rating, review_text, review_image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiisss", $order_id, $product_id, $customer_phone, $rating, $review_text, $review_image);
    
    if($stmt->execute()) {
        $_SESSION['review_success'] = true;
        // ✅ Penting: Tutup statement sebelum redirect
        $stmt->close();
        
        // ✅ Flush output buffer agar session tersimpan
        if (ob_get_length()) ob_end_flush();
        session_write_close();
        
        header("Location: riwayat.php");
        exit;
    } else {
        $_SESSION['review_error'] = "Gagal mengirim ulasan: " . $stmt->error;
        $stmt->close();
        
        if (ob_get_length()) ob_end_flush();
        session_write_close();
        
        header("Location: riwayat.php");
        exit;
    }
}

// Jika akses langsung (bukan POST)
header("Location: index.php");
exit;
?>