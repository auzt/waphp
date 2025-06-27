<?php

/**
 * Device Monitoring Dashboard
 * Real-time device status monitoring
 */

require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Device.php';
require_once '../../classes/NodeJSClient.php';

// Check permission
requireLogin();

$pageTitle = 'Device Monitoring';
$db = Database::getInstance()->getConnection();
$deviceObj = new Device();
$nodeClient = new NodeJSClient();

// Get filter parameters
$filter = [
    'status' => $_GET['status'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Build query
$query = "
    SELECT 
        d.*,
        u.username,
        u.full_name,
        (SELECT COUNT(*) FROM message_logs ml WHERE ml.device_id = d.id AND DATE(ml.created_at) = CURDATE()) as messages_today,
        (SELECT COUNT(*) FROM message_logs ml WHERE ml.device_id = d.id AND ml.direction = 'incoming' AND DATE(ml.created_at) = CURDATE()) as messages_received,
        (SELECT COUNT(*) FROM message_logs ml WHERE ml.device_id = d.id AND ml.direction = 'outgoing' AND DATE(ml.created_at) = CURDATE()) as messages_sent,
        (SELECT MAX(created_at) FROM message_logs ml WHERE ml.device_id = d.id) as last_message_at,
        (SELECT COUNT(*) FROM api_tokens at WHERE at.device_id = d.id AND at.is_active = TRUE) as active_tokens
    FROM devices d
    JOIN users u ON d.user_id = u.id
    WHERE 1=1
";

$params = [];

// Apply filters
if (!hasRole('admin')) {
    $query .= " AND d.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

if ($filter['status']) {
    $query .= " AND d.status = ?";
    $params[] = $filter['status'];
}

if ($filter['user_id'] && hasRole('admin')) {
    $query .= " AND d.user_id = ?";
    $params[] = $filter['user_id'];
}

if ($filter['search']) {
    $query .= " AND (d.device_name LIKE ? OR d.phone_number LIKE ? OR d.whatsapp_name LIKE ?)";
    $params[] = '%' . $filter['search'] . '%';
    $params[] = '%' . $filter['search'] . '%';
    $params[] = '%' . $filter['search'] . '%';
}

$query .= " ORDER BY d.is_online DESC, d.last_seen DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total' => count($devices),
    'connected' => 0,
    'disconnected' => 0,
    'error' => 0,
    'messages_today' => 0
];

foreach ($devices as $device) {
    if ($device['status'] === 'connected') $stats['connected']++;
    elseif ($device['status'] === 'disconnected') $stats['disconnected']++;
    elseif (in_array($device['status'], ['error', 'banned', 'auth_failure'])) $stats['error']++;
    $stats['messages_today'] += $device['messages_today'];
}

// Get users for filter (admin only)
$users = [];
if (hasRole('admin')) {
    $stmt = $db->query("SELECT id, username, full_name FROM users WHERE status = 'active' ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Test Node.js connection
$nodeStatus = $nodeClient->testConnection();

require_once '../../includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Device Monitoring</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                    <li class="breadcrumb-item">Monitoring</li>
                    <li class="breadcrumb-item active">Devices</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <!-- Node.js Status Alert -->
        <?php if (!$nodeStatus['success']): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <h5><i class="icon fas fa-ban"></i> Node.js Backend Offline!</h5>
                The Node.js backend is not responding. WhatsApp functionality will not work until it's back online.
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Devices</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $stats['connected']; ?></h3>
                        <p>Connected</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $stats['disconnected']; ?></h3>
                        <p>Disconnected</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-unlink"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $stats['error']; ?></h3>
                        <p>Error/Banned</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card collapsed-card">
            <div class="card-header">
                <h3 class="card-title">Filters</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="connected" <?php echo $filter['status'] === 'connected' ? 'selected' : ''; ?>>Connected</option>
                                    <option value="disconnected" <?php echo $filter['status'] === 'disconnected' ? 'selected' : ''; ?>>Disconnected</option>
                                    <option value="pairing" <?php echo $filter['status'] === 'pairing' ? 'selected' : ''; ?>>Pairing</option>
                                    <option value="error" <?php echo $filter['status'] === 'error' ? 'selected' : ''; ?>>Error</option>
                                    <option value="banned" <?php echo $filter['status'] === 'banned' ? 'selected' : ''; ?>>Banned</option>
                                </select>
                            </div>
                        </div>
                        <?php if (hasRole('admin')): ?>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>User</label>
                                    <select name="user_id" class="form-control select2">
                                        <option value="">All Users</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"
                                                <?php echo $filter['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['username']); ?> -
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" name="search" class="form-control"
                                    placeholder="Search device name, phone number..."
                                    value="<?php echo htmlspecialchars($filter['search']); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="devices.php" class="btn btn-default">
                                    <i class="fas fa-undo"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Device Grid -->
        <div class="row">
            <?php foreach ($devices as $device): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card device-card" data-device-id="<?php echo $device['device_id']; ?>">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-mobile-alt"></i>
                                <?php echo htmlspecialchars($device['device_name']); ?>
                            </h3>
                            <div class="card-tools">
                                <?php echo getStatusBadge($device['status']); ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <strong>Phone:</strong><br>
                                    <?php echo htmlspecialchars($device['phone_number']); ?>
                                </div>
                                <div class="col-6">
                                    <strong>WhatsApp:</strong><br>
                                    <?php echo htmlspecialchars($device['whatsapp_name'] ?: 'Not connected'); ?>
                                </div>
                            </div>

                            <hr class="my-2">

                            <div class="row text-center">
                                <div class="col-4">
                                    <small class="text-muted">Today</small><br>
                                    <strong><?php echo number_format($device['messages_today']); ?></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Received</small><br>
                                    <strong><?php echo number_format($device['messages_received']); ?></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Sent</small><br>
                                    <strong><?php echo number_format($device['messages_sent']); ?></strong>
                                </div>
                            </div>

                            <hr class="my-2">

                            <div class="device-info">
                                <small class="text-muted">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($device['username']); ?>
                                    <?php if ($device['active_tokens'] > 0): ?>
                                        | <i class="fas fa-key"></i> <?php echo $device['active_tokens']; ?> token(s)
                                    <?php endif; ?>
                                </small><br>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> Last seen:
                                    <?php echo $device['last_seen'] ? getTimeAgo($device['last_seen']) : 'Never'; ?>
                                </small>
                                <?php if ($device['last_message_at']): ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-comment"></i> Last message:
                                        <?php echo getTimeAgo($device['last_message_at']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group btn-block">
                                <?php if ($device['status'] === 'connected'): ?>
                                    <button class="btn btn-sm btn-warning" onclick="disconnectDevice('<?php echo $device['device_id']; ?>')">
                                        <i class="fas fa-unlink"></i> Disconnect
                                    </button>
                                    <a href="/pages/devices/view.php?id=<?php echo $device['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                <?php elseif ($device['status'] === 'disconnected'): ?>
                                    <button class="btn btn-sm btn-success" onclick="connectDevice('<?php echo $device['device_id']; ?>')">
                                        <i class="fas fa-link"></i> Connect
                                    </button>
                                    <a href="/pages/devices/view.php?id=<?php echo $device['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                <?php elseif ($device['status'] === 'pairing'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="showQR('<?php echo $device['device_id']; ?>')">
                                        <i class="fas fa-qrcode"></i> Show QR
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="cancelPairing('<?php echo $device['device_id']; ?>')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-primary" onclick="resetDevice('<?php echo $device['device_id']; ?>')">
                                        <i class="fas fa-redo"></i> Reset
                                    </button>
                                    <a href="/pages/devices/view.php?id=<?php echo $device['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($devices)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No devices found matching your criteria.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Scan QR Code</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center">
                <div id="qrContent">
                    <i class="fas fa-spinner fa-spin fa-3x"></i>
                    <p class="mt-3">Loading QR Code...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize
    $(document).ready(function() {
        $('.select2').select2();

        // Auto refresh every 30 seconds
        setInterval(refreshDeviceStatus, 30000);
    });

    // Connect device
    function connectDevice(deviceId) {
        $.post('/api/devices.php', {
            action: 'connect',
            device_id: deviceId
        }, function(response) {
            if (response.success) {
                toastr.success('Connecting device...');
                setTimeout(() => location.reload(), 2000);
            } else {
                toastr.error(response.message || 'Failed to connect device');
            }
        });
    }

    // Disconnect device
    function disconnectDevice(deviceId) {
        if (!confirm('Are you sure you want to disconnect this device?')) {
            return;
        }

        $.post('/api/devices.php', {
            action: 'disconnect',
            device_id: deviceId
        }, function(response) {
            if (response.success) {
                toastr.success('Device disconnected');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(response.message || 'Failed to disconnect device');
            }
        });
    }

    // Show QR code
    function showQR(deviceId) {
        $('#qrModal').modal('show');

        $.post('/api/devices.php', {
            action: 'getQR',
            device_id: deviceId
        }, function(response) {
            if (response.success && response.data.qr) {
                $('#qrContent').html(`
                <img src="${response.data.qr}" alt="QR Code" class="img-fluid">
                <p class="mt-3">Scan this QR code with WhatsApp on your phone</p>
                <small class="text-muted">QR expires in 5 minutes</small>
            `);
            } else {
                $('#qrContent').html(`
                <div class="alert alert-danger">
                    Failed to load QR code. Please try again.
                </div>
            `);
            }
        });
    }

    // Cancel pairing
    function cancelPairing(deviceId) {
        disconnectDevice(deviceId);
    }

    // Reset device
    function resetDevice(deviceId) {
        if (!confirm('This will reset the device and clear all session data. Continue?')) {
            return;
        }

        $.post('/api/devices.php', {
            action: 'reset',
            device_id: deviceId
        }, function(response) {
            if (response.success) {
                toastr.success('Device reset successfully');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(response.message || 'Failed to reset device');
            }
        });
    }

    // Refresh device status
    function refreshDeviceStatus() {
        $('.device-card').each(function() {
            const deviceId = $(this).data('device-id');
            const card = $(this);

            $.get('/api/devices.php', {
                action: 'status',
                device_id: deviceId
            }, function(response) {
                if (response.success && response.data) {
                    // Update status badge
                    const statusBadge = getStatusBadgeHtml(response.data.status);
                    card.find('.card-tools').html(statusBadge);

                    // Update last seen
                    if (response.data.last_seen) {
                        card.find('.device-info .fa-clock').parent().html(
                            '<i class="fas fa-clock"></i> Last seen: ' + response.data.last_seen_ago
                        );
                    }
                }
            });
        });
    }

    // Get status badge HTML
    function getStatusBadgeHtml(status) {
        const badges = {
            'connected': '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Connected</span>',
            'disconnected': '<span class="badge badge-warning"><i class="fas fa-unlink"></i> Disconnected</span>',
            'pairing': '<span class="badge badge-info"><i class="fas fa-qrcode"></i> Pairing</span>',
            'error': '<span class="badge badge-danger"><i class="fas fa-exclamation-circle"></i> Error</span>',
            'banned': '<span class="badge badge-danger"><i class="fas fa-ban"></i> Banned</span>'
        };
        return badges[status] || '<span class="badge badge-secondary">Unknown</span>';
    }

    // Helper functions
    <?php
    function getStatusBadge($status)
    {
        $badges = [
            'connected' => '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Connected</span>',
            'disconnected' => '<span class="badge badge-warning"><i class="fas fa-unlink"></i> Disconnected</span>',
            'pairing' => '<span class="badge badge-info"><i class="fas fa-qrcode"></i> Pairing</span>',
            'connecting' => '<span class="badge badge-primary"><i class="fas fa-spinner fa-spin"></i> Connecting</span>',
            'error' => '<span class="badge badge-danger"><i class="fas fa-exclamation-circle"></i> Error</span>',
            'banned' => '<span class="badge badge-danger"><i class="fas fa-ban"></i> Banned</span>',
            'auth_failure' => '<span class="badge badge-danger"><i class="fas fa-lock"></i> Auth Failed</span>',
            'timeout' => '<span class="badge badge-warning"><i class="fas fa-clock"></i> Timeout</span>',
            'logout' => '<span class="badge badge-secondary"><i class="fas fa-sign-out-alt"></i> Logged Out</span>'
        ];
        return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
    }

    function getTimeAgo($datetime)
    {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = round($diff / 60);
            return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = round($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = round($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('Y-m-d', $time);
        }
    }
    ?>
</script>

<?php require_once '../../includes/footer.php'; ?>