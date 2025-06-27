<?php
session_start();
require_once '../../config/database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Settings.php';
require_once '../../includes/functions.php';

// Check authentication and admin role
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    redirect('/pages/auth/login.php');
}

$db = Database::getInstance()->getConnection();
$settings = new Settings($db);
$errors = [];
$successMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Application Settings
        $settings->set('app_name', sanitizeInput($_POST['app_name']));
        $settings->set('app_description', sanitizeInput($_POST['app_description']));
        $settings->set('app_timezone', sanitizeInput($_POST['app_timezone']));
        $settings->set('app_language', sanitizeInput($_POST['app_language']));

        // User Settings
        $maxDevices = filter_input(INPUT_POST, 'max_devices_per_user', FILTER_VALIDATE_INT);
        if ($maxDevices === false || $maxDevices < 1) {
            $errors[] = 'Max devices per user must be a positive number';
        } else {
            $settings->set('max_devices_per_user', $maxDevices);
        }

        $sessionTimeout = filter_input(INPUT_POST, 'session_timeout', FILTER_VALIDATE_INT);
        if ($sessionTimeout === false || $sessionTimeout < 5) {
            $errors[] = 'Session timeout must be at least 5 minutes';
        } else {
            $settings->set('session_timeout', $sessionTimeout);
        }

        // Security Settings
        $settings->set('enable_2fa', isset($_POST['enable_2fa']) ? 'true' : 'false');
        $settings->set('force_ssl', isset($_POST['force_ssl']) ? 'true' : 'false');
        $settings->set('password_min_length', intval($_POST['password_min_length']));
        $settings->set('password_require_uppercase', isset($_POST['password_require_uppercase']) ? 'true' : 'false');
        $settings->set('password_require_number', isset($_POST['password_require_number']) ? 'true' : 'false');
        $settings->set('password_require_special', isset($_POST['password_require_special']) ? 'true' : 'false');

        // Data Retention Settings
        $messageRetention = filter_input(INPUT_POST, 'message_retention_days', FILTER_VALIDATE_INT);
        if ($messageRetention === false || $messageRetention < 1) {
            $errors[] = 'Message retention days must be a positive number';
        } else {
            $settings->set('message_retention_days', $messageRetention);
        }

        $logRetention = filter_input(INPUT_POST, 'log_retention_days', FILTER_VALIDATE_INT);
        if ($logRetention === false || $logRetention < 1) {
            $errors[] = 'Log retention days must be a positive number';
        } else {
            $settings->set('log_retention_days', $logRetention);
        }

        // Maintenance Settings
        $settings->set('maintenance_mode', isset($_POST['maintenance_mode']) ? 'true' : 'false');
        $settings->set('maintenance_message', sanitizeInput($_POST['maintenance_message']));

        // Email Settings
        $settings->set('smtp_host', sanitizeInput($_POST['smtp_host']));
        $settings->set('smtp_port', intval($_POST['smtp_port']));
        $settings->set('smtp_username', sanitizeInput($_POST['smtp_username']));
        if (!empty($_POST['smtp_password'])) {
            $settings->set('smtp_password', encryptData($_POST['smtp_password']));
        }
        $settings->set('smtp_encryption', sanitizeInput($_POST['smtp_encryption']));
        $settings->set('smtp_from_email', filter_var($_POST['smtp_from_email'], FILTER_VALIDATE_EMAIL) ?: '');
        $settings->set('smtp_from_name', sanitizeInput($_POST['smtp_from_name']));

        if (empty($errors)) {
            $successMessage = 'System settings updated successfully';

            // Log the settings update
            logActivity('system_settings_updated', 'System settings were updated');
        }
    } catch (Exception $e) {
        $errors[] = 'Failed to save settings: ' . $e->getMessage();
    }
}

// Get all settings
$allSettings = $settings->getAll();

// Get available timezones
$timezones = timezone_identifiers_list();

$pageTitle = 'System Settings';
$currentPage = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../../includes/header.php'; ?>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <?php include '../../includes/navbar.php'; ?>
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">System Settings</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="/pages/dashboard/">Home</a></li>
                                <li class="breadcrumb-item">Settings</li>
                                <li class="breadcrumb-item active">System</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-check"></i> Success!</h5>
                            <?php echo $successMessage; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row">
                            <!-- Application Settings -->
                            <div class="col-md-6">
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-cog"></i> Application Settings</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="app_name">Application Name</label>
                                            <input type="text" class="form-control" id="app_name" name="app_name"
                                                value="<?php echo htmlspecialchars($allSettings['app_name'] ?? 'WhatsApp Monitor'); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="app_description">Application Description</label>
                                            <textarea class="form-control" id="app_description" name="app_description" rows="3"><?php echo htmlspecialchars($allSettings['app_description'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label for="app_timezone">Timezone</label>
                                            <select class="form-control select2" id="app_timezone" name="app_timezone">
                                                <?php
                                                $currentTimezone = $allSettings['app_timezone'] ?? 'Asia/Jakarta';
                                                foreach ($timezones as $tz):
                                                ?>
                                                    <option value="<?php echo $tz; ?>" <?php echo ($tz == $currentTimezone) ? 'selected' : ''; ?>>
                                                        <?php echo $tz; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label for="app_language">Default Language</label>
                                            <select class="form-control" id="app_language" name="app_language">
                                                <option value="en" <?php echo ($allSettings['app_language'] ?? 'en') == 'en' ? 'selected' : ''; ?>>English</option>
                                                <option value="id" <?php echo ($allSettings['app_language'] ?? 'en') == 'id' ? 'selected' : ''; ?>>Indonesian</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- User Settings -->
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-users"></i> User Settings</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="max_devices_per_user">Max Devices per User</label>
                                            <input type="number" class="form-control" id="max_devices_per_user" name="max_devices_per_user"
                                                value="<?php echo $allSettings['max_devices_per_user'] ?? 10; ?>" min="1" required>
                                            <small class="form-text text-muted">Maximum number of WhatsApp devices a user can add</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="session_timeout">Session Timeout (minutes)</label>
                                            <input type="number" class="form-control" id="session_timeout" name="session_timeout"
                                                value="<?php echo $allSettings['session_timeout'] ?? 60; ?>" min="5" required>
                                            <small class="form-text text-muted">User will be logged out after this period of inactivity</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Security & Maintenance Settings -->
                            <div class="col-md-6">
                                <!-- Security Settings -->
                                <div class="card card-warning">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-shield-alt"></i> Security Settings</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="enable_2fa" name="enable_2fa"
                                                    <?php echo ($allSettings['enable_2fa'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="enable_2fa">Enable Two-Factor Authentication</label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="force_ssl" name="force_ssl"
                                                    <?php echo ($allSettings['force_ssl'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="force_ssl">Force SSL Connection</label>
                                            </div>
                                        </div>

                                        <hr>
                                        <h5>Password Requirements</h5>

                                        <div class="form-group">
                                            <label for="password_min_length">Minimum Password Length</label>
                                            <input type="number" class="form-control" id="password_min_length" name="password_min_length"
                                                value="<?php echo $allSettings['password_min_length'] ?? 8; ?>" min="6" max="32" required>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="password_require_uppercase" name="password_require_uppercase"
                                                    <?php echo ($allSettings['password_require_uppercase'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="password_require_uppercase">Require Uppercase Letter</label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="password_require_number" name="password_require_number"
                                                    <?php echo ($allSettings['password_require_number'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="password_require_number">Require Number</label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="password_require_special" name="password_require_special"
                                                    <?php echo ($allSettings['password_require_special'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="password_require_special">Require Special Character</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Data Retention Settings -->
                                <div class="card card-danger">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-database"></i> Data Retention</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="message_retention_days">Message Retention (days)</label>
                                            <input type="number" class="form-control" id="message_retention_days" name="message_retention_days"
                                                value="<?php echo $allSettings['message_retention_days'] ?? 30; ?>" min="1" required>
                                            <small class="form-text text-muted">Messages older than this will be automatically deleted</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="log_retention_days">Log Retention (days)</label>
                                            <input type="number" class="form-control" id="log_retention_days" name="log_retention_days"
                                                value="<?php echo $allSettings['log_retention_days'] ?? 90; ?>" min="1" required>
                                            <small class="form-text text-muted">System logs older than this will be automatically deleted</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Settings -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card card-secondary">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-envelope"></i> Email Settings (SMTP)</h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-tool" onclick="testEmailSettings()">
                                                <i class="fas fa-paper-plane"></i> Test Email
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="smtp_host">SMTP Host</label>
                                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                                        value="<?php echo htmlspecialchars($allSettings['smtp_host'] ?? ''); ?>"
                                                        placeholder="smtp.gmail.com">
                                                </div>

                                                <div class="form-group">
                                                    <label for="smtp_port">SMTP Port</label>
                                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                                        value="<?php echo $allSettings['smtp_port'] ?? 587; ?>"
                                                        placeholder="587">
                                                </div>

                                                <div class="form-group">
                                                    <label for="smtp_encryption">Encryption</label>
                                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                                        <option value="" <?php echo empty($allSettings['smtp_encryption']) ? 'selected' : ''; ?>>None</option>
                                                        <option value="tls" <?php echo ($allSettings['smtp_encryption'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                        <option value="ssl" <?php echo ($allSettings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="smtp_username">SMTP Username</label>
                                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                                        value="<?php echo htmlspecialchars($allSettings['smtp_username'] ?? ''); ?>"
                                                        placeholder="your-email@gmail.com">
                                                </div>

                                                <div class="form-group">
                                                    <label for="smtp_password">SMTP Password</label>
                                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                                        placeholder="Leave blank to keep current password">
                                                    <small class="form-text text-muted">Password is encrypted before storing</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="smtp_from_email">From Email</label>
                                                    <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                                        value="<?php echo htmlspecialchars($allSettings['smtp_from_email'] ?? ''); ?>"
                                                        placeholder="noreply@yourdomain.com">
                                                </div>

                                                <div class="form-group">
                                                    <label for="smtp_from_name">From Name</label>
                                                    <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name"
                                                        value="<?php echo htmlspecialchars($allSettings['smtp_from_name'] ?? 'WhatsApp Monitor'); ?>"
                                                        placeholder="WhatsApp Monitor">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance Mode -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card card-dark">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-tools"></i> Maintenance Mode</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="maintenance_mode" name="maintenance_mode"
                                                    <?php echo ($allSettings['maintenance_mode'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="maintenance_mode">Enable Maintenance Mode</label>
                                            </div>
                                            <small class="form-text text-muted">When enabled, only administrators can access the system</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="maintenance_message">Maintenance Message</label>
                                            <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3"><?php echo htmlspecialchars($allSettings['maintenance_message'] ?? 'System is under maintenance. Please check back later.'); ?></textarea>
                                            <small class="form-text text-muted">This message will be shown to users during maintenance</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                                <button type="button" class="btn btn-default" onclick="window.location.reload()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Test Email Modal -->
    <div class="modal fade" id="testEmailModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Email Settings</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="testEmailForm">
                        <div class="form-group">
                            <label for="test_email">Send Test Email To:</label>
                            <input type="email" class="form-control" id="test_email" required>
                        </div>
                    </form>
                    <div id="testEmailResult"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="sendTestEmail()">Send Test</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap4'
            });

            // Maintenance mode warning
            $('#maintenance_mode').change(function() {
                if ($(this).is(':checked')) {
                    Swal.fire({
                        title: 'Enable Maintenance Mode?',
                        text: 'This will prevent non-admin users from accessing the system!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, enable it!'
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            $(this).prop('checked', false);
                        }
                    });
                }
            });
        });

        function testEmailSettings() {
            $('#testEmailModal').modal('show');
            $('#testEmailResult').html('');
        }

        function sendTestEmail() {
            const email = $('#test_email').val();
            if (!email) return;

            $('#testEmailResult').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Sending test email...</div>');

            // In a real implementation, this would make an AJAX call to test the email settings
            $.post('/api/test-email.php', {
                email: email
            }).done(function(response) {
                $('#testEmailResult').html('<div class="alert alert-success">Test email sent successfully!</div>');
            }).fail(function() {
                $('#testEmailResult').html('<div class="alert alert-danger">Failed to send test email. Please check your settings.</div>');
            });
        }
    </script>
</body>

</html>