<?php
/**
 * CashTrack - Database Configuration & PDO Connection
 * Tự động chuyển đổi giữa Localhost (WAMP) và Hosting (InfinityFree)
 */

// Tự động nhận diện localhost (trình duyệt hoặc CLI) hay Hosting
$is_local = !isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false;

if ($is_local) {
    // 1. Cấu hình chạy trên máy tính cá nhân (WAMP Localhost)
    $db_host = '127.0.0.1';
    $db_name = 'cashtrack';
    $db_user = 'root';
    $db_pass = '';
} else {
    // 2. Cấu hình khi đưa lên Hosting InfinityFree
    // (Điền thông tin bạn lấy được ở mục MySQL Databases trên InfinityFree vào đây)
    $db_host = 'sqlXXX.infinityfree.com'; // Thay bằng MySQL Hostname
    $db_name = 'if0_XXXXXXXX_cashtrack'; // Thay bằng Database Name
    $db_user = 'if0_XXXXXXXX';           // Thay bằng MySQL Username
    $db_pass = 'YOUR_PASSWORD';          // Mật khẩu tài khoản Hosting của bạn
}

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . htmlspecialchars($e->getMessage()));
}
