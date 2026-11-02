<?php
/**
 * CashTrack - Add Planned Expense API
 */
require_once __DIR__ . '/auth/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức yêu cầu không hợp lệ!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$subcategory = trim($_POST['subcategory'] ?? '');
$expected_amount = floatval(str_replace([',', ' '], '', $_POST['expected_amount'] ?? '0'));
$expected_date = trim($_POST['expected_date'] ?? date('Y-m-d'));
$repeat_type = trim($_POST['repeat_type'] ?? 'monthly');
$repeat_interval = max(1, intval($_POST['repeat_interval'] ?? 1));
$day_of_month = isset($_POST['day_of_month']) && $_POST['day_of_month'] !== '' ? intval($_POST['day_of_month']) : null;
$note = trim($_POST['note'] ?? '');

$allowed_repeats = ['daily', 'weekly', 'monthly', 'yearly', 'custom_days', 'once'];
if (!in_array($repeat_type, $allowed_repeats)) {
    $repeat_type = 'monthly';
}

if (empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập hoặc chọn danh mục!']);
    exit;
}

if ($expected_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Số tiền dự kiến phải lớn hơn 0!']);
    exit;
}

if (empty($expected_date)) {
    $expected_date = date('Y-m-d');
}

// If day of month is not explicitly passed for monthly, deduce from expected_date
if ($repeat_type === 'monthly' && ($day_of_month === null || $day_of_month < 1 || $day_of_month > 31)) {
    $day_of_month = intval(date('d', strtotime($expected_date)));
}

try {
    // 1. Tự động lưu danh mục cha vào categories nếu chưa có
    $checkCat = $pdo->prepare("SELECT id FROM `categories` WHERE (user_id = ? OR user_id IS NULL) AND LOWER(name) = LOWER(?) AND parent_id IS NULL LIMIT 1");
    $checkCat->execute([$user_id, $category]);
    $catRow = $checkCat->fetch();
    $parentId = null;

    if (!$catRow) {
        $insertCat = $pdo->prepare("INSERT INTO `categories` (`user_id`, `name`, `type`, `parent_id`) VALUES (?, ?, 'expense', NULL)");
        $insertCat->execute([$user_id, $category]);
        $parentId = $pdo->lastInsertId();
    } else {
        $parentId = $catRow['id'];
    }

    // 2. Tự động lưu danh mục con nếu có
    if (!empty($subcategory) && $parentId) {
        $checkSub = $pdo->prepare("SELECT id FROM `categories` WHERE (user_id = ? OR user_id IS NULL) AND LOWER(name) = LOWER(?) AND parent_id = ? LIMIT 1");
        $checkSub->execute([$user_id, $subcategory, $parentId]);
        if (!$checkSub->fetch()) {
            $insertSub = $pdo->prepare("INSERT INTO `categories` (`user_id`, `name`, `type`, `parent_id`) VALUES (?, ?, 'expense', ?)");
            $insertSub->execute([$user_id, $subcategory, $parentId]);
        }
    }

    // 3. Thêm khoản chi dự kiến
    $stmt = $pdo->prepare("
        INSERT INTO `planned_expenses` (
            `user_id`, `title`, `category`, `subcategory`, 
            `expected_amount`, `repeat_type`, `repeat_interval`, 
            `day_of_month`, `expected_date`, `status`, `note`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([
        $user_id,
        !empty($title) ? $title : ($category . (!empty($subcategory) ? ' - ' . $subcategory : '')),
        $category,
        !empty($subcategory) ? $subcategory : null,
        $expected_amount,
        $repeat_type,
        $repeat_interval,
        $day_of_month,
        $expected_date,
        $note
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Đã thêm khoản chi dự kiến thành công!',
        'id' => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi lưu khoản chi dự kiến: ' . $e->getMessage()]);
}
exit;
