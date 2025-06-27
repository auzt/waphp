<?php

/**
 * ===============================================================================
 * API TOKENS GENERATE - Generate New API Token
 * ===============================================================================
 * Halaman untuk generate token API baru dengan fitur:
 * - Form generate token dengan validasi
 * - Pilih device untuk token
 * - Set nama dan deskripsi token
 * - Copy token yang baru dibuat
 * - Security warnings dan best practices
 * ===============================================================================
 */

// Include required files
require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Device.php';
require_once '../../classes/ApiToken.php';
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
$currentUser = getCurrentUser();

// Get user devices for dropdown
try {
    $userDevices = $device->getByUserId($currentUser['id']);
} catch (Exception $e) {
    error_log("Error loading devices: " . $e->getMessage());
    $userDevices = [];
}

// Handle form submission
$errors = [];
$success = false;
$generatedToken = null;
$generatedTokenData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $token_name = trim($_POST['token_name'] ?? '');
        $device_id = $_POST['device_id'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $expires_at = $_POST['expires_at'] ?? null;

        // Validation
        if (empty($token_name)) {
            $errors[] = 'Token name is required';
        } elseif (strlen($token_name) < 3) {
            $errors[] = 'Token name must be at least 3 characters';
        } elseif (strlen($token_name) > 100) {
            $errors[] = 'Token name must not exceed 100 characters';
        }

        if ($device_id && !is_numeric($device_id)) {
            $errors[] = 'Invalid device selection';
        }

        if ($device_id) {
            // Verify device belongs to user
            $deviceData = $device->getById($device_id, $currentUser['id']);
            if (!$deviceData) {
                $errors[] = 'Selected device not found or access denied';
            }
        }

        if ($description && strlen($description) > 500) {
            $errors[] = 'Description must not exceed 500 characters';
        }

        if ($expires_at) {
            $expiryDate = DateTime::createFromFormat('Y-m-d', $expires_at);
            if (!$expiryDate) {
                $errors[] = 'Invalid expiry date format';
            } elseif ($expiryDate <= new DateTime()) {
                $errors[] = 'Expiry date must be in the future';
            }
        }

        // Check if token name already exists for this user
        if (empty($errors)) {
            $existingToken = $apiToken->getByNameAndUserId($token_name, $currentUser['id']);
            if ($existingToken) {
                $errors[] = 'A token with this name already exists';
            }
        }

        // Generate token if no errors
        if (empty($errors)) {
            $tokenData = [
                'token_name' => $token_name,
                'device_id' => $device_id ?: null,
                'description' => $description ?: null,
                'expires_at' => $expires_at ?: null,
                'user_id' => $currentUser['id']
            ];

            $result = $apiToken->create($tokenData);

            if ($result) {
                $success = true;
                $generatedTokenData = $apiToken->getById($result, $currentUser['id']);
                $generatedToken = $generatedTokenData['token'];

                // Log activity
                logActivity(
                    $currentUser['id'],
                    'token_generated',
                    "Generated new API token: {$token_name}",
                    $result
                );

                // Clear form data on success
                $_POST = [];
            } else {
                $errors[] = 'Failed to generate token. Please try again.';
            }
        }
    } catch (Exception $e) {
        error_log("Token generation error: " . $e->getMessage());
        $errors[] = 'An error occurred while generating the token: ' . $e->getMessage();
    }
}

// Page settings
$pageTitle = 'Generate New API Token';
$currentPage = 'api-tokens';
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
        .generate-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .security-warning {
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .token-display {
            background: #f8f9fa;
            border: 2px dashed #28a745;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            margin: 20px 0;
        }

        .success-container {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-step {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .step-number {
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }

        .best-practices {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-top: 20px;
        }

        .feature-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .device-preview {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
            margin-top: 10px;
        }

        .copy-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
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
                            <h1 class="m-0">Generate New API Token</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">API Tokens</a></li>
                                <li class="breadcrumb-item active">Generate</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <?php if ($success && $generatedToken): ?>
                        <!-- Success Section -->
                        <div class="success-container">
                            <h2><i class="fas fa-check-circle fa-2x mb-3"></i></h2>
                            <h3>API Token Generated Successfully!</h3>
                            <p class="mb-4">Your new API token has been created and is ready to use.</p>

                            <div class="token-display">
                                <h5>Your New API Token:</h5>
                                <div class="h4 mb-3" id="generated-token"><?php echo htmlspecialchars($generatedToken); ?></div>
                                <button class="btn btn-light btn-lg" onclick="copyGeneratedToken()">
                                    <i class="fas fa-copy"></i> Copy Token
                                </button>
                                <div class="copy-success" id="copy-success">
                                    <i class="fas fa-check"></i> Token copied to clipboard!
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card bg-transparent border-light">
                                        <div class="card-body text-left">
                                            <h6><i class="fas fa-info-circle"></i> Token Details</h6>
                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($generatedTokenData['token_name']); ?></p>
                                            <?php if ($generatedTokenData['device_name']): ?>
                                                <p><strong>Device:</strong> <?php echo htmlspecialchars($generatedTokenData['device_name']); ?></p>
                                            <?php endif; ?>
                                            <p><strong>Status:</strong> <span class="badge badge-success">Active</span></p>
                                            <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($generatedTokenData['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-transparent border-light">
                                        <div class="card-body text-left">
                                            <h6><i class="fas fa-exclamation-triangle"></i> Important Security Notes</h6>
                                            <ul class="mb-0">
                                                <li>Save this token securely - it won't be shown again</li>
                                                <li>Never share this token publicly</li>
                                                <li>Use HTTPS when making API calls</li>
                                                <li>Monitor token usage regularly</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <a href="index.php" class="btn btn-light btn-lg">
                                    <i class="fas fa-list"></i> View All Tokens
                                </a>
                                <a href="generate.php" class="btn btn-outline-light btn-lg">
                                    <i class="fas fa-plus"></i> Generate Another
                                </a>
                            </div>
                        </div>

                        <!-- API Usage Examples -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-code"></i>
                                            API Usage Examples
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>cURL Example:</h6>
                                                <pre class="bg-dark text-light p-3 rounded"><code>curl -X POST \
  http://localhost:3000/api/message/send \
  -H 'x-api-key: <?php echo htmlspecialchars($generatedToken); ?>' \
  -H 'Content-Type: application/json' \
  -d '{
    "sessionId": "your_session",
    "to": "628123456789",
    "message": "Hello World!"
  }'</code></pre>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>JavaScript Example:</h6>
                                                <pre class="bg-dark text-light p-3 rounded"><code>fetch('http://localhost:3000/api/message/send', {
  method: 'POST',
  headers: {
    'x-api-key': '<?php echo htmlspecialchars($generatedToken); ?>',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    sessionId: 'your_session',
    to: '628123456789',
    message: 'Hello World!'
  })
})</code></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Generate Form Section -->
                        <div class="generate-header">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h2><i class="fas fa-key me-3"></i>Generate New API Token</h2>
                                    <p class="mb-0">Create a new API token to access WhatsApp Monitor API endpoints securely.</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-shield-alt fa-4x opacity-50"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Security Warning -->
                        <div class="security-warning">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>Security Important!</h5>
                            <p class="mb-2">API tokens provide full access to your WhatsApp devices. Please:</p>
                            <ul class="mb-0">
                                <li>Keep tokens secure and never share them publicly</li>
                                <li>Use different tokens for different applications</li>
                                <li>Regularly monitor token usage and revoke unused tokens</li>
                                <li>Always use HTTPS when making API calls</li>
                            </ul>
                        </div>

                        <!-- Error Messages -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <h5><i class="icon fas fa-ban"></i> Validation Errors!</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Generate Form -->
                        <form method="POST" action="" id="generate-form">
                            <!-- Step 1: Basic Information -->
                            <div class="form-step">
                                <h5>
                                    <span class="step-number">1</span>
                                    Basic Token Information
                                </h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="token_name">
                                                <i class="fas fa-tag"></i>
                                                Token Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                class="form-control"
                                                id="token_name"
                                                name="token_name"
                                                value="<?php echo htmlspecialchars($_POST['token_name'] ?? ''); ?>"
                                                placeholder="e.g., Production API Token"
                                                maxlength="100"
                                                required>
                                            <small class="form-text text-muted">
                                                A descriptive name to identify this token (3-100 characters)
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="device_id">
                                                <i class="fas fa-mobile-alt"></i>
                                                Associate with Device (Optional)
                                            </label>
                                            <select class="form-control" id="device_id" name="device_id">
                                                <option value="">No specific device</option>
                                                <?php foreach ($userDevices as $device): ?>
                                                    <option value="<?php echo $device['id']; ?>"
                                                        <?php echo ($_POST['device_id'] ?? '') == $device['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($device['device_name']); ?>
                                                        (+<?php echo $device['phone_number']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">
                                                Link this token to a specific device for better organization
                                            </small>

                                            <!-- Device Preview -->
                                            <div class="device-preview" id="device-preview" style="display: none;">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-mobile-alt me-2"></i>
                                                    <div>
                                                        <strong id="preview-device-name"></strong>
                                                        <br>
                                                        <small class="text-muted" id="preview-device-phone"></small>
                                                    </div>
                                                    <div class="ml-auto">
                                                        <span class="badge" id="preview-device-status"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description">
                                        <i class="fas fa-comment"></i>
                                        Description (Optional)
                                    </label>
                                    <textarea class="form-control"
                                        id="description"
                                        name="description"
                                        rows="3"
                                        placeholder="Describe what this token will be used for..."
                                        maxlength="500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">
                                        Help identify the purpose of this token (max 500 characters)
                                    </small>
                                </div>
                            </div>

                            <!-- Step 2: Security Settings -->
                            <div class="form-step">
                                <h5>
                                    <span class="step-number">2</span>
                                    Security Settings
                                </h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="expires_at">
                                                <i class="fas fa-calendar-times"></i>
                                                Token Expiry Date (Optional)
                                            </label>
                                            <input type="date"
                                                class="form-control"
                                                id="expires_at"
                                                name="expires_at"
                                                value="<?php echo htmlspecialchars($_POST['expires_at'] ?? ''); ?>"
                                                min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                            <small class="form-text text-muted">
                                                Leave empty for permanent token (not recommended for production)
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-info-circle"></i>
                                                Token Capabilities
                                            </label>
                                            <div class="bg-light p-3 rounded">
                                                <small class="text-muted">
                                                    This token will have access to:
                                                </small>
                                                <ul class="mb-0 mt-2">
                                                    <li>Send messages via API</li>
                                                    <li>Manage WhatsApp sessions</li>
                                                    <li>Access device status</li>
                                                    <li>View message logs</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Review & Generate -->
                            <div class="form-step">
                                <h5>
                                    <span class="step-number">3</span>
                                    Review & Generate Token
                                </h5>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="best-practices">
                                            <h6><i class="fas fa-lightbulb"></i> Security Best Practices</h6>
                                            <ul class="mb-0">
                                                <li><strong>Store Securely:</strong> Save the token in a secure location (environment variables, secure vault)</li>
                                                <li><strong>Use HTTPS:</strong> Always use encrypted connections when making API calls</li>
                                                <li><strong>Monitor Usage:</strong> Regularly check token usage statistics</li>
                                                <li><strong>Rotate Tokens:</strong> Consider regenerating tokens periodically</li>
                                                <li><strong>Principle of Least Privilege:</strong> Use separate tokens for different purposes</li>
                                            </ul>
                                        </div>

                                        <div class="alert alert-info mt-3">
                                            <h6><i class="fas fa-info-circle"></i> What happens after generation?</h6>
                                            <p class="mb-0">
                                                Once generated, the token will be displayed <strong>only once</strong>.
                                                Make sure to copy and store it securely before leaving this page.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6><i class="fas fa-eye"></i> Token Preview</h6>
                                            </div>
                                            <div class="card-body">
                                                <div id="token-preview">
                                                    <p><strong>Name:</strong> <span id="preview-name">-</span></p>
                                                    <p><strong>Device:</strong> <span id="preview-device">None selected</span></p>
                                                    <p><strong>Expires:</strong> <span id="preview-expiry">Never</span></p>
                                                    <p><strong>Status:</strong> <span class="badge badge-success">Will be Active</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mt-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="security-agreement" required>
                                        <label class="custom-control-label" for="security-agreement">
                                            I understand the security implications and will store this token securely
                                        </label>
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg" id="generate-btn" disabled>
                                        <i class="fas fa-key"></i> Generate API Token
                                    </button>
                                    <a href="index.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </form>

                        <!-- Features Overview -->
                        <div class="row mt-5">
                            <div class="col-12">
                                <h4><i class="fas fa-star"></i> API Token Features</h4>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="feature-card">
                                            <div class="text-center">
                                                <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                                                <h6>Secure Authentication</h6>
                                                <p class="text-muted">
                                                    Industry-standard token-based authentication with SHA-256 encryption
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="feature-card">
                                            <div class="text-center">
                                                <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                                                <h6>Usage Analytics</h6>
                                                <p class="text-muted">
                                                    Track API calls, success rates, and monitor token usage patterns
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="feature-card">
                                            <div class="text-center">
                                                <i class="fas fa-cog fa-3x text-warning mb-3"></i>
                                                <h6>Flexible Management</h6>
                                                <p class="text-muted">
                                                    Easy activation, deactivation, regeneration, and expiry management
                                                </p>
                                            </div>
                                        </div>
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

            // Device data for preview
            const devices = <?php echo json_encode($userDevices); ?>;

            // Update preview when form fields change
            $('#token_name').on('input', function() {
                const name = $(this).val() || '-';
                $('#preview-name').text(name);
            });

            $('#device_id').on('change', function() {
                const deviceId = $(this).val();
                if (deviceId) {
                    const device = devices.find(d => d.id == deviceId);
                    if (device) {
                        $('#preview-device').text(device.device_name);

                        // Show device preview
                        $('#preview-device-name').text(device.device_name);
                        $('#preview-device-phone').text('+' + device.phone_number);

                        const statusClass = device.status === 'connected' ? 'badge-success' :
                            device.status === 'connecting' ? 'badge-warning' : 'badge-secondary';
                        $('#preview-device-status').attr('class', `badge ${statusClass}`)
                            .text(device.status.charAt(0).toUpperCase() + device.status.slice(1));

                        $('#device-preview').show();
                    }
                } else {
                    $('#preview-device').text('None selected');
                    $('#device-preview').hide();
                }
            });

            $('#expires_at').on('change', function() {
                const expiry = $(this).val();
                if (expiry) {
                    const date = new Date(expiry);
                    $('#preview-expiry').text(date.toLocaleDateString());
                } else {
                    $('#preview-expiry').text('Never');
                }
            });

            // Security agreement checkbox
            $('#security-agreement').on('change', function() {
                $('#generate-btn').prop('disabled', !$(this).is(':checked'));
            });

            // Form validation
            $('#generate-form').on('submit', function(e) {
                const tokenName = $('#token_name').val().trim();

                if (tokenName.length < 3) {
                    e.preventDefault();
                    alert('Token name must be at least 3 characters long.');
                    $('#token_name').focus();
                    return false;
                }

                if (!$('#security-agreement').is(':checked')) {
                    e.preventDefault();
                    alert('Please acknowledge the security agreement.');
                    $('#security-agreement').focus();
                    return false;
                }

                // Show loading state
                $('#generate-btn').prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin"></i> Generating...');
            });

            // Character counter for description
            $('#description').on('input', function() {
                const current = $(this).val().length;
                const max = 500;
                const remaining = max - current;

                let counterClass = 'text-muted';
                if (remaining < 50) counterClass = 'text-warning';
                if (remaining < 10) counterClass = 'text-danger';

                $(this).siblings('.form-text')
                    .html(`Help identify the purpose of this token (${remaining} characters remaining)`)
                    .attr('class', `form-text ${counterClass}`);
            });

            // Initialize preview with current values
            $('#token_name').trigger('input');
            $('#device_id').trigger('change');
            $('#expires_at').trigger('change');
        });

        // Copy generated token function
        function copyGeneratedToken() {
            const token = document.getElementById('generated-token').textContent;
            const tempInput = document.createElement('input');
            tempInput.value = token;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);

            // Show success message
            $('#copy-success').slideDown().delay(3000).slideUp();
        }

        // Auto-focus first form field
        <?php if (!$success): ?>
            $(document).ready(function() {
                $('#token_name').focus();
            });
        <?php endif; ?>
    </script>

</body>

</html>