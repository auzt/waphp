<?php

/**
 * Authentication Class
 * 
 * Handles user authentication, login, logout, password management
 * Integrates with session management and security features
 * 
 * @author WhatsApp Monitor Team
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/config/constants.php';
require_once APP_ROOT . '/classes/Database.php';
require_once APP_ROOT . '/classes/User.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/session.php';

class Auth
{
    private $db;
    private $user;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->user = new User();
    }

    // =============================================================================
    // LOGIN & LOGOUT METHODS
    // =============================================================================

    /**
     * Authenticate user with username/email and password
     * 
     * @param string $login Username or email
     * @param string $password Plain text password
     * @param bool $rememberMe Whether to set remember me cookie
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public function login($login, $password, $rememberMe = false)
    {
        try {
            // Input validation
            if (empty($login) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Username dan password harus diisi',
                    'user' => null
                ];
            }

            // Rate limiting check
            $clientIp = getClientIpAddress();
            if (isRateLimited($clientIp) || isRateLimited($login)) {
                recordLoginAttempt($clientIp, false);
                recordLoginAttempt($login, false);

                logActivity('login_rate_limited', "Rate limited login attempt for: {$login}", null);

                return [
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.',
                    'user' => null
                ];
            }

            // Find user by username or email
            $user = $this->findUserByLogin($login);

            if (!$user) {
                recordLoginAttempt($clientIp, false);
                recordLoginAttempt($login, false);

                logActivity('login_failed', "Login failed - user not found: {$login}", null);

                return [
                    'success' => false,
                    'message' => 'Username atau password salah',
                    'user' => null
                ];
            }

            // Check if user is active
            if ($user['status'] !== USER_STATUS_ACTIVE) {
                recordLoginAttempt($clientIp, false);
                recordLoginAttempt($login, false);

                logActivity('login_failed', "Login failed - inactive user: {$login}", $user['id']);

                $statusMessage = $this->getStatusMessage($user['status']);
                return [
                    'success' => false,
                    'message' => $statusMessage,
                    'user' => null
                ];
            }

            // Verify password
            if (!verifyPassword($password, $user['password'])) {
                recordLoginAttempt($clientIp, false);
                recordLoginAttempt($login, false);

                // Update failed login attempts in database
                $this->incrementLoginAttempts($user['id']);

                logActivity('login_failed', "Login failed - invalid password: {$login}", $user['id']);

                return [
                    'success' => false,
                    'message' => 'Username atau password salah',
                    'user' => null
                ];
            }

            // Check if account is locked
            if ($this->isAccountLocked($user['id'])) {
                recordLoginAttempt($clientIp, false);
                recordLoginAttempt($login, false);

                logActivity('login_failed', "Login failed - account locked: {$login}", $user['id']);

                return [
                    'success' => false,
                    'message' => 'Akun terkunci karena terlalu banyak percobaan login gagal. Coba lagi nanti.',
                    'user' => null
                ];
            }

            // Successful login
            recordLoginAttempt($clientIp, true);
            recordLoginAttempt($login, true);

            // Reset login attempts
            $this->resetLoginAttempts($user['id']);

            // Check if password needs rehashing
            if (passwordNeedsRehash($user['password'])) {
                $this->updatePassword($user['id'], $password);
            }

            // Create user session
            loginUser($user, $rememberMe);

            logActivity('login_success', "User logged in successfully: {$user['username']}", $user['id']);

            return [
                'success' => true,
                'message' => 'Login berhasil',
                'user' => $this->sanitizeUserData($user)
            ];
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.',
                'user' => null
            ];
        }
    }

    /**
     * Logout current user
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function logout()
    {
        try {
            if (!isLoggedIn()) {
                return [
                    'success' => false,
                    'message' => 'User tidak sedang login'
                ];
            }

            $userId = getCurrentUserId();
            $username = $_SESSION['username'] ?? 'unknown';

            // Logout user (handled by session.php)
            logoutUser();

            return [
                'success' => true,
                'message' => 'Logout berhasil'
            ];
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat logout'
            ];
        }
    }

    // =============================================================================
    // PASSWORD MANAGEMENT
    // =============================================================================

    /**
     * Change user password
     * 
     * @param int $userId
     * @param string $currentPassword
     * @param string $newPassword
     * @return array ['success' => bool, 'message' => string]
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        try {
            // Get user data
            $user = $this->user->getById($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ];
            }

            // Verify current password
            if (!verifyPassword($currentPassword, $user['password'])) {
                logActivity('password_change_failed', "Invalid current password", $userId);

                return [
                    'success' => false,
                    'message' => 'Password saat ini salah'
                ];
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
            $hashedPassword = hashPassword($newPassword);
            $updated = $this->updatePassword($userId, $newPassword);

            if ($updated) {
                logActivity('password_changed', "Password changed successfully", $userId);

                return [
                    'success' => true,
                    'message' => 'Password berhasil diubah'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Gagal mengubah password'
                ];
            }
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ];
        }
    }

    /**
     * Reset password (admin function)
     * 
     * @param int $userId
     * @param string $newPassword
     * @return array ['success' => bool, 'message' => string]
     */
    public function resetPassword($userId, $newPassword)
    {
        try {
            // Check if user exists
            $user = $this->user->getById($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ];
            }

            // Validate new password
            $validation = validatePassword($newPassword);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Password tidak valid: ' . implode(', ', $validation['errors'])
                ];
            }

            // Update password
            $updated = $this->updatePassword($userId, $newPassword);

            if ($updated) {
                // Reset login attempts
                $this->resetLoginAttempts($userId);

                // Terminate all sessions for this user
                $this->terminateAllUserSessions($userId);

                logActivity('password_reset', "Password reset by admin for user: {$user['username']}", $userId);

                return [
                    'success' => true,
                    'message' => 'Password berhasil direset'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Gagal mereset password'
                ];
            }
        } catch (Exception $e) {
            error_log("Reset password error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ];
        }
    }

    // =============================================================================
    // ACCOUNT MANAGEMENT
    // =============================================================================

    /**
     * Lock user account
     * 
     * @param int $userId
     * @param string $reason
     * @return bool
     */
    public function lockAccount($userId, $reason = 'Administrative action')
    {
        try {
            $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);

            $updated = $this->db->update("
                UPDATE users 
                SET locked_until = ?, login_attempts = ?
                WHERE id = ?
            ", [$lockUntil, MAX_LOGIN_ATTEMPTS, $userId]);

            if ($updated) {
                // Terminate all sessions
                $this->terminateAllUserSessions($userId);

                logActivity('account_locked', "Account locked: {$reason}", $userId);
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Lock account error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unlock user account
     * 
     * @param int $userId
     * @return bool
     */
    public function unlockAccount($userId)
    {
        try {
            $updated = $this->db->update("
                UPDATE users 
                SET locked_until = NULL, login_attempts = 0
                WHERE id = ?
            ", [$userId]);

            if ($updated) {
                logActivity('account_unlocked', "Account unlocked", $userId);
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Unlock account error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable or disable user account
     * 
     * @param int $userId
     * @param string $status
     * @return bool
     */
    public function setAccountStatus($userId, $status)
    {
        try {
            if (!in_array($status, [USER_STATUS_ACTIVE, USER_STATUS_INACTIVE, USER_STATUS_SUSPENDED])) {
                return false;
            }

            $updated = $this->db->update("UPDATE users SET status = ? WHERE id = ?", [$status, $userId]);

            if ($updated) {
                // Terminate sessions if account is disabled
                if ($status !== USER_STATUS_ACTIVE) {
                    $this->terminateAllUserSessions($userId);
                }

                logActivity('account_status_changed', "Account status changed to: {$status}", $userId);
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Set account status error: " . $e->getMessage());
            return false;
        }
    }

    // =============================================================================
    // TWO-FACTOR AUTHENTICATION (FUTURE FEATURE)
    // =============================================================================

    /**
     * Enable two-factor authentication
     * 
     * @param int $userId
     * @return array ['success' => bool, 'secret' => string|null, 'qr_code' => string|null]
     */
    public function enableTwoFactor($userId)
    {
        // TODO: Implement 2FA with TOTP
        return [
            'success' => false,
            'message' => 'Two-factor authentication akan segera tersedia',
            'secret' => null,
            'qr_code' => null
        ];
    }

    /**
     * Disable two-factor authentication
     * 
     * @param int $userId
     * @return bool
     */
    public function disableTwoFactor($userId)
    {
        // TODO: Implement 2FA disable
        return false;
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    /**
     * Find user by username or email
     * 
     * @param string $login
     * @return array|null
     */
    private function findUserByLogin($login)
    {
        // Try username first
        $user = $this->db->selectOne("SELECT * FROM users WHERE username = ? AND status != 'deleted'", [$login]);

        // If not found, try email
        if (!$user && isValidEmail($login)) {
            $user = $this->db->selectOne("SELECT * FROM users WHERE email = ? AND status != 'deleted'", [$login]);
        }

        return $user;
    }

    /**
     * Update user password
     * 
     * @param int $userId
     * @param string $password
     * @return bool
     */
    private function updatePassword($userId, $password)
    {
        $hashedPassword = hashPassword($password);

        return $this->db->update("
            UPDATE users 
            SET password = ?, password_changed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ", [$hashedPassword, $userId]) > 0;
    }

    /**
     * Check if account is locked
     * 
     * @param int $userId
     * @return bool
     */
    private function isAccountLocked($userId)
    {
        $user = $this->db->selectOne("
            SELECT login_attempts, locked_until 
            FROM users 
            WHERE id = ?
        ", [$userId]);

        if (!$user) {
            return true;
        }

        // Check if manually locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return true;
        }

        // Check if auto-locked due to failed attempts
        return $user['login_attempts'] >= MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Increment login attempts
     * 
     * @param int $userId
     * @return void
     */
    private function incrementLoginAttempts($userId)
    {
        $this->db->execute("
            UPDATE users 
            SET login_attempts = login_attempts + 1,
                locked_until = CASE 
                    WHEN login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? SECOND)
                    ELSE locked_until 
                END
            WHERE id = ?
        ", [MAX_LOGIN_ATTEMPTS, LOCKOUT_DURATION, $userId]);
    }

    /**
     * Reset login attempts
     * 
     * @param int $userId
     * @return void
     */
    private function resetLoginAttempts($userId)
    {
        $this->db->update("
            UPDATE users 
            SET login_attempts = 0, locked_until = NULL 
            WHERE id = ?
        ", [$userId]);
    }

    /**
     * Terminate all sessions for a user
     * 
     * @param int $userId
     * @return int Number of terminated sessions
     */
    private function terminateAllUserSessions($userId)
    {
        return $this->db->delete("DELETE FROM user_sessions WHERE user_id = ?", [$userId]);
    }

    /**
     * Get status message for user status
     * 
     * @param string $status
     * @return string
     */
    private function getStatusMessage($status)
    {
        $messages = [
            USER_STATUS_INACTIVE => 'Akun tidak aktif. Hubungi administrator.',
            USER_STATUS_SUSPENDED => 'Akun ditangguhkan. Hubungi administrator.',
            USER_STATUS_PENDING => 'Akun menunggu aktivasi.',
        ];

        return $messages[$status] ?? 'Status akun tidak valid.';
    }

    /**
     * Sanitize user data for response
     * 
     * @param array $user
     * @return array
     */
    private function sanitizeUserData($user)
    {
        unset($user['password']);
        unset($user['two_factor_secret']);

        return $user;
    }

    // =============================================================================
    // STATIC HELPER METHODS
    // =============================================================================

    /**
     * Check if user is authenticated
     * 
     * @return bool
     */
    public static function check()
    {
        return isLoggedIn();
    }

    /**
     * Get current authenticated user
     * 
     * @return array|null
     */
    public static function user()
    {
        return getCurrentUser();
    }

    /**
     * Get current user ID
     * 
     * @return int|null
     */
    public static function id()
    {
        return getCurrentUserId();
    }

    /**
     * Check if current user has specific role
     * 
     * @param string $role
     * @return bool
     */
    public static function hasRole($role)
    {
        return hasRole($role);
    }

    /**
     * Check if current user has specific permission
     * 
     * @param string $permission
     * @return bool
     */
    public static function hasPermission($permission)
    {
        $userRole = getCurrentUserRole();
        return $userRole ? hasPermission($permission, $userRole) : false;
    }

    /**
     * Require authentication (redirect if not authenticated)
     * 
     * @param string $redirectUrl
     * @return void
     */
    public static function requireAuth($redirectUrl = '/pages/auth/login.php')
    {
        requireAuth($redirectUrl);
    }

    /**
     * Require specific permission (redirect if not authorized)
     * 
     * @param string $permission
     * @param string $redirectUrl
     * @return void
     */
    public static function requirePermission($permission, $redirectUrl = '/pages/dashboard/')
    {
        requirePermission($permission, $redirectUrl);
    }
}
