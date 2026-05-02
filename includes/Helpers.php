<?php
/**
 * SATA Messenger - Helper Functions
 * Implements core utility functions from the specification
 * Items: 6, 251, 252, 274, 351, 360
 */

// Prevent direct access
if (!defined('SATA_MESSENGER')) {
    http_response_code(403);
    exit('Access Denied');
}

/**
 * Item 251: Sanitize Input Data
 * Prevents XSS attacks (Item 6)
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    // Remove HTML tags and encode special characters
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

/**
 * Item 252: Generate Secure OTP
 * Creates unpredictable 5-digit code
 */
function generateOTP() {
    // Use cryptographically secure random number generator
    return str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * Validate Email Format (Item 360)
 */
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Check for disposable email domains (basic check)
    $disposable_domains = ['tempmail.com', 'throwaway.com', 'guerrillamail.com'];
    $domain = explode('@', $email)[1];
    
    if (in_array($domain, $disposable_domains)) {
        return false;
    }
    
    // Check MX records (Item 361)
    if (!checkdnsrr($domain, 'MX')) {
        return false;
    }
    
    return true;
}

/**
 * Validate Unique ID (@id) Format (Item 362)
 * Minimum 5 characters, no profanity
 */
function validateUniqueId($unique_id) {
    // Length check (Item 362)
    if (strlen($unique_id) < 5 || strlen($unique_id) > 20) {
        return false;
    }
    
    // Only allow alphanumeric and underscores
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $unique_id)) {
        return false;
    }
    
    // Reserved IDs (Item 33)
    $reserved_ids = ['admin', 'sata', 'support', 'system', 'bot', 'help', 'info'];
    if (in_array(strtolower($unique_id), $reserved_ids)) {
        return false;
    }
    
    // Profanity filter (Item 363)
    $bad_words = ['fuck', 'shit', 'ass', 'bitch', 'damn'];
    foreach ($bad_words as $word) {
        if (stripos($unique_id, $word) !== false) {
            return false;
        }
    }
    
    return true;
}

/**
 * Convert Timestamp to Jalali (Shamsi) Date (Item 274)
 */
function toJalaliDate($timestamp) {
    // Simple conversion (for production, use a proper library)
    $datetime = new DateTime("@$timestamp");
    $gregorian = [
        'year' => (int)$datetime->format('Y'),
        'month' => (int)$datetime->format('n'),
        'day' => (int)$datetime->format('j')
    ];
    
    // Algorithm to convert Gregorian to Jalali
    $gy = $gregorian['year'] - 1600;
    $gm = $gregorian['month'] - 1;
    $gd = $gregorian['day'] - 1;
    
    $g_day_no = 365 * $gy + intval(($gy + 3) / 4) - intval(($gy + 99) / 100) + intval(($gy + 399) / 400);
    
    for ($i = 0; $i < $gm; ++$i) {
        $g_day_no += cal_days_in_month(CAL_GREGORIAN, $i + 1, $gregorian['year']);
    }
    
    $g_day_no += $gd;
    $j_day_no = $g_day_no - 79;
    
    $j_np = intval($j_day_no / 12053);
    $j_day_no %= 12053;
    
    $jy = 979 + 33 * $j_np + 4 * intval($j_day_no / 1461);
    $j_day_no %= 1461;
    
    if ($j_day_no >= 366) {
        $jy += intval(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }
    
    for ($i = 0; $i < 11 && $j_day_no >= ($i < 6 ? 31 : 30); ++$i) {
        $j_day_no -= ($i < 6 ? 31 : 30);
    }
    
    $jm = $i + 1;
    $jd = $j_day_no + 1;
    
    return sprintf('%d/%02d/%02d', $jy, $jm, $jd);
}

/**
 * Format Time as AM/PM (Item 351)
 */
function formatTimeAMPM($timestamp) {
    return date('g:i A', $timestamp);
}

/**
 * Format Chat Date Divider (Item 275)
 */
function formatDateDivider($timestamp) {
    $now = time();
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');
    
    if ($timestamp >= $today) {
        return 'امروز';
    } elseif ($timestamp >= $yesterday) {
        return 'دیروز';
    } else {
        return toJalaliDate($timestamp);
    }
}

/**
 * Generate CSRF Token (Item 8)
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token (Item 8)
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenerate Session ID securely (Item 331)
 */
function regenerateSession() {
    session_regenerate_id(true);
}

/**
 * Get User IP Address
 */
function getUserIP() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Parse User Agent for Device Type (Item 36)
 */
function parseUserAgent($user_agent) {
    $device_type = 'Desktop';
    
    if (preg_match('/(mobile|android|iphone|ipad)/i', $user_agent)) {
        $device_type = 'Mobile';
    } elseif (preg_match('/tablet|ipad/i', $user_agent)) {
        $device_type = 'Tablet';
    }
    
    $browser = 'Unknown';
    if (preg_match('/Chrome\/([0-9.]+)/i', $user_agent, $matches)) {
        $browser = 'Chrome ' . $matches[1];
    } elseif (preg_match('/Firefox\/([0-9.]+)/i', $user_agent, $matches)) {
        $browser = 'Firefox ' . $matches[1];
    } elseif (preg_match('/Safari\/([0-9.]+)/i', $user_agent, $matches)) {
        $browser = 'Safari ' . $matches[1];
    }
    
    return [
        'device_type' => $device_type,
        'browser' => $browser,
        'user_agent' => substr($user_agent, 0, 255)
    ];
}

/**
 * Human Readable File Size (Item 327)
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Create JSON Response
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Redirect with Message
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
    header("Location: $url");
    exit;
}

/**
 * Log System Activity (Item 247)
 */
function logSystemActivity($action_type, $user_id = null, $details = null) {
    $db = Database::getInstance();
    $ip = getUserIP();
    
    $sql = "INSERT INTO system_logs (action_type, user_id, details, ip_address) VALUES (?, ?, ?, ?)";
    $db->query($sql, [$action_type, $user_id, $details, $ip]);
}

/**
 * Check if User is Banned (Item 286)
 */
function checkIfBanned($user_id) {
    $db = Database::getInstance();
    $sql = "SELECT is_banned, ban_reason FROM users WHERE id = ?";
    $user = $db->fetchOne($sql, [$user_id]);
    
    if ($user && $user['is_banned']) {
        return [
            'banned' => true,
            'reason' => $user['ban_reason']
        ];
    }
    
    return ['banned' => false, 'reason' => null];
}

/**
 * Clear sensitive data from memory (Item 399)
 */
function clearSensitiveData(&$variable) {
    if (is_string($variable)) {
        $variable = str_repeat('*', strlen($variable));
    }
    unset($variable);
}
