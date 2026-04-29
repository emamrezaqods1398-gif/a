<?php
/**
 * API ارسال پیام - با پشتیبانی از متن، تصویر، ویدیو، صدا و فایل
 */
require_once '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'لطفاً وارد شوید']);
    exit;
}

$user = getCurrentUser($pdo);
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'متد غیرمجاز']);
    exit;
}

// دریافت داده‌ها
$input = json_decode(file_get_contents('php://input'), true);
$messageText = sanitizeInput($input['message_text'] ?? '');
$chatId = $input['chat_id'] ?? null;
$groupId = $input['group_id'] ?? null;
$channelId = $input['channel_id'] ?? null;
$replyToMessageId = $input['reply_to_message_id'] ?? null;
$mediaType = 'none';
$mediaPath = null;

// بررسی وجود چت یا گروه یا کانال
if (!$chatId && !$groupId && !$channelId) {
    echo json_encode(['status' => 'error', 'message' => 'شناسه چت نامعتبر است']);
    exit;
}

// پردازش مدیا اگر وجود دارد
if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['media'];
    $allowedTypes = [
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        'video/mp4' => 'video',
        'video/webm' => 'video',
        'audio/ogg' => 'voice',
        'audio/mpeg' => 'voice',
        'application/pdf' => 'document',
        'application/zip' => 'document',
        'application/msword' => 'document',
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!array_key_exists($mimeType, $allowedTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'نوع فایل مجاز نیست']);
        exit;
    }
    
    $mediaType = $allowedTypes[$mimeType];
    $uploadDir = UPLOAD_DIR . $mediaType . 's/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $mediaPath = $mediaType . 's/' . $fileName;
    
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $mediaPath)) {
        echo json_encode(['status' => 'error', 'message' => 'خطا در آپلود فایل']);
        exit;
    }
}

// اگر نه متن داریم نه مدیا، خطا
if (empty($messageText) && $mediaType === 'none') {
    echo json_encode(['status' => 'error', 'message' => 'پیام خالی است']);
    exit;
}

// تعیین allow_download بر اساس تنظیمات چت/گروه
$allowDownload = 1;
if ($groupId) {
    // بررسی تنظیمات گروه برای دانلود
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    if ($group && isset($group['no_download'])) {
        $allowDownload = !$group['no_download'];
    }
}

// درج پیام در دیتابیس
try {
    $stmt = $pdo->prepare("
        INSERT INTO messages 
        (chat_id, group_id, channel_id, sender_id, message_text, media_type, media_path, allow_download, reply_to_message_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $chatId,
        $groupId,
        $channelId,
        $user['id'],
        $messageText ?: null,
        $mediaType,
        $mediaPath,
        $allowDownload,
        $replyToMessageId
    ]);
    
    $messageId = $pdo->lastInsertId();
    
    // به‌روزرسانی زمان آخرین پیام چت
    if ($chatId) {
        $updateChat = $pdo->prepare("UPDATE chats SET last_message_time = CURRENT_TIMESTAMP WHERE id = ?");
        $updateChat->execute([$chatId]);
    }
    
    // پاسخ موفقیت
    echo json_encode([
        'status' => 'success',
        'message_id' => $messageId,
        'message_text' => $messageText,
        'media_type' => $mediaType,
        'media_path' => $mediaPath,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("Send message error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'خطا در ارسال پیام']);
}
