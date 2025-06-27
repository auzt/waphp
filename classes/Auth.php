<?php

/**
 * Authentication Class
 * 
 * Handles user authentication, authorization, and security features
 * - Login/logout functionality
 * - Role-based access control
 * - Password validation
 * - Security measures (rate limiting, brute force protection)
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../includes/functions.php';

class Auth
{
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();

        // Load settings from config
        $this->maxLoginAttempts = $_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5;
        $this->lockoutDuration = $_ENV['LOCKOUT_DURATION'] ?? 900;
    }

    /**
     * Authenticate user login
     */
    public function login($username, $password, $remember = false)
    {
        try {
            // Check if account is locked
            if ($this->isAccountLocked($username)) {
                return [
                    'success' => false,
                    'message' => 'Akun terkunci karena terlalu banyak percobaan login. Coba lagi dalam 15 menit.',
                    'locked' => true
                ];
            }

            // Get user by username or email
            $user = $this->getUserByCredential($username);

            if (!$user) {
                $this->recordFailedAttempt($username);
                return [
                    'success' => false,
                    'message' => 'Username atau password salah',
                    'attempts_left' => $this->getRemainingAttempts($username)
                ];
            }

            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->recordFailedAttempt($username);
                return [
                    'success' => false,
                    'message' => 'Username atau password salah',
                    'attempts_left' => $this->getRemainingAttempts($username)
                ];
            }

            // Check if user is active
            if ($user['status'] !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Akun tidak aktif. Hubungi administrator.'
                ];
            }

            // Clear failed attempts
            $this->clearFailedAttempts($username);

            // Create session
            $this->createSession($user, $remember);

            // Log successful login
            $this->logActivity($user['id'], 'login_success', [
                'ip' => getClientIp(),
                'user_agent' => getUserAgent()
            ]);

            return [
                'success' => true,
                'message' => 'Login berhasil',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ];
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            error_log("Login error trace: " . $e->getTraceAsString());

            // In debug mode, show detailed error
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                return [
                    'success' => false,
                    'message' => 'Debug Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                ];
            }

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
            ];
        }
    }

    /**
     * Logout user
     */
    public function logout()
    {
        if ($this->isLoggedIn()) {
            $userId = $_SESSION['user_id'];

            // Log logout activity
            $this->logActivity($userId, 'logout', [
                'ip' => getClientIp()
            ]);

            // Destroy session
            $this->destroySession();
        }

        return ['success' => true, 'message' => 'Logout berhasil'];
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current logged in user
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, full_name, role, status, last_login 
                FROM users 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$_SESSION['user_id']]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user has specific role
     */
    public function hasRole($role)
    {
        $user = $this->getCurrentUser();
        if (!$user) return false;

        $roleHierarchy = [
            'viewer' => 1,
            'operator' => 2,
            'admin' => 3
        ];

        $userLevel = $roleHierarchy[$user['role']] ?? 0;
        $requiredLevel = $roleHierarchy[$role] ?? 999;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Check if user can access resource
     */
    public function canAccess($resource, $action = 'read')
    {
        $user = $this->getCurrentUser();
        if (!$user) return false;

        // Define permissions matrix
        $permissions = [
            'admin' => ['*'],
            'operator' => [
                'devices.*',
                'messages.*',
                'contacts.*',
                'api_tokens.*',
                'logs.read',
                'dashboard.read',
                'profile.*'
            ],
            'viewer' => [
                'devices.read',
                'messages.read',
                'logs.read',
                'dashboard.read',
                'profile.*'
            ]
        ];

        $userPerms = $permissions[$user['role']] ?? [];

        // Check wildcard permission
        if (in_array('*', $userPerms)) return true;

        // Check specific permission
        $permission = $resource . '.' . $action;
        if (in_array($permission, $userPerms)) return true;

        // Check resource wildcard
        $resourceWildcard = $resource . '.*';
        if (in_array($resourceWildcard, $userPerms)) return true;

        return false;
    }

    /**
     * Change user password
     */
    public function changePassword($currentPassword, $newPassword)
    {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'message' => 'Silakan login terlebih dahulu'];
        }

        try {
            $user = $this->getCurrentUser();

            // Verify current password
            if (!password_verify($currentPassword, $this->getUserPassword($user['id']))) {
                return ['success' => false, 'message' => 'Password lama salah'];
            }

            // Validate new password
            $validation = validatePassword($newPassword);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Password baru tidak valid: ' . implode(', ', $validation['errors'])
                ];
            }

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);

            $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $user['id']]);

            // Log password change
            $this->logActivity($user['id'], 'password_changed', ['ip' => getClientIp()]);

            return ['success' => true, 'message' => 'Password berhasil diubah'];
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }

    /**
     * Get user by username or email
     */
    private function getUserByCredential($credential)
    {
        $stmt = $this->db->prepare("
            SELECT id, username, email, password, full_name, role, status 
            FROM users 
            WHERE (username = ? OR email = ?) AND status = 'active'
        ");
        $stmt->execute([$credential, $credential]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user password hash
     */
    private function getUserPassword($userId)
    {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['password'] : null;
    }

    /**
     * Check if account is locked due to failed attempts
     */
    private function isAccountLocked($username)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$username, $this->lockoutDuration]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['attempts'] >= $this->maxLoginAttempts;
    }

    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt($username)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_attempts (username, ip_address, user_agent, attempt_time) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $username,
                getClientIp(),
                getUserAgent()
            ]);
        } catch (Exception $e) {
            error_log("Failed to record login attempt: " . $e->getMessage());
        }
    }

    /**
     * Clear failed attempts for user
     */
    private function clearFailedAttempts($username)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE username = ?");
            $stmt->execute([$username]);
        } catch (Exception $e) {
            error_log("Failed to clear login attempts: " . $e->getMessage());
        }
    }

    /**
     * Get remaining login attempts
     */
    private function getRemainingAttempts($username)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$username, $this->lockoutDuration]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return max(0, $this->maxLoginAttempts - $result['attempts']);
    }

    /**
     * Create user session
     */
    private function createSession($user, $remember = false)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // Set remember me cookie if requested
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (30 * 24 * 60 * 60); // 30 days

            setcookie('remember_token', $token, $expires, '/', '', false, true);

            // Store token in database
            $this->storeRememberToken($user['id'], $token, $expires);
        }

        // Store session in database
        $this->storeSession($user['id']);
    }

    /**
     * Store session in database
     */
    private function storeSession($userId)
    {
        try {
            $sessionId = session_id();
            $expiresAt = date('Y-m-d H:i:s', time() + ini_get('session.gc_maxlifetime'));

            $stmt = $this->db->prepare("
                INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                expires_at = VALUES(expires_at)
            ");

            $stmt->execute([
                $userId,
                $sessionId,
                getClientIp(),
                getUserAgent(),
                $expiresAt
            ]);
        } catch (Exception $e) {
            error_log("Failed to store session: " . $e->getMessage());
        }
    }

    /**
     * Store remember token
     */
    private function storeRememberToken($userId, $token, $expires)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO remember_tokens (user_id, token, expires_at) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, hash('sha256', $token), date('Y-m-d H:i:s', $expires)]);
        } catch (Exception $e) {
            error_log("Failed to store remember token: " . $e->getMessage());
        }
    }

    /**
     * Destroy session
     */
    private function destroySession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Remove session from database
        $this->removeSession(session_id());

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

        // Delete remember token cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);

        // Destroy session
        session_destroy();
    }

    /**
     * Remove session from database
     */
    private function removeSession($sessionId)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            error_log("Failed to remove session: " . $e->getMessage());
        }
    }

    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $data = [])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, data, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $action,
                json_encode($data),
                getClientIp(),
                getUserAgent()
            ]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }

    /**
     * Clean expired sessions and tokens
     */
    public function cleanup()
    {
        try {
            // Clean expired sessions
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
            $stmt->execute();

            // Clean expired remember tokens
            $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
            $stmt->execute();

            // Clean old login attempts (older than 24 hours)
            $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stmt->execute();

            return true;
        } catch (Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
            return false;
        }
    }
}
