<?php

/**
 * API Logs Viewer
 * View and filter API call logs
 */

require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/ApiLogger.php';

// Check permission
requireLogin();
if (!hasPermission('view_logs')) {
    redirect('/index.php', 'You do not have permission to view logs');
}

$pageTitle = 'API Logs';
$db = Database::getInstance()->getConnection();
$logger = new ApiLogger();

// Get filter parameters
$filter = [
    'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'endpoint' => $_GET['endpoint'] ?? '',
    'method' => $_GET['method'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Build query
$query = "
    SELECT 
        l.*,
        d.device_name,
        d.phone_number,
        u.username
    FROM api_logs l
    LEFT JOIN devices d ON l.device_id = d.id
    LEFT JOIN users u ON l.user_id = u.id
    WHERE 1=1
";

$params = [];

// Apply filters
if ($filter['date_from']) {
    $query .= " AND DATE(l.created_at) >= ?";
    $params[] = $filter['date_from'];
}

if ($filter['date_to']) {
    $query .= " AND DATE(l.created_at) <= ?";
    $params[] = $filter['date_to'];
}

if ($filter['endpoint']) {
    $query .= " AND l.endpoint LIKE ?";
    $params[] = '%' . $filter['endpoint'] . '%';
}

if ($filter['method']) {
    $query .= " AND l.method = ?";
    $params[] = $filter['method'];
}

if ($filter['status']) {
    if ($filter['status'] === 'success') {
        $query .= " AND l.response_code BETWEEN 200 AND 299";
    } elseif ($filter['status'] === 'error') {
        $query .= " AND l.response_code >= 400";
    }
}

if ($filter['search']) {
    $query .= " AND (l.request_data LIKE ? OR l.response_data LIKE ? OR l.ip_address LIKE ?)";
    $params[] = '%' . $filter['search'] . '%';
    $params[] = '%' . $filter['search'] . '%';
    $params[] = '%' . $filter['search'] . '%';
}

$query .= " ORDER BY l.created_at DESC LIMIT 1000";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = $logger->getApiStatistics($filter['date_from'], $filter['date_to']);

// Get unique endpoints for filter
$endpoints = $db->query("
    SELECT DISTINCT endpoint 
    FROM api_logs 
    ORDER BY endpoint
")->fetchAll(PDO::FETCH_COLUMN);

require_once '../../includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">API Logs</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                    <li class="breadcrumb-item">Logs</li>
                    <li class="breadcrumb-item active">API Logs</li>
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
                        <h3><?php echo number_format($stats['total_calls']); ?></h3>
                        <p>Total API Calls</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($stats['successful_calls']); ?></h3>
                        <p>Successful Calls</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo number_format($stats['failed_calls']); ?></h3>
                        <p>Failed Calls</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo round($stats['avg_response_time'], 2); ?>s</h3>
                        <p>Avg Response Time</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filter Logs</h3>
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
                                <label>Endpoint</label>
                                <select name="endpoint" class="form-control select2">
                                    <option value="">All Endpoints</option>
                                    <?php foreach ($endpoints as $endpoint): ?>
                                        <option value="<?php echo htmlspecialchars($endpoint); ?>"
                                            <?php echo $filter['endpoint'] === $endpoint ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($endpoint); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Method</label>
                                <select name="method" class="form-control">
                                    <option value="">All Methods</option>
                                    <option value="GET" <?php echo $filter['method'] === 'GET' ? 'selected' : ''; ?>>GET</option>
                                    <option value="POST" <?php echo $filter['method'] === 'POST' ? 'selected' : ''; ?>>POST</option>
                                    <option value="PUT" <?php echo $filter['method'] === 'PUT' ? 'selected' : ''; ?>>PUT</option>
                                    <option value="DELETE" <?php echo $filter['method'] === 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="success" <?php echo $filter['status'] === 'success' ? 'selected' : ''; ?>>Success</option>
                                    <option value="error" <?php echo $filter['status'] === 'error' ? 'selected' : ''; ?>>Error</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
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
                            <a href="api-logs.php" class="btn btn-default">
                                <i class="fas fa-undo"></i> Reset
                            </a>
                            <button type="button" class="btn btn-success float-right" onclick="exportLogs()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">API Call Logs</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th width="150">Timestamp</th>
                            <th width="80">Method</th>
                            <th>Endpoint</th>
                            <th>Device</th>
                            <th>User</th>
                            <th width="80">Status</th>
                            <th width="100">Response Time</th>
                            <th width="100">IP Address</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo getMethodBadgeClass($log['method']); ?>">
                                        <?php echo htmlspecialchars($log['method']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['endpoint']); ?></td>
                                <td>
                                    <?php if ($log['device_name']): ?>
                                        <small>
                                            <?php echo htmlspecialchars($log['device_name']); ?><br>
                                            <?php echo htmlspecialchars($log['phone_number']); ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">N/A</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $log['username'] ? htmlspecialchars($log['username']) : '<small class="text-muted">System</small>'; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo getStatusBadgeClass($log['response_code']); ?>">
                                        <?php echo $log['response_code']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo number_format($log['response_time'], 3); ?>s
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-xs btn-info" onclick="viewLogDetails(<?php echo $log['id']; ?>)">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">
                                    No logs found for the selected criteria
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">API Log Details</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="logDetailsContent">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Select2
    $(document).ready(function() {
        $('.select2').select2();
    });

    // View log details
    function viewLogDetails(logId) {
        $('#logDetailsModal').modal('show');
        $('#logDetailsContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');

        $.get('ajax/get-log-details.php', {
            id: logId,
            type: 'api'
        }, function(response) {
            if (response.success) {
                let html = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Timestamp:</strong> ${response.data.created_at}<br>
                        <strong>Method:</strong> ${response.data.method}<br>
                        <strong>Endpoint:</strong> ${response.data.endpoint}<br>
                        <strong>Response Code:</strong> ${response.data.response_code}<br>
                        <strong>Response Time:</strong> ${response.data.response_time}s
                    </div>
                    <div class="col-md-6">
                        <strong>IP Address:</strong> ${response.data.ip_address}<br>
                        <strong>User Agent:</strong> <small>${response.data.user_agent || 'N/A'}</small><br>
                        <strong>Device:</strong> ${response.data.device_name || 'N/A'}<br>
                        <strong>User:</strong> ${response.data.username || 'System'}
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Request Data</h5>
                        <pre class="bg-light p-2">${JSON.stringify(JSON.parse(response.data.request_data || '{}'), null, 2)}</pre>
                    </div>
                    <div class="col-md-6">
                        <h5>Response Data</h5>
                        <pre class="bg-light p-2">${JSON.stringify(JSON.parse(response.data.response_data || '{}'), null, 2)}</pre>
                    </div>
                </div>
            `;
                $('#logDetailsContent').html(html);
            } else {
                $('#logDetailsContent').html('<div class="alert alert-danger">Failed to load log details</div>');
            }
        });
    }

    // Export logs
    function exportLogs() {
        let params = new URLSearchParams(window.location.search);
        params.append('export', '1');
        window.location.href = 'export-logs.php?' + params.toString();
    }

    // Helper functions for badge classes
    <?php
    function getMethodBadgeClass($method)
    {
        $classes = [
            'GET' => 'primary',
            'POST' => 'success',
            'PUT' => 'warning',
            'DELETE' => 'danger'
        ];
        return $classes[$method] ?? 'secondary';
    }

    function getStatusBadgeClass($code)
    {
        if ($code >= 200 && $code < 300) return 'success';
        if ($code >= 300 && $code < 400) return 'info';
        if ($code >= 400 && $code < 500) return 'warning';
        if ($code >= 500) return 'danger';
        return 'secondary';
    }
    ?>
</script>

<?php require_once '../../includes/footer.php'; ?>