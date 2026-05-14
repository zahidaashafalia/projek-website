<?php
// Fungsi buat notifikasi
function createNotification($conn, $user_phone, $order_id, $title, $message, $type = 'order'){
    $user_phone = mysqli_real_escape_string($conn, $user_phone);
    $order_id = (int)$order_id;
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $type = mysqli_real_escape_string($conn, $type);
    
    $sql = "INSERT INTO notifications (user_phone, order_id, title, message, type) 
            VALUES ('$user_phone', '$order_id', '$title', '$message', '$type')";
    
    return mysqli_query($conn, $sql);
}

// Notifikasi saat pesanan dibuat
function notifyOrderCreated($conn, $user_phone, $order_id, $order_number){
    createNotification(
        $conn,
        $user_phone,
        $order_id,
        "Pesanan dibuat",
        "Pesanan Anda {$order_number} sudah masuk. Terima kasih sudah belanja di platform kami!"
    );
}

// Notifikasi pesanan dikirim
function notifyOrderShipped($conn, $user_phone, $order_id, $resi_number, $courier = 'J&T Express'){
    createNotification(
        $conn,
        $user_phone,
        $order_id,
        "Pesanan dikirim",
        "Paket dengan nomor resi {$resi_number} sudah dikirim dan akan diantarkan oleh {$courier}."
    );
}

// Notifikasi paket diantar
function notifyOrderDelivered($conn, $user_phone, $order_id, $resi_number){
    createNotification(
        $conn,
        $user_phone,
        $order_id,
        "Paket diantar",
        "Paket {$resi_number} Anda diantarkan."
    );
}

// Notifikasi COD
function notifyCODPayment($conn, $user_phone, $order_id, $total, $resi_number){
    createNotification(
        $conn,
        $user_phone,
        $order_id,
        "Paket Anda dengan metode bayar di tempat",
        "Siapkan dana sebesar Rp" . number_format($total, 0, ',', '.') . " untuk membayar pesanan dengan nomor resi {$resi_number} yang akan diantarkan oleh J&T Express."
    );
}
?>