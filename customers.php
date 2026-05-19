<?php
session_start();
include 'config.php';

$currentPage = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

// Cek kolom yang ada di tabel orders
$columns = mysqli_query($conn, "SHOW COLUMNS FROM orders");
$available_cols = [];
while($col = mysqli_fetch_assoc($columns)){
    $available_cols[] = $col['Field'];
}

$has_email = in_array('customer_email', $available_cols);
$has_address = in_array('customer_address', $available_cols);

// Search & Filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where = "customer_phone IS NOT NULL AND customer_phone != ''";
if($search){
    $where .= " AND (customer_name LIKE '%$search%' OR customer_phone LIKE '%$search%'";
    if($has_email) $where .= " OR customer_email LIKE '%$search%'";
    $where .= ")";
}

// Get all customers from orders
$customers = mysqli_query($conn, "
    SELECT 
        customer_name,
        customer_phone,
        " . ($has_email ? "customer_email," : "NULL as customer_email," ) . "
        " . ($has_address ? "customer_address," : "NULL as customer_address," ) . "
        COUNT(*) as total_orders,
        SUM(total_price) as total_spent,
        MIN(created_at) as first_order,
        MAX(created_at) as last_order
    FROM orders 
    WHERE $where
    GROUP BY customer_phone
    ORDER BY total_spent DESC
");

// Get total customers
$total_customers = mysqli_num_rows($customers);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pelanggan - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #8B6F4E; --primary-dark: #6B5637; --bg-cream: #FDF8F3; --text-dark: #3D2914; --text-gray: #8B7355; --border: #E8DDD4; }
        body { background: #f5f5f5; font-family: 'Segoe UI', sans-serif; }
        .sidebar { width: 260px; background: white; min-height: 100vh; position: fixed; left: 0; border-right: 1px solid var(--border); z-index: 1000; }
        .sidebar-logo { font-size: 1.5rem; font-weight: 800; color: var(--primary); padding: 12px 16px; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { list-style: none; padding: 0 8px; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-gray); text-decoration: none; border-radius: 12px; transition: all 0.2s; font-weight: 500; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: var(--bg-cream); color: var(--primary); }
        .sidebar-menu i { width: 20px; text-align: center; }
        .main-content { margin-left: 260px; padding: 24px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-card { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 20px; border-radius: 12px; margin-bottom: 24px; }
        .stat-card h3 { font-size: 2rem; font-weight: 800; margin: 0; }
        .stat-card p { margin: 0; opacity: 0.9; }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: var(--primary-dark); }
        .avatar { width: 50px; height: 50px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem; }
        @media (max-width: 768px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo"><i class="fas fa-pepper-hot"></i><span>Texcer Hot</span></div>
<ul class="sidebar-menu">
    <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
    <li><a href="orders.php"><i class="fas fa-shopping-bag"></i><span>Pesanan</span></a></li>
    <li><a href="products.php"><i class="fas fa-utensils"></i><span>Produk</span></a></li>
    <li><a href="categories.php"><i class="fas fa-tags"></i><span>Kategori</span></a></li>
    <li><a href="customers.php" class="<?= ($currentPage ?? '') == 'customers.php' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
    
    <!-- ✅ MENU BARU: Kelola Gambar -->
    <li><a href="manage-images.php" class="<?= ($currentPage ?? '') == 'manage-images.php' ? 'active' : '' ?>"><i class="fas fa-images"></i><span>Kelola Gambar</span></a></li>
    
    <li><a href="admin_topping.php" class="<?= ($currentPage ?? '') == 'admin_topping.php' ? 'active' : '' ?>">
    <i class="fas fa-pepper-hot"></i><span>Topping & Level</span>
</a></li>
<li>
    <a href="admin_store_profile.php" class="<?= ($currentPage == 'admin_store_profile.php') ? 'active' : '' ?>">
        <i class="fas fa-store"></i>
        <span>Profil Toko</span>
    </a>
</li>
    <li><a href="settings.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
</ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users me-2"></i>Data Pelanggan</h2>
    </div>

    <!-- Stats -->
    <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3><?= $total_customers ?></h3>
                <p>Total Pelanggan</p>
            </div>
            <i class="fas fa-users fa-3x opacity-50"></i>
        </div>
    </div>

    <!-- Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama atau nomor telepon..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Cari
                    </button>
                </div>
            </form>
            <?php if($search): ?>
                <a href="customers.php" class="btn btn-outline-secondary btn-sm mt-2">
                    <i class="fas fa-redo me-1"></i>Reset Pencarian
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Customers List -->
    <div class="card">
        <div class="card-body">
            <?php if($total_customers > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Pelanggan</th>
                                <th>Kontak</th>
                                <?php if($has_address): ?><th>Alamat</th><?php endif; ?>
                                <th>Total Pesanan</th>
                                <th>Total Belanja</th>
                                <th>Pesanan Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($cust = mysqli_fetch_assoc($customers)): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar">
                                            <?= strtoupper(substr($cust['customer_name'] ?: 'C', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($cust['customer_name'] ?: 'Tanpa Nama') ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><i class="fas fa-phone me-2 text-muted"></i><?= htmlspecialchars($cust['customer_phone']) ?></div>
                                    <?php if($has_email && $cust['customer_email']): ?>
                                    <div><i class="fas fa-envelope me-2 text-muted"></i><?= htmlspecialchars($cust['customer_email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if($has_address): ?>
                                <td><?= htmlspecialchars($cust['customer_address'] ?: '-') ?></td>
                                <?php endif; ?>
                                <td><span class="badge bg-primary"><?= $cust['total_orders'] ?> pesanan</span></td>
                                <td><strong>Rp<?= number_format($cust['total_spent'] ?? 0, 0, ',', '.') ?></strong></td>
                                <td><?= $cust['last_order'] ? date('d/m/Y H:i', strtotime($cust['last_order'])) : '-' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-users fa-3x mb-3 d-block"></i>
                    <p>Belum ada pelanggan</p>
                    <?php if($search): ?>
                        <a href="customers.php" class="btn btn-primary mt-2">Lihat Semua Pelanggan</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>