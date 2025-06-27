<?php

/**
 * ===============================================================================
 * ADD DEVICE - Add New WhatsApp Device Form
 * ===============================================================================
 * Form untuk menambah device WhatsApp baru
 * - Validasi input form
 * - Generate device ID otomatis
 * - Phone number formatting
 * - Integration dengan Node.js backend
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize and validate input
        $device_name = trim($_POST['device_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $device_id = trim($_POST['device_id'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Validation
        $errors = [];

        if (empty($device_name)) {
            $errors['device_name'] = 'Device name is required';
        } elseif (strlen($device_name) < 3) {
            $errors['device_name'] = 'Device name must be at least 3 characters';
        } elseif (strlen($device_name) > 100) {
            $errors['device_name'] = 'Device name must not exceed 100 characters';
        }

        if (empty($phone_number)) {
            $errors['phone_number'] = 'Phone number is required';
        } elseif (!preg_match('/^62[8-9]\d{8,12}$/', preg_replace('/\D/', '', $phone_number))) {
            $errors['phone_number'] = 'Please enter a valid Indonesian phone number (format: 628xxxxxxxxx)';
        }

        if (empty($device_id)) {
            $errors['device_id'] = 'Device ID is required';
        } elseif (strlen($device_id) < 5) {
            $errors['device_id'] = 'Device ID must be at least 5 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $device_id)) {
            $errors['device_id'] = 'Device ID can only contain letters, numbers, underscore, and dash';
        }

        // Check for duplicates
        if (empty($errors['phone_number']) && $device->phoneNumberExists($phone_number, $currentUser['id'])) {
            $errors['phone_number'] = 'This phone number is already registered';
        }

        if (empty($errors['device_id']) && $device->deviceIdExists($device_id)) {
            $errors['device_id'] = 'This device ID is already taken';
        }

        // Check device limit
        $deviceLimit = getSetting('max_devices_per_user', 10);
        $currentDeviceCount = $device->countUserDevices($currentUser['id']);

        if ($currentDeviceCount >= $deviceLimit) {
            $errors['general'] = "You have reached the maximum limit of {$deviceLimit} devices";
        }

        if (empty($errors)) {
            // Clean phone number (remove all non-digits)
            $clean_phone = preg_replace('/\D/', '', $phone_number);

            // Prepare device data
            $deviceData = [
                'user_id' => $currentUser['id'],
                'device_name' => $device_name,
                'phone_number' => $clean_phone,
                'device_id' => $device_id,
                'description' => $description,
                'status' => 'disconnected'
            ];

            // Add device to database
            $newDeviceId = $device->create($deviceData);

            if ($newDeviceId) {
                // Log activity
                logActivity($currentUser['id'], 'device_created', "Created new device: {$device_name}", $newDeviceId);

                // Redirect to device list with success message
                $_SESSION['success_message'] = "Device '{$device_name}' has been added successfully!";
                header('Location: index.php');
                exit;
            } else {
                $errors['general'] = 'Failed to add device. Please try again.';
            }
        }
    } catch (Exception $e) {
        error_log("Add device error: " . $e->getMessage());
        $errors['general'] = 'An error occurred while adding the device. Please try again.';
    }
}

// Generate default device ID
$default_device_id = 'device_' . strtolower($currentUser['username']) . '_' . time();

// Page settings
$pageTitle = 'Add New Device';
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
    <!-- iCheck -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../../assets/adminlte/dist/css/adminlte.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/custom/css/custom.css">

    <style>
        .form-wizard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .phone-preview {
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
            font-weight: bold;
            color: #28a745;
        }

        .device-id-generator {
            background: #f8f9fa;
            border: 1px dashed #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            margin: 0 5px;
            background: rgba(255, 255, 255, 0.2);
        }

        .step.active {
            background: rgba(255, 255, 255, 0.3);
            font-weight: bold;
        }

        .form-group.required label:after {
            content: " *";
            color: #e74c3c;
        }

        .phone-input {
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .char-counter {
            font-size: 0.8em;
            color: #6c757d;
            text-align: right;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .form-wizard {
                padding: 20px;
            }

            .step-indicator {
                flex-direction: column;
            }

            .step {
                margin: 2px 0;
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
                                <li class="breadcrumb-item"><a href="index.php">Devices</a></li>
                                <li class="breadcrumb-item active">Add Device</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <!-- Error Alert -->
                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <?php echo htmlspecialchars($errors['general']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Form Wizard Header -->
                    <div class="form-wizard">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-2">
                                    <i class="fas fa-mobile-alt me-2"></i>
                                    Add New WhatsApp Device
                                </h3>
                                <p class="mb-0">
                                    Connect a new WhatsApp number to your monitoring system.
                                    Each device will have its own session and API token.
                                </p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="device-count-info">
                                    <h4 class="mb-1"><?php echo $currentDeviceCount; ?>/<?php echo $deviceLimit; ?></h4>
                                    <small>Devices Used</small>
                                </div>
                            </div>
                        </div>

                        <!-- Step Indicator -->
                        <div class="step-indicator mt-4">
                            <div class="step active">
                                <i class="fas fa-info-circle"></i><br>
                                <small>Device Info</small>
                            </div>
                            <div class="step">
                                <i class="fas fa-phone"></i><br>
                                <small>Phone Number</small>
                            </div>
                            <div class="step">
                                <i class="fas fa-cog"></i><br>
                                <small>Configuration</small>
                            </div>
                            <div class="step">
                                <i class="fas fa-check"></i><br>
                                <small>Complete</small>
                            </div>
                        </div>
                    </div>

                    <!-- Add Device Form -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-mobile-alt"></i>
                                        Device Information
                                    </h3>
                                </div>

                                <form method="POST" id="add-device-form">
                                    <div class="card-body">
                                        <!-- Device Name -->
                                        <div class="form-group required">
                                            <label for="device_name">Device Name</label>
                                            <input type="text"
                                                class="form-control <?php echo isset($errors['device_name']) ? 'is-invalid' : ''; ?>"
                                                id="device_name"
                                                name="device_name"
                                                value="<?php echo htmlspecialchars($_POST['device_name'] ?? ''); ?>"
                                                placeholder="e.g., Customer Service, Marketing Team, Personal"
                                                maxlength="100"
                                                required>
                                            <div class="char-counter">
                                                <span id="device-name-count">0</span>/100 characters
                                            </div>
                                            <?php if (isset($errors['device_name'])): ?>
                                                <div class="invalid-feedback">
                                                    <?php echo htmlspecialchars($errors['device_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">
                                                Choose a descriptive name to identify this device
                                            </small>
                                        </div>

                                        <!-- Phone Number -->
                                        <div class="form-group required">
                                            <label for="phone_number">WhatsApp Phone Number</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <img src="https://flagcdn.com/16x12/id.png" alt="ID" class="me-2">
                                                        +62
                                                    </span>
                                                </div>
                                                <input type="text"
                                                    class="form-control phone-input <?php echo isset($errors['phone_number']) ? 'is-invalid' : ''; ?>"
                                                    id="phone_number"
                                                    name="phone_number"
                                                    value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                                                    placeholder="8123456789"
                                                    maxlength="15"
                                                    required>
                                            </div>
                                            <div class="phone-preview mt-2">
                                                Format: <span id="phone-preview">+62 8xxx xxxx xxxx</span>
                                            </div>
                                            <?php if (isset($errors['phone_number'])): ?>
                                                <div class="invalid-feedback">
                                                    <?php echo htmlspecialchars($errors['phone_number']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">
                                                Enter the WhatsApp number without country code (+62)
                                            </small>
                                        </div>

                                        <!-- Device ID -->
                                        <div class="form-group required">
                                            <label for="device_id">Device ID (Unique Identifier)</label>
                                            <input type="text"
                                                class="form-control <?php echo isset($errors['device_id']) ? 'is-invalid' : ''; ?>"
                                                id="device_id"
                                                name="device_id"
                                                value="<?php echo htmlspecialchars($_POST['device_id'] ?? $default_device_id); ?>"
                                                placeholder="device_unique_id"
                                                pattern="[a-zA-Z0-9_-]+"
                                                maxlength="50"
                                                required>
                                            <div class="char-counter">
                                                <span id="device-id-count">0</span>/50 characters
                                            </div>
                                            <?php if (isset($errors['device_id'])): ?>
                                                <div class="invalid-feedback">
                                                    <?php echo htmlspecialchars($errors['device_id']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Device ID Generator -->
                                            <div class="device-id-generator">
                                                <strong>Quick Generate:</strong>
                                                <div class="btn-group btn-group-sm mt-2" role="group">
                                                    <button type="button" class="btn btn-outline-primary" onclick="generateDeviceId('timestamp')">
                                                        Timestamp
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary" onclick="generateDeviceId('random')">
                                                        Random
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary" onclick="generateDeviceId('phone')">
                                                        Phone Based
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">
                                                Only letters, numbers, underscore (_) and dash (-) allowed
                                            </small>
                                        </div>

                                        <!-- Description -->
                                        <div class="form-group">
                                            <label for="description">Description (Optional)</label>
                                            <textarea class="form-control"
                                                id="description"
                                                name="description"
                                                rows="3"
                                                placeholder="Additional notes about this device..."
                                                maxlength="500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                            <div class="char-counter">
                                                <span id="description-count">0</span>/500 characters
                                            </div>
                                            <small class="form-text text-muted">
                                                Optional description or notes about this device
                                            </small>
                                        </div>
                                    </div>

                                    <div class="card-footer">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <a href="index.php" class="btn btn-secondary">
                                                    <i class="fas fa-arrow-left"></i> Back to List
                                                </a>
                                            </div>
                                            <div class="col-md-6 text-right">
                                                <button type="submit" class="btn btn-primary" id="submit-btn">
                                                    <i class="fas fa-plus"></i> Add Device
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Help & Info Sidebar -->
                        <div class="col-md-4">
                            <!-- Quick Info -->
                            <div class="card card-info">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-info-circle"></i>
                                        Quick Info
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="info-item mb-3">
                                        <h6><i class="fas fa-mobile-alt text-primary"></i> Device Limit</h6>
                                        <p class="mb-0">You can add up to <strong><?php echo $deviceLimit; ?></strong> devices.</p>
                                        <small class="text-muted">Currently using: <?php echo $currentDeviceCount; ?></small>
                                    </div>

                                    <div class="info-item mb-3">
                                        <h6><i class="fas fa-key text-warning"></i> API Token</h6>
                                        <p class="mb-0">Each device gets a unique API token automatically.</p>
                                    </div>

                                    <div class="info-item mb-3">
                                        <h6><i class="fas fa-qrcode text-info"></i> QR Code</h6>
                                        <p class="mb-0">After adding, you'll need to scan QR code to connect.</p>
                                    </div>

                                    <div class="info-item">
                                        <h6><i class="fas fa-shield-alt text-success"></i> Security</h6>
                                        <p class="mb-0">All communications are encrypted and secure.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Next Steps -->
                            <div class="card card-success">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-list-ol"></i>
                                        Next Steps
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <ol class="list-unstyled">
                                        <li class="mb-2">
                                            <span class="badge badge-primary">1</span>
                                            <strong>Add Device</strong>
                                            <br><small class="text-muted">Fill the form and submit</small>
                                        </li>
                                        <li class="mb-2">
                                            <span class="badge badge-info">2</span>
                                            <strong>Connect Device</strong>
                                            <br><small class="text-muted">Click connect button</small>
                                        </li>
                                        <li class="mb-2">
                                            <span class="badge badge-warning">3</span>
                                            <strong>Scan QR Code</strong>
                                            <br><small class="text-muted">Use WhatsApp mobile app</small>
                                        </li>
                                        <li class="mb-0">
                                            <span class="badge badge-success">4</span>
                                            <strong>Start Messaging</strong>
                                            <br><small class="text-muted">Device ready to use!</small>
                                        </li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Phone Number Guidelines -->
                            <div class="card card-warning">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Important Notes
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success"></i>
                                            Use active WhatsApp number
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success"></i>
                                            Indonesian numbers only (+62)
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-times-circle text-danger"></i>
                                            Don't use banned numbers
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-times-circle text-danger"></i>
                                            One number per device only
                                        </li>
                                    </ul>
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

            // Character counters
            function updateCharCounter(inputId, counterId, maxLength) {
                const input = document.getElementById(inputId);
                const counter = document.getElementById(counterId);

                if (input && counter) {
                    const updateCount = () => {
                        const currentLength = input.value.length;
                        counter.textContent = currentLength;

                        // Color coding
                        const percentage = (currentLength / maxLength) * 100;
                        if (percentage >= 90) {
                            counter.style.color = '#dc3545';
                        } else if (percentage >= 70) {
                            counter.style.color = '#ffc107';
                        } else {
                            counter.style.color = '#6c757d';
                        }
                    };

                    input.addEventListener('input', updateCount);
                    updateCount(); // Initial count
                }
            }

            // Initialize character counters
            updateCharCounter('device_name', 'device-name-count', 100);
            updateCharCounter('device_id', 'device-id-count', 50);
            updateCharCounter('description', 'description-count', 500);

            // Phone number formatting and validation
            $('#phone_number').on('input', function() {
                let value = this.value.replace(/\D/g, ''); // Remove non-digits

                // Limit length
                if (value.length > 13) {
                    value = value.substring(0, 13);
                }

                this.value = value;

                // Update preview
                updatePhonePreview(value);

                // Validate
                validatePhoneNumber(value);
            });

            function updatePhonePreview(phone) {
                const preview = document.getElementById('phone-preview');
                if (phone.length === 0) {
                    preview.textContent = '+62 8xxx xxxx xxxx';
                    preview.style.color = '#6c757d';
                } else if (phone.length >= 10) {
                    // Format: +62 8123 4567 890
                    const formatted = `+62 ${phone.substring(0, 4)} ${phone.substring(4, 8)} ${phone.substring(8)}`;
                    preview.textContent = formatted;
                    preview.style.color = '#28a745';
                } else {
                    preview.textContent = `+62 ${phone}`;
                    preview.style.color = '#ffc107';
                }
            }

            function validatePhoneNumber(phone) {
                const input = document.getElementById('phone_number');
                const isValid = /^8[0-9]{8,12}$/.test(phone);

                if (phone.length > 0 && !isValid) {
                    input.classList.add('is-invalid');
                    if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('invalid-feedback')) {
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'Phone number must start with 8 and be 9-13 digits total';
                        input.parentNode.insertBefore(feedback, input.nextElementSibling);
                    }
                } else {
                    input.classList.remove('is-invalid');
                    const feedback = input.parentNode.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.remove();
                    }
                }
            }

            // Device ID validation
            $('#device_id').on('input', function() {
                const value = this.value;
                const isValid = /^[a-zA-Z0-9_-]+$/.test(value);

                if (value.length > 0 && !isValid) {
                    this.classList.add('is-invalid');
                    // Show error message if not exists
                    if (!this.nextElementSibling || !this.nextElementSibling.classList.contains('invalid-feedback')) {
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'Only letters, numbers, underscore (_) and dash (-) allowed';
                        this.parentNode.insertBefore(feedback, this.nextElementSibling);
                    }
                } else {
                    this.classList.remove('is-invalid');
                    const feedback = this.parentNode.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.remove();
                    }
                }
            });

            // Form submission
            $('#add-device-form').on('submit', function(e) {
                // Show loading state
                const submitBtn = document.getElementById('submit-btn');
                const originalText = submitBtn.innerHTML;

                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Device...';
                submitBtn.disabled = true;

                // Validate form before submission
                if (!validateForm()) {
                    e.preventDefault();
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    return false;
                }
            });

            function validateForm() {
                let isValid = true;

                // Device name validation
                const deviceName = document.getElementById('device_name').value.trim();
                if (deviceName.length < 3) {
                    showFieldError('device_name', 'Device name must be at least 3 characters');
                    isValid = false;
                }

                // Phone number validation
                const phoneNumber = document.getElementById('phone_number').value.replace(/\D/g, '');
                if (!/^8[0-9]{8,12}$/.test(phoneNumber)) {
                    showFieldError('phone_number', 'Please enter a valid phone number');
                    isValid = false;
                }

                // Device ID validation
                const deviceId = document.getElementById('device_id').value.trim();
                if (deviceId.length < 5 || !/^[a-zA-Z0-9_-]+$/.test(deviceId)) {
                    showFieldError('device_id', 'Device ID must be at least 5 characters and contain only letters, numbers, _ and -');
                    isValid = false;
                }

                return isValid;
            }

            function showFieldError(fieldId, message) {
                const field = document.getElementById(fieldId);
                field.classList.add('is-invalid');

                // Remove existing error message
                const existingError = field.parentNode.querySelector('.invalid-feedback');
                if (existingError) {
                    existingError.remove();
                }

                // Add new error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = message;
                field.parentNode.appendChild(errorDiv);
            }
        });

        // Device ID generators
        function generateDeviceId(type) {
            const deviceIdInput = document.getElementById('device_id');
            const phoneInput = document.getElementById('phone_number');
            const deviceNameInput = document.getElementById('device_name');

            let newId = '';

            switch (type) {
                case 'timestamp':
                    newId = 'device_' + Date.now();
                    break;

                case 'random':
                    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
                    newId = 'device_';
                    for (let i = 0; i < 8; i++) {
                        newId += chars.charAt(Math.floor(Math.random() * chars.length));
                    }
                    break;

                case 'phone':
                    const phone = phoneInput.value.replace(/\D/g, '');
                    if (phone.length >= 4) {
                        newId = 'device_' + phone.substring(1, 5) + '_' + Math.floor(Math.random() * 1000);
                    } else {
                        newId = 'device_phone_' + Date.now();
                    }
                    break;

                default:
                    newId = 'device_' + Date.now();
            }

            deviceIdInput.value = newId;
            deviceIdInput.focus();

            // Trigger input event to update character counter
            deviceIdInput.dispatchEvent(new Event('input'));
        }

        // Auto-suggest device name based on phone number
        $('#phone_number').on('blur', function() {
            const phone = this.value.replace(/\D/g, '');
            const deviceNameInput = document.getElementById('device_name');

            if (phone.length >= 10 && deviceNameInput.value.trim() === '') {
                // Suggest device name based on phone pattern
                const suggestions = [
                    'WhatsApp ' + phone.substring(1, 5),
                    'Device ' + phone.substring(phone.length - 4),
                    'WA Number ' + phone.substring(1, 5)
                ];

                const suggestion = suggestions[0];
                deviceNameInput.value = suggestion;
                deviceNameInput.dispatchEvent(new Event('input'));

                // Highlight the suggested text
                deviceNameInput.focus();
                deviceNameInput.select();
            }
        });

        // Copy device ID to clipboard
        function copyDeviceId() {
            const deviceIdInput = document.getElementById('device_id');
            deviceIdInput.select();
            document.execCommand('copy');

            // Show feedback
            const feedback = document.createElement('small');
            feedback.className = 'text-success';
            feedback.textContent = ' ✓ Copied!';
            deviceIdInput.parentNode.appendChild(feedback);

            setTimeout(() => {
                feedback.remove();
            }, 2000);
        }

        // Form auto-save to localStorage (draft)
        function saveFormDraft() {
            const formData = {
                device_name: document.getElementById('device_name').value,
                phone_number: document.getElementById('phone_number').value,
                device_id: document.getElementById('device_id').value,
                description: document.getElementById('description').value
            };

            localStorage.setItem('device_form_draft', JSON.stringify(formData));
        }

        function loadFormDraft() {
            const draft = localStorage.getItem('device_form_draft');
            if (draft) {
                try {
                    const formData = JSON.parse(draft);

                    // Only load if form is empty
                    const isEmpty = document.getElementById('device_name').value === '' &&
                        document.getElementById('phone_number').value === '' &&
                        document.getElementById('description').value === '';

                    if (isEmpty) {
                        if (confirm('Found a saved draft. Do you want to restore it?')) {
                            document.getElementById('device_name').value = formData.device_name || '';
                            document.getElementById('phone_number').value = formData.phone_number || '';
                            document.getElementById('device_id').value = formData.device_id || '';
                            document.getElementById('description').value = formData.description || '';

                            // Trigger events to update counters and previews
                            document.getElementById('device_name').dispatchEvent(new Event('input'));
                            document.getElementById('phone_number').dispatchEvent(new Event('input'));
                            document.getElementById('device_id').dispatchEvent(new Event('input'));
                            document.getElementById('description').dispatchEvent(new Event('input'));
                        }
                    }
                } catch (e) {
                    console.error('Failed to load form draft:', e);
                }
            }
        }

        function clearFormDraft() {
            localStorage.removeItem('device_form_draft');
        }

        // Auto-save form every 30 seconds
        setInterval(saveFormDraft, 30000);

        // Load draft on page load
        $(document).ready(function() {
            loadFormDraft();
        });

        // Clear draft on successful submission
        $('#add-device-form').on('submit', function() {
            setTimeout(clearFormDraft, 1000);
        });

        // Step indicator animation
        function updateStepIndicator(currentStep) {
            const steps = document.querySelectorAll('.step');
            steps.forEach((step, index) => {
                if (index <= currentStep) {
                    step.classList.add('active');
                } else {
                    step.classList.remove('active');
                }
            });
        }

        // Progressive form validation with step updates
        $('#device_name').on('blur', function() {
            if (this.value.trim().length >= 3) {
                updateStepIndicator(0);
            }
        });

        $('#phone_number').on('blur', function() {
            const phone = this.value.replace(/\D/g, '');
            if (/^8[0-9]{8,12}$/.test(phone)) {
                updateStepIndicator(1);
            }
        });

        $('#device_id').on('blur', function() {
            if (this.value.trim().length >= 5 && /^[a-zA-Z0-9_-]+$/.test(this.value)) {
                updateStepIndicator(2);
            }
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + S to save draft
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveFormDraft();

                // Show feedback
                const feedback = document.createElement('div');
                feedback.className = 'alert alert-info alert-dismissible';
                feedback.innerHTML = `
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-save"></i> Draft saved automatically
        `;
                document.querySelector('.content').prepend(feedback);

                setTimeout(() => {
                    feedback.remove();
                }, 3000);
            }

            // Escape to clear form
            if (e.key === 'Escape' && e.shiftKey) {
                if (confirm('Clear all form data?')) {
                    document.getElementById('add-device-form').reset();
                    clearFormDraft();
                    updateStepIndicator(-1);
                }
            }
        });

        // Prevent accidental page leave with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            const hasChanges = document.getElementById('device_name').value !== '' ||
                document.getElementById('phone_number').value !== '' ||
                document.getElementById('description').value !== '';

            if (hasChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        // Remove beforeunload listener on form submission
        $('#add-device-form').on('submit', function() {
            window.removeEventListener('beforeunload', arguments.callee);
        });

        // Focus management for better UX
        $('#device_name').on('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#phone_number').focus();
            }
        });

        $('#phone_number').on('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#device_id').focus();
            }
        });

        $('#device_id').on('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#description').focus();
            }
        });

        // Auto-focus first empty field
        $(document).ready(function() {
            const fields = ['device_name', 'phone_number', 'device_id', 'description'];

            for (const fieldId of fields) {
                const field = document.getElementById(fieldId);
                if (field && field.value.trim() === '') {
                    field.focus();
                    break;
                }
            }
        });
    </script>

</body>

</html>