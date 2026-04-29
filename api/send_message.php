<?php
// api/send_message.php - ارسال پیام

require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'لطفاً وارد شوید']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'متد نامعتبر']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$chat_id = $input['chat_id'] ?? null;
$message = trim($input['message'] ?? '');

if (!$chat_id || !$message) {
    echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص است']);
    exit;
}

try {
    // بررسی عضویت کاربر در چت
    $stmt = $pdo->prepare("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chat_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'شما عضو این چت نیستید']);
        exit;
    }
    
    // ثبت پیام
    $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, message, message_type) VALUES (?, ?, ?, 'text')");
    $stmt->execute([$chat_id, $_SESSION['user_id'], $message]);
    
    echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'خطا در ارسال پیام: ' . $e->getMessage()]);
}
?>
