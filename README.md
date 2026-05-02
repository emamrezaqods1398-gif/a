# SATA Messenger - 2026 Gen Web-Based WhatsApp Clone

## 📋 Project Overview
SATA Messenger is a complete, production-ready messaging platform built with **Pure PHP 8.x** and **Vanilla JavaScript**, optimized for shared hosting environments. This project implements all **440 atomic specifications** from the engineering document.

## 🏗 Architecture

### Technology Stack
- **Backend**: Pure PHP 8.x (No Framework)
- **Frontend**: Vanilla JavaScript (No React/Vue)
- **Database**: MySQL with InnoDB engine
- **Storage**: Local file system with optimization

### Directory Structure
```
/workspace
├── config/              # Configuration files
│   └── config.php      # Main configuration
├── includes/           # Core PHP classes
│   ├── Database.php    # PDO Database connection
│   ├── Helpers.php     # Utility functions
│   └── UserAuth.php    # Authentication logic
├── api/                # API endpoints
│   ├── auth/          # Authentication APIs
│   ├── chat/          # Chat-related APIs
│   └── media/         # Media upload APIs
├── pages/              # Page controllers
│   ├── auth/          # Login/Register pages
│   ├── chat/          # Chat interface
│   ├── settings/      # User settings
│   └── admin/         # Admin dashboard
├── assets/             # Static files
│   ├── css/           # Stylesheets
│   ├── js/            # JavaScript files
│   └── images/        # Image assets
├── uploads/            # User uploaded files
├── database_schema.sql # Complete DB schema
├── index.php          # Main router
└── README.md          # This file
```

## 🔐 Security Features (Implemented)

1. **SQL Injection Prevention** - PDO with prepared statements (Item 5)
2. **XSS Protection** - htmlspecialchars on all inputs (Item 6)
3. **CSRF Tokens** - On all POST forms (Item 8)
4. **Session Management** - Auto-expiry after 24h (Item 7)
5. **Password Hashing** - Bcrypt algorithm (Item 27)
6. **IP Lockout** - After 5 failed attempts (Item 25)
7. **Input Sanitization** - Custom sanitizeInput() function (Item 251)
8. **Secure OTP** - Cryptographically random 5-digit codes (Item 252)

## 📱 Key Features

### Authentication (Items 21-40)
- Email-based registration/login
- 5-digit OTP verification
- 2FA support (optional)
- Session management (max 3 devices)
- Device tracking

### Sata Bot (Items 41-60)
- System account (@sata)
- Automated security notifications
- Broadcast messaging capability
- Protected from user interactions

### Privacy & Anti-Theft (Items 61-80)
- Right-click disabled
- Text selection blocked
- Screenshot detection (browser-supported)
- No-download toggle for media
- Online status privacy

### Chat Features (Items 121-140)
- Custom context menu
- Message reactions (6 emojis)
- Reply/Edit/Delete functionality
- Read receipts (ticks system)
- Pin chats (up to 5)
- Archive chats

### Groups (Items 141-165)
- Hierarchical admin system
- 15 granular admin permissions
- Custom admin titles
- Invite links with expiry
- Member limits based on verification

### Channels (Items 166-180)
- One-way broadcasting
- View counter per message
- Admin signatures
- Reaction-only for members

### Verification System (Items 181-195)
- 3 tick levels: Black, Blue, Gold
- Dynamic quotas per tier
- In-app application form
- Admin review queue

### Stories/Updates (Items 196-215)
- 24-hour auto-delete
- Progress bar UI
- Tap navigation
- Viewer list
- Daily quotas by tier

### Media Handling (Items 216-235)
- Server-side compression (GD)
- BlurHash thumbnails
- Chunked uploads
- Byte-range video streaming
- Orphaned file cleanup

### Admin Dashboard (Items 236-250)
- No separate login (UI toggle)
- Real-time stats
- User management
- Verification review
- System logs
- Dynamic quota editor

## 🚀 Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB
- GD Extension enabled
- mod_rewrite enabled (Apache)

### Steps

1. **Clone/Download** the project to your web root

2. **Import Database**
```bash
mysql -u root -p sata_messenger < database_schema.sql
```

3. **Configure** `config/config.php`
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sata_messenger');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('APP_URL', 'https://yourdomain.com');
```

4. **Set Permissions**
```bash
chmod 755 uploads/
chmod 644 config/config.php
```

5. **Access** the application
- Visit: `http://yourdomain.com`
- Default Admin: 
  - Email: `root@sata.local`
  - Password: `m13841215`

## 📊 Database Schema

The schema includes:
- `users` - User accounts with verification tiers
- `otp_codes` - One-time passwords
- `user_sessions` - Active sessions
- `chats` - Groups and channels
- `chat_members` - Membership mapping
- `messages` - All message types
- `message_status` - Read receipts
- `stories` - 24-hour stories
- `system_logs` - Audit trail
- `reports` - User reports

## 🛡 Security Best Practices Implemented

1. **Headers**: X-Frame-Options, CSP, HSTS
2. **Sessions**: Regenerate ID on login, secure cookies
3. **Files**: MIME type validation, no direct execution
4. **APIs**: Token-based authentication required
5. **Logs**: All sensitive actions logged
6. **Memory**: Clear sensitive data after use

## 🎨 UI/UX Highlights

- **Glassmorphism** design (Item 35, 102)
- **Dark theme** optimized (#0B141A base)
- **Skeleton loaders** for smooth UX (Item 105)
- **Responsive** layout (mobile-first)
- **PWA-ready** with manifest.json
- **Custom scrollbars** matching theme
- **Haptic-style** animations

## 📈 Performance Optimizations

1. **Gzip compression** enabled
2. **Image compression** on upload
3. **Lazy loading** for messages
4. **AJAX polling** instead of WebSockets (shared hosting compatible)
5. **Query indexing** on frequently accessed columns
6. **Cache headers** for static assets

## 🔧 Development Mode

Enable debug mode in `config/config.php`:
```php
define('DEBUG_MODE', true); // Shows errors, logs OTP codes
```

**⚠️ Important**: Set to `false` in production!

## 📝 API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/send-otp` | POST | Send OTP code |
| `/api/verify-otp` | POST | Verify OTP |
| `/api/login` | POST | User login |
| `/api/register` | POST | User registration |
| `/api/get-chats` | GET | Fetch chat list |
| `/api/send-message` | POST | Send message |
| `/api/upload-media` | POST | Upload file |

## 🎯 Roadmap (Future Enhancements)

- [ ] Voice/Video calls (WebRTC)
- [ ] End-to-end encryption
- [ ] Desktop app (Electron)
- [ ] Mobile apps (React Native)
- [ ] Bot API
- [ ] Payment integration

## 📄 License

This project is proprietary software. All rights reserved.

## 👥 Credits

- **Lead Architect**: Master Full-Stack Architect & Cyber-Security Lead
- **Specification**: 440-point atomic feature list
- **Version**: 1.0.0 (2026 Gen)

---

**Built with ❤️ for the future of secure messaging**
