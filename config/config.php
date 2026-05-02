<?php
/**
 * SATA Messenger - Database Configuration
 * Pure PHP 8.x - No Framework
 * Optimized for Shared Hosting
 */

// Prevent direct access
if (!defined('SATA_MESSENGER')) {
    http_response_code(403);
    exit('Access Denied');
}

// Database Constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'sata_messenger');
define('DB_USER', 'root'); // Change in production
define('DB_PASS', '');     // Change in production
define('DB_CHARSET', 'utf8mb4');

// Application Constants
define('APP_NAME', 'SATA Messenger');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/sata-messenger'); // Change to your domain

// Security Constants
define('SESSION_LIFETIME', 86400); // 24 hours
define('OTP_EXPIRY', 120);         // 2 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);       // 15 minutes

// Upload Constants
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 52428800); // 50MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm']);
define('ALLOWED_AUDIO_TYPES', ['audio/mpeg', 'audio/ogg', 'audio/wav']);

// Feature Flags
define('ENABLE_REGISTRATION', true);
define('MAINTENANCE_MODE', false);
define('DEBUG_MODE', true); // Set to false in production

// Sata Bot Configuration
define('SATA_BOT_ID', 1);
define('SATA_BOT_UNIQUE_ID', 'sata');
define('SATA_BOT_PHONE', '09121111111');

// Verification Ticks
define('TICK_NONE', 'none');
define('TICK_BLACK', 'black');
define('TICK_BLUE', 'blue');
define('TICK_GOLD', 'gold');

// Message Types
define('MSG_TEXT', 'text');
define('MSG_IMAGE', 'image');
define('MSG_VIDEO', 'video');
define('MSG_AUDIO', 'audio');
define('MSG_FILE', 'file');
define('MSG_STICKER', 'sticker');

// Chat Types
define('CHAT_PRIVATE', 'private');
define('CHAT_GROUP', 'group');
define('CHAT_CHANNEL', 'channel');

// Error Reporting (Disable in production)
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Asia/Tehran');

// Start Session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set Security Headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
