<?php
// api_topping.php - versi mysqli
// Letakkan di root project: localhost/texcer2/api_topping.php

require_once 'config.php'; // $conn sudah tersedia dari sini

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ------------------------------------------
    // GET: Ambil topping & level untuk 1 produk
    // Contoh: api_topping.php?action=get&product_id=5
    // ------------------------------------------
    case 'get':
        $product_id = (int)($_GET['product_id'] ?? 0);
        if (!$product_id) {
            echo json_encode(['error' => 'product_id wajib diisi']);
            exit;
        }

        // Ambil data produk
        $result  = mysqli_query($conn, "SELECT id, name, price, image FROM products WHERE id = $product_id LIMIT 1");
        $product = mysqli_fetch_assoc($result);

        if (!$product) {
            echo json_encode(['error' => 'Produk tidak ditemukan']);
            exit;
        }

        // Ambil topping yang tersedia untuk produk ini
        $result   = mysqli_query($conn, "
            SELECT t.id, t.name, t.price
            FROM toppings t
            INNER JOIN product_toppings pt ON pt.topping_id = t.id
            WHERE pt.product_id = $product_id AND t.is_active = 1
            ORDER BY t.name ASC
        ");
        $toppings = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $toppings[] = $row;
        }

        // Ambil level yang tersedia untuk produk ini
        $result = mysqli_query($conn, "
            SELECT l.id, l.name, l.price
            FROM levels l
            INNER JOIN product_levels pl ON pl.level_id = l.id
            WHERE pl.product_id = $product_id AND l.is_active = 1
            ORDER BY l.sort_order ASC
        ");
        $levels = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $levels[] = $row;
        }

        echo json_encode([
            'success'  => true,
            'product'  => $product,
            'toppings' => $toppings,
            'levels'   => $levels,
        ]);
        break;

    // ------------------------------------------
    // POST: Tambah ke keranjang
    // ------------------------------------------
    case 'add_cart':
        $product_id  = (int)($_POST['product_id'] ?? 0);
        $qty         = max(1, (int)($_POST['qty'] ?? 1));
        $level_id    = ($_POST['level_id'] !== '') ? (int)$_POST['level_id'] : null;
        $topping_ids = isset($_POST['topping_ids']) ? json_decode($_POST['topping_ids'], true) : [];

        if (!$product_id) {
            echo json_encode(['error' => 'product_id wajib']);
            exit;
        }

        // Ambil harga dasar produk
        $result  = mysqli_query($conn, "SELECT price, name FROM products WHERE id = $product_id LIMIT 1");
        $product = mysqli_fetch_assoc($result);
        if (!$product) {
            echo json_encode(['error' => 'Produk tidak ditemukan']);
            exit;
        }

        $base_price   = (int)$product['price'];
        $product_name = $product['name'];

        // Hitung total harga topping
        $topping_price = 0;
        if (!empty($topping_ids)) {
            $ids_str = implode(',', array_map('intval', $topping_ids));
            $result  = mysqli_query($conn, "SELECT SUM(price) as total FROM toppings WHERE id IN ($ids_str) AND is_active = 1");
            $row     = mysqli_fetch_assoc($result);
            $topping_price = (int)($row['total'] ?? 0);
        }

        // Hitung harga level
        $level_price = 0;
        $level_name  = '';
        if ($level_id !== null) {
            $result = mysqli_query($conn, "SELECT price, name FROM levels WHERE id = $level_id AND is_active = 1 LIMIT 1");
            $lv     = mysqli_fetch_assoc($result);
            if ($lv) {
                $level_price = (int)$lv['price'];
                $level_name  = $lv['name'];
            }
        }

        $total_price    = ($base_price + $topping_price + $level_price) * $qty;
        $toppings_json  = mysqli_real_escape_string($conn, json_encode($topping_ids));
        $product_name_e = mysqli_real_escape_string($conn, $product_name);
        $level_name_e   = mysqli_real_escape_string($conn, $level_name);
        $level_id_val   = $level_id !== null ? $level_id : 'NULL';

        // Cek apakah tabel cart_items ada
        $check = mysqli_query($conn, "SHOW TABLES LIKE 'cart_items'");
        if (mysqli_num_rows($check) === 0) {
            // Tabel belum ada, buat dulu
            mysqli_query($conn, "
                CREATE TABLE IF NOT EXISTS `cart_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `session_id` VARCHAR(100) NOT NULL,
                    `product_id` INT NOT NULL,
                    `product_name` VARCHAR(255) NOT NULL,
                    `qty` INT NOT NULL DEFAULT 1,
                    `base_price` INT NOT NULL,
                    `topping_price` INT NOT NULL DEFAULT 0,
                    `level_price` INT NOT NULL DEFAULT 0,
                    `total_price` INT NOT NULL,
                    `selected_toppings` TEXT NULL,
                    `selected_level_id` INT NULL,
                    `level_name` VARCHAR(100) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        // Simpan ke cart_items
        if (session_status() === PHP_SESSION_NONE) session_start();
        $session_id = mysqli_real_escape_string($conn, session_id());

        mysqli_query($conn, "
            INSERT INTO cart_items 
            (session_id, product_id, product_name, qty, base_price, topping_price, level_price, total_price, selected_toppings, selected_level_id, level_name)
            VALUES 
            ('$session_id', $product_id, '$product_name_e', $qty, $base_price, $topping_price, $level_price, $total_price, '$toppings_json', $level_id_val, '$level_name_e')
        ");

        if (mysqli_error($conn)) {
            echo json_encode(['error' => 'Gagal menyimpan: ' . mysqli_error($conn)]);
            exit;
        }

        echo json_encode([
            'success'     => true,
            'message'     => 'Berhasil ditambahkan ke keranjang',
            'total_price' => $total_price,
        ]);
        break;

    default:
        echo json_encode(['error' => 'Action tidak dikenali']);
}