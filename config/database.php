<?php

/**
 * Database Configuration
 * 
 * Konfigurasi koneksi database untuk WhatsApp Monitor
 * Mendukung environment variables dan fallback ke default values
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load environment variables if .env file exists
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, ' "\'');
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Database Configuration
$config = [
    // Database Connection Settings
    'database' => [
        'default' => 'mysql',

        'connections' => [
            'mysql' => [
                'driver'    => 'mysql',
                'host'      => $_ENV['DB_HOST'] ?? 'localhost',
                'port'      => $_ENV['DB_PORT'] ?? '3306',
                'database'  => $_ENV['DB_NAME'] ?? 'whatsapp_monitor',
                'username'  => $_ENV['DB_USER'] ?? 'root',
                'password'  => $_ENV['DB_PASS'] ?? '',
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
                'strict'    => true,
                'engine'    => null,
            ]
        ],

        // Connection Options
        'options' => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],

        // Connection Pool Settings
        'pool' => [
            'max_connections' => 10,
            'timeout'        => 30, // seconds
            'retry_attempts' => 3,
            'retry_delay'    => 1   // seconds
        ]
    ],

    // Redis Configuration (untuk session dan cache)
    'redis' => [
        'host'     => $_ENV['REDIS_HOST'] ?? 'localhost',
        'port'     => $_ENV['REDIS_PORT'] ?? 6379,
        'password' => $_ENV['REDIS_PASS'] ?? null,
        'database' => $_ENV['REDIS_DB'] ?? 0,
        'timeout'  => 5.0,
    ],

    // Database Migration Settings
    'migrations' => [
        'table' => 'migrations',
        'path'  => APP_ROOT . '/database/migrations'
    ]
];

// Validate required database settings
$required = ['host', 'database', 'username'];
foreach ($required as $key) {
    if (empty($config['database']['connections']['mysql'][$key])) {
        throw new Exception("Database configuration missing: {$key}");
    }
}

// Export configuration
return $config;
