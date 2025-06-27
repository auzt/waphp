<?php

/**
 * ===============================================================================
 * DEVICES API CONTROLLER
 * ===============================================================================
 * API endpoints untuk manajemen devices WhatsApp
 * - CRUD operations
 * - Connection management
 * - Status monitoring
 * - QR code generation
 * ===============================================================================
 */

require_once APP_ROOT . '/classes/Device.php';
require_once APP_ROOT . '/classes/NodeJSClient.php';

/**
 * List devices for the authenticated user
 */
function devices_index($id, $data, $device)
{
    try {
        $deviceClass = new Device();

        // Get filter parameters
        $filters = [
            'status' => $data['status'] ?? null,
            'search' => $data['search'] ?? null,
            'limit' => min(intval($data['limit'] ?? 50), 100), // Max 100
            'offset' => intval($data['offset'] ?? 0),
            'order_by' => $data['order_by'] ?? 'created_at',
            'order_dir' => strtoupper($data['order_dir'] ?? 'DESC')
        ];

        // Validate order direction
        if (!in_array($filters['order_dir'], ['ASC', 'DESC'])) {
            $filters['order_dir'] = 'DESC';
        }

        // Get devices for the user
        $devices = $deviceClass->getByUser($device['user_id'], $filters);

        // Get total count for pagination
        $totalDevices = count($deviceClass->getByUser($device['user_id']));

        return [
            'devices' => $devices,
            'pagination' => [
                'total' => $totalDevices,
                'limit' => $filters['limit'],
                'offset' => $filters['offset'],
                'has_more' => ($filters['offset'] + $filters['limit']) < $totalDevices
            ],
            'filters' => $filters
        ];
    } catch (Exception $e) {
        throw new Exception('Failed to retrieve devices: ' . $e->getMessage(), 500);
    }
}

/**
 * Get device details
 */
function devices_show($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();
        $deviceDetails = $deviceClass->getById($id, $device['user_id']);

        if (!$deviceDetails) {
            throw new Exception('Device not found', 404);
        }

        // Get additional statistics
        $stats = [
            'messages_today' => $deviceDetails['message_count_today'] ?? 0,
            'total_messages' => $deviceDetails['total_messages'] ?? 0,
            'last_message' => $deviceDetails['last_message'] ?? null,
            'uptime' => $deviceDetails['connected_at'] ?
                time() - strtotime($deviceDetails['connected_at']) : 0
        ];

        return [
            'device' => $deviceDetails,
            'statistics' => $stats
        ];
    } catch (Exception $e) {
        if ($e->getCode() === 404) {
            throw $e;
        }
        throw new Exception('Failed to retrieve device: ' . $e->getMessage(), 500);
    }
}

/**
 * Create new device
 */
function devices_create($id, $data, $device)
{
    try {
        // Validate required fields
        $required = ['device_name', 'phone_number'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '{$field}' is required", 400);
            }
        }

        $deviceClass = new Device();

        // Prepare device data
        $deviceData = [
            'user_id' => $device['user_id'],
            'device_name' => trim($data['device_name']),
            'phone_number' => trim($data['phone_number'])
        ];

        // Create device
        $result = $deviceClass->create($deviceData);

        if (!$result['success']) {
            throw new Exception($result['message'], 400);
        }

        return [
            'message' => 'Device created successfully',
            'device_id' => $result['device_id'],
            'device_key' => $result['device_key']
        ];
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            throw $e;
        }
        throw new Exception('Failed to create device: ' . $e->getMessage(), 500);
    }
}

/**
 * Update device
 */
function devices_update($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();

        // Update device
        $result = $deviceClass->update($id, $data, $device['user_id']);

        if (!$result['success']) {
            throw new Exception($result['message'], 400);
        }

        return [
            'message' => 'Device updated successfully'
        ];
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            throw $e;
        }
        throw new Exception('Failed to update device: ' . $e->getMessage(), 500);
    }
}

/**
 * Delete device
 */
function devices_delete($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();

        // Delete device
        $result = $deviceClass->delete($id, $device['user_id']);

        if (!$result['success']) {
            throw new Exception($result['message'], 400);
        }

        return [
            'message' => 'Device deleted successfully'
        ];
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            throw $e;
        }
        throw new Exception('Failed to delete device: ' . $e->getMessage(), 500);
    }
}

/**
 * Connect device to WhatsApp
 */
function devices_connect($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();

        // Connect device
        $result = $deviceClass->connect($id, $device['user_id']);

        if (!$result['success']) {
            throw new Exception($result['message'], 400);
        }

        return [
            'message' => 'Connection initiated successfully',
            'status' => 'connecting'
        ];
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            throw $e;
        }
        throw new Exception('Failed to connect device: ' . $e->getMessage(), 500);
    }
}

/**
 * Disconnect device from WhatsApp
 */
function devices_disconnect($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();

        // Disconnect device
        $result = $deviceClass->disconnect($id, $device['user_id']);

        if (!$result['success']) {
            throw new Exception($result['message'], 400);
        }

        return [
            'message' => 'Device disconnected successfully',
            'status' => 'disconnected'
        ];
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            throw $e;
        }
        throw new Exception('Failed to disconnect device: ' . $e->getMessage(), 500);
    }
}

/**
 * Get QR code for device pairing
 */
function devices_qr($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();

        // Get QR code
        $result = $deviceClass->getQRCode($id, $device['user_id']);

        if (!$result['success']) {
            throw new Exception($result['message'], 400);
        }

        return [
            'qr_code' => $result['qr_code'],
            'expires_at' => $result['expires_at'],
            'format' => 'base64',
            'instructions' => 'Scan this QR code with your WhatsApp mobile app'
        ];
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            throw $e;
        }
        throw new Exception('Failed to get QR code: ' . $e->getMessage(), 500);
    }
}

/**
 * Get device status
 */
function devices_status($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();
        $deviceDetails = $deviceClass->getById($id, $device['user_id']);

        if (!$deviceDetails) {
            throw new Exception('Device not found', 404);
        }

        // Get real-time status from Node.js
        $nodeClient = new NodeJSClient();
        $nodeStatus = $nodeClient->getStatus($deviceDetails['device_id']);

        $status = [
            'device_id' => $deviceDetails['id'],
            'device_name' => $deviceDetails['device_name'],
            'phone_number' => $deviceDetails['phone_number'],
            'status' => $deviceDetails['status'],
            'raw_status' => $deviceDetails['raw_status'],
            'is_online' => (bool)$deviceDetails['is_online'],
            'last_seen' => $deviceDetails['last_seen'],
            'connected_at' => $deviceDetails['connected_at'],
            'whatsapp_user_id' => $deviceDetails['whatsapp_user_id'],
            'whatsapp_name' => $deviceDetails['whatsapp_name'],
            'node_status' => $nodeStatus['success'] ? $nodeStatus['data'] : null
        ];

        return $status;
    } catch (Exception $e) {
        if ($e->getCode() === 404) {
            throw $e;
        }
        throw new Exception('Failed to get device status: ' . $e->getMessage(), 500);
    }
}

/**
 * Restart device connection
 */
function devices_restart($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();

        // First disconnect, then connect
        $disconnectResult = $deviceClass->disconnect($id, $device['user_id']);
        if (!$disconnectResult['success']) {
            throw new Exception('Failed to disconnect: ' . $disconnectResult['message'], 400);
        }

        // Wait a moment
        sleep(2);

        // Then connect
        $connectResult = $deviceClass->connect($id, $device['user_id']);
        if (!$connectResult['success']) {
            throw new Exception('Failed to reconnect: ' . $connectResult['message'], 400);
        }

        return [
            'message' => 'Device restart initiated successfully',
            'status' => 'restarting'
        ];
    } catch (Exception $e) {
        if ($e->getCode() === 400) {
            throw $e;
        }
        throw new Exception('Failed to restart device: ' . $e->getMessage(), 500);
    }
}

/**
 * Get device statistics
 */
function devices_stats($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();
        $deviceDetails = $deviceClass->getById($id, $device['user_id']);

        if (!$deviceDetails) {
            throw new Exception('Device not found', 404);
        }

        // Get chart data for last 7 days
        $chartData = $deviceClass->getChartData($device['user_id'], 7);

        // Filter chart data for this specific device
        $deviceChartData = array_filter($chartData, function ($item) use ($id) {
            return $item['device_id'] == $id;
        });

        $stats = [
            'device_id' => $id,
            'messages_today' => $deviceDetails['message_count_today'] ?? 0,
            'total_messages' => $deviceDetails['total_messages'] ?? 0,
            'uptime_seconds' => $deviceDetails['connected_at'] ?
                time() - strtotime($deviceDetails['connected_at']) : 0,
            'last_activity' => $deviceDetails['last_seen'],
            'chart_data' => array_values($deviceChartData)
        ];

        return $stats;
    } catch (Exception $e) {
        if ($e->getCode() === 404) {
            throw $e;
        }
        throw new Exception('Failed to get device statistics: ' . $e->getMessage(), 500);
    }
}

/**
 * Clear device QR code
 */
function devices_clear_qr($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $db = Database::getInstance()->getConnection();

        // Verify device ownership
        $stmt = $db->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $device['user_id']]);

        if (!$stmt->fetch()) {
            throw new Exception('Device not found', 404);
        }

        // Clear QR code
        $stmt = $db->prepare("
            UPDATE devices 
            SET qr_code = NULL, qr_expires_at = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        return [
            'message' => 'QR code cleared successfully'
        ];
    } catch (Exception $e) {
        if ($e->getCode() === 404) {
            throw $e;
        }
        throw new Exception('Failed to clear QR code: ' . $e->getMessage(), 500);
    }
}

/**
 * Test device connection to Node.js
 */
function devices_test($id, $data, $device)
{
    if (!$id) {
        throw new Exception('Device ID required', 400);
    }

    try {
        $deviceClass = new Device();
        $deviceDetails = $deviceClass->getById($id, $device['user_id']);

        if (!$deviceDetails) {
            throw new Exception('Device not found', 404);
        }

        // Test connection to Node.js
        $nodeClient = new NodeJSClient();
        $testResult = $nodeClient->testConnection();

        $result = [
            'device_id' => $id,
            'nodejs_connection' => $testResult,
            'device_status' => $deviceDetails['status'],
            'last_seen' => $deviceDetails['last_seen'],
            'test_timestamp' => date('Y-m-d H:i:s')
        ];

        return $result;
    } catch (Exception $e) {
        if ($e->getCode() === 404) {
            throw $e;
        }
        throw new Exception('Failed to test device connection: ' . $e->getMessage(), 500);
    }
}
