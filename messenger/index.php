<?php
/**
 * پیام‌رسان وب نسل ۲۰۲۶ - صفحه اصلی چت
 * شامل: لیست گفتگوها، تب Updates، پنل ادمین یکپارچه
 */

require_once 'config.php';

// بررسی ورود کاربر
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser($pdo);
$isAdmin = isAdmin($user);
$isBot = isSystemBot($user);

// دریافت تب فعلی
$activeTab = $_GET['tab'] ?? 'chats';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/glass-theme.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
</head>
<body class="<?php echo $activeTab; ?>-page">
    <!-- هدر شیشه‌ای -->
    <header class="glass-header">
        <div class="header-content">
            <div class="logo">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20Z" fill="#00A884"/>
                    <path d="M12 6C8.69 6 6 8.69 6 12C6 15.31 8.69 18 12 18C15.31 18 18 15.31 18 12C18 8.69 15.31 6 12 6ZM12 16C9.79 16 8 14.21 8 12C8 9.79 9.79 8 12 8C14.21 8 16 9.79 16 12C16 14.21 14.21 16 12 16Z" fill="#00A884"/>
                </svg>
                <span><?php echo $user['name']; ?></span>
                <?php if ($user['show_badge'] && $user['badge_type'] !== 'normal'): ?>
                    <span class="badge badge-<?php echo $user['badge_type']; ?>">✓</span>
                <?php endif; ?>
            </div>
            <div class="header-actions">
                <button class="icon-btn" onclick="toggleTheme()" title="تغییر تم">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3C7.03 3 3 7.03 3 12S7.03 21 12 21C12.83 21 13.5 20.33 13.5 19.5C13.5 19.11 13.35 18.76 13.11 18.5C12.88 18.23 12.73 17.88 12.73 17.5C12.73 16.95 13.18 16.5 13.73 16.5H16C18.76 16.5 21 14.26 21 11.5C21 6.81 16.97 3 12 3Z"/></svg>
                </button>
                <button class="icon-btn" onclick="logout()" title="خروج">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7L15.59 8.41L18.17 11H8V13H18.17L15.59 15.58L17 17L22 12L17 7ZM4 5H12V3H4C2.9 3 2 3.9 2 5V19C2 20.1 2.9 21 4 21H12V19H4V5Z"/></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- محتوای اصلی -->
    <main class="main-container">
        <!-- تب گفتگوها -->
        <div id="chats-tab" class="tab-content <?php echo $activeTab === 'chats' ? 'active' : ''; ?>">
            <div class="chat-list" id="chatList">
                <!-- چت‌ها با JavaScript لود می‌شوند -->
                <div class="skeleton-loader">
                    <div class="skeleton-item"></div>
                    <div class="skeleton-item"></div>
                    <div class="skeleton-item"></div>
                    <div class="skeleton-item"></div>
                </div>
            </div>
        </div>

        <!-- تب به‌روزرسانی‌ها (Updates) -->
        <div id="updates-tab" class="tab-content <?php echo $activeTab === 'updates' ? 'active' : ''; ?>">
            <div class="updates-container">
                <!-- بخش My Status -->
                <div class="my-status-section">
                    <div class="status-card" onclick="openStoryUploader()">
                        <div class="status-avatar">
                            <img src="uploads/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Profile">
                            <div class="add-status-btn">+</div>
                        </div>
                        <div class="status-info">
                            <h3>استوری من</h3>
                            <p>پس از ۲۴ ساعت ناپدید می‌شود</p>
                        </div>
                    </div>
                </div>

                <!-- بخش کانال‌های رسمی -->
                <div class="channels-section">
                    <h3>کانال‌های رسمی</h3>
                    <div class="channels-list" id="officialChannels">
                        <!-- کانال‌ها با JavaScript لود می‌شوند -->
                    </div>
                </div>
            </div>
        </div>

        <!-- تب مخاطبین -->
        <div id="contacts-tab" class="tab-content <?php echo $activeTab === 'contacts' ? 'active' : ''; ?>">
            <div class="contacts-list" id="contactsList">
                <!-- مخاطبین ذخیره شده -->
            </div>
        </div>

        <!-- تب پنل ادمین (فقط برای ادمین‌ها) -->
        <?php if ($isAdmin): ?>
        <div id="admin-tab" class="tab-content <?php echo $activeTab === 'admin' ? 'active' : ''; ?>">
            <iframe src="admin/dashboard.php" style="width:100%;height:calc(100vh - 140px);border:none;"></iframe>
        </div>
        <?php endif; ?>
    </main>

    <!-- نوار ناوبری پایین -->
    <nav class="bottom-nav glass-nav">
        <a href="?tab=chats" class="nav-item <?php echo $activeTab === 'chats' ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H6L4 18V4H20V16Z"/></svg>
            <span>گفتگوها</span>
        </a>
        <a href="?tab=updates" class="nav-item <?php echo $activeTab === 'updates' ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12S17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12.5 7H11V13L16.25 16.15L17 14.92L12.5 12.25V7Z"/></svg>
            <span>به‌روزرسانی‌ها</span>
        </a>
        <a href="?tab=contacts" class="nav-item <?php echo $activeTab === 'contacts' ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12C14.21 12 16 10.21 16 8S14.21 4 12 4 8 5.79 8 8 9.79 12 12 12ZM12 14C9.33 14 4 15.34 4 18V20H20V18C20 15.34 14.67 14 12 14Z"/></svg>
            <span>مخاطبین</span>
        </a>
        <?php if ($isAdmin): ?>
        <a href="?tab=admin" class="nav-item <?php echo $activeTab === 'admin' ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM12 11.99H7V10.01H12V7L16 10.01L12 13V11.99Z"/></svg>
            <span>پنل ادمین</span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- منوی Context سفارشی -->
    <div id="contextMenu" class="context-menu glass-panel" style="display:none;">
        <!-- گزینه‌ها با JavaScript پر می‌شوند -->
    </div>

    <!-- اسکریپت‌ها -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/context-menu.js"></script>
    <script src="assets/js/ui-interactions.js"></script>
</body>
</html>
