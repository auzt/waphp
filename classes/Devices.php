<?php

/**
 * Device Management Class
 * 
 * Handles WhatsApp device CRUD operations and management
 * - Device creation and management
 * - Status tracking and updates
 * - Integration with Node.js backend
 * - Statistics and monitoring
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NodeJSClient.php';
require_once __DIR__ . '/../includes/functions.php';

class Device
{
    private $db;
    private $nodeClient;

    // Device status constants
    const STATUS_CONNECTING = 'connecting';
    const STATUS_CONNECTED = 'connected';
    const STATUS_DISCONNECTED = 'disconnected';
    const STATUS_PAIRING = 'pairing';
    const STATUS_BANNED = 'banned';
    const STATUS_ERROR = 'error';
    const STATUS_TIMEOUT = 'timeout';
    const STATUS_AUTH_FAILURE = 'auth_failure';
    const STATUS_LOGOUT = 'logout';

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->nodeClient = new NodeJSClient();
    }

    /**
     * Create new device
     */
    public function create($data)
    {
        try {
            // Validate required fields
            $required = ['user_id', 'device_name', 'phone_number'];
            $validation = validateRequired($data, $required);

            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Field wajib tidak lengkap: ' . implode(', ', $validation['missing'])
                ];
            }

            // Validate phone number
            if (!isValidPhoneNumber($data['phone_number'])) {
                return [
                    'success' => false,
                    'message' => 'Format nomor telepon tidak valid'
                ];
            }

            // Format phone number
            $phoneNumber = formatPhoneNumber($data['phone_number']);

            // Check if phone number already exists
            if ($this->phoneNumberExists($phoneNumber, $data['user_id'])) {
                return [
                    'success' => false,
                    'message' => 'Nomor telepon sudah terdaftar untuk user ini'
                ];
            }

            // Check user device limit
            if (!$this->canAddDevice($data['user_id'])) {
                $maxDevices = $_ENV['MAX_DEVICES_PER_USER'] ?? 10;
                return [
                    'success' => false,
                    'message' => "Maksimal {$maxDevices} device per user"
                ];
            }

            // Generate unique device ID
            $deviceId = $this->generateDeviceId($phoneNumber);

            // Insert device to database
            $stmt = $this->db->prepare("
                INSERT INTO devices (user_id, device_name, phone_number, device_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $data['user_id'],
                sanitizeInput($data['device_name']),
                $phoneNumber,
                $deviceId,
                self::STATUS_DISCONNECTED
            ]);

            $deviceDbId = $this->db->lastInsertId();

            // Initialize device in Node.js
            $nodeResult = $this->nodeClient->initializeSession($deviceId, $phoneNumber);

            if (!$nodeResult['success']) {
                // Rollback if Node.js initialization fails
                $this->delete($deviceDbId);
                return [
                    'success' => false,
                    'message' => 'Gagal menginisialisasi device di backend: ' . $nodeResult['message']
                ];
            }

            // Log activity
            logActivity("Device created: {$data['device_name']} ({$phoneNumber})", 'info', [
                'device_id' => $deviceDbId,
                'device_name' => $data['device_name'],
                'phone_number' => $phoneNumber
            ]);

            return [
                'success' => true,
                'message' => 'Device berhasil dibuat',
                'device_id' => $deviceDbId,
                'device_key' => $deviceId
            ];
        } catch (Exception $e) {
            error_log("Device creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get device by ID
     */
    public function getById($id, $userId = null)
    {
        try {
            $sql = "
                SELECT d.*, u.username, u.full_name as owner_name,
                       t.token, t.is_active as token_active
                FROM devices d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN api_tokens t ON d.id = t.device_id AND t.is_active = 1
                WHERE d.id = ?
            ";

            $params = [$id];

            // Add user filter if specified
            if ($userId !== null) {
                $sql .= " AND d.user_id = ?";
                $params[] = $userId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $device = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($device) {
                // Get additional stats
                $device['message_count_today'] = $this->getMessageCountToday($id);
                $device['total_messages'] = $this->getTotalMessages($id);
                $device['last_message'] = $this->getLastMessage($id);
            }

            return $device;
        } catch (Exception $e) {
            error_log("Get device error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get devices list for user
     */
    public function getByUser($userId, $filters = [])
    {
        try {
            $sql = "
                SELECT d.*, t.token, t.is_active as token_active,
                       (SELECT COUNT(*) FROM message_logs ml WHERE ml.device_id = d.id AND DATE(ml.created_at) = CURDATE()) as messages_today
                FROM devices d
                LEFT JOIN api_tokens t ON d.id = t.device_id AND t.is_active = 1
                WHERE d.user_id = ?
            ";

            $params = [$userId];

            // Apply filters
            if (!empty($filters['status'])) {
                $sql .= " AND d.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (d.device_name LIKE ? OR d.phone_number LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Order by
            $orderBy = $filters['order_by'] ?? 'created_at';
            $orderDir = $filters['order_dir'] ?? 'DESC';
            $sql .= " ORDER BY d.{$orderBy} {$orderDir}";

            // Limit
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT " . intval($filters['limit']);
                if (!empty($filters['offset'])) {
                    $sql .= " OFFSET " . intval($filters['offset']);
                }
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get devices by user error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update device
     */
    public function update($id, $data, $userId = null)
    {
        try {
            // Verify device ownership
            if ($userId && !$this->isOwner($id, $userId)) {
                return [
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke device ini'
                ];
            }

            $updateFields = [];
            $params = [];

            // Allowed fields to update
            $allowedFields = ['device_name', 'phone_number'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'phone_number') {
                        if (!isValidPhoneNumber($data[$field])) {
                            return [
                                'success' => false,
                                'message' => 'Format nomor telepon tidak valid'
                            ];
                        }
                        $data[$field] = formatPhoneNumber($data[$field]);
                    }

                    $updateFields[] = "{$field} = ?";
                    $params[] = sanitizeInput($data[$field]);
                }
            }

            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'message' => 'Tidak ada data yang diupdate'
                ];
            }

            $updateFields[] = "updated_at = NOW()";
            $params[] = $id;

            $sql = "UPDATE devices SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Log activity
            logActivity("Device updated: ID {$id}", 'info', [
                'device_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return [
                'success' => true,
                'message' => 'Device berhasil diupdate'
            ];
        } catch (Exception $e) {
            error_log("Update device error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete device
     */
    public function delete($id, $userId = null)
    {
        try {
            // Get device info first
            $device = $this->getById($id, $userId);
            if (!$device) {
                return [
                    'success' => false,
                    'message' => 'Device tidak ditemukan'
                ];
            }

            // Disconnect from Node.js first
            $this->nodeClient->logout($device['device_id']);

            // Delete from database (cascade will handle related records)
            $stmt = $this->db->prepare("DELETE FROM devices WHERE id = ?");
            $stmt->execute([$id]);

            // Log activity
            logActivity("Device deleted: {$device['device_name']} ({$device['phone_number']})", 'warning', [
                'device_id' => $id,
                'device_name' => $device['device_name'],
                'phone_number' => $device['phone_number']
            ]);

            return [
                'success' => true,
                'message' => 'Device berhasil dihapus'
            ];
        } catch (Exception $e) {
            error_log("Delete device error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Connect device to WhatsApp
     */
    public function connect($id, $userId = null)
    {
        try {
            $device = $this->getById($id, $userId);
            if (!$device) {
                return [
                    'success' => false,
                    'message' => 'Device tidak ditemukan'
                ];
            }

            // Update status to connecting
            $this->updateStatus($id, self::STATUS_CONNECTING);

            // Send connect command to Node.js
            $result = $this->nodeClient->connect($device['device_id']);

            if ($result['success']) {
                logActivity("Device connection initiated: {$device['device_name']}", 'info', [
                    'device_id' => $id
                ]);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Connect device error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Disconnect device from WhatsApp
     */
    public function disconnect($id, $userId = null)
    {
        try {
            $device = $this->getById($id, $userId);
            if (!$device) {
                return [
                    'success' => false,
                    'message' => 'Device tidak ditemukan'
                ];
            }

            // Send disconnect command to Node.js
            $result = $this->nodeClient->disconnect($device['device_id']);

            if ($result['success']) {
                $this->updateStatus($id, self::STATUS_DISCONNECTED);

                logActivity("Device disconnected: {$device['device_name']}", 'info', [
                    'device_id' => $id
                ]);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Disconnect device error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get QR code for device
     */
    public function getQRCode($id, $userId = null)
    {
        try {
            $device = $this->getById($id, $userId);
            if (!$device) {
                return [
                    'success' => false,
                    'message' => 'Device tidak ditemukan'
                ];
            }

            // Check if QR is still valid
            if (
                $device['qr_code'] && $device['qr_expires_at'] &&
                strtotime($device['qr_expires_at']) > time()
            ) {
                return [
                    'success' => true,
                    'qr_code' => $device['qr_code'],
                    'expires_at' => $device['qr_expires_at']
                ];
            }

            // Get new QR from Node.js
            $result = $this->nodeClient->getQR($device['device_id']);

            if ($result['success'] && !empty($result['qr'])) {
                // Update QR in database
                $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes

                $stmt = $this->db->prepare("
                    UPDATE devices 
                    SET qr_code = ?, qr_expires_at = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$result['qr'], $expiresAt, $id]);

                return [
                    'success' => true,
                    'qr_code' => $result['qr'],
                    'expires_at' => $expiresAt
                ];
            }

            return $result;
        } catch (Exception $e) {
            error_log("Get QR code error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update device status
     */
    public function updateStatus($id, $status, $additionalData = [])
    {
        try {
            $updateFields = ['status = ?', 'updated_at = NOW()'];
            $params = [$status];

            // Handle additional data
            if (!empty($additionalData['raw_status'])) {
                $updateFields[] = 'raw_status = ?';
                $params[] = $additionalData['raw_status'];
            }

            if (!empty($additionalData['whatsapp_user_id'])) {
                $updateFields[] = 'whatsapp_user_id = ?';
                $params[] = $additionalData['whatsapp_user_id'];
            }

            if (!empty($additionalData['whatsapp_name'])) {
                $updateFields[] = 'whatsapp_name = ?';
                $params[] = $additionalData['whatsapp_name'];
            }

            if ($status === self::STATUS_CONNECTED) {
                $updateFields[] = 'connected_at = NOW()';
                $updateFields[] = 'is_online = 1';
                $updateFields[] = 'retry_count = 0';
            } else {
                $updateFields[] = 'is_online = 0';
            }

            $updateFields[] = 'last_seen = NOW()';
            $params[] = $id;

            $sql = "UPDATE devices SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return true;
        } catch (Exception $e) {
            error_log("Update device status error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats($userId = null)
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_devices,
                    SUM(CASE WHEN status = 'connected' THEN 1 ELSE 0 END) as connected_devices,
                    SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned_devices,
                    SUM(CASE WHEN status IN ('error', 'timeout', 'auth_failure') THEN 1 ELSE 0 END) as error_devices,
                    SUM(CASE WHEN status = 'pairing' THEN 1 ELSE 0 END) as pairing_devices
                FROM devices
            ";

            $params = [];

            if ($userId !== null) {
                $sql .= " WHERE user_id = ?";
                $params[] = $userId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get dashboard stats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get message statistics
     */
    public function getMessageStats($userId = null)
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as messages_today,
                    SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming_messages,
                    SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing_messages
                FROM message_logs ml
                INNER JOIN devices d ON ml.device_id = d.id
            ";

            $params = [];

            if ($userId !== null) {
                $sql .= " WHERE d.user_id = ?";
                $params[] = $userId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get message stats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent activities
     */
    public function getRecentActivities($userId = null, $limit = 10)
    {
        try {
            $sql = "
                SELECT 
                    'device_status' as activity_type,
                    d.device_name,
                    d.status,
                    d.updated_at as activity_time,
                    'Device status changed' as description
                FROM devices d
            ";

            $params = [];

            if ($userId !== null) {
                $sql .= " WHERE d.user_id = ?";
                $params[] = $userId;
            }

            $sql .= "
                UNION ALL
                SELECT 
                    'message' as activity_type,
                    d.device_name,
                    ml.direction as status,
                    ml.created_at as activity_time,
                    CONCAT('Message ', ml.direction) as description
                FROM message_logs ml
                INNER JOIN devices d ON ml.device_id = d.id
            ";

            if ($userId !== null) {
                $sql .= " WHERE d.user_id = ?";
                $params[] = $userId;
            }

            $sql .= " ORDER BY activity_time DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get recent activities error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get chart data for dashboard
     */
    public function getChartData($userId = null, $days = 7)
    {
        try {
            $sql = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming,
                    SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing
                FROM message_logs ml
                INNER JOIN devices d ON ml.device_id = d.id
                WHERE ml.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ";

            $params = [$days];

            if ($userId !== null) {
                $sql .= " AND d.user_id = ?";
                $params[] = $userId;
            }

            $sql .= " GROUP BY DATE(created_at) ORDER BY date ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get chart data error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if phone number already exists for user
     */
    private function phoneNumberExists($phoneNumber, $userId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM devices 
            WHERE phone_number = ? AND user_id = ?
        ");
        $stmt->execute([$phoneNumber, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    /**
     * Check if user can add more devices
     */
    private function canAddDevice($userId)
    {
        $maxDevices = $_ENV['MAX_DEVICES_PER_USER'] ?? 10;

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM devices 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] < $maxDevices;
    }

    /**
     * Generate unique device ID
     */
    private function generateDeviceId($phoneNumber)
    {
        $prefix = 'WA_';
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        $phoneHash = substr(md5($phoneNumber), 0, 8);

        return $prefix . $timestamp . '_' . $phoneHash . '_' . $random;
    }

    /**
     * Check if user owns the device
     */
    private function isOwner($deviceId, $userId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM devices 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$deviceId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    /**
     * Get message count for today
     */
    private function getMessageCountToday($deviceId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM message_logs 
            WHERE device_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$deviceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] ?? 0;
    }

    /**
     * Get total message count
     */
    private function getTotalMessages($deviceId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM message_logs 
            WHERE device_id = ?
        ");
        $stmt->execute([$deviceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] ?? 0;
    }

    /**
     * Get last message
     */
    private function getLastMessage($deviceId)
    {
        $stmt = $this->db->prepare("
            SELECT message_content, direction, created_at 
            FROM message_logs 
            WHERE device_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$deviceId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
