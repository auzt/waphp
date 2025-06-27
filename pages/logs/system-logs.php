<?php

/**
 * System Logs Viewer
 * View system events and errors
 */

require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';

// Check permission - only admins can view system logs
requireLogin();
requireRole('admin');

$pageTitle = 'System Logs';
$db = Database::getInstance()->getConnection();

// Get filter parameters
$filter = [
    'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'log_type' => $_GET['log_type'] ?? '',
    'severity' => $_GET['severity'] ?? '',
    'category' => $_GET['category'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get log files
$logDir = __DIR__ . '/../../logs/';
$logFiles = [
    'error.log' => 'Error Log',
    'access.log' => 'Access Log',
    'api.log' => 'API Log'
];

// Read system events from database
$query = "
    SELECT * FROM (
        -- User login events
        SELECT 
            'login' as event_type,
            'info' as severity,
            CONCAT('User login: ', username) as message,
            JSON_OBJECT('user_id', id, 'username', username, 'ip', 'unknown') as context,
            last_login as created_at
        FROM users 
        WHERE last_login IS NOT NULL
        
        UNION ALL
        
        -- Device status changes
        SELECT 
            'device_status' as event_type,
            CASE 
                WHEN status = 'banned' THEN 'error'
                WHEN status IN ('error', 'auth_failure') THEN 'warning'
                ELSE 'info'
            END as severity,
            CONCAT('Device ', device_name, ' status changed to ', status) as message,
            JSON_OBJECT('device_id', device_id, 'status', status, 'raw_status', raw_status) as context,
            updated_at as created_at
        FROM devices
        WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        -- Failed webhook calls
        SELECT 
            'webhook_error' as event_type,
            'error' as severity,
            CONCAT('Webhook failed: ', event_name, ' - ', IFNULL(error_message, 'Unknown error')) as message,
            JSON_OBJECT('event', event_name, 'device_id', device_id) as context,
            created_at
        FROM webhook_logs
        WHERE success = FALSE
        
        UNION ALL
        
        -- Failed Node.js commands
        SELECT 
            'command_error' as event_type,
            'error' as severity,
            CONCAT('Command failed: ', command, ' - ', IFNULL(error_message, 'Unknown error')) as message,
            command_data as context,
            created_at
        FROM nodejs_commands
        WHERE status = 'failed'
        
        UNION ALL
        
        -- API errors
        SELECT 
            'api_error' as event_type,
            'warning' as severity,
            CONCAT('API error: ', method, ' ', endpoint, ' returned ', response_code) as message,
            JSON_OBJECT('endpoint', endpoint, 'code', response_code) as context,
            created_at
        FROM api_logs
        WHERE response_code >= 400
        
    ) as system_events
    WHERE 1=1
";

$params = [];

// Apply filters
if ($filter['date_from']) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $filter['date_from'];
}

if ($filter['date_to']) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $filter['date_to'];
}

if ($filter['log_type']) {
    $query .= " AND event_type = ?";
    $params[] = $filter['log_type'];
}

if ($filter['severity']) {
    $query .= " AND severity = ?";
    $params[] = $filter['severity'];
}

if ($filter['search']) {
    $query .= " AND (message LIKE ? OR context LIKE ?)";
    $params[] = '%' . $filter['search'] . '%';
    $params[] = '%' . $filter['search'] . '%';
}

$query .= " ORDER BY created_at DESC LIMIT 500";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get event statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_events,
        SUM(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) as error_count,
        SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning_count,
        SUM(CASE WHEN severity = 'info' THEN 1 ELSE 0 END) as info_count
    FROM ($query) as stats
";

$stmt = $db->prepare(str_replace('ORDER BY created_at DESC LIMIT 500', '', $statsQuery));
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">System Logs</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                    <li class="breadcrumb-item">Logs</li>
                    <li class="breadcrumb-item active">System Logs</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <!-- Statistics -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($stats['total_events']); ?></h3>
                        <p>Total Events</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-list"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo number_format($stats['error_count']); ?></h3>
                        <p>Errors</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($stats['warning_count']); ?></h3>
                        <p>Warnings</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($stats['info_count']); ?></h3>
                        <p>Info</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Files -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Log Files</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($logFiles as $file => $name): ?>
                        <?php
                        $filePath = $logDir . $file;
                        $exists = file_exists($filePath);
                        $size = $exists ? filesize($filePath) : 0;
                        $modified = $exists ? filemtime($filePath) : 0;
                        ?>
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-<?php echo $exists ? 'primary' : 'gray'; ?>">
                                    <i class="fas fa-file-alt"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text"><?php echo $name; ?></span>
                                    <span class="info-box-number">
                                        <?php if ($exists): ?>
                                            <?php echo formatFileSize($size); ?>
                                            <small class="text-muted d-block">
                                                Modified: <?php echo date('Y-m-d H:i', $modified); ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">File not found</small>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($exists): ?>
                                        <div class="mt-2">
                                            <button class="btn btn-xs btn-info" onclick="viewLogFile('<?php echo $file; ?>')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-xs btn-warning" onclick="downloadLogFile('<?php echo $file; ?>')">
                                                <i class="fas fa-download"></i> Download
                                            </button>
                                            <button class="btn btn-xs btn-danger" onclick="clearLogFile('<?php echo $file; ?>')">
                                                <i class="fas fa-trash"></i> Clear
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filter System Events</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" class="form-control"
                                    value="<?php echo htmlspecialchars($filter['date_from']); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" class="form-control"
                                    value="<?php echo htmlspecialchars($filter['date_to']); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Event Type</label>
                                <select name="log_type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="login" <?php echo $filter['log_type'] === 'login' ? 'selected' : ''; ?>>Login</option>
                                    <option value="device_status" <?php echo $filter['log_type'] === 'device_status' ? 'selected' : ''; ?>>Device Status</option>
                                    <option value="webhook_error" <?php echo $filter['log_type'] === 'webhook_error' ? 'selected' : ''; ?>>Webhook Error</option>
                                    <option value="command_error" <?php echo $filter['log_type'] === 'command_error' ? 'selected' : ''; ?>>Command Error</option>
                                    <option value="api_error" <?php echo $filter['log_type'] === 'api_error' ? 'selected' : ''; ?>>API Error</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Severity</label>
                                <select name="severity" class="form-control">
                                    <option value="">All Severities</option>
                                    <option value="error" <?php echo $filter['severity'] === 'error' ? 'selected' : ''; ?>>Error</option>
                                    <option value="warning" <?php echo $filter['severity'] === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                    <option value="info" <?php echo $filter['severity'] === 'info' ? 'selected' : ''; ?>>Info</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" name="search" class="form-control"
                                    placeholder="Search in logs..."
                                    value="<?php echo htmlspecialchars($filter['search']); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                            <a href="system-logs.php" class="btn btn-default">
                                <i class="fas fa-undo"></i> Reset
                            </a>
                            <button type="button" class="btn btn-danger float-right" onclick="clearAllLogs()">
                                <i class="fas fa-trash"></i> Clear All Events
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- System Events Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">System Events</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="150">Timestamp</th>
                            <th width="100">Type</th>
                            <th width="80">Severity</th>
                            <th>Message</th>
                            <th width="150">Context</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr class="<?php echo getSeverityRowClass($event['severity']); ?>">
                                <td>
                                    <small><?php echo date('Y-m-d H:i:s', strtotime($event['created_at'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo getEventTypeBadge($event['event_type']); ?>">
                                        <?php echo formatEventType($event['event_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo getSeverityBadge($event['severity']); ?>">
                                        <?php echo ucfirst($event['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($event['message']); ?>
                                </td>
                                <td>
                                    <?php
                                    $context = json_decode($event['context'], true);
                                    if ($context): ?>
                                        <button class="btn btn-xs btn-info" onclick='viewContext(<?php echo json_encode($context); ?>)'>
                                            <i class="fas fa-info-circle"></i> View Details
                                        </button>
                                    <?php else: ?>
                                        <small class="text-muted">No context</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    No events found for the selected criteria
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Context Modal -->
<div class="modal fade" id="contextModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Event Context</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <pre id="contextContent" class="bg-light p-3"></pre>
            </div>
        </div>
    </div>
</div>

<!-- Log File Viewer Modal -->
<div class="modal fade" id="logFileModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Log File Viewer</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="logFileContent" style="max-height: 500px; overflow-y: auto;">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // View context details
    function viewContext(context) {
        $('#contextContent').text(JSON.stringify(context, null, 2));
        $('#contextModal').modal('show');
    }

    // View log file
    function viewLogFile(filename) {
        $('#logFileModal').modal('show');
        $('#logFileContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');

        $.get('ajax/view-log-file.php', {
            file: filename
        }, function(response) {
            if (response.success) {
                let content = '<pre class="bg-dark text-light p-3">' + response.content + '</pre>';
                $('#logFileContent').html(content);
            } else {
                $('#logFileContent').html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        });
    }

    // Download log file
    function downloadLogFile(filename) {
        window.location.href = 'ajax/download-log-file.php?file=' + filename;
    }

    // Clear log file
    function clearLogFile(filename) {
        if (!confirm('Are you sure you want to clear this log file?')) {
            return;
        }

        $.post('ajax/clear-log-file.php', {
            file: filename
        }, function(response) {
            if (response.success) {
                toastr.success('Log file cleared successfully');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(response.message || 'Failed to clear log file');
            }
        });
    }

    // Clear all events
    function clearAllLogs() {
        if (!confirm('Are you sure you want to clear all system events? This action cannot be undone.')) {
            return;
        }

        $.post('ajax/clear-system-events.php', function(response) {
            if (response.success) {
                toastr.success('System events cleared successfully');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(response.message || 'Failed to clear events');
            }
        });
    }

    // Auto-refresh toggle
    let autoRefresh = false;
    let refreshInterval;

    function toggleAutoRefresh() {
        autoRefresh = !autoRefresh;

        if (autoRefresh) {
            refreshInterval = setInterval(() => location.reload(), 30000); // 30 seconds
            toastr.info('Auto-refresh enabled (30s)');
        } else {
            clearInterval(refreshInterval);
            toastr.info('Auto-refresh disabled');
        }
    }

    // Helper functions
    <?php
    function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
    }

    function formatEventType($type)
    {
        $types = [
            'login' => 'Login',
            'device_status' => 'Device Status',
            'webhook_error' => 'Webhook Error',
            'command_error' => 'Command Error',
            'api_error' => 'API Error'
        ];
        return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    function getEventTypeBadge($type)
    {
        $badges = [
            'login' => 'primary',
            'device_status' => 'info',
            'webhook_error' => 'danger',
            'command_error' => 'warning',
            'api_error' => 'danger'
        ];
        return $badges[$type] ?? 'secondary';
    }

    function getSeverityBadge($severity)
    {
        $badges = [
            'error' => 'danger',
            'warning' => 'warning',
            'info' => 'info'
        ];
        return $badges[$severity] ?? 'secondary';
    }

    function getSeverityRowClass($severity)
    {
        $classes = [
            'error' => 'table-danger',
            'warning' => 'table-warning',
            'info' => ''
        ];
        return $classes[$severity] ?? '';
    }
    ?>
</script>

<?php require_once '../../includes/footer.php'; ?>