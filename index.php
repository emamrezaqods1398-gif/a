<?php
/**
 * SATA Messenger - Main Entry Point (Router)
 * Implements Items: 1, 10, 11, 277, 332
 * Pure PHP 8.x - No Framework
 */

// Define application constant
define('SATA_MESSENGER', true);

// Load configuration
require_once __DIR__ . '/config/config.php';

// Load core classes
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Helpers.php';
require_once __DIR__ . '/includes/UserAuth.php';

// Check maintenance mode (Item 348-349)
if (MAINTENANCE_MODE && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
    http_response_code(503);
    exit('<h1>در حال بروزرسانی سرورها</h1><p>لطفاً چند دقیقه دیگر تلاش کنید.</p>');
}

// Simple Router (Item 11)
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
$request_uri = str_replace($base_path, '', $request_uri);

// Remove trailing slash
$request_uri = rtrim($request_uri, '/');

// Route mapping
$routes = [
    '' => 'pages/home.php',
    'login' => 'pages/auth/login.php',
    'register' => 'pages/auth/register.php',
    'logout' => 'pages/auth/logout.php',
    'chat' => 'pages/chat/index.php',
    'settings' => 'pages/settings/index.php',
    'admin' => 'pages/admin/dashboard.php',
    
    // API endpoints
    'api/send-otp' => 'api/auth/send_otp.php',
    'api/verify-otp' => 'api/auth/verify_otp.php',
    'api/login' => 'api/auth/login.php',
    'api/register' => 'api/auth/register.php',
    'api/get-chats' => 'api/chat/get_chats.php',
    'api/send-message' => 'api/chat/send_message.php',
    'api/upload-media' => 'api/media/upload.php',
];

// Find matching route
$controller = null;
foreach ($routes as $route => $file) {
    if ($request_uri === '/' . $route || $request_uri === $route) {
        $controller = $file;
        break;
    }
}

// Default to home if no match
if (!$controller) {
    // Check if it's a static file
    $static_file = __DIR__ . $request_uri;
    if (file_exists($static_file) && is_file($static_file)) {
        // Serve static file
        return false;
    }
    
    // Show 404 page (Item 277)
    http_response_code(404);
    $controller = 'pages/errors/404.php';
}

// Include controller
if (file_exists(__DIR__ . '/' . $controller)) {
    require_once __DIR__ . '/' . $controller;
} else {
    http_response_code(500);
    echo "Error: Controller not found";
}
