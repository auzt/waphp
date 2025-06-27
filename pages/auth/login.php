<?php

/**
 * ===============================================================================
 * LOGIN PAGE - WhatsApp Monitor Authentication
 * ===============================================================================
 * Halaman login dengan AdminLTE theme
 * - Form validation
 * - CSRF protection
 * - Rate limiting protection
 * - Remember me functionality
 * ===============================================================================
 */

// Define APP_ROOT if not defined
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(dirname(__DIR__)));
}

// Include required files
require_once APP_ROOT . '/includes/bootstrap.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirectTo('pages/dashboard/index.php');
}

// Initialize variables
$error = '';
$success = '';
$username = '';
$showCaptcha = false;

// Get messages from URL parameters
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'session_expired':
            $error = 'Sesi Anda telah berakhir. Silakan login kembali.';
            break;
        case 'access_denied':
            $error = 'Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman tersebut.';
            break;
        case 'security_violation':
            $error = 'Pelanggaran keamanan terdeteksi. Silakan login kembali.';
            break;
        default:
            $error = 'Terjadi kesalahan. Silakan coba lagi.';
    }
}

if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'logged_out':
            $success = 'Anda telah berhasil logout.';
            break;
        case 'registered':
            $success = 'Akun berhasil dibuat. Silakan login.';
            break;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF token validation
        if (!isset($_POST['_token']) || !verifyCsrfToken($_POST['_token'])) {
            $error = 'Token keamanan tidak valid. Silakan refresh halaman dan coba lagi.';
        } else {
            // Sanitize input
            $username = sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);

            // Basic validation
            if (empty($username) || empty($password)) {
                $error = 'Username dan password harus diisi.';
            } else {
                // Attempt login
                $auth = new Auth();
                $result = $auth->login($username, $password, $remember);

                if ($result['success']) {
                    // Login successful
                    logActivity('User login successful', 'info', [
                        'username' => $username,
                        'ip' => getClientIp()
                    ]);

                    // Redirect to intended page or dashboard
                    $redirectUrl = $_SESSION['intended_url'] ?? '../dashboard/index.php';
                    unset($_SESSION['intended_url']);

                    header('Location: ' . $redirectUrl);
                    exit;
                } else {
                    // Login failed
                    $error = $result['message'];

                    // Show captcha after multiple failed attempts
                    if (isset($result['attempts_left']) && $result['attempts_left'] <= 2) {
                        $showCaptcha = true;
                    }

                    logActivity('User login failed', 'warning', [
                        'username' => $username,
                        'ip' => getClientIp(),
                        'reason' => $result['message']
                    ]);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    }
}

// Page settings
$pageTitle = 'Login';
$currentPage = 'login';
$appName = $_ENV['APP_NAME'] ?? 'WhatsApp Monitor';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle . ' | ' . $appName); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/custom/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/custom/images/favicon-16x16.png">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <!-- icheck bootstrap -->
    <link rel="stylesheet" href="../../assets/adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../../assets/adminlte/dist/css/adminlte.min.css">

    <!-- Custom CSS for login page -->
    <style>
        .login-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .login-box {
            width: 400px;
        }

        .login-card-body {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .login-logo a {
            color: white;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .login-logo img {
            max-height: 60px;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: bold;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .form-control {
            border-radius: 25px;
            padding: 12px 20px;
            border: 1px solid #ddd;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .input-group-text {
            border-radius: 25px 0 0 25px;
            border: 1px solid #ddd;
            border-right: none;
            background: #f8f9fa;
        }

        .input-group .form-control {
            border-radius: 0 25px 25px 0;
            border-left: none;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .icheck-primary {
            margin-top: 10px;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
        }

        .forgot-password:hover {
            color: #5a6fd8;
            text-decoration: underline;
        }
    </style>
</head>

<body class="hold-transition login-page">
    <div class="login-box">
        <!-- Logo -->
        <div class="login-logo">
            <a href="../../index.php">
                <img src="../../assets/custom/images/logo.png" alt="<?php echo htmlspecialchars($appName); ?>"
                    onerror="this.style.display='none'">
                <br>
                <b><?php echo htmlspecialchars($appName); ?></b>
            </a>
        </div>

        <!-- Login Card -->
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">Masuk untuk memulai sesi Anda</p>

                <!-- Success Message -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" action="" autocomplete="off">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="<?php echo getCsrfToken(); ?>">

                    <!-- Username Field -->
                    <div class="input-group mb-3">
                        <input type="text"
                            class="form-control"
                            name="username"
                            placeholder="Username atau Email"
                            value="<?php echo htmlspecialchars($username); ?>"
                            required
                            autocomplete="username"
                            autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-user"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="input-group mb-3">
                        <input type="password"
                            class="form-control"
                            name="password"
                            placeholder="Password"
                            required
                            autocomplete="current-password">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Captcha (if needed) -->
                    <?php if ($showCaptcha): ?>
                        <div class="input-group mb-3">
                            <div class="captcha-container text-center w-100">
                                <div class="alert alert-warning">
                                    <i class="fas fa-shield-alt"></i>
                                    Untuk keamanan, silakan verifikasi bahwa Anda bukan robot.
                                </div>
                                <!-- Simple math captcha -->
                                <?php
                                $num1 = mt_rand(1, 10);
                                $num2 = mt_rand(1, 10);
                                $captchaAnswer = $num1 + $num2;
                                ?>
                                <input type="hidden" name="captcha_answer" value="<?php echo $captchaAnswer; ?>">
                                <label><?php echo $num1; ?> + <?php echo $num2; ?> = ?</label>
                                <input type="number" class="form-control" name="captcha_input" placeholder="Hasil penjumlahan" required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Remember Me & Submit -->
                    <div class="row">
                        <div class="col-8">
                            <div class="icheck-primary">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">
                                    Ingat saya
                                </label>
                            </div>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-sign-in-alt"></i> Masuk
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Additional Links -->
                <p class="mb-1 text-center mt-3">
                    <a href="forgot-password.php" class="forgot-password">
                        <i class="fas fa-key"></i> Lupa password?
                    </a>
                </p>

                <?php if (($_ENV['ALLOW_REGISTRATION'] ?? 'false') === 'true'): ?>
                    <p class="mb-0 text-center">
                        <a href="register.php" class="text-center">
                            <i class="fas fa-user-plus"></i> Daftar akun baru
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="../../assets/adminlte/plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="../../assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="../../assets/adminlte/dist/js/adminlte.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        $(document).ready(function() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Form validation
            $('form').on('submit', function(e) {
                var username = $('input[name="username"]').val().trim();
                var password = $('input[name="password"]').val();

                if (username === '' || password === '') {
                    e.preventDefault();
                    showAlert('Username dan password harus diisi!', 'danger');
                    return false;
                }

                // Captcha validation
                <?php if ($showCaptcha): ?>
                    var captchaInput = $('input[name="captcha_input"]').val();
                    var captchaAnswer = $('input[name="captcha_answer"]').val();

                    if (parseInt(captchaInput) !== parseInt(captchaAnswer)) {
                        e.preventDefault();
                        showAlert('Hasil penjumlahan salah!', 'danger');
                        return false;
                    }
                <?php endif; ?>

                // Show loading state
                var submitBtn = $(this).find('button[type="submit"]');
                submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
                submitBtn.prop('disabled', true);
            });

            // Show/hide password
            $(document).on('click', '.toggle-password', function() {
                var passwordField = $(this).siblings('input[type="password"], input[type="text"]');
                var icon = $(this).find('i');

                if (passwordField.attr('type') === 'password') {
                    passwordField.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordField.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Focus on first input
            $('input[name="username"]').focus();
        });

        // Helper function to show alerts
        function showAlert(message, type) {
            var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                '<i class="fas fa-exclamation-triangle"></i> ' + message +
                '<button type="button" class="close" data-dismiss="alert">' +
                '<span>&times;</span></button></div>';

            $('.login-box-msg').after(alertHtml);

            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        }

        // Prevent back button after logout
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

    <!-- System Status Check -->
    <script>
        $(document).ready(function() {
            // Check system status
            $.ajax({
                url: '../../system-status.php',
                method: 'GET',
                timeout: 5000,
                success: function(response) {
                    if (response.status === 'error') {
                        showAlert('Sistem mengalami masalah: ' + (response.issues ? response.issues.join(', ') : 'Unknown error'), 'danger');
                    } else if (response.status === 'warning') {
                        showAlert('Peringatan sistem: ' + (response.issues ? response.issues.join(', ') : 'System warnings detected'), 'warning');
                    }

                    // Log system info for debugging
                    console.log('System Status:', response);
                },
                error: function(xhr, status, error) {
                    // Silently fail - don't show error to user on login page
                    console.log('System status check failed:', error);
                }
            });
        });
    </script>

</body>

</html>