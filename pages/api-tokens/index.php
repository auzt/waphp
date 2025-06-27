<?php

/**
 * ===============================================================================
 * API TOKENS INDEX - List & Manage API Tokens
 * ===============================================================================
 * Halaman untuk mengelola API tokens dengan fitur:
 * - List semua tokens dengan informasi detail
 * - Generate token baru
 * - Revoke/activate tokens
 * - Usage statistics
 * - Token security management
 * ===============================================================================
 */

// Include required files
require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Device.php';
require_once '../../classes/ApiToken.php';
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
$apiToken = new ApiToken($db);
$device = new Device($db);
$apiLogger = new ApiLogger($db);
$currentUser = getCurrentUser();

// Handle actions
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$token_id = $_GET['token_id'] ?? $_POST['token_id'] ?? null;
$actionMessage = '';
$actionType = '';

if ($action && $token_id) {
    try {
        switch ($action) {
            case 'toggle_status':
                // Toggle token active/inactive status
                $tokenData = $apiToken->getById($token_id, $currentUser['id']);
                if ($tokenData) {
                    $newStatus = !$tokenData['is_active'];
                    $result = $apiToken->updateStatus($token_id, $newStatus, $currentUser['id']);

                    if ($result) {
                        $statusText = $newStatus ? 'activated' : 'deactivated';
                        $actionMessage = "Token '{$tokenData['token_name']}' has been {$statusText} successfully";
                        $actionType = 'success';

                        logActivity(
                            $currentUser['id'],
                            'token_status_changed',
                            "Token {$tokenData['token_name']} {$statusText}",
                            $token_id
                        );
                    } else {
                        $actionMessage = 'Failed to update token status';
                        $actionType = 'error';
                    }
                } else {
                    $actionMessage = 'Token not found or access denied';
                    $actionType = 'error';
                }
                break;

            case 'regenerate':
                // Regenerate token
                $result = $apiToken->regenerateToken($token_id, $currentUser['id']);
                if ($result) {
                    $actionMessage = 'Token regenerated successfully. Please update your applications with the new token.';
                    $actionType = 'success';

                    logActivity(
                        $currentUser['id'],
                        'token_regenerated',
                        "Token regenerated for token ID: {$token_id}",
                        $token_id
                    );
                } else {
                    $actionMessage = 'Failed to regenerate token';
                    $actionType = 'error';
                }
                break;

            case 'delete':
                // Delete token (soft delete - mark as revoked)
                $tokenData = $apiToken->getById($token_id, $currentUser['id']);
                if ($tokenData) {
                    $result = $apiToken->revokeToken($token_id, $currentUser['id']);
                    if ($result) {
                        $actionMessage = "Token '{$tokenData['token_name']}' has been revoked successfully";
                        $actionType = 'success';

                        logActivity(
                            $currentUser['id'],
                            'token_revoked',
                            "Token {$tokenData['token_name']} revoked",
                            $token_id
                        );
                    } else {
                        $actionMessage = 'Failed to revoke token';
                        $actionType = 'error';
                    }
                } else {
                    $actionMessage = 'Token not found or access denied';
                    $actionType = 'error';
                }
                break;
        }
    } catch (Exception $e) {
        error_log("API Token action error: " . $e->getMessage());
        $actionMessage = 'Error performing action: ' . $e->getMessage();
        $actionType = 'error';
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$device_filter = $_GET['device_id'] ?? '';
$search = $_GET['search'] ?? '';

// Build filter conditions
$filters = [];
if ($status_filter) {
    $filters['status'] = $status_filter;
}
if ($device_filter) {
    $filters['device_id'] = $device_filter;
}
if ($search) {
    $filters['search'] = $search;
}

// Get tokens data
try {
    $tokens = $apiToken->getByUserId($currentUser['id'], $filters);
    $userDevices = $device->getByUserId($currentUser['id']);
    $totalTokens = $apiToken->countByUserId($currentUser['id']);
    $activeTokens = $apiToken->countByUserId($currentUser['id'], ['status' => 'active']);
    $tokenStats = $apiLogger->getTokenStatsByUser($currentUser['id']);
} catch (Exception $e) {
    error_log("Error loading tokens: " . $e->getMessage());
    $tokens = [];
    $userDevices = [];
    $totalTokens = 0;
    $activeTokens = 0;
    $tokenStats = [];
}

// Page settings
$pageTitle = 'API Tokens Management';
$currentPage = 'api-tokens';

// Helper functions
function getStatusClass($isActive)
{
    return $isActive ? 'success' : 'secondary';
}

function getStatusText($isActive)
{
    return $isActive ? 'Active' : 'Inactive';
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

function formatTokenForDisplay($token)
{
    if (strlen($token) <= 16) {
        return $token;
    }
    return substr($token, 0, 8) . '...' . substr($token, -8);
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
    <!-- DataTables -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../../assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="../../assets/adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../../assets/adminlte/dist/css/adminlte.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/custom/css/custom.css">

    <style>
        .token-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .token-card.active {
            border-left-color: #28a745;
        }

        .token-card.inactive {
            border-left-color: #6c757d;
            opacity: 0.8;
        }

        .token-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .token-display {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            font-size: 0.9em;
        }

        .usage-badge {
            font-size: 0.75em;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .token-actions .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .token-actions .btn {
                width: 100%;
                margin-right: 0;
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
                            <h1 class="m-0">API Tokens Management</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                                <li class="breadcrumb-item active">API Tokens</li>
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

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-info elevation-1">
                                    <i class="fas fa-key"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Tokens</span>
                                    <span class="info-box-number"><?php echo number_format($totalTokens); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-success elevation-1">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Active Tokens</span>
                                    <span class="info-box-number"><?php echo number_format($activeTokens); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning elevation-1">
                                    <i class="fas fa-chart-line"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">API Calls Today</span>
                                    <span class="info-box-number"><?php echo number_format($tokenStats['today_calls'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-danger elevation-1">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Failed Calls</span>
                                    <span class="info-box-number"><?php echo number_format($tokenStats['failed_calls'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-plus-circle"></i>
                                        Quick Actions
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <a href="generate.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Generate New Token
                                    </a>
                                    <button type="button" class="btn btn-info" onclick="refreshTokens()">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="exportTokens()">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                    <a href="../logs/api-logs.php" class="btn btn-warning">
                                        <i class="fas fa-file-alt"></i> View API Logs
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row">
                        <div class="col-12">
                            <div class="filter-card">
                                <form method="GET" action="" id="filter-form">
                                    <div class="row align-items-end">
                                        <div class="col-md-3">
                                            <label for="status">Status Filter:</label>
                                            <select name="status" id="status" class="form-control">
                                                <option value="">All Status</option>
                                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="device_id">Device Filter:</label>
                                            <select name="device_id" id="device_id" class="form-control">
                                                <option value="">All Devices</option>
                                                <?php foreach ($userDevices as $device): ?>
                                                    <option value="<?php echo $device['id']; ?>"
                                                        <?php echo $device_filter == $device['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($device['device_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="search">Search:</label>
                                            <input type="text" name="search" id="search" class="form-control"
                                                placeholder="Search token name..." value="<?php echo htmlspecialchars($search); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary btn-block">
                                                <i class="fas fa-search"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tokens Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-list"></i>
                                        API Tokens (<?php echo count($tokens); ?> found)
                                    </h3>
                                    <div class="card-tools">
                                        <div class="input-group input-group-sm" style="width: 150px;">
                                            <input type="text" id="table-search" class="form-control float-right" placeholder="Quick search">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body table-responsive p-0">
                                    <table id="tokens-table" class="table table-hover text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>Token Name</th>
                                                <th>Device</th>
                                                <th>Token</th>
                                                <th>Status</th>
                                                <th>Usage</th>
                                                <th>Last Used</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($tokens)): ?>
                                                <?php foreach ($tokens as $token): ?>
                                                    <tr class="token-row" data-token-id="<?php echo $token['id']; ?>">
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($token['token_name']); ?></strong>
                                                            <?php if ($token['description']): ?>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($token['description']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($token['device_name']): ?>
                                                                <span class="badge badge-info">
                                                                    <?php echo htmlspecialchars($token['device_name']); ?>
                                                                </span>
                                                                <br>
                                                                <small class="text-muted">+<?php echo $token['phone_number']; ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted">No device</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="token-display">
                                                                <span id="token-display-<?php echo $token['id']; ?>">
                                                                    <?php echo formatTokenForDisplay($token['token']); ?>
                                                                </span>
                                                                <button class="btn btn-sm btn-outline-secondary ml-2"
                                                                    onclick="toggleTokenVisibility(<?php echo $token['id']; ?>, '<?php echo htmlspecialchars($token['token']); ?>')">
                                                                    <i class="fas fa-eye" id="eye-<?php echo $token['id']; ?>"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-secondary"
                                                                    onclick="copyToken('<?php echo htmlspecialchars($token['token']); ?>')">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?php echo getStatusClass($token['is_active']); ?>">
                                                                <i class="fas fa-<?php echo $token['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                                                <?php echo getStatusText($token['is_active']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-primary usage-badge">
                                                                <?php echo number_format($token['usage_count'] ?? 0); ?> calls
                                                            </span>
                                                            <?php if ($token['success_rate'] !== null): ?>
                                                                <br>
                                                                <span class="badge badge-<?php echo $token['success_rate'] >= 90 ? 'success' : ($token['success_rate'] >= 70 ? 'warning' : 'danger'); ?> usage-badge">
                                                                    <?php echo number_format($token['success_rate'], 1); ?>% success
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo timeAgo($token['last_used']); ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M d, Y', strtotime($token['created_at'])); ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo date('H:i', strtotime($token['created_at'])); ?></small>
                                                        </td>
                                                        <td class="token-actions">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <a href="?action=toggle_status&token_id=<?php echo $token['id']; ?>"
                                                                    class="btn btn-<?php echo $token['is_active'] ? 'warning' : 'success'; ?>"
                                                                    title="<?php echo $token['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                                    onclick="return confirm('Are you sure you want to <?php echo $token['is_active'] ? 'deactivate' : 'activate'; ?> this token?')">
                                                                    <i class="fas fa-<?php echo $token['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                                </a>

                                                                <a href="?action=regenerate&token_id=<?php echo $token['id']; ?>"
                                                                    class="btn btn-info"
                                                                    title="Regenerate Token"
                                                                    onclick="return confirm('Regenerating will invalidate the current token. Applications using this token will need to be updated. Continue?')">
                                                                    <i class="fas fa-sync-alt"></i>
                                                                </a>

                                                                <button class="btn btn-secondary"
                                                                    onclick="viewTokenDetails(<?php echo $token['id']; ?>)"
                                                                    title="View Details">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>

                                                                <a href="?action=delete&token_id=<?php echo $token['id']; ?>"
                                                                    class="btn btn-danger"
                                                                    title="Revoke Token"
                                                                    onclick="return confirm('Are you sure you want to revoke this token? This action cannot be undone and will immediately disable all API access using this token.')">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <div class="empty-state">
                                                            <i class="fas fa-key fa-3x text-muted mb-3"></i>
                                                            <h5 class="text-muted">No API Tokens Found</h5>
                                                            <p class="text-muted">You haven't created any API tokens yet.</p>
                                                            <a href="generate.php" class="btn btn-primary">
                                                                <i class="fas fa-plus"></i> Generate Your First Token
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Token Details Modal -->
                    <div class="modal fade" id="tokenDetailsModal" tabindex="-1" role="dialog">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-key"></i>
                                        Token Details
                                    </h5>
                                    <button type="button" class="close" data-dismiss="modal">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body" id="tokenDetailsContent">
                                    <div class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                                        <p class="mt-2">Loading token details...</p>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
    <!-- DataTables -->
    <script src="../../assets/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
    <script src="../../assets/adminlte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
    <!-- AdminLTE App -->
    <script src="../../assets/adminlte/dist/js/adminlte.min.js"></script>

    <script>
        $(document).ready(function() {
            'use strict';

            // Initialize DataTable
            $('#tokens-table').DataTable({
                responsive: true,
                lengthChange: false,
                autoWidth: false,
                order: [
                    [6, 'desc']
                ], // Sort by created date
                pageLength: 25,
                language: {
                    search: "",
                    searchPlaceholder: "Search tokens..."
                },
                columnDefs: [{
                        orderable: false,
                        targets: [7]
                    } // Disable sorting on actions column
                ]
            });

            // Quick search functionality
            $('#table-search').on('keyup', function() {
                $('#tokens-table').DataTable().search(this.value).draw();
            });

            // Auto-refresh every 5 minutes
            setInterval(function() {
                refreshTokens();
            }, 300000);
        });

        // Token visibility toggle
        function toggleTokenVisibility(tokenId, fullToken) {
            const displayElement = document.getElementById(`token-display-${tokenId}`);
            const eyeIcon = document.getElementById(`eye-${tokenId}`);

            if (displayElement.textContent.includes('...')) {
                // Show full token
                displayElement.textContent = fullToken;
                eyeIcon.className = 'fas fa-eye-slash';
            } else {
                // Hide token
                displayElement.textContent = formatTokenForDisplay(fullToken);
                eyeIcon.className = 'fas fa-eye';
            }
        }

        // Copy token to clipboard
        function copyToken(token) {
            const tempInput = document.createElement('input');
            tempInput.value = token;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);

            showToast('success', 'Token copied to clipboard');
        }

        // Format token for display (JavaScript version)
        function formatTokenForDisplay(token) {
            if (token.length <= 16) {
                return token;
            }
            return token.substring(0, 8) + '...' + token.substring(token.length - 8);
        }

        // View token details modal
        function viewTokenDetails(tokenId) {
            $('#tokenDetailsModal').modal('show');

            $.ajax({
                url: '../../api/tokens.php',
                method: 'GET',
                data: {
                    action: 'details',
                    token_id: tokenId
                },
                success: function(response) {
                    if (response.success) {
                        renderTokenDetails(response.data);
                    } else {
                        $('#tokenDetailsContent').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                Error: ${response.message || 'Failed to load token details'}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#tokenDetailsContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-times"></i>
                            Failed to load token details. Please try again.
                        </div>
                    `);
                }
            });
        }

        function renderTokenDetails(tokenData) {
            const successRate = tokenData.usage_stats.total_calls > 0 ?
                ((tokenData.usage_stats.success_calls / tokenData.usage_stats.total_calls) * 100).toFixed(1) : 0;

            const html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle"></i> Basic Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Token Name:</strong></td>
                                <td>${escapeHtml(tokenData.token_name)}</td>
                            </tr>
                            <tr>
                                <td><strong>Device:</strong></td>
                                <td>${tokenData.device_name ? escapeHtml(tokenData.device_name) : 'No device'}</td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge badge-${tokenData.is_active ? 'success' : 'secondary'}">
                                        ${tokenData.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td>${new Date(tokenData.created_at).toLocaleString()}</td>
                            </tr>
                            <tr>
                                <td><strong>Last Used:</strong></td>
                                <td>${tokenData.last_used ? new Date(tokenData.last_used).toLocaleString() : 'Never'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-chart-bar"></i> Usage Statistics</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Total Calls:</strong></td>
                                <td><span class="badge badge-primary">${tokenData.usage_stats.total_calls.toLocaleString()}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Success Calls:</strong></td>
                                <td><span class="badge badge-success">${tokenData.usage_stats.success_calls.toLocaleString()}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Failed Calls:</strong></td>
                                <td><span class="badge badge-danger">${tokenData.usage_stats.failed_calls.toLocaleString()}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Success Rate:</strong></td>
                                <td><span class="badge badge-${successRate >= 90 ? 'success' : (successRate >= 70 ? 'warning' : 'danger')}">${successRate}%</span></td>
                            </tr>
                            <tr>
                                <td><strong>Today's Calls:</strong></td>
                                <td><span class="badge badge-info">${tokenData.usage_stats.today_calls.toLocaleString()}</span></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-12">
                        <h6><i class="fas fa-key"></i> Token Information</h6>
                        <div class="form-group">
                            <label>Full Token:</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="modal-token-display" value="${tokenData.token}" readonly>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="toggleModalTokenVisibility()">
                                        <i class="fas fa-eye" id="modal-token-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyToken('${tokenData.token}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Keep this token secure and never share it publicly.</small>
                        </div>
                    </div>
                </div>

                ${tokenData.description ? `
                <div class="row">
                    <div class="col-12">
                        <h6><i class="fas fa-comment"></i> Description</h6>
                        <p class="text-muted">${escapeHtml(tokenData.description)}</p>
                    </div>
                </div>
                ` : ''}

                <div class="row">
                    <div class="col-12">
                        <h6><i class="fas fa-history"></i> Recent Activity</h6>
                        ${tokenData.recent_activity && tokenData.recent_activity.length > 0 ? `
                            <div class="timeline">
                                ${tokenData.recent_activity.map(activity => `
                                    <div class="time-label">
                                        <span class="bg-${activity.type === 'success' ? 'success' : 'danger'}">
                                            ${new Date(activity.created_at).toLocaleDateString()}
                                        </span>
                                    </div>
                                    <div>
                                        <i class="fas fa-${activity.type === 'success' ? 'check' : 'times'} bg-${activity.type === 'success' ? 'success' : 'danger'}"></i>
                                        <div class="timeline-item">
                                            <h3 class="timeline-header">${escapeHtml(activity.endpoint || 'API Call')}</h3>
                                            <div class="timeline-body">
                                                ${activity.response_code ? `Response Code: ${activity.response_code}` : ''}
                                                ${activity.error_message ? `<br>Error: ${escapeHtml(activity.error_message)}` : ''}
                                            </div>
                                            <div class="timeline-footer">
                                                <small class="text-muted">${new Date(activity.created_at).toLocaleTimeString()}</small>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : '<p class="text-muted">No recent activity</p>'}
                    </div>
                </div>
            `;

            $('#tokenDetailsContent').html(html);
        }

        function toggleModalTokenVisibility() {
            const tokenInput = document.getElementById('modal-token-display');
            const eyeIcon = document.getElementById('modal-token-eye');

            if (tokenInput.type === 'password') {
                tokenInput.type = 'text';
                eyeIcon.className = 'fas fa-eye-slash';
            } else {
                tokenInput.type = 'password';
                eyeIcon.className = 'fas fa-eye';
            }
        }

        // Refresh tokens
        function refreshTokens() {
            window.location.reload();
        }

        // Export tokens
        function exportTokens() {
            const url = new URL('../../api/tokens.php', window.location.origin);
            url.searchParams.append('action', 'export');
            url.searchParams.append('format', 'csv');

            // Add current filters
            const formData = new FormData(document.getElementById('filter-form'));
            for (const [key, value] of formData) {
                if (value) {
                    url.searchParams.append(key, value);
                }
            }

            window.open(url.toString(), '_blank');
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

        function showToast(type, message) {
            // Create toast notification
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

        // Auto-submit filters on change
        $('#status, #device_id').on('change', function() {
            $('#filter-form').submit();
        });

        // Search with debounce
        let searchTimeout;
        $('#search').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                $('#filter-form').submit();
            }, 500);
        });
    </script>

</body>

</html>