<?php
/**
 * Setup Database and Seed Initial Data for CashTrack
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'cashtrack';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create DB if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Table 1: users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `fullname` VARCHAR(100) NOT NULL,
            `role` ENUM('admin', 'user') DEFAULT 'admin',
            `avatar` VARCHAR(255) DEFAULT NULL,
            `remember_token` VARCHAR(100) DEFAULT NULL,
            `status` TINYINT(1) DEFAULT 1,
            `last_login` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Table 2: transactions (for CashTrack cashflow management)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `transactions` (
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
    ");

    // Table 3: categories (Quản lý danh mục thu chi & danh mục con)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `categories` (
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
    ");

    // Table 4: planned_expenses (Khoản chi dự kiến & chu kỳ)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `planned_expenses` (
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
    ");

    // Table 4: login_logs (tracks security login attempts)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('success', 'failed') NOT NULL,
            `attempted_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Seed default admin account if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
    $stmt->execute(['admin']);
    if ($stmt->fetchColumn() == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $insertAdmin = $pdo->prepare("
            INSERT INTO `users` (`username`, `email`, `password`, `fullname`, `role`)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insertAdmin->execute([
            'admin',
            'admin@cashtrack.local',
            $adminPass,
            'Quản trị viên Hệ thống',
            'admin'
        ]);
        $adminId = $pdo->lastInsertId();
        echo "Created default admin account: admin / admin123\n";

        // Seed demo transactions for admin
        $transStmt = $pdo->prepare("
            INSERT INTO `transactions` (`user_id`, `type`, `category`, `amount`, `transaction_date`, `note`)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $sampleData = [
            [$adminId, 'income', 'Thu nhập kinh doanh', 35000000, date('Y-m-d', strtotime('-5 days')), 'Doanh thu hợp đồng dịch vụ Q4'],
            [$adminId, 'income', 'Đầu tư cổ tức', 8500000, date('Y-m-d', strtotime('-3 days')), 'Cổ tức nhận từ danh mục đầu tư'],
            [$adminId, 'expense', 'Chi phí máy chủ & Cloud', 2800000, date('Y-m-d', strtotime('-2 days')), 'Hạ tầng AWS / Cloudflare'],
            [$adminId, 'expense', 'Thuê văn phòng', 12000000, date('Y-m-d', strtotime('-1 days')), 'Tiền thuê mặt bằng tháng 11'],
            [$adminId, 'income', 'Thanh toán khách hàng', 15000000, date('Y-m-d'), 'Thu tiền đợt 2 dự án Remini App']
        ];
        foreach ($sampleData as $item) {
            $transStmt->execute($item);
        }
        echo "Seeded 5 sample transactions!\n";
    }

    // Seed demo user account if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
    $stmt->execute(['demo']);
    if ($stmt->fetchColumn() == 0) {
        $demoPass = password_hash('demo123', PASSWORD_DEFAULT);
        $insertDemo = $pdo->prepare("
            INSERT INTO `users` (`username`, `email`, `password`, `fullname`, `role`)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insertDemo->execute([
            'demo',
            'demo@cashtrack.local',
            $demoPass,
            'Người dùng Demo',
            'user'
        ]);
        echo "Created demo account: demo / demo123\n";
    }

    // Seed default categories if empty
    $catCount = $pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();
    if ($catCount == 0) {
        $defaultCategories = [
            // Thu nhập
            [null, 'Lương & Thưởng', 'income'],
            [null, 'Thu nhập kinh doanh', 'income'],
            [null, 'Bán hàng', 'income'],
            [null, 'Đầu tư cổ tức', 'income'],
            [null, 'Thanh toán khách hàng', 'income'],
            [null, 'Thu nhập khác', 'income'],
            // Khoản chi
            [null, 'Ăn uống & Sinh hoạt', 'expense'],
            [null, 'Tiền thuê phòng / văn phòng', 'expense'],
            [null, 'Chi phí máy chủ & IT', 'expense'],
            [null, 'Đi lại / Xăng xe', 'expense'],
            [null, 'Mua sắm thiết bị', 'expense'],
            [null, 'Hóa đơn điện nước, mạng', 'expense'],
            [null, 'Chi phí giải trí & Cafe', 'expense'],
            [null, 'Khoản chi khác', 'expense']
        ];
        $catStmt = $pdo->prepare("INSERT INTO `categories` (`user_id`, `name`, `type`) VALUES (?, ?, ?)");
        foreach ($defaultCategories as $cat) {
            $catStmt->execute($cat);
        }
        echo "Seeded " . count($defaultCategories) . " default categories!\n";
    }

    echo "Database setup completed successfully!\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}
