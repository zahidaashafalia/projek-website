<?php
session_start();
include 'config.php';

// Cek apakah admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: auth/login.php");
    exit;
}

// Handle delete order
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM orders WHERE id=$id");
    $_SESSION['success'] = "Pesanan berhasil dihapus";
    header("Location: orders.php");
    exit;
}

// Filter & Search
$where = ["1=1"];
if(isset($_GET['status']) && $_GET['status'] != ''){
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where[] = "status = '$status'";
}
if(isset($_GET['search']) && $_GET['search'] != ''){
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where[] = "(customer_name LIKE '%$search%' OR customer_phone LIKE '%$search%' OR id LIKE '%$search%')";
}
if(isset($_GET['date_from']) && $_GET['date_from'] != ''){
    $date_from = mysqli_real_escape_string($conn, $_GET['date_from']);
    $where[] = "DATE(created_at) >= '$date_from'";
}
if(isset($_GET['date_to']) && $_GET['date_to'] != ''){
    $date_to = mysqli_real_escape_string($conn, $_GET['date_to']);
    $where[] = "DATE(created_at) <= '$date_to'";
}

$whereSQL = implode(" AND ", $where);
$orders = mysqli_query($conn, "SELECT * FROM orders WHERE $whereSQL ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Texcer Hot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #8B6F4E;
            --primary-dark: #6B5637;
            --bg-cream: #FDF8F3;
            --text-dark: #3D2914;
            --text-gray: #8B7355;
            --border: #E8DDD4;
        }
        body { background: #f5f5f5; font-family: 'Segoe UI', sans-serif; }
        .sidebar {
            width: 260px;
            background: white;
            min-height: 100vh;
            padding: 24px 16px;
            position: fixed;
            left: 0;
            border-right: 1px solid var(--border);
        }
        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            padding: 12px 16px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-gray);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s;
            font-weight: 500;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: var(--bg-cream);
            color: var(--primary);
        }
        .sidebar-menu i { width: 20px; text-align: center; }
        .main-content { margin-left: 260px; padding: 24px; }
        
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: var(--primary-dark); }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .table img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending { background: #FFF7ED; color: #C2410C; }
        .status-processing { background: #FEF3C7; color: #92400E; }
        .status-shipped { background: #DBEAFE; color: #1E40AF; }
        .status-completed { background: #D1FAE5; color: #065F46; }
        .status-cancelled { background: #FEE2E2; color: #B91C1C; }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <i class="fas fa-pepper-hot"></i>
        <span>Texcer Hot</span>
    </div>
    <ul class="sidebar-menu">
    <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
    <li><a href="orders.php"><i class="fas fa-shopping-bag"></i><span>Pesanan</span></a></li>
    <li><a href="products.php"><i class="fas fa-utensils"></i><span>Produk</span></a></li>
    <li><a href="categories.php"><i class="fas fa-tags"></i><span>Kategori</span></a></li>
    <li><a href="customers.php" class="<?= ($currentPage ?? '') == 'customers.php' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
    
    <!-- ✅ MENU BARU: Kelola Gambar -->
    <li><a href="manage-images.php" class="<?= ($currentPage ?? '') == 'manage-images.php' ? 'active' : '' ?>"><i class="fas fa-images"></i><span>Kelola Gambar</span></a></li>
    
    <li><a href="settings.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
</ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-shopping-bag me-2"></i>Kelola Pesanan</h2>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Cari</label>
                <input type="text" name="search" class="form-control" placeholder="ID / Nama / No HP" value="<?= $_GET['search'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="Menunggu Konfirmasi" <?= ($_GET['status'] ?? '') == 'Menunggu Konfirmasi' ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                    <option value="Diproses" <?= ($_GET['status'] ?? '') == 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                    <option value="Dikemas" <?= ($_GET['status'] ?? '') == 'Dikemas' ? 'selected' : '' ?>>Dikemas</option>
                    <option value="Dikirim" <?= ($_GET['status'] ?? '') == 'Dikirim' ? 'selected' : '' ?>>Dikirim</option>
                    <option value="Selesai" <?= ($_GET['status'] ?? '') == 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="Dibatalkan" <?= ($_GET['status'] ?? '') == 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control" value="<?= $_GET['date_from'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control" value="<?= $_GET['date_to'] ?? '' ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>Filter
                </button>
                <a href="orders.php" class="btn btn-outline-secondary">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-body">
            <div style="overflow-x: auto;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pelanggan</th>
                            <th>Produk</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Resi</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($orders) > 0): ?>
                            <?php while($order = mysqli_fetch_assoc($orders)): 
                                $statusClass = match($order['status']){
                                    'Menunggu Konfirmasi', 'Pending' => 'status-pending',
                                    'Diproses', 'Dikemas' => 'status-processing',
                                    'Dikirim' => 'status-shipped',
                                    'Selesai' => 'status-completed',
                                    'Dibatalkan' => 'status-cancelled',
                                    default => ''
                                };
                            ?>
                            <tr>
                                <td><strong>#<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                                <td>
                                    <div><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($order['customer_phone'] ?? '') ?></small>
                                </td>
                                <td>
                                    <?php
                                    // Get order items
                                    $items = mysqli_query($conn, "SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = {$order['id']}");
                                    while($item = mysqli_fetch_assoc($items)){
                                        echo "<div>" . htmlspecialchars($item['name'] ?? 'Produk') . " (x{$item['quantity']})</div>";
                                    }
                                    ?>
                                </td>
                                <td><strong>Rp<?= number_format($order['total_price'] ?? 0, 0, ',', '.') ?></strong></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                                <td><?= htmlspecialchars($order['resi_number'] ?? '-') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info text-white" onclick="viewOrder(<?= $order['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if(!in_array($order['status'], ['Selesai', 'Dibatalkan'])): ?>
                                    <button class="btn btn-sm btn-warning" onclick="updateStatus(<?= $order['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <a href="?delete=<?= $order['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus pesanan ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    Tidak ada pesanan yang ditemukan
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal Update Status (sama seperti di dashboard) -->
<div class="modal fade" id="modalUpdateStatus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="update_status.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Status Pesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="update_order_id">
                    <div class="mb-3">
                        <label class="form-label">ID Pesanan</label>
                        <input type="text" class="form-control" id="update_order_id_display" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ubah Status Ke</label>
                        <select name="new_status" class="form-select" required>
                            <option value="">-- Pilih Status --</option>
                            <option value="Menunggu Konfirmasi">⏳ Menunggu Konfirmasi</option>
                            <option value="Diproses">🔄 Diproses</option>
                            <option value="Dikemas">📦 Dikemas</option>
                            <option value="Dikirim">🚚 Dikirim</option>
                            <option value="Selesai">✅ Selesai</option>
                            <option value="Dibatalkan">❌ Dibatalkan</option>
                        </select>
                    </div>
                    <div class="mb-3" id="resi_field" style="display: none;">
                        <label class="form-label">📮 Nomor Resi</label>
                        <input type="text" name="resi_number" class="form-control" placeholder="Contoh: JNE123456789">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewOrder(id) {
    alert('Detail pesanan #' + id + '\n\nFitur detail lengkap akan segera hadir!');
}

function updateStatus(id) {
    document.getElementById('update_order_id').value = id;
    document.getElementById('update_order_id_display').value = '#' + String(id).padStart(4, '0');
    document.getElementById('resi_field').style.display = 'none';
    document.querySelector('select[name="new_status"]').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('modalUpdateStatus'));
    modal.show();
}

document.querySelector('select[name="new_status"]')?.addEventListener('change', function(e){
    document.getElementById('resi_field').style.display = 
        e.target.value === 'Dikirim' ? 'block' : 'none';
});
</script>
</body>
</html>