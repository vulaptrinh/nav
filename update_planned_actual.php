<?php
/**
 * CashTrack - Update Planned Expense Actual API (Xác nhận thực chi)
 */
require_once __DIR__ . '/auth/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức yêu cầu không hợp lệ!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$id = intval($_POST['id'] ?? 0);
$actual_date = trim($_POST['actual_date'] ?? date('Y-m-d'));
$actual_amount = floatval(str_replace([',', ' '], '', $_POST['actual_amount'] ?? '0'));
$record_to_trans = !empty($_POST['record_to_transactions']) && ($_POST['record_to_transactions'] == '1' || $_POST['record_to_transactions'] === 'true');
$actual_note = trim($_POST['note'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mã khoản chi không hợp lệ!']);
    exit;
}

if ($actual_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Số tiền thực tế phải lớn hơn 0!']);
    exit;
}

if (empty($actual_date)) {
    $actual_date = date('Y-m-d');
}

function calculateNextDueDate($currentDate, $repeatType, $interval = 1, $dayOfMonth = null) {
    $time = strtotime($currentDate);
    switch ($repeatType) {
        case 'daily':
            return date('Y-m-d', strtotime('+1 day', $time));
        case 'weekly':
            return date('Y-m-d', strtotime('+1 week', $time));
        case 'monthly':
            $y = intval(date('Y', $time));
            $m = intval(date('m', $time));
            $nextMonthTime = strtotime("+1 month", strtotime(sprintf('%04d-%02d-01', $y, $m)));
            $daysInNext = intval(date('t', $nextMonthTime));
            $targetDay = $dayOfMonth ? min(intval($dayOfMonth), $daysInNext) : min(intval(date('d', $time)), $daysInNext);
            return date('Y-m-', $nextMonthTime) . sprintf('%02d', $targetDay);
        case 'yearly':
            return date('Y-m-d', strtotime('+1 year', $time));
        case 'custom_days':
            $days = max(1, intval($interval));
            return date('Y-m-d', strtotime("+$days days", $time));
        case 'once':
        default:
            return null;
    }
}

try {
    // 1. Kiểm tra bản ghi
    $fetchStmt = $pdo->prepare("SELECT * FROM `planned_expenses` WHERE `id` = ? AND `user_id` = ? LIMIT 1");
    $fetchStmt->execute([$id, $user_id]);
    $item = $fetchStmt->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy khoản chi dự kiến!']);
        exit;
    }

    $combinedNote = $item['note'];
    if (!empty($actual_note)) {
        $combinedNote = !empty($combinedNote) ? $combinedNote . " | Thực tế: " . $actual_note : $actual_note;
    }

    // 2. Cập nhật bản ghi hiện tại thành completed
    $updateStmt = $pdo->prepare("
        UPDATE `planned_expenses` 
        SET `actual_date` = ?, `actual_amount` = ?, `status` = 'completed', `note` = ?
        WHERE `id` = ? AND `user_id` = ?
    ");
    $updateStmt->execute([$actual_date, $actual_amount, $combinedNote, $id, $user_id]);

    // 3. Nếu người dùng chọn ghi vào Lịch sử giao dịch chính
    if ($record_to_trans) {
        $txNote = "Khoản chi dự kiến: " . $item['category'] . (!empty($item['subcategory']) ? " - " . $item['subcategory'] : "");
        if (!empty($actual_note)) {
            $txNote .= " (" . $actual_note . ")";
        }
        $txStmt = $pdo->prepare("
            INSERT INTO `transactions` (`user_id`, `type`, `category`, `subcategory`, `amount`, `transaction_date`, `note`)
            VALUES (?, 'expense', ?, ?, ?, ?, ?)
        ");
        $txStmt->execute([
            $user_id,
            $item['category'],
            $item['subcategory'],
            $actual_amount,
            $actual_date,
            $txNote
        ]);
    }

    // 4. Nếu có chu kỳ lặp lại (hằng tháng, hằng tuần, X ngày...), tự động tạo kỳ tiếp theo
    $nextDate = null;
    if ($item['repeat_type'] !== 'once') {
        $baseDate = $item['expected_date'];
        $nextDate = calculateNextDueDate($baseDate, $item['repeat_type'], $item['repeat_interval'], $item['day_of_month']);

        if ($nextDate) {
            // Kiểm tra tránh trùng lặp
            $checkNext = $pdo->prepare("
                SELECT id FROM `planned_expenses` 
                WHERE `user_id` = ? AND `category` = ? AND (subcategory = ? OR (subcategory IS NULL AND ? IS NULL))
                  AND `expected_date` = ? AND `status` = 'pending'
                LIMIT 1
            ");
            $checkNext->execute([$user_id, $item['category'], $item['subcategory'], $item['subcategory'], $nextDate]);
            if (!$checkNext->fetch()) {
                $insertNext = $pdo->prepare("
                    INSERT INTO `planned_expenses` (
                        `user_id`, `title`, `category`, `subcategory`, 
                        `expected_amount`, `repeat_type`, `repeat_interval`, 
                        `day_of_month`, `expected_date`, `status`, `note`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                ");
                $insertNext->execute([
                    $user_id,
                    $item['title'],
                    $item['category'],
                    $item['subcategory'],
                    $item['expected_amount'],
                    $item['repeat_type'],
                    $item['repeat_interval'],
                    $item['day_of_month'],
                    $nextDate,
                    $item['note']
                ]);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Đã cập nhật thực tế chi thành công!' . ($nextDate ? ' Kỳ dự kiến kế tiếp: ' . date('d/m/Y', strtotime($nextDate)) : ''),
        'next_due_date' => $nextDate
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật thực tế: ' . $e->getMessage()]);
}
exit;
