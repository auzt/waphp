<?php

/**
 * ===============================================================================
 * SYSTEM API CONTROLLER
 * ===============================================================================
 * Public API endpoints untuk system status dan informasi
 * Tidak memerlukan authentication
 * ===============================================================================
 */

/**
 * System status check - Public endpoint
 */
function system_status($id, $data, $device = null)
{
    try {
        // Check database connection
        $db = Database::getInstance();
        $dbStatus = $db->testConnection();

        // Check Node.js backend
        $nodeStatus = checkNodeJSStatus();

        // Check disk space
        $diskFree = disk_free_space('.');
        $diskTotal = disk_total_space('.');
        $diskUsage = $diskTotal > 0 ? (($diskTotal - $diskFree) / $diskTotal) * 100 : 0;

        // System info
        $systemInfo = [
            'version' => '1.0.0',
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'timezone' => date_default_timezone_get(),
            'php_version' => PHP_VERSION,
            'memory_usage' => formatBytes(memory_get_usage()),
            'memory_limit' => ini_get('memory_limit'),
            'disk_free' => formatBytes($diskFree),
            'disk_usage_percent' => round($diskUsage, 2),
            'server_time' => date('Y-m-d H:i:s'),
            'uptime' => getSystemUptime()
        ];

        // Determine overall status
        $overallStatus = 'ok';
        $issues = [];

        if (!$dbStatus) {
            $overallStatus = 'error';
            $issues[] = 'Database connection failed';
        }

        if (!$nodeStatus) {
            if ($overallStatus === 'ok') $overallStatus = 'warning';
            $issues[] = 'Node.js backend unreachable';
        }

        if ($diskUsage > 90) {
            if ($overallStatus === 'ok') $overallStatus = 'warning';
            $issues[] = 'Low disk space';
        }

        return [
            'status' => $overallStatus,
            'timestamp' => date('c'),
            'services' => [
                'database' => $dbStatus ? 'up' : 'down',
                'nodejs' => $nodeStatus ? 'up' : 'down',
                'filesystem' => $diskUsage < 95 ? 'up' : 'critical'
            ],
            'system' => $systemInfo,
            'checks' => [
                'database_connection' => $dbStatus,
                'nodejs_reachable' => $nodeStatus,
                'disk_space_ok' => $diskUsage < 90,
                'memory_ok' => memory_get_usage() < (parseBytes(ini_get('memory_limit')) * 0.8)
            ],
            'issues' => $issues
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ];
    }
}

/**
 * System health check - Alias for status
 */
function system_health($id, $data, $device = null)
{
    return system_status($id, $data, $device);
}

/**
 * System information - Public endpoint
 */
function system_info($id, $data, $device = null)
{
    try {
        return [
            'application' => [
                'name' => 'WhatsApp Monitor',
                'version' => '1.0.0',
                'environment' => $_ENV['APP_ENV'] ?? 'production',
                'debug_mode' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true'
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
                'timezone' => date_default_timezone_get(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ],
            'database' => [
                'driver' => 'mysql',
                'connected' => Database::getInstance()->testConnection()
            ],
            'features' => [
                'nodejs_integration' => true,
                'api_enabled' => true,
                'webhook_support' => true,
                'file_uploads' => is_writable(APP_ROOT . '/assets/uploads'),
                'logging' => is_writable(APP_ROOT . '/logs')
            ]
        ];
    } catch (Exception $e) {
        throw new Exception('Failed to get system information: ' . $e->getMessage(), 500);
    }
}

/**
 * Check Node.js backend status
 */
function checkNodeJSStatus()
{
    try {
        $nodeUrl = $_ENV['NODEJS_URL'] ?? 'http://localhost:3000';
        $timeout = 3; // 3 seconds timeout for quick check

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => 'User-Agent: WhatsApp-Monitor-PHP/1.0'
            ]
        ]);

        $result = @file_get_contents($nodeUrl . '/health', false, $context);
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get system uptime (Linux/Unix only)
 */
function getSystemUptime()
{
    if (file_exists('/proc/uptime')) {
        $uptime = file_get_contents('/proc/uptime');
        $uptimeSeconds = floatval(explode(' ', $uptime)[0]);
        return round($uptimeSeconds);
    }
    return null;
}

/**
 * Parse bytes string to integer
 */
function parseBytes($str)
{
    if (empty($str)) return 0;

    $str = trim($str);
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
