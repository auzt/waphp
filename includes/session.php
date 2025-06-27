<?php

/**
 * Session Management
 * 
 * Handles user sessions, authentication, and security
 * Includes session security, CSRF protection, and user management
 * 
 * @author WhatsApp Monitor Team
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load required files
require_once APP_ROOT . '/config/constants.php';
require_once APP_ROOT . '/includes/functions.php';

// =============================================================================
// SESSION CONFIGURATION
// =============================================================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Load app configuration
    $appConfig = require APP_ROOT . '/config/app.php';

    // Configure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $appConfig['session']['cookie']['secure'] ?? false);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', $appConfig['session']['cookie']['samesite'] ?? 'Strict');
    ini_set('session.gc_maxlifetime', $appConfig['session']['lifetime'] ?? SESSION_TIMEOUT);

    // Set session name
    session_name($appConfig['session']['cookie']['name'] ?? 'whatsapp_monitor_session');

    // Start session
    session_start();

    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// =============================================================================
// SESSION MANAGEMENT FUNCTIONS
// =============================================================================

/**
 * Check if user is logged in
 * 
 * @return bool
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) &&
        isset($_SESSION['username']) &&
        isset($_SESSION['session_token']) &&
        !isSessionExpired();
}

/**
 * Check if session is expired
 * 
 * @return bool
 */
function isSessionExpired()
{
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }

    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
    return (time() - $_SESSION['last_activity']) > $timeout;
}

/**
 * Login user
 * 
 * @param array $user User data from database
 * @param bool $rememberMe Whether to remember user
 * @return bool
 */
function loginUser($user, $rememberMe = false)
{
    // Regenerate session ID for security
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['session_token'] = generateSessionToken();
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['ip_address'] = getClientIpAddress();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['csrf_token'] = generateCsrfToken();

    // Set remember me cookie if requested
    if ($rememberMe) {
        $rememberToken = generateRememberToken();
        $_SESSION['remember_token'] = $rememberToken;

        $expiry = time() + REMEMBER_ME_TIMEOUT;
        setcookie('remember_token', $rememberToken, $expiry, '/', '', false, true);

        // Store remember token in database
        saveRememberToken($user['id'], $rememberToken, $expiry);
    }

    // Update last login in database
    updateLastLogin($user['id']);

    // Log login activity
    logActivity('user_login', "User {$user['username']} logged in", $user['id']);

    return true;
}

/**
 * Logout user
 * 
 * @return void
 */
function logoutUser()
{
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'unknown';

    // Clear remember me cookie and database entry
    if (isset($_SESSION['remember_token'])) {
        deleteRememberToken($_SESSION['remember_token']);
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }

    // Log logout activity
    if ($userId) {
        logActivity('user_logout', "User {$username} logged out", $userId);
    }

    // Clear all session data
    $_SESSION = [];

    // Destroy session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destroy session
    session_destroy();
}

/**
 * Update session activity
 * 
 * @return void
 */
function updateSessionActivity()
{
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();

        // Update session in database periodically
        if (
            !isset($_SESSION['last_db_update']) ||
            (time() - $_SESSION['last_db_update']) > 300
        ) { // 5 minutes
            updateSessionInDatabase();
            $_SESSION['last_db_update'] = time();
        }
    }
}

/**
 * Require authentication
 * 
 * @param string $redirectUrl URL to redirect if not authenticated
 * @return void
 */
function requireAuth($redirectUrl = '/pages/auth/login.php')
{
    if (!isLoggedIn()) {
        // Store intended URL for redirect after login
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';

        if (isAjaxRequest()) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Authentication required',
                'redirect' => $redirectUrl
            ], 401);
        } else {
            header("Location: $redirectUrl");
            exit;
        }
    }

    // Update activity
    updateSessionActivity();
}

/**
 * Require specific permission
 * 
 * @param string $permission
 * @param string $redirectUrl
 * @return void
 */
function requirePermission($permission, $redirectUrl = '/pages/dashboard/')
{
    requireAuth();

    if (!hasPermission($permission, $_SESSION['role'])) {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Insufficient permissions'
            ], 403);
        } else {
            redirectWithMessage($redirectUrl, 'Anda tidak memiliki akses untuk halaman ini', 'error');
        }
    }
}

/**
 * Get current user data
 * 
 * @return array|null
 */
function getCurrentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role'],
        'login_time' => $_SESSION['login_time'],
        'last_activity' => $_SESSION['last_activity']
    ];
}

/**
 * Get current user ID
 * 
 * @return int|null
 */
function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * 
 * @return string|null
 */
function getCurrentUserRole()
{
    return $_SESSION['role'] ?? null;
}

/**
 * Check if current user has role
 * 
 * @param string $role
 * @return bool
 */
function hasRole($role)
{
    return getCurrentUserRole() === $role;
}

/**
 * Check if current user is admin
 * 
 * @return bool
 */
function isAdmin()
{
    return hasRole(ROLE_ADMIN);
}

// =============================================================================
// CSRF PROTECTION
// =============================================================================

/**
 * Generate CSRF token
 * 
 * @return string
 */
function generateCsrfToken()
{
    return bin2hex(random_bytes(32));
}

/**
 * Get CSRF token
 * 
 * @return string
 */
function getCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateCsrfToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token
 * @return bool
 */
function validateCsrfToken($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token
 * 
 * @return void
 */
function requireCsrfToken()
{
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    if (!validateCsrfToken($token)) {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Invalid CSRF token'
            ], 403);
        } else {
            redirectWithMessage('/pages/dashboard/', 'Invalid request. Please try again.', 'error');
        }
    }
}

/**
 * Generate CSRF hidden input field
 * 
 * @return string
 */
function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken()) . '">';
}

// =============================================================================
// SESSION TOKEN MANAGEMENT
// =============================================================================

/**
 * Generate session token
 * 
 * @return string
 */
function generateSessionToken()
{
    return bin2hex(random_bytes(32));
}

/**
 * Generate remember token
 * 
 * @return string
 */
function generateRememberToken()
{
    return bin2hex(random_bytes(32));
}

/**
 * Save remember token to database
 * 
 * @param int $userId
 * @param string $token
 * @param int $expiry
 * @return bool
 */
function saveRememberToken($userId, $token, $expiry)
{
    try {
        $db = Database::getInstance();

        // Delete existing remember tokens for this user
        $db->delete("DELETE FROM user_sessions WHERE user_id = ? AND session_id LIKE 'remember_%'", [$userId]);

        // Insert new remember token
        $sessionId = 'remember_' . $token;
        $db->insert("
            INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))
        ", [
            $userId,
            $sessionId,
            getClientIpAddress(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiry
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Failed to save remember token: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate remember token
 * 
 * @param string $token
 * @return array|null User data if valid, null if invalid
 */
function validateRememberToken($token)
{
    try {
        $db = Database::getInstance();

        $session = $db->selectOne("
            SELECT us.*, u.* FROM user_sessions us
            JOIN users u ON us.user_id = u.id
            WHERE us.session_id = ? AND us.expires_at > NOW() AND u.status = 'active'
        ", ['remember_' . $token]);

        if ($session) {
            // Update last activity
            $db->update("UPDATE user_sessions SET last_activity = NOW() WHERE id = ?", [$session['id']]);
            return $session;
        }

        return null;
    } catch (Exception $e) {
        error_log("Failed to validate remember token: " . $e->getMessage());
        return null;
    }
}

/**
 * Delete remember token
 * 
 * @param string $token
 * @return bool
 */
function deleteRememberToken($token)
{
    try {
        $db = Database::getInstance();
        $db->delete("DELETE FROM user_sessions WHERE session_id = ?", ['remember_' . $token]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to delete remember token: " . $e->getMessage());
        return false;
    }
}

// =============================================================================
// SESSION DATABASE OPERATIONS
// =============================================================================

/**
 * Update last login time
 * 
 * @param int $userId
 * @return bool
 */
function updateLastLogin($userId)
{
    try {
        $db = Database::getInstance();
        $db->update("UPDATE users SET last_login = NOW() WHERE id = ?", [$userId]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
        return false;
    }
}

/**
 * Update session in database
 * 
 * @return bool
 */
function updateSessionInDatabase()
{
    if (!isLoggedIn()) {
        return false;
    }

    try {
        $db = Database::getInstance();

        $sessionId = session_id();
        $userId = $_SESSION['user_id'];
        $ipAddress = getClientIpAddress();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check if session exists
        $existing = $db->selectOne("SELECT id FROM user_sessions WHERE session_id = ?", [$sessionId]);

        if ($existing) {
            // Update existing session
            $db->update("
                UPDATE user_sessions 
                SET last_activity = NOW(), ip_address = ?, user_agent = ?
                WHERE session_id = ?
            ", [$ipAddress, $userAgent, $sessionId]);
        } else {
            // Insert new session
            $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
            $db->insert("
                INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?)
            ", [$userId, $sessionId, $ipAddress, $userAgent, $expiresAt]);
        }

        return true;
    } catch (Exception $e) {
        error_log("Failed to update session in database: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean expired sessions
 * 
 * @return int Number of cleaned sessions
 */
function cleanExpiredSessions()
{
    try {
        $db = Database::getInstance();
        return $db->delete("DELETE FROM user_sessions WHERE expires_at < NOW()");
    } catch (Exception $e) {
        error_log("Failed to clean expired sessions: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get active sessions for user
 * 
 * @param int $userId
 * @return array
 */
function getActiveSessions($userId)
{
    try {
        $db = Database::getInstance();
        return $db->select("
            SELECT session_id, ip_address, user_agent, created_at, last_activity, expires_at
            FROM user_sessions 
            WHERE user_id = ? AND expires_at > NOW()
            ORDER BY last_activity DESC
        ", [$userId]);
    } catch (Exception $e) {
        error_log("Failed to get active sessions: " . $e->getMessage());
        return [];
    }
}

/**
 * Terminate session
 * 
 * @param string $sessionId
 * @param int $userId
 * @return bool
 */
function terminateSession($sessionId, $userId)
{
    try {
        $db = Database::getInstance();
        $affected = $db->delete("DELETE FROM user_sessions WHERE session_id = ? AND user_id = ?", [$sessionId, $userId]);

        // If current session is terminated, logout
        if ($sessionId === session_id()) {
            logoutUser();
        }

        return $affected > 0;
    } catch (Exception $e) {
        error_log("Failed to terminate session: " . $e->getMessage());
        return false;
    }
}

/**
 * Terminate all sessions except current
 * 
 * @param int $userId
 * @return int Number of terminated sessions
 */
function terminateAllOtherSessions($userId)
{
    try {
        $db = Database::getInstance();
        $currentSessionId = session_id();

        return $db->delete("
            DELETE FROM user_sessions 
            WHERE user_id = ? AND session_id != ?
        ", [$userId, $currentSessionId]);
    } catch (Exception $e) {
        error_log("Failed to terminate other sessions: " . $e->getMessage());
        return 0;
    }
}

// =============================================================================
// SECURITY FUNCTIONS
// =============================================================================

/**
 * Check for suspicious activity
 * 
 * @return bool
 */
function checkSuspiciousActivity()
{
    if (!isLoggedIn()) {
        return false;
    }

    $currentIp = getClientIpAddress();
    $sessionIp = $_SESSION['ip_address'] ?? '';

    // Check IP change
    if (!empty($sessionIp) && $sessionIp !== $currentIp) {
        logActivity('suspicious_ip_change', "IP changed from {$sessionIp} to {$currentIp}", getCurrentUserId());

        // Optionally force re-authentication
        if (defined('FORCE_REAUTH_ON_IP_CHANGE') && FORCE_REAUTH_ON_IP_CHANGE) {
            logoutUser();
            return true;
        }

        // Update IP address
        $_SESSION['ip_address'] = $currentIp;
    }

    return false;
}

/**
 * Rate limit login attempts
 * 
 * @param string $identifier IP address or username
 * @param int $maxAttempts
 * @param int $windowSeconds
 * @return bool True if rate limited
 */
function isRateLimited($identifier, $maxAttempts = 5, $windowSeconds = 900)
{
    $key = 'login_attempts_' . md5($identifier);

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        return false;
    }

    $attempts = $_SESSION[$key];

    // Reset if window expired
    if (time() - $attempts['first_attempt'] > $windowSeconds) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        return false;
    }

    return $attempts['count'] >= $maxAttempts;
}

/**
 * Record login attempt
 * 
 * @param string $identifier
 * @param bool $success
 * @return void
 */
function recordLoginAttempt($identifier, $success = false)
{
    $key = 'login_attempts_' . md5($identifier);

    if ($success) {
        // Clear attempts on successful login
        unset($_SESSION[$key]);
    } else {
        // Increment failed attempts
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        $_SESSION[$key]['count']++;
    }
}

/**
 * Generate secure password hash
 * 
 * @param string $password
 * @return string
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3,
    ]);
}

/**
 * Verify password
 * 
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Check if password needs rehashing
 * 
 * @param string $hash
 * @return bool
 */
function passwordNeedsRehash($hash)
{
    return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3,
    ]);
}

// =============================================================================
// AUTO-LOGIN FUNCTIONALITY
// =============================================================================

/**
 * Check for remember me cookie and auto-login
 * 
 * @return bool True if auto-logged in
 */
function checkRememberMe()
{
    if (isLoggedIn() || !isset($_COOKIE['remember_token'])) {
        return false;
    }

    $token = $_COOKIE['remember_token'];
    $user = validateRememberToken($token);

    if ($user && $user['status'] === 'active') {
        // Auto login user
        loginUser($user, true);

        logActivity('auto_login', "User {$user['username']} auto-logged in via remember token", $user['id']);
        return true;
    } else {
        // Invalid token, clear cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        if ($token) {
            deleteRememberToken($token);
        }
    }

    return false;
}

// =============================================================================
// SESSION INITIALIZATION
// =============================================================================

// Auto-check remember me if not logged in
if (!isLoggedIn()) {
    checkRememberMe();
}

// Check for suspicious activity
if (isLoggedIn()) {
    checkSuspiciousActivity();
}

// Clean expired sessions periodically (1% chance)
if (rand(1, 100) === 1) {
    cleanExpiredSessions();
}
