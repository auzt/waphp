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
                $deleteStats = $device->getDeleteStatistics($device_id);

                // 3. Delete related data in correct order

                // Delete API tokens
                $device->deleteApiTokens($device_id);

                // Delete message logs
                $device->deleteMessageLogs($device_id);

                // Delete webhook logs
                $device->deleteWebhookLogs($device_id);

                // Delete NodeJS command logs
                $device->deleteNodeJSCommands($device_id);

                // Delete device logs
                $device->deleteDeviceLogs($device_id);

                // Delete session data files (if any)
                $device->deleteSessionFiles($device_id);

                // 4. Finally delete the device record
                $deleted = $device->delete($device_id, $currentUser['id']);

                if ($deleted) {
                    // Commit transaction
                    $db->commit();

                    // Log the deletion activity
                    $logMessage = "Deleted device: '{$deviceData['device_name']}' " .
                        "Phone: +{$deviceData['phone_number']} " .
                        "Statistics: {$deleteStats['messages']} messages, " .
                        "{$deleteStats['api_calls']} API calls, " .
                        "{$deleteStats['logs']} logs deleted";

                    logActivity($currentUser['id'], 'device_deleted', $logMessage, $device_id);

                    // Set success message
                    $_SESSION['success_message'] = "Device '{$deviceData['device_name']}' and all associated data has been permanently deleted.";

                    // Redirect to device list
                    header('Location: index.php');
                    exit;
                } else {
                    throw new Exception('Failed to delete device record');
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollback();
                throw $e;
            }
        }
    } catch (Exception $e) {
        error_log("Delete device error: " . $e->getMessage());
        $error_message = 'Error deleting device: ' . $e->getMessage();
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Page settings
$pageTitle = 'Delete Device: ' . htmlspecialchars($deviceData['device_name']);
$currentPage = 'devices';

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
    <!-- Theme style -->
    <link rel="stylesheet" href="../../assets/adminlte/dist/css/adminlte.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/custom/css/custom.css">

    <style>
        .danger-zone {
            background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .device-info-card {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }

        .confirmation-input {
            border: 2px solid #dc3545;
            background: #fff5f5;
        }

        .confirmation-input:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .delete-stats {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }

        .warning-icon {
            color: #ffc107;
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .danger-list li {
            margin-bottom: 10px;
            padding: 5px 0;
        }

        @media (max-width: 768px) {
            .danger-zone {
                padding: 20px;
            }

            .warning-icon {
                font-size: 3rem;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

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
                            <h1 class="m-0 text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                Delete Device
                            </h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Devices</a></li>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $device_id; ?>">
                                        <?php echo htmlspecialchars($deviceData['device_name']); ?>
                                    </a></li>
                                <li class="breadcrumb-item active">Delete</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <!-- Error Alert -->
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Danger Zone Header -->
                    <div class="danger-zone">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-3">
                                    <i class="fas fa-skull-crossbones me-3"></i>
                                    DANGER ZONE
                                </h2>
                                <p class="mb-2 lead">
                                    You are about to permanently delete this WhatsApp device and ALL associated data.
                                </p>
                                <p class="mb-0">
                                    <strong>This action cannot be undone!</strong>
                                    All messages, logs, API tokens, and configuration will be permanently lost.
                                </p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-exclamation-triangle warning-icon"></i>
                                <h4>PERMANENT DELETION</h4>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Device Information -->
                        <div class="col-md-6">
                            <div class="card device-info-card">
                                <div class="card-header bg-danger">
                                    <h3 class="card-title text-white">
                                        <i class="fas fa-mobile-alt"></i>
                                        Device to be Deleted
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
                                            <td><code><?php echo htmlspecialchars($deviceData['device_id']); ?></code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                <?php
                                                $statusClass = getStatusClass($deviceData['status']);
                                                $statusText = getStatusText($deviceData['status']);
                                                ?>
                                                <span class="badge badge-<?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Owner:</strong></td>
                                            <td><?php echo htmlspecialchars($deviceData['owner'] ?? 'Unknown'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Created:</strong></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($deviceData['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Last Updated:</strong></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($deviceData['updated_at'])); ?></td>
                                        </tr>
                                    </table>

                                    <?php if ($deviceData['description']): ?>
                                        <div class="mt-3">
                                            <strong>Description:</strong>
                                            <p class="text-muted mt-1"><?php echo nl2br(htmlspecialchars($deviceData['description'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- What Will Be Deleted -->
                            <div class="card card-warning">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-list"></i>
                                        What Will Be Deleted
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <ul class="danger-list list-unstyled">
                                        <li>
                                            <i class="fas fa-mobile-alt text-danger"></i>
                                            <strong>Device Configuration</strong> - All device settings and metadata
                                        </li>
                                        <li>
                                            <i class="fas fa-envelope text-danger"></i>
                                            <strong>Message History</strong> - All incoming and outgoing messages
                                        </li>
                                        <li>
                                            <i class="fas fa-key text-danger"></i>
                                            <strong>API Tokens</strong> - Authentication tokens and API access
                                        </li>
                                        <li>
                                            <i class="fas fa-file-alt text-danger"></i>
                                            <strong>System Logs</strong> - All device activity and error logs
                                        </li>
                                        <li>
                                            <i class="fas fa-link text-danger"></i>
                                            <strong>Webhook Data</strong> - All webhook logs and configurations
                                        </li>
                                        <li>
                                            <i class="fas fa-cog text-danger"></i>
                                            <strong>Session Data</strong> - WhatsApp session and authentication data
                                        </li>
                                        <li>
                                            <i class="fas fa-chart-line text-danger"></i>
                                            <strong>Statistics</strong> - Usage statistics and performance metrics
                                        </li>
                                    </ul>

                                    <!-- Statistics Preview -->
                                    <div class="delete-stats">
                                        <h6><i class="fas fa-chart-bar"></i> Data to be Deleted:</h6>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <strong class="text-danger" id="messages-count">Loading...</strong><br>
                                                <small>Messages</small>
                                            </div>
                                            <div class="col-4">
                                                <strong class="text-danger" id="api-calls-count">Loading...</strong><br>
                                                <small>API Calls</small>
                                            </div>
                                            <div class="col-4">
                                                <strong class="text-danger" id="logs-count">Loading...</strong><br>
                                                <small>Log Entries</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Deletion Form -->
                        <div class="col-md-6">
                            <div class="card card-danger">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-trash"></i>
                                        Confirm Deletion
                                    </h3>
                                </div>

                                <form method="POST" id="delete-form">
                                    <div class="card-body">
                                        <div class="alert alert-danger">
                                            <h5><i class="icon fas fa-exclamation-triangle"></i> Final Warning!</h5>
                                            <p class="mb-2">
                                                This will <strong>permanently delete</strong> the device and all its data.
                                                This action <strong>cannot be reversed</strong>.
                                            </p>
                                            <p class="mb-0">
                                                <strong>Before proceeding:</strong>
                                            </p>
                                            <ul class="mt-2 mb-0">
                                                <li>Make sure you have backed up any important data</li>
                                                <li>Update any applications using this device's API</li>
                                                <li>Inform team members about this deletion</li>
                                            </ul>
                                        </div>

                                        <!-- Device Name Confirmation -->
                                        <div class="form-group">
                                            <label for="device_name_confirm">
                                                <strong>Type the device name to confirm:</strong>
                                            </label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-danger text-white">
                                                        <i class="fas fa-keyboard"></i>
                                                    </span>
                                                </div>
                                                <input type="text"
                                                    class="form-control confirmation-input"
                                                    id="device_name_confirm"
                                                    name="device_name_confirm"
                                                    placeholder="<?php echo htmlspecialchars($deviceData['device_name']); ?>"
                                                    required>
                                            </div>
                                            <small class="form-text text-danger">
                                                <strong>Required:</strong> <?php echo htmlspecialchars($deviceData['device_name']); ?>
                                            </small>
                                        </div>

                                        <!-- Additional Confirmations -->
                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="confirm-backup" required>
                                                <label class="custom-control-label" for="confirm-backup">
                                                    I have backed up any important data
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="confirm-understand" required>
                                                <label class="custom-control-label" for="confirm-understand">
                                                    I understand this action cannot be undone
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="confirm-responsibility" required>
                                                <label class="custom-control-label" for="confirm-responsibility">
                                                    I take full responsibility for this deletion
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Hidden fields -->
                                        <input type="hidden" name="device_id" value="<?php echo $device_id; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="confirm_delete" value="1">
                                    </div>

                                    <div class="card-footer">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <a href="view.php?id=<?php echo $device_id; ?>" class="btn btn-secondary btn-block">
                                                    <i class="fas fa-arrow-left"></i> Cancel & Go Back
                                                </a>
                                            </div>
                                            <div class="col-md-6">
                                                <button type="submit" class="btn btn-danger btn-block" id="delete-btn" disabled>
                                                    <i class="fas fa-trash"></i> DELETE PERMANENTLY
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Emergency Contact -->
                            <div class="card card-info">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-life-ring"></i>
                                        Need Help?
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2">
                                        If you're having issues with the device instead of wanting to delete it:
                                    </p>
                                    <div class="btn-group-vertical d-block">
                                        <a href="view.php?id=<?php echo $device_id; ?>&action=restart" class="btn btn-warning btn-sm mb-2">
                                            <i class="fas fa-redo"></i> Try Restarting Device
                                        </a>
                                        <a href="../logs/api-logs.php?device_id=<?php echo $device_id; ?>" class="btn btn-info btn-sm mb-2">
                                            <i class="fas fa-bug"></i> Check Error Logs
                                        </a>
                                        <a href="../settings/system.php" class="btn btn-secondary btn-sm mb-2">
                                            <i class="fas fa-cog"></i> System Settings
                                        </a>
                                        <a href="mailto:support@yourcompany.com" class="btn btn-primary btn-sm">
                                            <i class="fas fa-envelope"></i> Contact Support
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
    </div>

    <!-- jQuery -->
    <script src="../../assets/adminlte/plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="../../assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="../../assets/adminlte/dist/js/adminlte.min.js"></script>

    <script>
        $(document).ready(function() {
            'use strict';

            // Load deletion statistics
            loadDeletionStats();

            // Device name confirmation validation
            $('#device_name_confirm').on('input', function() {
                validateForm();
            });

            // Checkbox validation
            $('.custom-control-input').on('change', function() {
                validateForm();
            });

            // Form submission with additional confirmation
            $('#delete-form').on('submit', function(e) {
                e.preventDefault();

                const deviceName = '<?php echo addslashes($deviceData['device_name']); ?>';
                const enteredName = $('#device_name_confirm').val();

                if (enteredName !== deviceName) {
                    alert('Device name confirmation does not match. Please type the exact device name.');
                    $('#device_name_confirm').focus();
                    return false;
                }

                // Final confirmation dialog
                if (confirm('FINAL CONFIRMATION: Are you absolutely sure you want to permanently delete this device and ALL its data? This action CANNOT be undone.')) {
                    if (confirm('Last chance to cancel. Click OK to proceed with permanent deletion.')) {
                        // Show loading state
                        const submitBtn = $('#delete-btn');
                        submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Deleting...').prop('disabled', true);

                        // Submit form
                        this.submit();
                    }
                }
            });

            // Prevent accidental page leave
            window.addEventListener('beforeunload', function(e) {
                const deviceNameEntered = $('#device_name_confirm').val().trim();
                if (deviceNameEntered.length > 0) {
                    e.preventDefault();
                    e.returnValue = 'You have started the deletion process. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });

            // Auto-focus device name input
            $('#device_name_confirm').focus();
        });

        function loadDeletionStats() {
            $.ajax({
                url: '../../api/devices.php',
                method: 'GET',
                data: {
                    action: 'delete_stats',
                    device_id: <?php echo $device_id; ?>
                },
                success: function(response) {
                    if (response.success) {
                        updateDeletionStats(response.data);
                    } else {
                        updateDeletionStats({
                            messages: 0,
                            api_calls: 0,
                            logs: 0
                        });
                    }
                },
                error: function() {
                    updateDeletionStats({
                        messages: '?',
                        api_calls: '?',
                        logs: '?'
                    });
                }
            });
        }

        function updateDeletionStats(stats) {
            $('#messages-count').text(formatNumber(stats.messages));
            $('#api-calls-count').text(formatNumber(stats.api_calls));
            $('#logs-count').text(formatNumber(stats.logs));
        }

        function formatNumber(num) {
            if (typeof num === 'number') {
                return num.toLocaleString();
            }
            return num;
        }

        function validateForm() {
            const deviceName = '<?php echo addslashes($deviceData['device_name']); ?>';
            const enteredName = $('#device_name_confirm').val();

            const nameMatches = enteredName === deviceName;
            const backupChecked = $('#confirm-backup').is(':checked');
            const understandChecked = $('#confirm-understand').is(':checked');
            const responsibilityChecked = $('#confirm-responsibility').is(':checked');

            const allValid = nameMatches && backupChecked && understandChecked && responsibilityChecked;

            // Update delete button state
            $('#delete-btn').prop('disabled', !allValid);

            // Update device name input styling
            const nameInput = $('#device_name_confirm');
            if (enteredName.length > 0) {
                if (nameMatches) {
                    nameInput.removeClass('is-invalid').addClass('is-valid');
                } else {
                    nameInput.removeClass('is-valid').addClass('is-invalid');
                }
            } else {
                nameInput.removeClass('is-valid is-invalid');
            }

            // Update progress indicator
            updateValidationProgress(nameMatches, backupChecked, understandChecked, responsibilityChecked);
        }

        function updateValidationProgress(name, backup, understand, responsibility) {
            const validations = [name, backup, understand, responsibility];
            const validCount = validations.filter(Boolean).length;
            const percentage = (validCount / 4) * 100;

            // Update any progress indicators if you have them
            console.log(`Validation progress: ${validCount}/4 (${percentage}%)`);
        }

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Escape to cancel
            if (e.key === 'Escape') {
                if (confirm('Cancel deletion and go back to device details?')) {
                    window.location.href = 'view.php?id=<?php echo $device_id; ?>';
                }
            }

            // Ctrl+Enter to focus delete button (when enabled)
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const deleteBtn = $('#delete-btn');
                if (!deleteBtn.prop('disabled')) {
                    deleteBtn.focus();
                }
            }
        });

        // Auto-clear device name input if user starts typing wrong name
        let wrongAttempts = 0;
        $('#device_name_confirm').on('input', function() {
            const deviceName = '<?php echo addslashes($deviceData['device_name']); ?>';
            const enteredName = this.value;

            // If user has typed something that doesn't match the beginning of device name
            if (enteredName.length > 0 && !deviceName.startsWith(enteredName)) {
                wrongAttempts++;

                if (wrongAttempts >= 3) {
                    // Clear field and show hint
                    this.value = '';
                    this.placeholder = 'Please type exactly: ' + deviceName;
                    this.focus();
                    wrongAttempts = 0;
                }
            } else if (enteredName === deviceName) {
                wrongAttempts = 0;
                this.placeholder = deviceName;
            }
        });

        // Copy device name to clipboard helper
        function copyDeviceName() {
            const deviceName = '<?php echo addslashes($deviceData['device_name']); ?>';

            const tempInput = document.createElement('input');
            tempInput.value = deviceName;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);

            // Show feedback
            alert('Device name copied to clipboard: ' + deviceName);
        }

        // Add copy button to device name field
        $(document).ready(function() {
            const copyButton = $(`
        <div class="input-group-append">
            <button type="button" class="btn btn-outline-secondary" onclick="copyDeviceName()" title="Copy device name">
                <i class="fas fa-copy"></i>
            </button>
        </div>
    `);

            $('#device_name_confirm').parent().addClass('input-group').append(copyButton);
        });
    </script>

    <?php
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
    ?>

</body>

</html>