<?php
// new_chat.php - ایجاد چت جدید، گروه یا کانال

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chat_type = $_POST['type'] ?? 'private';
    $chat_name = trim($_POST['name'] ?? '');
    $target_user = $_POST['target_user'] ?? null;
    
    try {
        if ($chat_type === 'private') {
            // ایجاد چت خصوصی
            if (!$target_user) {
                $error = 'کاربر را انتخاب کنید';
            } else {
                // بررسی وجود چت قبلی بین این دو کاربر
                $stmt = $pdo->prepare("
                    SELECT c.id FROM chats c
                    JOIN chat_members cm1 ON c.id = cm1.chat_id AND cm1.user_id = ?
                    JOIN chat_members cm2 ON c.id = cm2.chat_id AND cm2.user_id = ?
                    WHERE c.type = 'private'
                ");
                $stmt->execute([$user['id'], $target_user]);
                $existing_chat = $stmt->fetch();
                
                if ($existing_chat) {
                    // چت از قبل وجود دارد
                    redirect('index.php?chat=' . $existing_chat['id']);
                } else {
                    // ایجاد چت جدید
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("INSERT INTO chats (type, created_by) VALUES ('private', ?)");
                    $stmt->execute([$user['id']]);
                    $chat_id = $pdo->lastInsertId();
                    
                    // اضافه کردن هر دو کاربر به چت
                    $stmt = $pdo->prepare("INSERT INTO chat_members (chat_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$chat_id, $user['id']]);
                    $stmt->execute([$chat_id, $target_user]);
                    
                    $pdo->commit();
                    redirect('index.php?chat=' . $chat_id);
                }
            }
        } elseif ($chat_type === 'group' || $chat_type === 'channel') {
            // ایجاد گروه یا کانال
            if (empty($chat_name)) {
                $error = 'نام گروه/کانال الزامی است';
            } else {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO chats (name, type, created_by) VALUES (?, ?, ?)");
                $stmt->execute([$chat_name, $chat_type, $user['id']]);
                $chat_id = $pdo->lastInsertId();
                
                // اضافه کردن سازنده به عنوان عضو
                $stmt = $pdo->prepare("INSERT INTO chat_members (chat_id, user_id) VALUES (?, ?)");
                $stmt->execute([$chat_id, $user['id']]);
                
                // اضافه کردن اعضای انتخاب شده (اگر وجود داشته باشد)
                $members = $_POST['members'] ?? [];
                foreach ($members as $member_id) {
                    $stmt->execute([$chat_id, $member_id]);
                }
                
                $pdo->commit();
                redirect('index.php?chat=' . $chat_id);
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'خطا در ایجاد چت: ' . $e->getMessage();
    }
}

// دریافت لیست کاربران برای انتخاب
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id != ? ORDER BY username");
$stmt->execute([$user['id']]);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چت جدید - پیامرسان</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .new-chat-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .new-chat-container h1 {
            color: #128C7E;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .chat-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .chat-type-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .chat-type-btn.active {
            border-color: #128C7E;
            background-color: #e8f5e9;
            color: #128C7E;
        }
        
        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
        }
        
        .user-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .user-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-item input[type="checkbox"] {
            width: auto;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #128C7E;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="new-chat-container">
        <a href="index.php" class="back-link">← بازگشت به صفحه اصلی</a>
        
        <h1>ایجاد چت جدید</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="chat-type-selector">
                <div class="chat-type-btn active" onclick="selectType('private')">
                    💬 چت خصوصی
                </div>
                <div class="chat-type-btn" onclick="selectType('group')">
                    👥 گروه
                </div>
                <div class="chat-type-btn" onclick="selectType('channel')">
                    📢 کانال
                </div>
            </div>
            
            <input type="hidden" name="type" id="chatType" value="private">
            
            <!-- بخش چت خصوصی -->
            <div class="form-section active" id="privateSection">
                <div class="form-group">
                    <label for="target_user">انتخاب کاربر</label>
                    <select name="target_user" id="target_user" class="form-control" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">کاربر را انتخاب کنید...</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">شروع چت</button>
            </div>
            
            <!-- بخش گروه/کانال -->
            <div class="form-section" id="groupSection">
                <div class="form-group">
                    <label for="name">نام گروه/کانال</label>
                    <input type="text" name="name" id="groupName" placeholder="نام را وارد کنید...">
                </div>
                
                <div class="form-group">
                    <label>اعضا (اختیاری)</label>
                    <div class="user-list">
                        <?php foreach ($users as $u): ?>
                        <div class="user-item">
                            <input type="checkbox" name="members[]" value="<?php echo $u['id']; ?>" id="user_<?php echo $u['id']; ?>">
                            <label for="user_<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">ایجاد</button>
            </div>
        </form>
    </div>
    
    <script>
        function selectType(type) {
            document.getElementById('chatType').value = type;
            
            // بروزرسانی دکمه‌ها
            document.querySelectorAll('.chat-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // نمایش بخش مربوطه
            if (type === 'private') {
                document.getElementById('privateSection').classList.add('active');
                document.getElementById('groupSection').classList.remove('active');
            } else {
                document.getElementById('privateSection').classList.remove('active');
                document.getElementById('groupSection').classList.add('active');
            }
        }
    </script>
</body>
</html>
