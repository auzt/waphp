<?php

/**
 * ===============================================================================
 * API TOKENS REVOKE - Revoke Token Handler
 * ===============================================================================
 * Halaman untuk revoke/hapus token API dengan fitur:
 * - Konfirmasi revoke token
 * - Bulk revoke multiple tokens
 * - Safety checks dan warnings
 * - Log revocation activity
 * - Grace period untuk undo
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

// Get parameters
$token_id = $_GET['token_id'] ?? $_POST['token_id'] ?? null;
$token_ids = $_POST['token_ids'] ?? []; // For bulk operations
$action = $_GET['action'] ?? $_POST['action'] ?? 'confirm';
$confirmed = $_POST['confirmed'] ?? false;

// Handle different actions
$errors = [];
$success = false;
$tokenData = null;
$tokensData = [];
$actionType = '';

try {
    if ($action === 'bulk' && !empty($token_ids)) {
        // Bulk revoke handling
        $actionType = 'bulk';

        // Get all tokens data for confirmation
        foreach ($token_ids as $id) {
            if (is_numeric($id)) {
                $token = $apiToken->getById($id, $currentUser['id']);
                if ($token) {
                    $tokensData[] = $token;
                }
            }
        }

        if (empty($tokensData)) {
            $errors[] = 'No valid tokens selected for revocation';
        }

        // Process bulk revocation if confirmed
        if ($confirmed && !empty($tokensData)) {
            $revokedCount = 0;
            $failedTokens = [];

            foreach ($tokensData as $token) {
                $result = $apiToken->revokeToken($token['id'], $currentUser['id']);
                if ($result) {
                    $revokedCount++;

                    // Log activity
                    logActivity(
                        $currentUser['id'],
                        'token_revoked',
                        "Revoked token: {$token['token_name']} (Bulk operation)",
                        $token['id']
                    );
                } else {
                    $failedTokens[] = $token['token_name'];
                }
            }

            if ($revokedCount > 0) {
                $success = true;
                $_SESSION['success_message'] = "{$revokedCount} token(s) revoked successfully";

                if (!empty($failedTokens)) {
                    $_SESSION['warning_message'] = "Failed to revoke: " . implode(', ', $failedTokens);
                }
            } else {
                $errors[] = 'Failed to revoke any tokens';
            }
        }
    } elseif ($token_id && is_numeric($token_id)) {
        // Single token handling
        $actionType = 'single';
        $tokenData = $apiToken->getById($token_id, $currentUser['id']);

        if (!$tokenData) {
            $errors[] = 'Token not found or access denied';
        } elseif (!$tokenData['is_active']) {
            $errors[] = 'Token is already inactive';
        }

        // Process single revocation if confirmed
        if ($confirmed && $tokenData && empty($errors)) {
            $result = $apiToken->revokeToken($token_id, $currentUser['id']);

            if ($result) {
                $success = true;
                $_SESSION['success_message'] = "Token '{$tokenData['token_name']}' has been revoked successfully";

                // Log activity
                logActivity(
                    $currentUser['id'],
                    'token_revoked',
                    "Revoked token: {$tokenData['token_name']}",
                    $token_id
                );
            } else {
                $errors[] = 'Failed to revoke token. Please try again.';
            }
        }
    } else {
        $errors[] = 'No token specified for revocation';
    }

    // Redirect back to tokens list if successful
    if ($success) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Token revocation error: " . $e->getMessage());
    $errors[] = 'An error occurred: ' . $e->getMessage();
}

// Page settings
$pageTitle = $actionType === 'bulk' ? 'Revoke Multiple Tokens' : 'Revoke API Token';
$currentPage = 'api-tokens';

// Helper functions
function getImpactLevel($tokenData, $apiLogger)
{
    if (!$tokenData) return 'low';

    $usage = $tokenData['usage_count'] ?? 0;
    $lastUsed = $tokenData['last_used'] ?? null;

    // Check if recently used (within 7 days)
    $recentlyUsed = $lastUsed && (strtotime($lastUsed) > strtotime('-7 days'));

    if ($usage > 1000 || $recentlyUsed) {
        return 'high';
    } elseif ($usage > 100) {
        return 'medium';
    } else {
        return 'low';
    }
}

function getImpactClass($level)
{
    switch ($level) {
        case 'high':
            return 'danger';
        case 'medium':
            return 'warning';
        default:
            return 'info';
    }
}

function getImpactText($level)
{
    switch ($level) {
        case 'high':
            return 'High Impact - Actively used token';
        case 'medium':
            return 'Medium Impact - Moderately used token';
        default:
            return 'Low Impact - Minimal usage';
    }
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
    <!-- Theme style -->
    <link rel="stylesheet" href="../../assets/adminlte/dist/css/adminlte.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/custom/css/custom.css">

    <style>
        .revoke-header {
            background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .warning-container {
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .token-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .token-card.high-impact {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }

        .token-card.medium-impact {
            border-left: 4px solid #ffc107;
            background: #fffdf5;
        }

        .token-card.low-impact {
            border-left: 4px solid #17a2b8;
            background: #f5fffe;
        }

        .impact-badge {
            font-size: 0.75em;
            padding: 4px 8px;
        }

        .countdown-timer {
            background: #343a40;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            text-align: center;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
        }

        .safety-checklist {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
        }

        .consequences-list {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 20px 0;
        }

        .confirmation-form {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        @keyframes pulse-danger {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }

        .danger-pulse {
            animation: pulse-danger 2s infinite;
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
                                <li class="breadcrumb-item"><a href="index.php">API Tokens</a></li>
                                <li class="breadcrumb-item active">Revoke</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <!-- Error Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-3">
                                <a href="index.php" class="btn btn-outline-danger">
                                    <i class="fas fa-arrow-left"></i> Back to Tokens
                                </a>
                            </div>
                        </div>
                    <?php else: ?>

                        <!-- Revoke Header -->
                        <div class="revoke-header">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h2>
                                        <i class="fas fa-exclamation-triangle me-3"></i>
                                        <?php echo $actionType === 'bulk' ? 'Revoke Multiple Tokens' : 'Revoke API Token'; ?>
                                    </h2>
                                    <p class="mb-0">
                                        <?php if ($actionType === 'bulk'): ?>
                                            You are about to revoke <?php echo count($tokensData); ?> API token(s). This action is irreversible.
                                        <?php else: ?>
                                            You are about to revoke the API token. This action is irreversible.
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-ban fa-4x opacity-50"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Critical Warning -->
                        <div class="warning-container">
                            <h5><i class="fas fa-skull-crossbones me-2"></i>Critical Action Warning!</h5>
                            <p class="mb-2">Revoking API tokens will immediately:</p>
                            <ul class="mb-2">
                                <li><strong>Block all API access</strong> using the token(s)</li>
                                <li><strong>Break applications</strong> that depend on these tokens</li>
                                <li><strong>Cannot be undone</strong> - you'll need to generate new tokens</li>
                                <li><strong>Stop all automated processes</strong> using these tokens</li>
                            </ul>
                            <div class="alert alert-light mt-3 mb-0">
                                <i class="fas fa-lightbulb"></i>
                                <strong>Alternative:</strong> Consider deactivating tokens temporarily instead of revoking them permanently.
                            </div>
                        </div>

                        <!-- Token(s) Information -->
                        <?php if ($actionType === 'single' && $tokenData): ?>
                            <!-- Single Token -->
                            <?php
                            $impactLevel = getImpactLevel($tokenData, $apiLogger);
                            $impactClass = getImpactClass($impactLevel);
                            $impactText = getImpactText($impactLevel);
                            ?>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="token-card <?php echo $impactLevel; ?>-impact">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5>
                                                    <i class="fas fa-key"></i>
                                                    <?php echo htmlspecialchars($tokenData['token_name']); ?>
                                                    <span class="badge badge-<?php echo $impactClass; ?> impact-badge ml-2">
                                                        <?php echo strtoupper($impactLevel); ?> IMPACT
                                                    </span>
                                                </h5>

                                                <?php if ($tokenData['description']): ?>
                                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($tokenData['description']); ?></p>
                                                <?php endif; ?>

                                                <div class="row">
                                                    <div class="col-sm-6">
                                                        <small><strong>Device:</strong>
                                                            <?php echo $tokenData['device_name'] ? htmlspecialchars($tokenData['device_name']) : 'No device'; ?>
                                                        </small><br>
                                                        <small><strong>Created:</strong> <?php echo date('M d, Y', strtotime($tokenData['created_at'])); ?></small>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <small><strong>Usage:</strong> <?php echo number_format($tokenData['usage_count'] ?? 0); ?> calls</small><br>
                                                        <small><strong>Last Used:</strong>
                                                            <?php echo $tokenData['last_used'] ? date('M d, Y H:i', strtotime($tokenData['last_used'])) : 'Never'; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="h4 text-<?php echo $impactClass; ?>">
                                                    <i class="fas fa-<?php echo $impactLevel === 'high' ? 'exclamation-triangle' : ($impactLevel === 'medium' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                                                </div>
                                                <small class="text-<?php echo $impactClass; ?>"><?php echo $impactText; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header bg-<?php echo $impactClass; ?> text-white">
                                            <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Token Statistics</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <div class="h4 text-primary"><?php echo number_format($tokenData['usage_count'] ?? 0); ?></div>
                                                    <small class="text-muted">Total Calls</small>
                                                </div>
                                                <div class="col-6">
                                                    <div class="h4 text-success">
                                                        <?php
                                                        $successRate = $tokenData['usage_count'] > 0 && isset($tokenData['success_rate'])
                                                            ? number_format($tokenData['success_rate'], 1)
                                                            : '0';
                                                        echo $successRate;
                                                        ?>%
                                                    </div>
                                                    <small class="text-muted">Success Rate</small>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="text-center">
                                                <small class="text-muted">
                                                    Active for <?php echo floor((time() - strtotime($tokenData['created_at'])) / 86400); ?> days
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($actionType === 'bulk' && !empty($tokensData)): ?>
                            <!-- Multiple Tokens -->
                            <div class="row">
                                <div class="col-12">
                                    <h5><i class="fas fa-list"></i> Tokens to be Revoked (<?php echo count($tokensData); ?>)</h5>

                                    <?php foreach ($tokensData as $token): ?>
                                        <?php
                                        $impactLevel = getImpactLevel($token, $apiLogger);
                                        $impactClass = getImpactClass($impactLevel);
                                        $impactText = getImpactText($impactLevel);
                                        ?>

                                        <div class="token-card <?php echo $impactLevel; ?>-impact">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <h6>
                                                        <i class="fas fa-key"></i>
                                                        <?php echo htmlspecialchars($token['token_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo $token['device_name'] ? htmlspecialchars($token['device_name']) : 'No device'; ?> |
                                                        Created: <?php echo date('M d, Y', strtotime($token['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-3 text-center">
                                                    <div class="h6"><?php echo number_format($token['usage_count'] ?? 0); ?></div>
                                                    <small class="text-muted">API Calls</small>
                                                </div>
                                                <div class="col-md-3 text-right">
                                                    <span class="badge badge-<?php echo $impactClass; ?> impact-badge">
                                                        <?php echo strtoupper($impactLevel); ?> IMPACT
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Consequences Warning -->
                        <div class="consequences-list">
                            <h6><i class="fas fa-exclamation-triangle"></i> Immediate Consequences</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="mb-0">
                                        <li><strong>API Access:</strong> All requests will return 401 Unauthorized</li>
                                        <li><strong>Applications:</strong> May stop working or throw errors</li>
                                        <li><strong>Automation:</strong> Scheduled processes will fail</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="mb-0">
                                        <li><strong>Recovery:</strong> Requires generating new tokens</li>
                                        <li><strong>Data:</strong> Usage history will be preserved</li>
                                        <li><strong>Logs:</strong> Past API calls remain accessible</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Safety Checklist -->
                        <div class="safety-checklist">
                            <h6><i class="fas fa-clipboard-check"></i> Safety Checklist</h6>
                            <p>Before proceeding, ensure you have:</p>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check1">
                                <label class="form-check-label" for="check1">
                                    Identified all applications using <?php echo $actionType === 'bulk' ? 'these tokens' : 'this token'; ?>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check2">
                                <label class="form-check-label" for="check2">
                                    Prepared replacement tokens (if needed)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check3">
                                <label class="form-check-label" for="check3">
                                    Notified team members about the revocation
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check4">
                                <label class="form-check-label" for="check4">
                                    Have a rollback plan if issues occur
                                </label>
                            </div>
                        </div>

                        <!-- Countdown Timer (for high impact tokens) -->
                        <?php if (($actionType === 'single' && isset($tokenData) && getImpactLevel($tokenData, $apiLogger) === 'high') ||
                            ($actionType === 'bulk' && !empty($tokensData) && array_filter($tokensData, function ($t) use ($apiLogger) {
                                return getImpactLevel($t, $apiLogger) === 'high';
                            }))
                        ): ?>
                            <div class="countdown-timer" id="countdown-timer">
                                <i class="fas fa-clock"></i>
                                Please wait <span id="countdown">10</span> seconds before proceeding with high-impact revocation...
                            </div>
                        <?php endif; ?>

                        <!-- Confirmation Form -->
                        <div class="confirmation-form">
                            <form method="POST" action="" id="revoke-form">
                                <input type="hidden" name="action" value="<?php echo htmlspecialchars($actionType); ?>">
                                <?php if ($actionType === 'single'): ?>
                                    <input type="hidden" name="token_id" value="<?php echo htmlspecialchars($token_id); ?>">
                                <?php else: ?>
                                    <?php foreach ($token_ids as $id): ?>
                                        <input type="hidden" name="token_ids[]" value="<?php echo htmlspecialchars($id); ?>">
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <input type="hidden" name="confirmed" value="1">

                                <div class="text-center">
                                    <h5 class="text-danger mb-4">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Final Confirmation Required
                                    </h5>

                                    <div class="form-group">
                                        <label for="confirmation-text">
                                            Type <strong>"REVOKE"</strong> to confirm this action:
                                        </label>
                                        <input type="text"
                                            class="form-control text-center"
                                            id="confirmation-text"
                                            placeholder="Type REVOKE here"
                                            style="max-width: 200px; margin: 0 auto;">
                                    </div>

                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="understand-consequences">
                                            <label class="custom-control-label" for="understand-consequences">
                                                I understand this action is irreversible and may break applications
                                            </label>
                                        </div>
                                    </div>

                                    <div class="btn-group" role="group">
                                        <button type="submit"
                                            class="btn btn-danger btn-lg danger-pulse"
                                            id="revoke-btn"
                                            disabled>
                                            <i class="fas fa-ban"></i>
                                            <?php echo $actionType === 'bulk' ? 'Revoke All Tokens' : 'Revoke Token'; ?>
                                        </button>
                                        <a href="index.php" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>

                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-shield-alt"></i>
                                            Alternative: <a href="index.php">Deactivate tokens temporarily</a> instead of permanent revocation
                                        </small>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Alternative Actions -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fas fa-pause"></i> Safer Alternative</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>Consider temporarily deactivating tokens instead:</p>
                                        <ul class="mb-3">
                                            <li>Blocks API access immediately</li>
                                            <li>Can be reactivated later</li>
                                            <li>Preserves token for future use</li>
                                        </ul>
                                        <a href="index.php" class="btn btn-info">
                                            <i class="fas fa-pause"></i> Deactivate Instead
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="fas fa-sync-alt"></i> Token Regeneration</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>Generate a new token value while keeping the same token:</p>
                                        <ul class="mb-3">
                                            <li>Invalidates old token immediately</li>
                                            <li>Provides new secure token</li>
                                            <li>Maintains token history</li>
                                        </ul>
                                        <a href="index.php" class="btn btn-success">
                                            <i class="fas fa-sync-alt"></i> Regenerate Instead
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>

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

            let countdownActive = false;
            let confirmationValid = false;
            let checklistComplete = false;
            let countdownFinished = false;

            // Initialize countdown if needed
            <?php if (($actionType === 'single' && isset($tokenData) && getImpactLevel($tokenData, $apiLogger) === 'high') ||
                ($actionType === 'bulk' && !empty($tokensData) && array_filter($tokensData, function ($t) use ($apiLogger) {
                    return getImpactLevel($t, $apiLogger) === 'high';
                }))
            ): ?>
                startCountdown();
            <?php else: ?>
                countdownFinished = true;
            <?php endif; ?>

            // Countdown function
            function startCountdown() {
                countdownActive = true;
                let seconds = 10;

                const timer = setInterval(() => {
                    $('#countdown').text(seconds);
                    seconds--;

                    if (seconds < 0) {
                        clearInterval(timer);
                        countdownActive = false;
                        countdownFinished = true;
                        $('#countdown-timer').html(`
                            <i class="fas fa-check-circle text-success"></i>
                            Countdown complete. You may now proceed with revocation.
                        `).removeClass('countdown-timer').addClass('alert alert-success');
                        checkFormValidity();
                    }
                }, 1000);
            }

            // Confirmation text validation
            $('#confirmation-text').on('input', function() {
                const value = $(this).val().trim().toUpperCase();
                confirmationValid = value === 'REVOKE';

                if (confirmationValid) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                }

                checkFormValidity();
            });

            // Understand consequences checkbox
            $('#understand-consequences').on('change', function() {
                checkFormValidity();
            });

            // Safety checklist
            $('.safety-checklist input[type="checkbox"]').on('change', function() {
                const totalChecks = $('.safety-checklist input[type="checkbox"]').length;
                const checkedBoxes = $('.safety-checklist input[type="checkbox"]:checked').length;
                checklistComplete = checkedBoxes === totalChecks;

                // Visual feedback
                if (checklistComplete) {
                    $('.safety-checklist').removeClass('border-primary').addClass('border-success');
                    $('.safety-checklist h6').html('<i class="fas fa-check-circle text-success"></i> Safety Checklist Complete');
                } else {
                    $('.safety-checklist').removeClass('border-success').addClass('border-primary');
                    $('.safety-checklist h6').html('<i class="fas fa-clipboard-check"></i> Safety Checklist');
                }

                checkFormValidity();
            });

            // Check overall form validity
            function checkFormValidity() {
                const understoodConsequences = $('#understand-consequences').is(':checked');
                const allValid = confirmationValid && understoodConsequences && checklistComplete && countdownFinished;

                $('#revoke-btn').prop('disabled', !allValid);

                if (allValid) {
                    $('#revoke-btn').removeClass('btn-secondary').addClass('btn-danger danger-pulse');
                } else {
                    $('#revoke-btn').removeClass('btn-danger danger-pulse').addClass('btn-secondary');
                }
            }

            // Form submission
            $('#revoke-form').on('submit', function(e) {
                if (!confirmationValid) {
                    e.preventDefault();
                    alert('Please type "REVOKE" to confirm this action.');
                    $('#confirmation-text').focus();
                    return false;
                }

                if (!$('#understand-consequences').is(':checked')) {
                    e.preventDefault();
                    alert('Please acknowledge that you understand the consequences.');
                    $('#understand-consequences').focus();
                    return false;
                }

                if (!checklistComplete) {
                    e.preventDefault();
                    alert('Please complete the safety checklist before proceeding.');
                    return false;
                }

                // Final confirmation
                const tokenCount = <?php echo $actionType === 'bulk' ? count($tokensData ?? []) : 1; ?>;
                const message = tokenCount > 1 ?
                    `Are you absolutely sure you want to revoke ${tokenCount} tokens? This action cannot be undone.` :
                    'Are you absolutely sure you want to revoke this token? This action cannot be undone.';

                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }

                // Show loading state
                $('#revoke-btn').prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin"></i> Revoking...');
            });

            // Escape key to cancel
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (confirm('Are you sure you want to cancel the revocation process?')) {
                        window.location.href = 'index.php';
                    }
                }
            });

            // Warning on page leave
            let formSubmitted = false;

            $('#revoke-form').on('submit', function() {
                formSubmitted = true;
            });

            $(window).on('beforeunload', function(e) {
                if (!formSubmitted && ($('#confirmation-text').val() || $('.safety-checklist input[type="checkbox"]:checked').length > 0)) {
                    const message = 'You have started the token revocation process. Are you sure you want to leave?';
                    e.returnValue = message;
                    return message;
                }
            });

            // Auto-focus confirmation text
            setTimeout(() => {
                $('#confirmation-text').focus();
            }, 500);

            // Enhanced visual feedback
            function addVisualFeedback() {
                // Add ripple effect to buttons
                $('.btn').on('click', function(e) {
                    const btn = $(this);
                    const ripple = $('<span class="ripple"></span>');

                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;

                    ripple.css({
                        width: size,
                        height: size,
                        left: x,
                        top: y
                    }).appendTo(btn);

                    setTimeout(() => ripple.remove(), 600);
                });

                // Add hover effects to cards
                $('.token-card').on('mouseenter', function() {
                    $(this).addClass('shadow-lg');
                }).on('mouseleave', function() {
                    $(this).removeClass('shadow-lg');
                });
            }

            addVisualFeedback();

            // Accessibility improvements
            function enhanceAccessibility() {
                // Add ARIA labels
                $('#confirmation-text').attr('aria-describedby', 'confirmation-help');
                $('<div id="confirmation-help" class="sr-only">Type the word REVOKE in capital letters to confirm token revocation</div>').appendTo('body');

                // Add live region for status updates
                $('<div id="status-live" class="sr-only" aria-live="polite" aria-atomic="true"></div>').appendTo('body');

                // Update live region on form changes
                $('#revoke-form input, #revoke-form checkbox').on('change', function() {
                    const completed = $('.safety-checklist input[type="checkbox"]:checked').length;
                    const total = $('.safety-checklist input[type="checkbox"]').length;
                    const confirmationStatus = confirmationValid ? 'entered' : 'not entered';

                    $('#status-live').text(`Safety checklist: ${completed} of ${total} items completed. Confirmation text: ${confirmationStatus}.`);
                });
            }

            enhanceAccessibility();
        });

        // Additional utility functions
        function resetForm() {
            $('#confirmation-text').val('').removeClass('is-valid is-invalid');
            $('#understand-consequences').prop('checked', false);
            $('.safety-checklist input[type="checkbox"]').prop('checked', false);
            $('#revoke-btn').prop('disabled', true).removeClass('btn-danger danger-pulse').addClass('btn-secondary');
        }

        // Dev helper functions (only in development)
        <?php if (defined('APP_ENV') && APP_ENV === 'development'): ?>
            window.devHelpers = {
                fillForm: () => {
                    $('.safety-checklist input[type="checkbox"]').prop('checked', true).trigger('change');
                    $('#understand-consequences').prop('checked', true).trigger('change');
                    $('#confirmation-text').val('REVOKE').trigger('input');
                },
                resetForm: resetForm
            };

            console.log('Dev helpers available: window.devHelpers.fillForm(), window.devHelpers.resetForm()');
        <?php endif; ?>
    </script>

    <style>
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
            z-index: 1;
        }

        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        .focus-highlight {
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25) !important;
            transition: box-shadow 0.2s ease;
        }

        .shadow-lg {
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175) !important;
            transition: box-shadow 0.3s ease;
        }

        .sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        /* Enhanced button styles */
        .btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .btn:focus {
            outline: 2px solid #007bff;
            outline-offset: 2px;
        }

        /* Form validation styles */
        .form-control.is-valid {
            border-color: #28a745;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.94-.94 2.84-2.84-.94-.94-2.84 2.84-1.06-1.06-.94.94 2 2z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        /* Loading spinner */
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .fa-spin {
            animation: spin 1s linear infinite;
        }

        /* Accessibility improvements */
        @media (prefers-reduced-motion: reduce) {

            .danger-pulse,
            .ripple,
            .fa-spin {
                animation: none !important;
            }

            * {
                transition: none !important;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .token-card {
                border: 2px solid !important;
            }

            .badge {
                border: 1px solid;
            }

            .btn {
                border: 2px solid;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .confirmation-form {
                background: #2d3436;
                color: #ddd;
            }

            .token-card {
                background: #2d3436;
                border-color: #555;
                color: #ddd;
            }

            .safety-checklist,
            .consequences-list {
                background: #2d3436;
                color: #ddd;
            }
        }

        /* Print styles */
        @media print {

            .btn,
            .countdown-timer,
            .confirmation-form {
                display: none !important;
            }

            .token-card {
                border: 1px solid #000 !important;
                break-inside: avoid;
            }
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .revoke-header {
                padding: 15px;
                text-align: center;
            }

            .token-card {
                margin-bottom: 10px;
            }

            .btn-group .btn {
                display: block;
                width: 100%;
                margin-bottom: 10px;
            }

            .confirmation-form {
                padding: 15px;
            }
        }

        /* Tablet optimizations */
        @media (min-width: 768px) and (max-width: 1024px) {
            .revoke-header h2 {
                font-size: 1.5rem;
            }

            .token-card {
                padding: 15px;
            }
        }

        /* Large screen optimizations */
        @media (min-width: 1200px) {
            .container-fluid {
                max-width: 1140px;
            }
        }
    </style>

</body>

</html>