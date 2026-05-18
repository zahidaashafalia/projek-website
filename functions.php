<?php
// functions.php

/**
 * Fungsi Notifikasi Pesanan Dibuat
 * Ini adalah placeholder. Anda bisa mengisinya dengan logika kirim WhatsApp/API nanti.
 */
function notifyOrderCreated($conn, $phone, $order_id, $order_number) {
    // Contoh: Catat log atau kirim API notification
    // $message = "Pesanan Baru #$order_number telah dibuat.";
    // kirimWhatsApp($phone, $message); 
    
    // Untuk saat ini, biarkan kosong agar tidak error
    return true;
}

/**
 * Fungsi Notifikasi Pesanan Dikirim (Jika dibutuhkan di admin)
 */
function notifyOrderShipped($conn, $phone, $order_id, $resi_number) {
    return true;
}

/**
 * Fungsi Notifikasi COD (Jika dibutuhkan di admin)
 */
function notifyCODPayment($conn, $phone, $order_id, $total, $resi_number) {
    return true;
}

/**
 * Fungsi Notifikasi Pesanan Selesai (Jika dibutuhkan di admin)
 */
function notifyOrderDelivered($conn, $phone, $order_id, $resi_number) {
    return true;
}
?>