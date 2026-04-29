<?php
// story.php - نمایش و مدیریت استوری

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$action = $_GET['action'] ?? 'view';

// آپلود استوری جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['story_file'])) {
    $file = $_FILES['story_file'];
    $content_type = $_POST['content_type'] ?? 'image';
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4'];
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'نوع فایل مجاز نیست';
        } else {
            // تولید نام فایل یکتا
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $upload_path = 'uploads/stories/' . $filename;
            
            if (!is_dir('uploads/stories')) {
                mkdir('uploads/stories', 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // ثبت استوری در دیتابیس (24 ساعت اعتبار)
                $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                $stmt = $pdo->prepare("INSERT INTO stories (user_id, content_url, content_type, expires_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user['id'], $upload_path, $content_type, $expires_at]);
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'خطا در آپلود فایل';
            }
        }
    } else {
        $error = 'خطا در آپلود فایل';
    }
}

// حذف استوری
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $story_id = (int)$_GET['delete'];
    $expected_token = hash('sha256', $user['id'] . '_delete_story_' . $story_id);
    
    if ($_GET['token'] === $expected_token) {
        $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ? AND user_id = ?");
        $stmt->execute([$story_id, $user['id']]);
        header('Location: story.php?deleted=1');
        exit;
    }
}

// دریافت استوری‌های کاربر
$stmt = $pdo->prepare("SELECT * FROM stories WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$my_stories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استوری‌ها - پیامرسان</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .story-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .story-container h1 {
            color: #128C7E;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .upload-form {
            background: #f9f9f9;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
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
        
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .story-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .story-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .story-card img,
        .story-card video {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .story-card-body {
            padding: 15px;
        }
        
        .story-card-time {
            font-size: 12px;
            color: #999;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
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
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
    </style>
</head>
<body>
    <div class="story-container">
        <a href="index.php" class="back-link">← بازگشت به صفحه اصلی</a>
        
        <h1>مدیریت استوری‌ها</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- فرم آپلود استوری -->
        <div class="upload-form">
            <h3 style="margin-bottom: 20px; color: #128C7E;">آپلود استوری جدید</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="story_file">انتخاب فایل (عکس یا ویدیو)</label>
                    <input type="file" name="story_file" id="story_file" accept="image/*,video/*" required>
                </div>
                
                <div class="form-group">
                    <label for="content_type">نوع محتوا</label>
                    <select name="content_type" id="content_type">
                        <option value="image">عکس</option>
                        <option value="video">ویدیو</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">آپلود استوری</button>
            </form>
        </div>
        
        <!-- لیست استوری‌های من -->
        <h3 style="margin-bottom: 20px; color: #128C7E;">استوری‌های من</h3>
        
        <?php if (empty($my_stories)): ?>
            <p style="text-align: center; color: #999;">هنوز استوری‌ای ندارید</p>
        <?php else: ?>
            <div class="stories-grid">
                <?php foreach ($my_stories as $story): ?>
                <div class="story-card">
                    <?php if ($story['content_type'] === 'image'): ?>
                        <img src="<?php echo htmlspecialchars($story['content_url']); ?>" alt="Story">
                    <?php else: ?>
                        <video src="<?php echo htmlspecialchars($story['content_url']); ?>" controls></video>
                    <?php endif; ?>
                    
                    <div class="story-card-body">
                        <div class="story-card-time">
                            ایجاد: <?php echo date('Y/m/d H:i', strtotime($story['created_at'])); ?><br>
                            انقضا: <?php echo date('Y/m/d H:i', strtotime($story['expires_at'])); ?>
                        </div>
                        
                        <a href="?delete=<?php echo $story['id']; ?>&token=<?php echo hash('sha256', $user['id'] . '_delete_story_' . $story['id']); ?>" 
                           class="btn-delete"
                           onclick="return confirm('آیا از حذف این استوری مطمئن هستید؟')">
                            حذف استوری
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
