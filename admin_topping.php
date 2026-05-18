<?php
// admin_topping.php
// Letakkan di: localhost/texcer2/admin/admin_topping.php (atau sesuaikan)

require_once '../config.php'; // sesuaikan path

$msg = '';

// ============ HANDLE FORM SUBMIT ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // --- Simpan Master Topping ---
    if ($act === 'save_topping') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($id) {
            $pdo->prepare("UPDATE toppings SET name=?, price=?, is_active=? WHERE id=?")->execute([$name, $price, $active, $id]);
        } else {
            $pdo->prepare("INSERT INTO toppings (name, price, is_active) VALUES (?,?,?)")->execute([$name, $price, $active]);
        }
        $msg = '✅ Topping berhasil disimpan';
    }

    // --- Hapus Topping ---
    if ($act === 'del_topping') {
        $pdo->prepare("DELETE FROM toppings WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = '🗑️ Topping dihapus';
    }

    // --- Simpan Master Level ---
    if ($act === 'save_level') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $sort  = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($id) {
            $pdo->prepare("UPDATE levels SET name=?, price=?, sort_order=?, is_active=? WHERE id=?")->execute([$name, $price, $sort, $active, $id]);
        } else {
            $pdo->prepare("INSERT INTO levels (name, price, sort_order, is_active) VALUES (?,?,?,?)")->execute([$name, $price, $sort, $active]);
        }
        $msg = '✅ Level berhasil disimpan';
    }

    // --- Hapus Level ---
    if ($act === 'del_level') {
        $pdo->prepare("DELETE FROM levels WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = '🗑️ Level dihapus';
    }

    // --- Simpan Topping & Level per Produk ---
    if ($act === 'save_product_settings') {
        $pid = (int)($_POST['product_id'] ?? 0);

        // Reset dulu
        $pdo->prepare("DELETE FROM product_toppings WHERE product_id=?")->execute([$pid]);
        $pdo->prepare("DELETE FROM product_levels WHERE product_id=?")->execute([$pid]);

        // Insert topping yang dicentang
        if (!empty($_POST['topping_ids'])) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO product_toppings (product_id, topping_id) VALUES (?,?)");
            foreach ($_POST['topping_ids'] as $tid) {
                $stmt->execute([$pid, (int)$tid]);
            }
        }
        // Insert level yang dicentang
        if (!empty($_POST['level_ids'])) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO product_levels (product_id, level_id) VALUES (?,?)");
            foreach ($_POST['level_ids'] as $lid) {
                $stmt->execute([$pid, (int)$lid]);
            }
        }
        $msg = '✅ Pengaturan topping & level produk disimpan';
    }
}

// ============ AMBIL DATA ============
$allToppings = $pdo->query("SELECT * FROM toppings ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$allLevels   = $pdo->query("SELECT * FROM levels ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$allProducts = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Topping & level per produk yang sudah tersimpan
$productToppings = [];
$rows = $pdo->query("SELECT product_id, topping_id FROM product_toppings")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) $productToppings[$r['product_id']][$r['topping_id']] = true;

$productLevels = [];
$rows = $pdo->query("SELECT product_id, level_id FROM product_levels")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) $productLevels[$r['product_id']][$r['level_id']] = true;

$selPid = (int)($_GET['pid'] ?? ($allProducts[0]['id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kelola Topping & Level - Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:sans-serif;background:#f5f5f5;color:#1a1a1a;font-size:14px;}
.wrap{max-width:1100px;margin:0 auto;padding:24px 16px;}
h1{font-size:20px;font-weight:700;margin-bottom:20px;color:#1a1a1a;}
h2{font-size:16px;font-weight:600;margin-bottom:14px;}
.card{background:#fff;border-radius:12px;padding:20px;margin-bottom:20px;border:1px solid #eee;}
.msg{background:#E8F5E9;color:#1B5E20;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;}
table{width:100%;border-collapse:collapse;}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #f0f0f0;font-size:13px;}
th{background:#fafafa;font-weight:600;color:#555;}
.badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;}
.badge-on{background:#E8F5E9;color:#1B5E20;}
.badge-off{background:#FFEBEE;color:#B71C1C;}
input[type=text],input[type=number],select{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;outline:none;}
input:focus,select:focus{border-color:#C8861C;}
.btn{display:inline-block;padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-size:13px;font-weight:600;}
.btn-primary{background:#C8861C;color:#fff;}
.btn-danger{background:#e53935;color:#fff;}
.btn-sm{padding:5px 10px;font-size:12px;}
.btn-secondary{background:#f0f0f0;color:#333;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.topping-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;}
.topping-item{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1.5px solid #eee;border-radius:8px;cursor:pointer;}
.topping-item input{width:16px;height:16px;cursor:pointer;}
.level-grid{display:flex;flex-wrap:wrap;gap:8px;}
.tabs{display:flex;gap:8px;margin-bottom:20px;border-bottom:2px solid #eee;padding-bottom:0;}
.tab{padding:8px 18px;cursor:pointer;border-radius:8px 8px 0 0;border:1px solid transparent;font-weight:600;font-size:13px;background:transparent;color:#888;}
.tab.active{border-color:#eee #eee #fff;background:#fff;color:#C8861C;border-bottom-color:#fff;margin-bottom:-2px;}
.tab-content{display:none;}.tab-content.active{display:block;}
@media(max-width:600px){.grid2{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="wrap">
  <h1>🍜 Kelola Topping & Level Pedas</h1>

  <?php if($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- TAB -->
  <div class="tabs">
    <button class="tab active" onclick="showTab('tab-produk')">⚙️ Per Produk</button>
    <button class="tab" onclick="showTab('tab-topping')">🧀 Master Topping</button>
    <button class="tab" onclick="showTab('tab-level')">🌶️ Master Level</button>
  </div>

  <!-- ===== TAB 1: SETTING PER PRODUK ===== -->
  <div id="tab-produk" class="tab-content active">
    <div class="card">
      <h2>Pengaturan Topping & Level per Produk</h2>
      <form method="POST">
        <input type="hidden" name="act" value="save_product_settings">

        <div style="margin-bottom:16px;">
          <label style="font-size:13px;color:#555;display:block;margin-bottom:6px;">Pilih Produk</label>
          <select name="product_id" onchange="this.form.submit(); window.location='?pid='+this.value;" style="max-width:320px;">
            <?php foreach($allProducts as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $p['id']==$selPid?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <h3 style="font-size:14px;font-weight:600;margin-bottom:10px;">🧀 Centang Topping yang Tersedia</h3>
        <div class="topping-grid" style="margin-bottom:20px;">
          <?php foreach($allToppings as $t): ?>
            <?php $checked = isset($productToppings[$selPid][$t['id']]); ?>
            <label class="topping-item" style="border-color:<?= $checked?'#C8861C':'#eee' ?>; background:<?= $checked?'#FFF8EE':'#fff' ?>;">
              <input type="checkbox" name="topping_ids[]" value="<?= $t['id'] ?>" <?= $checked?'checked':'' ?>>
              <span><?= htmlspecialchars($t['name']) ?></span>
              <span style="margin-left:auto;color:#C8861C;font-size:12px;">+Rp <?= number_format($t['price'],0,',','.') ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <h3 style="font-size:14px;font-weight:600;margin-bottom:10px;">🌶️ Centang Level yang Tersedia</h3>
        <div class="level-grid" style="margin-bottom:20px;">
          <?php foreach($allLevels as $lv): ?>
            <?php $checked = isset($productLevels[$selPid][$lv['id']]); ?>
            <label style="display:flex;align-items:center;gap:6px;padding:8px 14px;border:1.5px solid <?= $checked?'#C8861C':'#eee' ?>;border-radius:10px;background:<?= $checked?'#FFF8EE':'#fff' ?>;cursor:pointer;">
              <input type="checkbox" name="level_ids[]" value="<?= $lv['id'] ?>" <?= $checked?'checked':'' ?>>
              <span style="font-size:13px;"><?= htmlspecialchars($lv['name']) ?></span>
              <?php if($lv['price']>0): ?>
                <span style="font-size:11px;color:#C8861C;">+Rp <?= number_format($lv['price'],0,',','.') ?></span>
              <?php else: ?>
                <span style="font-size:11px;color:#aaa;">Gratis</span>
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>

        <input type="hidden" name="product_id" value="<?= $selPid ?>">
        <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan Produk</button>
      </form>
    </div>
  </div>

  <!-- ===== TAB 2: MASTER TOPPING ===== -->
  <div id="tab-topping" class="tab-content">
    <div class="grid2">
      <div class="card">
        <h2>Tambah / Edit Topping</h2>
        <form method="POST" id="formTopping">
          <input type="hidden" name="act" value="save_topping">
          <input type="hidden" name="id" id="tpId" value="0">
          <div style="margin-bottom:12px;">
            <label style="font-size:12px;color:#555;display:block;margin-bottom:4px;">Nama Topping</label>
            <input type="text" name="name" id="tpNameInput" placeholder="cth: Pangsit Goreng" required>
          </div>
          <div style="margin-bottom:12px;">
            <label style="font-size:12px;color:#555;display:block;margin-bottom:4px;">Harga Tambahan (Rp)</label>
            <input type="number" name="price" id="tpPriceInput" placeholder="3000" min="0" required>
          </div>
          <div style="margin-bottom:16px;display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="is_active" id="tpActiveInput" checked style="width:16px;height:16px;">
            <label for="tpActiveInput" style="font-size:13px;">Aktif (tersedia)</label>
          </div>
          <button type="submit" class="btn btn-primary">💾 Simpan Topping</button>
          <button type="button" onclick="resetToppingForm()" class="btn btn-secondary" style="margin-left:8px;">Reset</button>
        </form>
      </div>
      <div class="card">
        <h2>Daftar Topping</h2>
        <table>
          <thead><tr><th>Nama</th><th>Harga</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php foreach($allToppings as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['name']) ?></td>
              <td>Rp <?= number_format($t['price'],0,',','.') ?></td>
              <td><span class="badge <?= $t['is_active']?'badge-on':'badge-off' ?>"><?= $t['is_active']?'Aktif':'Nonaktif' ?></span></td>
              <td style="display:flex;gap:6px;">
                <button class="btn btn-primary btn-sm" onclick="editTopping(<?= $t['id'] ?>,'<?= addslashes($t['name']) ?>',<?= $t['price'] ?>,<?= $t['is_active'] ?>)">Edit</button>
                <form method="POST" onsubmit="return confirm('Hapus topping ini?');" style="display:inline">
                  <input type="hidden" name="act" value="del_topping">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button class="btn btn-danger btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ===== TAB 3: MASTER LEVEL ===== -->
  <div id="tab-level" class="tab-content">
    <div class="grid2">
      <div class="card">
        <h2>Tambah / Edit Level</h2>
        <form method="POST" id="formLevel">
          <input type="hidden" name="act" value="save_level">
          <input type="hidden" name="id" id="lvId" value="0">
          <div style="margin-bottom:12px;">
            <label style="font-size:12px;color:#555;display:block;margin-bottom:4px;">Nama Level</label>
            <input type="text" name="name" id="lvNameInput" placeholder="cth: Level 5 (Pedas)" required>
          </div>
          <div style="margin-bottom:12px;">
            <label style="font-size:12px;color:#555;display:block;margin-bottom:4px;">Harga Tambahan (Rp, 0 = Gratis)</label>
            <input type="number" name="price" id="lvPriceInput" placeholder="0" min="0" required>
          </div>
          <div style="margin-bottom:12px;">
            <label style="font-size:12px;color:#555;display:block;margin-bottom:4px;">Urutan Tampil</label>
            <input type="number" name="sort_order" id="lvSortInput" placeholder="5" min="0" required>
          </div>
          <div style="margin-bottom:16px;display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="is_active" id="lvActiveInput" checked style="width:16px;height:16px;">
            <label for="lvActiveInput" style="font-size:13px;">Aktif</label>
          </div>
          <button type="submit" class="btn btn-primary">💾 Simpan Level</button>
          <button type="button" onclick="resetLevelForm()" class="btn btn-secondary" style="margin-left:8px;">Reset</button>
        </form>
      </div>
      <div class="card">
        <h2>Daftar Level</h2>
        <table>
          <thead><tr><th>Nama</th><th>Harga</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php foreach($allLevels as $lv): ?>
            <tr>
              <td><?= htmlspecialchars($lv['name']) ?></td>
              <td><?= $lv['price']>0 ? 'Rp '.number_format($lv['price'],0,',','.') : '<span style="color:#aaa">Gratis</span>' ?></td>
              <td><?= $lv['sort_order'] ?></td>
              <td><span class="badge <?= $lv['is_active']?'badge-on':'badge-off' ?>"><?= $lv['is_active']?'Aktif':'Nonaktif' ?></span></td>
              <td style="display:flex;gap:6px;">
                <button class="btn btn-primary btn-sm" onclick="editLevel(<?= $lv['id'] ?>,'<?= addslashes($lv['name']) ?>',<?= $lv['price'] ?>,<?= $lv['sort_order'] ?>,<?= $lv['is_active'] ?>)">Edit</button>
                <form method="POST" onsubmit="return confirm('Hapus level ini?');" style="display:inline">
                  <input type="hidden" name="act" value="del_level">
                  <input type="hidden" name="id" value="<?= $lv['id'] ?>">
                  <button class="btn btn-danger btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /wrap -->

<script>
function showTab(id){
  document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(el=>el.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  event.target.classList.add('active');
}
function editTopping(id,name,price,active){
  showTab('tab-topping');
  document.getElementById('tpId').value=id;
  document.getElementById('tpNameInput').value=name;
  document.getElementById('tpPriceInput').value=price;
  document.getElementById('tpActiveInput').checked=active==1;
}
function resetToppingForm(){
  document.getElementById('tpId').value=0;
  document.getElementById('tpNameInput').value='';
  document.getElementById('tpPriceInput').value='';
  document.getElementById('tpActiveInput').checked=true;
}
function editLevel(id,name,price,sort,active){
  showTab('tab-level');
  document.getElementById('lvId').value=id;
  document.getElementById('lvNameInput').value=name;
  document.getElementById('lvPriceInput').value=price;
  document.getElementById('lvSortInput').value=sort;
  document.getElementById('lvActiveInput').checked=active==1;
}
function resetLevelForm(){
  document.getElementById('lvId').value=0;
  document.getElementById('lvNameInput').value='';
  document.getElementById('lvPriceInput').value='';
  document.getElementById('lvSortInput').value='';
  document.getElementById('lvActiveInput').checked=true;
}
</script>
</body>
</html>