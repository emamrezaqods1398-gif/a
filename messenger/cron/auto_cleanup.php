<?php
/**
 * Cron Job - پاکسازی خودکار استوری‌های منقضی‌شده و مدیاهای حذف‌شده
 * باید هر ۱ دقیقه اجرا شود
 */

// جلوگیری از اجرای مستقیم در مرورگر
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    die("این اسکریپت فقط از طریق خط فرمان قابل اجرا است.\n");
}

require_once __DIR__ . '/../config.php';

echo "[" . date('Y-m-d H:i:s') . "] شروع عملیات پاکسازی...\n";

// ۱. حذف استوری‌های منقضی‌شده
try {
    $stmt = $pdo->prepare("DELETE FROM stories WHERE expires_at < NOW()");
    $stmt->execute();
    $deletedStories = $stmt->rowCount();
    echo "✓ تعداد {$deletedStories} استوری منقضی‌شده حذف شد.\n";
    
    // حذف فایل‌های فیزیکی استوری‌های حذف‌شده (نیاز به کوئری جداگانه دارد)
} catch (PDOException $e) {
    echo "✗ خطا در حذف استوری‌ها: " . $e->getMessage() . "\n";
}

// ۲. حذف سشن‌های قدیمی (بیش از ۳۰ روز)
try {
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $deletedSessions = $stmt->rowCount();
    echo "✓ تعداد {$deletedSessions} سشن قدیمی حذف شد.\n";
} catch (PDOException $e) {
    echo "✗ خطا در حذف سشن‌ها: " . $e->getMessage() . "\n";
}

// ۳. حذف OTPهای استفاده‌شده یا منقضی‌شده
try {
    $stmt = $pdo->prepare("DELETE FROM email_otps WHERE is_used = 1 OR expires_at < NOW()");
    $stmt->execute();
    $deletedOtps = $stmt->rowCount();
    echo "✓ تعداد {$deletedOtps} کد OTP قدیمی حذف شد.\n";
} catch (PDOException $e) {
    echo "✗ خطا در حذف OTPها: " . $e->getMessage() . "\n";
}

// ۴. به‌روزرسانی last_seen برای کاربران آنلاین
try {
    $stmt = $pdo->query("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id IN (SELECT DISTINCT user_id FROM sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE))");
    echo "✓ وضعیت آخرین بازدید کاربران به‌روزرسانی شد.\n";
} catch (PDOException $e) {
    echo "✗ خطا در به‌روزرسانی last_seen: " . $e->getMessage() . "\n";
}

// ۵. پاکسازی لاگ‌های قدیمی ادمین (بیش از ۹۰ روز)
try {
    $stmt = $pdo->prepare("DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
    $deletedLogs = $stmt->rowCount();
    echo "✓ تعداد {$deletedLogs} لاگ ادمین قدیمی حذف شد.\n";
} catch (PDOException $e) {
    echo "✗ خطا در حذف لاگ‌ها: " . $e->getMessage() . "\n";
}

// ۶. OPTIMIZE کردن جداول برای بازیابی فضا
try {
    $tables = ['messages', 'stories', 'sessions', 'email_otps', 'admin_logs'];
    foreach ($tables as $table) {
        $pdo->exec("OPTIMIZE TABLE {$table}");
        echo "✓ جدول {$table} بهینه‌سازی شد.\n";
    }
} catch (PDOException $e) {
    echo "✗ خطا در بهینه‌سازی جداول: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] عملیات پاکسازی با موفقیت پایان یافت.\n";
