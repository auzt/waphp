<?php

/**
 * Webhook Settings Page
 * WhatsApp Monitor - AdminLTE
 */

require_once '../../includes/session.php';
require_once '../../classes/Database.php';
require_once '../../classes/Settings.php';
require_once '../../classes/NodeJSClient.php';
require_once '../../classes/Auth.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if user has admin or operator role
$user = Auth::getCurrentUser();
if (!in_array($user['role'], ['admin', 'operator'])) {
    header('Location: ../dashboard/index.php?error=access_denied');
    exit;
}

$database = new Database();
$settings = new Settings($database);
$nodeClient = new NodeJSClient($database);

$message = '';
$messageType = '';

// Handle form submission
if ($_POST) {
    try {
        $webhookUrl = trim($_POST['webhook_url'] ?? '');
        $webhookTimeout = (int)($_POST['webhook_timeout'] ?? 30);
        $webhookRetryAttempts = (int)($_POST['webhook_retry_attempts'] ?? 3);
        $webhookRetryDelay = (int)($_POST['webhook_retry_delay'] ?? 2000);
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');
        $webhookEnabled = isset($_POST['webhook_enabled']) ? 1 : 0;

        // Validation
        if ($webhookEnabled && empty($webhookUrl)) {
            throw new Exception('Webhook URL is required when webhook is enabled');
        }

        if ($webhookEnabled && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid webhook URL format');
        }

        if ($webhookTimeout < 5 || $webhookTimeout > 120) {
            throw new Exception('Webhook timeout must be between 5-120 seconds');
        }

        if ($webhookRetryAttempts < 0 || $webhookRetryAttempts > 10) {
            throw new Exception('Retry attempts must be between 0-10');
        }

        if ($webhookRetryDelay < 1000 || $webhookRetryDelay > 30000) {
            throw new Exception('Retry delay must be between 1000-30000 milliseconds');
        }

        // Save settings
        $settings->set('webhook_url', $webhookUrl);
        $settings->set('webhook_timeout', $webhookTimeout);
        $settings->set('webhook_retry_attempts', $webhookRetryAttempts);
        $settings->set('webhook_retry_delay', $webhookRetryDelay);
        $settings->set('webhook_secret', $webhookSecret);
        $settings->set('webhook_enabled', $webhookEnabled);

        // Test webhook if action is test
        if (isset($_POST['action']) && $_POST['action'] === 'test' && $webhookEnabled && $webhookUrl) {
            $testResult = $nodeClient->testWebhook($webhookUrl, [
                'event' => 'webhook_test',
                'message' => 'Test webhook from WhatsApp Monitor',
                'timestamp' => time(),
                'test_data' => [
                    'session_id' => 'test_session',
                    'from' => 'system'
                ]
            ]);

            if ($testResult['success']) {
                $message = 'Webhook settings saved and test successful! Response time: ' .
                    ($testResult['response_time'] ?? 'unknown') . 'ms';
                $messageType = 'success';
            } else {
                $message = 'Settings saved but webhook test failed: ' . $testResult['error'];
                $messageType = 'warning';
            }
        } else {
            $message = 'Webhook settings saved successfully!';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current settings
$currentSettings = [
    'webhook_url' => $settings->get('webhook_url', ''),
    'webhook_timeout' => $settings->get('webhook_timeout', 30),
    'webhook_retry_attempts' => $settings->get('webhook_retry_attempts', 3),
    'webhook_retry_delay' => $settings->get('webhook_retry_delay', 2000),
    'webhook_secret' => $settings->get('webhook_secret', ''),
    'webhook_enabled' => $settings->get('webhook_enabled', 0)
];

// Get webhook statistics
$webhookStats = $nodeClient->getWebhookStatistics();
$recentWebhooks = $nodeClient->getRecentWebhookLogs(10);

$pageTitle = 'Webhook Settings';
require_once '../../includes/header.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-webhook"></i> Webhook Settings</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="system.php">Settings</a></li>
                        <li class="breadcrumb-item active">Webhook</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="icon fas fa-<?= $messageType === 'success' ? 'check' : ($messageType === 'warning' ? 'exclamation-triangle' : 'times') ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Webhook Configuration -->
                <div class="col-md-8">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cog"></i> Webhook Configuration</h3>
                        </div>
                        <form method="POST" id="webhookForm">
                            <div class="card-body">

                                <!-- Enable Webhook -->
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="webhook_enabled"
                                            name="webhook_enabled" <?= $currentSettings['webhook_enabled'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="webhook_enabled">
                                            <strong>Enable Webhook</strong>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Enable webhook notifications for WhatsApp events
                                    </small>
                                </div>

                                <!-- Webhook URL -->
                                <div class="form-group">
                                    <label for="webhook_url">Webhook URL</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-link"></i></span>
                                        </div>
                                        <input type="url" class="form-control" id="webhook_url" name="webhook_url"
                                            value="<?= htmlspecialchars($currentSettings['webhook_url']) ?>"
                                            placeholder="https://your-server.com/webhook"
                                            <?= !$currentSettings['webhook_enabled'] ? 'disabled' : '' ?>>
                                    </div>
                                    <small class="form-text text-muted">
                                        URL endpoint to receive webhook notifications from Node.js backend
                                    </small>
                                </div>

                                <!-- Webhook Secret -->
                                <div class="form-group">
                                    <label for="webhook_secret">Webhook Secret</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                                        </div>
                                        <input type="password" class="form-control" id="webhook_secret" name="webhook_secret"
                                            value="<?= htmlspecialchars($currentSettings['webhook_secret']) ?>"
                                            placeholder="Enter webhook secret key"
                                            <?= !$currentSettings['webhook_enabled'] ? 'disabled' : '' ?>>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" id="generateSecret">
                                                <i class="fas fa-random"></i> Generate
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Secret key for webhook authentication (optional but recommended)
                                    </small>
                                </div>

                                <div class="row">
                                    <!-- Timeout -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="webhook_timeout">Timeout (seconds)</label>
                                            <input type="number" class="form-control" id="webhook_timeout" name="webhook_timeout"
                                                value="<?= $currentSettings['webhook_timeout'] ?>" min="5" max="120"
                                                <?= !$currentSettings['webhook_enabled'] ? 'disabled' : '' ?>>
                                            <small class="form-text text-muted">Request timeout (5-120 seconds)</small>
                                        </div>
                                    </div>

                                    <!-- Retry Attempts -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="webhook_retry_attempts">Retry Attempts</label>
                                            <input type="number" class="form-control" id="webhook_retry_attempts" name="webhook_retry_attempts"
                                                value="<?= $currentSettings['webhook_retry_attempts'] ?>" min="0" max="10"
                                                <?= !$currentSettings['webhook_enabled'] ? 'disabled' : '' ?>>
                                            <small class="form-text text-muted">Number of retry attempts (0-10)</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Retry Delay -->
                                <div class="form-group">
                                    <label for="webhook_retry_delay">Retry Delay (milliseconds)</label>
                                    <input type="number" class="form-control" id="webhook_retry_delay" name="webhook_retry_delay"
                                        value="<?= $currentSettings['webhook_retry_delay'] ?>" min="1000" max="30000" step="500"
                                        <?= !$currentSettings['webhook_enabled'] ? 'disabled' : '' ?>>
                                    <small class="form-text text-muted">Delay between retries (1000-30000ms)</small>
                                </div>

                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary" name="action" value="save">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                                <button type="submit" class="btn btn-success" name="action" value="test"
                                    <?= !$currentSettings['webhook_enabled'] ? 'disabled' : '' ?>>
                                    <i class="fas fa-flask"></i> Save & Test
                                </button>
                                <button type="button" class="btn btn-info" id="testWebhook"
                                    <?= !$currentSettings['webhook_enabled'] ? 'disabled' : '' ?>>
                                    <i class="fas fa-play"></i> Test Only
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Webhook Statistics -->
                <div class="col-md-4">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-bar"></i> Webhook Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box bg-success">
                                <span class="info-box-icon"><i class="fas fa-check"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Successful</span>
                                    <span class="info-box-number"><?= number_format($webhookStats['successful'] ?? 0) ?></span>
                                </div>
                            </div>

                            <div class="info-box bg-danger">
                                <span class="info-box-icon"><i class="fas fa-times"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Failed</span>
                                    <span class="info-box-number"><?= number_format($webhookStats['failed'] ?? 0) ?></span>
                                </div>
                            </div>

                            <div class="info-box bg-warning">
                                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Pending</span>
                                    <span class="info-box-number"><?= number_format($webhookStats['pending'] ?? 0) ?></span>
                                </div>
                            </div>

                            <div class="info-box bg-info">
                                <span class="info-box-icon"><i class="fas fa-tachometer-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Avg Response</span>
                                    <span class="info-box-number"><?= $webhookStats['avg_response_time'] ?? 0 ?>ms</span>
                                </div>
                            </div>

                            <?php if ($webhookStats['last_success']): ?>
                                <p class="text-muted">
                                    <i class="fas fa-check text-success"></i> Last success:<br>
                                    <small><?= date('Y-m-d H:i:s', strtotime($webhookStats['last_success'])) ?></small>
                                </p>
                            <?php endif; ?>

                            <?php if ($webhookStats['last_error']): ?>
                                <p class="text-muted">
                                    <i class="fas fa-times text-danger"></i> Last error:<br>
                                    <small><?= date('Y-m-d H:i:s', strtotime($webhookStats['last_error'])) ?></small>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Webhook Events Info -->
                    <div class="card card-secondary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle"></i> Webhook Events</h3>
                        </div>
                        <div class="card-body">
                            <small class="text-muted">
                                <strong>Available webhook events:</strong><br>
                                • <code>connection_update</code> - Device connection status<br>
                                • <code>message_received</code> - Incoming messages<br>
                                • <code>message_sent</code> - Outgoing messages<br>
                                • <code>qr_code</code> - QR code updates<br>
                                • <code>auth_state</code> - Authentication changes<br>
                                • <code>device_error</code> - Device errors<br>
                                • <code>device_banned</code> - Device banned<br>
                                <br>
                                <strong>Webhook payload includes:</strong><br>
                                • Event type and timestamp<br>
                                • Device ID and session info<br>
                                • Event-specific data<br>
                                • Authentication signature (if secret set)
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Webhook Logs -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-history"></i> Recent Webhook Logs</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" id="refreshLogs">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>Event</th>
                                            <th>Device</th>
                                            <th>Status</th>
                                            <th>Response Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="webhookLogsTable">
                                        <?php if (empty($recentWebhooks)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No webhook logs found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentWebhooks as $log): ?>
                                                <tr>
                                                    <td><?= date('M d, H:i:s', strtotime($log['created_at'])) ?></td>
                                                    <td>
                                                        <span class="badge badge-info"><?= htmlspecialchars($log['event_name']) ?></span>
                                                    </td>
                                                    <td><?= htmlspecialchars($log['device_name'] ?? 'Unknown') ?></td>
                                                    <td>
                                                        <?php if ($log['success']): ?>
                                                            <span class="badge badge-success">
                                                                <i class="fas fa-check"></i> <?= $log['response_code'] ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">
                                                                <i class="fas fa-times"></i> Error
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?= $log['execution_time'] ? number_format($log['execution_time'] * 1000, 0) . 'ms' : '-' ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-xs btn-outline-info view-webhook-detail"
                                                            data-id="<?= $log['id'] ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Webhook Detail Modal -->
<div class="modal fade" id="webhookDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Webhook Log Detail</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="webhookDetailContent">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Toggle webhook form fields based on enabled status
        $('#webhook_enabled').change(function() {
            const enabled = $(this).is(':checked');
            $('#webhook_url, #webhook_secret, #webhook_timeout, #webhook_retry_attempts, #webhook_retry_delay').prop('disabled', !enabled);
            $('button[name="action"][value="test"], #testWebhook').prop('disabled', !enabled);
        });

        // Generate random secret
        $('#generateSecret').click(function() {
            const length = 32;
            const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let secret = '';
            for (let i = 0; i < length; i++) {
                secret += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            $('#webhook_secret').val(secret);
        });

        // Test webhook only
        $('#testWebhook').click(function() {
            if (!$('#webhook_url').val()) {
                alert('Please enter webhook URL first');
                return;
            }

            const btn = $(this);
            const originalText = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin"></i> Testing...');
            btn.prop('disabled', true);

            $.ajax({
                url: '../../api/webhooks.php',
                method: 'POST',
                data: {
                    action: 'test',
                    webhook_url: $('#webhook_url').val(),
                    webhook_secret: $('#webhook_secret').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Webhook test successful! Response time: ' + (response.response_time || 'unknown') + 'ms');
                    } else {
                        alert('Webhook test failed: ' + response.error);
                    }
                },
                error: function() {
                    alert('Failed to test webhook. Please try again.');
                },
                complete: function() {
                    btn.html(originalText);
                    btn.prop('disabled', false);
                }
            });
        });

        // View webhook detail
        $('.view-webhook-detail').click(function() {
            const logId = $(this).data('id');

            $('#webhookDetailContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
            $('#webhookDetailModal').modal('show');

            $.ajax({
                url: '../../api/webhooks.php',
                method: 'GET',
                data: {
                    action: 'detail',
                    id: logId
                },
                success: function(response) {
                    if (response.success) {
                        const log = response.data;
                        let html = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Basic Info</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Event:</strong></td><td>${log.event_name}</td></tr>
                                    <tr><td><strong>Device:</strong></td><td>${log.device_name || 'Unknown'}</td></tr>
                                    <tr><td><strong>Timestamp:</strong></td><td>${log.created_at}</td></tr>
                                    <tr><td><strong>Success:</strong></td><td>${log.success ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>'}</td></tr>
                                    <tr><td><strong>Response Code:</strong></td><td>${log.response_code || 'N/A'}</td></tr>
                                    <tr><td><strong>Execution Time:</strong></td><td>${log.execution_time ? (log.execution_time * 1000).toFixed(0) + 'ms' : 'N/A'}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Error Message</h6>
                                <pre class="bg-light p-2" style="max-height: 150px; overflow-y: auto;">${log.error_message || 'No errors'}</pre>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <h6>Payload</h6>
                                <pre class="bg-light p-2" style="max-height: 200px; overflow-y: auto;">${JSON.stringify(log.payload, null, 2)}</pre>
                            </div>
                        </div>
                    `;
                        if (log.response_data) {
                            html += `
                            <div class="row">
                                <div class="col-12">
                                    <h6>Response Data</h6>
                                    <pre class="bg-light p-2" style="max-height: 200px; overflow-y: auto;">${JSON.stringify(log.response_data, null, 2)}</pre>
                                </div>
                            </div>
                        `;
                        }
                        $('#webhookDetailContent').html(html);
                    } else {
                        $('#webhookDetailContent').html('<div class="alert alert-danger">Failed to load webhook details</div>');
                    }
                },
                error: function() {
                    $('#webhookDetailContent').html('<div class="alert alert-danger">Error loading webhook details</div>');
                }
            });
        });

        // Refresh logs
        $('#refreshLogs').click(function() {
            location.reload();
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>