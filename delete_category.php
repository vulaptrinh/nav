<?php
/**
 * CashTrack - Delete Category API
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
    echo json_encode(['success' => false, 'message' => 'Mã danh mục không hợp lệ!']);
    exit;
}

try {
    // Delete subcategories if any
    if ($_SESSION['role'] === 'admin') {
        $subStmt = $pdo->prepare("DELETE FROM `categories` WHERE parent_id = ?");
        $subStmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM `categories` WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $subStmt = $pdo->prepare("DELETE FROM `categories` WHERE parent_id = ? AND user_id = ?");
        $subStmt->execute([$id, $user_id]);

        $stmt = $pdo->prepare("DELETE FROM `categories` WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    }

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Đã xóa danh mục thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy danh mục hoặc danh mục mặc định của hệ thống không thể xóa!']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi xóa danh mục: ' . $e->getMessage()]);
}
exit;
