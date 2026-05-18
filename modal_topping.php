<?php
// modal_topping.php - v2 FIXED
// Include sebelum </body> di index.php
?>

<div id="toppingOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:flex-end; justify-content:center;">
  <div id="toppingModal" style="background:#fff; border-radius:16px 16px 0 0; width:100%; max-width:480px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 -4px 24px rgba(0,0,0,0.15);">

    <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #f0f0f0; flex-shrink:0;">
      <div style="display:flex; align-items:center; gap:10px;">
        <img id="tpImg" src="" alt="" style="width:44px; height:44px; border-radius:8px; object-fit:cover; background:#f5f5f5; display:none;">
        <div>
          <p id="tpName" style="margin:0; font-size:15px; font-weight:600; color:#1a1a1a;"></p>
          <p id="tpBaseLabel" style="margin:0; font-size:12px; color:#888;"></p>
        </div>
      </div>
      <button onclick="tpClose()" style="background:none; border:none; cursor:pointer; padding:4px; font-size:22px; color:#888; line-height:1;">&times;</button>
    </div>

    <div style="overflow-y:auto; padding:14px 16px; flex:1;">

      <div id="tpLoading" style="text-align:center; padding:40px 0; color:#888; font-size:14px;">
        <div style="font-size:24px; margin-bottom:8px;">&#9203;</div>
        Memuat pilihan...
      </div>

      <div id="tpContent" style="display:none;">

        <div style="background:#FFF8EE; border:1px solid #F5DEB3; border-radius:10px; padding:10px 14px; margin-bottom:16px;">
          <div style="display:flex; justify-content:space-between; font-size:12px; color:#888; margin-bottom:4px;">
            <span>Harga dasar</span>
            <span id="tpBasePrice">Rp 0</span>
          </div>
          <div id="tpToppingBreakdown"></div>
          <div id="tpLevelBreakdown"></div>
          <div style="border-top:1px dashed #F5DEB3; margin-top:8px; padding-top:8px; display:flex; justify-content:space-between;">
            <span style="font-size:13px; font-weight:600; color:#1a1a1a;">Total 1 porsi</span>
            <span id="tpPerPortion" style="font-size:13px; font-weight:600; color:#C8861C;">Rp 0</span>
          </div>
        </div>

        <div id="tpToppingSection" style="margin-bottom:18px; display:none;">
          <div style="display:flex; align-items:center; gap:6px; margin-bottom:10px;">
            <span style="background:#FFF3E0; color:#E65100; font-size:11px; font-weight:600; padding:2px 9px; border-radius:99px;">Pilihan</span>
            <span style="font-size:14px; font-weight:600; color:#1a1a1a;">Topping Tambahan</span>
          </div>
          <div id="tpToppingList" style="display:flex; flex-direction:column; gap:8px;"></div>
        </div>

        <div id="tpLevelSection" style="margin-bottom:18px; display:none;">
          <div style="display:flex; align-items:center; gap:6px; margin-bottom:10px;">
            <span style="background:#FBE9E7; color:#BF360C; font-size:11px; font-weight:600; padding:2px 9px; border-radius:99px;">Wajib</span>
            <span style="font-size:14px; font-weight:600; color:#1a1a1a;">Level Pedas</span>
          </div>
          <div id="tpLevelList" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
          <p id="tpLevelWarn" style="display:none; color:#e53935; font-size:12px; margin:6px 0 0;">Pilih level pedas dulu ya!</p>
        </div>

        <div>
          <p style="margin:0 0 8px; font-size:14px; font-weight:600; color:#1a1a1a;">Jumlah</p>
          <div style="display:flex; align-items:center; gap:14px;">
            <button onclick="tpChangeQty(-1)" style="width:36px; height:36px; border-radius:50%; border:1.5px solid #ddd; background:#fff; font-size:20px; cursor:pointer; line-height:1; color:#1a1a1a;">-</button>
            <span id="tpQtyDisplay" style="font-size:16px; font-weight:600; min-width:24px; text-align:center; color:#1a1a1a;">1</span>
            <button onclick="tpChangeQty(1)" style="width:36px; height:36px; border-radius:50%; border:none; background:#C8861C; font-size:20px; cursor:pointer; line-height:1; color:#fff;">+</button>
          </div>
        </div>

      </div>
    </div>

    <div style="padding:12px 16px; border-top:1px solid #f0f0f0; flex-shrink:0;">
      <button id="tpBtnCart" onclick="tpSubmit('cart')" style="width:100%; padding:13px 16px; background:#fff; color:#C8861C; border:2px solid #C8861C; border-radius:12px; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
        <span>Tambah ke Keranjang</span>
        <span id="tpTotalCart">Rp 0</span>
      </button>
      <button id="tpBtnBuy" onclick="tpSubmit('buy')" style="width:100%; padding:13px 16px; background:#C8861C; color:#fff; border:none; border-radius:12px; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:space-between;">
        <span>Beli Sekarang</span>
        <span id="tpTotalBuy">Rp 0</span>
      </button>
    </div>

  </div>
</div>

<script>
(function() {

  var tpState = {
    product: null,
    toppings: [],
    levels: [],
    selectedToppings: {},
    selectedLevel: null,
    qty: 1,
    hasLevel: false
  };

  function tpFmt(n) {
    return 'Rp ' + parseInt(n).toLocaleString('id-ID');
  }

  function tpRenderToppings() {
    var html = '';
    for (var i = 0; i < tpState.toppings.length; i++) {
      var t   = tpState.toppings[i];
      var sel = !!tpState.selectedToppings[t.id];
      html += '<div onclick="tpToggle(' + t.id + ')" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-radius:10px;border:1.5px solid ' + (sel ? '#C8861C' : '#e8e8e8') + ';cursor:pointer;background:' + (sel ? '#FFF8EE' : '#fff') + ';margin-bottom:8px;">'
            + '<div style="display:flex;align-items:center;gap:10px;">'
            + '<div style="width:20px;height:20px;border-radius:5px;border:2px solid ' + (sel ? '#C8861C' : '#ccc') + ';background:' + (sel ? '#C8861C' : 'transparent') + ';display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;color:#fff;">' + (sel ? 'v' : '') + '</div>'
            + '<span style="font-size:13px;color:#1a1a1a;">' + t.name + '</span>'
            + '</div>'
            + '<span style="font-size:13px;font-weight:600;color:' + (sel ? '#C8861C' : '#888') + ';">+' + tpFmt(t.price) + '</span>'
            + '</div>';
    }
    document.getElementById('tpToppingList').innerHTML = html;
  }

  function tpRenderLevels() {
    var html = '';
    for (var i = 0; i < tpState.levels.length; i++) {
      var lv  = tpState.levels[i];
      var sel = tpState.selectedLevel === lv.id;
      html += '<div onclick="tpPickLevel(' + lv.id + ')" style="padding:8px 14px;border-radius:10px;border:1.5px solid ' + (sel ? '#C8861C' : '#e8e8e8') + ';cursor:pointer;background:' + (sel ? '#C8861C' : '#fff') + ';text-align:center;min-width:80px;margin-bottom:8px;">'
            + '<div style="font-size:12px;font-weight:600;color:' + (sel ? '#fff' : '#1a1a1a') + ';">' + lv.name + '</div>'
            + '<div style="font-size:11px;color:' + (sel ? '#FFE0B2' : (lv.price > 0 ? '#C8861C' : '#aaa')) + ';">' + (lv.price > 0 ? '+' + tpFmt(lv.price) : 'Gratis') + '</div>'
            + '</div>';
    }
    document.getElementById('tpLevelList').innerHTML = html;
  }

  function tpUpdatePrice() {
    var base    = parseInt(tpState.product.price);
    var topSum  = 0;
    var topHtml = '';

    for (var id in tpState.selectedToppings) {
      if (tpState.selectedToppings[id]) {
        for (var i = 0; i < tpState.toppings.length; i++) {
          if (tpState.toppings[i].id == id) {
            topSum  += parseInt(tpState.toppings[i].price);
            topHtml += '<div style="display:flex;justify-content:space-between;margin-top:4px;">'
                     + '<span style="font-size:12px;color:#888;">+ ' + tpState.toppings[i].name + '</span>'
                     + '<span style="font-size:12px;color:#C8861C;">+' + tpFmt(tpState.toppings[i].price) + '</span>'
                     + '</div>';
            break;
          }
        }
      }
    }

    var lvPrice = 0;
    var lvHtml  = '';
    if (tpState.selectedLevel !== null) {
      for (var j = 0; j < tpState.levels.length; j++) {
        if (tpState.levels[j].id === tpState.selectedLevel && tpState.levels[j].price > 0) {
          lvPrice = parseInt(tpState.levels[j].price);
          lvHtml  = '<div style="display:flex;justify-content:space-between;margin-top:4px;">'
                  + '<span style="font-size:12px;color:#888;">+ ' + tpState.levels[j].name + '</span>'
                  + '<span style="font-size:12px;color:#C8861C;">+' + tpFmt(tpState.levels[j].price) + '</span>'
                  + '</div>';
          break;
        }
      }
    }

    var perPortion = base + topSum + lvPrice;
    var total      = perPortion * tpState.qty;

    document.getElementById('tpToppingBreakdown').innerHTML = topHtml;
    document.getElementById('tpLevelBreakdown').innerHTML   = lvHtml;
    document.getElementById('tpPerPortion').textContent     = tpFmt(perPortion);
    document.getElementById('tpTotalCart').textContent      = tpFmt(total);
    document.getElementById('tpTotalBuy').textContent       = tpFmt(total);
    document.getElementById('tpQtyDisplay').textContent     = tpState.qty;
  }

  function tpRenderAll() {
    tpRenderToppings();
    tpRenderLevels();
    tpUpdatePrice();
  }

  // Fungsi global dipasang ke window
  window.openToppingModal = function(productId) {
    tpState = {
      product: null, toppings: [], levels: [],
      selectedToppings: {}, selectedLevel: null, qty: 1, hasLevel: false
    };

    document.getElementById('toppingOverlay').style.display = 'flex';
    document.getElementById('tpLoading').style.display      = 'block';
    document.getElementById('tpContent').style.display      = 'none';
    document.getElementById('tpLevelWarn').style.display    = 'none';

    fetch('api_topping.php?action=get&product_id=' + productId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.error) { alert(data.error); tpClose(); return; }

        tpState.product  = data.product;
        tpState.toppings = data.toppings;
        tpState.levels   = data.levels;
        tpState.hasLevel = data.levels.length > 0;

        document.getElementById('tpName').textContent      = data.product.name;
        document.getElementById('tpBaseLabel').textContent = 'Harga dasar: ' + tpFmt(data.product.price);
        document.getElementById('tpBasePrice').textContent = tpFmt(data.product.price);

        if (data.product.image) {
          var img     = document.getElementById('tpImg');
          img.src     = 'uploads/' + data.product.image;
          img.style.display = 'block';
        }

        document.getElementById('tpToppingSection').style.display = data.toppings.length ? 'block' : 'none';
        document.getElementById('tpLevelSection').style.display   = data.levels.length   ? 'block' : 'none';
        document.getElementById('tpLoading').style.display        = 'none';
        document.getElementById('tpContent').style.display        = 'block';
        tpRenderAll();
      })
      .catch(function() { alert('Gagal memuat data. Coba lagi.'); tpClose(); });
  };

  window.tpClose = function() {
    document.getElementById('toppingOverlay').style.display = 'none';
  };

  window.tpToggle = function(id) {
    tpState.selectedToppings[id] = !tpState.selectedToppings[id];
    tpRenderAll();
  };

  window.tpPickLevel = function(id) {
    tpState.selectedLevel = id;
    document.getElementById('tpLevelWarn').style.display = 'none';
    tpRenderAll();
  };

  window.tpChangeQty = function(d) {
    tpState.qty = Math.max(1, tpState.qty + d);
    tpUpdatePrice();
  };

  window.tpSubmit = function(action) {
    if (tpState.hasLevel && tpState.selectedLevel === null) {
      document.getElementById('tpLevelWarn').style.display = 'block';
      document.getElementById('tpLevelSection').scrollIntoView({ behavior: 'smooth' });
      return;
    }

    var toppingIds = [];
    for (var id in tpState.selectedToppings) {
      if (tpState.selectedToppings[id]) toppingIds.push(id);
    }

    var tpFd = new FormData();
    tpFd.append('action',      'add_cart');
    tpFd.append('product_id',  tpState.product.id);
    tpFd.append('qty',         tpState.qty);
    tpFd.append('level_id',    tpState.selectedLevel !== null ? tpState.selectedLevel : '');
    tpFd.append('topping_ids', JSON.stringify(toppingIds));

    document.getElementById('tpBtnCart').disabled = true;
    document.getElementById('tpBtnBuy').disabled  = true;

    fetch('api_topping.php', { method: 'POST', body: tpFd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          tpClose();
          if (action === 'buy') {
            window.location.href = 'checkout.php';
          } else {
            if (typeof updateCartCount === 'function') updateCartCount();
            var n = document.createElement('div');
            n.textContent  = 'Berhasil ditambahkan ke keranjang!';
            n.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:10px 18px;border-radius:99px;font-size:13px;z-index:99999;white-space:nowrap;';
            document.body.appendChild(n);
            setTimeout(function() { n.remove(); }, 2500);
          }
        } else {
          alert(data.error || 'Terjadi kesalahan');
        }
      })
      .catch(function() { alert('Gagal menghubungi server'); })
      .finally(function() {
        document.getElementById('tpBtnCart').disabled = false;
        document.getElementById('tpBtnBuy').disabled  = false;
      });
  };

  document.getElementById('toppingOverlay').addEventListener('click', function(e) {
    if (e.target === this) tpClose();
  });

})();
</script>