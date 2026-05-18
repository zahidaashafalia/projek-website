<?php
require_once 'config.php'; // Pastikan path config.php sesuai
$currentPage = basename($_SERVER['PHP_SELF']);

// Auto-buat tabel jika belum ada (Sama seperti sebelumnya)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `toppings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `price` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `levels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `price` INT NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `product_toppings` (
    `product_id` INT NOT NULL,
    `topping_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `topping_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `product_levels` (
    `product_id` INT NOT NULL,
    `level_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg     = '';
$msgType = 'success';

// ============================================================
// HANDLE FORM SUBMIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // --- Simpan Master Topping ---
    if ($act === 'save_topping') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
        $price  = (int)($_POST['price'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            $msg = 'Nama topping tidak boleh kosong!';
            $msgType = 'error';
        } else {
            if ($id > 0) {
                mysqli_query($conn, "UPDATE toppings SET name='$name', price=$price, is_active=$active WHERE id=$id");
                $msg = '✅ Topping berhasil diupdate';
            } else {
                mysqli_query($conn, "INSERT INTO toppings (name, price, is_active) VALUES ('$name', $price, $active)");
                $msg = '✅ Topping berhasil ditambahkan';
            }
        }
    }

    // --- Hapus Topping ---
    if ($act === 'del_topping') {
        $id = (int)($_POST['id'] ?? 0);
        mysqli_query($conn, "DELETE FROM toppings WHERE id=$id");
        mysqli_query($conn, "DELETE FROM product_toppings WHERE topping_id=$id");
        $msg = '🗑️ Topping berhasil dihapus';
    }

    // --- Simpan Master Level ---
    if ($act === 'save_level') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
        $price  = (int)($_POST['price'] ?? 0);
        $sort   = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            $msg = 'Nama level/varian tidak boleh kosong!';
            $msgType = 'error';
        } else {
            if ($id > 0) {
                mysqli_query($conn, "UPDATE levels SET name='$name', price=$price, sort_order=$sort, is_active=$active WHERE id=$id");
                $msg = '✅ Varian/Level berhasil diupdate';
            } else {
                mysqli_query($conn, "INSERT INTO levels (name, price, sort_order, is_active) VALUES ('$name', $price, $sort, $active)");
                $msg = '✅ Varian/Level berhasil ditambahkan';
            }
        }
    }

    // --- Hapus Level ---
    if ($act === 'del_level') {
        $id = (int)($_POST['id'] ?? 0);
        mysqli_query($conn, "DELETE FROM levels WHERE id=$id");
        mysqli_query($conn, "DELETE FROM product_levels WHERE level_id=$id");
        $msg = '🗑️ Varian/Level berhasil dihapus';
    }

    // --- Simpan Topping & Level per Produk ---
    if ($act === 'save_product_settings') {
        $pid = (int)($_POST['product_id'] ?? 0);
        if ($pid > 0) {
            // Reset dulu
            mysqli_query($conn, "DELETE FROM product_toppings WHERE product_id=$pid");
            mysqli_query($conn, "DELETE FROM product_levels WHERE product_id=$pid");

            // Insert topping yang dicentang
            if (!empty($_POST['topping_ids'])) {
                foreach ($_POST['topping_ids'] as $tid) {
                    $tid = (int)$tid;
                    mysqli_query($conn, "INSERT IGNORE INTO product_toppings (product_id, topping_id) VALUES ($pid, $tid)");
                }
            }
            // Insert level/varian yang dicentang
            if (!empty($_POST['level_ids'])) {
                foreach ($_POST['level_ids'] as $lid) {
                    $lid = (int)$lid;
                    mysqli_query($conn, "INSERT IGNORE INTO product_levels (product_id, level_id) VALUES ($pid, $lid)");
                }
            }
            $msg = '✅ Pengaturan varian & topping produk berhasil disimpan';
        }
    }

    // Redirect
    if ($msg) {
        $pid_redirect = (int)($_POST['product_id'] ?? $_GET['pid'] ?? 0);
        header("Location: admin_topping.php?pid=$pid_redirect&msg=" . urlencode($msg) . "&type=$msgType");
        exit;
    }
}

if (isset($_GET['msg'])) {
    $msg     = $_GET['msg'];
    $msgType = $_GET['type'] ?? 'success';
}

// ============================================================
// AMBIL DATA
// ============================================================
$allToppings = [];
$r = mysqli_query($conn, "SELECT * FROM toppings ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($r)) $allToppings[] = $row;

$allLevels = [];
// Urutkan berdasarkan sort_order
$r = mysqli_query($conn, "SELECT * FROM levels ORDER BY sort_order ASC, id ASC");
while ($row = mysqli_fetch_assoc($r)) $allLevels[] = $row;

$allProducts = [];
$r = mysqli_query($conn, "SELECT id, name FROM products ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($r)) $allProducts[] = $row;

$productToppings = [];
$r = mysqli_query($conn, "SELECT product_id, topping_id FROM product_toppings");
while ($row = mysqli_fetch_assoc($r)) $productToppings[$row['product_id']][$row['topping_id']] = true;

$productLevels = [];
$r = mysqli_query($conn, "SELECT product_id, level_id FROM product_levels");
while ($row = mysqli_fetch_assoc($r)) $productLevels[$row['product_id']][$row['level_id']] = true;

$selPid = (int)($_GET['pid'] ?? ($allProducts[0]['id'] ?? 0));

// Deteksi Tipe Produk untuk Menampilkan Opsi yang Tepat di Tab Per Produk
$selectedProductName = '';
foreach($allProducts as $p) {
    if($p['id'] == $selPid) {
        $selectedProductName = strtolower($p['name']);
        break;
    }
}

$isWonton = strpos($selectedProductName, 'wonton') !== false || strpos($selectedProductName, 'pangsit') !== false;
$isMinuman = strpos($selectedProductName, 'minum') !== false || strpos($selectedProductName, 'es ') !== false || strpos($selectedProductName, 'jus') !== false || strpos($selectedProductName, 'teh') !== false;
$isCeker = strpos($selectedProductName, 'ceker') !== false || strpos($selectedProductName, 'dimsum') !== false;
$isMie = strpos($selectedProductName, 'mie') !== false || strpos($selectedProductName, 'mercon') !== false;

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kelola Varian & Topping - Texcer Hot Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
/* CSS SAMA SEPERTI DASHBOARD LAINNYA */
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f7f3ee;color:#1a0f00;font-size:14px;display:flex;min-height:100vh;}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: #3D2914;
    color: white;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 100%;
    left: 0;
    top: 0;
    z-index: 100;
}
.sidebar-header {
    padding: 20px;
    font-size: 1.2rem;
    font-weight: 700;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}
.sidebar-menu {
    list-style: none;
    padding: 20px 0;
    flex-grow: 1;
}
.sidebar-menu li a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    transition: all 0.3s;
    font-weight: 500;
}
.sidebar-menu li a:hover, .sidebar-menu li a.active {
    background: rgba(255,255,255,0.1);
    color: white;
    border-left: 4px solid #D4A574;
}
.sidebar-menu li a i {
    width: 20px;
    text-align: center;
}

/* Main Content Wrapper */
.main-content {
    margin-left: 250px;
    flex-grow: 1;
    padding: 28px;
}

/* Existing Styles */
.wrap{max-width:1100px;margin:0 auto;}
.page-header{background:linear-gradient(135deg,#7c5c2a,#5a3f18);color:#fff;border-radius:16px;padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;gap:16px;}
.page-header h1{font-size:20px;font-weight:700;margin:0;}
.page-header p{font-size:13px;opacity:0.75;margin:4px 0 0;}
.msg{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;}
.msg-success{background:#e8f5e9;color:#1b5e20;border:1px solid #a5d6a7;}
.msg-error{background:#ffebee;color:#b71c1c;border:1px solid #ef9a9a;}
.tabs{display:flex;gap:6px;margin-bottom:20px;background:#fff;padding:6px;border-radius:12px;border:1px solid #ede8e0;}
.tab{padding:8px 20px;cursor:pointer;border-radius:8px;border:none;font-weight:600;font-size:13px;background:transparent;color:#9e8a72;transition:all .15s;font-family:inherit;}
.tab.active{background:#7c5c2a;color:#fff;}
.tab-content{display:none;}
.tab-content.active{display:block;}
.card{background:#fff;border-radius:14px;padding:22px;margin-bottom:20px;border:1px solid #ede8e0;}
.card h2{font-size:15px;font-weight:700;color:#4a3520;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f0ebe3;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
@media(max-width:640px){.grid2{grid-template-columns:1fr;}}
.form-group{margin-bottom:14px;}
.form-group label{font-size:12px;font-weight:600;color:#7c5c2a;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;}
.form-group input[type=text],.form-group input[type=number],.form-group select{width:100%;padding:9px 12px;border:1.5px solid #e8ddd0;border-radius:9px;font-size:13px;outline:none;font-family:inherit;color:#1a0f00;background:#fafaf8;transition:border-color .15s;}
.form-group input:focus,.form-group select:focus{border-color:#7c5c2a;}
.checkbox-row{display:flex;align-items:center;gap:8px;}
.checkbox-row input[type=checkbox]{width:17px;height:17px;accent-color:#7c5c2a;cursor:pointer;}
.checkbox-row label{font-size:13px;cursor:pointer;color:#1a0f00;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;border:none;cursor:pointer;font-size:13px;font-weight:600;font-family:inherit;transition:all .15s;}
.btn-primary{background:#7c5c2a;color:#fff;}
.btn-primary:hover{background:#5a3f18;}
.btn-secondary{background:#f5f0e8;color:#7c5c2a;}
.btn-secondary:hover{background:#ede4d4;}
.btn-danger{background:#fee2e2;color:#b91c1c;}
.btn-danger:hover{background:#fca5a5;}
.btn-sm{padding:5px 12px;font-size:12px;}
.btn-group{display:flex;gap:8px;flex-wrap:wrap;}
table{width:100%;border-collapse:collapse;}
th,td{text-align:left;padding:10px 12px;border-bottom:1px solid #f5f0e8;font-size:13px;}
th{background:#fef9f0;font-weight:700;color:#7c5c2a;font-size:12px;text-transform:uppercase;letter-spacing:.4px;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafaf8;}
.badge{display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;}
.badge-on{background:#dcfce7;color:#166534;}
.badge-off{background:#fee2e2;color:#991b1b;}
.topping-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:8px;margin-bottom:20px;}
.topping-item{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1.5px solid #e8ddd0;border-radius:10px;cursor:pointer;background:#fafaf8;transition:all .15s;}
.topping-item:hover{border-color:#c49a6c;}
.topping-item.checked{border-color:#7c5c2a;background:#fef9f0;}
.topping-item input{width:16px;height:16px;accent-color:#7c5c2a;cursor:pointer;flex-shrink:0;}
.topping-item .tp-name{font-size:13px;font-weight:600;color:#1a0f00;flex:1;}
.topping-item .tp-price{font-size:11px;color:#c17d2a;font-weight:700;white-space:nowrap;}
.level-grid{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;}
.level-item{display:flex;align-items:center;gap:6px;padding:9px 14px;border:1.5px solid #e8ddd0;border-radius:10px;cursor:pointer;background:#fafaf8;transition:all .15s;}
.level-item:hover{border-color:#c49a6c;}
.level-item.checked{border-color:#7c5c2a;background:#fef9f0;}
.level-item input{width:16px;height:16px;accent-color:#7c5c2a;cursor:pointer;}
.empty{text-align:center;padding:30px;color:#9e8a72;font-size:13px;}
.produk-select-wrap{margin-bottom:20px;}
.produk-select-wrap label{font-size:12px;font-weight:700;color:#7c5c2a;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:6px;}
.produk-select-wrap select{max-width:360px;padding:10px 14px;border:1.5px solid #e8ddd0;border-radius:10px;font-size:14px;font-weight:600;color:#1a0f00;background:#fff;outline:none;font-family:inherit;cursor:pointer;}
.produk-select-wrap select:focus{border-color:#7c5c2a;}
.save-bar{background:#fef9f0;border:1px dashed #d4a96a;border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.save-bar p{font-size:13px;color:#7c5c2a;font-weight:600;margin:0;}

/* Badge Tipe Produk */
.product-type-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    margin-left: 10px;
    vertical-align: middle;
}
.type-mie { background: #ffebee; color: #c62828; }
.type-minuman { background: #e3f2fd; color: #1565c0; }
.type-ceker { background: #fff3e0; color: #ef6c00; }
.type-wonton { background: #e8f5e9; color: #2e7d32; }

/* Responsive Sidebar */
@media (max-width: 768px) {
    .sidebar { width: 70px; }
    .sidebar-header span, .sidebar-menu li a span { display: none; }
    .main-content { margin-left: 70px; padding: 15px; }
    .sidebar-menu li a { justify-content: center; padding: 15px 0; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-pepper-hot"></i>
        <span>Texcer Admin</span>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
        <li><a href="orders.php"><i class="fas fa-shopping-bag"></i><span>Pesanan</span></a></li>
        <li><a href="products.php"><i class="fas fa-utensils"></i><span>Produk</span></a></li>
        <li><a href="categories.php"><i class="fas fa-tags"></i><span>Kategori</span></a></li>
        <li><a href="customers.php"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
        <li><a href="manage-images.php"><i class="fas fa-images"></i><span>Kelola Gambar</span></a></li>
        <!-- Active Menu -->
        <li><a href="admin_topping.php" class="active"><i class="fas fa-pepper-hot"></i><span>Topping & Level</span></a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
        <li style="margin-top: auto;"><a href="index.php" target="_blank"><i class="fas fa-external-link-alt"></i><span>Lihat Toko</span></a></li>
    </ul>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="wrap">

    <div class="page-header">
        <div style="width:44px;height:44px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">🍜</div>
        <div>
        <h1>Kelola Varian & Topping</h1>
        <p>Texcer Hot — Atur pilihan menu per produk</p>
        </div>
    </div>

    <?php if($msg): ?>
        <div class="msg <?= $msgType==='error' ? 'msg-error' : 'msg-success' ?>">
        <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab active" onclick="showTab('tab-produk', this)">⚙️ Per Produk</button>
        <button class="tab"        onclick="showTab('tab-topping', this)">🧀 Master Topping</button>
        <button class="tab"        onclick="showTab('tab-level', this)">📦 Master Varian/Level</button>
    </div>

    <!-- TAB 1: SETTING PER PRODUK -->
    <div id="tab-produk" class="tab-content active">
        <div class="card">
        <h2>⚙️ Atur Varian & Topping untuk Tiap Produk</h2>
        <form method="POST" id="formProduk">
            <input type="hidden" name="act" value="save_product_settings">
            <input type="hidden" name="product_id" value="<?= $selPid ?>">

            <div class="produk-select-wrap">
            <label>Pilih Produk</label>
            <select onchange="window.location='?pid='+this.value">
                <?php foreach($allProducts as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id']==$selPid?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            </div>

            <?php if(empty($allToppings) && empty($allLevels)): ?>
            <div class="empty">Belum ada data. Tambahkan dulu di tab <b>Master Topping</b> dan <b>Master Varian</b>.</div>
            <?php else: ?>

            <?php if(!empty($allToppings)): ?>
            <h3 style="font-size:13px;font-weight:700;color:#4a3520;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px;">🧀 Centang Topping yang Tersedia</h3>
            <div class="topping-grid">
                <?php foreach($allToppings as $t): ?>
                <?php $checked = isset($productToppings[$selPid][$t['id']]); ?>
                <label class="topping-item <?= $checked?'checked':'' ?>" onclick="this.classList.toggle('checked')">
                    <input type="checkbox" name="topping_ids[]" value="<?= $t['id'] ?>" <?= $checked?'checked':'' ?>>
                    <span class="tp-name"><?= htmlspecialchars($t['name']) ?></span>
                    <span class="tp-price">+Rp <?= number_format($t['price'],0,',','.') ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- LOGIKA DINAMIS VARIAN/LEVEL -->
            <?php 
            // Kita akan menampilkan SEMUA level dari database, tapi kita beri label visual
            // Agar admin tahu mana yang untuk Mie, Minuman, dll.
            // Namun, agar lebih rapi, kita bisa memfilter atau memberi badge.
            // Di sini kita tampilkan semua level yang ada di database, karena admin bisa memilih mana yang dicentang.
            ?>

            <?php if(!empty($allLevels)): ?>
            <h3 style="font-size:13px;font-weight:700;color:#4a3520;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px;">
                📦 Centang Varian/Level yang Tersedia 
                <?php if($selectedProductName): ?>
                    <span class="product-type-badge <?= $isMie ? 'type-mie' : ($isMinuman ? 'type-minuman' : ($isCeker ? 'type-ceker' : ($isWonton ? 'type-wonton' : ''))) ?>">
                        <?= $isMie ? 'TIPE MIE' : ($isMinuman ? 'TIPE MINUMAN' : ($isCeker ? 'TIPE CEKER' : ($isWonton ? 'TIPE WONTON' : 'UMUM'))) ?>
                    </span>
                <?php endif; ?>
            </h3>
            
            <div style="background: #fff8f0; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 12px; color: #666;">
                <i class="fas fa-info-circle"></i> 
                Pilih varian yang sesuai dengan tipe produk di atas. 
                <br><strong>Mie:</strong> Level 1-5 | <strong>Minuman:</strong> Es/Hangat | <strong>Ceker:</strong> Medium/Large/Paket | <strong>Wonton:</strong> Goreng/Rebus.
            </div>

            <div class="level-grid">
                <?php foreach($allLevels as $lv): ?>
                    <?php $checked = isset($productLevels[$selPid][$lv['id']]); ?>
                    <label class="level-item <?= $checked?'checked':'' ?>" onclick="this.classList.toggle('checked')">
                        <input type="checkbox" name="level_ids[]" value="<?= $lv['id'] ?>" <?= $checked?'checked':'' ?>>
                        <span style="font-size:13px;font-weight:600;"><?= htmlspecialchars($lv['name']) ?></span>
                        <?php if($lv['price']>0): ?>
                            <span style="font-size:11px;color:#c17d2a;font-weight:700;">+Rp <?= number_format($lv['price'],0,',','.') ?></span>
                        <?php else: ?>
                            <span style="font-size:11px;color:#9e8a72;">Gratis</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p style="color:red; font-size:12px;">Belum ada Varian/Level di database. Silakan tambahkan di tab "Master Varian/Level".</p>
            <?php endif; ?>

            <div class="save-bar">
                <p>💡 Centang item yang ingin tersedia untuk produk <b><?= htmlspecialchars($allProducts[array_search($selPid, array_column($allProducts,'id'))]['name'] ?? '') ?></b></p>
                <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
            </div>

            <?php endif; ?>
        </form>
        </div>
    </div>

    <!-- TAB 2: MASTER TOPPING -->
    <div id="tab-topping" class="tab-content">
        <div class="grid2">
        <div class="card">
            <h2 id="tpFormTitle">➕ Tambah Topping Baru</h2>
            <form method="POST" id="formTopping">
            <input type="hidden" name="act" value="save_topping">
            <input type="hidden" name="id" id="tpId" value="0">
            <div class="form-group">
                <label>Nama Topping</label>
                <input type="text" name="name" id="tpNameInput" placeholder="cth: Pangsit Goreng" required>
            </div>
            <div class="form-group">
                <label>Harga Tambahan (Rp)</label>
                <input type="number" name="price" id="tpPriceInput" placeholder="3000" min="0" required>
            </div>
            <div class="form-group">
                <div class="checkbox-row">
                <input type="checkbox" name="is_active" id="tpActiveInput" checked>
                <label for="tpActiveInput">Aktif (tampil di menu)</label>
                </div>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">💾 Simpan</button>
                <button type="button" onclick="resetToppingForm()" class="btn btn-secondary">Reset</button>
            </div>
            </form>
        </div>
        <div class="card">
            <h2>📋 Daftar Topping</h2>
            <?php if(empty($allToppings)): ?>
            <div class="empty">Belum ada topping.</div>
            <?php else: ?>
            <table>
            <thead><tr><th>Nama</th><th>Harga</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach($allToppings as $t): ?>
                <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($t['name']) ?></td>
                <td style="color:#c17d2a;font-weight:600;">Rp <?= number_format($t['price'],0,',','.') ?></td>
                <td><span class="badge <?= $t['is_active']?'badge-on':'badge-off' ?>"><?= $t['is_active']?'Aktif':'Nonaktif' ?></span></td>
                <td>
                    <div class="btn-group">
                    <button class="btn btn-secondary btn-sm" onclick="editTopping(<?= $t['id'] ?>,'<?= addslashes(htmlspecialchars($t['name'])) ?>',<?= $t['price'] ?>,<?= $t['is_active'] ?>)">✏️ Edit</button>
                    <form method="POST" onsubmit="return confirm('Hapus topping ini?');" style="display:inline">
                        <input type="hidden" name="act" value="del_topping">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button class="btn btn-danger btn-sm">🗑️</button>
                    </form>
                    </div>
                </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
            <?php endif; ?>
        </div>
        </div>
    </div>

    <!-- TAB 3: MASTER LEVEL/VARIAN -->
    <div id="tab-level" class="tab-content">
        <div class="grid2">
        <div class="card">
            <h2 id="lvFormTitle">➕ Tambah Varian/Level Baru</h2>
            <p style="font-size:12px;color:#888;margin-bottom:15px;">
                Buat varian sesuai kebutuhan:<br>
                - <strong>Mie:</strong> Level 1, Level 2, dst.<br>
                - <strong>Minuman:</strong> Es, Hangat.<br>
                - <strong>Ceker:</strong> Medium (5pc), Large (10pcs), Paket Nasi.<br>
                - <strong>Wonton:</strong> Goreng, Rebus.
            </p>
            <form method="POST" id="formLevel">
            <input type="hidden" name="act" value="save_level">
            <input type="hidden" name="id" id="lvId" value="0">
            <div class="form-group">
                <label>Nama Varian/Level</label>
                <input type="text" name="name" id="lvNameInput" placeholder="cth: Level 1 atau Es" required>
            </div>
            <div class="form-group">
                <label>Harga Tambahan (Rp)</label>
                <input type="number" name="price" id="lvPriceInput" placeholder="0" min="0" required>
            </div>
            <div class="form-group">
                <label>Urutan Tampil (1 = Paling Awal)</label>
                <input type="number" name="sort_order" id="lvSortInput" placeholder="1" min="0" required>
            </div>
            <div class="form-group">
                <div class="checkbox-row">
                <input type="checkbox" name="is_active" id="lvActiveInput" checked>
                <label for="lvActiveInput">Aktif</label>
                </div>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">💾 Simpan</button>
                <button type="button" onclick="resetLevelForm()" class="btn btn-secondary">Reset</button>
            </div>
            </form>
        </div>
        <div class="card">
            <h2>📋 Daftar Semua Varian/Level</h2>
            <?php if(empty($allLevels)): ?>
            <div class="empty">Belum ada varian/level.</div>
            <?php else: ?>
            <table>
            <thead><tr><th>Nama</th><th>Harga</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach($allLevels as $lv): ?>
                <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($lv['name']) ?></td>
                <td><?= $lv['price']>0 ? '<span style="color:#c17d2a;font-weight:600;">Rp '.number_format($lv['price'],0,',','.').'</span>' : '<span style="color:#9e8a72;">Gratis</span>' ?></td>
                <td><?= $lv['sort_order'] ?></td>
                <td><span class="badge <?= $lv['is_active']?'badge-on':'badge-off' ?>"><?= $lv['is_active']?'Aktif':'Nonaktif' ?></span></td>
                <td>
                    <div class="btn-group">
                    <button class="btn btn-secondary btn-sm" onclick="editLevel(<?= $lv['id'] ?>,'<?= addslashes(htmlspecialchars($lv['name'])) ?>',<?= $lv['price'] ?>,<?= $lv['sort_order'] ?>,<?= $lv['is_active'] ?>)">✏️ Edit</button>
                    <form method="POST" onsubmit="return confirm('Hapus varian ini?');" style="display:inline">
                        <input type="hidden" name="act" value="del_level">
                        <input type="hidden" name="id" value="<?= $lv['id'] ?>">
                        <button class="btn btn-danger btn-sm">🗑️</button>
                    </form>
                    </div>
                </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
            <?php endif; ?>
        </div>
        </div>
    </div>

    </div>
</div>

<script>
function showTab(id, btn) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  btn.classList.add('active');
}

function editTopping(id, name, price, active) {
  showTab('tab-topping', document.querySelectorAll('.tab')[1]);
  document.getElementById('tpFormTitle').textContent = '✏️ Edit Topping';
  document.getElementById('tpId').value         = id;
  document.getElementById('tpNameInput').value  = name;
  document.getElementById('tpPriceInput').value = price;
  document.getElementById('tpActiveInput').checked = active == 1;
  document.getElementById('formTopping').scrollIntoView({behavior:'smooth'});
}

function resetToppingForm() {
  document.getElementById('tpFormTitle').textContent = '➕ Tambah Topping Baru';
  document.getElementById('tpId').value         = 0;
  document.getElementById('tpNameInput').value  = '';
  document.getElementById('tpPriceInput').value = '';
  document.getElementById('tpActiveInput').checked = true;
}

function editLevel(id, name, price, sort, active) {
  showTab('tab-level', document.querySelectorAll('.tab')[2]);
  document.getElementById('lvFormTitle').textContent = '✏️ Edit Varian/Level';
  document.getElementById('lvId').value         = id;
  document.getElementById('lvNameInput').value  = name;
  document.getElementById('lvPriceInput').value = price;
  document.getElementById('lvSortInput').value  = sort;
  document.getElementById('lvActiveInput').checked = active == 1;
  document.getElementById('formLevel').scrollIntoView({behavior:'smooth'});
}

function resetLevelForm() {
  document.getElementById('lvFormTitle').textContent = '➕ Tambah Varian/Level Baru';
  document.getElementById('lvId').value         = 0;
  document.getElementById('lvNameInput').value  = '';
  document.getElementById('lvPriceInput').value = '';
  document.getElementById('lvSortInput').value  = '';
  document.getElementById('lvActiveInput').checked = true;
}
</script>
</body>
</html>