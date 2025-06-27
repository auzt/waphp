<?php

/**
 * NodeJS Client Class
 * Handles all communication between PHP and Node.js WhatsApp backend
 * 
 * @author WhatsApp Monitor System
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ApiLogger.php';
require_once __DIR__ . '/Device.php';

class NodeJSClient
{
    private $db;
    private $baseUrl;
    private $apiKey;
    private $timeout;
    private $logger;

    // Command constants
    const CMD_INITIALIZE = 'initialize';
    const CMD_CONNECT = 'connect';
    const CMD_DISCONNECT = 'disconnect';
    const CMD_LOGOUT = 'logout';
    const CMD_SEND_MESSAGE = 'sendMessage';
    const CMD_SEND_IMAGE = 'sendImage';
    const CMD_SEND_DOCUMENT = 'sendDocument';
    const CMD_GET_QR = 'getQR';
    const CMD_GET_STATUS = 'getStatus';
    const CMD_GET_CONTACTS = 'getContacts';
    const CMD_GET_CHATS = 'getChats';
    const CMD_GET_MESSAGES = 'getMessages';
    const CMD_CHECK_NUMBER = 'checkNumber';
    const CMD_GET_PROFILE_PIC = 'getProfilePic';
    const CMD_SET_PROFILE_PIC = 'setProfilePic';
    const CMD_SET_STATUS = 'setStatus';
    const CMD_BLOCK_CONTACT = 'blockContact';
    const CMD_UNBLOCK_CONTACT = 'unblockContact';
    const CMD_DELETE_SESSION = 'deleteSession';

    // Event types from Node.js
    const EVENT_CONNECTION_UPDATE = 'connection.update';
    const EVENT_MESSAGE_RECEIVED = 'message.received';
    const EVENT_MESSAGE_SENT = 'message.sent';
    const EVENT_MESSAGE_STATUS = 'message.status';
    const EVENT_QR_UPDATE = 'qr.update';
    const EVENT_AUTH_FAILURE = 'auth.failure';
    const EVENT_DISCONNECTED = 'disconnected';
    const EVENT_READY = 'ready';

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new ApiLogger();

        // Load configuration
        $settings = $this->loadSettings();
        $this->baseUrl = rtrim($settings['nodejs_url'] ?? 'http://localhost:3000', '/');
        $this->apiKey = $settings['nodejs_api_key'] ?? '';
        $this->timeout = intval($settings['nodejs_timeout'] ?? 30);
    }

    /**
     * Initialize a new WhatsApp session
     */
    public function initializeSession($deviceId, $phoneNumber = null)
    {
        $command = self::CMD_INITIALIZE;
        $data = [
            'deviceId' => $deviceId,
            'phoneNumber' => $phoneNumber,
            'webhookUrl' => $this->getWebhookUrl()
        ];

        return $this->sendCommand($deviceId, $command, $data);
    }

    /**
     * Connect/Reconnect a WhatsApp session
     */
    public function connect($deviceId)
    {
        return $this->sendCommand($deviceId, self::CMD_CONNECT);
    }

    /**
     * Disconnect a WhatsApp session
     */
    public function disconnect($deviceId)
    {
        return $this->sendCommand($deviceId, self::CMD_DISCONNECT);
    }

    /**
     * Logout and destroy session
     */
    public function logout($deviceId)
    {
        $result = $this->sendCommand($deviceId, self::CMD_LOGOUT);

        if ($result['success']) {
            // Update device status in database
            $this->updateDeviceStatus($deviceId, 'logout');
        }

        return $result;
    }

    /**
     * Send text message
     */
    public function sendMessage($deviceId, $to, $message, $options = [])
    {
        $data = array_merge([
            'to' => $this->formatPhoneNumber($to),
            'message' => $message
        ], $options);

        return $this->sendCommand($deviceId, self::CMD_SEND_MESSAGE, $data);
    }

    /**
     * Send image with caption
     */
    public function sendImage($deviceId, $to, $imagePath, $caption = '')
    {
        $data = [
            'to' => $this->formatPhoneNumber($to),
            'image' => $imagePath,
            'caption' => $caption
        ];

        return $this->sendCommand($deviceId, self::CMD_SEND_IMAGE, $data);
    }

    /**
     * Send document
     */
    public function sendDocument($deviceId, $to, $documentPath, $filename = '')
    {
        $data = [
            'to' => $this->formatPhoneNumber($to),
            'document' => $documentPath,
            'filename' => $filename ?: basename($documentPath)
        ];

        return $this->sendCommand($deviceId, self::CMD_SEND_DOCUMENT, $data);
    }

    /**
     * Get QR Code for pairing
     */
    public function getQR($deviceId)
    {
        return $this->sendCommand($deviceId, self::CMD_GET_QR);
    }

    /**
     * Get connection status
     */
    public function getStatus($deviceId)
    {
        return $this->sendCommand($deviceId, self::CMD_GET_STATUS);
    }

    /**
     * Get all contacts
     */
    public function getContacts($deviceId)
    {
        return $this->sendCommand($deviceId, self::CMD_GET_CONTACTS);
    }

    /**
     * Get all chats
     */
    public function getChats($deviceId)
    {
        return $this->sendCommand($deviceId, self::CMD_GET_CHATS);
    }

    /**
     * Get messages from a chat
     */
    public function getMessages($deviceId, $chatId, $limit = 50)
    {
        $data = [
            'chatId' => $chatId,
            'limit' => $limit
        ];

        return $this->sendCommand($deviceId, self::CMD_GET_MESSAGES, $data);
    }

    /**
     * Check if phone number is registered on WhatsApp
     */
    public function checkNumber($deviceId, $phoneNumber)
    {
        $data = [
            'phoneNumber' => $this->formatPhoneNumber($phoneNumber)
        ];

        return $this->sendCommand($deviceId, self::CMD_CHECK_NUMBER, $data);
    }

    /**
     * Get profile picture URL
     */
    public function getProfilePic($deviceId, $phoneNumber)
    {
        $data = [
            'phoneNumber' => $this->formatPhoneNumber($phoneNumber)
        ];

        return $this->sendCommand($deviceId, self::CMD_GET_PROFILE_PIC, $data);
    }

    /**
     * Set profile picture
     */
    public function setProfilePic($deviceId, $imagePath)
    {
        $data = [
            'image' => $imagePath
        ];

        return $this->sendCommand($deviceId, self::CMD_SET_PROFILE_PIC, $data);
    }

    /**
     * Set status/about
     */
    public function setStatus($deviceId, $status)
    {
        $data = [
            'status' => $status
        ];

        return $this->sendCommand($deviceId, self::CMD_SET_STATUS, $data);
    }

    /**
     * Block contact
     */
    public function blockContact($deviceId, $phoneNumber)
    {
        $data = [
            'phoneNumber' => $this->formatPhoneNumber($phoneNumber)
        ];

        return $this->sendCommand($deviceId, self::CMD_BLOCK_CONTACT, $data);
    }

    /**
     * Unblock contact
     */
    public function unblockContact($deviceId, $phoneNumber)
    {
        $data = [
            'phoneNumber' => $this->formatPhoneNumber($phoneNumber)
        ];

        return $this->sendCommand($deviceId, self::CMD_UNBLOCK_CONTACT, $data);
    }

    /**
     * Delete session data
     */
    public function deleteSession($deviceId)
    {
        return $this->sendCommand($deviceId, self::CMD_DELETE_SESSION);
    }

    /**
     * Send command to Node.js
     */
    private function sendCommand($deviceId, $command, $data = [])
    {
        $startTime = microtime(true);

        try {
            // Log command to database
            $commandId = $this->logCommand($deviceId, $command, $data);

            // Prepare request
            $url = $this->baseUrl . '/api/device/' . $deviceId . '/' . $command;
            $headers = [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apiKey,
                'X-Device-ID: ' . $deviceId
            ];

            // Execute request
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $executionTime = microtime(true) - $startTime;

            // Parse response
            if ($error) {
                throw new Exception("CURL Error: " . $error);
            }

            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response: " . $response);
            }

            // Update command log
            $this->updateCommandLog($commandId, 'completed', $responseData, null, $executionTime);

            // Log API call
            $this->logger->logApiCall(
                'nodejs',
                $command,
                $data,
                $httpCode,
                $responseData,
                $executionTime
            );

            return [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'data' => $responseData,
                'httpCode' => $httpCode,
                'executionTime' => $executionTime
            ];
        } catch (Exception $e) {
            // Update command log with error
            if (isset($commandId)) {
                $this->updateCommandLog($commandId, 'failed', null, $e->getMessage());
            }

            // Log error
            $this->logger->logError('nodejs_command_error', [
                'deviceId' => $deviceId,
                'command' => $command,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'httpCode' => 0,
                'executionTime' => microtime(true) - $startTime
            ];
        }
    }

    /**
     * Process webhook from Node.js
     */
    public function processWebhook($event, $data)
    {
        try {
            // Validate webhook data
            if (!isset($data['deviceId'])) {
                throw new Exception("Missing deviceId in webhook data");
            }

            $deviceId = $data['deviceId'];

            // Log webhook
            $this->logWebhook($deviceId, 'incoming', $event, $data);

            // Process based on event type
            switch ($event) {
                case self::EVENT_CONNECTION_UPDATE:
                    $this->handleConnectionUpdate($deviceId, $data);
                    break;

                case self::EVENT_MESSAGE_RECEIVED:
                    $this->handleMessageReceived($deviceId, $data);
                    break;

                case self::EVENT_MESSAGE_SENT:
                    $this->handleMessageSent($deviceId, $data);
                    break;

                case self::EVENT_MESSAGE_STATUS:
                    $this->handleMessageStatus($deviceId, $data);
                    break;

                case self::EVENT_QR_UPDATE:
                    $this->handleQRUpdate($deviceId, $data);
                    break;

                case self::EVENT_AUTH_FAILURE:
                    $this->handleAuthFailure($deviceId, $data);
                    break;

                case self::EVENT_DISCONNECTED:
                    $this->handleDisconnected($deviceId, $data);
                    break;

                case self::EVENT_READY:
                    $this->handleReady($deviceId, $data);
                    break;

                default:
                    $this->logger->logWarning('unknown_webhook_event', [
                        'event' => $event,
                        'deviceId' => $deviceId
                    ]);
            }

            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->logError('webhook_processing_error', [
                'event' => $event,
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle connection update webhook
     */
    private function handleConnectionUpdate($deviceId, $data)
    {
        $status = $data['status'] ?? '';
        $rawStatus = $data['rawStatus'] ?? '';

        // Update device status using stored procedure
        $stmt = $this->db->prepare("CALL UpdateDeviceFromNodeJS(?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $deviceId,
            $status,
            $rawStatus,
            $data['user'] ?? null,
            $data['pushName'] ?? null,
            $data['qr'] ?? null
        ]);

        // Handle specific status updates
        if ($status === 'connected') {
            $this->sendNotification($deviceId, 'Device connected successfully');
        } elseif ($status === 'disconnected') {
            $this->sendNotification($deviceId, 'Device disconnected', 'warning');
        }
    }

    /**
     * Handle message received webhook
     */
    private function handleMessageReceived($deviceId, $data)
    {
        if (!isset($data['message'])) {
            return;
        }

        $message = $data['message'];

        // Log message using stored procedure
        $stmt = $this->db->prepare("CALL LogMessage(?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $deviceId,
            $message['id'] ?? null,
            $message['from'] ?? '',
            'incoming',
            $message['from'] ?? '',
            $message['to'] ?? '',
            $message['type'] ?? 'text',
            $message['body'] ?? '',
            $message['timestamp'] ?? time()
        ]);

        // Trigger any message received hooks
        $this->triggerMessageHooks($deviceId, $message, 'received');
    }

    /**
     * Handle message sent webhook
     */
    private function handleMessageSent($deviceId, $data)
    {
        if (!isset($data['message'])) {
            return;
        }

        $message = $data['message'];

        // Log message
        $stmt = $this->db->prepare("CALL LogMessage(?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $deviceId,
            $message['id'] ?? null,
            $message['to'] ?? '',
            'outgoing',
            $message['from'] ?? '',
            $message['to'] ?? '',
            $message['type'] ?? 'text',
            $message['body'] ?? '',
            $message['timestamp'] ?? time()
        ]);
    }

    /**
     * Handle message status update
     */
    private function handleMessageStatus($deviceId, $data)
    {
        if (!isset($data['messageId']) || !isset($data['status'])) {
            return;
        }

        // Update message status
        $stmt = $this->db->prepare("
            UPDATE message_logs 
            SET status = ?, updated_at = NOW() 
            WHERE message_id = ? AND device_id = (SELECT id FROM devices WHERE device_id = ?)
        ");

        $status = $this->mapMessageStatus($data['status']);
        $stmt->execute([$status, $data['messageId'], $deviceId]);
    }

    /**
     * Handle QR code update
     */
    private function handleQRUpdate($deviceId, $data)
    {
        if (!isset($data['qr'])) {
            return;
        }

        // Update QR code in database
        $stmt = $this->db->prepare("
            UPDATE devices 
            SET qr_code = ?, 
                qr_expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
                status = 'pairing',
                updated_at = NOW()
            WHERE device_id = ?
        ");
        $stmt->execute([$data['qr'], $deviceId]);

        // Notify user about new QR
        $this->sendNotification($deviceId, 'New QR code available for scanning');
    }

    /**
     * Handle auth failure
     */
    private function handleAuthFailure($deviceId, $data)
    {
        // Update device status
        $this->updateDeviceStatus($deviceId, 'auth_failure');

        // Log the failure
        $this->logger->logError('auth_failure', [
            'deviceId' => $deviceId,
            'reason' => $data['reason'] ?? 'Unknown'
        ]);

        // Notify user
        $this->sendNotification($deviceId, 'Authentication failed. Please reconnect.', 'error');
    }

    /**
     * Handle disconnected event
     */
    private function handleDisconnected($deviceId, $data)
    {
        // Update device status
        $this->updateDeviceStatus($deviceId, 'disconnected');

        // Check if we should auto-retry
        $device = new Device();
        $deviceInfo = $device->getDeviceByDeviceId($deviceId);

        if ($deviceInfo && $deviceInfo['retry_count'] < 3) {
            // Schedule reconnection
            $this->scheduleReconnection($deviceId, $deviceInfo['retry_count'] + 1);
        }
    }

    /**
     * Handle ready event
     */
    private function handleReady($deviceId, $data)
    {
        // Update device with full info
        $stmt = $this->db->prepare("
            UPDATE devices 
            SET status = 'connected',
                whatsapp_user_id = ?,
                whatsapp_name = ?,
                is_online = TRUE,
                retry_count = 0,
                connected_at = NOW(),
                updated_at = NOW()
            WHERE device_id = ?
        ");
        $stmt->execute([
            $data['user'] ?? null,
            $data['pushName'] ?? null,
            $deviceId
        ]);

        // Clear any QR codes
        $this->clearQRCode($deviceId);

        // Notify success
        $this->sendNotification($deviceId, 'WhatsApp connected successfully!', 'success');
    }

    /**
     * Schedule device reconnection
     */
    private function scheduleReconnection($deviceId, $retryCount)
    {
        // Log the reconnection attempt
        $stmt = $this->db->prepare("
            INSERT INTO nodejs_commands (device_id, command, command_data, status)
            SELECT id, 'connect', JSON_OBJECT('retry', ?), 'pending'
            FROM devices WHERE device_id = ?
        ");
        $stmt->execute([$retryCount, $deviceId]);

        // Update retry count
        $stmt = $this->db->prepare("
            UPDATE devices 
            SET retry_count = ?, updated_at = NOW()
            WHERE device_id = ?
        ");
        $stmt->execute([$retryCount, $deviceId]);
    }

    /**
     * Log command to database
     */
    private function logCommand($deviceId, $command, $data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO nodejs_commands (device_id, command, command_data, status, executed_by, executed_at)
            SELECT id, ?, ?, 'processing', ?, NOW()
            FROM devices WHERE device_id = ?
        ");

        $userId = $_SESSION['user_id'] ?? null;
        $stmt->execute([$command, json_encode($data), $userId, $deviceId]);

        return $this->db->lastInsertId();
    }

    /**
     * Update command log
     */
    private function updateCommandLog($commandId, $status, $responseData = null, $error = null, $executionTime = null)
    {
        $stmt = $this->db->prepare("
            UPDATE nodejs_commands 
            SET status = ?,
                response_data = ?,
                error_message = ?,
                completed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $responseData ? json_encode($responseData) : null,
            $error,
            $commandId
        ]);
    }

    /**
     * Log webhook
     */
    private function logWebhook($deviceId, $type, $event, $data)
    {
        $stmt = $this->db->prepare("CALL LogWebhook(?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $deviceId,
            $type,
            $event,
            json_encode($data),
            200,
            true,
            null
        ]);
    }

    /**
     * Update device status
     */
    private function updateDeviceStatus($deviceId, $status)
    {
        $stmt = $this->db->prepare("
            UPDATE devices 
            SET status = ?, updated_at = NOW()
            WHERE device_id = ?
        ");
        $stmt->execute([$status, $deviceId]);
    }

    /**
     * Clear QR code
     */
    private function clearQRCode($deviceId)
    {
        $stmt = $this->db->prepare("
            UPDATE devices 
            SET qr_code = NULL, qr_expires_at = NULL
            WHERE device_id = ?
        ");
        $stmt->execute([$deviceId]);
    }

    /**
     * Send notification (implement based on your notification system)
     */
    private function sendNotification($deviceId, $message, $type = 'info')
    {
        // This could send email, push notification, etc.
        // For now, just log it
        $this->logger->logInfo('notification', [
            'deviceId' => $deviceId,
            'message' => $message,
            'type' => $type
        ]);
    }

    /**
     * Trigger message hooks (for future webhook implementations)
     */
    private function triggerMessageHooks($deviceId, $message, $type)
    {
        // This could trigger external webhooks, SMS notifications, etc.
        // Placeholder for future implementation
    }

    /**
     * Format phone number to WhatsApp format
     */
    private function formatPhoneNumber($number)
    {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Add country code if not present (default to Indonesia)
        if (substr($number, 0, 2) !== '62' && substr($number, 0, 1) === '0') {
            $number = '62' . substr($number, 1);
        }

        // Add @s.whatsapp.net suffix if not present
        if (strpos($number, '@') === false) {
            $number .= '@s.whatsapp.net';
        }

        return $number;
    }

    /**
     * Map message status from Node.js to database
     */
    private function mapMessageStatus($nodeStatus)
    {
        $statusMap = [
            'pending' => 'sent',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            'error' => 'failed'
        ];

        return $statusMap[$nodeStatus] ?? 'sent';
    }

    /**
     * Get webhook URL for Node.js callbacks
     */
    private function getWebhookUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/api/webhooks.php';
    }

    /**
     * Load settings from database
     */
    private function loadSettings()
    {
        $stmt = $this->db->prepare("
            SELECT setting_key, setting_value 
            FROM settings 
            WHERE setting_key IN ('nodejs_url', 'nodejs_api_key', 'nodejs_timeout', 'webhook_secret')
        ");
        $stmt->execute();

        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Test Node.js connection
     */
    public function testConnection()
    {
        try {
            $ch = curl_init($this->baseUrl . '/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [
                'success' => $httpCode === 200,
                'response' => json_decode($response, true),
                'httpCode' => $httpCode
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all pending commands for processing
     */
    public function getPendingCommands($limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT c.*, d.device_id 
            FROM nodejs_commands c
            JOIN devices d ON c.device_id = d.id
            WHERE c.status = 'pending'
            ORDER BY c.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Process pending commands (for cron job)
     */
    public function processPendingCommands()
    {
        $commands = $this->getPendingCommands();
        $results = [];

        foreach ($commands as $cmd) {
            $data = json_decode($cmd['command_data'], true) ?: [];
            $result = $this->sendCommand($cmd['device_id'], $cmd['command'], $data);

            $results[] = [
                'commandId' => $cmd['id'],
                'deviceId' => $cmd['device_id'],
                'command' => $cmd['command'],
                'success' => $result['success']
            ];
        }

        return $results;
    }

    /**
     * Bulk send message to multiple recipients
     */
    public function bulkSendMessage($deviceId, $recipients, $message, $delay = 1000)
    {
        $results = [];

        foreach ($recipients as $recipient) {
            $result = $this->sendMessage($deviceId, $recipient, $message);
            $results[] = [
                'recipient' => $recipient,
                'success' => $result['success'],
                'error' => $result['error'] ?? null
            ];

            // Delay between messages to avoid spam detection
            usleep($delay * 1000); // Convert to microseconds
        }

        return $results;
    }

    /**
     * Get Node.js system status
     */
    public function getSystemStatus()
    {
        try {
            $ch = curl_init($this->baseUrl . '/api/system/status');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $this->apiKey]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return json_decode($response, true);
            }

            return null;
        } catch (Exception $e) {
            $this->logger->logError('system_status_error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
