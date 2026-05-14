CREATE DATABASE IF NOT EXISTS texcer1;
USE texcer1;

-- Tabel Menu Produk
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    description TEXT
);

-- Masukkan Data Menu (Path gambar mengarah ke folder assets/images)
INSERT INTO products (name, category, price, image, description) VALUES
('Mie Yamin Pedas', 'Mie', 15000, 'assets/images/mie1.jpg', 'Mie yamin spesial level pedas.'),
('Mie Ayam Bakso', 'Mie', 18000, 'assets/images/mie2.jpg', 'Mie ayam dengan bakso sapi jumbo.'),
('Keripik Mercon', 'Mercon', 12000, 'assets/images/mercon1.jpg', 'Keripik singkong pedas nampol.'),
('Keripik Mercon Lvl 2', 'Mercon', 15000, 'assets/images/mercon2.jpg', 'Keripik pedas level 2.'),
('Dimsum Hakau', 'Dimsum', 15000, 'assets/images/dimsum1.jpg', 'Hakau udang segar.'),
('Dimsum Siomay', 'Dimsum', 12000, 'assets/images/dimsum2.jpg', 'Siomay ayam lembut.'),
('Dimsum Ayam', 'Dimsum', 13000, 'assets/images/dimsum3.jpg', 'Dimsum isi ayam spesial.'),
('Es Teh Manis', 'Minuman', 5000, 'assets/images/drink1.jpg', 'Teh manis segar.'),
('Es Jeruk Peras', 'Minuman', 8000, 'assets/images/drink2.jpg', 'Jeruk murni diperas.'),
('Es Kopi Susu', 'Minuman', 15000, 'assets/images/drink3.jpg', 'Kopi susu gula aren.'),
('Jus Alpukat', 'Minuman', 15000, 'assets/images/drink4.jpg', 'Alpukat mentega kental.'),
('Air Mineral', 'Minuman', 4000, 'assets/images/drink5.jpg', 'Air mineral 600ml.');

-- Tabel Pesanan (Orders)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100),
    total_price INT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Detail Pesanan (Item per Order)
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_name VARCHAR(100),
    qty INT,
    subtotal INT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);