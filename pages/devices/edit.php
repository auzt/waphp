<?php
/**
 * ===============================================================================
 * DELETE DEVICE - Device Deletion Handler
 * ===============================================================================
 * Handler untuk menghapus device WhatsApp
 * - Konfirmasi penghapusan device
 * - Cleanup data terkait (messages, logs, tokens)
 * - Disconnect dari Node.js backend
 * - Logging aktivitas
 * ===============================================================================
 */

// Include required files
require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Device.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Get device ID from URL/POST
$device_id = $_GET['id'] ?? $_POST['device_id'] ?? null;

if (!$device_id || !is_numeric($device_id)) {
    $_SESSION['error_message'] = 'Invalid device ID';
    header('Location: index.php');
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize classes
$device = new Device($db);
$currentUser = getCurrentUser();

// Get device data for confirmation
try {
    $deviceData = $device->getById($device_id, $currentUser['id']);
    
    if (!$deviceData) {
        $_SESSION['error_message'] = 'Device not found or access denied';
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Delete device error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error loading device data';
    header('Location: index.php');
    exit;
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Verify the confirmation token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid security token');
        }
        
        // Verify device name confirmation
        if ($_POST['device_name_confirm'] !== $deviceData['device_name']) {
            $error_message = 'Device name confirmation does not match. Please type the exact device name.';
        } else {
            // Start database transaction
            $db->beginTransaction();
            
            try {
                // 1. Disconnect from Node.js backend first
                if ($deviceData['status'] !== 'disconnected') {
                    $device->disconnectFromNodeJS($device_id);
                    
                    // Give time for disconnection
                    sleep(1);
                }
                
                // 2. Get statistics before deletion (for logging)
                $deleteStats = $device->getDelete