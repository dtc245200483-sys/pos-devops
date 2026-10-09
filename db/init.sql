SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Database Schema and Initial Seed for POS System
CREATE DATABASE IF NOT EXISTS `pos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pos`;

-- 1. Table users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('manager', 'staff') NOT NULL,
    `is_active` TINYINT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table products
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `barcode` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(12,0) NOT NULL,
    `stock_qty` INT NOT NULL,
    `status` ENUM('in_stock', 'out_of_stock') NOT NULL DEFAULT 'in_stock',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_stock_qty` CHECK (`stock_qty` >= 0),
    INDEX `idx_products_barcode` (`barcode`),
    INDEX `idx_products_code` (`code`),
    INDEX `idx_products_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table orders
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `total_amount` DECIMAL(12,0) NOT NULL,
    `payment_method` ENUM('cash', 'card', 'qr') NOT NULL,
    `amount_paid` DECIMAL(12,0) NOT NULL,
    `change_amount` DECIMAL(12,0) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
    INDEX `idx_orders_user_id` (`user_id`),
    INDEX `idx_orders_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table order_details
CREATE TABLE IF NOT EXISTS `order_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(12,0) NOT NULL,
    CONSTRAINT `chk_detail_qty` CHECK (`quantity` > 0),
    CONSTRAINT `fk_details_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_details_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
    INDEX `idx_order_details_order_id` (`order_id`),
    INDEX `idx_order_details_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Accounts
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
('admin', '$2y$10$WFsqW5OgGous59Q3IwAXmuGSecqvYDFHedvG2dGpnHju5kbN4paLS', 'Quản lý Hệ thống (Admin)', 'manager', 1),
('nhanvien01', '$2y$10$Wwz4pFO57WGXu3ngIJXRFOeJ/aUYMA4rTP62bCgPswaB2H3/3UD7q', 'Nhân viên Bán hàng 01', 'staff', 1)
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Seed 15 Grocery Products
INSERT INTO `products` (`code`, `barcode`, `name`, `price`, `stock_qty`, `status`) VALUES
('SP001', '893500110001', 'Gạo ST25 Ông Cua (Túi 5kg)', 185000, 45, 'in_stock'),
('SP002', '893500110002', 'Dầu ăn đậu nành Simply 1L', 58000, 80, 'in_stock'),
('SP003', '893500110003', 'Nước mắm Nam Ngư Đệ Nhị 750ml', 32000, 110, 'in_stock'),
('SP004', '893500110004', 'Đường tinh luyện Biên Hòa 1kg', 29000, 60, 'in_stock'),
('SP005', '893500110005', 'Hạt nêm Knorr thịt thăn 400g', 36000, 75, 'in_stock'),
('SP006', '893500110006', 'Mì Hảo Hảo tôm chua cay (Thùng 30 gói)', 125000, 35, 'in_stock'),
('SP007', '893500110007', 'Sữa tươi tiệt trùng Vinamilk có đường 1L', 38000, 90, 'in_stock'),
('SP008', '893500110008', 'Nước tương Maggi Đậm Đặc 700ml', 27000, 65, 'in_stock'),
('SP009', '893500110009', 'Cà phê G7 3in1 Trung Nguyên (Hộp 18 gói)', 62000, 50, 'in_stock'),
('SP010', '893500110010', 'Nước ngọt Coca-Cola Sleek 320ml (Lốc 6 lon)', 59000, 40, 'in_stock'),
('SP011', '893500110011', 'Trà xanh Không Độ 455ml (Chai)', 10000, 150, 'in_stock'),
('SP012', '893500110012', 'Bánh Chocopie Orion (Hộp 12 cái)', 55000, 40, 'in_stock'),
('SP013', '893500110013', 'Nước rửa chén Sunlight Chanh 750g', 34000, 55, 'in_stock'),
('SP014', '893500110014', 'Bột giặt OMO Đỏ Hương ban mai 3kg', 149000, 25, 'in_stock'),
('SP015', '893500110015', 'Kem đánh răng P/S Trà xanh 180g', 31000, 70, 'in_stock')
ON DUPLICATE KEY UPDATE `code`=`code`;

-- MySQL Exporter User for Prometheus Monitoring
CREATE USER IF NOT EXISTS 'exporter'@'%' IDENTIFIED BY 'change_this_exporter_password_min16chars' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'exporter'@'%';
GRANT SELECT ON performance_schema.* TO 'exporter'@'%';
FLUSH PRIVILEGES;
