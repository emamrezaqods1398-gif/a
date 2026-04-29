<?php
// index.php - صفحه اصلی چت

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$pdo;

// دریافت لیست چت‌ها
$stmt = $pdo->prepare("
    SELECT DISTINCT c.*, 
           (SELECT username FROM users WHERE id = c.created_by) as creator_name,
           (SELECT COUNT(*) FROM messages WHERE chat_id = c.id) as message_count
    FROM chats c
    JOIN chat_members cm ON c.id = cm.chat_id
    WHERE cm.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user['id']]);
$chats = $stmt->fetchAll();

// دریافت استوری‌های فعال
$stmt = $pdo->prepare("
    SELECT s.*, u.username, u.avatar
    FROM stories s
    JOIN users u ON s.user_id = u.id
    WHERE s.expires_at > NOW()
    AND s.user_id != ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$user['id']]);
$stories = $stmt->fetchAll();

$selected_chat_id = $_GET['chat'] ?? null;
$messages = [];
$current_chat = null;

if ($selected_chat_id) {
    // دریافت اطلاعات چت انتخاب شده
    $stmt = $pdo->prepare("SELECT * FROM chats WHERE id = ?");
    $stmt->execute([$selected_chat_id]);
    $current_chat = $stmt->fetch();
    
    if ($current_chat) {
        // دریافت پیام‌های چت
        $stmt = $pdo->prepare("
            SELECT m.*, u.username as sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.chat_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$selected_chat_id]);
        $messages = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیامرسان وب</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container <?php echo $selected_chat_id ? 'chat-active' : ''; ?>">
        <!-- سایدبار -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="user-info">
                    <div class="avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div>
                    <button class="action-btn" onclick="location.href='new_chat.php'" title="چت جدید">+</button>
                    <button class="action-btn" onclick="location.href='logout.php'" title="خروج">🚪</button>
                </div>
            </div>
            
            <!-- بخش استوری‌ها -->
            <?php if (!empty($stories)): ?>
            <div class="stories-section">
                <div class="stories-title">استوری‌ها</div>
                <div class="stories-container">
                    <?php foreach ($stories as $story): ?>
                    <div class="story-item" onclick="viewStory(<?php echo $story['id']; ?>)">
                        <div class="story-avatar">
                            <?php echo strtoupper(substr($story['username'], 0, 1)); ?>
                        </div>
                        <div class="story-username"><?php echo htmlspecialchars($story['username']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- لیست چت‌ها -->
            <div class="chat-list">
                <?php foreach ($chats as $chat): ?>
                <div class="chat-item <?php echo $chat['id'] == $selected_chat_id ? 'active' : ''; ?>" 
                     onclick="selectChat(<?php echo $chat['id']; ?>)">
                    <div class="chat-avatar">
                        <?php 
                        if ($chat['type'] === 'private') {
                            // پیدا کردن نام کاربر مقابل
                            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = (
                                SELECT user_id FROM chat_members WHERE chat_id = ? AND user_id != ?
                            )");
                            $stmt->execute([$chat['id'], $user['id']]);
                            $other_user = $stmt->fetch();
                            echo $other_user ? strtoupper(substr($other_user['username'], 0, 1)) : '?';
                        } else {
                            echo strtoupper(substr($chat['name'], 0, 1));
                        }
                        ?>
                    </div>
                    <div class="chat-details">
                        <div class="chat-name">
                            <?php 
                            if ($chat['type'] === 'private') {
                                echo $other_user['username'] ?? 'کاربر';
                            } else {
                                echo htmlspecialchars($chat['name']) . 
                                     ($chat['type'] === 'group' ? ' (گروه)' : ' (کانال)');
                            }
                            ?>
                        </div>
                        <div class="chat-last-message">
                            <?php echo $chat['message_count']; ?> پیام
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- ناحیه چت -->
        <div class="chat-area">
            <?php if ($current_chat): ?>
            <div class="chat-header">
                <div class="chat-avatar">
                    <?php 
                    if ($current_chat['type'] === 'private') {
                        echo strtoupper(substr($other_user['username'] ?? 'U', 0, 1));
                    } else {
                        echo strtoupper(substr($current_chat['name'], 0, 1));
                    }
                    ?>
                </div>
                <div style="margin-right: 15px;">
                    <div class="chat-name">
                        <?php 
                        if ($current_chat['type'] === 'private') {
                            echo $other_user['username'] ?? 'کاربر';
                        } else {
                            echo htmlspecialchars($current_chat['name']) . 
                                 ($current_chat['type'] === 'group' ? ' (گروه)' : ' (کانال)');
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="messages-container" id="messagesContainer">
                <?php foreach ($messages as $msg): ?>
                <div class="message <?php echo $msg['sender_id'] == $user['id'] ? 'sent' : 'received'; ?>">
                    <div class="message-text">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    </div>
                    <div class="message-time">
                        <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="message-input-area">
                <input type="text" class="message-input" id="messageInput" 
                       placeholder="پیام خود را بنویسید..." 
                       onkeypress="if(event.key === 'Enter') sendMessage()">
                <button class="send-btn" onclick="sendMessage()">➤</button>
            </div>
            
            <script>
                function sendMessage() {
                    const input = document.getElementById('messageInput');
                    const message = input.value.trim();
                    
                    if (!message) return;
                    
                    fetch('api/send_message.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            chat_id: <?php echo $selected_chat_id; ?>,
                            message: message
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('خطا در ارسال پیام');
                        }
                    });
                }
                
                function selectChat(chatId) {
                    window.location.href = 'index.php?chat=' + chatId;
                }
                
                // اسکرول به پایین پیام‌ها
                const container = document.getElementById('messagesContainer');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            </script>
            <?php else: ?>
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #999;">
                <div style="text-align: center;">
                    <div style="font-size: 64px; margin-bottom: 20px;">💬</div>
                    <h2>یک چت را انتخاب کنید</h2>
                    <p>برای شروع گفتگو، یک چت از لیست سمت راست انتخاب کنید</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
