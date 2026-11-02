-- ==========================================================
-- CashTrack Database Schema & Seed Data
-- Hệ quản trị CSDL: MySQL / MariaDB (HeidiSQL compatible)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `cashtrack` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cashtrack`;

-- ----------------------------------------------------------
-- 1. Bảng `users` (Người dùng & Quản trị viên)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `login_logs`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'user') DEFAULT 'user',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Bảng `categories` (Quản lý Danh mục Thu - Chi)
-- ----------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `parent_id` INT NULL DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('income', 'expense', 'both') DEFAULT 'both',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`parent_id`),
  CONSTRAINT `fk_categories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Bảng `transactions` (Quản lý Thu - Chi dòng tiền CashTrack)
-- ----------------------------------------------------------
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('income', 'expense') NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `subcategory` VARCHAR(100) NULL DEFAULT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`transaction_date`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Bảng `planned_expenses` (Quản lý Khoản chi dự kiến & Chu kỳ)
-- ----------------------------------------------------------
CREATE TABLE `planned_expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NULL,
  `category` VARCHAR(100) NOT NULL,
  `subcategory` VARCHAR(100) NULL DEFAULT NULL,
  `expected_amount` DECIMAL(15,2) NOT NULL,
  `repeat_type` ENUM('daily', 'weekly', 'monthly', 'yearly', 'custom_days', 'once') NOT NULL DEFAULT 'monthly',
  `repeat_interval` INT DEFAULT 1,
  `day_of_month` TINYINT NULL,
  `expected_date` DATE NOT NULL,
  `actual_date` DATE NULL,
  `actual_amount` DECIMAL(15,2) NULL,
  `status` ENUM('pending', 'completed') NOT NULL DEFAULT 'pending',
  `note` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`expected_date`),
  INDEX (`status`),
  CONSTRAINT `fk_planned_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Bảng `login_logs` (Nhật ký truy cập và kiểm tra an ninh)
-- ----------------------------------------------------------
CREATE TABLE `login_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('success', 'failed') NOT NULL,
  `attempted_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Dữ liệu mẫu (Seed Data)
-- Mật khẩu mặc định: admin123 (cho admin) và demo123 (cho demo)
-- ----------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `email`, `password`, `fullname`, `role`) VALUES
(1, 'admin', 'admin@cashtrack.local', '$2y$10$pQzspRuIuouKOYTywN4U3ePNQJmzYUfiZLglwksm6XK/wX30jmCBa', 'Quản trị viên Hệ thống', 'admin'),
(2, 'demo', 'demo@cashtrack.local', '$2y$10$RaQNQb5beaUJsOrOg9E7uOxc9MGvZnXbWSOU4UJZC7tC0wvlcfcsK', 'Người dùng Demo', 'user');

INSERT INTO `categories` (`user_id`, `name`, `type`) VALUES
(NULL, 'Lương & Thưởng', 'income'),
(NULL, 'Thu nhập kinh doanh', 'income'),
(NULL, 'Bán hàng', 'income'),
(NULL, 'Đầu tư cổ tức', 'income'),
(NULL, 'Thanh toán khách hàng', 'income'),
(NULL, 'Thu nhập khác', 'income'),
(NULL, 'Ăn uống & Sinh hoạt', 'expense'),
(NULL, 'Tiền thuê phòng / văn phòng', 'expense'),
(NULL, 'Chi phí máy chủ & IT', 'expense'),
(NULL, 'Đi lại / Xăng xe', 'expense'),
(NULL, 'Mua sắm thiết bị', 'expense'),
(NULL, 'Hóa đơn điện nước, mạng', 'expense'),
(NULL, 'Chi phí giải trí & Cafe', 'expense'),
(NULL, 'Khoản chi khác', 'expense');

INSERT INTO `transactions` (`user_id`, `type`, `category`, `amount`, `transaction_date`, `note`) VALUES
(1, 'income', 'Thu nhập kinh doanh', 35000000.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Doanh thu hợp đồng dịch vụ Q4'),
(1, 'income', 'Đầu tư cổ tức', 8500000.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Cổ tức nhận từ danh mục đầu tư'),
(1, 'expense', 'Chi phí máy chủ & Cloud', 2800000.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Hạ tầng AWS / Cloudflare'),
(1, 'expense', 'Thuê văn phòng', 12000000.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Tiền thuê mặt bằng tháng 11'),
(1, 'income', 'Thanh toán khách hàng', 15000000.00, CURDATE(), 'Thu tiền đợt 2 dự án Remini App');
