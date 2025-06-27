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

try {
    // Load bootstrap file
    require_once APP_ROOT . '/includes/bootstrap.php';

    // Check if user is logged in
    if (isLoggedIn()) {
        // User is logged in, redirect to dashboard
        redirectTo('pages/dashboard/index.php');
    } else {
        // User not logged in, redirect to login page
        redirectTo('pages/auth/login.php');
    }
} catch (Exception $e) {
    // Handle any errors gracefully
    error_log("Index error: " . $e->getMessage());

    $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

    if ($isDebug) {
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>WhatsApp Monitor - Error</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; background: #f8f9fa; }
                .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb; }
                .debug { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-top: 20px; border: 1px solid #bee5eb; }
                .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; white-space: pre-wrap; }
                h1 { color: #dc3545; }
                ul { margin: 10px 0; padding-left: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>WhatsApp Monitor - Application Error</h1>
                <div class='error'>
                    <strong>Error:</strong> {$e->getMessage()}
                </div>
                <div class='debug'>
                    <strong>File:</strong> {$e->getFile()}<br>
                    <strong>Line:</strong> {$e->getLine()}<br>
                    <strong>Trace:</strong><br>
                    <div class='code'>" . $e->getTraceAsString() . "</div>
                </div>
                <hr>
                <h3>Troubleshooting Steps:</h3>
                <ul>
                    <li>Check if database connection is properly configured in <code>.env</code> file</li>
                    <li>Ensure <code>.env</code> file exists and contains correct settings</li>
                    <li>Verify file permissions for logs and storage directories</li>
                    <li>Check if all required PHP extensions are installed (PDO, mysqli, mbstring)</li>
                    <li>Run <code>check-functions.php</code> to check for function conflicts</li>
                </ul>
                <h3>System Information:</h3>
                <ul>
                    <li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>
                    <li><strong>Working Directory:</strong> " . getcwd() . "</li>
                    <li><strong>APP_ROOT:</strong> " . APP_ROOT . "</li>
                    <li><strong>Include Path:</strong> " . get_include_path() . "</li>
                </ul>
            </div>
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
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }
                .container {
                    text-align: center;
                    background: white;
                    padding: 50px;
                    border-radius: 15px;
                    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
                    max-width: 500px;
                }
                .error-icon {
                    font-size: 80px;
                    color: #dc3545;
                    margin-bottom: 30px;
                }
                h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
                p { color: #666; line-height: 1.6; font-size: 16px; }
                .btn {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 25px;
                    margin-top: 30px;
                    font-weight: bold;
                    transition: transform 0.2s;
                }
                .btn:hover { 
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                }
                .support {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #eee;
                    font-size: 14px;
                    color: #999;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='error-icon'>⚠️</div>
                <h1>Service Temporarily Unavailable</h1>
                <p>We're experiencing technical difficulties. Our team has been notified and is working to resolve the issue.</p>
                <p>Please try again in a few minutes.</p>
                <a href='/' class='btn'>🔄 Refresh Page</a>
                <div class='support'>
                    <p>If the problem persists, please contact your system administrator.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

// Clean output buffer and exit
ob_end_flush();
