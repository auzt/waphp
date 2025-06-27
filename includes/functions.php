<?php

/**
 * Helper Functions
 * 
 * Collection of utility functions used throughout the application
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    die('Direct access not allowed');
}

// =============================================================================
// AUTHENTICATION FUNCTIONS
// =============================================================================

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    static $currentUser = null;

    if ($currentUser === null) {
        $db = Database::getInstance();
        $currentUser = $db->fetch(
            "SELECT * FROM users WHERE id = ? AND status = 'active'",
            [getCurrentUserId()]
        );
    }

    return $currentUser;
}

/**
 * Check if user has specific role
 */
function hasRole($role)
{
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

/**
 * Check if user is admin
 */
function isAdmin()
{
    return hasRole(USER_ROLE_ADMIN);
}

/**
 * Check if user is operator
 */
function isOperator()
{
    return hasRole(USER_ROLE_OPERATOR) || isAdmin();
}

/**
 * Require specific role or redirect
 */
function requireRole($role, $redirectUrl = '/login')
{
    if (!hasRole($role)) {
        redirect($redirectUrl);
    }
}

/**
 * Logout user
 */
function logout()
{
    // Update last login
    if (isLoggedIn()) {
        $db = Database::getInstance();
        $db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [getCurrentUserId()]
        );
    }

    // Clear session
    session_destroy();
    session_start();
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Redirect to URL
 */
function redirect($url, $statusCode = 302)
{
    if (!headers_sent()) {
        header("Location: $url", true, $statusCode);
    } else {
        echo "<script>window.location.href='$url';</script>";
    }
    exit;
}

/**
 * Get base URL
 */
function getBaseUrl()
{
    static $baseUrl = null;

    if ($baseUrl === null) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['SCRIPT_NAME'];
        $path = dirname($script);

        $baseUrl = $protocol . '://' . $host . ($path === '/' ? '' : $path);
    }

    return $baseUrl;
}

/**
 * Generate URL
 */
function url($path = '')
{
    return getBaseUrl() . '/' . ltrim($path, '/');
}

/**
 * Generate asset URL
 */
function asset($path)
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Sanitize input
 */
function sanitize($input, $type = 'string')
{
    if (is_array($input)) {
        return array_map(function ($item) use ($type) {
            return sanitize($item, $type);
        }, $input);
    }

    switch ($type) {
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Escape output for HTML
 */
function e($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token input field
 */
function csrfField()
{
    $token = generateCsrfToken();
    return "<input type='hidden' name='" . CSRF_TOKEN_NAME . "' value='$token'>";
}

// =============================================================================
// VALIDATION FUNCTIONS
// =============================================================================

/**
 * Validate email
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 */
function isValidPhoneNumber($phone)
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match(REGEX_PHONE_NUMBER, $phone);
}

/**
 * Format phone number
 */
function formatPhoneNumber($phone, $countryCode = '62')
{
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Remove leading zeros
    $phone = ltrim($phone, '0');

    // Add country code if not present
    if (!str_starts_with($phone, $countryCode)) {
        $phone = $countryCode . $phone;
    }

    return $phone;
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields)
{
    $errors = [];

    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    return $errors;
}

/**
 * Validate password strength
 */
function isValidPassword($password)
{
    return strlen($password) >= PASSWORD_MIN_LENGTH;
}

// =============================================================================
// STRING FUNCTIONS
// =============================================================================

/**
 * Generate random string
 */
function generateRandomString($length = 32, $characters = null)
{
    if ($characters === null) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }

    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomString;
}

/**
 * Truncate string
 */
function truncate($string, $length = 100, $append = '...')
{
    if (strlen($string) <= $length) {
        return $string;
    }

    return substr($string, 0, $length) . $append;
}

/**
 * Convert string to slug
 */
function str_slug($string, $separator = '-')
{
    $string = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
    $string = preg_replace('/\s+/', $separator, trim($string));
    return strtolower($string);
}

/**
 * Check if string starts with
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return strpos($haystack, $needle) === 0;
    }
}

/**
 * Check if string ends with
 */
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

// =============================================================================
// DATE/TIME FUNCTIONS
// =============================================================================

/**
 * Format date for display
 */
function formatDate($date, $format = DISPLAY_DATE_FORMAT)
{
    if (empty($date)) {
        return '';
    }

    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = DISPLAY_DATETIME_FORMAT)
{
    if (empty($datetime)) {
        return '';
    }

    return date($format, strtotime($datetime));
}

/**
 * Get time ago string
 */
function timeAgo($datetime)
{
    if (empty($datetime)) {
        return '';
    }

    $time = time() - strtotime($datetime);

    if ($time < 1) return 'just now';

    $tokens = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];

    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '') . ' ago';
    }

    return '';
}

// =============================================================================
// FILE FUNCTIONS
// =============================================================================

/**
 * Format file size
 */
function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
}

/**
 * Get file extension
 */
function getFileExtension($filename)
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file type is allowed
 */
function isAllowedFileType($filename, $allowedTypes = null)
{
    if ($allowedTypes === null) {
        $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOCUMENT_TYPES);
    }

    $extension = getFileExtension($filename);
    return in_array($extension, $allowedTypes);
}

// =============================================================================
// ARRAY FUNCTIONS
// =============================================================================

/**
 * Get array value with default
 */
function array_get($array, $key, $default = null)
{
    if (is_array($array) && array_key_exists($key, $array)) {
        return $array[$key];
    }
    return $default;
}

/**
 * Check if array has key
 */
function array_has($array, $key)
{
    return is_array($array) && array_key_exists($key, $array);
}

// =============================================================================
// SESSION FUNCTIONS
// =============================================================================

/**
 * Set flash message
 */
function setFlash($type, $message)
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Get flash message
 */
function getFlash($type = null)
{
    if ($type === null) {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

/**
 * Check if flash message exists
 */
function hasFlash($type)
{
    return isset($_SESSION['flash'][$type]);
}

// =============================================================================
// CONFIG FUNCTIONS
// =============================================================================

/**
 * Get configuration value
 */
function config($key, $default = null)
{
    static $config = null;

    if ($config === null) {
        $config = require APP_ROOT . '/config/app.php';
    }

    $keys = explode('.', $key);
    $value = $config;

    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }

    return $value;
}

// =============================================================================
// LOGGING FUNCTIONS
// =============================================================================

/**
 * Log message
 */
function logger($level, $message, $context = [])
{
    $logFile = LOGS_PATH . '/app.log';
    $timestamp = date(DATETIME_FORMAT);
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;

    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Log info message
 */
function logInfo($message, $context = [])
{
    logger(LOG_INFO, $message, $context);
}

/**
 * Log error message
 */
function logError($message, $context = [])
{
    logger(LOG_ERROR, $message, $context);
}

/**
 * Log warning message
 */
function logWarning($message, $context = [])
{
    logger(LOG_WARNING, $message, $context);
}

/**
 * Log debug message
 */
function logDebug($message, $context = [])
{
    if (config('app.debug')) {
        logger(LOG_DEBUG, $message, $context);
    }
}

// =============================================================================
// API FUNCTIONS
// =============================================================================

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200, $headers = [])
{
    http_response_code($statusCode);

    header('Content-Type: application/json');
    foreach ($headers as $key => $value) {
        header("$key: $value");
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send success response
 */
function successResponse($data = null, $message = 'Success', $statusCode = 200)
{
    $response = [
        'success' => true,
        'message' => $message,
        'data' => $data
    ];

    jsonResponse($response, $statusCode);
}

/**
 * Send error response
 */
function errorResponse($message = 'Error', $statusCode = 400, $errors = null)
{
    $response = [
        'success' => false,
        'message' => $message
    ];

    if ($errors !== null) {
        $response['errors'] = $errors;
    }

    jsonResponse($response, $statusCode);
}

/**
 * Validate API token
 */
function validateApiToken($token = null)
{
    if ($token === null) {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_GET['token'] ?? $_POST['token'] ?? null;

        // Extract Bearer token
        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }
    }

    if (empty($token)) {
        return false;
    }

    $db = Database::getInstance();
    $apiToken = $db->fetch(
        "SELECT at.*, d.device_id, d.status 
         FROM api_tokens at 
         JOIN devices d ON at.device_id = d.id 
         WHERE at.token = ? AND at.is_active = 1",
        [$token]
    );

    if ($apiToken) {
        // Update last used
        $db->execute(
            "UPDATE api_tokens SET last_used = NOW(), usage_count = usage_count + 1 WHERE id = ?",
            [$apiToken['id']]
        );

        return $apiToken;
    }

    return false;
}

// =============================================================================
// NODEJS INTEGRATION FUNCTIONS
// =============================================================================

/**
 * Send request to Node.js backend
 */
function sendNodeJSRequest($endpoint, $data = null, $method = 'GET')
{
    $baseUrl = config('nodejs.base_url');
    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => config('nodejs.timeout'),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . config('nodejs.api_key', ''),
            'X-Webhook-Secret: ' . config('nodejs.webhook_secret')
        ]
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        logError("Node.js request failed: $error", ['url' => $url, 'method' => $method]);
        return false;
    }

    $decodedResponse = json_decode($response, true);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status_code' => $httpCode,
        'data' => $decodedResponse,
        'raw_response' => $response
    ];
}

// =============================================================================
// DEVICE FUNCTIONS
// =============================================================================

/**
 * Get device status color
 */
function getDeviceStatusColor($status)
{
    switch ($status) {
        case DEVICE_STATUS_CONNECTED:
            return 'success';
        case DEVICE_STATUS_CONNECTING:
        case DEVICE_STATUS_PAIRING:
            return 'warning';
        case DEVICE_STATUS_BANNED:
        case DEVICE_STATUS_ERROR:
        case DEVICE_STATUS_AUTH_FAILURE:
            return 'danger';
        case DEVICE_STATUS_DISCONNECTED:
        case DEVICE_STATUS_LOGOUT:
            return 'secondary';
        case DEVICE_STATUS_TIMEOUT:
            return 'info';
        default:
            return 'secondary';
    }
}

/**
 * Get device status text
 */
function getDeviceStatusText($status)
{
    switch ($status) {
        case DEVICE_STATUS_CONNECTED:
            return 'Connected';
        case DEVICE_STATUS_CONNECTING:
            return 'Connecting';
        case DEVICE_STATUS_DISCONNECTED:
            return 'Disconnected';
        case DEVICE_STATUS_PAIRING:
            return 'Waiting for QR Scan';
        case DEVICE_STATUS_BANNED:
            return 'Banned';
        case DEVICE_STATUS_ERROR:
            return 'Error';
        case DEVICE_STATUS_TIMEOUT:
            return 'Timeout';
        case DEVICE_STATUS_AUTH_FAILURE:
            return 'Authentication Failed';
        case DEVICE_STATUS_LOGOUT:
            return 'Logged Out';
        default:
            return ucfirst($status);
    }
}

/**
 * Get user devices count
 */
function getUserDevicesCount($userId)
{
    $db = Database::getInstance();
    $result = $db->fetch(
        "SELECT COUNT(*) as count FROM devices WHERE user_id = ?",
        [$userId]
    );

    return $result['count'] ?? 0;
}

/**
 * Check if user can add more devices
 */
function canAddDevice($userId)
{
    $currentCount = getUserDevicesCount($userId);
    $maxDevices = config('users.max_devices_per_user');

    return $currentCount < $maxDevices;
}

// =============================================================================
// SECURITY FUNCTIONS
// =============================================================================

/**
 * Hash password
 */
function hashPassword($password)
{
    return password_hash($password, config('security.password_hash'));
}

/**
 * Verify password
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Generate API token
 */
function generateApiToken($length = API_KEY_LENGTH)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Rate limit check
 */
function checkRateLimit($key, $maxRequests = null, $windowSize = null)
{
    if (!config('security.rate_limiting')) {
        return true;
    }

    $maxRequests = $maxRequests ?? config('api.rate_limit.max_requests');
    $windowSize = $windowSize ?? config('api.rate_limit.window_size');

    $cacheFile = STORAGE_PATH . '/cache/rate_limit_' . md5($key);
    $now = time();

    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        $data = $data ?: ['requests' => [], 'count' => 0];
    } else {
        $data = ['requests' => [], 'count' => 0];
    }

    // Remove old requests outside the window
    $data['requests'] = array_filter($data['requests'], function ($timestamp) use ($now, $windowSize) {
        return ($now - $timestamp) < $windowSize;
    });

    $data['count'] = count($data['requests']);

    if ($data['count'] >= $maxRequests) {
        return false;
    }

    // Add current request
    $data['requests'][] = $now;
    $data['count']++;

    // Save to cache
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);

    return true;
}

// =============================================================================
// PAGINATION FUNCTIONS
// =============================================================================

/**
 * Calculate pagination
 */
function paginate($totalItems, $currentPage = 1, $perPage = null)
{
    $perPage = $perPage ?? DEFAULT_PAGE_SIZE;
    $currentPage = max(1, (int)$currentPage);

    $totalPages = ceil($totalItems / $perPage);
    $offset = ($currentPage - 1) * $perPage;

    return [
        'current_page' => $currentPage,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
        'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null
    ];
}

/**
 * Generate pagination links
 */
function paginationLinks($pagination, $baseUrl, $params = [])
{
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    // Previous button
    if ($pagination['has_previous']) {
        $url = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $pagination['previous_page']]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }

    // Page numbers
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    if ($start > 1) {
        $url = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => 1]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $url = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $i]));
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">' . $i . '</a></li>';
        }
    }

    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $url = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $pagination['total_pages']]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">' . $pagination['total_pages'] . '</a></li>';
    }

    // Next button
    if ($pagination['has_next']) {
        $url = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $pagination['next_page']]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }

    $html .= '</ul></nav>';

    return $html;
}

// =============================================================================
// MISCELLANEOUS FUNCTIONS
// =============================================================================

/**
 * Debug function (only works in development)
 */
function dd($data)
{
    if (config('app.debug')) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        exit;
    }
}

/**
 * Get client IP address
 */
function getClientIP()
{
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get user agent
 */
function getUserAgent()
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Check if request is POST
 */
function isPostRequest()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 */
function isGetRequest()
{
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get request method
 */
function getRequestMethod()
{
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Memory usage
 */
function getMemoryUsage()
{
    return [
        'current' => formatFileSize(memory_get_usage()),
        'peak' => formatFileSize(memory_get_peak_usage()),
        'limit' => ini_get('memory_limit')
    ];
}

/**
 * Execution time
 */
function getExecutionTime()
{
    return round((microtime(true) - APP_START_TIME) * 1000, 2) . ' ms';
}
