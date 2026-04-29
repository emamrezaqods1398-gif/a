<?php
/**
 * Login Page - Messenger
 */
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Check maintenance mode for non-admin
if (isMaintenanceMode() && !isAdmin()) {
    $error = 'سایت در حالت تعمیرات است. فقط ادمین‌ها می‌توانند وارد شوند.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($phone) || empty($password)) {
        $error = 'لطفاً شماره تلفن و رمز عبور را وارد کنید.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, password_hash, is_banned, is_deleted FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_deleted']) {
                $error = 'این حساب کاربری حذف شده است.';
            } elseif ($user['is_banned']) {
                // Get ban reason
                $stmt = $pdo->prepare("SELECT ban_reason FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $banData = $stmt->fetch();
                $_SESSION['banned_user_id'] = $user['id'];
                header('Location: banned.php');
                exit;
            } else {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['device_fingerprint'] = generateDeviceFingerprint();
                
                // Update last seen
                $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                header('Location: index.php');
                exit;
            }
        } else {
            $error = 'شماره تلفن یا رمز عبور اشتباه است.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پیام‌رسان</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #075e54;
            margin: 0;
            font-size: 28px;
        }
        .login-header p {
            color: #666;
            margin: 10px 0 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #075e54;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #25D366;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #128C7E;
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .register-link a {
            color: #075e54;
            text-decoration: none;
            font-weight: bold;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>پیام‌رسان</h1>
            <p>وارد حساب خود شوید</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="phone">شماره تلفن</label>
                <input type="tel" id="phone" name="phone" required placeholder="مثال: 09123456789">
            </div>
            
            <div class="form-group">
                <label for="password">رمز عبور</label>
                <input type="password" id="password" name="password" required placeholder="رمز عبور خود را وارد کنید">
            </div>
            
            <button type="submit" class="btn-login">ورود</button>
        </form>
        
        <?php if (isRegistrationEnabled()): ?>
            <div class="register-link">
                حساب ندارید؟ <a href="register.php">ثبت‌نام کنید</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
