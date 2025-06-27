<?php

/**
 * Login Page
 * 
 * User authentication form with AdminLTE theme
 * Handles login form display and processing
 */

// Define APP_ROOT if not already defined
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(dirname(__DIR__)));
}

// Load required files
require_once APP_ROOT . '/config/constants.php';
require_once APP_ROOT . '/classes/Database.php';
require_once APP_ROOT . '/classes/Auth.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $intendedUrl = $_SESSION['intended_url'] ?? '/pages/dashboard/';
    unset($_SESSION['intended_url']);
    header("Location: " . $intendedUrl);
    exit;
}

// Initialize variables
$error = '';
$success = '';
$loginData = [
    'username' => '',
    'remember_me' => false
];

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request. Please refresh the page and try again.';
        } else {
            // Get form data
            $username = sanitizeString($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $rememberMe = isset($_POST['remember_me']);

            // Remember form data (except password)
            $loginData['username'] = $username;
            $loginData['remember_me'] = $rememberMe;

            // Validate required fields
            if (empty($username) || empty($password)) {
                $error = 'Username dan password harus diisi.';
            } else {
                // Attempt login
                $auth = new Auth();
                $result = $auth->login($username, $password, $rememberMe);

                if ($result['success']) {
                    // Login successful
                    $intendedUrl = $_SESSION['intended_url'] ?? '/pages/dashboard/';
                    unset($_SESSION['intended_url']);

                    // Set success message in session for dashboard
                    $_SESSION['flash_message'] = 'Selamat datang, ' . $result['user']['full_name'] . '!';
                    $_SESSION['flash_type'] = 'success';

                    header("Location: " . $intendedUrl);
                    exit;
                } else {
                    // Login failed
                    $error = $result['message'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    }
}

// Check for remember me cookie auto-login
if (!isLoggedIn()) {
    checkRememberMe();

    // If auto-logged in, redirect
    if (isLoggedIn()) {
        header("Location: /pages/dashboard/");
        exit;
    }
}

// Page configuration
$pageTitle = 'Login';
$pageDescription = 'Login to WhatsApp Monitor Dashboard';
$bodyClass = 'hold-transition login-page';
$currentPage = 'login';

// Custom CSS for login page
$extraCSS = [];
$inlineCSS = '
.login-page {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-box {
    width: 400px;
}

.card {
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
}

.card-header {
    background: transparent;
    border-bottom: 1px solid #f0f0f0;
}

.login-logo {
    color: white;
    font-weight: 300;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 25px;
    padding: 12px 30px;
    font-weight: 500;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.form-control {
    border-radius: 25px;
    border: 1px solid #e0e0e0;
    padding: 12px 20px;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.input-group-text {
    border-radius: 25px 0 0 25px;
    border: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.remember-me {
    margin: 20px 0;
}

.forgot-password {
    text-align: center;
    margin-top: 20px;
}

.alert {
    border-radius: 10px;
    border: none;
}

.footer-text {
    color: rgba(255,255,255,0.8);
    text-align: center;
    margin-top: 30px;
}
';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle . ' - WhatsApp Monitor'); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo ASSETS_URL; ?>/custom/images/favicon-32x32.png">

    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/dist/css/adminlte.min.css">

    <!-- Custom CSS -->
    <style>
        <?php echo $inlineCSS; ?>
    </style>
</head>

<body class="<?php echo htmlspecialchars($bodyClass); ?>">
    <div class="login-box">
        <!-- Logo -->
        <div class="login-logo">
            <img src="<?php echo ASSETS_URL; ?>/custom/images/logo-white.png"
                alt="WhatsApp Monitor"
                style="height: 60px; margin-bottom: 10px;"
                onerror="this.style.display='none'">
            <br>
            <b>WhatsApp</b> Monitor
        </div>

        <!-- Login Card -->
        <div class="card">
            <div class="card-header text-center">
                <h4 class="mb-0">
                    <i class="fas fa-sign-in-alt text-primary"></i>
                    Login Dashboard
                </h4>
                <p class="text-muted mt-2">Masuk ke akun Anda untuk melanjutkan</p>
            </div>

            <div class="card-body">
                <!-- Error Alert -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <i class="icon fas fa-ban"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Success Alert -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <i class="icon fas fa-check"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="post" id="loginForm">
                    <?php echo csrfField(); ?>

                    <!-- Username Field -->
                    <div class="form-group">
                        <label for="username" class="sr-only">Username atau Email</label>
                        <div class="input-group">
                            <input type="text"
                                class="form-control"
                                id="username"
                                name="username"
                                placeholder="Username atau Email"
                                value="<?php echo htmlspecialchars($loginData['username']); ?>"
                                required
                                autofocus>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-user"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="password" class="sr-only">Password</label>
                        <div class="input-group">
                            <input type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="Password"
                                required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-lock"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="form-group remember-me">
                        <div class="icheck-primary">
                            <input type="checkbox"
                                id="remember_me"
                                name="remember_me"
                                <?php echo $loginData['remember_me'] ? 'checked' : ''; ?>>
                            <label for="remember_me">
                                Ingat saya selama 30 hari
                            </label>
                        </div>
                    </div>

                    <!-- Login Button -->
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
                            <i class="fas fa-sign-in-alt"></i>
                            Masuk Dashboard
                        </button>
                    </div>
                </form>

                <!-- Forgot Password Link -->
                <div class="forgot-password">
                    <a href="#" onclick="showForgotPasswordModal()" class="text-primary">
                        <i class="fas fa-key"></i> Lupa password?
                    </a>
                </div>

                <!-- Demo Login Info -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6><i class="fas fa-info-circle text-info"></i> Demo Login:</h6>
                    <small class="text-muted">
                        <strong>Username:</strong> admin<br>
                        <strong>Password:</strong> password
                    </small>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-text">
            <p>&copy; <?php echo date('Y'); ?> WhatsApp Monitor. All rights reserved.</p>
            <small>Powered by AdminLTE & Baileys</small>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">
                        <i class="fas fa-key"></i> Reset Password
                    </h4>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Untuk reset password, silakan hubungi administrator sistem:</p>
                    <div class="alert alert-info">
                        <i class="fas fa-envelope"></i>
                        <strong>Email:</strong> admin@whatsapp-monitor.com<br>
                        <i class="fas fa-phone"></i>
                        <strong>Phone:</strong> +62 812-3456-7890
                    </div>
                    <p class="text-muted">
                        <small>Administrator akan membantu Anda mereset password dalam waktu 1x24 jam.</small>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/adminlte/dist/js/adminlte.min.js"></script>

    <script>
        $(document).ready(function() {
            // Focus on username field
            $('#username').focus();

            // Form submission handling
            $('#loginForm').on('submit', function(e) {
                const username = $('#username').val().trim();
                const password = $('#password').val();

                if (!username || !password) {
                    e.preventDefault();
                    showAlert('Username dan password harus diisi', 'danger');
                    return false;
                }

                // Disable submit button to prevent double submission
                $('#loginBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
            });

            // Show password toggle
            $('.input-group-text').on('click', function() {
                const input = $(this).parent().prev('input');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    $(this).find('span').removeClass('fa-lock').addClass('fa-lock-open');
                } else {
                    input.attr('type', 'password');
                    $(this).find('span').removeClass('fa-lock-open').addClass('fa-lock');
                }
            });

            // Auto-dismiss alerts
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Demo login button
            $('#username').on('dblclick', function() {
                $(this).val('admin');
                $('#password').val('password');
                $('#remember_me').prop('checked', true);
            });
        });

        function showForgotPasswordModal() {
            $('#forgotPasswordModal').modal('show');
        }

        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="icon fas fa-${type === 'danger' ? 'ban' : 'check'}"></i>
                    ${message}
                </div>
            `;

            $('.card-body').prepend(alertHtml);

            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        }

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl + Enter to submit form
            if (e.ctrlKey && e.keyCode === 13) {
                $('#loginForm').submit();
            }
        });

        // Prevent back button after login
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>

</html>