<?php
/**
 * SATA Messenger - Login Page
 * Implements Items: 35 (Glassmorphism Design), 21-40
 */

// Check if already logged in
$auth = new UserAuth();
if ($auth->verifySession()) {
    header('Location: chat');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'خطای امنیتی. لطفاً صفحه را رفرش کنید.';
    } elseif (empty($email) || empty($password)) {
        $error = 'لطفاً تمام فیلدها را پر کنید';
    } else {
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            header('Location: chat');
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به ساتا مسنجر</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Item 35: Glassmorphism Design */
        :root {
            --primary-color: #00a884;
            --bg-gradient: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #fff;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #fff;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: var(--primary-color);
            color: #fff;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn:hover {
            background: #008f6f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 168, 132, 0.4);
        }
        
        .btn:active {
            transform: scale(0.98);
        }
        
        .btn.loading {
            pointer-events: none;
        }
        
        .btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-top: -10px;
            margin-left: -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.5);
            color: #ffcdd2;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.5);
            color: #c8e6c9;
        }
        
        .links {
            margin-top: 20px;
            text-align: center;
        }
        
        .links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .links a:hover {
            color: #fff;
        }
        
        .divider {
            margin: 0 10px;
            color: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ساتا مسنجر</h1>
            <p>پیام‌رسان نسل ۲۰۲۶</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label for="email">ایمیل</label>
                <input type="email" id="email" name="email" required 
                       placeholder="example@email.com" autocomplete="email">
            </div>
            
            <div class="form-group">
                <label for="password">رمز عبور</label>
                <input type="password" id="password" name="password" required 
                       placeholder="••••••••" autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn" id="loginBtn">
                ورود به حساب
            </button>
        </form>
        
        <div class="links">
            <a href="register">ثبت‌نام</a>
            <span class="divider">|</span>
            <a href="#">بازیابی رمز عبور</a>
        </div>
    </div>
    
    <script>
        // Item 335: Loading animation on submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.textContent = 'در حال ورود...';
        });
        
        // Item 61: Disable right-click
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
    </script>
</body>
</html>
