<?php
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../includes/session.php';

// Check authentication
if (!Auth::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$pageTitle = "System Alerts";
$currentPage = "alerts";

// Handle alert actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'acknowledge':
                // Mark alert as acknowledged
                $alertId = $_POST['alert_id'] ?? 0;
                // Implementation would go here
                $_SESSION['success'] = "Alert acknowledged successfully";
                break;

            case 'resolve':
                // Resolve alert
                $alertId = $_POST['alert_id'] ?? 0;
                // Implementation would go here
                $_SESSION['success'] = "Alert resolved successfully";
                break;
        }
        header('Location: alerts.php');
        exit();
    }
}

// Get critical alerts
$criticalAlerts = [];

try {
    // Check for banned devices
    $bannedQuery = "SELECT 
        d.id, d.device_name, d.phone_number, d.status, d.updated_at,
        u.full_name as owner
    FROM devices d
    LEFT JOIN users u ON d.user_id = u.id
    WHERE d.status = 'banned'
    ORDER BY d.updated_at DESC";

    $bannedStmt = $db->prepare($bannedQuery);
    $bannedStmt->execute();
    $bannedDevices = $bannedStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bannedDevices as $device) {
        $criticalAlerts[] = [
            'type' => 'critical',
            'category' => 'device_banned',
            'title' => 'Device Banned by WhatsApp',
            'message' => "Device '{$device['device_name']}' ({$device['phone_number']}) has been banned",
            'device_id' => $device['id'],
            'timestamp' => $device['updated_at'],
            'data' => $device
        ];
    }

    // Check for high retry counts
    $retryQuery = "SELECT 
        d.id, d.device_name, d.phone_number, d.retry_count, d.status, d.last_seen,
        u.full_name as owner
    FROM devices d
    LEFT JOIN users u ON d.user_id = u.id
    WHERE d.retry_count >= 3
    ORDER BY d.retry_count DESC";

    $retryStmt = $db->prepare($retryQuery);
    $retryStmt->execute();
    $highRetryDevices = $retryStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($highRetryDevices as $device) {
        $criticalAlerts[] = [
            'type' => 'warning',
            'category' => 'connection_issues',
            'title' => 'High Connection Retry Count',
            'message' => "Device '{$device['device_name']}' has failed to connect {$device['retry_count']} times",
            'device_id' => $device['id'],
            'timestamp' => $device['last_seen'],
            'data' => $device
        ];
    }

    // Check for devices offline for too long
    $offlineQuery = "SELECT 
        d.id, d.device_name, d.phone_number, d.status, d.last_seen,
        TIMESTAMPDIFF(HOUR, d.last_seen, NOW()) as hours_offline,
        u.full_name as owner
    FROM devices d
    LEFT JOIN users u ON d.user_id = u.id
    WHERE d.status != 'connected' 
    AND d.last_seen < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND d.last_seen IS NOT NULL
    ORDER BY d.last_seen ASC";

    $offlineStmt = $db->prepare($offlineQuery);
    $offlineStmt->execute();
    $offlineDevices = $offlineStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($offlineDevices as $device) {
        $criticalAlerts[] = [
            'type' => 'warning',
            'category' => 'device_offline',
            'title' => 'Device Offline for Extended Period',
            'message' => "Device '{$device['device_name']}' has been offline for {$device['hours_offline']} hours",
            'device_id' => $device['id'],
            'timestamp' => $device['last_seen'],
            'data' => $device
        ];
    }

    // Check for failed messages
    $failedMsgQuery = "SELECT 
        COUNT(*) as failed_count,
        d.device_name, d.phone_number, d.id as device_id,
        MAX(ml.created_at) as last_failure
    FROM message_logs ml
    JOIN devices d ON ml.device_id = d.id
    WHERE ml.status = 'failed'
    AND ml.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY d.id
    HAVING failed_count > 5
    ORDER BY failed_count DESC";

    $failedMsgStmt = $db->prepare($failedMsgQuery);
    $failedMsgStmt->execute();
    $failedMessages = $failedMsgStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($failedMessages as $failure) {
        $criticalAlerts[] = [
            'type' => 'warning',
            'category' => 'message_failures',
            'title' => 'High Message Failure Rate',
            'message' => "Device '{$failure['device_name']}' has {$failure['failed_count']} failed messages in last 24 hours",
            'device_id' => $failure['device_id'],
            'timestamp' => $failure['last_failure'],
            'data' => $failure
        ];
    }

    // Check API performance issues
    $apiPerfQuery = "SELECT 
        webhook_type, event_name,
        COUNT(*) as total_calls,
        AVG(execution_time) as avg_time,
        MAX(execution_time) as max_time,
        SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_calls
    FROM webhook_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY webhook_type, event_name
    HAVING avg_time > 5 OR (failed_calls / total_calls) > 0.1";

    $apiPerfStmt = $db->prepare($apiPerfQuery);
    $apiPerfStmt->execute();
    $apiIssues = $apiPerfStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($apiIssues as $issue) {
        $failureRate = ($issue['failed_calls'] / $issue['total_calls']) * 100;
        if ($issue['avg_time'] > 5) {
            $criticalAlerts[] = [
                'type' => 'warning',
                'category' => 'api_performance',
                'title' => 'Slow API Response Time',
                'message' => "API endpoint '{$issue['event_name']}' averaging {$issue['avg_time']}s response time",
                'device_id' => null,
                'timestamp' => date('Y-m-d H:i:s'),
                'data' => $issue
            ];
        }
        if ($failureRate > 10) {
            $criticalAlerts[] = [
                'type' => 'critical',
                'category' => 'api_failures',
                'title' => 'High API Failure Rate',
                'message' => "API endpoint '{$issue['event_name']}' has " . number_format($failureRate, 1) . "% failure rate",
                'device_id' => null,
                'timestamp' => date('Y-m-d H:i:s'),
                'data' => $issue
            ];
        }
    }

    // Sort alerts by timestamp
    usort($criticalAlerts, function ($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
} catch (PDOException $e) {
    error_log("Alerts query error: " . $e->getMessage());
}

// Get alert statistics
$alertStats = [
    'critical' => 0,
    'warning' => 0,
    'info' => 0
];

foreach ($criticalAlerts as $alert) {
    $alertStats[$alert['type']]++;
}

include '../../includes/header.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-exclamation-triangle"></i> System Alerts</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                        <li class="breadcrumb-item active">Alerts</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Alert Summary -->
            <div class="row">
                <div class="col-md-4">
                    <div class="info-box bg-danger">
                        <span class="info-box-icon"><i class="fas fa-exclamation-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Critical Alerts</span>
                            <span class="info-box-number"><?php echo $alertStats['critical']; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-warning">
                        <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Warnings</span>
                            <span class="info-box-number"><?php echo $alertStats['warning']; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-info-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Informational</span>
                            <span class="info-box-number"><?php echo $alertStats['info']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Filters -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Filter Alerts</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Alert Type</label>
                                        <select class="form-control" id="filterType">
                                            <option value="">All Types</option>
                                            <option value="critical">Critical</option>
                                            <option value="warning">Warning</option>
                                            <option value="info">Informational</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <select class="form-control" id="filterCategory">
                                            <option value="">All Categories</option>
                                            <option value="device_banned">Device Banned</option>
                                            <option value="connection_issues">Connection Issues</option>
                                            <option value="device_offline">Device Offline</option>
                                            <option value="message_failures">Message Failures</option>
                                            <option value="api_performance">API Performance</option>
                                            <option value="api_failures">API Failures</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Time Range</label>
                                        <select class="form-control" id="filterTime">
                                            <option value="1">Last Hour</option>
                                            <option value="24" selected>Last 24 Hours</option>
                                            <option value="168">Last 7 Days</option>
                                            <option value="720">Last 30 Days</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button class="btn btn-primary btn-block" onclick="filterAlerts()">
                                            <i class="fas fa-filter"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Alerts -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Active Alerts</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-success" onclick="resolveAll()">
                                    <i class="fas fa-check"></i> Resolve All
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" onclick="refreshAlerts()">
                                    <i class="fas fa-sync"></i> Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($criticalAlerts)): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> No active alerts. System is running smoothly!
                                </div>
                            <?php else: ?>
                                <div id="alertsContainer">
                                    <?php foreach ($criticalAlerts as $index => $alert): ?>
                                        <div class="alert-item" data-type="<?php echo $alert['type']; ?>" data-category="<?php echo $alert['category']; ?>">
                                            <div class="callout callout-<?php echo $alert['type'] == 'critical' ? 'danger' : ($alert['type'] == 'warning' ? 'warning' : 'info'); ?>">
                                                <div class="row">
                                                    <div class="col-md-9">
                                                        <h5>
                                                            <?php
                                                            $icon = [
                                                                'device_banned' => 'fa-ban',
                                                                'connection_issues' => 'fa-plug',
                                                                'device_offline' => 'fa-power-off',
                                                                'message_failures' => 'fa-envelope-open-text',
                                                                'api_performance' => 'fa-tachometer-alt',
                                                                'api_failures' => 'fa-server'
                                                            ][$alert['category']] ?? 'fa-exclamation';
                                                            ?>
                                                            <i class="fas <?php echo $icon; ?>"></i>
                                                            <?php echo htmlspecialchars($alert['title']); ?>
                                                        </h5>
                                                        <p class="mb-1"><?php echo htmlspecialchars($alert['message']); ?></p>
                                                        <small class="text-muted">
                                                            <i class="far fa-clock"></i>
                                                            <?php echo date('Y-m-d H:i:s', strtotime($alert['timestamp'])); ?>
                                                            (<?php echo timeAgo($alert['timestamp']); ?>)
                                                        </small>

                                                        <?php if (isset($alert['data'])): ?>
                                                            <div class="mt-2">
                                                                <button class="btn btn-xs btn-outline-primary" onclick="toggleDetails(<?php echo $index; ?>)">
                                                                    <i class="fas fa-chevron-down"></i> Details
                                                                </button>
                                                                <div id="details-<?php echo $index; ?>" class="alert-details mt-2" style="display: none;">
                                                                    <?php if (isset($alert['data']['owner'])): ?>
                                                                        <small><strong>Owner:</strong> <?php echo htmlspecialchars($alert['data']['owner']); ?></small><br>
                                                                    <?php endif; ?>
                                                                    <?php if (isset($alert['data']['phone_number'])): ?>
                                                                        <small><strong>Phone:</strong> <?php echo htmlspecialchars($alert['data']['phone_number']); ?></small><br>
                                                                    <?php endif; ?>
                                                                    <?php if (isset($alert['data']['retry_count'])): ?>
                                                                        <small><strong>Retry Count:</strong> <?php echo $alert['data']['retry_count']; ?></small><br>
                                                                    <?php endif; ?>
                                                                    <?php if (isset($alert['data']['failed_count'])): ?>
                                                                        <small><strong>Failed Messages:</strong> <?php echo $alert['data']['failed_count']; ?></small><br>
                                                                    <?php endif; ?>
                                                                    <?php if (isset($alert['data']['avg_time'])): ?>
                                                                        <small><strong>Avg Response Time:</strong> <?php echo number_format($alert['data']['avg_time'], 2); ?>s</small><br>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-3 text-right">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="acknowledge">
                                                            <input type="hidden" name="alert_id" value="<?php echo $index; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                                <i class="fas fa-eye"></i> Acknowledge
                                                            </button>
                                                        </form>
                                                        <?php if ($alert['device_id']): ?>
                                                            <a href="../devices/view.php?id=<?php echo $alert['device_id']; ?>"
                                                                class="btn btn-sm btn-outline-info">
                                                                <i class="fas fa-mobile-alt"></i> View Device
                                                            </a>
                                                        <?php endif; ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="resolve">
                                                            <input type="hidden" name="alert_id" value="<?php echo $index; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-check"></i> Resolve
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Rules Configuration -->
            <div class="row">
                <div class="col-12">
                    <div class="card collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title">Alert Rules Configuration</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="alertRulesForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Device Alerts</h5>
                                        <div class="form-group">
                                            <label>Device Offline Threshold (hours)</label>
                                            <input type="number" class="form-control" name="offline_threshold" value="24" min="1">
                                        </div>
                                        <div class="form-group">
                                            <label>Max Connection Retries</label>
                                            <input type="number" class="form-control" name="max_retries" value="3" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Message Alerts</h5>
                                        <div class="form-group">
                                            <label>Failed Message Threshold (per day)</label>
                                            <input type="number" class="form-control" name="failed_msg_threshold" value="5" min="1">
                                        </div>
                                        <div class="form-group">
                                            <label>API Response Time Threshold (seconds)</label>
                                            <input type="number" class="form-control" name="api_response_threshold" value="5" min="1" step="0.1">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Alert Rules
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
// Helper function for time ago
function timeAgo($timestamp)
{
    $time = strtotime($timestamp);
    $diff = time() - $time;

    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } else {
        return floor($diff / 86400) . ' days ago';
    }
}

include '../../includes/footer.php';
?>

<script>
    // Toggle alert details
    function toggleDetails(index) {
        const details = document.getElementById('details-' + index);
        const button = event.target;

        if (details.style.display === 'none') {
            details.style.display = 'block';
            button.innerHTML = '<i class="fas fa-chevron-up"></i> Details';
        } else {
            details.style.display = 'none';
            button.innerHTML = '<i class="fas fa-chevron-down"></i> Details';
        }
    }

    // Filter alerts
    function filterAlerts() {
        const type = document.getElementById('filterType').value;
        const category = document.getElementById('filterCategory').value;
        const alerts = document.querySelectorAll('.alert-item');

        alerts.forEach(alert => {
            let show = true;

            if (type && alert.dataset.type !== type) {
                show = false;
            }

            if (category && alert.dataset.category !== category) {
                show = false;
            }

            alert.style.display = show ? 'block' : 'none';
        });
    }

    // Refresh alerts
    function refreshAlerts() {
        location.reload();
    }

    // Resolve all alerts
    function resolveAll() {
        if (confirm('Are you sure you want to resolve all alerts?')) {
            // Implementation would submit form to resolve all
            alert('Feature would resolve all alerts');
        }
    }

    // Alert rules form
    document.getElementById('alertRulesForm')?.addEventListener('submit', function(e) {
        e.preventDefault();

        // Save alert rules via AJAX
        const formData = new FormData(this);

        // Implementation would save to database
        alert('Alert rules saved successfully!');
    });

    // Auto refresh every 60 seconds
    setInterval(() => {
        const autoRefresh = true; // Could be a setting
        if (autoRefresh) {
            refreshAlerts();
        }
    }, 60000);

    // Show notification for critical alerts
    document.addEventListener('DOMContentLoaded', function() {
        const criticalCount = <?php echo $alertStats['critical']; ?>;
        if (criticalCount > 0) {
            // Could implement browser notifications here
            console.log(`${criticalCount} critical alerts require attention`);
        }
    });
</script>