<?php
/**
 * CashTrack - Get Categories API
 */
require_once __DIR__ . '/auth/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'all';
$parent_id = isset($_GET['parent_id']) && $_GET['parent_id'] !== '' ? intval($_GET['parent_id']) : null;

try {
    $sql = "SELECT id, user_id, parent_id, name, type FROM `categories` WHERE (user_id = ? OR user_id IS NULL)";
    $params = [$user_id];

    if ($type === 'income' || $type === 'expense') {
        $sql .= " AND (type = ? OR type = 'both')";
        $params[] = $type;
    }

    if ($parent_id !== null) {
        $sql .= " AND parent_id = ?";
        $params[] = $parent_id;
    }

    $sql .= " ORDER BY parent_id ASC, user_id DESC, type ASC, name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi tải danh mục: ' . $e->getMessage()]);
}
exit;
