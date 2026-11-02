<?php
/**
 * CashTrack - Add Transaction API
 */
require_once __DIR__ . '/auth/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$type = $_POST['type'] ?? 'expense';
$category = trim($_POST['category'] ?? '');
$subcategory = trim($_POST['subcategory'] ?? '');
$amount = floatval(str_replace([',', ' '], '', $_POST['amount'] ?? '0'));
$date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
$note = trim($_POST['note'] ?? '');

if (!in_array($type, ['income', 'expense'])) {
    echo json_encode(['success' => false, 'message' => 'Loại giao dịch không hợp lệ!']);
    exit;
}

if (empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập hoặc chọn danh mục!']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Số tiền phải lớn hơn 0!']);
    exit;
}

try {
    // 1. Tự động lưu danh mục cha mới vào bảng categories nếu chưa có
    $checkCat = $pdo->prepare("SELECT id FROM `categories` WHERE (user_id = ? OR user_id IS NULL) AND LOWER(name) = LOWER(?) AND parent_id IS NULL LIMIT 1");
    $checkCat->execute([$user_id, $category]);
    $catRow = $checkCat->fetch();
    $parentId = null;

    if (!$catRow) {
        $insertCat = $pdo->prepare("INSERT INTO `categories` (`user_id`, `name`, `type`, `parent_id`) VALUES (?, ?, ?, NULL)");
        $insertCat->execute([$user_id, $category, $type]);
        $parentId = $pdo->lastInsertId();
    } else {
        $parentId = $catRow['id'];
    }

    // 2. Tự động lưu danh mục con nếu có nhập
    if (!empty($subcategory) && $parentId) {
        $checkSub = $pdo->prepare("SELECT id FROM `categories` WHERE (user_id = ? OR user_id IS NULL) AND LOWER(name) = LOWER(?) AND parent_id = ? LIMIT 1");
        $checkSub->execute([$user_id, $subcategory, $parentId]);
        if (!$checkSub->fetch()) {
            $insertSub = $pdo->prepare("INSERT INTO `categories` (`user_id`, `name`, `type`, `parent_id`) VALUES (?, ?, ?, ?)");
            $insertSub->execute([$user_id, $subcategory, $type, $parentId]);
        }
    }

    // 3. Lưu giao dịch
    $stmt = $pdo->prepare("
        INSERT INTO `transactions` (`user_id`, `type`, `category`, `subcategory`, `amount`, `transaction_date`, `note`)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $type, $category, !empty($subcategory) ? $subcategory : null, $amount, $date, $note]);

    echo json_encode([
        'success' => true,
        'message' => 'Đã thêm giao dịch thành công!',
        'id' => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi lưu dữ liệu: ' . $e->getMessage()]);
}
exit;
