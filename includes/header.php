<?php

/**
 * HTML Header with AdminLTE CSS
 * 
 * Include this file at the top of every page
 * Handles authentication check, page variables, and CSS loading
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load required files
require_once APP_ROOT . '/config/constants.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/session.php';

// Set default values if not defined
$pageTitle = $pageTitle ?? 'Dashboard';
$pageDescription = $pageDescription ?? 'WhatsApp Monitor Dashboard';
$bodyClass = $bodyClass ?? 'sidebar-mini layout-fixed';
$currentPage = $currentPage ?? '';
$breadcrumbs = $breadcrumbs ?? [];

// Check authentication (skip for login page)
if ($currentPage !== 'login' && $currentPage !== 'logout') {
    requireAuth();
}

// Get current user
$currentUser = getCurrentUser();
$userRole = getCurrentUserRole();

// Set page-specific variables
$appName = 'WhatsApp Monitor';
$appVersion = '1.0.0';

// Check for flash messages
$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <!-- Page Info -->
    <title><?php echo htmlspecialchars($pageTitle . ' - ' . $appName); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="author" content="WhatsApp Monitor Team">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo ASSETS_URL; ?>/custom/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo ASSETS_URL; ?>/custom/images/favicon-16x16.png">

    <!-- Security Headers -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?php echo getCsrfToken(); ?>">

    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/dist/css/adminlte.min.css">

    <!-- Additional Plugins CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/bootstrap4-duallistbox/bootstrap-duallistbox.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/bs-stepper/css/bs-stepper.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/dropzone/min/dropzone.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

    <!-- Toastr -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/adminlte/plugins/toastr/toastr.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/custom/css/custom.css">

    <!-- Page-specific CSS -->
    <?php if (isset($extraCSS) && is_array($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Inline CSS -->
    <style>
        .main-header.navbar {
            border-bottom: 1px solid #dee2e6;
        }

        .content-wrapper {
            background-color: #f8f9fa;
        }

        .card {
            box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
            margin-bottom: 1rem;
        }

        .btn {
            border-radius: 0.25rem;
        }

        .badge {
            font-size: 75%;
        }

        /* Status badges */
        .status-connected {
            background-color: #28a745;
        }

        .status-connecting {
            background-color: #ffc107;
        }

        .status-disconnected {
            background-color: #6c757d;
        }

        .status-pairing {
            background-color: #17a2b8;
        }

        .status-banned {
            background-color: #dc3545;
        }

        .status-error {
            background-color: #dc3545;
        }

        .status-timeout {
            background-color: #fd7e14;
        }

        /* Loading animation */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #007bff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Custom scrollbar */
        .main-sidebar .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .main-sidebar .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .main-sidebar .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        /* Responsive adjustments */
        @media (max-width: 767.98px) {

            .content-wrapper,
            .right-side,
            .main-footer {
                margin-left: 0;
            }
        }
    </style>

    <!-- Page-specific inline CSS -->
    <?php if (isset($inlineCSS)): ?>
        <style>
            <?php echo $inlineCSS; ?>
        </style>
    <?php endif; ?>
</head>

<body class="hold-transition <?php echo htmlspecialchars($bodyClass); ?>">
    <!-- Wrapper -->
    <div class="wrapper">

        <!-- Flash Messages -->
        <?php if ($flashMessage): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    toastr.<?php echo $flashMessage['type']; ?>('<?php echo addslashes($flashMessage['message']); ?>');
                });
            </script>
        <?php endif; ?>

        <!-- CSRF Token for AJAX -->
        <script>
            window.csrfToken = '<?php echo getCsrfToken(); ?>';
            window.baseUrl = '<?php echo BASE_URL; ?>';
            window.assetsUrl = '<?php echo ASSETS_URL; ?>';

            // Set CSRF token for all AJAX requests
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken
                }
            });
        </script>