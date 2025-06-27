<?php

/**
 * Application Constants
 * 
 * Define all constants used throughout the application
 * Check if constants are already defined to prevent redefinition warnings
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    die('Direct access not allowed');
}

// =============================================================================
// PATH CONSTANTS
// =============================================================================

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', APP_ROOT . '/config');
}

if (!defined('CLASSES_PATH')) {
    define('CLASSES_PATH', APP_ROOT . '/classes');
}

if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', APP_ROOT . '/includes');
}

if (!defined('PAGES_PATH')) {
    define('PAGES_PATH', APP_ROOT . '/pages');
}

if (!defined('API_PATH')) {
    define('API_PATH', APP_ROOT . '/api');
}

if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', APP_ROOT . '/assets');
}

if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', APP_ROOT . '/assets/uploads');
}

if (!defined('LOGS_PATH')) {
    define('LOGS_PATH', APP_ROOT . '/logs');
}

if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', APP_ROOT . '/storage');
}

if (!defined('VENDOR_PATH')) {
    define('VENDOR_PATH', APP_ROOT . '/vendor');
}

// =============================================================================
// APPLICATION CONSTANTS
// =============================================================================

if (!defined('APP_NAME')) {
    define('APP_NAME', 'WhatsApp Monitor');
}

if (!defined('APP_DESCRIPTION')) {
    define('APP_DESCRIPTION', 'WhatsApp API Monitor & Management System');
}

if (!defined('APP_AUTHOR')) {
    define('APP_AUTHOR', 'WhatsApp Monitor Team');
}

if (!defined('DEFAULT_TIMEZONE')) {
    define('DEFAULT_TIMEZONE', 'Asia/Jakarta');
}

if (!defined('DEFAULT_LOCALE')) {
    define('DEFAULT_LOCALE', 'id');
}

if (!defined('DEFAULT_CURRENCY')) {
    define('DEFAULT_CURRENCY', 'IDR');
}

// =============================================================================
// SECURITY CONSTANTS
// =============================================================================

if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', '_token');
}

if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'whatsapp_monitor_session');
}

if (!defined('API_KEY_LENGTH')) {
    define('API_KEY_LENGTH', 64);
}

if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}

if (!defined('TOKEN_EXPIRE_HOURS')) {
    define('TOKEN_EXPIRE_HOURS', 24);
}

// =============================================================================
// DATABASE CONSTANTS
// =============================================================================

if (!defined('DB_TABLE_PREFIX')) {
    define('DB_TABLE_PREFIX', '');
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('DB_COLLATION')) {
    define('DB_COLLATION', 'utf8mb4_unicode_ci');
}

// =============================================================================
// API CONSTANTS
// =============================================================================

if (!defined('API_VERSION')) {
    define('API_VERSION', 'v1');
}

if (!defined('API_PREFIX')) {
    define('API_PREFIX', '/api/' . API_VERSION);
}

if (!defined('API_RATE_LIMIT')) {
    define('API_RATE_LIMIT', 1000);
}

if (!defined('API_RATE_WINDOW')) {
    define('API_RATE_WINDOW', 3600); // 1 hour
}

if (!defined('API_TIMEOUT')) {
    define('API_TIMEOUT', 30);
}

// =============================================================================
// FILE UPLOAD CONSTANTS
// =============================================================================

if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB in bytes
}

if (!defined('ALLOWED_IMAGE_TYPES')) {
    define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
}

if (!defined('ALLOWED_DOCUMENT_TYPES')) {
    define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'txt']);
}

if (!defined('QR_CODE_EXPIRE_MINUTES')) {
    define('QR_CODE_EXPIRE_MINUTES', 5);
}

// =============================================================================
// NODEJS INTEGRATION CONSTANTS
// =============================================================================

if (!defined('NODEJS_DEFAULT_URL')) {
    define('NODEJS_DEFAULT_URL', 'http://localhost:3000');
}

if (!defined('NODEJS_TIMEOUT')) {
    define('NODEJS_TIMEOUT', 30);
}

if (!defined('WEBHOOK_RETRY_ATTEMPTS')) {
    define('WEBHOOK_RETRY_ATTEMPTS', 3);
}

if (!defined('WEBHOOK_RETRY_DELAY')) {
    define('WEBHOOK_RETRY_DELAY', 2000); // milliseconds
}

// =============================================================================
// LOGGING CONSTANTS (only define if not already defined by PHP)
// =============================================================================

if (!defined('LOG_EMERGENCY')) {
    define('LOG_EMERGENCY', 'emergency');
}

if (!defined('LOG_CRITICAL')) {
    define('LOG_CRITICAL', 'critical');
}

if (!defined('LOG_ERROR')) {
    define('LOG_ERROR', 'error');
}

// Only define these if they don't exist as PHP constants
if (!defined('LOG_ALERT')) {
    define('LOG_ALERT', 'alert');
}

if (!defined('LOG_WARNING')) {
    define('LOG_WARNING', 'warning');
}

if (!defined('LOG_NOTICE')) {
    define('LOG_NOTICE', 'notice');
}

if (!defined('LOG_INFO')) {
    define('LOG_INFO', 'info');
}

if (!defined('LOG_DEBUG')) {
    define('LOG_DEBUG', 'debug');
}

// =============================================================================
// DEVICE STATUS CONSTANTS
// =============================================================================

if (!defined('DEVICE_STATUS_CONNECTING')) {
    define('DEVICE_STATUS_CONNECTING', 'connecting');
}

if (!defined('DEVICE_STATUS_CONNECTED')) {
    define('DEVICE_STATUS_CONNECTED', 'connected');
}

if (!defined('DEVICE_STATUS_DISCONNECTED')) {
    define('DEVICE_STATUS_DISCONNECTED', 'disconnected');
}

if (!defined('DEVICE_STATUS_PAIRING')) {
    define('DEVICE_STATUS_PAIRING', 'pairing');
}

if (!defined('DEVICE_STATUS_BANNED')) {
    define('DEVICE_STATUS_BANNED', 'banned');
}

if (!defined('DEVICE_STATUS_ERROR')) {
    define('DEVICE_STATUS_ERROR', 'error');
}

if (!defined('DEVICE_STATUS_TIMEOUT')) {
    define('DEVICE_STATUS_TIMEOUT', 'timeout');
}

if (!defined('DEVICE_STATUS_AUTH_FAILURE')) {
    define('DEVICE_STATUS_AUTH_FAILURE', 'auth_failure');
}

if (!defined('DEVICE_STATUS_LOGOUT')) {
    define('DEVICE_STATUS_LOGOUT', 'logout');
}

// =============================================================================
// USER ROLE CONSTANTS
// =============================================================================

if (!defined('USER_ROLE_ADMIN')) {
    define('USER_ROLE_ADMIN', 'admin');
}

if (!defined('USER_ROLE_OPERATOR')) {
    define('USER_ROLE_OPERATOR', 'operator');
}

if (!defined('USER_ROLE_VIEWER')) {
    define('USER_ROLE_VIEWER', 'viewer');
}

// =============================================================================
// MESSAGE TYPE CONSTANTS
// =============================================================================

if (!defined('MESSAGE_TYPE_TEXT')) {
    define('MESSAGE_TYPE_TEXT', 'text');
}

if (!defined('MESSAGE_TYPE_IMAGE')) {
    define('MESSAGE_TYPE_IMAGE', 'image');
}

if (!defined('MESSAGE_TYPE_VIDEO')) {
    define('MESSAGE_TYPE_VIDEO', 'video');
}

if (!defined('MESSAGE_TYPE_AUDIO')) {
    define('MESSAGE_TYPE_AUDIO', 'audio');
}

if (!defined('MESSAGE_TYPE_DOCUMENT')) {
    define('MESSAGE_TYPE_DOCUMENT', 'document');
}

if (!defined('MESSAGE_TYPE_STICKER')) {
    define('MESSAGE_TYPE_STICKER', 'sticker');
}

if (!defined('MESSAGE_TYPE_LOCATION')) {
    define('MESSAGE_TYPE_LOCATION', 'location');
}

if (!defined('MESSAGE_TYPE_CONTACT')) {
    define('MESSAGE_TYPE_CONTACT', 'contact');
}

// =============================================================================
// HTTP STATUS CONSTANTS
// =============================================================================

if (!defined('HTTP_OK')) {
    define('HTTP_OK', 200);
}

if (!defined('HTTP_CREATED')) {
    define('HTTP_CREATED', 201);
}

if (!defined('HTTP_BAD_REQUEST')) {
    define('HTTP_BAD_REQUEST', 400);
}

if (!defined('HTTP_UNAUTHORIZED')) {
    define('HTTP_UNAUTHORIZED', 401);
}

if (!defined('HTTP_FORBIDDEN')) {
    define('HTTP_FORBIDDEN', 403);
}

if (!defined('HTTP_NOT_FOUND')) {
    define('HTTP_NOT_FOUND', 404);
}

if (!defined('HTTP_METHOD_NOT_ALLOWED')) {
    define('HTTP_METHOD_NOT_ALLOWED', 405);
}

if (!defined('HTTP_UNPROCESSABLE_ENTITY')) {
    define('HTTP_UNPROCESSABLE_ENTITY', 422);
}

if (!defined('HTTP_TOO_MANY_REQUESTS')) {
    define('HTTP_TOO_MANY_REQUESTS', 429);
}

if (!defined('HTTP_INTERNAL_SERVER_ERROR')) {
    define('HTTP_INTERNAL_SERVER_ERROR', 500);
}

if (!defined('HTTP_SERVICE_UNAVAILABLE')) {
    define('HTTP_SERVICE_UNAVAILABLE', 503);
}

// =============================================================================
// CACHE CONSTANTS
// =============================================================================

if (!defined('CACHE_TTL_SHORT')) {
    define('CACHE_TTL_SHORT', 300); // 5 minutes
}

if (!defined('CACHE_TTL_MEDIUM')) {
    define('CACHE_TTL_MEDIUM', 3600); // 1 hour
}

if (!defined('CACHE_TTL_LONG')) {
    define('CACHE_TTL_LONG', 86400); // 24 hours
}

// =============================================================================
// VALIDATION CONSTANTS
// =============================================================================

if (!defined('PHONE_NUMBER_MIN_LENGTH')) {
    define('PHONE_NUMBER_MIN_LENGTH', 10);
}

if (!defined('PHONE_NUMBER_MAX_LENGTH')) {
    define('PHONE_NUMBER_MAX_LENGTH', 15);
}

if (!defined('USERNAME_MIN_LENGTH')) {
    define('USERNAME_MIN_LENGTH', 3);
}

if (!defined('USERNAME_MAX_LENGTH')) {
    define('USERNAME_MAX_LENGTH', 50);
}

if (!defined('DEVICE_NAME_MAX_LENGTH')) {
    define('DEVICE_NAME_MAX_LENGTH', 100);
}

// =============================================================================
// PAGINATION CONSTANTS
// =============================================================================

if (!defined('DEFAULT_PAGE_SIZE')) {
    define('DEFAULT_PAGE_SIZE', 20);
}

if (!defined('MAX_PAGE_SIZE')) {
    define('MAX_PAGE_SIZE', 100);
}

// =============================================================================
// ENVIRONMENT DETECTION
// =============================================================================

if (!defined('IS_DEVELOPMENT')) {
    define('IS_DEVELOPMENT', (($_ENV['APP_ENV'] ?? 'development') === 'development'));
}

if (!defined('IS_PRODUCTION')) {
    define('IS_PRODUCTION', (($_ENV['APP_ENV'] ?? 'development') === 'production'));
}

if (!defined('IS_TESTING')) {
    define('IS_TESTING', (($_ENV['APP_ENV'] ?? 'development') === 'testing'));
}

// =============================================================================
// FEATURE FLAGS
// =============================================================================

if (!defined('FEATURE_REGISTRATION_ENABLED')) {
    define('FEATURE_REGISTRATION_ENABLED', true);
}

if (!defined('FEATURE_EMAIL_VERIFICATION')) {
    define('FEATURE_EMAIL_VERIFICATION', false);
}

if (!defined('FEATURE_AUTO_BACKUP')) {
    define('FEATURE_AUTO_BACKUP', true);
}

if (!defined('FEATURE_MONITORING_ALERTS')) {
    define('FEATURE_MONITORING_ALERTS', true);
}

if (!defined('FEATURE_API_RATE_LIMITING')) {
    define('FEATURE_API_RATE_LIMITING', true);
}

// =============================================================================
// REGEX PATTERNS
// =============================================================================

if (!defined('REGEX_PHONE_NUMBER')) {
    define('REGEX_PHONE_NUMBER', '/^[1-9][0-9]{7,14}$/');
}

if (!defined('REGEX_USERNAME')) {
    define('REGEX_USERNAME', '/^[a-zA-Z0-9_]{3,50}$/');
}

if (!defined('REGEX_EMAIL')) {
    define('REGEX_EMAIL', '/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
}

// =============================================================================
// DATETIME FORMATS
// =============================================================================

if (!defined('DATE_FORMAT')) {
    define('DATE_FORMAT', 'Y-m-d');
}

if (!defined('DATETIME_FORMAT')) {
    define('DATETIME_FORMAT', 'Y-m-d H:i:s');
}

if (!defined('TIME_FORMAT')) {
    define('TIME_FORMAT', 'H:i:s');
}

if (!defined('DISPLAY_DATE_FORMAT')) {
    define('DISPLAY_DATE_FORMAT', 'd/m/Y');
}

if (!defined('DISPLAY_DATETIME_FORMAT')) {
    define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i:s');
}

// =============================================================================
// NOTIFICATION TYPES
// =============================================================================

if (!defined('NOTIFICATION_SUCCESS')) {
    define('NOTIFICATION_SUCCESS', 'success');
}

if (!defined('NOTIFICATION_ERROR')) {
    define('NOTIFICATION_ERROR', 'error');
}

if (!defined('NOTIFICATION_WARNING')) {
    define('NOTIFICATION_WARNING', 'warning');
}

if (!defined('NOTIFICATION_INFO')) {
    define('NOTIFICATION_INFO', 'info');
}

// Create necessary directories if they don't exist
$directories = [
    UPLOADS_PATH,
    LOGS_PATH,
    STORAGE_PATH,
    STORAGE_PATH . '/cache',
    STORAGE_PATH . '/sessions',
    STORAGE_PATH . '/backups',
    UPLOADS_PATH . '/qr_codes',
    UPLOADS_PATH . '/media',
    UPLOADS_PATH . '/temp'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
