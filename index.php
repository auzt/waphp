<?php

/**
 * WhatsApp Monitor - Entry Point
 * Router utama untuk aplikasi PHP
 * 
 * Handles:
 * - Session management
 * - Authentication check
 * - Route dispatching
 * - API routing
 */

// Start session first
session_start();

// Define base paths
define('BASE_PATH', __DIR__);
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('PAGES_PATH', BASE_PATH . '/pages');
define('CLASSES_PATH', BASE_PATH . '/classes');

// Load configuration
require_once CONFIG_PATH . '/app.php';
require_once CONFIG_PATH . '/database.php';
require_once CONFIG_PATH . '/constants.php';

// Load core functions
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/session.php';

// Load core classes
require_once CLASSES_PATH . '/Database.php';
require_once CLASSES_PATH . '/Auth.php';

// Initialize error handling
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/logs/error.log');

/**
 * Custom error handler
 */
function customErrorHandler($errno, $errstr, $errfile, $errline)
{
    $error_types = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE'
    ];

    $type = isset($error_types[$errno]) ? $error_types[$errno] : 'UNKNOWN';
    $message = "[{$type}] {$errstr} in {$errfile} on line {$errline}";

    error_log($message);

    if (!APP_DEBUG && in_array($errno, [E_ERROR, E_PARSE])) {
        redirect('/pages/errors/500.php');
    }

    return false;
}

set_error_handler('customErrorHandler');

/**
 * Exception handler
 */
function customExceptionHandler($exception)
{
    $message = "Uncaught exception: " . $exception->getMessage() .
        " in " . $exception->getFile() .
        " on line " . $exception->getLine();

    error_log($message);

    if (!APP_DEBUG) {
        redirect('/pages/errors/500.php');
    } else {
        echo "<h1>Uncaught Exception</h1>";
        echo "<p>" . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
    }
}

set_exception_handler('customExceptionHandler');

/**
 * Get current route from URL
 */
function getCurrentRoute()
{
    $request_uri = $_SERVER['REQUEST_URI'];
    $script_name = dirname($_SERVER['SCRIPT_NAME']);

    // Remove script directory from URI
    if ($script_name !== '/') {
        $request_uri = str_replace($script_name, '', $request_uri);
    }

    // Remove query string
    $request_uri = strtok($request_uri, '?');

    // Clean up the route
    $route = trim($request_uri, '/');

    return $route === '' ? 'dashboard' : $route;
}

/**
 * Route dispatcher
 */
function routeRequest($route)
{
    // Check if it's an API request
    if (strpos($route, 'api/') === 0) {
        return handleApiRequest($route);
    }

    // Initialize auth
    $auth = new Auth();

    // Public routes (no authentication required)
    $publicRoutes = [
        'pages/auth/login',
        'pages/auth/register',
        'pages/errors/404',
        'pages/errors/500',
        'assets'
    ];

    // Check if current route is public
    $isPublicRoute = false;
    foreach ($publicRoutes as $publicRoute) {
        if (strpos($route, $publicRoute) === 0) {
            $isPublicRoute = true;
            break;
        }
    }

    // If not logged in and trying to access protected route
    if (!$auth->isLoggedIn() && !$isPublicRoute) {
        // Store intended URL for redirect after login
        if ($route !== 'pages/auth/login') {
            $_SESSION['intended_url'] = '/' . $route;
        }
        redirect('/pages/auth/login.php');
        return;
    }

    // If logged in and trying to access login page
    if ($auth->isLoggedIn() && $route === 'pages/auth/login') {
        redirect('/pages/dashboard/');
        return;
    }

    // Route to appropriate page
    return dispatchRoute($route);
}

/**
 * Handle API requests
 */
function handleApiRequest($route)
{
    // Set JSON content type
    header('Content-Type: application/json');

    // Enable CORS for API
    header('Access-Control-Allow-Origin: http://localhost:3000');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    header('Access-Control-Allow-Credentials: true');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Remove 'api/' from route
    $apiRoute = substr($route, 4);

    // Route to appropriate API endpoint
    $apiFile = BASE_PATH . '/api/index.php';

    if (file_exists($apiFile)) {
        // Set API route for internal routing
        $_SERVER['API_ROUTE'] = $apiRoute;
        include $apiFile;
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'API endpoint not found',
            'route' => $apiRoute
        ]);
    }

    exit;
}

/**
 * Dispatch route to appropriate page
 */
function dispatchRoute($route)
{
    // Default route mappings
    $routes = [
        // Dashboard
        '' => '/pages/dashboard/index.php',
        'dashboard' => '/pages/dashboard/index.php',

        // Authentication
        'login' => '/pages/auth/login.php',
        'logout' => '/pages/auth/logout.php',
        'register' => '/pages/auth/register.php',

        // Devices
        'devices' => '/pages/devices/index.php',
        'devices/add' => '/pages/devices/add.php',
        'devices/edit' => '/pages/devices/edit.php',
        'devices/view' => '/pages/devices/view.php',
        'devices/delete' => '/pages/devices/delete.php',

        // Users
        'users' => '/pages/users/index.php',
        'users/add' => '/pages/users/add.php',
        'users/edit' => '/pages/users/edit.php',
        'users/profile' => '/pages/users/profile.php',

        // API Tokens
        'api-tokens' => '/pages/api-tokens/index.php',
        'api-tokens/generate' => '/pages/api-tokens/generate.php',
        'api-tokens/revoke' => '/pages/api-tokens/revoke.php',

        // Logs
        'logs/api' => '/pages/logs/api-logs.php',
        'logs/messages' => '/pages/logs/message-logs.php',
        'logs/system' => '/pages/logs/system-logs.php',

        // Settings
        'settings' => '/pages/settings/system.php',
        'settings/api' => '/pages/settings/api.php',
        'settings/webhook' => '/pages/settings/webhook.php',

        // Monitoring
        'monitoring/devices' => '/pages/monitoring/devices.php',
        'monitoring/performance' => '/pages/monitoring/performance.php',
        'monitoring/alerts' => '/pages/monitoring/alerts.php'
    ];

    // Check if route exists in mapping
    if (isset($routes[$route])) {
        $filePath = BASE_PATH . $routes[$route];
    } else {
        // Try direct file mapping
        $filePath = BASE_PATH . '/' . $route;

        // Add .php extension if not present
        if (!pathinfo($filePath, PATHINFO_EXTENSION)) {
            $filePath .= '.php';
        }
    }

    // Security check - prevent directory traversal
    $realPath = realpath($filePath);
    $basePath = realpath(BASE_PATH);

    if ($realPath === false || strpos($realPath, $basePath) !== 0) {
        return show404();
    }

    // Check if file exists and include it
    if (file_exists($filePath)) {
        include $filePath;
    } else {
        return show404();
    }
}

/**
 * Show 404 error page
 */
function show404()
{
    http_response_code(404);
    $errorFile = BASE_PATH . '/pages/errors/404.php';

    if (file_exists($errorFile)) {
        include $errorFile;
    } else {
        echo "<h1>404 - Page Not Found</h1>";
        echo "<p>The requested page could not be found.</p>";
        echo "<a href='/'>Return to Home</a>";
    }
    exit;
}

/**
 * Handle static assets
 */
function handleAssets($route)
{
    $assetPath = BASE_PATH . '/' . $route;

    if (!file_exists($assetPath)) {
        return show404();
    }

    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject'
    ];

    $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));

    if (isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);

        // Set cache headers for assets
        $expiryTime = 3600 * 24 * 30; // 30 days
        header('Cache-Control: public, max-age=' . $expiryTime);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expiryTime) . ' GMT');

        readfile($assetPath);
        exit;
    }

    return show404();
}

/**
 * Database connection check
 */
function checkDatabaseConnection()
{
    try {
        $db = new Database();
        $pdo = $db->getConnection();

        if (!$pdo) {
            throw new Exception('Database connection failed');
        }

        return true;
    } catch (Exception $e) {
        error_log('Database connection error: ' . $e->getMessage());

        if (!APP_DEBUG) {
            show_error_page('Database connection failed. Please try again later.');
        } else {
            show_error_page('Database Error: ' . $e->getMessage());
        }

        return false;
    }
}

/**
 * Show error page
 */
function show_error_page($message)
{
    http_response_code(500);
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>WhatsApp Monitor - Error</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .error-container { max-width: 500px; margin: 0 auto; }
            .error-title { color: #dc3545; font-size: 24px; margin-bottom: 20px; }
            .error-message { color: #666; margin-bottom: 30px; }
            .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <div class='error-title'>System Error</div>
            <div class='error-message'>" . htmlspecialchars($message) . "</div>
            <a href='/' class='btn'>Return to Home</a>
        </div>
    </body>
    </html>";
    exit;
}

// Main execution
try {
    // Check database connection first
    if (!checkDatabaseConnection()) {
        exit;
    }

    // Get current route
    $route = getCurrentRoute();

    // Handle static assets
    if (strpos($route, 'assets/') === 0) {
        handleAssets($route);
        exit;
    }

    // Route the request
    routeRequest($route);
} catch (Exception $e) {
    error_log('Fatal error in index.php: ' . $e->getMessage());

    if (APP_DEBUG) {
        echo "<h1>Fatal Error</h1>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        show_error_page('An unexpected error occurred. Please try again later.');
    }
}
