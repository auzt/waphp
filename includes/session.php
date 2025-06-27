<?php

/**
 * Session Management
 * 
 * Handles secure session management with security features
 * - Session security configuration
 * - Session regeneration
 * - Session cleanup
 * - Cookie security
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load configuration
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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Initialize session security
 */
function initializeSession()
{
    // Regenerate session ID to prevent fixation attacks
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['created_at'] = time();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['ip_address'] = getClientIp();
    }

    // Check session expiry
    checkSessionExpiry();

    // Validate session security
    validateSessionSecurity();

    // Regenerate session ID periodically (every 30 minutes)
    if (
        isset($_SESSION['last_regeneration']) &&
        (time() - $_SESSION['last_regeneration']) > 1800
    ) {
        regenerateSession();
    }
}

/**
 * Check session expiry
 */
function checkSessionExpiry()
{
    global $sessionConfig;

    $lifetime = $sessionConfig['lifetime'] ?? 3600;

    if (
        isset($_SESSION['created_at']) &&
        (time() - $_SESSION['created_at']) > $lifetime
    ) {
        destroySession();
        return false;
    }

    // Update last activity
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Validate session security
 */
function validateSessionSecurity()
{
    // Check user agent consistency
    if (
        isset($_SESSION['user_agent']) &&
        $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')
    ) {
        destroySession();
        header('Location: /pages/auth/login.php?error=security_violation');
        exit;
    }

    // Check IP address consistency (optional, can be disabled for users with dynamic IPs)
    $checkIp = $_ENV['SESSION_CHECK_IP'] ?? false;
    if (
        $checkIp && isset($_SESSION['ip_address']) &&
        $_SESSION['ip_address'] !== getClientIp()
    ) {
        destroySession();
        header('Location: /pages/auth/login.php?error=security_violation');
        exit;
    }
}

/**
 * Regenerate session ID
 */
function regenerateSession()
{
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

/**
 * Login user and create secure session
 */
function loginUser($user)
{
    // Regenerate session ID
    session_regenerate_id(true);

    // Set session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_regeneration'] = time();

    // Update last login in database
    updateLastLogin($user['id']);

    return true;
}

/**
 * Logout user and destroy session
 */
function logoutUser()
{
    // Log activity
    if (function_exists('logActivity')) {
        logActivity('User logged out', 'info');
    }

    // Destroy session
    destroySession();

    // Redirect to login
    header('Location: /pages/auth/login.php?message=logged_out');
    exit;
}

/**
 * Destroy session completely
 */
function destroySession()
{
    // Clear session data
    $_SESSION = [];

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy session
    session_destroy();
}

/**
 * Update last login timestamp
 */
function updateLastLogin($userId)
{
    try {
        require_once APP_ROOT . '/classes/Database.php';
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

/**
 * Get client IP address for session validation
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
 * Check if session is valid
 */
function isValidSession()
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }

    return checkSessionExpiry() && validateSessionSecurity();
}

/**
 * Get session info for debugging
 */
function getSessionInfo()
{
    return [
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'created_at' => $_SESSION['created_at'] ?? null,
        'ip_address' => $_SESSION['ip_address'] ?? null,
        'user_agent' => substr($_SESSION['user_agent'] ?? '', 0, 100) . '...'
    ];
}

/**
 * Clean expired sessions from database
 */
function cleanExpiredSessions()
{
    try {
        require_once APP_ROOT . '/classes/Database.php';
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();

        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Failed to clean expired sessions: " . $e->getMessage());
        return 0;
    }
}

// Initialize session when this file is included
initializeSession();
