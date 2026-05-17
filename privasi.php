<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kebijakan Privasi - Texcer Hot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #FDF8F3;
            color: #4A3728;
            line-height: 1.6;
        }
        a { text-decoration: none; color: inherit; transition: 0.3s; }
        
        /* Navbar */
        .navbar {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 1rem 5%;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand {
            font-size: 1.6rem;
            font-weight: 700;
            color: #8B6F47;
        }
        .nav-links { display: flex; gap: 2rem; }
        .nav-links a { font-weight: 500; color: #4A3728; }
        .nav-links a:hover { color: #8B6F47; }
        
        /* Content */
        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            min-height: 60vh;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #8B6F47;
            font-weight: 500;
        }
        .back-link:hover { text-decoration: underline; }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .page-header h1 {
            color: #8B6F47;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .page-header p {
            color: #666;
            font-size: 1rem;
        }
        
        .privacy-section {
            background: #fff;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #8B6F47;
        }
        .privacy-section h2 {
            color: #4A3728;
            margin-bottom: 15px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .privacy-section h2 i {
            color: #8B6F47;
        }
        .privacy-section p {
            color: #555;
            margin-bottom: 10px;
            text-align: justify;
        }
        
        /* Footer Sederhana */
        footer {
            background: #422816;
            color: #E8D5C4;
            padding: 2rem 5%;
            margin-top: 4rem;
            text-align: center;
        }
        footer p {
            margin: 0.5rem 0;
        }
        footer strong {
            color: #D4A574;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="brand">Texcer Hot</a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="menu.php">Menu</a>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <div class="container">
        <a href="index.php" class="back-link">← Kembali ke Beranda</a>
        
        <div class="page-header">
            <h1>Kebijakan Privasi</h1>
            <p>Kami berkomitmen melindungi data pribadi Anda</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-user-shield"></i> 1. Informasi yang Kami Kumpulkan</h2>
            <p>Kami mengumpulkan informasi yang Anda berikan secara langsung saat membuat pesanan, seperti nama, nomor telepon, alamat pengiriman, dan metode pembayaran.</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-database"></i> 2. Penggunaan Informasi</h2>
            <p>Data Anda digunakan untuk memproses pesanan, mengirim notifikasi status pesanan, dan meningkatkan layanan kami. Kami tidak akan menjual data Anda ke pihak ketiga.</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-lock"></i> 3. Keamanan Data</h2>
            <p>Kami menggunakan langkah-langkah keamanan teknis untuk melindungi data pribadi Anda dari akses tidak sah, perubahan, atau pengungkapan.</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-cookie-bite"></i> 4. Cookie</h2>
            <p>Website kami menggunakan cookie untuk meningkatkan pengalaman pengguna. Anda dapat memilih untuk menonaktifkan cookie melalui pengaturan browser Anda.</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-envelope"></i> 5. Kontak</h2>
            <p>Jika Anda memiliki pertanyaan mengenai kebijakan privasi ini, silakan hubungi kami di:</p>
            <ul style="margin-left: 20px; color: #555;">
                <li>Email: hello@texcerhot.com</li>
                <li>WhatsApp: 6281934174198</li>
            </ul>
        </div>
    </div>

    <!-- Footer Sederhana -->
    <footer>
        <p>&copy; 2020 - 2026 <strong>Texcer Hot</strong>. Berdiri sejak 2020.</p>
        <p>Didirikan oleh <strong>Ahmad Amrullah Baharuddin</strong>. All rights reserved.</p>
    </footer>
</body>
</html>