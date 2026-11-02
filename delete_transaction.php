<?php
/**
 * CashTrack - Delete Transaction API
 */
require_once __DIR__ . '/auth/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID giao dịch không hợp lệ!']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM `transactions` WHERE `id` = ? AND `user_id` = ?");
    $stmt->execute([$id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Đã xóa giao dịch thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy giao dịch hoặc bạn không có quyền xóa!']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi xóa dữ liệu: ' . $e->getMessage()]);
}
exit;
