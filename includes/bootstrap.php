<?php

/**
 * Application Bootstrap
 * 
 * Central bootstrap file untuk inisialisasi aplikasi
 * Memastikan semua file dimuat dalam urutan yang benar
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Prevent multiple inclusion
if (defined('BOOTSTRAP_LOADED')) {
    return;
}
define('BOOTSTRAP_LOADED', true);

// Set error reporting based on environment
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, ' "\'');
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

$isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
if ($isDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
}

// Set timezone
$timezone = $_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta';
date_default_timezone_set($timezone);

// Load core functions first
if (!function_exists('sanitizeInput')) {
    require_once APP_ROOT . '/includes/functions.php';
}

// Load path helper
require_once APP_ROOT . '/includes/path-helper.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    // Load session configuration
    if (file_exists(APP_ROOT . '/config/app.php')) {
        $config = include APP_ROOT . '/config/app.php';
        $sessionConfig = $config['session'] ?? [];
    } else {
        $sessionConfig = [
            'lifetime' => 3600,
            'cookie' => [
                'name' => 'whatsapp_monitor_session',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'strict'
            ]
        ];
    }

    // Configure session settings
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', $sessionConfig['cookie']['samesite'] ?? 'strict');

    // Set session name
    session_name($sessionConfig['cookie']['name'] ?? 'whatsapp_monitor_session');

    // Configure session cookie parameters
    session_set_cookie_params([
        'lifetime' => $sessionConfig['lifetime'] ?? 3600,
        'path' => $sessionConfig['cookie']['path'] ?? '/',
        'domain' => $sessionConfig['cookie']['domain'] ?? '',
        'secure' => $sessionConfig['cookie']['secure'] ?? false,
        'httponly' => $sessionConfig['cookie']['httponly'] ?? true,
        'samesite' => $sessionConfig['cookie']['samesite'] ?? 'strict'
    ]);

    // Start session
    session_start();
}

// Initialize session security if not already done
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
    $_SESSION['created_at'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['ip_address'] = getClientIp();
}

/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Get current user
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser()
    {
        if (!isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'role' => $_SESSION['role'] ?? 'viewer'
        ];
    }
}

/**
 * Require authentication
 */
if (!function_exists('requireAuth')) {
    function requireAuth()
    {
        if (!isLoggedIn()) {
            // Determine redirect path based on current location
            $currentPath = $_SERVER['REQUEST_URI'];

            // If we're in pages/auth/, redirect to ../auth/login.php
            if (strpos($currentPath, '/pages/auth/') !== false) {
                header('Location: login.php');
            }
            // If we're in any other pages/, redirect to ../auth/login.php
            elseif (strpos($currentPath, '/pages/') !== false) {
                header('Location: ../auth/login.php');
            }
            // If we're at root, redirect to pages/auth/login.php
            else {
                header('Location: pages/auth/login.php');
            }
            exit;
        }
    }
}

// Auto-load classes if needed
spl_autoload_register(function ($className) {
    $file = APP_ROOT . '/classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Create necessary directories
$directories = [
    APP_ROOT . '/logs',
    APP_ROOT . '/storage',
    APP_ROOT . '/assets/uploads',
    APP_ROOT . '/storage/sessions'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
