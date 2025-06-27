<?php

/**
 * ===============================================================================
 * DEVICE VIEW - Device Details & Monitoring Page
 * ===============================================================================
 * Halaman detail device dengan monitoring real-time
 * - Device status dan informasi lengkap
 * - QR Code untuk pairing
 * - Message statistics dan logs
 * - Device control actions
 * - Real-time status updates
 * ===============================================================================
 */

// Include required files
require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Device.php';
require_once '../../classes/ApiLogger.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Get device ID from URL
$device_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

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
$apiLogger = new ApiLogger($db);
$currentUser = getCurrentUser();

// Get device data
try {
    $deviceData = $device->getDetailById($device_id, $currentUser['id']);

    if (!$deviceData) {
        $_SESSION['error_message'] = 'Device not found or access denied';
        header('Location: index.php');
        exit;
    }

    // Get additional data
    $messageStats = $device->getMessageStatsByDevice($device_id);
    $recentMessages = $device->getRecentMessages($device_id, 10);
    $apiStats = $apiLogger->getDeviceApiStats($device_id);
    $deviceLogs = $device->getDeviceLogs($device_id, 20);
} catch (Exception $e) {
    error_log("View device error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error loading device data';
    header('Location: index.php');
    exit;
}

// Handle actions
$actionMessage = '';
$actionType = '';

if ($action && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        switch ($action) {
            case 'connect':
                $result = $device->connectToNodeJS($device_id);
                if ($result['success']) {
                    $actionMessage = 'Device connection initiated successfully';
                    $actionType = 'success';
                    logActivity(
                        $currentUser['id'],
                        'device_connect_initiated',
                        "Initiated connection for device: {$deviceData['device_name']}",
                        $device_id
                    );
                } else {
                    $actionMessage = 'Failed to connect device: ' . $result['message'];
                    $actionType = 'error';
                }
                break;

            case 'disconnect':
                $result = $device->disconnectFromNodeJS($device_id);
                if ($result['success']) {
                    $actionMessage = 'Device disconnected successfully';
                    $actionType = 'success';
                    logActivity(
                        $currentUser['id'],
                        'device_disconnect',
                        "Disconnected device: {$deviceData['device_name']}",
                        $device_id
                    );
                } else {
                    $actionMessage = 'Failed to disconnect device: ' . $result['message'];
                    $actionType = 'error';
                }
                break;

            case 'restart':
                $result = $device->restartNodeJSConnection($device_id);
                if ($result['success']) {
                    $actionMessage = 'Device restart initiated successfully';
                    $actionType = 'success';
                    logActivity(
                        $currentUser['id'],
                        'device_restart',
                        "Restarted device: {$deviceData['device_name']}",
                        $device_id
                    );
                } else {
                    $actionMessage = 'Failed to restart device: ' . $result['message'];
                    $actionType = 'error';
                }
                break;
        }

        // Refresh device data after action
        if ($actionType === 'success') {
            sleep(1); // Give time for action to process
            $deviceData = $device->getDetailById($device_id, $currentUser['id']);
        }
    } catch (Exception $e) {
        error_log("Device action error: " . $e->getMessage());
        $actionMessage = 'Error performing action: ' . $e->getMessage();
        $actionType = 'error';
    }
}

// Page settings
$pageTitle = 'Device: ' . htmlspecialchars($deviceData['device_name']);
$currentPage = 'devices';

// Helper functions
function getStatusClass($status)
{
    $classes = [
        'connected' => 'success',
        'connecting' => 'warning',
        'disconnected' => 'secondary',
        'pairing' => 'info',
        'banned' => 'danger',
        'error' => 'danger',
        'timeout' => 'warning',
        'auth_failure' => 'danger',
        'logout' => 'secondary'
    ];

    return $classes[$status] ?? 'secondary';
}

function getStatusText($status)
{
    $texts = [
        'connected' => 'Connected',
        'connecting' => 'Connecting',
        'disconnected' => 'Disconnected',
        'pairing' => 'Pairing',
        'banned' => 'Banned',
        'error' => 'Error',
        'timeout' => 'Timeout',
        'auth_failure' => 'Auth Failed',
        'logout' => 'Logged Out'
    ];

    return $texts[$status] ?? 'Unknown';
}

function getStatusIcon($status)
{
    $icons = [
        'connected' => 'fa-check-circle',
        'connecting' => 'fa-spinner fa-spin',
        'disconnected' => 'fa-times-circle',
        'pairing' => 'fa-qrcode',
        'banned' => 'fa-ban',
        'error' => 'fa-exclamation-triangle',
        'timeout' => 'fa-clock',
        'auth_failure' => 'fa-key',
        'logout' => 'fa-sign-out-alt'
    ];

    return $icons[$status] ?? 'fa-question-circle';
}

function renderDeviceActions($deviceData, $verbose = false)
{
    $actions = [];

    switch ($deviceData['status']) {
        case 'disconnected':
        case 'error':
        case 'timeout':
        case 'auth_failure':
            $text = $verbose ? ' Connect' : '';
            $actions[] = "<a href='?id={$deviceData['id']}&action=connect' class='btn btn-success'>
                           <i class='fas fa-play'></i>{$text}
                         </a>";
            break;

        case 'connected':
            $text = $verbose ? ' Disconnect' : '';
            $actions[] = "<a href='?id={$deviceData['id']}&action=disconnect' class='btn btn-warning'>
                           <i class='fas fa-stop'></i>{$text}
                         </a>";
            break;

        case 'connecting':
            $text = $verbose ? ' Connecting...' : '';
            $actions[] = "<button class='btn btn-secondary' disabled>
                           <i class='fas fa-spinner fa-spin'></i>{$text}
                         </button>";
            break;

        case 'pairing':
            $text = $verbose ? ' Refresh QR' : '';
            $actions[] = "<button class='btn btn-info' onclick='refreshQRCode()'>
                           <i class='fas fa-qrcode'></i>{$text}
                         </button>";
            break;
    }

    if ($verbose) {
        $actions[] = "<a href='?id={$deviceData['id']}&action=restart' class='btn btn-secondary'>
                       <i class='fas fa-redo'></i> Restart
                     </a>";
    }

    return implode(' ', $actions);
}

function timeAgo($datetime)
{
    if (!$datetime) return 'Never';

    $time = time() - strtotime($datetime);

    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time / 60) . ' minutes ago';
    if ($time < 86400) return floor($time / 3600) . ' hours ago';
    if ($time < 604800) return floor($time / 86400) . ' days ago';

    return date('M d, Y', strtotime($datetime));
}

function formatPhoneNumber($phone)
{
    if (empty($phone)) return $phone;

    // Remove + and country code, then format
    $cleaned = preg_replace('/^\+?62/', '', $phone);
    if (strlen($cleaned) >= 10) {
        return '+62 ' . substr($cleaned, 0, 3) . ' ' . substr($cleaned, 3, 4) . ' ' . substr($cleaned, 7);
    }
    return '+62 ' . $cleaned;
}

function getLogLevel($level)
{
    $levels = [
        'success' => 'success',
        'info' => 'info',
        'warning' => 'warning',
        'error' => 'error'
    ];
    return $levels[$level] ?? 'secondary';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $pageTitle; ?> | WhatsApp Monitor</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <!-- Chart.js -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/chart.js/Chart.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../../assets/adminlte/dist/css/adminlte.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/custom/css/custom.css">

    <style>
        .device-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-online {
            background-color: #28a745;
        }

        .status-offline {
            background-color: #6c757d;
        }

        .status-connecting {
            background-color: #ffc107;
            animation: pulse 2s infinite;
        }

        .status-error {
            background-color: #dc3545;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        .metric-card {
            transition: transform 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
        }

        .qr-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .message-item {
            border-left: 3px solid transparent;
            padding: 10px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }

        .message-item:hover {
            background-color: #f8f9fa;
            border-left-color: #007bff;
        }

        .log-item {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            padding: 8px 12px;
            border-left: 3px solid #dee2e6;
            margin-bottom: 8px;
            background: #f8f9fa;
        }

        .log-item.success {
            border-left-color: #28a745;
        }

        .log-item.warning {
            border-left-color: #ffc107;
        }

        .log-item.error {
            border-left-color: #dc3545;
        }

        .log-item.info {
            border-left-color: #17a2b8;
        }

        .auto-refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            display: none;
        }

        @media (max-width: 768px) {
            .device-header {
                padding: 20px;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <!-- Auto refresh indicator -->
        <div class="auto-refresh-indicator" id="refresh-indicator">
            <i class="fas fa-sync-alt fa-spin"></i> Refreshing...
        </div>

        <!-- Navbar -->
        <?php include '../../includes/navbar.php'; ?>

        <!-- Main Sidebar Container -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Device Details</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Devices</a></li>
                                <li class="breadcrumb-item active"><?php echo htmlspecialchars($deviceData['device_name']); ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <!-- Action Result Alert -->
                    <?php if ($actionMessage): ?>
                        <div class="alert alert-<?php echo $actionType === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-<?php echo $actionType === 'success' ? 'check' : 'ban'; ?>"></i>
                                <?php echo $actionType === 'success' ? 'Success!' : 'Error!'; ?>
                            </h5>
                            <?php echo htmlspecialchars($actionMessage); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Device Header -->
                    <div class="device-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-3">
                                    <i class="fas fa-mobile-alt me-3"></i>
                                    <?php echo htmlspecialchars($deviceData['device_name']); ?>
                                </h2>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <p class="mb-1">
                                            <i class="fas fa-phone me-2"></i>
                                            <strong>Phone:</strong> +<?php echo htmlspecialchars($deviceData['phone_number']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-id-card me-2"></i>
                                            <strong>Device ID:</strong> <?php echo htmlspecialchars($deviceData['device_id']); ?>
                                        </p>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="mb-1">
                                            <i class="fas fa-user me-2"></i>
                                            <strong>Owner:</strong> <?php echo htmlspecialchars($deviceData['owner'] ?? 'Unknown'); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-calendar me-2"></i>
                                            <strong>Created:</strong> <?php echo date('M d, Y', strtotime($deviceData['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="device-status mb-3">
                                    <?php
                                    $statusClass = getStatusClass($deviceData['status']);
                                    $statusText = getStatusText($deviceData['status']);
                                    $statusIcon = getStatusIcon($deviceData['status']);
                                    ?>
                                    <span class="status-indicator status-<?php echo $deviceData['is_online'] ? 'online' : 'offline'; ?>"></span>
                                    <span class="badge badge-<?php echo $statusClass; ?> badge-lg">
                                        <i class="fas <?php echo $statusIcon; ?>"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                </div>
                                <div class="device-actions">
                                    <?php echo renderDeviceActions($deviceData); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Row -->
                    <div class="row">
                        <!-- Messages Today -->
                        <div class="col-lg-3 col-6">
                            <div class="info-box metric-card">
                                <span class="info-box-icon bg-info elevation-1">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Messages Today</span>
                                    <span class="info-box-number"><?php echo number_format($messageStats['today'] ?? 0); ?></span>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" style="width: 70%"></div>
                                    </div>
                                    <span class="progress-description">
                                        <?php echo number_format($messageStats['today_incoming'] ?? 0); ?> in,
                                        <?php echo number_format($messageStats['today_outgoing'] ?? 0); ?> out
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Messages -->
                        <div class="col-lg-3 col-6">
                            <div class="info-box metric-card">
                                <span class="info-box-icon bg-success elevation-1">
                                    <i class="fas fa-chart-line"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Messages</span>
                                    <span class="info-box-number"><?php echo number_format($messageStats['total'] ?? 0); ?></span>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: 100%"></div>
                                    </div>
                                    <span class="progress-description">
                                        All time messages
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- API Calls -->
                        <div class="col-lg-3 col-6">
                            <div class="info-box metric-card">
                                <span class="info-box-icon bg-warning elevation-1">
                                    <i class="fas fa-code"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">API Calls</span>
                                    <span class="info-box-number"><?php echo number_format($apiStats['total_calls'] ?? 0); ?></span>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning"
                                            style="width: <?php echo $apiStats['total_calls'] > 0 ? ($apiStats['success_calls'] / $apiStats['total_calls'] * 100) : 0; ?>%"></div>
                                    </div>
                                    <span class="progress-description">
                                        <?php
                                        $successRate = $apiStats['total_calls'] > 0 ?
                                            round(($apiStats['success_calls'] / $apiStats['total_calls']) * 100, 1) : 0;
                                        echo $successRate;
                                        ?>% success rate
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Uptime -->
                        <div class="col-lg-3 col-6">
                            <div class="info-box metric-card">
                                <span class="info-box-icon bg-danger elevation-1">
                                    <i class="fas fa-clock"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Last Seen</span>
                                    <span class="info-box-number" style="font-size: 1rem;">
                                        <?php echo $deviceData['last_seen'] ? timeAgo($deviceData['last_seen']) : 'Never'; ?>
                                    </span>
                                    <div class="progress">
                                        <div class="progress-bar bg-danger"
                                            style="width: <?php echo $deviceData['is_online'] ? 100 : 20; ?>%"></div>
                                    </div>
                                    <span class="progress-description">
                                        <?php echo $deviceData['is_online'] ? 'Currently online' : 'Offline'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Row -->
                    <div class="row">
                        <!-- QR Code & Device Info -->
                        <div class="col-md-6">
                            <?php if ($deviceData['status'] === 'pairing'): ?>
                                <!-- QR Code Card -->
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-qrcode"></i>
                                            QR Code Scanner
                                        </h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-sm btn-primary" onclick="refreshQRCode()">
                                                <i class="fas fa-sync-alt"></i> Refresh
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="qr-container" id="qr-container">
                                            <div class="text-center py-4">
                                                <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                                                <p>Loading QR Code...</p>
                                            </div>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <h6>How to connect:</h6>
                                            <ol class="text-left">
                                                <li>Open WhatsApp on your phone</li>
                                                <li>Go to Settings → Linked Devices</li>
                                                <li>Tap "Link a device"</li>
                                                <li>Scan the QR code above</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Device Information -->
                            <div class="card card-info">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-info-circle"></i>
                                        Device Information
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Device Name:</strong></td>
                                            <td><?php echo htmlspecialchars($deviceData['device_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Phone Number:</strong></td>
                                            <td>+<?php echo htmlspecialchars($deviceData['phone_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Device ID:</strong></td>
                                            <td>
                                                <code><?php echo htmlspecialchars($deviceData['device_id']); ?></code>
                                                <button class="btn btn-sm btn-outline-secondary ml-2" onclick="copyText('<?php echo $deviceData['device_id']; ?>')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>WhatsApp Name:</strong></td>
                                            <td><?php echo htmlspecialchars($deviceData['whatsapp_name'] ?: 'Not set'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>WhatsApp JID:</strong></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($deviceData['whatsapp_user_id'] ?: 'Not connected'); ?>
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                <span class="badge badge-<?php echo $statusClass; ?>">
                                                    <i class="fas <?php echo $statusIcon; ?>"></i>
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Online Status:</strong></td>
                                            <td>
                                                <span class="status-indicator status-<?php echo $deviceData['is_online'] ? 'online' : 'offline'; ?>"></span>
                                                <?php echo $deviceData['is_online'] ? 'Online' : 'Offline'; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Retry Count:</strong></td>
                                            <td>
                                                <span class="badge badge-warning"><?php echo $deviceData['retry_count'] ?? 0; ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Last Seen:</strong></td>
                                            <td><?php echo $deviceData['last_seen'] ? timeAgo($deviceData['last_seen']) : 'Never'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Connected At:</strong></td>
                                            <td><?php echo $deviceData['connected_at'] ? date('M d, Y H:i', strtotime($deviceData['connected_at'])) : 'Never'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Created:</strong></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($deviceData['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Updated:</strong></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($deviceData['updated_at'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- API Token -->
                            <div class="card card-warning">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-key"></i>
                                        API Token
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="api-token"
                                            value="<?php echo htmlspecialchars($deviceData['token'] ?? 'N/A'); ?>" readonly>
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="toggleTokenVisibility()">
                                                <i class="fas fa-eye" id="token-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" type="button" onclick="copyToken()">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-2 d-block">
                                        Use this token for API authentication. Keep it secure!
                                    </small>

                                    <?php if ($deviceData['token']): ?>
                                        <div class="mt-3">
                                            <strong>Token Usage:</strong>
                                            <ul class="list-unstyled mb-0 mt-2">
                                                <li><i class="fas fa-check text-success"></i> Total Calls: <?php echo number_format($apiStats['total_calls'] ?? 0); ?></li>
                                                <li><i class="fas fa-check text-success"></i> Success: <?php echo number_format($apiStats['success_calls'] ?? 0); ?></li>
                                                <li><i class="fas fa-times text-danger"></i> Errors: <?php echo number_format($apiStats['error_calls'] ?? 0); ?></li>
                                                <li><i class="fas fa-clock text-info"></i> Last Used: <?php echo $apiStats['last_used'] ? timeAgo($apiStats['last_used']) : 'Never'; ?></li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Messages & Logs -->
                        <div class="col-md-6">
                            <!-- Message Statistics Chart -->
                            <div class="card card-success">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-chart-area"></i>
                                        Message Activity (Last 7 Days)
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="messageChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Messages -->
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-comments"></i>
                                        Recent Messages
                                    </h3>
                                    <div class="card-tools">
                                        <span class="badge badge-primary"><?php echo count($recentMessages); ?></span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div style="max-height: 300px; overflow-y: auto;">
                                        <?php if (!empty($recentMessages)): ?>
                                            <?php foreach ($recentMessages as $msg): ?>
                                                <div class="message-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex align-items-center mb-1">
                                                                <i class="fas fa-<?php echo $msg['direction'] === 'incoming' ? 'arrow-down text-success' : 'arrow-up text-primary'; ?> me-2"></i>
                                                                <strong><?php echo $msg['direction'] === 'incoming' ? 'From' : 'To'; ?>:</strong>
                                                                <span class="ml-2"><?php echo formatPhoneNumber($msg['direction'] === 'incoming' ? $msg['from_number'] : $msg['to_number']); ?></span>
                                                            </div>
                                                            <div class="message-content">
                                                                <span class="badge badge-info badge-sm"><?php echo ucfirst($msg['message_type']); ?></span>
                                                                <?php if ($msg['message_type'] === 'text' && $msg['message_content']): ?>
                                                                    <p class="mb-1 text-truncate" style="max-width: 300px;">
                                                                        <?php echo htmlspecialchars($msg['message_content']); ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted ml-2">
                                                            <?php echo timeAgo(date('Y-m-d H:i:s', $msg['timestamp'] / 1000)); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                <p class="text-muted">No recent messages</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <a href="../logs/message-logs.php?device_id=<?php echo $device_id; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-list"></i> View All Messages
                                    </a>
                                </div>
                            </div>

                            <!-- Device Logs -->
                            <div class="card card-secondary">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-file-alt"></i>
                                        Device Logs
                                    </h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="refreshLogs()">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div id="device-logs" style="max-height: 300px; overflow-y: auto; padding: 15px;">
                                        <?php if (!empty($deviceLogs)): ?>
                                            <?php foreach ($deviceLogs as $log): ?>
                                                <div class="log-item <?php echo getLogLevel($log['log_level']); ?>">
                                                    <div class="d-flex justify-content-between">
                                                        <span>[<?php echo date('H:i:s', strtotime($log['created_at'])); ?>]</span>
                                                        <span class="badge badge-<?php echo getLogLevel($log['log_level']); ?> badge-sm">
                                                            <?php echo strtoupper($log['log_level']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="mt-1">
                                                        <?php echo htmlspecialchars($log['message']); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-file fa-2x text-muted mb-2"></i>
                                                <p class="text-muted">No logs available</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="btn-group mr-2" role="group">
                                        <a href="edit.php?id=<?php echo $device_id; ?>" class="btn btn-warning">
                                            <i class="fas fa-edit"></i> Edit Device
                                        </a>
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-list"></i> Back to List
                                        </a>
                                    </div>

                                    <div class="btn-group mr-2" role="group">
                                        <?php echo renderDeviceActions($deviceData, true); ?>
                                    </div>

                                    <div class="btn-group" role="group">
                                        <button class="btn btn-info" onclick="exportDeviceData()">
                                            <i class="fas fa-download"></i> Export Data
                                        </button>
                                        <button class="btn btn-danger" onclick="deleteDevice()">
                                            <i class="fas fa-trash"></i> Delete Device
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- jQuery -->
    <script src="../../assets/adminlte/plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="../../assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="../../assets/adminlte/plugins/chart.js/Chart.min.js"></script>
    <!-- AdminLTE App -->
    <script src="../../assets/adminlte/dist/js/adminlte.min.js"></script>

    <script>
        $(document).ready(function() {
            'use strict';

            // Initialize message chart
            initializeMessageChart();

            // Load QR code if in pairing mode
            <?php if ($deviceData['status'] === 'pairing'): ?>
                loadQRCode();
            <?php endif; ?>

            // Auto refresh every 30 seconds
            setInterval(function() {
                refreshDeviceStatus();
            }, 30000);

            // Real-time updates via WebSocket (if available)
            if (typeof WebSocket !== 'undefined') {
                initializeWebSocket();
            }
        });

        // Initialize message chart
        function initializeMessageChart() {
            const ctx = document.getElementById('messageChart').getContext('2d');

            // Get chart data via AJAX
            $.ajax({
                url: '../../api/devices.php',
                method: 'GET',
                data: {
                    action: 'chart_data',
                    device_id: <?php echo $device_id; ?>,
                    days: 7
                },
                success: function(response) {
                    if (response.success) {
                        renderMessageChart(ctx, response.data);
                    }
                },
                error: function() {
                    console.log('Failed to load chart data');
                }
            });
        }

        function renderMessageChart(ctx, data) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Incoming Messages',
                        data: data.incoming || [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Outgoing Messages',
                        data: data.outgoing || [],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }

        // Load QR Code
        function loadQRCode() {
            $.ajax({
                url: '../../api/devices.php',
                method: 'GET',
                data: {
                    action: 'qr',
                    device_id: <?php echo $device_id; ?>
                },
                success: function(response) {
                    if (response.success && response.data.qr_code) {
                        $('#qr-container').html(`
                    <img src="${response.data.qr_code}" class="img-fluid" style="max-width: 250px;">
                    <p class="mt-3 text-muted">Scan this QR code with your WhatsApp mobile app</p>
                    <small class="text-warning">
                        <i class="fas fa-clock"></i> 
                        QR Code expires in ${response.data.expires_in || 300} seconds
                    </small>
                `);

                        // Start countdown
                        if (response.data.expires_in) {
                            startQRCountdown(response.data.expires_in);
                        }
                    } else {
                        $('#qr-container').html(`
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        QR Code not available. Device may not be in pairing mode.
                    </div>
                `);
                    }
                },
                error: function() {
                    $('#qr-container').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-times"></i>
                    Failed to load QR Code. Please try again.
                </div>
            `);
                }
            });
        }

        function startQRCountdown(seconds) {
            let remaining = seconds;

            const countdown = setInterval(() => {
                remaining--;

                const countdownElement = $('#qr-container').find('.text-warning');
                if (countdownElement.length) {
                    countdownElement.html(`
                <i class="fas fa-clock"></i> 
                QR Code expires in ${remaining} seconds
            `);

                    if (remaining <= 30) {
                        countdownElement.removeClass('text-warning').addClass('text-danger');
                    }
                }

                if (remaining <= 0) {
                    clearInterval(countdown);
                    $('#qr-container').html(`
                <div class="alert alert-warning">
                    <i class="fas fa-clock"></i>
                    QR Code has expired. 
                    <button class="btn btn-sm btn-primary ml-2" onclick="refreshQRCode()">
                        Generate New QR
                    </button>
                </div>
            `);
                }
            }, 1000);
        }

        // Refresh QR Code
        function refreshQRCode() {
            $('#qr-container').html(`
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
            <p>Generating new QR Code...</p>
        </div>
    `);
            loadQRCode();
        }

        // Refresh device status
        function refreshDeviceStatus() {
            $('#refresh-indicator').show();

            $.ajax({
                url: '../../api/devices.php',
                method: 'GET',
                data: {
                    action: 'status',
                    device_id: <?php echo $device_id; ?>
                },
                success: function(response) {
                    if (response.success) {
                        updateDeviceStatus(response.data);
                    }
                },
                error: function() {
                    console.log('Failed to refresh device status');
                },
                complete: function() {
                    $('#refresh-indicator').hide();
                }
            });
        }

        // Update device status in UI
        function updateDeviceStatus(deviceData) {
            // Update status badge
            const statusConfig = getStatusConfig(deviceData.status);
            $('.device-status .badge').attr('class', `badge badge-${statusConfig.class} badge-lg`)
                .html(`<i class="fas ${statusConfig.icon}"></i> ${statusConfig.text}`);

            // Update online indicator
            const onlineClass = deviceData.is_online ? 'status-online' : 'status-offline';
            $('.status-indicator').attr('class', `status-indicator ${onlineClass}`);

            // Update last seen
            $('.info-box-text').each(function() {
                if ($(this).text() === 'Last Seen') {
                    $(this).siblings('.info-box-number').text(timeAgo(deviceData.last_seen));
                }
            });

            // Update device actions if status changed
            if (deviceData.status !== '<?php echo $deviceData['status']; ?>') {
                $('.device-actions').html(renderDeviceActions(deviceData));
            }
        }

        function getStatusConfig(status) {
            const configs = {
                'connecting': {
                    class: 'warning',
                    icon: 'fa-spinner fa-spin',
                    text: 'Connecting'
                },
                'connected': {
                    class: 'success',
                    icon: 'fa-check-circle',
                    text: 'Connected'
                },
                'disconnected': {
                    class: 'secondary',
                    icon: 'fa-times-circle',
                    text: 'Disconnected'
                },
                'pairing': {
                    class: 'info',
                    icon: 'fa-qrcode',
                    text: 'Pairing'
                },
                'banned': {
                    class: 'danger',
                    icon: 'fa-ban',
                    text: 'Banned'
                },
                'error': {
                    class: 'danger',
                    icon: 'fa-exclamation-triangle',
                    text: 'Error'
                },
                'timeout': {
                    class: 'warning',
                    icon: 'fa-clock',
                    text: 'Timeout'
                },
                'auth_failure': {
                    class: 'danger',
                    icon: 'fa-key',
                    text: 'Auth Failed'
                },
                'logout': {
                    class: 'secondary',
                    icon: 'fa-sign-out-alt',
                    text: 'Logged Out'
                }
            };

            return configs[status] || configs.error;
        }

        function renderDeviceActions(deviceData) {
            let actions = [];

            switch (deviceData.status) {
                case 'disconnected':
                case 'error':
                case 'timeout':
                case 'auth_failure':
                    actions.push(`<a href="?id=${deviceData.id}&action=connect" class="btn btn-success">
                           <i class="fas fa-play"></i> Connect
                         </a>`);
                    break;

                case 'connected':
                    actions.push(`<a href="?id=${deviceData.id}&action=disconnect" class="btn btn-warning">
                           <i class="fas fa-stop"></i> Disconnect
                         </a>`);
                    break;

                case 'connecting':
                    actions.push(`<button class="btn btn-secondary" disabled>
                           <i class="fas fa-spinner fa-spin"></i> Connecting...
                         </button>`);
                    break;

                case 'pairing':
                    actions.push(`<button class="btn btn-info" onclick="refreshQRCode()">
                           <i class="fas fa-qrcode"></i> Refresh QR
                         </button>`);
                    break;
            }

            actions.push(`<a href="?id=${deviceData.id}&action=restart" class="btn btn-secondary">
                   <i class="fas fa-redo"></i> Restart
                 </a>`);

            return actions.join(' ');
        }

        // Refresh logs
        function refreshLogs() {
            $.ajax({
                url: '../../api/devices.php',
                method: 'GET',
                data: {
                    action: 'logs',
                    device_id: <?php echo $device_id; ?>,
                    limit: 20
                },
                success: function(response) {
                    if (response.success) {
                        renderLogs(response.data);
                    }
                },
                error: function() {
                    console.log('Failed to refresh logs');
                }
            });
        }

        function renderLogs(logs) {
            const container = $('#device-logs');

            if (logs.length === 0) {
                container.html(`
            <div class="text-center py-4">
                <i class="fas fa-file fa-2x text-muted mb-2"></i>
                <p class="text-muted">No logs available</p>
            </div>
        `);
                return;
            }

            let html = '';
            logs.forEach(log => {
                const logLevel = getLogLevelClass(log.log_level);
                const time = new Date(log.created_at).toLocaleTimeString();

                html += `
            <div class="log-item ${logLevel}">
                <div class="d-flex justify-content-between">
                    <span>[${time}]</span>
                    <span class="badge badge-${logLevel} badge-sm">
                        ${log.log_level.toUpperCase()}
                    </span>
                </div>
                <div class="mt-1">
                    ${escapeHtml(log.message)}
                </div>
            </div>
        `;
            });

            container.html(html);
        }

        function getLogLevelClass(level) {
            const classes = {
                'success': 'success',
                'info': 'info',
                'warning': 'warning',
                'error': 'error'
            };
            return classes[level] || 'secondary';
        }

        // Initialize WebSocket for real-time updates
        function initializeWebSocket() {
            try {
                const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const wsUrl = `${protocol}//${window.location.host}/ws/device/${<?php echo $device_id; ?>}`;

                const ws = new WebSocket(wsUrl);

                ws.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        handleWebSocketMessage(data);
                    } catch (error) {
                        console.error('WebSocket message error:', error);
                    }
                };

                ws.onclose = function() {
                    console.log('WebSocket connection closed, attempting to reconnect...');
                    setTimeout(initializeWebSocket, 5000);
                };

            } catch (error) {
                console.error('WebSocket error:', error);
            }
        }

        function handleWebSocketMessage(data) {
            switch (data.type) {
                case 'status_update':
                    updateDeviceStatus(data.device);
                    break;

                case 'new_message':
                    // Add new message to recent messages list
                    addNewMessage(data.message);
                    break;

                case 'qr_update':
                    if (data.qr_code) {
                        updateQRCode(data.qr_code, data.expires_in);
                    }
                    break;

                case 'log_entry':
                    addNewLogEntry(data.log);
                    break;
            }
        }

        function addNewMessage(message) {
            const messagesList = $('.message-item').parent();
            const direction = message.direction === 'incoming' ? 'arrow-down text-success' : 'arrow-up text-primary';
            const directionText = message.direction === 'incoming' ? 'From' : 'To';
            const phoneNumber = message.direction === 'incoming' ? message.from_number : message.to_number;

            const messageHtml = `
        <div class="message-item">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-1">
                        <i class="fas fa-${direction} me-2"></i>
                        <strong>${directionText}:</strong>
                        <span class="ml-2">${formatPhoneNumber(phoneNumber)}</span>
                    </div>
                    <div class="message-content">
                        <span class="badge badge-info badge-sm">${message.message_type}</span>
                        ${message.message_type === 'text' && message.message_content ? 
                          `<p class="mb-1 text-truncate" style="max-width: 300px;">${escapeHtml(message.message_content)}</p>` : 
                          ''}
                    </div>
                </div>
                <small class="text-muted ml-2">Just now</small>
            </div>
        </div>
    `;

            messagesList.prepend(messageHtml);

            // Keep only latest 10 messages
            if (messagesList.children().length > 10) {
                messagesList.children().last().remove();
            }
        }

        function addNewLogEntry(log) {
            const logsContainer = $('#device-logs');
            const logLevel = getLogLevelClass(log.log_level);
            const time = new Date().toLocaleTimeString();

            const logHtml = `
        <div class="log-item ${logLevel}">
            <div class="d-flex justify-content-between">
                <span>[${time}]</span>
                <span class="badge badge-${logLevel} badge-sm">
                    ${log.log_level.toUpperCase()}
                </span>
            </div>
            <div class="mt-1">
                ${escapeHtml(log.message)}
            </div>
        </div>
    `;

            logsContainer.prepend(logHtml);

            // Keep only latest 20 logs
            if (logsContainer.children().length > 20) {
                logsContainer.children().last().remove();
            }
        }

        // Token management
        function toggleTokenVisibility() {
            const tokenInput = document.getElementById('api-token');
            const eyeIcon = document.getElementById('token-eye');

            if (tokenInput.type === 'password') {
                tokenInput.type = 'text';
                eyeIcon.className = 'fas fa-eye-slash';
            } else {
                tokenInput.type = 'password';
                eyeIcon.className = 'fas fa-eye';
            }
        }

        function copyToken() {
            const tokenInput = document.getElementById('api-token');
            const originalType = tokenInput.type;

            tokenInput.type = 'text';
            tokenInput.select();
            document.execCommand('copy');
            tokenInput.type = originalType;

            // Show feedback
            showToast('success', 'API token copied to clipboard');
        }

        // Utility functions
        function copyText(text) {
            const tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);

            showToast('success', 'Copied to clipboard');
        }

        function formatPhoneNumber(phone) {
            if (!phone) return phone;

            // Remove country code and format
            const cleaned = phone.replace(/^\+?62/, '');
            if (cleaned.length >= 10) {
                return `+62 ${cleaned.substring(0, 3)} ${cleaned.substring(3, 7)} ${cleaned.substring(7)}`;
            }
            return `+62 ${cleaned}`;
        }

        function timeAgo(dateString) {
            if (!dateString) return 'Never';

            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + ' minutes ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + ' hours ago';
            if (diff < 604800000) return Math.floor(diff / 86400000) + ' days ago';

            return date.toLocaleDateString();
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }

        function showToast(type, message) {
            // Create toast notification (you can use any toast library)
            const toast = $(`
        <div class="toast" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
            <div class="toast-header">
                <i class="fas fa-${type === 'success' ? 'check-circle text-success' : 'exclamation-circle text-danger'} me-2"></i>
                <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        </div>
    `);

            $('body').append(toast);
            toast.toast('show');

            setTimeout(() => toast.remove(), 5000);
        }

        // Device actions
        function exportDeviceData() {
            const url = `../../api/devices.php?action=export&device_id=${<?php echo $device_id; ?>}&format=json`;
            window.open(url, '_blank');
        }

        function deleteDevice() {
            if (confirm('Are you sure you want to delete this device? This action cannot be undone and will also delete all associated data.')) {
                if (confirm('This will permanently delete all messages, logs, and configuration. Are you absolutely sure?')) {
                    window.location.href = `delete.php?id=${<?php echo $device_id; ?>}`;
                }
            }
        }

        // Page visibility change handler
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                // Page became visible, refresh data
                refreshDeviceStatus();
                refreshLogs();
            }
        });
    </script>

</body>

</html>