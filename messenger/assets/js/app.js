/**
 * پیام‌رسان وب نسل ۲۰۲۶ - جاوااسکریپت اصلی
 */

// متغیرهای سراسری
let currentChatId = null;
let lastMessageId = 0;
let isOnline = navigator.onLine;

// بررسی وضعیت آنلاین/آفلاین
window.addEventListener('online', () => {
    isOnline = true;
    showNotification('اتصال مجدد برقرار شد', 'success');
});

window.addEventListener('offline', () => {
    isOnline = false;
    showNotification('شما آفلاین هستید', 'warning');
});

// لود کردن چت‌ها در صفحه اصلی
async function loadChats() {
    const chatList = document.getElementById('chatList');
    if (!chatList) return;
    
    try {
        const response = await fetch('api/get_chats.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            renderChats(data.chats);
        } else {
            chatList.innerHTML = '<div class="empty-state">هیچ گفتگویی یافت نشد</div>';
        }
    } catch (error) {
        console.error('Error loading chats:', error);
        chatList.innerHTML = '<div class="empty-state">خطا در بارگذاری گفتگوها</div>';
    }
}

// رندر کردن لیست چت‌ها
function renderChats(chats) {
    const chatList = document.getElementById('chatList');
    if (!chats || chats.length === 0) {
        chatList.innerHTML = '<div class="empty-state">هیچ گفتگویی یافت نشد</div>';
        return;
    }
    
    chatList.innerHTML = chats.map(chat => `
        <a href="chat.php?id=${chat.id}" class="chat-item">
            <img src="uploads/${chat.avatar}" alt="${escapeHtml(chat.name)}" class="chat-avatar">
            <div class="chat-info">
                <div class="chat-name">
                    ${escapeHtml(chat.name)}
                    ${chat.badge_type !== 'normal' ? `<span class="badge badge-${chat.badge_type}">✓</span>` : ''}
                </div>
                <div class="chat-preview">${escapeHtml(chat.last_message || 'بدون پیام')}</div>
            </div>
            <div class="chat-meta">
                <div class="chat-time">${formatTime(chat.last_message_time)}</div>
                ${chat.unread_count > 0 ? `<div class="unread-badge">${chat.unread_count}</div>` : ''}
            </div>
        </a>
    `).join('');
}

// لود کردن پیام‌های یک چت
async function loadMessages(chatId, groupId = null, channelId = null) {
    const messagesList = document.getElementById('messagesList');
    if (!messagesList) return;
    
    try {
        let url = 'api/get_messages.php?';
        if (chatId) url += `chat_id=${chatId}`;
        if (groupId) url += `group_id=${groupId}`;
        if (channelId) url += `channel_id=${channelId}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.status === 'success') {
            renderMessages(data.messages);
            scrollToBottom();
        }
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

// رندر کردن پیام‌ها
function renderMessages(messages) {
    const messagesList = document.getElementById('messagesList');
    if (!messages || messages.length === 0) {
        messagesList.innerHTML = '<div class="empty-state">هنوز پیامی ارسال نشده است</div>';
        return;
    }
    
    messagesList.innerHTML = messages.map(msg => `
        <div class="message ${msg.sender_id == currentUserId ? 'sent' : 'received'}" data-id="${msg.id}">
            ${msg.media_type !== 'none' ? renderMedia(msg) : ''}
            ${msg.message_text ? `<div class="message-text">${escapeHtml(msg.message_text)}</div>` : ''}
            <div class="message-time">${formatTime(msg.created_at)}</div>
        </div>
    `).join('');
}

// رندر کردن مدیا
function renderMedia(msg) {
    const basePath = 'uploads/' + msg.media_path;
    
    switch (msg.media_type) {
        case 'image':
            return `<img src="${basePath}" alt="Image" class="message-media" onclick="openLightbox('${basePath}')">`;
        case 'video':
            return `<video src="${basePath}" controls class="message-media"></video>`;
        case 'voice':
            return `<audio src="${basePath}" controls class="message-audio"></audio>`;
        case 'document':
            return `<a href="${basePath}" download class="message-document">📎 دانلود فایل</a>`;
        default:
            return '';
    }
}

// ارسال پیام
async function sendMessage() {
    const input = document.getElementById('messageInput');
    const messageText = input.value.trim();
    
    if (!messageText && !window.selectedMedia) return;
    
    const formData = new FormData();
    formData.append('message_text', messageText);
    
    if (typeof chatId !== 'undefined') {
        formData.append('chat_id', chatId);
    }
    
    if (window.selectedMedia) {
        formData.append('media', window.selectedMedia);
    }
    
    try {
        const response = await fetch('api/send_message.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            input.value = '';
            window.selectedMedia = null;
            
            // اضافه کردن پیام به لیست
            appendMessage({
                id: data.message_id,
                sender_id: currentUserId,
                message_text: messageText,
                media_type: data.media_type,
                media_path: data.media_path,
                created_at: data.created_at
            });
            
            scrollToBottom();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Error sending message:', error);
        showNotification('خطا در ارسال پیام', 'error');
    }
}

// اضافه کردن پیام به لیست
function appendMessage(msg) {
    const messagesList = document.getElementById('messagesList');
    const emptyState = messagesList.querySelector('.empty-state');
    if (emptyState) emptyState.remove();
    
    const messageEl = document.createElement('div');
    messageEl.className = `message ${msg.sender_id == currentUserId ? 'sent' : 'received'}`;
    messageEl.dataset.id = msg.id;
    messageEl.innerHTML = `
        ${msg.media_type !== 'none' ? renderMedia(msg) : ''}
        ${msg.message_text ? `<div class="message-text">${escapeHtml(msg.message_text)}</div>` : ''}
        <div class="message-time">${formatTime(msg.created_at)}</div>
    `;
    
    messagesList.appendChild(messageEl);
}

// اسکرول به پایین
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// انتخاب فایل
function openFilePicker() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*,video/*,audio/*,.pdf,.doc,.docx,.zip';
    input.onchange = (e) => {
        window.selectedMedia = e.target.files[0];
        showNotification('فایل انتخاب شد، اکنون ارسال کنید', 'success');
    };
    input.click();
}

// فرمت زمان
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'همین الان';
    if (diff < 3600000) return Math.floor(diff / 60000) + ' دقیقه پیش';
    if (diff < 86400000) return Math.floor(diff / 3600000) + ' ساعت پیش';
    
    return date.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
}

// فرار کردن HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// نمایش نوتیفیکیشن
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 80px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#ff5252' : '#2196f3'};
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        z-index: 2000;
        animation: slideDown 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// خروج از حساب
function logout() {
    if (confirm('آیا مطمئن هستید که می‌خواهید خارج شوید؟')) {
        fetch('api/logout.php').then(() => {
            window.location.href = 'login.php';
        });
    }
}

// تغییر تم
function toggleTheme() {
    document.body.classList.toggle('light-theme');
    localStorage.setItem('theme', document.body.classList.contains('light-theme') ? 'light' : 'dark');
}

// پولینگ برای پیام‌های جدید
setInterval(() => {
    if (typeof chatId !== 'undefined' && isOnline) {
        loadMessages(chatId);
    }
}, 5000);

// لود اولیه
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('chatList')) {
        loadChats();
    }
    
    // بازیابی تم ذخیره‌شده
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        document.body.classList.add('light-theme');
    }
});
