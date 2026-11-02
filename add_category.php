<?php
/**
 * CashTrack - Add Category API
 */
require_once __DIR__ . '/auth/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức yêu cầu không hợp lệ!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$type = trim($_POST['type'] ?? 'both');
$parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên danh mục!']);
    exit;
}

if (!in_array($type, ['income', 'expense', 'both'])) {
    $type = 'both';
}

try {
    // If parent_id provided, inherit type from parent if needed
    if ($parent_id) {
        $parentCheck = $pdo->prepare("SELECT id, name, type FROM `categories` WHERE id = ?");
        $parentCheck->execute([$parent_id]);
        $parentCat = $parentCheck->fetch();
        if (!$parentCat) {
            echo json_encode(['success' => false, 'message' => 'Danh mục cha không tồn tại!']);
            exit;
        }
        if ($type === 'both' && !empty($parentCat['type']) && $parentCat['type'] !== 'both') {
            $type = $parentCat['type'];
        }
    }

    // Check if category name already exists at this level
    if ($parent_id) {
        $checkStmt = $pdo->prepare("
            SELECT id, name, type, parent_id FROM `categories` 
            WHERE (user_id = ? OR user_id IS NULL) AND LOWER(name) = LOWER(?) AND parent_id = ?
            LIMIT 1
        ");
        $checkStmt->execute([$user_id, $name, $parent_id]);
    } else {
        $checkStmt = $pdo->prepare("
            SELECT id, name, type, parent_id FROM `categories` 
            WHERE (user_id = ? OR user_id IS NULL) AND LOWER(name) = LOWER(?) AND parent_id IS NULL
            LIMIT 1
        ");
        $checkStmt->execute([$user_id, $name]);
    }
    $existing = $checkStmt->fetch();

    if ($existing) {
        echo json_encode([
            'success' => true,
            'message' => 'Danh mục đã có sẵn trong danh sách!',
            'already_exists' => true,
            'category' => $existing
        ]);
        exit;
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO `categories` (`user_id`, `parent_id`, `name`, `type`) 
        VALUES (?, ?, ?, ?)
    ");
    $insertStmt->execute([$user_id, $parent_id, $name, $type]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => $parent_id ? 'Đã thêm danh mục con "' . htmlspecialchars($name) . '" thành công!' : 'Đã thêm danh mục "' . htmlspecialchars($name) . '" thành công!',
        'category' => [
            'id' => $newId,
            'parent_id' => $parent_id,
            'name' => $name,
            'type' => $type
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
exit;
