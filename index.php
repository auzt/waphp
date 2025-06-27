<?php

/**
 * ===============================================================================
 * WHATSAPP MONITOR - MAIN ENTRY POINT
 * ===============================================================================
 * Main entry point untuk WhatsApp Monitor application
 * Handles routing, authentication, dan redirect ke dashboard
 * ===============================================================================
 */

// Define application root
define('APP_ROOT', __DIR__);

// Start output buffering
ob_start();

// Set error reporting based on environment
if (file_exists(APP_ROOT . '/.env')) {
    $envLines = file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, ' "\'');
        }
    }
}

$isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
if ($isDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
}

// Set timezone
$timezone = $_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta';
date_default_timezone_set($timezone);

// Load required files
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/session.php';

try {
    // Check if user is logged in
    if (isLoggedIn()) {
        // User is logged in, redirect to dashboard
        header('Location: /pages/dashboard/index.php');
        exit;
    } else {
        // User not logged in, redirect to login page
        header('Location: /pages/auth/login.php');
        exit;
    }
} catch (Exception $e) {
    // Handle any errors gracefully
    error_log("Index error: " . $e->getMessage());

    if ($isDebug) {
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>WhatsApp Monitor - Error</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; }
                .debug { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <h1>WhatsApp Monitor - Application Error</h1>
            <div class='error'>
                <strong>Error:</strong> {$e->getMessage()}
            </div>
            <div class='debug'>
                <strong>File:</strong> {$e->getFile()}<br>
                <strong>Line:</strong> {$e->getLine()}<br>
                <strong>Trace:</strong><br>
                <pre>" . $e->getTraceAsString() . "</pre>
            </div>
            <hr>
            <p><strong>Troubleshooting:</strong></p>
            <ul>
                <li>Check if database connection is properly configured</li>
                <li>Ensure .env file exists and contains correct settings</li>
                <li>Verify file permissions for logs and storage directories</li>
                <li>Check if all required PHP extensions are installed</li>
            </ul>
        </body>
        </html>
        ";
    } else {
        // Production error page
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>WhatsApp Monitor - Service Unavailable</title>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; padding: 0; 
                    background: #f8f9fa;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }
                .container {
                    text-align: center;
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    max-width: 500px;
                }
                .error-icon {
                    font-size: 64px;
                    color: #dc3545;
                    margin-bottom: 20px;
                }
                h1 { color: #333; margin-bottom: 20px; }
                p { color: #666; line-height: 1.6; }
                .btn {
                    display: inline-block;
                    background: #007bff;
                    color: white;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 20px;
                }
                .btn:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='error-icon'>⚠️</div>
                <h1>Service Temporarily Unavailable</h1>
                <p>We're experiencing technical difficulties. Our team has been notified and is working to resolve the issue.</p>
                <p>Please try again in a few minutes.</p>
                <a href='/' class='btn'>Refresh Page</a>
            </div>
        </body>
        </html>
        ";
    }
}

// Clean output buffer and exit
ob_end_flush();
