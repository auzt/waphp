<?php

/**
 * ===============================================================================
 * DEVICES INDEX - Device Management List Page
 * ===============================================================================
 * Halaman utama untuk manajemen devices dengan DataTables
 * - List semua devices dengan search dan filter
 * - Bulk operations
 * - Real-time status updates
 * - Export functionality
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

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize classes
$device = new Device($db);
$currentUser = getCurrentUser();

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $device_ids = $_POST['device_ids'] ?? [];

    if (!empty($device_ids) && in_array($action, ['connect', 'disconnect', 'restart', 'delete'])) {
        try {
            $results = $device->bulkAction($action, $device_ids, $currentUser['id']);
            $success_message = "Bulk {$action} completed. {$results['success']} successful, {$results['failed']} failed.";
        } catch (Exception $e) {
            $error_message = "Bulk action failed: " . $e->getMessage();
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Page settings
$pageTitle = 'Device Management';
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
    <!-- DataTables -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../../assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="../../assets/adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../../assets/adminlte/dist/css/adminlte.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/custom/css/custom.css">

    <style>
        .device-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .action-buttons .btn {
            margin-right: 2px;
            margin-bottom: 2px;
        }

        .bulk-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .device-info {
            display: flex;
            align-items: center;
        }

        .online-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .online {
            background-color: #28a745;
        }

        .offline {
            background-color: #6c757d;
        }

        @media (max-width: 768px) {
            .action-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 2px;
            }

            .action-buttons .btn {
                flex: 1;
                min-width: 35px;
                margin: 1px;
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
                            <h1 class="m-0"><?php echo $pageTitle; ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                                <li class="breadcrumb-item active">Devices</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <!-- Alerts -->
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-check"></i> Success!</h5>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Filter and Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Device Filters & Actions</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <!-- Status Filter -->
                                    <div class="form-group">
                                        <label>Filter by Status:</label>
                                        <select id="status-filter" class="form-control">
                                            <option value="">All Status</option>
                                            <option value="connected">Connected</option>
                                            <option value="connecting">Connecting</option>
                                            <option value="disconnected">Disconnected</option>
                                            <option value="pairing">Pairing</option>
                                            <option value="banned">Banned</option>
                                            <option value="error">Error</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <!-- Actions -->
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="btn-group d-block">
                                            <a href="add.php" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Add Device
                                            </a>
                                            <button class="btn btn-success" onclick="refreshTable()">
                                                <i class="fas fa-sync-alt"></i> Refresh
                                            </button>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown">
                                                    <i class="fas fa-download"></i> Export
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="#" onclick="exportData('csv')">
                                                        <i class="fas fa-file-csv"></i> Export CSV
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="exportData('excel')">
                                                        <i class="fas fa-file-excel"></i> Export Excel
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="exportData('pdf')">
                                                        <i class="fas fa-file-pdf"></i> Export PDF
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Actions Panel -->
                    <div id="bulk-actions" class="bulk-actions">
                        <form method="POST" id="bulk-form">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <span id="selected-count">0</span> device(s) selected
                                </div>
                                <div class="col-md-6">
                                    <select name="bulk_action" class="form-control" required>
                                        <option value="">Choose Action</option>
                                        <option value="connect">Connect All</option>
                                        <option value="disconnect">Disconnect All</option>
                                        <option value="restart">Restart All</option>
                                        <option value="delete">Delete All</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-play"></i> Execute
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="selected-devices" name="device_ids">
                        </form>
                    </div>

                    <!-- Devices Table -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">WhatsApp Devices</h3>
                            <div class="card-tools">
                                <span class="badge badge-primary" id="total-devices">Loading...</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="devices-table" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="select-all">
                                        </th>
                                        <th>Device</th>
                                        <th>Status</th>
                                        <th>WhatsApp Info</th>
                                        <th>Messages Today</th>
                                        <th>Last Seen</th>
                                        <th>Owner</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Device Detail Modal -->
    <div class="modal fade" id="modal-device-detail" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Device Details</h4>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="device-detail-content">
                    <!-- Content loaded via AJAX -->
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Loading device details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="modal-qr-code" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h4 class="modal-title text-white">
                        <i class="fas fa-qrcode"></i> QR Code Scanner
                    </h4>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center" id="qr-code-content">
                    <!-- QR Code content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-info" onclick="refreshQRCode()">
                        <i class="fas fa-sync-alt"></i> Refresh QR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="../../assets/adminlte/plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="../../assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables & Plugins -->
    <script src="../../assets/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="../../assets/adminlte/plugins/sweetalert2/sweetalert2.min.js"></script>
    <!-- AdminLTE App -->
    <script src="../../assets/adminlte/dist/js/adminlte.min.js"></script>
    <!-- Custom JS -->
    <script src="../../assets/custom/js/devices.js"></script>

    <script>
        $(document).ready(function() {
            'use strict';

            // Initialize DataTable
            const table = $('#devices-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '../../api/devices.php?action=datatable',
                    type: 'POST',
                    data: function(d) {
                        d.status_filter = $('#status-filter').val();
                        return d;
                    }
                },
                columns: [{
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function(data) {
                            return `<input type="checkbox" class="device-checkbox" value="${data}">`;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            const avatar = `<div class="device-avatar bg-primary">
                                      <i class="fas fa-mobile-alt text-white"></i>
                                    </div>`;
                            return `
                        <div class="device-info">
                            ${avatar}
                            <div>
                                <strong>${escapeHtml(row.device_name)}</strong><br>
                                <small class="text-muted">${escapeHtml(row.phone_number)}</small>
                            </div>
                        </div>
                    `;
                        }
                    },
                    {
                        data: 'status',
                        render: function(data, type, row) {
                            const statusConfig = getStatusConfig(data);
                            const onlineIndicator = row.is_online ?
                                '<span class="online-indicator online"></span>' :
                                '<span class="online-indicator offline"></span>';

                            return `
                        <div>
                            ${onlineIndicator}
                            <span class="badge badge-${statusConfig.class} status-badge">
                                <i class="fas ${statusConfig.icon}"></i>
                                ${statusConfig.text}
                            </span>
                        </div>
                    `;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `
                        <div>
                            <strong>${escapeHtml(row.whatsapp_name || 'Not set')}</strong><br>
                            <small class="text-muted">${escapeHtml(row.whatsapp_user_id || 'Not connected')}</small>
                        </div>
                    `;
                        }
                    },
                    {
                        data: 'messages_today',
                        render: function(data) {
                            return `<span class="badge badge-info">${data || 0}</span>`;
                        }
                    },
                    {
                        data: 'last_seen',
                        render: function(data) {
                            return data ? timeAgo(data) : 'Never';
                        }
                    },
                    {
                        data: 'owner',
                        render: function(data) {
                            return escapeHtml(data || 'Unknown');
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return generateActionButtons(row);
                        }
                    }
                ],
                order: [
                    [1, 'asc']
                ],
                pageLength: 25,
                responsive: true,
                language: {
                    processing: 'Loading devices...',
                    emptyTable: 'No devices found. <a href="add.php">Add your first device</a>'
                },
                drawCallback: function() {
                    updateDeviceCount();
                    // Re-initialize tooltips
                    $('[data-toggle="tooltip"]').tooltip();
                }
            });

            // Status filter change
            $('#status-filter').on('change', function() {
                table.ajax.reload();
            });

            // Select all checkbox
            $('#select-all').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.device-checkbox').prop('checked', isChecked);
                updateBulkActions();
            });

            // Individual checkbox change
            $(document).on('change', '.device-checkbox', function() {
                updateBulkActions();

                // Update select all checkbox
                const totalCheckboxes = $('.device-checkbox').length;
                const checkedCheckboxes = $('.device-checkbox:checked').length;

                $('#select-all').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
                $('#select-all').prop('checked', checkedCheckboxes === totalCheckboxes);
            });

            // Bulk form submission
            $('#bulk-form').on('submit', function(e) {
                e.preventDefault();

                const action = $('select[name="bulk_action"]').val();
                const selectedDevices = getSelectedDevices();

                if (!action || selectedDevices.length === 0) {
                    showAlert('warning', 'Please select devices and an action');
                    return;
                }

                const confirmText = `Are you sure you want to ${action} ${selectedDevices.length} device(s)?`;

                Swal.fire({
                    title: 'Confirm Bulk Action',
                    text: confirmText,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: `Yes, ${action}!`
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeBulkAction(action, selectedDevices);
                    }
                });
            });

            // Auto refresh every 30 seconds
            setInterval(function() {
                table.ajax.reload(null, false);
            }, 30000);
        });

        // Helper functions
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

        function generateActionButtons(device) {
            let buttons = [];

            // Status-specific action button
            switch (device.status) {
                case 'disconnected':
                case 'error':
                case 'timeout':
                case 'auth_failure':
                    buttons.push(`<button class="btn btn-sm btn-success" onclick="connectDevice(${device.id})" title="Connect">
                           <i class="fas fa-play"></i>
                         </button>`);
                    break;

                case 'connected':
                    buttons.push(`<button class="btn btn-sm btn-warning" onclick="disconnectDevice(${device.id})" title="Disconnect">
                           <i class="fas fa-stop"></i>
                         </button>`);
                    break;

                case 'connecting':
                    buttons.push(`<button class="btn btn-sm btn-secondary" disabled title="Connecting...">
                           <i class="fas fa-spinner fa-spin"></i>
                         </button>`);
                    break;

                case 'pairing':
                    buttons.push(`<button class="btn btn-sm btn-info" onclick="showQRCode(${device.id})" title="Show QR Code">
                           <i class="fas fa-qrcode"></i>
                         </button>`);
                    break;
            }

            // Common action buttons
            buttons.push(`<button class="btn btn-sm btn-primary" onclick="viewDevice(${device.id})" title="View Details">
                   <i class="fas fa-eye"></i>
                 </button>`);

            buttons.push(`<button class="btn btn-sm btn-info" onclick="editDevice(${device.id})" title="Edit">
                   <i class="fas fa-edit"></i>
                 </button>`);

            buttons.push(`<button class="btn btn-sm btn-danger" onclick="deleteDevice(${device.id})" title="Delete">
                   <i class="fas fa-trash"></i>
                 </button>`);

            return `<div class="action-buttons">${buttons.join('')}</div>`;
        }

        function updateBulkActions() {
            const selectedDevices = getSelectedDevices();
            const bulkPanel = $('#bulk-actions');

            if (selectedDevices.length > 0) {
                $('#selected-count').text(selectedDevices.length);
                $('#selected-devices').val(JSON.stringify(selectedDevices));
                bulkPanel.show();
            } else {
                bulkPanel.hide();
            }
        }

        function getSelectedDevices() {
            const selected = [];
            $('.device-checkbox:checked').each(function() {
                selected.push(parseInt($(this).val()));
            });
            return selected;
        }

        function clearSelection() {
            $('.device-checkbox, #select-all').prop('checked', false);
            updateBulkActions();
        }

        function updateDeviceCount() {
            const info = $('#devices-table').DataTable().page.info();
            $('#total-devices').text(info.recordsTotal + ' devices');
        }

        function refreshTable() {
            $('#devices-table').DataTable().ajax.reload();
            showAlert('info', 'Device list refreshed');
        }

        // Device action functions
        function connectDevice(deviceId) {
            performDeviceAction('connect', deviceId, 'Connect device?');
        }

        function disconnectDevice(deviceId) {
            performDeviceAction('disconnect', deviceId, 'Disconnect device?');
        }

        function restartDevice(deviceId) {
            performDeviceAction('restart', deviceId, 'Restart device?');
        }

        function deleteDevice(deviceId) {
            Swal.fire({
                title: 'Delete Device?',
                text: 'This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    performDeviceAction('delete', deviceId);
                }
            });
        }

        function viewDevice(deviceId) {
            window.location.href = `view.php?id=${deviceId}`;
        }

        function editDevice(deviceId) {
            window.location.href = `edit.php?id=${deviceId}`;
        }

        function showQRCode(deviceId) {
            $('#qr-code-content').html(`
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Loading QR Code...</p>
        </div>
    `);

            $('#modal-qr-code').modal('show');

            // Load QR code
            $.ajax({
                url: '../../api/devices.php',
                method: 'GET',
                data: {
                    action: 'qr',
                    id: deviceId
                },
                success: function(response) {
                    if (response.success && response.data.qr_code) {
                        $('#qr-code-content').html(`
                    <img src="${response.data.qr_code}" class="img-fluid" style="max-width: 300px;">
                    <p class="mt-3">Scan this QR code with your WhatsApp mobile app</p>
                    <small class="text-muted">QR Code expires in ${response.data.expires_in || 300} seconds</small>
                `);
                    } else {
                        $('#qr-code-content').html(`
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        QR Code not available. Device may not be in pairing mode.
                    </div>
                `);
                    }
                },
                error: function() {
                    $('#qr-code-content').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-times"></i>
                    Failed to load QR Code. Please try again.
                </div>
            `);
                }
            });
        }

        function performDeviceAction(action, deviceId, confirmText = null) {
            const executeAction = () => {
                $.ajax({
                    url: '../../api/devices.php',
                    method: 'POST',
                    data: JSON.stringify({
                        action: action,
                        device_id: deviceId
                    }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', response.message || `Device ${action} successful`);
                            $('#devices-table').DataTable().ajax.reload(null, false);
                        } else {
                            showAlert('error', response.message || `Failed to ${action} device`);
                        }
                    },
                    error: function() {
                        showAlert('error', `Error performing ${action}. Please try again.`);
                    }
                });
            };

            if (confirmText) {
                Swal.fire({
                    title: confirmText,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeAction();
                    }
                });
            } else {
                executeAction();
            }
        }

        function executeBulkAction(action, deviceIds) {
            $.ajax({
                url: '../../api/devices.php',
                method: 'POST',
                data: JSON.stringify({
                    action: 'bulk_' + action,
                    device_ids: deviceIds
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', response.message || `Bulk ${action} completed`);
                        $('#devices-table').DataTable().ajax.reload();
                        clearSelection();
                    } else {
                        showAlert('error', response.message || `Bulk ${action} failed`);
                    }
                },
                error: function() {
                    showAlert('error', 'Error performing bulk action. Please try again.');
                }
            });
        }

        function exportData(format) {
            const statusFilter = $('#status-filter').val();
            const url = `../../api/devices.php?action=export&format=${format}&status=${statusFilter}`;
            window.open(url, '_blank');
            showAlert('info', `Exporting data as ${format.toUpperCase()}...`);
        }

        function refreshQRCode() {
            // Get current device ID from modal data
            const deviceId = $('#modal-qr-code').data('device-id');
            if (deviceId) {
                showQRCode(deviceId);
            }
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

        function showAlert(type, message) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });

            Toast.fire({
                icon: type,
                title: message
            });
        }

        // Initialize tooltips
        $(function() {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>

</body>

</html>