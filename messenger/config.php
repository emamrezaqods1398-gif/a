<?php
/**
 * پیام‌رسان وب نسل ۲۰۲۶ - تنظیمات پیکربندی
 * شامل: اتصال به دیتابیس، توابع امنیتی، تنظیمات سهمیه
 */

// جلوگیری از خطاهای نمایشی در تولید
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// شروع سشن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات هدر برای امنیت و فشرده‌سازی
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// فعال‌سازی Gzip Compression
if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
    ob_start("ob_gzhandler");
} else {
    ob_start();
}

// تعریف ثابت‌های سیستم
define('SITE_NAME', 'پیام‌رسان وب ۲۰۲۶');
define('SITE_URL', 'http://localhost/messenger');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
define('SYSTEM_BOT_ID', 1); // ID اکانت sata

// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_NAME', 'messenger_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// اتصال به دیتابیس با PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true // Connection Pooling
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die(json_encode(['status' => 'error', 'message' => 'خطا در اتصال به پایگاه داده']));
}

/**
 * بررسی وضعیت ورود کاربر
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * دریافت اطلاعات کاربر فعلی
 */
function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // بررسی بن بودن
    if ($user && $user['is_banned']) {
        header('Location: banned.php');
        exit;
    }
    
    return $user;
}

/**
 * بررسی ادمین بودن
 */
function isAdmin($user) {
    return $user && ($user['is_admin'] == 1 || $user['badge_type'] === 'black');
}

/**
 * بررسی اکانت سیستمی (sata)
 */
function isSystemBot($user) {
    return $user && $user['is_system_bot'] == 1;
}

/**
 * دریافت سهمیه کاربر بر اساس نوع تیک
 */
function getUserQuotas($pdo, $badgeType) {
    $stmt = $pdo->prepare("SELECT * FROM badge_quotas WHERE badge_type = ?");
    $stmt->execute([$badgeType]);
    return $stmt->fetch();
}

/**
 * بررسی مجوز ساخت گروه
 */
function canCreateGroup($pdo, $userId) {
    $user = getCurrentUser($pdo);
    if (!$user) return false;
    
    // اکانت ریشه محدودیت ندارد
    if ($user['is_system_bot']) return true;
    
    // بررسی override
    if ($user['group_quota_override'] !== null) {
        $maxGroups = $user['group_quota_override'];
    } else {
        $quotas = getUserQuotas($pdo, $user['badge_type']);
        $maxGroups = $quotas['max_groups'];
    }
    
    // شمارش گروه‌های ساخته شده
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM groups WHERE creator_id = ?");
    $stmt->execute([$userId]);
    $currentCount = $stmt->fetch()['count'];
    
    return $currentCount < $maxGroups;
}

/**
 * بررسی مجوز ساخت کانال
 */
function canCreateChannel($pdo, $userId) {
    $user = getCurrentUser($pdo);
    if (!$user) return false;
    
    if ($user['is_system_bot']) return true;
    
    if ($user['channel_quota_override'] !== null) {
        $maxChannels = $user['channel_quota_override'];
    } else {
        $quotas = getUserQuotas($pdo, $user['badge_type']);
        $maxChannels = $quotas['max_channels'];
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM channels WHERE creator_id = ?");
    $stmt->execute([$userId]);
    $currentCount = $stmt->fetch()['count'];
    
    return $currentCount < $maxChannels;
}

/**
 * تمیز کردن ورودی‌ها (ضد XSS)
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * تولید توکن سشن امن
 */
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

/**
 * ثبت لاگ اقدامات ادمین
 */
function logAdminAction($pdo, $adminId, $actionType, $targetUserId = null, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action_type, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$adminId, $actionType, $targetUserId, $details, $ip]);
}

/**
 * ارسال پیام سیستمی از طرف اکانت sata
 */
function sendSystemAlert($pdo, $userId, $messageText) {
    $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, message_text, media_type) VALUES (?, ?, ?, 'none')");
    // پیدا کردن چت بین sata و کاربر
    $chatStmt = $pdo->prepare("SELECT id FROM chats WHERE (user_one = ? AND user_two = ?) OR (user_one = ? AND user_two = ?)");
    $chatStmt->execute([SYSTEM_BOT_ID, $userId, $userId, SYSTEM_BOT_ID]);
    $chat = $chatStmt->fetch();
    
    if (!$chat) {
        // ایجاد چت جدید
        $createChat = $pdo->prepare("INSERT INTO chats (user_one, user_two) VALUES (?, ?)");
        $createChat->execute([SYSTEM_BOT_ID, $userId]);
        $chatId = $pdo->lastInsertId();
    } else {
        $chatId = $chat['id'];
    }
    
    $stmt->execute([$chatId, SYSTEM_BOT_ID, $messageText]);
}

/**
 * بررسی وضعیت ثبت‌نام
 */
function isRegistrationEnabled($pdo) {
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_enabled'");
    return $stmt->fetchColumn() == '1';
}

/**
 * پاکسازی متغیرهای سنگین از حافظه
 */
function finalCleanup() {
    global $pdo;
    $pdo = null;
    unset($_SESSION['temp_data']);
    gc_collect_cycles();
}

// ثبت shutdown function برای پاکسازی
register_shutdown_function('finalCleanup');
