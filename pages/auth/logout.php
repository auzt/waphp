<?php

/**
 * Logout Handler
 * 
 * Handles user logout and session cleanup
 * Redirects to login page with confirmation message
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

// Initialize variables
$redirectUrl = '/pages/auth/login.php';
$message = '';
$messageType = 'info';

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        $message = 'Anda sudah logout sebelumnya.';
        $messageType = 'info';
    } else {
        // Get current user info for logging
        $currentUser = getCurrentUser();
        $username = $currentUser['username'] ?? 'unknown';
        $userId = $currentUser['id'] ?? null;

        // Perform logout
        $auth = new Auth();
        $result = $auth->logout();

        if ($result['success']) {
            $message = 'Anda telah berhasil logout. Terima kasih telah menggunakan WhatsApp Monitor.';
            $messageType = 'success';

            // Log activity (before session is destroyed)
            logActivity('manual_logout', "User {$username} logged out manually", $userId);
        } else {
            $message = 'Logout berhasil.'; // Even if there's an error, treat as success
            $messageType = 'success';
        }
    }

    // Handle AJAX logout requests
    if (isAjaxRequest()) {
        sendJsonResponse([
            'success' => true,
            'message' => $message,
            'redirect' => $redirectUrl
        ]);
    }
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());

    // Force logout even if there's an error
    session_destroy();

    $message = 'Logout berhasil.';
    $messageType = 'success';

    // Handle AJAX logout requests
    if (isAjaxRequest()) {
        sendJsonResponse([
            'success' => true,
            'message' => $message,
            'redirect' => $redirectUrl
        ]);
    }
}

// Check for custom redirect URL
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $customRedirect = sanitizeString($_GET['redirect']);

    // Validate redirect URL (prevent open redirect attacks)
    $allowedRedirects = [
        '/pages/auth/login.php',
        '/index.php',
        '/'
    ];

    if (
        in_array($customRedirect, $allowedRedirects) ||
        strpos($customRedirect, '/pages/') === 0
    ) {
        $redirectUrl = $customRedirect;
    }
}

// Set flash message for login page
if (!empty($message)) {
    session_start(); // Start new session for flash message
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $messageType;
}

// Page configuration for logout confirmation
$pageTitle = 'Logout';
$pageDescription = 'Logout dari WhatsApp Monitor';
$bodyClass = 'hold-transition login-page';

// Show logout confirmation page for a moment before redirect
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
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/dist/css/adminlte.min.css">

    <!-- Custom CSS -->
    <style>
        .logout-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-box {
            width: 400px;
            text-align: center;
        }

        .logout-box .card {
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .logout-logo {
            color: white;
            font-weight: 300;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
        }

        .logout-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        .redirect-info {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .progress {
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            background-color: #e9ecef;
        }

        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.1s ease;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 30px;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body class="logout-page">
    <div class="logout-box">
        <!-- Logo -->
        <div class="logout-logo">
            <img src="<?php echo ASSETS_URL; ?>/custom/images/logo-white.png"
                alt="WhatsApp Monitor"
                style="height: 50px; margin-bottom: 10px;"
                onerror="this.style.display='none'">
            <br>
            <b>WhatsApp</b> Monitor
        </div>

        <!-- Logout Card -->
        <div class="card">
            <div class="card-body p-4">
                <!-- Success Icon -->
                <div class="logout-icon">
                    <i class="fas fa-check-circle"></i>
                </div>

                <!-- Logout Message -->
                <h4 class="mb-3">Logout Berhasil!</h4>
                <p class="text-muted mb-4">
                    <?php echo htmlspecialchars($message); ?>
                </p>

                <!-- Auto Redirect Progress -->
                <div class="redirect-info mb-3">
                    <small>
                        <i class="fas fa-clock"></i>
                        Mengarahkan ke halaman login dalam <span id="countdown">3</span> detik...
                    </small>
                </div>

                <!-- Progress Bar -->
                <div class="progress mb-3">
                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                </div>

                <!-- Manual Login Button -->
                <a href="<?php echo htmlspecialchars($redirectUrl); ?>" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Login Kembali
                </a>

                <!-- Additional Info -->
                <div class="mt-4 pt-3 border-top">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt"></i>
                        Sesi Anda telah dihapus dengan aman
                    </small>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-4">
            <small style="color: rgba(255,255,255,0.8);">
                &copy; <?php echo date('Y'); ?> WhatsApp Monitor. All rights reserved.
            </small>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            let countdown = 3;
            const redirectUrl = '<?php echo addslashes($redirectUrl); ?>';

            // Update countdown and progress bar
            const countdownInterval = setInterval(function() {
                countdown--;
                $('#countdown').text(countdown);

                // Update progress bar
                const progress = ((3 - countdown) / 3) * 100;
                $('#progressBar').css('width', progress + '%');

                if (countdown <= 0) {
                    clearInterval(countdownInterval);

                    // Redirect to login page
                    window.location.href = redirectUrl;
                }
            }, 1000);

            // Allow manual redirect by clicking anywhere
            $(document).on('click', function() {
                clearInterval(countdownInterval);
                window.location.href = redirectUrl;
            });

            // Prevent back button
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            window.addEventListener('popstate', function(event) {
                window.location.href = redirectUrl;
            });
        });

        // Clear any remaining session data
        if (typeof(Storage) !== "undefined") {
            // Clear localStorage
            localStorage.removeItem('autoRefreshInterval');
            localStorage.removeItem('sidebarMini');
            localStorage.removeItem('sidebarCollapse');
            localStorage.removeItem('navbarFixed');
            localStorage.removeItem('footerFixed');
        }

        // Clear sessionStorage
        if (typeof(sessionStorage) !== "undefined") {
            sessionStorage.clear();
        }
    </script>
</body>

</html>