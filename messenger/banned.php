<?php
/**
 * Banned User Landing Page (Feature #156)
 * Shows ban reason and appeal form
 */
require_once 'config.php';

$userId = $_SESSION['banned_user_id'] ?? null;

if (!$userId) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT ban_reason, display_name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !$user['is_banned']) {
    unset($_SESSION['banned_user_id']);
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

// Handle appeal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appeal_text'])) {
    $appealText = trim($_POST['appeal_text']);
    
    if (empty($appealText)) {
        $error = 'لطفاً توضیحات خود را وارد کنید.';
    } else {
        // Check if already has pending appeal
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ban_appeals WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'شما قبلاً درخواست بررسی مجدد ارسال کرده‌اید. لطفاً منتظر پاسخ ادمین باشید.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO ban_appeals (user_id, appeal_text) VALUES (?, ?)");
            $stmt->execute([$userId, $appealText]);
            $success = 'درخواست شما با موفقیت ثبت شد. ادمین به زودی بررسی خواهد کرد.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حساب کاربری مسدود شده</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .ban-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .ban-icon {
            font-size: 80px;
            color: #d32f2f;
            margin-bottom: 20px;
        }
        .ban-title {
            color: #d32f2f;
            font-size: 28px;
            margin: 0 0 15px;
        }
        .ban-message {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .ban-reason {
            background: #ffebee;
            border: 2px solid #ef9a9a;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: right;
        }
        .ban-reason h3 {
            margin: 0 0 10px;
            color: #c62828;
            font-size: 16px;
        }
        .ban-reason p {
            margin: 0;
            color: #b71c1c;
            line-height: 1.5;
        }
        .appeal-form {
            text-align: right;
            margin-top: 30px;
            border-top: 2px solid #eee;
            padding-top: 25px;
        }
        .appeal-form h3 {
            margin: 0 0 15px;
            color: #075e54;
            font-size: 18px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            box-sizing: border-box;
        }
        .form-group textarea:focus {
            outline: none;
            border-color: #075e54;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #075e54;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-submit:hover {
            background: #128C7E;
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        .logout-link {
            margin-top: 20px;
            color: #666;
        }
        .logout-link a {
            color: #075e54;
            text-decoration: none;
            font-weight: bold;
        }
        .logout-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="ban-container">
        <div class="ban-icon">🚫</div>
        <h1 class="ban-title">حساب کاربری شما مسدود شده است</h1>
        
        <p class="ban-message">
            سلام <?php echo htmlspecialchars($user['display_name'] ?? 'کاربر گرامی'); ?>،<br>
            حساب کاربری شما توسط تیم مدیریت مسدود شده است.
        </p>
        
        <?php if ($user['ban_reason']): ?>
            <div class="ban-reason">
                <h3>دلیل مسدودیت:</h3>
                <p><?php echo nl2br(htmlspecialchars($user['ban_reason'])); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="appeal-form">
            <h3>📝 درخواست بررسی مجدد</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                اگر فکر می‌کنید این اشتباه رخ داده، می‌توانید درخواست بررسی مجدد ارسال کنید.
            </p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="appeal_text">توضیحات شما:</label>
                    <textarea id="appeal_text" name="appeal_text" placeholder="لطفاً توضیح دهید چرا فکر می‌کنید این مسدودیت اشتباه است..." required></textarea>
                </div>
                
                <button type="submit" class="btn-submit">ارسال درخواست</button>
            </form>
        </div>
        
        <div class="logout-link">
            <a href="logout.php">خروج از حساب</a>
        </div>
    </div>
</body>
</html>
