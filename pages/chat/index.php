<?php
/**
 * SATA Messenger - Main Chat Interface
 * Implements Items: 101-120 (UI/UX), 118-119 (Responsive)
 */

// Verify authentication
$auth = new UserAuth();
if (!$auth->verifySession()) {
    header('Location: login');
    exit;
}

$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0B141A">
    <title>ساتا مسنجر</title>
    
    <!-- Item 338: PWA-like manifest -->
    <link rel="manifest" href="manifest.json">
    
    <style>
        /* Item 101: Dark Theme with #0B141A */
        :root {
            --bg-primary: #0B141A;
            --bg-secondary: #1F2C34;
            --bg-chat: #0B141A;
            --bg-message-out: #005c4b;
            --bg-message-in: #202c33;
            --text-primary: #e9edef;
            --text-secondary: #8696a0;
            --accent: #00a884;
            --border: #2f3b43;
            --glass-bg: rgba(31, 44, 52, 0.9);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
        }
        
        .app-container {
            display: flex;
            height: 100vh;
        }
        
        /* Sidebar - Chat List */
        .sidebar {
            width: 400px;
            background: var(--bg-primary);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 16px;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .search-box {
            padding: 8px 16px;
            background: var(--bg-primary);
        }
        
        .search-input {
            width: 100%;
            padding: 10px 15px;
            border-radius: 8px;
            border: none;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .chat-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .chat-item {
            display: flex;
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid var(--border);
        }
        
        .chat-item:hover {
            background: var(--bg-secondary);
        }
        
        .chat-item.active {
            background: var(--bg-secondary);
        }
        
        .chat-avatar {
            width: 49px;
            height: 49px;
            border-radius: 50%;
            background: var(--accent);
            margin-left: 15px;
            flex-shrink: 0;
        }
        
        .chat-info {
            flex: 1;
            min-width: 0;
        }
        
        .chat-name {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .chat-preview {
            font-size: 14px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-meta {
            text-align: left;
            min-width: 60px;
        }
        
        .chat-time {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        /* Main Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-chat);
        }
        
        .chat-header {
            padding: 10px 16px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            z-index: 100;
        }
        
        .back-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            margin-left: 10px;
            cursor: pointer;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        
        .message-bubble {
            max-width: 65%;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            position: relative;
            animation: messageSlide 0.3s ease-out;
        }
        
        @keyframes messageSlide {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-out {
            background: var(--bg-message-out);
            margin-right: auto;
            border-top-left-radius: 0;
        }
        
        .message-in {
            background: var(--bg-message-in);
            margin-left: auto;
            border-top-right-radius: 0;
        }
        
        .message-text {
            font-size: 14.2px;
            line-height: 19px;
        }
        
        .message-time {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.6);
            text-align: left;
            margin-top: 4px;
        }
        
        .message-input-area {
            padding: 10px 16px;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
        }
        
        .message-input {
            flex: 1;
            padding: 12px 15px;
            border-radius: 8px;
            border: none;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 15px;
            resize: none;
            max-height: 100px;
        }
        
        .send-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--accent);
            color: #fff;
            margin-right: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }
        
        .send-btn:hover {
            transform: scale(1.05);
        }
        
        /* Bottom Navigation (Mobile) */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border);
            padding: 8px 0;
        }
        
        .nav-item {
            flex: 1;
            text-align: center;
            padding: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .nav-item.active {
            color: var(--accent);
        }
        
        /* Item 119: Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
            }
            
            .chat-area {
                display: none;
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 200;
            }
            
            .chat-area.active {
                display: flex;
            }
            
            .back-btn {
                display: block;
            }
            
            .bottom-nav {
                display: flex;
            }
            
            .message-bubble {
                max-width: 85%;
            }
        }
        
        /* Custom Scrollbar (Item 313) */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
        
        /* Item 105: Skeleton Loader */
        .skeleton {
            background: linear-gradient(90deg, var(--bg-secondary) 25%, var(--border) 50%, var(--bg-secondary) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="user-avatar"><?php echo strtoupper(substr($user['display_name'] ?? $user['unique_id'], 0, 1)); ?></div>
                <div style="display: flex; gap: 15px;">
                    <button style="background:none;border:none;color:var(--text-primary);cursor:pointer;">📝</button>
                    <button style="background:none;border:none;color:var(--text-primary);cursor:pointer;">⋮</button>
                </div>
            </div>
            
            <div class="search-box">
                <input type="text" class="search-input" placeholder="جستجو یا شروع گفتگوی جدید">
            </div>
            
            <div class="chat-list" id="chatList">
                <!-- Chat items will be loaded here via AJAX (Item 19) -->
                <div class="skeleton" style="height: 72px; margin: 8px 16px; border-radius: 8px;"></div>
                <div class="skeleton" style="height: 72px; margin: 8px 16px; border-radius: 8px;"></div>
                <div class="skeleton" style="height: 72px; margin: 8px 16px; border-radius: 8px;"></div>
            </div>
            
            <!-- Bottom Nav for Mobile -->
            <div class="bottom-nav">
                <div class="nav-item active">💬</div>
                <div class="nav-item">🔄</div>
                <div class="nav-item">👥</div>
                <div class="nav-item">⚙️</div>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area" id="chatArea">
            <div class="chat-header">
                <button class="back-btn" onclick="closeChat()">←</button>
                <div class="chat-avatar" style="width: 40px; height: 40px; margin-left: 12px;"></div>
                <div class="chat-info">
                    <div class="chat-name">انتخاب کنید</div>
                    <div class="chat-preview">برای شروع پیام، یک چت را انتخاب کنید</div>
                </div>
            </div>
            
            <div class="messages-container" id="messagesContainer">
                <div style="text-align: center; color: var(--text-secondary); margin-top: 50px;">
                    <p>به ساتا مسنجر خوش آمدید</p>
                    <p style="font-size: 12px; margin-top: 10px;">پیام‌رسان امن نسل ۲۰۲۶</p>
                </div>
            </div>
            
            <div class="message-input-area">
                <button style="background:none;border:none;color:var(--text-secondary);font-size:20px;cursor:pointer;">📎</button>
                <input type="text" class="message-input" placeholder="پیام خود را بنویسید" id="messageInput">
                <button class="send-btn" onclick="sendMessage()">➤</button>
            </div>
        </div>
    </div>
    
    <script>
        // Item 19: Asynchronous loading
        async function loadChats() {
            try {
                const response = await fetch('api/get-chats');
                const data = await response.json();
                // Render chats here
            } catch (error) {
                console.error('Error loading chats:', error);
            }
        }
        
        // Item 270, 271: Auto-focus and send button behavior
        const messageInput = document.getElementById('messageInput');
        messageInput.addEventListener('input', function() {
            const btn = document.querySelector('.send-btn');
            if (this.value.trim()) {
                btn.textContent = '➤';
            } else {
                btn.textContent = '🎤';
            }
        });
        
        // Item 295-296: Enter to send
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            if (message) {
                // Send via AJAX
                input.value = '';
            }
        }
        
        // Mobile navigation
        function closeChat() {
            document.getElementById('chatArea').classList.remove('active');
        }
        
        // Item 61: Disable right-click
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
        
        // Load chats on page load
        loadChats();
    </script>
</body>
</html>
