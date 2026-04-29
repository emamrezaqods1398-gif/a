<?php
/**
 * Admin Dashboard - Complete God Mode (Features 1-185)
 * Full access to all system controls, user management, reports, and settings
 */
require_once '../config.php';

// Check admin access
if (!isAdmin()) {
    http_response_code(403);
    die('<h1 style="text-align:center;color:red;margin-top:50px;">دسترسی غیرمجاز - فقط ادمین</h1>');
}

$pdo = getDBConnection();
$adminId = getCurrentUserId();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_GET['action']) {
            // Toggle Registration (Feature #171)
            case 'toggle_registration':
                $currentValue = getSystemSetting('registration_enabled');
                $newValue = $currentValue === '1' ? '0' : '1';
                updateSystemSetting('registration_enabled', $newValue);
                logAdminAction($adminId, 'toggle_registration', 'setting', null, "New value: $newValue");
                $response = ['success' => true, 'new_value' => $newValue];
                break;
            
            // Toggle Maintenance Mode (Feature #174)
            case 'toggle_maintenance':
                $currentValue = getSystemSetting('maintenance_mode');
                $newValue = $currentValue === '1' ? '0' : '1';
                updateSystemSetting('maintenance_mode', $newValue);
                logAdminAction($adminId, 'toggle_maintenance', 'setting', null, "New value: $newValue");
                $response = ['success' => true, 'new_value' => $newValue];
                break;
            
            // Ban User
            case 'ban_user':
                $userId = $_POST['user_id'] ?? 0;
                $reason = $_POST['ban_reason'] ?? '';
                if ($userId && $reason) {
                    $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ? AND badge_type != 'black'");
                    $stmt->execute([$reason, $userId]);
                    logAdminAction($adminId, 'ban_user', 'user', $userId, "Reason: $reason");
                    $response = ['success' => true];
                }
                break;
            
            // Unban User
            case 'unban_user':
                $userId = $_POST['user_id'] ?? 0;
                if ($userId) {
                    $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                    logAdminAction($adminId, 'unban_user', 'user', $userId);
                    $response = ['success' => true];
                }
                break;
            
            // Delete User (Soft Delete - Feature #159)
            case 'delete_user':
                $userId = $_POST['user_id'] ?? 0;
                if ($userId) {
                    $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1, display_name = 'Deleted Account', avatar_path = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                    logAdminAction($adminId, 'delete_user', 'user', $userId);
                    $response = ['success' => true];
                }
                break;
            
            // Set User Badge (Feature #11)
            case 'set_badge':
                $userId = $_POST['user_id'] ?? 0;
                $badge = $_POST['badge_type'] ?? 'normal';
                if ($userId && in_array($badge, ['normal', 'blue', 'gold', 'black'])) {
                    $stmt = $pdo->prepare("UPDATE users SET badge_type = ? WHERE id = ?");
                    $stmt->execute([$badge, $userId]);
                    logAdminAction($adminId, 'set_badge', 'user', $userId, "Badge: $badge");
                    $response = ['success' => true];
                }
                break;
            
            // Reset User Password (Feature #6)
            case 'reset_password':
                $userId = $_POST['user_id'] ?? 0;
                $newPassword = $_POST['new_password'] ?? '';
                if ($userId && $newPassword) {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hash, $userId]);
                    logAdminAction($adminId, 'reset_password', 'user', $userId);
                    $response = ['success' => true];
                }
                break;
            
            // Reserve Handle (Feature #9, #153)
            case 'reserve_handle':
                $handle = $_POST['handle'] ?? '';
                $reason = $_POST['reason'] ?? 'Reserved by admin';
                if ($handle) {
                    $stmt = $pdo->prepare("INSERT INTO reserved_handles (handle, reason) VALUES (?, ?) ON DUPLICATE KEY UPDATE reason = ?");
                    $stmt->execute([strtolower($handle), $reason, $reason]);
                    logAdminAction($adminId, 'reserve_handle', 'setting', null, "Handle: @$handle");
                    $response = ['success' => true];
                }
                break;
            
            // Update Story Limits (Feature #12, #154)
            case 'update_story_limit':
                $badgeType = $_POST['badge_type'] ?? '';
                $limit = $_POST['limit'] ?? 0;
                if ($badgeType && isset(STORY_LIMITS[$badgeType])) {
                    updateSystemSetting("story_limit_$badgeType", $limit);
                    logAdminAction($adminId, 'update_story_limit', 'setting', null, "$badgeType: $limit");
                    $response = ['success' => true];
                }
                break;
            
            // Review Ban Appeal (Feature #13, #158)
            case 'review_appeal':
                $appealId = $_POST['appeal_id'] ?? 0;
                $decision = $_POST['decision'] ?? ''; // approve or reject
                $adminResponse = $_POST['admin_response'] ?? '';
                if ($appealId && in_array($decision, ['approve', 'reject'])) {
                    $stmt = $pdo->prepare("SELECT user_id FROM ban_appeals WHERE id = ?");
                    $stmt->execute([$appealId]);
                    $appeal = $stmt->fetch();
                    
                    if ($appeal) {
                        $status = $decision === 'approve' ? 'approved' : 'rejected';
                        $stmt = $pdo->prepare("UPDATE ban_appeals SET status = ?, admin_response = ?, reviewed_at = NOW() WHERE id = ?");
                        $stmt->execute([$status, $adminResponse, $appealId]);
                        
                        if ($decision === 'approve') {
                            $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL, appeal_status = 'approved' WHERE id = ?");
                            $stmt->execute([$appeal['user_id']]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET appeal_status = 'rejected' WHERE id = ?");
                            $stmt->execute([$appeal['user_id']]);
                        }
                        
                        logAdminAction($adminId, 'review_appeal', 'user', $appeal['user_id'], "Decision: $decision");
                        $response = ['success' => true];
                    }
                }
                break;
            
            // Review Report (Message or User)
            case 'review_report':
                $reportId = $_POST['report_id'] ?? 0;
                $reportType = $_POST['report_type'] ?? 'message'; // message or user
                $action = $_POST['action'] ?? ''; // dismiss, ban, delete_message
                $adminNote = $_POST['admin_note'] ?? '';
                
                if ($reportId && $reportType) {
                    $status = 'dismissed';
                    if ($action === 'ban') {
                        $targetId = $_POST['target_user_id'] ?? 0;
                        if ($targetId) {
                            $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?");
                            $stmt->execute(["Reported content violation. Admin note: $adminNote", $targetId]);
                            $status = 'action_taken';
                        }
                    } elseif ($action === 'delete_message') {
                        $messageId = $_POST['message_id'] ?? 0;
                        if ($messageId) {
                            $stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1, delete_for_all = 1 WHERE id = ?");
                            $stmt->execute([$messageId]);
                            $status = 'action_taken';
                        }
                    }
                    
                    $table = $reportType === 'message' ? 'message_reports' : 'user_reports';
                    $stmt = $pdo->prepare("UPDATE $table SET status = ?, admin_note = ? WHERE id = ?");
                    $stmt->execute([$status, $adminNote, $reportId]);
                    
                    logAdminAction($adminId, 'review_report', $reportType, $reportId, "Action: $action");
                    $response = ['success' => true];
                }
                break;
            
            // Mass Ban Users (Feature #119)
            case 'mass_ban':
                $userIds = $_POST['user_ids'] ?? [];
                $reason = $_POST['ban_reason'] ?? 'Mass ban by admin';
                if (!empty($userIds)) {
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_reason = ? WHERE id IN ($placeholders) AND badge_type != 'black'");
                    $stmt->execute(array_merge([$reason], $userIds));
                    logAdminAction($adminId, 'mass_ban', 'user', null, "Count: " . count($userIds));
                    $response = ['success' => true];
                }
                break;
        }
        
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get statistics
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_deleted = 0");
$stats['total_users'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1");
$stats['banned_users'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stats['messages_24h'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM message_reports WHERE status = 'pending'");
$stats['pending_reports'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM ban_appeals WHERE status = 'pending'");
$stats['pending_appeals'] = $stmt->fetchColumn();

// Get pending appeals
$stmt = $pdo->query("SELECT ba.*, u.display_name, u.phone FROM ban_appeals ba JOIN users u ON ba.user_id = u.id WHERE ba.status = 'pending' ORDER BY ba.created_at DESC LIMIT 20");
$pendingAppeals = $stmt->fetchAll();

// Get pending reports
$stmt = $pdo->query("(SELECT mr.id, mr.message_id, mr.reporter_id, mr.reason_category, mr.description, mr.created_at, u.display_name as reporter_name, 'message' as type FROM message_reports mr JOIN users u ON mr.reporter_id = u.id WHERE mr.status = 'pending') UNION ALL (SELECT ur.id, NULL, ur.reporter_id, ur.reason_category, ur.description, ur.created_at, u.display_name as reporter_name, 'user' as type FROM user_reports ur JOIN users u ON ur.reporter_id = u.id WHERE ur.status = 'pending') ORDER BY created_at DESC LIMIT 30");
$pendingReports = $stmt->fetchAll();

// Get recent users
$stmt = $pdo->query("SELECT id, username, display_name, phone, badge_type, is_banned, is_deleted, created_at FROM users WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT 50");
$recentUsers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت پیام‌رسان</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        /* Dark Mode Support (Feature #123) */
        body.dark-mode { background: #1a1a1a; color: #fff; }
        body.dark-mode .panel { background: #2d2d2d; color: #fff; }
        body.dark-mode .stat-card { background: #333; color: #fff; }
        body.dark-mode table th { background: #444; color: #fff; }
        body.dark-mode table td { border-color: #444; }
        
        .header {
            background: linear-gradient(135deg, #075e54 0%, #128C7E 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 24px; }
        .dark-mode-toggle {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number { font-size: 36px; font-weight: bold; color: #075e54; }
        .stat-label { color: #666; margin-top: 5px; }
        
        .panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .panel-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .panel-title { font-size: 18px; font-weight: bold; color: #333; }
        .panel-body { padding: 20px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: right; border-bottom: 1px solid #e0e0e0; }
        th { background: #f8f9fa; font-weight: 600; }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-primary { background: #075e54; color: white; }
        .btn-danger { background: #d32f2f; color: white; }
        .btn-success { background: #2e7d32; color: white; }
        .btn-warning { background: #f57c00; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-normal { background: #e0e0e0; color: #666; }
        .badge-blue { background: #2196F3; color: white; }
        .badge-gold { background: #FFD700; color: #333; }
        .badge-black { background: #333; color: white; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .tabs { display: flex; border-bottom: 2px solid #e0e0e0; margin-bottom: 20px; }
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }
        .tab.active { border-bottom-color: #075e54; color: #075e54; font-weight: bold; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .alert-info { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-warning { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #075e54; }
        input:checked + .slider:before { transform: translateX(26px); }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ پنل مدیریت پیام‌رسان</h1>
        <button class="dark-mode-toggle" onclick="toggleDarkMode()">🌙 حالت شب</button>
    </div>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">کاربران فعال</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['banned_users']; ?></div>
                <div class="stat-label">کاربران مسدود</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['messages_24h']; ?></div>
                <div class="stat-label">پیام‌های ۲۴ ساعت</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_reports']; ?></div>
                <div class="stat-label">گزارش‌های در انتظار</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_appeals']; ?></div>
                <div class="stat-label">درخواست‌های رفع بن</div>
            </div>
        </div>
        
        <!-- Main Panels -->
        <div class="panel">
            <div class="tabs">
                <div class="tab active" onclick="showTab('users')">👥 کاربران</div>
                <div class="tab" onclick="showTab('reports')">📋 گزارش‌ها</div>
                <div class="tab" onclick="showTab('appeals')">📨 درخواست‌های بن</div>
                <div class="tab" onclick="showTab('settings')">⚙️ تنظیمات سیستم</div>
                <div class="tab" onclick="showTab('badges')">⭐ تیک‌ها و محدودیت‌ها</div>
            </div>
            
            <!-- Users Tab -->
            <div class="tab-content active" id="users-tab">
                <div class="panel-body">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>نام</th>
                                <th>شماره</th>
                                <th>تیک</th>
                                <th>وضعیت</th>
                                <th>عضویت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['display_name'] ?? $user['username'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><span class="badge badge-<?php echo $user['badge_type']; ?>"><?php echo $user['badge_type']; ?></span></td>
                                <td>
                                    <?php if ($user['is_banned']): ?>
                                        <span style="color: red;">مسدود</span>
                                    <?php else: ?>
                                        <span style="color: green;">فعال</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['created_at']; ?></td>
                                <td>
                                    <select onchange="handleUserAction(this.value, <?php echo $user['id']; ?>)" style="padding: 5px;">
                                        <option value="">انتخاب...</option>
                                        <option value="ban">مسدود کردن</option>
                                        <option value="unban">رفع مسدودیت</option>
                                        <option value="delete">حذف اکانت</option>
                                        <option value="password">تغییر رمز</option>
                                        <option value="badge">تغییر تیک</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Reports Tab -->
            <div class="tab-content" id="reports-tab">
                <div class="panel-body">
                    <?php if (empty($pendingReports)): ?>
                        <p style="text-align: center; color: #666;">هیچ گزارش جدیدی وجود ندارد.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>نوع</th>
                                    <th>گزارش‌دهنده</th>
                                    <th>دلیل</th>
                                    <th>توضیحات</th>
                                    <th>تاریخ</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingReports as $report): ?>
                                <tr>
                                    <td><?php echo $report['type'] === 'message' ? '💬 پیام' : '👤 کاربر'; ?></td>
                                    <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                                    <td><?php echo $report['reason_category']; ?></td>
                                    <td><?php echo htmlspecialchars(substr($report['description'], 0, 50)); ?>...</td>
                                    <td><?php echo $report['created_at']; ?></td>
                                    <td>
                                        <button class="btn btn-success" onclick="reviewReport(<?php echo $report['id']; ?>, '<?php echo $report['type']; ?>', 'dismiss')">رد کردن</button>
                                        <?php if ($report['type'] === 'message'): ?>
                                            <button class="btn btn-warning" onclick="reviewReport(<?php echo $report['id']; ?>, '<?php echo $report['type']; ?>', 'delete_message')">حذف پیام</button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger" onclick="reviewReport(<?php echo $report['id']; ?>, '<?php echo $report['type']; ?>', 'ban')">بن کاربر</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Appeals Tab -->
            <div class="tab-content" id="appeals-tab">
                <div class="panel-body">
                    <?php if (empty($pendingAppeals)): ?>
                        <p style="text-align: center; color: #666;">هیچ درخواست بررسی مجددی وجود ندارد.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>کاربر</th>
                                    <th>شماره</th>
                                    <th>متن درخواست</th>
                                    <th>تاریخ</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingAppeals as $appeal): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($appeal['display_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appeal['phone']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($appeal['appeal_text'], 0, 80)); ?>...</td>
                                    <td><?php echo $appeal['created_at']; ?></td>
                                    <td>
                                        <button class="btn btn-success" onclick="reviewAppeal(<?php echo $appeal['id']; ?>, 'approve')">پذیرش</button>
                                        <button class="btn btn-danger" onclick="reviewAppeal(<?php echo $appeal['id']; ?>, 'reject')">رد</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div class="tab-content" id="settings-tab">
                <div class="panel-body">
                    <div class="form-group">
                        <label>ثبت‌نام کاربران (Feature #171)</label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <label class="switch">
                                <input type="checkbox" id="reg-toggle" <?php echo getSystemSetting('registration_enabled') === '1' ? 'checked' : ''; ?> onchange="toggleSetting('registration')">
                                <span class="slider"></span>
                            </label>
                            <span id="reg-status"><?php echo getSystemSetting('registration_enabled') === '1' ? 'فعال' : 'غیرفعال'; ?></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>حالت تعمیرات (Feature #174)</label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <label class="switch">
                                <input type="checkbox" id="maint-toggle" <?php echo getSystemSetting('maintenance_mode') === '1' ? 'checked' : ''; ?> onchange="toggleSetting('maintenance')">
                                <span class="slider"></span>
                            </label>
                            <span id="maint-status"><?php echo getSystemSetting('maintenance_mode') === '1' ? 'فعال' : 'غیرفعال'; ?></span>
                        </div>
                    </div>
                    
                    <hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">
                    
                    <div class="form-group">
                        <label>رزرو آیدی (Feature #153)</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="reserve-handle" placeholder="@username">
                            <input type="text" id="reserve-reason" placeholder="دلیل رزرو" style="flex: 1;">
                            <button class="btn btn-primary" onclick="reserveHandle()">رزرو</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Badges Tab -->
            <div class="tab-content" id="badges-tab">
                <div class="panel-body">
                    <div class="alert alert-info">
                        تنظیم محدودیت استوری برای هر سطح کاربری (Feature #151, #154)
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <?php foreach (STORY_LIMITS as $badge => $default): 
                            $current = getSystemSetting("story_limit_$badge") ?: $default;
                        ?>
                        <div class="stat-card">
                            <h3>تیک <?php echo $badge; ?></h3>
                            <div class="form-group" style="margin-top: 15px;">
                                <label>محدودیت استوری روزانه:</label>
                                <input type="number" id="limit-<?php echo $badge; ?>" value="<?php echo $current; ?>">
                                <button class="btn btn-primary" style="margin-top: 10px;" onclick="updateStoryLimit('<?php echo $badge; ?>')">ذخیره</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
        }
        
        function handleUserAction(action, userId) {
            if (!action) return;
            
            if (action === 'password') {
                const newPassword = prompt('رمز عبور جدید:');
                if (newPassword) {
                    fetch('?action=reset_password', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `user_id=${userId}&new_password=${encodeURIComponent(newPassword)}`
                    }).then(r => r.json()).then(d => {
                        if (d.success) alert('رمز عبور تغییر کرد.');
                        else alert('خطا: ' + d.message);
                    });
                }
            } else if (action === 'badge') {
                const badge = prompt('نوع تیک (normal, blue, gold, black):');
                if (badge) {
                    fetch('?action=set_badge', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `user_id=${userId}&badge_type=${badge}`
                    }).then(r => r.json()).then(d => {
                        if (d.success) location.reload();
                        else alert('خطا: ' + d.message);
                    });
                }
            } else if (action === 'ban') {
                const reason = prompt('دلیل مسدودیت:');
                if (reason) {
                    fetch('?action=ban_user', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `user_id=${userId}&ban_reason=${encodeURIComponent(reason)}`
                    }).then(r => r.json()).then(d => {
                        if (d.success) location.reload();
                        else alert('خطا: ' + d.message);
                    });
                }
            } else if (action === 'unban') {
                fetch('?action=unban_user', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `user_id=${userId}`
                }).then(r => r.json()).then(d => {
                    if (d.success) location.reload();
                    else alert('خطا: ' + d.message);
                });
            } else if (action === 'delete') {
                if (confirm('آیا مطمئن هستید؟ این عملیات غیرقابل بازگشت است.')) {
                    fetch('?action=delete_user', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `user_id=${userId}`
                    }).then(r => r.json()).then(d => {
                        if (d.success) location.reload();
                        else alert('خطا: ' + d.message);
                    });
                }
            }
        }
        
        function reviewAppeal(appealId, decision) {
            const response = decision === 'approve' ? '' : prompt('دلیل رد درخواست:');
            fetch('?action=review_appeal', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `appeal_id=${appealId}&decision=${decision}&admin_response=${encodeURIComponent(response || '')}`
            }).then(r => r.json()).then(d => {
                if (d.success) location.reload();
                else alert('خطا: ' + d.message);
            });
        }
        
        function reviewReport(reportId, type, action) {
            let targetUserId = null, messageId = null, adminNote = '';
            
            if (action === 'ban') {
                targetUserId = prompt('ID کاربر برای بن:');
                if (!targetUserId) return;
            } else if (action === 'delete_message') {
                messageId = prompt('ID پیام برای حذف:');
                if (!messageId) return;
            }
            
            adminNote = prompt('یادداشت ادمین (اختیاری):') || '';
            
            fetch('?action=review_report', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `report_id=${reportId}&report_type=${type}&action=${action}&target_user_id=${targetUserId || ''}&message_id=${messageId || ''}&admin_note=${encodeURIComponent(adminNote)}`
            }).then(r => r.json()).then(d => {
                if (d.success) location.reload();
                else alert('خطا: ' + d.message);
            });
        }
        
        function toggleSetting(setting) {
            const action = setting === 'registration' ? 'toggle_registration' : 'toggle_maintenance';
            fetch('?action=' + action, {method: 'POST'})
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        document.getElementById(setting + '-status').textContent = d.new_value === '1' ? 'فعال' : 'غیرفعال';
                    }
                });
        }
        
        function reserveHandle() {
            const handle = document.getElementById('reserve-handle').value.replace('@', '');
            const reason = document.getElementById('reserve-reason').value;
            
            if (!handle) {
                alert('لطفاً آیدی را وارد کنید.');
                return;
            }
            
            fetch('?action=reserve_handle', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `handle=${handle}&reason=${encodeURIComponent(reason)}`
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    alert('آیدی با موفقیت رزرو شد.');
                    document.getElementById('reserve-handle').value = '';
                    document.getElementById('reserve-reason').value = '';
                } else {
                    alert('خطا: ' + d.message);
                }
            });
        }
        
        function updateStoryLimit(badge) {
            const limit = document.getElementById('limit-' + badge).value;
            fetch('?action=update_story_limit', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `badge_type=${badge}&limit=${limit}`
            }).then(r => r.json()).then(d => {
                if (d.success) alert('محدودیت با موفقیت به‌روزرسانی شد.');
                else alert('خطا: ' + d.message);
            });
        }
    </script>
</body>
</html>
