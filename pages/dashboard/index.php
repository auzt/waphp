<?php

/**
 * ===============================================================================
 * DASHBOARD INDEX - WhatsApp Monitor Dashboard Utama
 * ===============================================================================
 * Halaman dashboard utama dengan statistik real-time
 * - Device statistics
 * - Message analytics  
 * - System monitoring
 * - Recent activities
 * - Performance metrics
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

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize classes
$device = new Device($db);
$apiLogger = new ApiLogger($db);

// Get current user info
$currentUser = getCurrentUser();

// Get dashboard statistics
try {
    // Device statistics
    $deviceStats = $device->getDashboardStats($currentUser['id']);

    // Message statistics for today
    $messageStats = $device->getMessageStats($currentUser['id']);

    // API usage statistics
    $apiStats = $apiLogger->getApiStats($currentUser['id']);

    // Recent activities
    $recentActivities = $device->getRecentActivities($currentUser['id'], 10);

    // System performance
    $systemPerformance = getSystemPerformance();

    // Chart data for last 7 days
    $chartData = $device->getChartData($currentUser['id'], 7);
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error_message = "Failed to load dashboard data. Please refresh the page.";
}

// Page title
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

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
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Tempusdominus Bootstrap 4 -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
    <!-- iCheck -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <!-- JQVMap -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/jqvmap/jqvmap.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../../assets/adminlte/dist/css/adminlte.min.css">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <!-- Daterange picker -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/daterangepicker/daterangepicker.css">
    <!-- Chart.js -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/chart.js/Chart.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/custom/css/custom.css">

    <style>
        .info-box {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .info-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .activity-item {
            padding: 10px;
            border-left: 3px solid transparent;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background-color: #f8f9fa;
            border-left-color: #007bff;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .device-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .performance-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .performance-good {
            background-color: #28a745;
        }

        .performance-warning {
            background-color: #ffc107;
        }

        .performance-danger {
            background-color: #dc3545;
        }

        @media (max-width: 768px) {
            .device-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <!-- Preloader -->
        <div class="preloader flex-column justify-content-center align-items-center">
            <img class="animation__shake" src="../../assets/adminlte/dist/img/AdminLTELogo.png" alt="AdminLTELogo" height="60" width="60">
        </div>

        <!-- Navbar -->
        <?php include '../../includes/navbar.php'; ?>

        <!-- Main Sidebar Container -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Dashboard</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item active">Dashboard</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Welcome Card -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card card-primary card-outline">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h4 class="mb-1">Welcome back, <?php echo htmlspecialchars($currentUser['full_name']); ?>!</h4>
                                            <p class="text-muted mb-0">
                                                Here's what's happening with your WhatsApp devices today.
                                                Last login: <?php echo $currentUser['last_login'] ? date('M d, Y h:i A', strtotime($currentUser['last_login'])) : 'First time'; ?>
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <div class="info-box bg-gradient-info">
                                                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">Current Time</span>
                                                    <span class="info-box-number" id="current-time">
                                                        <?php echo date('H:i:s'); ?>
                                                    </span>
                                                    <span class="info-box-more">
                                                        <?php echo date('l, F d, Y'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row">
                        <!-- Total Devices -->
                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-mobile-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Devices</span>
                                    <span class="info-box-number">
                                        <?php echo number_format($deviceStats['total_devices'] ?? 0); ?>
                                    </span>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" style="width: 100%"></div>
                                    </div>
                                    <span class="progress-description">
                                        All registered devices
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Connected Devices -->
                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Connected</span>
                                    <span class="info-box-number">
                                        <?php echo number_format($deviceStats['connected_devices'] ?? 0); ?>
                                    </span>
                                    <div class="progress">
                                        <div class="progress-bar bg-success"
                                            style="width: <?php echo $deviceStats['total_devices'] > 0 ? ($deviceStats['connected_devices'] / $deviceStats['total_devices'] * 100) : 0; ?>%"></div>
                                    </div>
                                    <span class="progress-description">
                                        <?php
                                        $connectionRate = $deviceStats['total_devices'] > 0 ?
                                            round(($deviceStats['connected_devices'] / $deviceStats['total_devices']) * 100, 1) : 0;
                                        echo $connectionRate;
                                        ?>% connection rate
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Messages Today -->
                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-envelope"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Messages Today</span>
                                    <span class="info-box-number">
                                        <?php echo number_format($messageStats['messages_today'] ?? 0); ?>
                                    </span>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" style="width: 70%"></div>
                                    </div>
                                    <span class="progress-description">
                                        <?php echo number_format($messageStats['incoming_today'] ?? 0); ?> incoming,
                                        <?php echo number_format($messageStats['outgoing_today'] ?? 0); ?> outgoing
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- API Calls -->
                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-code"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">API Calls</span>
                                    <span class="info-box-number">
                                        <?php echo number_format($apiStats['total_calls'] ?? 0); ?>
                                    </span>
                                    <div class="progress">
                                        <div class="progress-bar bg-danger"
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
                    </div>

                    <!-- Device Status Overview -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Device Status Overview</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="deviceStatusChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Message Activity (Last 7 Days)</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="messageActivityChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Device Grid -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Device Status Grid</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="refreshDeviceGrid()">
                                            <i class="fas fa-sync-alt"></i> Refresh
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="device-grid" class="device-grid">
                                        <!-- Device cards akan di-load via AJAX -->
                                        <div class="text-center py-5">
                                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                            <p class="mt-3 text-muted">Loading devices...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activities & System Performance -->
                    <div class="row">
                        <!-- Recent Activities -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Recent Activities</h3>
                                    <div class="card-tools">
                                        <span class="badge badge-info"><?php echo count($recentActivities); ?> items</span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Device</th>
                                                    <th>Activity</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($recentActivities)): ?>
                                                    <?php foreach ($recentActivities as $activity): ?>
                                                        <tr class="activity-item">
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?php echo timeAgo($activity['created_at']); ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($activity['device_name']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($activity['phone_number']); ?></small>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($activity['activity_description']); ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $statusClass = getStatusClass($activity['status']);
                                                                $statusText = getStatusText($activity['status']);
                                                                ?>
                                                                <span class="badge badge-<?php echo $statusClass; ?> status-badge">
                                                                    <?php echo $statusText; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center py-4">
                                                            <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                                                            <p class="text-muted">No recent activities found</p>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <a href="../logs/activity-logs.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-list"></i> View All Activities
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- System Performance -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">System Performance</h3>
                                </div>
                                <div class="card-body">
                                    <!-- Server Status -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Server Status</span>
                                            <span class="performance-indicator performance-good"></span>
                                        </div>
                                        <small class="text-muted">All systems operational</small>
                                    </div>

                                    <!-- Memory Usage -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Memory Usage</span>
                                            <span><?php echo $systemPerformance['memory_usage'] ?? 'N/A'; ?>%</span>
                                        </div>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-info"
                                                style="width: <?php echo $systemPerformance['memory_usage'] ?? 0; ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- CPU Usage -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>CPU Usage</span>
                                            <span><?php echo $systemPerformance['cpu_usage'] ?? 'N/A'; ?>%</span>
                                        </div>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-warning"
                                                style="width: <?php echo $systemPerformance['cpu_usage'] ?? 0; ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Disk Usage -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Disk Usage</span>
                                            <span><?php echo $systemPerformance['disk_usage'] ?? 'N/A'; ?>%</span>
                                        </div>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-danger"
                                                style="width: <?php echo $systemPerformance['disk_usage'] ?? 0; ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Node.js Status -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Node.js Backend</span>
                                            <span class="performance-indicator <?php echo checkNodeJSStatus() ? 'performance-good' : 'performance-danger'; ?>"></span>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo checkNodeJSStatus() ? 'Connected' : 'Disconnected'; ?>
                                        </small>
                                    </div>

                                    <!-- Database Status -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Database</span>
                                            <span class="performance-indicator performance-good"></span>
                                        </div>
                                        <small class="text-muted">Connected</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Quick Actions</h3>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="../devices/add.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Add New Device
                                        </a>
                                        <a href="../devices/index.php" class="btn btn-info">
                                            <i class="fas fa-mobile-alt"></i> Manage Devices
                                        </a>
                                        <a href="../api-tokens/index.php" class="btn btn-warning">
                                            <i class="fas fa-key"></i> API Tokens
                                        </a>
                                        <a href="../logs/api-logs.php" class="btn btn-secondary">
                                            <i class="fas fa-chart-bar"></i> View Logs
                                        </a>
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

        <!-- Control Sidebar -->
        <aside class="control-sidebar control-sidebar-dark">
            <!-- Control sidebar content goes here -->
        </aside>
    </div>

    <!-- jQuery -->
    <script src="../../assets/adminlte/plugins/jquery/jquery.min.js"></script>
    <!-- jQuery UI 1.11.4 -->
    <script src="../../assets/adminlte/plugins/jquery-ui/jquery-ui.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="../../assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- ChartJS -->
    <script src="../../assets/adminlte/plugins/chart.js/Chart.min.js"></script>
    <!-- Sparkline -->
    <script src="../../assets/adminlte/plugins/sparklines/sparkline.js"></script>
    <!-- jQuery Knob Chart -->
    <script src="../../assets/adminlte/plugins/jquery-knob/jquery.knob.min.js"></script>
    <!-- daterangepicker -->
    <script src="../../assets/adminlte/plugins/moment/moment.min.js"></script>
    <script src="../../assets/adminlte/plugins/daterangepicker/daterangepicker.js"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script src="../../assets/adminlte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
    <!-- overlayScrollbars -->
    <script src="../../assets/adminlte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
    <!-- AdminLTE App -->
    <script src="../../assets/adminlte/dist/js/adminlte.js"></script>
    <!-- Dashboard JS -->
    <script src="../../assets/custom/js/dashboard.js"></script>

    <script>
        $(function() {
            'use strict';

            // Real-time clock
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                $('#current-time').text(timeString);
            }

            // Update clock every second
            setInterval(updateClock, 1000);

            // Device Status Chart
            const deviceStatusCtx = document.getElementById('deviceStatusChart').getContext('2d');
            const deviceStatusChart = new Chart(deviceStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Connected', 'Disconnected', 'Pairing', 'Error', 'Banned'],
                    datasets: [{
                        data: [
                            <?php echo $deviceStats['connected_devices'] ?? 0; ?>,
                            <?php echo $deviceStats['disconnected_devices'] ?? 0; ?>,
                            <?php echo $deviceStats['pairing_devices'] ?? 0; ?>,
                            <?php echo $deviceStats['error_devices'] ?? 0; ?>,
                            <?php echo $deviceStats['banned_devices'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            '#28a745', // Green for connected
                            '#6c757d', // Gray for disconnected
                            '#17a2b8', // Blue for pairing
                            '#ffc107', // Yellow for error
                            '#dc3545' // Red for banned
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'bottom'
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });

            // Message Activity Chart
            const messageActivityCtx = document.getElementById('messageActivityChart').getContext('2d');
            const messageActivityChart = new Chart(messageActivityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartData['labels'] ?? []); ?>,
                    datasets: [{
                        label: 'Incoming Messages',
                        data: <?php echo json_encode($chartData['incoming'] ?? []); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Outgoing Messages',
                        data: <?php echo json_encode($chartData['outgoing'] ?? []); ?>,
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

            // Load device grid
            loadDeviceGrid();

            // Auto refresh device grid every 30 seconds
            setInterval(loadDeviceGrid, 30000);
        });

        // Load device grid via AJAX
        function loadDeviceGrid() {
            $.ajax({
                url: '../../api/devices.php?action=grid',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderDeviceGrid(response.data);
                    } else {
                        $('#device-grid').html(`
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-3"></i>
                        <p class="text-muted">Failed to load devices: ${response.message}</p>
                        <button class="btn btn-primary" onclick="loadDeviceGrid()">
                            <i class="fas fa-refresh"></i> Retry
                        </button>
                    </div>
                `);
                    }
                },
                error: function(xhr, status, error) {
                    $('#device-grid').html(`
                <div class="col-12 text-center py-5">
                    <i class="fas fa-wifi fa-2x text-danger mb-3"></i>
                    <p class="text-muted">Connection error. Please check your internet connection.</p>
                    <button class="btn btn-primary" onclick="loadDeviceGrid()">
                        <i class="fas fa-refresh"></i> Retry
                    </button>
                </div>
            `);
                }
            });
        }

        // Render device grid
        function renderDeviceGrid(devices) {
            if (!devices || devices.length === 0) {
                $('#device-grid').html(`
            <div class="col-12 text-center py-5">
                <i class="fas fa-mobile-alt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No devices found</h5>
                <p class="text-muted">Add your first WhatsApp device to get started.</p>
                <a href="../devices/add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Device
                </a>
            </div>
        `);
                return;
            }

            let gridHtml = '';
            devices.forEach(device => {
                const statusConfig = getStatusConfig(device.status);
                const lastSeen = device.last_seen ? timeAgo(device.last_seen) : 'Never';
                const onlineStatus = device.is_online ? 'online' : 'offline';

                gridHtml += `
            <div class="card device-card" data-device-id="${device.id}">
                <div class="card-header bg-${statusConfig.class}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 text-white">
                            <i class="fas fa-mobile-alt me-2"></i>
                            ${escapeHtml(device.device_name)}
                        </h6>
                        <div class="device-actions">
                            <div class="btn-group btn-group-sm">
                                ${getDeviceActionButtons(device)}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <p class="mb-1">
                                <strong>Phone:</strong> 
                                <span class="text-muted">${escapeHtml(device.phone_number)}</span>
                            </p>
                            <p class="mb-1">
                                <strong>WhatsApp:</strong> 
                                <span class="text-muted">${escapeHtml(device.whatsapp_name || 'Not set')}</span>
                            </p>
                            <p class="mb-1">
                                <strong>Last Seen:</strong> 
                                <span class="text-muted">${lastSeen}</span>
                            </p>
                        </div>
                        <div class="col-4 text-center">
                            <div class="device-status-indicator">
                                <span class="badge badge-${statusConfig.class} badge-lg">
                                    <i class="fas ${statusConfig.icon}"></i>
                                </span>
                                <p class="mt-2 mb-0">
                                    <small class="text-muted">${statusConfig.text}</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-2">
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="metric">
                                <h6 class="text-primary mb-0">${device.messages_today || 0}</h6>
                                <small class="text-muted">Messages Today</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="metric">
                                <h6 class="text-${onlineStatus === 'online' ? 'success' : 'secondary'} mb-0">
                                    <i class="fas fa-circle"></i>
                                </h6>
                                <small class="text-muted">${onlineStatus.charAt(0).toUpperCase() + onlineStatus.slice(1)}</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="metric">
                                <h6 class="text-warning mb-0">${device.retry_count || 0}</h6>
                                <small class="text-muted">Retries</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Owner: ${escapeHtml(device.owner || 'Unknown')}
                        </small>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewDeviceDetails(${device.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="editDevice(${device.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="showQRCode(${device.id})">
                                        <i class="fas fa-qrcode me-2"></i>QR Code
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="restartDevice(${device.id})">
                                        <i class="fas fa-redo me-2"></i>Restart
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteDevice(${device.id})">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
            });

            $('#device-grid').html(gridHtml);
        }

        // Get status configuration
        function getStatusConfig(status) {
            const configs = {
                'connecting': {
                    class: 'warning',
                    icon: 'fa-spinner fa-spin',
                    text: 'Connecting...'
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
                    text: 'Scan QR Code'
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

        // Get device action buttons based on status
        function getDeviceActionButtons(device) {
            let buttons = '';

            switch (device.status) {
                case 'disconnected':
                case 'error':
                case 'timeout':
                case 'auth_failure':
                    buttons = `<button class="btn btn-success btn-sm" onclick="connectDevice(${device.id})" title="Connect">
                        <i class="fas fa-play"></i>
                       </button>`;
                    break;

                case 'connected':
                    buttons = `<button class="btn btn-warning btn-sm" onclick="disconnectDevice(${device.id})" title="Disconnect">
                        <i class="fas fa-stop"></i>
                       </button>`;
                    break;

                case 'connecting':
                    buttons = `<button class="btn btn-secondary btn-sm" disabled title="Connecting...">
                        <i class="fas fa-spinner fa-spin"></i>
                       </button>`;
                    break;

                case 'pairing':
                    buttons = `<button class="btn btn-info btn-sm" onclick="showQRCode(${device.id})" title="Show QR Code">
                        <i class="fas fa-qrcode"></i>
                       </button>`;
                    break;

                case 'banned':
                    buttons = `<button class="btn btn-danger btn-sm" disabled title="Device Banned">
                        <i class="fas fa-ban"></i>
                       </button>`;
                    break;

                default:
                    buttons = `<button class="btn btn-primary btn-sm" onclick="connectDevice(${device.id})" title="Connect">
                        <i class="fas fa-play"></i>
                       </button>`;
            }

            return buttons;
        }

        // Device action functions
        function connectDevice(deviceId) {
            if (confirm('Connect this device to WhatsApp?')) {
                $.ajax({
                    url: '../../api/devices.php',
                    method: 'POST',
                    data: JSON.stringify({
                        action: 'connect',
                        device_id: deviceId
                    }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'Device connection initiated');
                            setTimeout(loadDeviceGrid, 2000);
                        } else {
                            showToast('error', response.message || 'Failed to connect device');
                        }
                    },
                    error: function() {
                        showToast('error', 'Connection error. Please try again.');
                    }
                });
            }
        }

        function disconnectDevice(deviceId) {
            if (confirm('Disconnect this device from WhatsApp?')) {
                $.ajax({
                    url: '../../api/devices.php',
                    method: 'POST',
                    data: JSON.stringify({
                        action: 'disconnect',
                        device_id: deviceId
                    }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'Device disconnected successfully');
                            setTimeout(loadDeviceGrid, 1000);
                        } else {
                            showToast('error', response.message || 'Failed to disconnect device');
                        }
                    },
                    error: function() {
                        showToast('error', 'Connection error. Please try again.');
                    }
                });
            }
        }

        function restartDevice(deviceId) {
            if (confirm('Restart this device connection?')) {
                $.ajax({
                    url: '../../api/devices.php',
                    method: 'POST',
                    data: JSON.stringify({
                        action: 'restart',
                        device_id: deviceId
                    }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'Device restart initiated');
                            setTimeout(loadDeviceGrid, 2000);
                        } else {
                            showToast('error', response.message || 'Failed to restart device');
                        }
                    },
                    error: function() {
                        showToast('error', 'Connection error. Please try again.');
                    }
                });
            }
        }

        function deleteDevice(deviceId) {
            if (confirm('Are you sure you want to delete this device? This action cannot be undone.')) {
                $.ajax({
                    url: `../../api/devices.php?id=${deviceId}`,
                    method: 'DELETE',
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'Device deleted successfully');
                            loadDeviceGrid();
                        } else {
                            showToast('error', response.message || 'Failed to delete device');
                        }
                    },
                    error: function() {
                        showToast('error', 'Connection error. Please try again.');
                    }
                });
            }
        }

        function showQRCode(deviceId) {
            // Redirect to device details page with QR modal
            window.location.href = `../devices/view.php?id=${deviceId}&action=qr`;
        }

        function viewDeviceDetails(deviceId) {
            window.location.href = `../devices/view.php?id=${deviceId}`;
        }

        function editDevice(deviceId) {
            window.location.href = `../devices/edit.php?id=${deviceId}`;
        }

        // Refresh device grid
        function refreshDeviceGrid() {
            loadDeviceGrid();
            showToast('info', 'Refreshing device status...');
        }

        // Utility functions
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

        function showToast(type, message) {
            // Create toast notification
            const toast = $(`
        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="5000">
            <div class="toast-header">
                <i class="fas fa-${type === 'success' ? 'check-circle text-success' : 
                                  type === 'error' ? 'exclamation-circle text-danger' : 
                                  'info-circle text-info'} me-2"></i>
                <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                <small class="text-muted">Just now</small>
                <button type="button" class="btn-close" data-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `);

            // Add to toast container (create if doesn't exist)
            if (!$('#toast-container').length) {
                $('body').append('<div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>');
            }

            $('#toast-container').append(toast);
            toast.toast('show');

            // Remove toast after it's hidden
            toast.on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            // Refresh charts data
            $.ajax({
                url: '../../api/dashboard.php?action=refresh',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        // Update statistics in info boxes
                        updateStatistics(response.data);
                    }
                },
                error: function() {
                    console.log('Failed to refresh dashboard data');
                }
            });
        }, 300000); // 5 minutes

        function updateStatistics(data) {
            // Update device statistics
            $('.info-box-number').each(function(index) {
                const $this = $(this);
                const parent = $this.closest('.info-box');

                if (parent.find('.fa-mobile-alt').length) {
                    $this.text(data.devices.total || 0);
                } else if (parent.find('.fa-check-circle').length) {
                    $this.text(data.devices.connected || 0);
                } else if (parent.find('.fa-envelope').length) {
                    $this.text(data.messages.today || 0);
                } else if (parent.find('.fa-code').length) {
                    $this.text(data.api.total_calls || 0);
                }
            });
        }

        // Initialize tooltips and popovers
        $(function() {
            $('[data-toggle="tooltip"]').tooltip();
            $('[data-toggle="popover"]').popover();
        });

        // Page visibility change handler
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                // Page became visible, refresh data
                loadDeviceGrid();
            }
        });

        // Error handling for AJAX requests
        $(document).ajaxError(function(event, xhr, settings, thrownError) {
            if (xhr.status === 401) {
                // Unauthorized, redirect to login
                window.location.href = '../auth/login.php';
            } else if (xhr.status === 403) {
                // Forbidden
                showToast('error', 'Access denied. You do not have permission to perform this action.');
            } else if (xhr.status >= 500) {
                // Server error
                showToast('error', 'Server error. Please try again later.');
            }
        });
    </script>

    <style>
        .device-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .device-status-indicator {
            text-align: center;
        }

        .badge-lg {
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .metric h6 {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .metric small {
            font-size: 0.75rem;
        }

        .toast {
            min-width: 300px;
        }

        .activity-item:hover {
            transform: translateX(5px);
        }

        @media (max-width: 768px) {
            .device-grid {
                grid-template-columns: 1fr;
            }

            .info-box {
                margin-bottom: 15px;
            }

            .chart-container {
                height: 250px;
            }
        }

        @media (max-width: 576px) {
            .btn-group-sm .btn {
                padding: 0.25rem 0.4rem;
                font-size: 0.75rem;
            }

            .card-header h6 {
                font-size: 0.9rem;
            }

            .metric h6 {
                font-size: 1rem;
            }
        }

        /* Loading animation */
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

        .loading {
            animation: pulse 2s infinite;
        }

        /* Status indicators animation */
        .fa-spinner {
            animation: fa-spin 2s infinite linear;
        }

        /* Performance indicators */
        .performance-indicator {
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
        }

        /* Custom scrollbar for activities */
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }

        .table-responsive::-webkit-scrollbar {
            width: 6px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>

    <?php
    // Helper functions for the dashboard

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

    function timeAgo($datetime)
    {
        $time = time() - strtotime($datetime);

        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . ' minutes ago';
        if ($time < 86400) return floor($time / 3600) . ' hours ago';
        if ($time < 604800) return floor($time / 86400) . ' days ago';

        return date('M d, Y', strtotime($datetime));
    }

    function getSystemPerformance()
    {
        // Dummy data - in real implementation, get from system
        return [
            'memory_usage' => rand(30, 80),
            'cpu_usage' => rand(10, 60),
            'disk_usage' => rand(20, 70)
        ];
    }

    function checkNodeJSStatus()
    {
        // Simple check - in real implementation, ping Node.js endpoint
        $nodejs_url = getSetting('nodejs_url') . '/health';

        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'method' => 'GET'
            ]
        ]);

        $result = @file_get_contents($nodejs_url, false, $context);
        return $result !== false;
    }
    ?>

</body>

</html>