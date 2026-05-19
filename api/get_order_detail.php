<?php
header('Content-Type: application/json');
include '../config.php'; // Sesuaikan path config.php Anda

$response = ['success' => false, 'message' => 'Invalid request'];

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Ambil data order utama
    $orderQuery = mysqli_query($conn, "SELECT * FROM orders WHERE id = $id LIMIT 1");
    
    if ($orderQuery && mysqli_num_rows($orderQuery) > 0) {
        $order = mysqli_fetch_assoc($orderQuery);
        
        // Ambil data item pesanan
        $itemsQuery = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = $id");
        $items = [];
        while ($item = mysqli_fetch_assoc($itemsQuery)) {
            $items[] = $item;
        }
        
        $response = [
            'success' => true,
            'order' => $order,
            'items' => $items
        ];
    } else {
        $response['message'] = 'Pesanan tidak ditemukan';
    }
} else {
    $response['message'] = 'ID pesanan tidak disediakan';
}

echo json_encode($response);
?>