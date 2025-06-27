<?php

/**
 * ===============================================================================
 * STANDALONE SYSTEM STATUS
 * ===============================================================================
 * Standalone endpoint untuk system status tanpa memerlukan API key
 * Akses: http://localhost:8080/wanew/system-status.php
 * ===============================================================================
 */

define('APP_ROOT', __DIR__);

// Set proper headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Load minimal required files
    require_once APP_ROOT . '/includes/functions.php';
    require_once APP_ROOT . '/classes/Database.php';

    // Check database connection
    $dbStatus = false;
    $dbError = null;
    try {
        $db = Database::getInstance();
        $dbStatus = $db->testConnection();
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }

    // Check Node.js backend
    $nodeStatus = checkNodeJSBackend();

    // Check disk space
    $diskFree = disk_free_space('.');
    $diskTotal = disk_total_space('.');
    $diskUsage = $diskTotal > 0 ? (($diskTotal - $diskFree) / $diskTotal) * 100 : 0;

    // Memory check
    $memoryUsage = memory_get_usage();
    $memoryLimit = parseBytes(ini_get('memory_limit'));
    $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;

    // Determine overall status
    $overallStatus = 'ok';
    $issues = [];

    if (!$dbStatus) {
        $overallStatus = 'error';
        $issues[] = 'Database connection failed' . ($dbError ? ': ' . $dbError : '');
    }

    if (!$nodeStatus) {
        if ($overallStatus === 'ok') $overallStatus = 'warning';
        $issues[] = 'Node.js backend unreachable';
    }

    if ($diskUsage > 90) {
        if ($overallStatus === 'ok') $overallStatus = 'warning';
        $issues[] = 'Low disk space (' . round($diskUsage, 1) . '% used)';
    }

    if ($memoryPercent > 80) {
        if ($overallStatus === 'ok') $overallStatus = 'warning';
        $issues[] = 'High memory usage (' . round($memoryPercent, 1) . '% used)';
    }

    // Response
    $response = [
        'status' => $overallStatus,
        'timestamp' => date('c'),
        'services' => [
            'database' => $dbStatus ? 'up' : 'down',
            'nodejs' => $nodeStatus ? 'up' : 'down',
            'filesystem' => $diskUsage < 95 ? 'up' : 'critical',
            'memory' => $memoryPercent < 90 ? 'up' : 'critical'
        ],
        'system' => [
            'version' => '1.0.0',
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'php_version' => PHP_VERSION,
            'timezone' => date_default_timezone_get(),
            'server_time' => date('Y-m-d H:i:s'),
            'memory_usage' => formatBytes($memoryUsage),
            'memory_limit' => ini_get('memory_limit'),
            'memory_percent' => round($memoryPercent, 1),
            'disk_free' => formatBytes($diskFree),
            'disk_total' => formatBytes($diskTotal),
            'disk_usage_percent' => round($diskUsage, 1)
        ],
        'checks' => [
            'database_connection' => $dbStatus,
            'nodejs_reachable' => $nodeStatus,
            'disk_space_ok' => $diskUsage < 90,
            'memory_ok' => $memoryPercent < 80,
            'write_permissions' => [
                'logs' => is_writable(APP_ROOT . '/logs'),
                'uploads' => is_writable(APP_ROOT . '/assets/uploads'),
                'storage' => is_writable(APP_ROOT . '/storage')
            ]
        ],
        'issues' => $issues
    ];

    // Set appropriate HTTP status code
    $httpStatus = 200;
    if ($overallStatus === 'error') {
        $httpStatus = 503; // Service Unavailable
    } elseif ($overallStatus === 'warning') {
        $httpStatus = 200; // OK but with warnings
    }

    http_response_code($httpStatus);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Check Node.js backend
 */
function checkNodeJSBackend()
{
    try {
        $nodeUrl = $_ENV['NODEJS_URL'] ?? 'http://localhost:3000';
        $timeout = 3;

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => 'User-Agent: WhatsApp-Monitor-SystemCheck/1.0'
            ]
        ]);

        $result = @file_get_contents($nodeUrl . '/health', false, $context);
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Parse bytes string to integer
 */
function parseBytes($str)
{
    if (empty($str)) return 0;

    $str = trim($str);
    if (is_numeric($str)) return intval($str);

    $last = strtolower($str[strlen($str) - 1]);
    $str = substr($str, 0, -1);

    switch ($last) {
        case 'g':
            $str *= 1024;
        case 'm':
            $str *= 1024;
        case 'k':
            $str *= 1024;
    }

    return intval($str);
}
