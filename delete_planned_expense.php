<?php
/**
 * CashTrack - Delete Planned Expense API
 */
require_once __DIR__ . '/auth/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức yêu cầu không hợp lệ!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mã khoản chi dự kiến không hợp lệ!']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM `planned_expenses` WHERE `id` = ? AND `user_id` = ?");
    $stmt->execute([$id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Đã xóa khoản chi dự kiến thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy khoản chi dự kiến để xóa!']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi xóa khoản chi dự kiến: ' . $e->getMessage()]);
}
exit;
