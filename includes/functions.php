<?php

/**
 * Global Helper Functions
 * 
 * Collection of utility functions used throughout the application
 * Security, validation, formatting, and session management
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// =============================================================================
// SECURITY FUNCTIONS
// =============================================================================

/**
 * Generate CSRF token
 */
function generateCsrfToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token
 */
function getCsrfToken()
{
    return generateCsrfToken();
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }

    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate secure random string
 */
function generateRandomString($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hash password securely
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_ARGON2ID);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

// =============================================================================
// VALIDATION FUNCTIONS
// =============================================================================

/**
 * Validate email address
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate username
 */
function isValidUsername($username)
{
    return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username);
}

/**
 * Validate phone number
 */
function isValidPhoneNumber($phone)
{
    $cleaned = preg_replace('/[^0-9+]/', '', $phone);
    return preg_match('/^(\+62|62|0)[0-9]{8,13}$/', $cleaned);
}

/**
 * Validate required fields
 */
function validateRequired($data, $required)
{
    $missing = [];

    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }

    return [
        'valid' => empty($missing),
        'missing' => $missing
    ];
}

/**
 * Validate password strength
 */
function validatePassword($password)
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password harus memiliki huruf besar';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password harus memiliki huruf kecil';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password harus memiliki angka';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

// =============================================================================
// SESSION MANAGEMENT
// =============================================================================

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user
 */
function getCurrentUser()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

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

/**
 * Get current user role
 */
function getCurrentUserRole()
{
    $user = getCurrentUser();
    return $user ? $user['role'] : null;
}

/**
 * Check if user has specific role
 */
function hasRole($role)
{
    $currentRole = getCurrentUserRole();

    $roleHierarchy = [
        'viewer' => 1,
        'operator' => 2,
        'admin' => 3
    ];

    return isset($roleHierarchy[$currentRole]) &&
        isset($roleHierarchy[$role]) &&
        $roleHierarchy[$currentRole] >= $roleHierarchy[$role];
}

/**
 * Require authentication
 */
function requireAuth()
{
    if (!isLoggedIn()) {
        header('Location: /pages/auth/login.php');
        exit;
    }
}

/**
 * Require specific role
 */
function requireRole($role)
{
    requireAuth();

    if (!hasRole($role)) {
        header('Location: /pages/dashboard/index.php?error=access_denied');
        exit;
    }
}

// =============================================================================
// FLASH MESSAGES
// =============================================================================

/**
 * Set flash message
 */
function setFlashMessage($message, $type = 'info')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type,
        'timestamp' => time()
    ];
}

/**
 * Get and clear flash message
 */
function getFlashMessage()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }

    return null;
}

// =============================================================================
// FORMATTING FUNCTIONS
// =============================================================================

/**
 * Format phone number
 */
function formatPhoneNumber($phone)
{
    if (empty($phone)) return $phone;

    // Remove non-numeric characters
    $cleaned = preg_replace('/[^0-9]/', '', $phone);

    // Add country code if starting with 0
    if (substr($cleaned, 0, 1) === '0') {
        $cleaned = '62' . substr($cleaned, 1);
    }

    // Add + prefix
    if (substr($cleaned, 0, 1) !== '+') {
        $cleaned = '+' . $cleaned;
    }

    return $cleaned;
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Format time ago
 */
function timeAgo($datetime)
{
    if (empty($datetime)) return '-';

    $time = is_string($datetime) ? strtotime($datetime) : $datetime;
    $diff = time() - $time;

    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';

    return date('d M Y', $time);
}

/**
 * Format date in Indonesian
 */
function formatDateIndonesian($date, $format = 'd F Y H:i')
{
    if (empty($date)) return '-';

    $months = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];

    $timestamp = is_string($date) ? strtotime($date) : $date;
    $formatted = date($format, $timestamp);

    return str_replace(array_keys($months), array_values($months), $formatted);
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Generate unique ID
 */
function generateUniqueId($prefix = '')
{
    return $prefix . uniqid() . mt_rand(1000, 9999);
}

/**
 * Redirect to URL
 */
function redirect($url, $statusCode = 302)
{
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get client IP address
 */
function getClientIp()
{
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

    foreach ($keys as $key) {
        if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            return trim($ips[0]);
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get user agent
 */
function getUserAgent()
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Log activity
 */
function logActivity($message, $level = 'info', $context = [])
{
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'ip' => getClientIp(),
        'user_agent' => getUserAgent()
    ];

    if (isLoggedIn()) {
        $user = getCurrentUser();
        $log['user_id'] = $user['id'];
        $log['username'] = $user['username'];
    }

    $logFile = APP_ROOT . '/logs/activity.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }

    file_put_contents($logFile, json_encode($log) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Convert array to JSON with proper encoding
 */
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Get system performance info
 */
function getSystemPerformance()
{
    return [
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'memory_limit' => ini_get('memory_limit'),
        'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
        'disk_space' => disk_free_space('.'),
        'disk_total' => disk_total_space('.')
    ];
}

/**
 * Create directory if not exists
 */
function ensureDirectoryExists($path)
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

/**
 * Clean old files
 */
function cleanOldFiles($directory, $maxAge = 86400)
{
    if (!is_dir($directory)) return;

    $files = glob($directory . '/*');
    $now = time();

    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
            unlink($file);
        }
    }
}
