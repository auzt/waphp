<?php

/**
 * User Management Class
 * 
 * Handles user CRUD operations, user management, and user-related business logic
 * Works with Auth class for authentication and permission management
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
require_once APP_ROOT . '/includes/functions.php';

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =============================================================================
    // USER CRUD OPERATIONS
    // =============================================================================

    /**
     * Create new user
     * 
     * @param array $data User data
     * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
     */
    public function create($data)
    {
        try {
            // Validate required fields
            $required = ['username', 'email', 'password', 'full_name'];
            $validation = validateRequired($data, $required);

            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Field wajib tidak lengkap: ' . implode(', ', $validation['missing']),
                    'user_id' => null
                ];
            }

            // Validate email format
            if (!isValidEmail($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Format email tidak valid',
                    'user_id' => null
                ];
            }

            // Validate username
            if (!isValidUsername($data['username'])) {
                return [
                    'success' => false,
                    'message' => 'Username tidak valid. Gunakan 3-50 karakter (huruf, angka, underscore, dash)',
                    'user_id' => null
                ];
            }

            // Validate password
            $passwordValidation = validatePassword($data['password']);
            if (!$passwordValidation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Password tidak valid: ' . implode(', ', $passwordValidation['errors']),
                    'user_id' => null
                ];
            }

            // Check if username already exists
            if ($this->usernameExists($data['username'])) {
                return [
                    'success' => false,
                    'message' => 'Username sudah digunakan',
                    'user_id' => null
                ];
            }

            // Check if email already exists
            if ($this->emailExists($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Email sudah digunakan',
                    'user_id' => null
                ];
            }

            // Validate role
            $role = $data['role'] ?? ROLE_OPERATOR;
            if (!isValidUserRole($role)) {
                $role = ROLE_OPERATOR;
            }

            // Sanitize data
            $userData = [
                'username' => sanitizeString($data['username']),
                'email' => sanitizeEmail($data['email']),
                'password' => hashPassword($data['password']),
                'full_name' => sanitizeString($data['full_name']),
                'role' => $role,
                'status' => $data['status'] ?? USER_STATUS_ACTIVE,
                'email_verified' => $data['email_verified'] ?? false,
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
                'language' => $data['language'] ?? 'id'
            ];

            // Insert user
            $userId = $this->db->insert("
                INSERT INTO users (
                    username, email, password, full_name, role, status,
                    email_verified, timezone, language, password_changed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $userData['username'],
                $userData['email'],
                $userData['password'],
                $userData['full_name'],
                $userData['role'],
                $userData['status'],
                $userData['email_verified'],
                $userData['timezone'],
                $userData['language']
            ]);

            if ($userId) {
                logActivity('user_created', "New user created: {$userData['username']}", $userId);

                return [
                    'success' => true,
                    'message' => 'User berhasil dibuat',
                    'user_id' => $userId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Gagal membuat user',
                    'user_id' => null
                ];
            }
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'user_id' => null
            ];
        }
    }

    /**
     * Update user data
     * 
     * @param int $userId
     * @param array $data
     * @return array ['success' => bool, 'message' => string]
     */
    public function update($userId, $data)
    {
        try {
            // Check if user exists
            $existingUser = $this->getById($userId);
            if (!$existingUser) {
                return [
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ];
            }

            $updateFields = [];
            $updateValues = [];

            // Update username
            if (isset($data['username']) && $data['username'] !== $existingUser['username']) {
                if (!isValidUsername($data['username'])) {
                    return [
                        'success' => false,
                        'message' => 'Username tidak valid'
                    ];
                }

                if ($this->usernameExists($data['username'], $userId)) {
                    return [
                        'success' => false,
                        'message' => 'Username sudah digunakan'
                    ];
                }

                $updateFields[] = 'username = ?';
                $updateValues[] = sanitizeString($data['username']);
            }

            // Update email
            if (isset($data['email']) && $data['email'] !== $existingUser['email']) {
                if (!isValidEmail($data['email'])) {
                    return [
                        'success' => false,
                        'message' => 'Format email tidak valid'
                    ];
                }

                if ($this->emailExists($data['email'], $userId)) {
                    return [
                        'success' => false,
                        'message' => 'Email sudah digunakan'
                    ];
                }

                $updateFields[] = 'email = ?';
                $updateValues[] = sanitizeEmail($data['email']);

                // Reset email verification if email changed
                $updateFields[] = 'email_verified = ?';
                $updateValues[] = false;
            }

            // Update full name
            if (isset($data['full_name'])) {
                $updateFields[] = 'full_name = ?';
                $updateValues[] = sanitizeString($data['full_name']);
            }

            // Update role (only admin can change roles)
            if (isset($data['role']) && hasRole(ROLE_ADMIN)) {
                if (isValidUserRole($data['role'])) {
                    $updateFields[] = 'role = ?';
                    $updateValues[] = $data['role'];
                }
            }

            // Update status (only admin can change status)
            if (isset($data['status']) && hasRole(ROLE_ADMIN)) {
                if (in_array($data['status'], [USER_STATUS_ACTIVE, USER_STATUS_INACTIVE, USER_STATUS_SUSPENDED])) {
                    $updateFields[] = 'status = ?';
                    $updateValues[] = $data['status'];
                }
            }

            // Update timezone
            if (isset($data['timezone'])) {
                $updateFields[] = 'timezone = ?';
                $updateValues[] = sanitizeString($data['timezone']);
            }

            // Update language
            if (isset($data['language'])) {
                $updateFields[] = 'language = ?';
                $updateValues[] = sanitizeString($data['language']);
            }

            // Update avatar URL
            if (isset($data['avatar_url'])) {
                $updateFields[] = 'avatar_url = ?';
                $updateValues[] = sanitizeString($data['avatar_url']);
            }

            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'message' => 'Tidak ada data yang diubah'
                ];
            }

            // Add updated_at timestamp
            $updateFields[] = 'updated_at = NOW()';
            $updateValues[] = $userId;

            // Execute update
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $updated = $this->db->update($sql, $updateValues);

            if ($updated) {
                logActivity('user_updated', "User updated: {$existingUser['username']}", $userId);

                return [
                    'success' => true,
                    'message' => 'Data user berhasil diupdate'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Tidak ada perubahan data'
                ];
            }
        } catch (Exception $e) {
            error_log("Update user error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ];
        }
    }

    /**
     * Delete user (soft delete)
     * 
     * @param int $userId
     * @return array ['success' => bool, 'message' => string]
     */
    public function delete($userId)
    {
        try {
            // Check if user exists
            $user = $this->getById($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ];
            }

            // Prevent deleting own account
            if ($userId == getCurrentUserId()) {
                return [
                    'success' => false,
                    'message' => 'Tidak dapat menghapus akun sendiri'
                ];
            }

            // Prevent deleting the last admin
            if ($user['role'] === ROLE_ADMIN && $this->countAdmins() <= 1) {
                return [
                    'success' => false,
                    'message' => 'Tidak dapat menghapus admin terakhir'
                ];
            }

            // Soft delete by changing status
            $deleted = $this->db->update("
                UPDATE users 
                SET status = 'deleted', 
                    username = CONCAT(username, '_deleted_', UNIX_TIMESTAMP()),
                    email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()),
                    updated_at = NOW()
                WHERE id = ?
            ", [$userId]);

            if ($deleted) {
                // Delete all user devices
                $this->deleteUserDevices($userId);

                // Terminate all user sessions
                $this->db->delete("DELETE FROM user_sessions WHERE user_id = ?", [$userId]);

                logActivity('user_deleted', "User deleted: {$user['username']}", $userId);

                return [
                    'success' => true,
                    'message' => 'User berhasil dihapus'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Gagal menghapus user'
                ];
            }
        } catch (Exception $e) {
            error_log("Delete user error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ];
        }
    }

    // =============================================================================
    // USER RETRIEVAL METHODS
    // =============================================================================

    /**
     * Get user by ID
     * 
     * @param int $userId
     * @return array|null
     */
    public function getById($userId)
    {
        return $this->db->selectOne("
            SELECT * FROM users 
            WHERE id = ? AND status != 'deleted'
        ", [$userId]);
    }

    /**
     * Get user by username
     * 
     * @param string $username
     * @return array|null
     */
    public function getByUsername($username)
    {
        return $this->db->selectOne("
            SELECT * FROM users 
            WHERE username = ? AND status != 'deleted'
        ", [$username]);
    }

    /**
     * Get user by email
     * 
     * @param string $email
     * @return array|null
     */
    public function getByEmail($email)
    {
        return $this->db->selectOne("
            SELECT * FROM users 
            WHERE email = ? AND status != 'deleted'
        ", [$email]);
    }

    /**
     * Get all users with pagination
     * 
     * @param int $page
     * @param int $limit
     * @param array $filters
     * @return array
     */
    public function getAll($page = 1, $limit = 20, $filters = [])
    {
        $conditions = ["status != 'deleted'"];
        $params = [];

        // Apply filters
        if (!empty($filters['role'])) {
            $conditions[] = "role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT * FROM users WHERE {$whereClause} ORDER BY created_at DESC";

        return $this->db->paginate($sql, $params, $page, $limit);
    }

    /**
     * Get user statistics
     * 
     * @return array
     */
    public function getStatistics()
    {
        $stats = $this->db->selectOne("
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                SUM(CASE WHEN role = 'operator' THEN 1 ELSE 0 END) as operator_count,
                SUM(CASE WHEN role = 'viewer' THEN 1 ELSE 0 END) as viewer_count,
                SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_last_30_days
            FROM users 
            WHERE status != 'deleted'
        ");

        return $stats;
    }

    /**
     * Get users with device counts
     * 
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getUsersWithDeviceCounts($page = 1, $limit = 20)
    {
        $sql = "
            SELECT 
                u.*,
                COUNT(d.id) as device_count,
                SUM(CASE WHEN d.status = 'connected' THEN 1 ELSE 0 END) as connected_devices
            FROM users u
            LEFT JOIN devices d ON u.id = d.user_id
            WHERE u.status != 'deleted'
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ";

        return $this->db->paginate($sql, [], $page, $limit);
    }

    // =============================================================================
    // USER VALIDATION METHODS
    // =============================================================================

    /**
     * Check if username exists
     * 
     * @param string $username
     * @param int $excludeUserId
     * @return bool
     */
    public function usernameExists($username, $excludeUserId = null)
    {
        $sql = "SELECT id FROM users WHERE username = ? AND status != 'deleted'";
        $params = [$username];

        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }

        $result = $this->db->selectOne($sql, $params);
        return $result !== null;
    }

    /**
     * Check if email exists
     * 
     * @param string $email
     * @param int $excludeUserId
     * @return bool
     */
    public function emailExists($email, $excludeUserId = null)
    {
        $sql = "SELECT id FROM users WHERE email = ? AND status != 'deleted'";
        $params = [$email];

        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }

        $result = $this->db->selectOne($sql, $params);
        return $result !== null;
    }

    /**
     * Count admin users
     * 
     * @return int
     */
    private function countAdmins()
    {
        $result = $this->db->selectOne("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE role = 'admin' AND status = 'active'
        ");

        return (int) $result['count'];
    }

    // =============================================================================
    // USER DEVICE MANAGEMENT
    // =============================================================================

    /**
     * Get user devices
     * 
     * @param int $userId
     * @param bool $activeOnly
     * @return array
     */
    public function getUserDevices($userId, $activeOnly = true)
    {
        $sql = "SELECT * FROM devices WHERE user_id = ?";
        $params = [$userId];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY created_at DESC";

        return $this->db->select($sql, $params);
    }

    /**
     * Count user devices
     * 
     * @param int $userId
     * @param bool $activeOnly
     * @return int
     */
    public function countUserDevices($userId, $activeOnly = true)
    {
        $sql = "SELECT COUNT(*) as count FROM devices WHERE user_id = ?";
        $params = [$userId];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $result = $this->db->selectOne($sql, $params);
        return (int) $result['count'];
    }

    /**
     * Check if user can add more devices
     * 
     * @param int $userId
     * @return bool
     */
    public function canAddDevice($userId)
    {
        $currentCount = $this->countUserDevices($userId);
        $maxDevices = $this->getMaxDevicesForUser($userId);

        return $currentCount < $maxDevices;
    }

    /**
     * Get maximum devices allowed for user
     * 
     * @param int $userId
     * @return int
     */
    public function getMaxDevicesForUser($userId)
    {
        $user = $this->getById($userId);

        if (!$user) {
            return 0;
        }

        // Admin can have unlimited devices (or higher limit)
        if ($user['role'] === ROLE_ADMIN) {
            return 100; // Or unlimited
        }

        // Get from settings
        try {
            $setting = $this->db->selectOne("
                SELECT setting_value 
                FROM settings 
                WHERE setting_key = 'max_devices_per_user'
            ");

            return $setting ? (int) $setting['setting_value'] : 10;
        } catch (Exception $e) {
            return 10; // Default fallback
        }
    }

    /**
     * Delete all user devices
     * 
     * @param int $userId
     * @return int Number of deleted devices
     */
    private function deleteUserDevices($userId)
    {
        return $this->db->update("
            UPDATE devices 
            SET is_active = 0, status = 'disconnected', updated_at = NOW()
            WHERE user_id = ?
        ", [$userId]);
    }

    // =============================================================================
    // USER PROFILE METHODS
    // =============================================================================

    /**
     * Update user profile
     * 
     * @param int $userId
     * @param array $profileData
     * @return array ['success' => bool, 'message' => string]
     */
    public function updateProfile($userId, $profileData)
    {
        $allowedFields = ['full_name', 'timezone', 'language', 'avatar_url'];
        $updateData = array_intersect_key($profileData, array_flip($allowedFields));

        return $this->update($userId, $updateData);
    }

    /**
     * Update user avatar
     * 
     * @param int $userId
     * @param string $avatarUrl
     * @return bool
     */
    public function updateAvatar($userId, $avatarUrl)
    {
        return $this->db->update("
            UPDATE users 
            SET avatar_url = ?, updated_at = NOW() 
            WHERE id = ?
        ", [$avatarUrl, $userId]) > 0;
    }

    /**
     * Get user activity summary
     * 
     * @param int $userId
     * @param int $days
     * @return array
     */
    public function getUserActivity($userId, $days = 30)
    {
        $activity = $this->db->selectOne("
            SELECT 
                u.last_login,
                COUNT(DISTINCT d.id) as total_devices,
                SUM(CASE WHEN d.status = 'connected' THEN 1 ELSE 0 END) as connected_devices,
                COUNT(DISTINCT ml.id) as total_messages,
                COUNT(DISTINCT CASE WHEN ml.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN ml.id END) as recent_messages
            FROM users u
            LEFT JOIN devices d ON u.id = d.user_id
            LEFT JOIN message_logs ml ON d.id = ml.device_id
            WHERE u.id = ?
            GROUP BY u.id
        ", [$days, $userId]);

        return $activity ?: [
            'last_login' => null,
            'total_devices' => 0,
            'connected_devices' => 0,
            'total_messages' => 0,
            'recent_messages' => 0
        ];
    }

    // =============================================================================
    // USER PREFERENCES
    // =============================================================================

    /**
     * Get user preferences
     * 
     * @param int $userId
     * @return array
     */
    public function getPreferences($userId)
    {
        $user = $this->getById($userId);

        if (!$user) {
            return [];
        }

        return [
            'timezone' => $user['timezone'] ?? 'Asia/Jakarta',
            'language' => $user['language'] ?? 'id',
            'two_factor_enabled' => $user['two_factor_enabled'] ?? false,
            'email_verified' => $user['email_verified'] ?? false
        ];
    }

    /**
     * Update user preferences
     * 
     * @param int $userId
     * @param array $preferences
     * @return bool
     */
    public function updatePreferences($userId, $preferences)
    {
        $allowedPrefs = ['timezone', 'language'];
        $updateData = array_intersect_key($preferences, array_flip($allowedPrefs));

        if (empty($updateData)) {
            return false;
        }

        $result = $this->update($userId, $updateData);
        return $result['success'];
    }

    // =============================================================================
    // UTILITY METHODS
    // =============================================================================

    /**
     * Get users for dropdown/select options
     * 
     * @param bool $activeOnly
     * @return array
     */
    public function getUsersForSelect($activeOnly = true)
    {
        $sql = "SELECT id, username, full_name FROM users WHERE status != 'deleted'";

        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }

        $sql .= " ORDER BY full_name";

        $users = $this->db->select($sql);
        $options = [];

        foreach ($users as $user) {
            $options[$user['id']] = $user['full_name'] . ' (' . $user['username'] . ')';
        }

        return $options;
    }

    /**
     * Search users
     * 
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function searchUsers($query, $limit = 10)
    {
        $searchTerm = '%' . $query . '%';

        return $this->db->select("
            SELECT id, username, full_name, email, role, status
            FROM users 
            WHERE status != 'deleted' 
            AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)
            ORDER BY 
                CASE WHEN username LIKE ? THEN 1 ELSE 2 END,
                full_name
            LIMIT ?
        ", [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
    }

    /**
     * Get user roles for dropdown
     * 
     * @return array
     */
    public static function getRoles()
    {
        return getUserRoles();
    }

    /**
     * Get user statuses for dropdown
     * 
     * @return array
     */
    public static function getStatuses()
    {
        return [
            USER_STATUS_ACTIVE => 'Active',
            USER_STATUS_INACTIVE => 'Inactive',
            USER_STATUS_SUSPENDED => 'Suspended',
            USER_STATUS_PENDING => 'Pending'
        ];
    }

    /**
     * Sanitize user data for API response
     * 
     * @param array $user
     * @param bool $includeEmail
     * @return array
     */
    public function sanitizeForApi($user, $includeEmail = false)
    {
        $sanitized = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'status' => $user['status'],
            'avatar_url' => $user['avatar_url'],
            'timezone' => $user['timezone'],
            'language' => $user['language'],
            'last_login' => $user['last_login'],
            'created_at' => $user['created_at']
        ];

        if ($includeEmail) {
            $sanitized['email'] = $user['email'];
            $sanitized['email_verified'] = $user['email_verified'];
        }

        return $sanitized;
    }
}
