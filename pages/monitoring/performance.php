<?php

/**
 * Performance Metrics Dashboard
 * System performance monitoring and analytics
 */

require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/NodeJSClient.php';

// Check permission
requireLogin();
requireRole('admin');

$pageTitle = 'Performance Metrics';
$db = Database::getInstance()->getConnection();
$nodeClient = new NodeJSClient();

// Get date range
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Get performance metrics
$metrics = [];

// 1. API Performance
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_calls,
        AVG(response_time) as avg_response_time,
        MAX(response_time) as max_response_time,
        MIN(response_time) as min_response_time,
        SUM(CASE WHEN response_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as successful_calls,
        SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as failed_calls
    FROM api_logs
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$metrics['api'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Message Throughput
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        HOUR(created_at) as hour,
        COUNT(*) as message_count,
        SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming,
        SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM message_logs
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at), HOUR(created_at)
    ORDER BY date DESC, hour DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$metrics['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Device Performance
$stmt = $db->prepare("
    SELECT 
        d.id,
        d.device_name,
        d.status,
        d.retry_count,
        COUNT(DISTINCT ml.id) as total_messages,
        COUNT(DISTINCT CASE WHEN ml.status = 'failed' THEN ml.id END) as failed_messages,
        COUNT(DISTINCT wl.id) as webhook_calls,
        COUNT(DISTINCT CASE WHEN wl.success = FALSE THEN wl.id END) as failed_webhooks,
        AVG(wl.execution_time) as avg_webhook_time
    FROM devices d
    LEFT JOIN message_logs ml ON d.id = ml.device_id 
        AND DATE(ml.created_at) BETWEEN ? AND ?
    LEFT JOIN webhook_logs wl ON d.id = wl.device_id 
        AND DATE(wl.created_at) BETWEEN ? AND ?
    GROUP BY d.id
    ORDER BY total_messages DESC
");
$stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
$metrics['devices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. System Health
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN status = 'connected' THEN id END) as connected_devices,
        COUNT(DISTINCT CASE WHEN status = 'disconnected' THEN id END) as disconnected_devices,
        COUNT(DISTINCT CASE WHEN status IN ('error', 'banned', 'auth_failure') THEN id END) as error_devices,
        COUNT(DISTINCT id) as total_devices
    FROM devices
");
$stmt->execute();
$metrics['health'] = $stmt->fetch(PDO::FETCH_ASSOC);

// 5. Node.js Status
$nodeStatus = $nodeClient->getSystemStatus();

// 6. Database Size
$stmt = $db->query("
    SELECT 
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
        table_rows
    FROM information_schema.TABLES 
    WHERE table_schema = DATABASE()
    ORDER BY (data_length + index_length) DESC
");
$metrics['database'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$summary = [
    'total_api_calls' => array_sum(array_column($metrics['api'], 'total_calls')),
    'avg_response_time' => $metrics['api'] ? round(array_sum(array_column($metrics['api'], 'avg_response_time')) / count($metrics['api']), 3) : 0,
    'total_messages' => array_sum(array_column($metrics['messages'], 'message_count')),
    'success_rate' => 0
];

if ($summary['total_api_calls'] > 0) {
    $successful = array_sum(array_column($metrics['api'], 'successful_calls'));
    $summary['success_rate'] = round(($successful / $summary['total_api_calls']) * 100, 2);
}

require_once '../../includes/header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Performance Metrics</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                    <li class="breadcrumb-item">Monitoring</li>
                    <li class="breadcrumb-item active">Performance</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <!-- Date Filter -->
        <div class="card">
            <div class="card-body">
                <form method="get" action="" class="form-inline">
                    <div class="form-group mr-2">
                        <label class="mr-2">Date From:</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="form-group mr-2">
                        <label class="mr-2">Date To:</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-exchange-alt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total API Calls</span>
                        <span class="info-box-number"><?php echo number_format($summary['total_api_calls']); ?></span>
                        <div class="progress">
                            <div class="progress-bar bg-info" style="width: <?php echo $summary['success_rate']; ?>%"></div>
                        </div>
                        <span class="progress-description">
                            <?php echo $summary['success_rate']; ?>% Success Rate
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-tachometer-alt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Avg Response Time</span>
                        <span class="info-box-number"><?php echo $summary['avg_response_time']; ?>s</span>
                        <?php if ($summary['avg_response_time'] < 1): ?>
                            <span class="badge badge-success">Excellent</span>
                        <?php elseif ($summary['avg_response_time'] < 3): ?>
                            <span class="badge badge-warning">Good</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Needs Attention</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-primary"><i class="fas fa-comments"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Messages</span>
                        <span class="info-box-number"><?php echo number_format($summary['total_messages']); ?></span>
                        <span class="progress-description">
                            Last <?php echo (strtotime($dateTo) - strtotime($dateFrom)) / 86400; ?> days
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-<?php echo $nodeStatus ? 'success' : 'danger'; ?>">
                        <i class="fas fa-server"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Node.js Backend</span>
                        <span class="info-box-number">
                            <?php echo $nodeStatus ? 'Online' : 'Offline'; ?>
                        </span>
                        <?php if ($nodeStatus && isset($nodeStatus['uptime'])): ?>
                            <span class="progress-description">
                                Uptime: <?php echo formatUptime($nodeStatus['uptime']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- API Performance Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">API Performance</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="apiChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Message Throughput Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Message Throughput</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="messageChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Device Performance Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Device Performance</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Status</th>
                            <th>Messages</th>
                            <th>Failed</th>
                            <th>Success Rate</th>
                            <th>Webhooks</th>
                            <th>Avg Response</th>
                            <th>Retry Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metrics['devices'] as $device): ?>
                            <?php
                            $messageSuccessRate = $device['total_messages'] > 0
                                ? round((($device['total_messages'] - $device['failed_messages']) / $device['total_messages']) * 100, 2)
                                : 100;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($device['device_name']); ?></td>
                                <td><?php echo getStatusBadge($device['status']); ?></td>
                                <td><?php echo number_format($device['total_messages']); ?></td>
                                <td>
                                    <?php if ($device['failed_messages'] > 0): ?>
                                        <span class="text-danger"><?php echo number_format($device['failed_messages']); ?></span>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar bg-<?php echo $messageSuccessRate >= 90 ? 'success' : ($messageSuccessRate >= 70 ? 'warning' : 'danger'); ?>"
                                            style="width: <?php echo $messageSuccessRate; ?>%"></div>
                                    </div>
                                    <small><?php echo $messageSuccessRate; ?>%</small>
                                </td>
                                <td><?php echo number_format($device['webhook_calls']); ?></td>
                                <td>
                                    <?php echo $device['avg_webhook_time'] ? round($device['avg_webhook_time'], 3) . 's' : 'N/A'; ?>
                                </td>
                                <td>
                                    <?php if ($device['retry_count'] > 0): ?>
                                        <span class="badge badge-warning"><?php echo $device['retry_count']; ?></span>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Information -->
        <div class="row">
            <!-- Database Size -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Database Usage</h3>
                    </div>
                    <div class="card-body table-responsive p-0" style="max-height: 300px;">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Table</th>
                                    <th>Rows</th>
                                    <th>Size (MB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($metrics['database'] as $table): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($table['table_name']); ?></td>
                                        <td><?php echo number_format($table['table_rows']); ?></td>
                                        <td><?php echo $table['size_mb']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- System Health -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">System Health</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <canvas id="deviceHealthChart"></canvas>
                            </div>
                            <div class="col-6">
                                <dl>
                                    <dt>Total Devices</dt>
                                    <dd><?php echo $metrics['health']['total_devices']; ?></dd>

                                    <dt>Connected</dt>
                                    <dd class="text-success"><?php echo $metrics['health']['connected_devices']; ?></dd>

                                    <dt>Disconnected</dt>
                                    <dd class="text-warning"><?php echo $metrics['health']['disconnected_devices']; ?></dd>

                                    <dt>Error/Banned</dt>
                                    <dd class="text-danger"><?php echo $metrics['health']['error_devices']; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    // Prepare data for charts
    const apiData = <?php echo json_encode($metrics['api']); ?>;
    const messageData = <?php echo json_encode($metrics['messages']); ?>;
    const healthData = <?php echo json_encode($metrics['health']); ?>;

    // API Performance Chart
    const apiCtx = document.getElementById('apiChart').getContext('2d');
    new Chart(apiCtx, {
        type: 'line',
        data: {
            labels: apiData.map(d => d.date).reverse(),
            datasets: [{
                label: 'Total Calls',
                data: apiData.map(d => d.total_calls).reverse(),
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1,
                yAxisID: 'y'
            }, {
                label: 'Avg Response Time (s)',
                data: apiData.map(d => d.avg_response_time).reverse(),
                borderColor: 'rgb(255, 99, 132)',
                tension: 0.1,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left'
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Message Throughput Chart
    const messageCtx = document.getElementById('messageChart').getContext('2d');
    const hourlyData = {};
    messageData.forEach(d => {
        const key = `${d.date} ${String(d.hour).padStart(2, '0')}:00`;
        hourlyData[key] = {
            incoming: d.incoming,
            outgoing: d.outgoing
        };
    });

    new Chart(messageCtx, {
        type: 'bar',
        data: {
            labels: Object.keys(hourlyData).slice(-24).reverse(),
            datasets: [{
                label: 'Incoming',
                data: Object.values(hourlyData).slice(-24).map(d => d.incoming).reverse(),
                backgroundColor: 'rgba(75, 192, 192, 0.8)'
            }, {
                label: 'Outgoing',
                data: Object.values(hourlyData).slice(-24).map(d => d.outgoing).reverse(),
                backgroundColor: 'rgba(54, 162, 235, 0.8)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true
                }
            }
        }
    });

    // Device Health Chart
    const healthCtx = document.getElementById('deviceHealthChart').getContext('2d');
    new Chart(healthCtx, {
        type: 'doughnut',
        data: {
            labels: ['Connected', 'Disconnected', 'Error/Banned'],
            datasets: [{
                data: [
                    healthData.connected_devices,
                    healthData.disconnected_devices,
                    healthData.error_devices
                ],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(255, 99, 132, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });

    // Auto-refresh every 60 seconds
    setInterval(() => {
        location.reload();
    }, 60000);

    // Helper functions
    <?php
    function getStatusBadge($status)
    {
        $badges = [
            'connected' => '<span class="badge badge-success">Connected</span>',
            'disconnected' => '<span class="badge badge-warning">Disconnected</span>',
            'error' => '<span class="badge badge-danger">Error</span>',
            'banned' => '<span class="badge badge-danger">Banned</span>'
        ];
        return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
    }

    function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = $days . 'd';
        if ($hours > 0) $parts[] = $hours . 'h';
        if ($minutes > 0) $parts[] = $minutes . 'm';

        return implode(' ', $parts) ?: '< 1m';
    }
    ?>
</script>

<?php require_once '../../includes/footer.php'; ?>