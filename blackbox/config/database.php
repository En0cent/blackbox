<?php
// blackbox/config/database.php
session_start();

$host = 'localhost';
$dbname = 'sweet_factory';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");
    
    // Create all tables (including image_uploads)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'common', 'vip') DEFAULT 'common',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            stock_quantity INT NOT NULL DEFAULT 0,
            image VARCHAR(255),
            target_role ENUM('common', 'vip', 'all') DEFAULT 'all',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS image_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS cart (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            total_amount DECIMAL(10,2) NOT NULL,
            receipt_number VARCHAR(50) UNIQUE NOT NULL,
            status VARCHAR(20) DEFAULT 'completed',
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id)
        );
        
        CREATE TABLE IF NOT EXISTS stock_movements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            movement_type ENUM('addition', 'removal') NOT NULL,
            reference VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id)
        );
    ");
    
    // Ensure target_role column exists (for older installations)
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN target_role ENUM('common', 'vip', 'all') DEFAULT 'all'");
    } catch(PDOException $e) {}
    
    // Insert default admin
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, email, phone, password, role) VALUES (?, ?, ?, ?, 'admin')")
            ->execute(['admin', 'admin@sweetfactory.com', '1234567890', $hashed]);
    }
    
    // Auto-create uploads directory (if missing)
    $uploads_dir = __DIR__ . '/../uploads/';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
    
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>