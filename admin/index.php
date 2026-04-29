<?php
// admin/index.php - پنل مدیریت

require_once '../config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$pdo;

// آمار کلی
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM chats");
$stats['total_chats'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
$stats['total_messages'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM stories");
$stats['total_stories'] = $stmt->fetch()['total'];

// لیست کاربران
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// مدیریت کاربران (حذف)
if (isset($_GET['delete_user']) && isset($_GET['token'])) {
    if ($_GET['token'] === hash('sha256', $_SESSION['user_id'] . 'delete_user')) {
        $user_id = (int)$_GET['delete_user'];
        if ($user_id !== $_SESSION['user_id']) { // نمی‌توان خود را حذف کرد
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            header('Location: index.php?deleted=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت - پیامرسان</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #128C7E 0%, #25D366 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header h1 {
            margin: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #128C7E;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .content-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .content-box h2 {
            color: #128C7E;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #f0f2f5;
        }
        
        th {
            background-color: #f9f9f9;
            color: #333;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-admin {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .badge-user {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .btn-danger:hover {
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
    </style>
</head>
<body>
    <div class="admin-container">
        <a href="../index.php" class="back-link">← بازگشت به پیامرسان</a>
        
        <div class="admin-header">
            <div>
                <h1>پنل مدیریت</h1>
                <p style="margin-top: 10px; opacity: 0.9;">خوش آمدید، <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
            <a href="../logout.php" class="btn btn-primary">خروج</a>
        </div>
        
        <!-- آمار -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">کاربران</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_chats']; ?></div>
                <div class="stat-label">چت‌ها</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_messages']; ?></div>
                <div class="stat-label">پیام‌ها</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_stories']; ?></div>
                <div class="stat-label">استوری‌ها</div>
            </div>
        </div>
        
        <!-- لیست کاربران -->
        <div class="content-box">
            <h2>لیست کاربران</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>نام کاربری</th>
                        <th>نقش</th>
                        <th>تاریخ ثبت‌نام</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td>
                            <span class="badge <?php echo $u['role'] === 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                                <?php echo $u['role'] === 'admin' ? 'مدیر' : 'کاربر'; ?>
                            </span>
                        </td>
                        <td><?php echo date('Y/m/d H:i', strtotime($u['created_at'])); ?></td>
                        <td>
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                            <a href="?delete_user=<?php echo $u['id']; ?>&token=<?php echo hash('sha256', $_SESSION['user_id'] . 'delete_user'); ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('آیا از حذف این کاربر مطمئن هستید؟')">
                                حذف
                            </a>
                            <?php else: ?>
                            <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
