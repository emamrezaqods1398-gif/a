<?php
/**
 * Register Page - With Invite System & Privacy Controls
 */
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Check if registration is enabled
if (!isRegistrationEnabled()) {
    $error = 'ثبت‌نام در حال حاضر غیرفعال است.';
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isRegistrationEnabled()) {
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $username = !empty($_POST['username']) ? sanitizeInput($_POST['username']) : null;
    $displayName = sanitizeInput($_POST['display_name'] ?? '');
    $inviteCode = !empty($_POST['invite_code']) ? sanitizeInput($_POST['invite_code']) : null;
    
    // Validation
    if (empty($phone) || empty($password)) {
        $error = 'لطفاً شماره تلفن و رمز عبور را وارد کنید.';
    } elseif ($password !== $confirmPassword) {
        $error = 'رمز عبور و تکرار آن مطابقت ندارند.';
    } elseif (strlen($password) < 6) {
        $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
    } elseif (!preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = 'شماره تلفن نامعتبر است.';
    } else {
        // Check invite-only mode
        if (getSystemSetting('invite_only_mode') === '1') {
            if (empty($inviteCode)) {
                $error = 'ثبت‌نام فقط با لینک دعوت امکان‌پذیر است.';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM invites WHERE code = ? AND is_active = 1 AND uses_count < uses_limit");
                $stmt->execute([$inviteCode]);
                if (!$stmt->fetch()) {
                    $error = 'کد دعوت نامعتبر یا منقضی شده است.';
                }
            }
        }
        
        // Check reserved handles
        if ($username && isReservedHandle($username)) {
            $error = 'این نام کاربری رزرو شده است و نمی‌توانید از آن استفاده کنید.';
        }
        
        if (empty($error)) {
            $pdo = getDBConnection();
            
            // Check if phone exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'این شماره تلفن قبلاً ثبت شده است.';
            } else {
                // Check username uniqueness
                if ($username) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'این نام کاربری قبلاً گرفته شده است.';
                    }
                }
                
                if (empty($error)) {
                    // Create user
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $deviceFingerprint = generateDeviceFingerprint();
                    
                    $stmt = $pdo->prepare("INSERT INTO users (phone, password_hash, username, display_name, device_fingerprint) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$phone, $passwordHash, $username, $displayName, $deviceFingerprint]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Update invite usage
                    if ($inviteCode) {
                        $stmt = $pdo->prepare("UPDATE invites SET uses_count = uses_count + 1 WHERE code = ?");
                        $stmt->execute([$inviteCode]);
                    }
                    
                    // Send welcome message (Feature #175)
                    $welcomeMessage = getSystemSetting('welcome_message');
                    if ($welcomeMessage) {
                        // Find or create chat with admin
                        $stmt = $pdo->prepare("SELECT id FROM chats WHERE type = 'private' AND creator_id = 1");
                        $stmt->execute();
                        $chat = $stmt->fetch();
                        
                        if (!$chat) {
                            // This is simplified - in real app you'd create proper private chat
                        }
                    }
                    
                    logAdminAction($userId, 'user_registered', 'user', $userId);
                    
                    // Auto login
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['device_fingerprint'] = $deviceFingerprint;
                    
                    header('Location: index.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام در پیام‌رسان</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h1 {
            color: #075e54;
            margin: 0;
            font-size: 28px;
        }
        .register-header p {
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
        .form-group small {
            color: #666;
            font-size: 12px;
        }
        .btn-register {
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
        .btn-register:hover {
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
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .login-link a {
            color: #075e54;
            text-decoration: none;
            font-weight: bold;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .invite-section {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>پیام‌رسان</h1>
            <p>حساب کاربری جدید بسازید</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isRegistrationEnabled()): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="phone">شماره تلفن *</label>
                    <input type="tel" id="phone" name="phone" required placeholder="مثال: 09123456789" pattern="09[0-9]{9}">
                </div>
                
                <div class="form-group">
                    <label for="display_name">نام نمایشی</label>
                    <input type="text" id="display_name" name="display_name" placeholder="نام شما (اختیاری)">
                </div>
                
                <div class="form-group">
                    <label for="username">نام کاربری (اختیاری)</label>
                    <input type="text" id="username" name="username" placeholder="@username" pattern="[a-zA-Z0-9_]+">
                    <small>فقط حروف انگلیسی، اعداد و زیرخط</small>
                </div>
                
                <div class="form-group">
                    <label for="password">رمز عبور *</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="حداقل ۶ کاراکتر">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">تکرار رمز عبور *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="تکرار رمز عبور">
                </div>
                
                <?php if (getSystemSetting('invite_only_mode') === '1'): ?>
                    <div class="invite-section">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="invite_code">کد دعوت *</label>
                            <input type="text" id="invite_code" name="invite_code" required placeholder="کد دعوت خود را وارد کنید">
                        </div>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-register">ثبت‌نام</button>
            </form>
        <?php else: ?>
            <div class="alert alert-error" style="margin-top: 20px;">
                ثبت‌نام در حال حاضر غیرفعال است. لطفاً بعداً مراجعه کنید.
            </div>
        <?php endif; ?>
        
        <div class="login-link">
            قبلاً ثبت‌نام کرده‌اید؟ <a href="login.php">وارد شوید</a>
        </div>
    </div>
</body>
</html>
