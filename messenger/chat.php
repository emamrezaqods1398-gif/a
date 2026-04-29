<?php
/**
 * صفحه چت خصوصی - نمایش گفتگو بین دو کاربر
 */
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser($pdo);
$chatId = $_GET['id'] ?? null;

if (!$chatId) {
    header('Location: index.php');
    exit;
}

// دریافت اطلاعات چت
$stmt = $pdo->prepare("SELECT c.*, u1.name as user1_name, u1.avatar as user1_avatar, u2.name as user2_name, u2.avatar as user2_avatar 
                       FROM chats c 
                       JOIN users u1 ON c.user_one = u1.id 
                       JOIN users u2 ON c.user_two = u2.id 
                       WHERE c.id = ? AND (c.user_one = ? OR c.user_two = ?)");
$stmt->execute([$chatId, $user['id'], $user['id']]);
$chat = $stmt->fetch();

if (!$chat) {
    die('چت یافت نشد');
}

// تعیین طرف مقابل
$otherUserId = ($chat['user_one'] == $user['id']) ? $chat['user_two'] : $chat['user_one'];
$otherUserName = ($chat['user_one'] == $user['id']) ? $chat['user2_name'] : $chat['user1_name'];
$otherUserAvatar = ($chat['user_one'] == $user['id']) ? $chat['user2_avatar'] : $chat['user1_avatar'];

// دریافت اطلاعات کامل طرف مقابل
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$otherUserId]);
$otherUser = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($otherUserName); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/glass-theme.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
</head>
<body class="chat-page">
    <!-- هدر چت -->
    <header class="chat-header glass-panel">
        <a href="index.php" class="back-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83L13.42 5.41L12 4L4 12L12 20L13.41 18.59L7.83 13H20V11Z"/></svg>
        </a>
        <div class="chat-user-info">
            <img src="uploads/<?php echo htmlspecialchars($otherUser['avatar']); ?>" alt="<?php echo htmlspecialchars($otherUserName); ?>">
            <div class="user-details">
                <h3><?php echo htmlspecialchars($otherUserName); ?></h3>
                <?php if ($otherUser['show_badge'] && $otherUser['badge_type'] !== 'normal'): ?>
                    <span class="badge badge-<?php echo $otherUser['badge_type']; ?>">✓</span>
                <?php endif; ?>
                <p class="last-seen" id="lastSeen">آخرین بازدید: در حال نوشتن...</p>
            </div>
        </div>
        <div class="chat-actions">
            <button class="icon-btn" onclick="toggleMute()" title="بی‌صدا کردن">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12S17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12.5 7H11V13L16.25 16.15L17 14.92L12.5 12.25V7Z"/></svg>
            </button>
            <button class="icon-btn" onclick="openChatSettings()" title="تنظیمات">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94C19.16 12.78 19.16 12.61 19.16 12.45C19.16 12.29 19.16 12.13 19.14 11.97L21.41 10.2C21.61 10.04 21.67 9.76 21.53 9.53L19.39 5.83C19.25 5.6 18.98 5.52 18.74 5.61L16.07 6.69C15.51 6.26 14.9 5.9 14.24 5.63L13.84 2.76C13.8 2.5 13.58 2.31 13.32 2.31H9.05C8.79 2.31 8.57 2.5 8.53 2.76L8.13 5.63C7.47 5.9 6.86 6.26 6.3 6.69L3.63 5.61C3.39 5.51 3.12 5.6 2.98 5.83L0.84 9.53C0.7 9.76 0.76 10.04 0.96 10.2L3.23 11.97C3.21 12.13 3.21 12.29 3.21 12.45C3.21 12.61 3.21 12.78 3.23 12.94L0.96 14.71C0.76 14.87 0.7 15.15 0.84 15.38L2.98 19.08C3.12 19.31 3.39 19.4 3.63 19.3L6.3 18.22C6.86 18.65 7.47 19.01 8.13 19.28L8.53 22.15C8.57 22.41 8.79 22.6 9.05 22.6H13.32C13.58 22.6 13.8 22.41 13.84 22.15L14.24 19.28C14.9 19.01 15.51 18.65 16.07 18.22L18.74 19.3C18.98 19.4 19.25 19.31 19.39 19.08L21.53 15.38C21.67 15.15 21.61 14.87 21.41 14.71L19.14 12.94ZM11.18 15.86C9.3 15.86 7.78 14.34 7.78 12.46C7.78 10.58 9.3 9.06 11.18 9.06C13.06 9.06 14.58 10.58 14.58 12.46C14.58 14.34 13.06 15.86 11.18 15.86Z"/></svg>
            </button>
        </div>
    </header>

    <!-- ناحیه پیام‌ها -->
    <main class="messages-container" id="messagesContainer">
        <div class="messages-list" id="messagesList">
            <!-- پیام‌ها با JavaScript لود می‌شوند -->
            <div class="skeleton-loader">
                <div class="skeleton-message skeleton-left"></div>
                <div class="skeleton-message skeleton-right"></div>
                <div class="skeleton-message skeleton-left"></div>
                <div class="skeleton-message skeleton-right"></div>
            </div>
        </div>
    </main>

    <!-- ورودی پیام -->
    <footer class="message-input-area glass-panel">
        <button class="attach-btn" onclick="openFilePicker()" title="پیوست فایل">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 6V11.5C16.5 13.98 14.48 16 12 16C9.52 16 7.5 13.98 7.5 11.5V5C7.5 3.89 8.39 3 9.5 3C10.61 3 11.5 3.89 11.5 5V12.5C11.5 13.33 12.17 14 13 14C13.83 14 14.5 13.33 14.5 12.5V6C14.5 4.34 13.16 3 11.5 3C9.84 3 8.5 4.34 8.5 6V11.5C8.5 14.54 10.96 17 14 17C17.04 17 19.5 14.54 19.5 11.5V6H16.5Z"/></svg>
        </button>
        <input type="text" id="messageInput" placeholder="پیام خود را بنویسید..." autocomplete="off">
        <button class="send-btn" onclick="sendMessage()" title="ارسال">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12L2.01 3L2 10L17 12L2 14L2.01 21Z"/></svg>
        </button>
    </footer>

    <script>
        const chatId = <?php echo $chatId; ?>;
        const otherUserId = <?php echo $otherUserId; ?>;
        const currentUserId = <?php echo $user['id']; ?>;
    </script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/chat.js"></script>
</body>
</html>
