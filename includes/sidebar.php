<aside class="sidebar">
    <div class="p-3">
        <h4 class="text-primary"><i class="fas fa-pepper-hot me-2"></i>Texcer Hot</h4>
    </div>
    <nav class="nav flex-column px-2">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active bg-light' : '' ?>" href="dashboard.php">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active bg-light' : '' ?>" href="orders.php">
            <i class="fas fa-shopping-bag me-2"></i>Pesanan
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active bg-light' : '' ?>" href="products.php">
            <i class="fas fa-utensils me-2"></i>Produk
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active bg-light' : '' ?>" href="categories.php">
            <i class="fas fa-tags me-2"></i>Kategori
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active bg-light' : '' ?>" href="customers.php">
            <i class="fas fa-users me-2"></i>Pelanggan
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active bg-light' : '' ?>" href="settings.php">
            <i class="fas fa-cog me-2"></i>Pengaturan
        </a>
        <hr>
        <a class="nav-link text-danger" href="auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Keluar
        </a>
    </nav>
</aside>