<?php

/**
 * Application Configuration
 * 
 * Konfigurasi utama aplikasi WhatsApp Monitor
 * Termasuk security, session, logging, dan integration settings
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load .env file if exists
if (file_exists(APP_ROOT . '/.env')) {
    $lines = file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, ' "\'');

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

$config = [
    // Application Basic Settings
    'app' => [
        'name'        => $_ENV['APP_NAME'] ?? 'WhatsApp Monitor',
        'version'     => '1.0.0',
        'environment' => $_ENV['APP_ENV'] ?? 'development', // development, staging, production
        'debug'       => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'url'         => $_ENV['APP_URL'] ?? 'http://localhost',
        'timezone'    => $_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta',
        'locale'      => 'id',
        'fallback_locale' => 'en',
    ],

    // Security Settings
    'security' => [
        'app_key'           => $_ENV['APP_KEY'] ?? 'default-development-key-12345678',
        'encryption_cipher' => 'AES-256-CBC',
        'hash_algo'        => 'sha256',
        'password_hash'    => PASSWORD_ARGON2ID,
        'csrf_token'       => true,
        'csrf_expire'      => 3600, // 1 hour in seconds
        'rate_limiting'    => true,
        'max_login_attempts' => 5,
        'lockout_duration'   => 900, // 15 minutes in seconds
    ],

    // Session Configuration
    'session' => [
        'driver'          => $_ENV['SESSION_DRIVER'] ?? 'file', // file, database, redis
        'lifetime'        => $_ENV['SESSION_LIFETIME'] ?? 3600, // 1 hour in seconds
        'expire_on_close' => false,
        'encrypt'         => true,
        'files'           => APP_ROOT . '/storage/sessions',
        'connection'      => null,
        'table'           => 'user_sessions',
        'store'           => null,
        'lottery'         => [2, 100],
        'cookie'          => [
            'name'     => 'whatsapp_monitor_session',
            'path'     => '/',
            'domain'   => null,
            'secure'   => $_ENV['SESSION_SECURE_COOKIE'] ?? false,
            'httponly' => true,
            'samesite' => 'strict'
        ]
    ],

    // Database Configuration (MySQL)
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver'    => 'mysql',
                'host'      => $_ENV['DB_HOST'] ?? 'localhost',
                'port'      => $_ENV['DB_PORT'] ?? '3306',
                'database'  => $_ENV['DB_DATABASE'] ?? 'whatsapp_monitor',
                'username'  => $_ENV['DB_USERNAME'] ?? 'root',
                'password'  => $_ENV['DB_PASSWORD'] ?? '',
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
                'options'   => [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            ]
        ]
    ],

    // Logging Configuration
    'logging' => [
        'default' => 'daily',
        'channels' => [
            'single' => [
                'driver' => 'single',
                'path'   => APP_ROOT . '/logs/app.log',
                'level'  => $_ENV['LOG_LEVEL'] ?? 'info',
            ],
            'daily' => [
                'driver' => 'daily',
                'path'   => APP_ROOT . '/logs/app.log',
                'level'  => $_ENV['LOG_LEVEL'] ?? 'info',
                'days'   => 7,
            ],
            'api' => [
                'driver' => 'daily',
                'path'   => APP_ROOT . '/logs/api.log',
                'level'  => 'info',
                'days'   => 30,
            ],
            'error' => [
                'driver' => 'daily',
                'path'   => APP_ROOT . '/logs/error.log',
                'level'  => 'error',
                'days'   => 30,
            ]
        ]
    ],

    // File Upload Settings
    'uploads' => [
        'max_size'        => $_ENV['MAX_UPLOAD_SIZE'] ?? '10M', // 10MB
        'allowed_types'   => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt'],
        'qr_codes_path'   => APP_ROOT . '/assets/uploads/qr_codes',
        'media_path'      => APP_ROOT . '/assets/uploads/media',
        'temp_path'       => APP_ROOT . '/assets/uploads/temp',
    ],

    // Node.js Integration Settings
    'nodejs' => [
        'base_url'        => $_ENV['NODEJS_URL'] ?? 'http://localhost:3000',
        'timeout'         => $_ENV['NODEJS_TIMEOUT'] ?? 30,
        'retry_attempts'  => 3,
        'webhook_secret'  => $_ENV['WEBHOOK_SECRET'] ?? 'default-webhook-secret',
        'endpoints' => [
            'devices'        => '/api/devices',
            'messages'       => '/api/messages',
            'session_status' => '/api/session/status',
            'qr_code'        => '/api/session/qr',
            'disconnect'     => '/api/session/disconnect'
        ]
    ],

    // API Configuration
    'api' => [
        'rate_limit' => [
            'max_requests'  => $_ENV['API_RATE_LIMIT'] ?? 1000,
            'window_size'   => 3600, // 1 hour in seconds
            'headers'       => true   // Include rate limit headers in response
        ],
        'pagination' => [
            'default_limit' => 20,
            'max_limit'     => 100
        ],
        'token' => [
            'expire_hours'  => $_ENV['API_TOKEN_EXPIRE'] ?? 8760, // 1 year
            'refresh_days'  => 30
        ]
    ],

    // User Management
    'users' => [
        'max_devices_per_user' => $_ENV['MAX_DEVICES_PER_USER'] ?? 10,
        'default_role'         => 'operator',
        'password_min_length'  => 8,
        'require_email_verify' => false,
        'auto_lockout'         => true
    ],

    // Device Management
    'devices' => [
        'max_inactive_days'    => 30,
        'auto_cleanup'         => true,
        'qr_code_expire'       => 300, // 5 minutes
        'session_timeout'      => 3600, // 1 hour
        'retry_connection'     => 3
    ],

    // Monitoring & Alerts
    'monitoring' => [
        'enable_alerts'        => true,
        'check_interval'       => 60, // seconds
        'offline_threshold'    => 300, // 5 minutes
        'alert_methods'        => ['email', 'webhook'],
        'metrics_retention'    => 30 // days
    ],

    // Cache Configuration
    'cache' => [
        'default' => $_ENV['CACHE_DRIVER'] ?? 'file',
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path'   => APP_ROOT . '/storage/cache',
            ],
            'redis' => [
                'driver'     => 'redis',
                'connection' => 'default',
            ]
        ],
        'prefix' => 'whatsapp_monitor_cache',
        'ttl'    => 3600 // 1 hour default TTL
    ],

    // Mail Configuration (untuk notifications)
    'mail' => [
        'driver'     => $_ENV['MAIL_DRIVER'] ?? 'smtp',
        'host'       => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port'       => $_ENV['MAIL_PORT'] ?? 587,
        'username'   => $_ENV['MAIL_USERNAME'] ?? '',
        'password'   => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@whatsapp-monitor.com',
            'name'    => $_ENV['MAIL_FROM_NAME'] ?? 'WhatsApp Monitor'
        ]
    ],

    // Backup Configuration
    'backup' => [
        'enabled'     => filter_var($_ENV['BACKUP_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'schedule'    => $_ENV['BACKUP_SCHEDULE'] ?? 'daily',
        'path'        => APP_ROOT . '/storage/backups',
        'retention'   => 7, // days
        'include' => [
            'database' => true,
            'files'    => true,
            'logs'     => false
        ]
    ]
];

// Validate critical settings in production
if ($config['app']['environment'] === 'production') {
    if (
        empty($config['security']['app_key']) ||
        $config['security']['app_key'] === 'default-development-key-12345678'
    ) {
        die('
            <h1>Configuration Error</h1>
            <p><strong>APP_KEY must be set in production environment!</strong></p>
            <p>Please:</p>
            <ol>
                <li>Copy <code>.env.example</code> to <code>.env</code></li>
                <li>Generate a secure APP_KEY: <code>openssl rand -base64 32</code></li>
                <li>Set APP_KEY in your .env file</li>
                <li>Set APP_ENV=production in your .env file</li>
            </ol>
        ');
    }

    if (
        empty($config['nodejs']['webhook_secret']) ||
        $config['nodejs']['webhook_secret'] === 'default-webhook-secret'
    ) {
        die('
            <h1>Configuration Error</h1>
            <p><strong>WEBHOOK_SECRET must be set in production environment!</strong></p>
        ');
    }
}

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Configure error reporting based on environment
if ($config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
}

// Create necessary directories
$directories = [
    $config['uploads']['qr_codes_path'],
    $config['uploads']['media_path'],
    $config['uploads']['temp_path'],
    dirname($config['logging']['channels']['single']['path']),
    $config['cache']['stores']['file']['path'],
    $config['session']['files'],
    $config['backup']['path']
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

return $config;
