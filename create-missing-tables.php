<?php

/**
 * ===============================================================================
 * CREATE MISSING TABLES SCRIPT
 * ===============================================================================
 * Script untuk membuat table-table yang missing di database
 * Akses: http://localhost:8080/wanew/create-missing-tables.php
 * ===============================================================================
 */

define('APP_ROOT', __DIR__);

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create Missing Tables - WhatsApp Monitor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f8f9fa; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeaa7; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; margin: 5px 0; white-space: pre-wrap; font-size: 12px; }
        button { background: #007bff; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        button:hover { background: #0056b3; }
        .danger { background: #dc3545; }
        .danger:hover { background: #c82333; }
        h1 { color: #007bff; }
        h2 { color: #28a745; border-bottom: 2px solid #28a745; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🛠️ Create Missing Tables - WhatsApp Monitor</h1>";

try {
    // Load database
    require_once APP_ROOT . '/includes/bootstrap.php';
    $db = Database::getInstance()->getConnection();

    echo "<div class='success'>✅ Database connection successful</div>";

    // Check existing tables
    echo "<h2>📋 Current Database Tables:</h2>";
    $stmt = $db->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($existingTables) > 0) {
        echo "<div class='info'>Found " . count($existingTables) . " existing tables:<br>";
        foreach ($existingTables as $table) {
            echo "• {$table}<br>";
        }
        echo "</div>";
    } else {
        echo "<div class='warning'>⚠️ No tables found in database</div>";
    }

    // Define required tables with their SQL
    $requiredTables = [
        'users' => "
            CREATE TABLE users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                role ENUM('admin', 'operator', 'viewer') DEFAULT 'operator',
                status ENUM('active', 'inactive') DEFAULT 'active',
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'devices' => "
            CREATE TABLE devices (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                device_name VARCHAR(100) NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                device_id VARCHAR(100) UNIQUE NOT NULL,
                status ENUM('connecting','connected','disconnected','pairing','banned','error','timeout','auth_failure','logout') DEFAULT 'connecting',
                raw_status VARCHAR(50) NULL,
                whatsapp_user_id VARCHAR(100) NULL,
                whatsapp_name VARCHAR(100) NULL,
                qr_code TEXT NULL,
                qr_expires_at TIMESTAMP NULL,
                session_data JSON NULL,
                last_seen TIMESTAMP NULL,
                connected_at TIMESTAMP NULL,
                is_online BOOLEAN DEFAULT FALSE,
                retry_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_device_id (device_id),
                INDEX idx_status (status),
                INDEX idx_user_devices (user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'api_tokens' => "
            CREATE TABLE api_tokens (
                id INT PRIMARY KEY AUTO_INCREMENT,
                device_id INT NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                token_name VARCHAR(100) NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                last_used TIMESTAMP NULL,
                usage_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
                INDEX idx_token (token),
                INDEX idx_device_token (device_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'login_attempts' => "
            CREATE TABLE login_attempts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                success BOOLEAN DEFAULT FALSE,
                INDEX idx_username_time (username, attempt_time),
                INDEX idx_ip_time (ip_address, attempt_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'user_sessions' => "
            CREATE TABLE user_sessions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                session_id VARCHAR(128) UNIQUE NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_session_id (session_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'activity_logs' => "
            CREATE TABLE activity_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                data JSON NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_action (user_id, action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'message_logs' => "
            CREATE TABLE message_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                device_id INT NOT NULL,
                message_id VARCHAR(100) NULL,
                chat_id VARCHAR(100) NOT NULL,
                direction ENUM('incoming', 'outgoing') NOT NULL,
                from_number VARCHAR(100) NOT NULL,
                to_number VARCHAR(100) NOT NULL,
                message_type ENUM('text', 'image', 'video', 'audio', 'document', 'sticker', 'location', 'contact') DEFAULT 'text',
                message_content TEXT NULL,
                media_url VARCHAR(500) NULL,
                status ENUM('sent', 'delivered', 'read', 'failed') DEFAULT 'sent',
                timestamp BIGINT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
                INDEX idx_device_date (device_id, created_at),
                INDEX idx_chat_id (chat_id),
                INDEX idx_direction (direction)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'webhook_logs' => "
            CREATE TABLE webhook_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                device_id INT NULL,
                webhook_type ENUM('incoming', 'outgoing') NOT NULL,
                event_name VARCHAR(50) NOT NULL,
                payload JSON NULL,
                response_code INT NULL,
                response_data JSON NULL,
                success BOOLEAN DEFAULT TRUE,
                error_message TEXT NULL,
                execution_time DECIMAL(5,3) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
                INDEX idx_device_webhook (device_id, webhook_type),
                INDEX idx_event (event_name),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'nodejs_commands' => "
            CREATE TABLE nodejs_commands (
                id INT PRIMARY KEY AUTO_INCREMENT,
                device_id INT NOT NULL,
                command VARCHAR(50) NOT NULL,
                command_data JSON NULL,
                status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                response_data JSON NULL,
                error_message TEXT NULL,
                executed_by INT NULL,
                executed_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
                FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_device_command (device_id, command),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'settings' => "
            CREATE TABLE settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT NOT NULL,
                setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
                description TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'remember_tokens' => "
            CREATE TABLE remember_tokens (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_token (token),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",

        'api_logs' => "
            CREATE TABLE api_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                device_id INT NULL,
                api_key VARCHAR(64) NULL,
                method VARCHAR(10) NOT NULL,
                endpoint VARCHAR(255) NOT NULL,
                request_data JSON NULL,
                response_data JSON NULL,
                status_code INT NOT NULL,
                execution_time DECIMAL(8,3) NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                error_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
                INDEX idx_device_date (device_id, created_at),
                INDEX idx_endpoint (endpoint),
                INDEX idx_status_code (status_code),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        "
    ];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_tables'])) {
            echo "<h2>🔨 Creating Missing Tables...</h2>";

            $createdCount = 0;
            $errorCount = 0;

            foreach ($requiredTables as $tableName => $sql) {
                try {
                    if (!in_array($tableName, $existingTables)) {
                        echo "<div class='info'>Creating table: <strong>{$tableName}</strong></div>";
                        $db->exec($sql);
                        echo "<div class='success'>✅ Table '{$tableName}' created successfully</div>";
                        $createdCount++;
                    } else {
                        echo "<div class='warning'>⚠️ Table '{$tableName}' already exists</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Failed to create table '{$tableName}': {$e->getMessage()}</div>";
                    $errorCount++;
                }
            }

            echo "<div class='info'><strong>Summary:</strong> {$createdCount} tables created, {$errorCount} errors</div>";

            // Insert default settings
            echo "<h3>📝 Inserting Default Settings...</h3>";
            try {
                $defaultSettings = [
                    ['app_name', 'WhatsApp Monitor', 'string', 'Application name'],
                    ['nodejs_url', 'http://localhost:3000', 'string', 'Node.js backend URL'],
                    ['webhook_secret', 'your-secret-key', 'string', 'Webhook secret'],
                    ['max_devices_per_user', '10', 'integer', 'Maximum devices per user'],
                    ['qr_expire_minutes', '5', 'integer', 'QR code expiry in minutes'],
                    ['auto_retry_count', '3', 'integer', 'Auto retry connection attempts'],
                    ['message_retention_days', '30', 'integer', 'Keep message logs for days'],
                    ['webhook_timeout', '30', 'integer', 'Webhook timeout in seconds']
                ];

                foreach ($defaultSettings as $setting) {
                    $stmt = $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute($setting);
                }

                echo "<div class='success'>✅ Default settings inserted</div>";
            } catch (Exception $e) {
                echo "<div class='error'>❌ Failed to insert settings: {$e->getMessage()}</div>";
            }
        } elseif (isset($_POST['drop_all_tables'])) {
            echo "<h2>🗑️ Dropping All Tables...</h2>";

            // Disable foreign key checks
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            $droppedCount = 0;
            foreach ($existingTables as $table) {
                try {
                    $db->exec("DROP TABLE IF EXISTS `{$table}`");
                    echo "<div class='warning'>🗑️ Dropped table: {$table}</div>";
                    $droppedCount++;
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Failed to drop table '{$table}': {$e->getMessage()}</div>";
                }
            }

            // Re-enable foreign key checks
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");

            echo "<div class='info'><strong>Summary:</strong> {$droppedCount} tables dropped</div>";
        }

        // Refresh page to show updated table list
        echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
    }

    // Check missing tables
    echo "<h2>🔍 Missing Tables Analysis:</h2>";
    $missingTables = array_diff(array_keys($requiredTables), $existingTables);

    if (count($missingTables) > 0) {
        echo "<div class='warning'>⚠️ Missing " . count($missingTables) . " tables:<br>";
        foreach ($missingTables as $table) {
            echo "• <strong>{$table}</strong><br>";
        }
        echo "</div>";
    } else {
        echo "<div class='success'>✅ All required tables exist!</div>";
    }

    // Action buttons
    echo "<h2>⚙️ Actions:</h2>";

    if (count($missingTables) > 0) {
        echo "<form method='POST' style='display: inline-block;'>
                <button type='submit' name='create_tables'>
                    🔨 Create Missing Tables (" . count($missingTables) . ")
                </button>
              </form>";
    }

    echo "<form method='POST' style='display: inline-block;' onsubmit='return confirm(\"Are you sure you want to DROP ALL TABLES? This will delete all data!\")'>
            <button type='submit' name='drop_all_tables' class='danger'>
                🗑️ Drop All Tables (DANGER!)
            </button>
          </form>";

    echo "<div class='info'>
            <h3>📝 What this script does:</h3>
            <ol>
                <li>Analyzes current database structure</li>
                <li>Identifies missing tables required by the application</li>
                <li>Creates missing tables with proper structure and indexes</li>
                <li>Inserts default settings data</li>
                <li>Sets up foreign key relationships</li>
            </ol>
            
            <h3>🗂️ Tables that will be created:</h3>
            <ul>";

    foreach (array_keys($requiredTables) as $table) {
        echo "<li><strong>{$table}</strong> - " . getTableDescription($table) . "</li>";
    }

    echo "    </ul>
          </div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: {$e->getMessage()}</div>";
    echo "<div class='code'>" . $e->getTraceAsString() . "</div>";
}

function getTableDescription($table)
{
    $descriptions = [
        'users' => 'User accounts and authentication',
        'devices' => 'WhatsApp devices and sessions',
        'api_tokens' => 'API authentication tokens',
        'login_attempts' => 'Failed login tracking for security',
        'user_sessions' => 'Active user sessions',
        'activity_logs' => 'User activity logging',
        'message_logs' => 'WhatsApp message history',
        'webhook_logs' => 'Webhook execution logs',
        'nodejs_commands' => 'Commands sent to Node.js backend',
        'settings' => 'Application configuration',
        'remember_tokens' => 'Remember me functionality',
        'api_logs' => 'API request logging'
    ];

    return $descriptions[$table] ?? 'Application table';
}

echo "</div></body></html>";
