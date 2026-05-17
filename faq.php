<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Texcer Hot</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: #f9f9f9;
            color: #333;
        }

        /* ===== NAVBAR SEDERHANA ===== */
        .navbar {
            background: #4A3728;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar .logo {
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            text-decoration: none;
        }

        .navbar a.nav-home {
            color: #F5EBE0;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .navbar a.nav-home:hover {
            color: #fff;
        }

        /* ===== FAQ CONTAINER ===== */
        .faq-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            min-height: 60vh;
        }

        .faq-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .faq-header h1 {
            color: #4A3728;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .faq-header p {
            color: #666;
            font-size: 1rem;
            font-weight: 400;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #8B6F47;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        /* ===== KATEGORI ===== */
        .faq-category {
            margin-top: 35px;
        }

        .faq-category h3 {
            color: #4A3728;
            font-size: 1.1rem;
            font-weight: 700;
            border-bottom: 3px solid #8B6F47;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        /* ===== FAQ ITEM ===== */
        .faq-item {
            background: #fff;
            border-radius: 10px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .faq-question {
            padding: 18px 25px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FDF8F3;
            font-weight: 700;
            font-size: 0.95rem;
            color: #4A3728;
            transition: background 0.3s;
            user-select: none;
        }

        .faq-question:hover {
            background: #F5EBE0;
        }

        .faq-toggle {
            flex-shrink: 0;
            margin-left: 15px;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
        }

        .faq-toggle svg {
            width: 22px;
            height: 22px;
            stroke: #8B6F47;
        }

        .faq-item.active .faq-toggle {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 25px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            color: #444;
            font-size: 0.92rem;
            font-weight: 400;
            line-height: 1.7;
        }

        .faq-item.active .faq-answer {
            padding: 18px 25px;
            max-height: 500px;
        }

        .faq-answer ol,
        .faq-answer ul {
            margin-left: 20px;
            margin-top: 8px;
        }

        .faq-answer li {
            margin-bottom: 4px;
        }

        /* ===== CTA BAWAH ===== */
        .faq-cta {
            text-align: center;
            margin-top: 40px;
            padding: 30px;
            background: #FDF8F3;
            border-radius: 10px;
        }

        .faq-cta h3 {
            color: #4A3728;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .faq-cta p {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 28px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.85;
        }

        .btn-wa {
            background: #25D366;
            color: white;
        }

        .btn-email {
            background: #8B6F47;
            color: white;
        }

        /* ===== FOOTER SEDERHANA ===== */
        .footer {
            background: #4A3728;
            color: #F5EBE0;
            text-align: center;
            padding: 20px;
            margin-top: 60px;
            font-size: 0.85rem;
            font-weight: 400;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="index.php" class="logo">🌶️ Texcer Hot</a>
    <a href="index.php" class="nav-home">← Beranda</a>
</nav>

<!-- FAQ KONTEN -->
<div class="faq-container">

    <div class="faq-header">
        <h1>Pertanyaan yang Sering Diajukan</h1>
        <p>Temukan jawaban atas pertanyaan umum tentang Texcer Hot</p>
    </div>

    <!-- KATEGORI 1 -->
    <div class="faq-category">
        <h3>🍽️ Pemesanan & Menu</h3>

        <div class="faq-item">
            <div class="faq-question">
                Bagaimana cara memesan makanan di Texcer Hot?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Anda bisa memesan melalui website ini dengan cara:</p>
                <ol>
                    <li>Pilih menu yang diinginkan</li>
                    <li>Klik tombol "Tambah ke Keranjang"</li>
                    <li>Lihat keranjang belanja Anda</li>
                    <li>Klik "Checkout" dan isi data pengiriman</li>
                    <li>Pilih metode pembayaran</li>
                    <li>Konfirmasi pesanan Anda</li>
                </ol>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                Apakah ada minimal pemesanan?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Untuk area dekat (radius 3 km), tidak ada minimal pemesanan. Namun untuk area yang lebih jauh, ada minimal pemesanan <strong>Rp 50.000</strong>.</p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                Bisakah saya request level kepedasan?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Tentu! Kami menyediakan 5 level kepedasan:</p>
                <ul>
                    <li>Level 0: Tidak pedas</li>
                    <li>Level 1: Sedikit pedas</li>
                    <li>Level 2: Pedas sedang</li>
                    <li>Level 3: Pedas</li>
                    <li>Level 4: Super pedas (Extreme!)</li>
                </ul>
                <p style="margin-top:8px;">Pilih level kepedasan saat menambahkan menu ke keranjang.</p>
            </div>
        </div>
    </div>

    <!-- KATEGORI 2 -->
    <div class="faq-category">
        <h3>🚚 Pengiriman</h3>

        <div class="faq-item">
            <div class="faq-question">
                Berapa lama waktu pengiriman?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Waktu pengiriman tergantung lokasi:</p>
                <ul>
                    <li>Area Bangsri: 20–30 menit</li>
                    <li>Area Jepara kota: 30–45 menit</li>
                    <li>Area sekitar (±10 km): 45–60 menit</li>
                </ul>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                Berapa biaya pengiriman?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Biaya pengiriman dihitung berdasarkan jarak:</p>
                <ul>
                    <li>0–3 km: Rp 5.000</li>
                    <li>3–5 km: Rp 10.000</li>
                    <li>5–10 km: Rp 15.000</li>
                    <li>&gt;10 km: Hubungi kami untuk info lebih lanjut</li>
                </ul>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                Apakah bisa ambil sendiri (pickup)?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Bisa! Pilih opsi <strong>"Ambil Sendiri"</strong> saat checkout dan dapatkan diskon <strong>10%</strong> untuk pesanan pickup.</p>
            </div>
        </div>
    </div>

    <!-- KATEGORI 3 -->
    <div class="faq-category">
        <h3>💳 Pembayaran</h3>

        <div class="faq-item">
            <div class="faq-question">
                Metode pembayaran apa saja yang tersedia?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Kami menerima berbagai metode pembayaran:</p>
                <ul>
                    <li>Tunai (COD - Cash on Delivery)</li>
                    <li>Transfer Bank (BCA, BNI, Mandiri)</li>
                    <li>E-wallet (GoPay, OVO, DANA, ShopeePay)</li>
                    <li>QRIS</li>
                </ul>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                Apakah harus bayar di muka?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Untuk pesanan pertama atau pesanan dengan nilai besar, kami meminta <strong>DP 50%</strong>. Untuk pelanggan regular, bisa bayar di tempat (COD).</p>
            </div>
        </div>
    </div>

    <!-- KATEGORI 4 -->
    <div class="faq-category">
        <h3>🔄 Pembatalan & Komplain</h3>

        <div class="faq-item">
            <div class="faq-question">
                Bisakah saya membatalkan pesanan?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Pembatalan bisa dilakukan maksimal <strong>10 menit</strong> setelah pesanan dikonfirmasi. Setelah makanan mulai dimasak, pesanan tidak dapat dibatalkan.</p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                Bagaimana jika pesanan saya salah atau ada masalah?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Hubungi kami segera melalui WhatsApp <strong>6281934174198</strong> atau email <strong>hello@texcerhot.com</strong>. Kami akan segera menyelesaikan masalah Anda dengan penggantian atau refund.</p>
            </div>
        </div>
    </div>

    <!-- KATEGORI 5 -->
    <div class="faq-category">
        <h3>📞 Kontak & Informasi Lain</h3>

        <div class="faq-item">
            <div class="faq-question">
                Kapan jam operasional Texcer Hot?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Kami buka setiap hari termasuk Sabtu, Minggu, dan hari libur nasional:</p>
                <p style="margin-top:8px; font-size:1.05rem;"><strong>⏰ 11:00 – 21:00 WIB</strong></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                Apakah ada program loyalitas atau diskon?
                <span class="faq-toggle"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 8 12 16 20 8"/></svg></span>
            </div>
            <div class="faq-answer">
                <p>Ya! Kami memiliki beberapa program menarik:</p>
                <ul>
                    <li>Diskon 10% untuk pesanan pertama</li>
                    <li>Point reward setiap pembelian (1 poin per Rp 10.000)</li>
                    <li>Promo spesial di hari-hari tertentu</li>
                    <li>Diskon ulang tahun</li>
                </ul>
                <p style="margin-top:8px;">Follow media sosial kami untuk info promo terbaru!</p>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="faq-cta">
        <h3>Masih Ada Pertanyaan?</h3>
        <p>Tim kami siap membantu Anda</p>
        <div class="btn-group">
            <a href="https://wa.me/6281934174198" class="btn btn-wa">Chat WhatsApp</a>
            <a href="mailto:hello@texcerhot.com" class="btn btn-email">Kirim Email</a>
        </div>
    </div>

</div>

<!-- FOOTER -->
<div class="footer">
    &copy; 2024 Texcer Hot. All rights reserved.
</div>

<script>
document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
        const item = question.parentElement;
        const isActive = item.classList.contains('active');

        document.querySelectorAll('.faq-item').forEach(faq => {
            faq.classList.remove('active');
        });

        if (!isActive) {
            item.classList.add('active');
        }
    });
});
</script>

</body>
</html>