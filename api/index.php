<?php

/**
 * ===============================================================================
 * API ROUTER - WhatsApp Monitor API Entry Point
 * ===============================================================================
 * Main API router yang menangani semua request API
 * - Authentication via API token
 * - Rate limiting
 * - Request routing
 * - Error handling
 * - CORS support
 * ===============================================================================
 */

// Define APP_ROOT
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Set proper headers for API
header('Content-Type: application/json; charset=utf-8');
header('X-Powered-By: WhatsApp Monitor API v1.0');

// Enable CORS for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load required files
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/classes/Database.php';
require_once APP_ROOT . '/classes/Auth.php';

// Initialize variables
$requestStartTime = microtime(true);
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$requestData = null;
$apiLogger = null;
$device = null;

try {
    // Initialize API logger if available
    if (file_exists(APP_ROOT . '/classes/ApiLogger.php')) {
        require_once APP_ROOT . '/classes/ApiLogger.php';
        $apiLogger = new ApiLogger();
    }

    // Get request data based on method
    switch ($requestMethod) {
        case 'GET':
            $requestData = $_GET;
            break;
        case 'POST':
        case 'PUT':
        case 'DELETE':
            $rawInput = file_get_contents('php://input');
            $requestData = json_decode($rawInput, true) ?: $_POST;
            break;
    }

    // Extract API path
    $apiPath = str_replace('/api/', '', parse_url($requestUri, PHP_URL_PATH));
    $apiPath = trim($apiPath, '/');

    // Parse API path
    $pathParts = explode('/', $apiPath);
    $controller = $pathParts[0] ?? '';
    $action = $pathParts[1] ?? 'index';
    $id = $pathParts[2] ?? null;

    // Handle public endpoints (no authentication required)
    if (
        in_array($apiPath, ['health', 'status', 'docs', 'documentation']) ||
        strpos($apiPath, 'system/') === 0
    ) {
        handlePublicEndpoint($apiPath);
        exit;
    }

    // Validate API path
    if (empty($controller)) {
        throw new Exception('API endpoint not specified', 400);
    }

    // API Authentication
    $apiKey = getApiKey();
    if (!$apiKey) {
        throw new Exception('API key required. Please provide API key in X-API-Key header or Authorization Bearer token', 401);
    }

    // Verify API key and get associated device
    $device = verifyApiKey($apiKey);
    if (!$device) {
        throw new Exception('Invalid API key. Please check your API key and try again', 401);
    }

    // Rate limiting check
    if (!checkRateLimit($apiKey)) {
        throw new Exception('Rate limit exceeded. Please wait before making more requests', 429);
    }

    // Update API token last used
    updateApiTokenUsage($apiKey);

    // Route to appropriate controller
    $response = routeRequest($controller, $action, $id, $requestData, $device);

    // Log successful API request
    $executionTime = microtime(true) - $requestStartTime;
    if ($apiLogger) {
        $apiLogger->logApiRequest(
            $device['id'],
            $requestMethod,
            $apiPath,
            $requestData,
            $response,
            200,
            $executionTime
        );
    }

    // Send successful response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $response,
        'meta' => [
            'execution_time' => round($executionTime * 1000, 2) . 'ms',
            'memory_usage' => formatBytes(memory_get_usage()),
            'timestamp' => date('c'),
            'api_version' => '1.0.0'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    // Log error
    $executionTime = microtime(true) - $requestStartTime;
    $statusCode = $e->getCode() ?: 500;

    if ($apiLogger && $device) {
        $apiLogger->logApiRequest(
            $device['id'] ?? null,
            $requestMethod,
            $apiPath ?? '',
            $requestData,
            null,
            $statusCode,
            $executionTime,
            $e->getMessage()
        );
    }

    // Log to error log
    error_log("API Error [{$statusCode}]: {$e->getMessage()} | Path: {$apiPath} | IP: " . getClientIp());

    // Send error response
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $statusCode,
            'message' => $e->getMessage(),
            'type' => getErrorType($statusCode),
            'details' => ($_ENV['APP_DEBUG'] ?? false) ? [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ] : null
        ],
        'meta' => [
            'execution_time' => round($executionTime * 1000, 2) . 'ms',
            'timestamp' => date('c'),
            'request_id' => uniqid('req_')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Handle public endpoints that don't require authentication
 */
function handlePublicEndpoint($endpoint)
{
    switch ($endpoint) {
        case 'health':
        case 'status':
        case 'system/status':
            handleHealthCheck();
            break;
        case 'docs':
        case 'documentation':
            handleDocumentation();
            break;
        default:
            throw new Exception('Unknown public endpoint', 404);
    }
}

/**
 * Handle health check endpoint
 */
function handleHealthCheck()
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
        $diskUsage = (($diskTotal - $diskFree) / $diskTotal) * 100;

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
            'uptime' => getSystemUptime()
        ];

        // Determine overall status
        $overallStatus = 'healthy';
        if (!$dbStatus) $overallStatus = 'unhealthy';
        elseif (!$nodeStatus || $diskUsage > 90) $overallStatus = 'degraded';

        $statusCode = $overallStatus === 'unhealthy' ? 503 : 200;

        http_response_code($statusCode);
        echo json_encode([
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
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Handle API documentation endpoint
 */
function handleDocumentation()
{
    $documentation = [
        'name' => 'WhatsApp Monitor API',
        'version' => '1.0.0',
        'description' => 'REST API for WhatsApp monitoring and management',
        'base_url' => getBaseUrl() . '/api',
        'authentication' => [
            'type' => 'API Key',
            'methods' => [
                'header' => 'X-API-Key: {your_api_key}',
                'bearer' => 'Authorization: Bearer {your_api_key}',
                'query' => '?api_key={your_api_key} (not recommended for production)'
            ],
            'note' => 'API keys are generated automatically when you create a device'
        ],
        'endpoints' => [
            'devices' => [
                'GET /devices' => [
                    'description' => 'List all devices for authenticated user',
                    'parameters' => [
                        'status' => 'Filter by device status',
                        'search' => 'Search in device name or phone number',
                        'limit' => 'Number of results (max 100)',
                        'offset' => 'Offset for pagination'
                    ]
                ],
                'GET /devices/{id}' => 'Get device details and statistics',
                'POST /devices' => [
                    'description' => 'Create new device',
                    'required' => ['device_name', 'phone_number']
                ],
                'PUT /devices/{id}' => 'Update device information',
                'DELETE /devices/{id}' => 'Delete device and all associated data',
                'POST /devices/{id}/connect' => 'Connect device to WhatsApp',
                'POST /devices/{id}/disconnect' => 'Disconnect device from WhatsApp',
                'POST /devices/{id}/restart' => 'Restart device connection',
                'GET /devices/{id}/qr' => 'Get QR code for device pairing',
                'GET /devices/{id}/status' => 'Get real-time device status',
                'GET /devices/{id}/stats' => 'Get device statistics and charts',
                'POST /devices/{id}/clear-qr' => 'Clear stored QR code',
                'GET /devices/{id}/test' => 'Test device connection'
            ],
            'messages' => [
                'GET /messages' => 'List messages with filters',
                'POST /messages' => 'Send text message',
                'POST /messages/media' => 'Send media message',
                'POST /messages/bulk' => 'Send bulk messages',
                'GET /messages/{id}' => 'Get message details',
                'DELETE /messages/{id}' => 'Delete message'
            ],
            'contacts' => [
                'GET /contacts' => 'List contacts',
                'GET /contacts/{id}' => 'Get contact details',
                'POST /contacts/{id}/block' => 'Block contact',
                'POST /contacts/{id}/unblock' => 'Unblock contact'
            ],
            'webhooks' => [
                'POST /webhooks/test' => 'Test webhook connectivity',
                'GET /webhooks/logs' => 'Get webhook execution logs'
            ]
        ],
        'status_codes' => [
            200 => 'OK - Request successful',
            400 => 'Bad Request - Invalid parameters or request format',
            401 => 'Unauthorized - Invalid or missing API key',
            403 => 'Forbidden - Insufficient permissions',
            404 => 'Not Found - Resource not found',
            429 => 'Too Many Requests - Rate limit exceeded',
            500 => 'Internal Server Error - Server error',
            503 => 'Service Unavailable - Service temporarily unavailable'
        ],
        'rate_limits' => [
            'per_minute' => '60 requests per minute per API key',
            'per_hour' => '1000 requests per hour per API key',
            'burst' => 'Short bursts of up to 10 requests per second allowed'
        ],
        'response_format' => [
            'success' => [
                'success' => true,
                'data' => 'Response data',
                'meta' => 'Metadata (execution time, memory usage, etc.)'
            ],
            'error' => [
                'success' => false,
                'error' => [
                    'code' => 'HTTP status code',
                    'message' => 'Error description',
                    'type' => 'Error type'
                ],
                'meta' => 'Request metadata'
            ]
        ],
        'examples' => [
            'create_device' => [
                'method' => 'POST',
                'url' => '/api/devices',
                'headers' => ['X-API-Key: your_api_key'],
                'body' => [
                    'device_name' => 'My WhatsApp Device',
                    'phone_number' => '+628123456789'
                ]
            ],
            'send_message' => [
                'method' => 'POST',
                'url' => '/api/messages',
                'headers' => ['X-API-Key: your_api_key'],
                'body' => [
                    'to' => '+628123456789',
                    'message' => 'Hello from WhatsApp Monitor!'
                ]
            ]
        ]
    ];

    http_response_code(200);
    echo json_encode($documentation, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Get API key from request headers
 */
function getApiKey()
{
    // Check X-API-Key header (preferred method)
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        return trim($_SERVER['HTTP_X_API_KEY']);
    }

    // Check Authorization Bearer token
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return trim($matches[1]);
        }
    }

    // Check query parameter (less secure, for testing only)
    if (isset($_GET['api_key']) && ($_ENV['APP_DEBUG'] ?? false)) {
        return trim($_GET['api_key']);
    }

    return null;
}

/**
 * Verify API key and return associated device
 */
function verifyApiKey($apiKey)
{
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT d.*, u.username, u.full_name, u.role,
                   t.usage_count, t.last_used, t.token_name
            FROM devices d
            INNER JOIN api_tokens t ON d.id = t.device_id
            INNER JOIN users u ON d.user_id = u.id
            WHERE t.token = ? AND t.is_active = 1 AND u.status = 'active'
        ");
        $stmt->execute([$apiKey]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("API key verification error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check rate limiting
 */
function checkRateLimit($apiKey)
{
    try {
        $db = Database::getInstance()->getConnection();

        // Check requests in last minute
        $stmt = $db->prepare("
            SELECT COUNT(*) as request_count
            FROM api_logs 
            WHERE api_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$apiKey]);
        $minuteResult = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check requests in last hour
        $stmt = $db->prepare("
            SELECT COUNT(*) as request_count
            FROM api_logs 
            WHERE api_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$apiKey]);
        $hourResult = $stmt->fetch(PDO::FETCH_ASSOC);

        $maxPerMinute = $_ENV['API_RATE_LIMIT_MINUTE'] ?? 60;
        $maxPerHour = $_ENV['API_RATE_LIMIT_HOUR'] ?? 1000;

        return $minuteResult['request_count'] < $maxPerMinute &&
            $hourResult['request_count'] < $maxPerHour;
    } catch (Exception $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return true; // Allow request if check fails
    }
}

/**
 * Update API token usage statistics
 */
function updateApiTokenUsage($apiKey)
{
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            UPDATE api_tokens 
            SET usage_count = usage_count + 1, last_used = NOW() 
            WHERE token = ?
        ");
        $stmt->execute([$apiKey]);
    } catch (Exception $e) {
        error_log("API token usage update error: " . $e->getMessage());
    }
}

/**
 * Route request to appropriate controller
 */
function routeRequest($controller, $action, $id, $data, $device)
{
    $controllerFile = APP_ROOT . "/api/{$controller}.php";

    if (!file_exists($controllerFile)) {
        throw new Exception("API endpoint '{$controller}' not found", 404);
    }

    // Include controller file
    require_once $controllerFile;

    // Build function name
    $functionName = $controller . '_' . $action;

    // Check if function exists
    if (!function_exists($functionName)) {
        throw new Exception("API action '{$action}' not found in '{$controller}' controller", 404);
    }

    // Call controller function
    return call_user_func($functionName, $id, $data, $device);
}

/**
 * Check Node.js backend status
 */
function checkNodeJSStatus()
{
    try {
        $nodeUrl = $_ENV['NODEJS_URL'] ?? 'http://localhost:3000';
        $timeout = 5; // 5 seconds timeout

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
 * Get error type based on status code
 */
function getErrorType($statusCode)
{
    $errorTypes = [
        400 => 'validation_error',
        401 => 'authentication_error',
        403 => 'authorization_error',
        404 => 'not_found_error',
        429 => 'rate_limit_error',
        500 => 'internal_error',
        503 => 'service_unavailable'
    ];

    return $errorTypes[$statusCode] ?? 'unknown_error';
}

/**
 * Get base URL for the application
 */
function getBaseUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

/**
 * Get system uptime (Linux only)
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
