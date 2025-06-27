<?php

/**
 * ===============================================================================
 * LOGIN DEBUG TOOL
 * ===============================================================================
 * Tool untuk debug masalah login
 * Akses: http://localhost:8080/wanew/debug-login.php
 * ===============================================================================
 */

define('APP_ROOT', __DIR__);

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Login Debug Tool - WhatsApp Monitor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 10px 0; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; border: 1px solid #bee5eb; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; margin: 5px 0; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .test-form { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0; }
        input, button { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 3px; }
        button { background: #007bff; color: white; cursor: pointer; }
        button:hover { background: #0056b3; }
        h2 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h3 { color: #28a745; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Login Debug Tool - WhatsApp Monitor</h1>";

try {
    echo "<div class='section'>
            <h2>📋 1. File Loading Test</h2>";

    // Test loading files
    $files = [
        'includes/bootstrap.php',
        'includes/functions.php',
        'includes/path-helper.php',
        'classes/Database.php',
        'classes/Auth.php'
    ];

    foreach ($files as $file) {
        $fullPath = APP_ROOT . '/' . $file;
        if (file_exists($fullPath)) {
            echo "<div class='success'>✅ {$file} - EXISTS</div>";
            try {
                require_once $fullPath;
                echo "<div class='success'>✅ {$file} - LOADED</div>";
            } catch (Exception $e) {
                echo "<div class='error'>❌ {$file} - ERROR: {$e->getMessage()}</div>";
            }
        } else {
            echo "<div class='error'>❌ {$file} - NOT FOUND</div>";
        }
    }
    echo "</div>";

    echo "<div class='section'>
            <h2>🗄️ 2. Database Connection Test</h2>";

    try {
        $db = Database::getInstance()->getConnection();
        echo "<div class='success'>✅ Database connection successful</div>";

        // Test database structure
        $tables = ['users', 'devices', 'api_tokens', 'user_sessions'];
        foreach ($tables as $table) {
            try {
                $stmt = $db->query("DESCRIBE {$table}");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<div class='success'>✅ Table '{$table}' exists (" . count($columns) . " columns)</div>";
            } catch (Exception $e) {
                echo "<div class='error'>❌ Table '{$table}' missing or error: {$e->getMessage()}</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Database connection failed: {$e->getMessage()}</div>";
    }
    echo "</div>";

    echo "<div class='section'>
            <h2>👥 3. User Data Check</h2>";

    try {
        $stmt = $db->query("SELECT id, username, email, full_name, role, status, created_at FROM users ORDER BY id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) > 0) {
            echo "<div class='success'>✅ Found " . count($users) . " users in database</div>";
            echo "<table>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>";

            foreach ($users as $user) {
                $statusColor = $user['status'] === 'active' ? '#28a745' : '#dc3545';
                echo "<tr>
                        <td>{$user['id']}</td>
                        <td><strong>{$user['username']}</strong></td>
                        <td>{$user['email']}</td>
                        <td>{$user['full_name']}</td>
                        <td><span style='background: #007bff; color: white; padding: 2px 6px; border-radius: 3px;'>{$user['role']}</span></td>
                        <td><span style='background: {$statusColor}; color: white; padding: 2px 6px; border-radius: 3px;'>{$user['status']}</span></td>
                        <td>{$user['created_at']}</td>
                      </tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ No users found in database</div>";
            echo "<div class='info'>💡 Run this SQL to create admin user:
                    <div class='code'>INSERT INTO users (username, email, password, full_name, role, status, created_at) 
VALUES ('admin', 'admin@localhost', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 'active', NOW());</div>
                  </div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error reading users: {$e->getMessage()}</div>";
    }
    echo "</div>";

    echo "<div class='section'>
            <h2>🔐 4. Password Hash Test</h2>";

    $testPasswords = ['password', 'admin123', '123456'];
    foreach ($testPasswords as $testPass) {
        $hash = password_hash($testPass, PASSWORD_ARGON2ID);
        $verify = password_verify($testPass, $hash);
        echo "<div class='info'>
                Password: <strong>{$testPass}</strong><br>
                Hash: <div class='code'>{$hash}</div>
                Verify: " . ($verify ? "✅ OK" : "❌ FAIL") . "
              </div>";
    }
    echo "</div>";

    echo "<div class='section'>
            <h2>🧪 5. Auth Class Test</h2>";

    try {
        $auth = new Auth();
        echo "<div class='success'>✅ Auth class instantiated successfully</div>";

        // Test with existing users
        if (isset($users) && count($users) > 0) {
            foreach ($users as $user) {
                echo "<div class='info'>
                        <h4>Testing login for user: {$user['username']}</h4>";

                $testPasswords = ['password', 'admin123', '123456'];
                foreach ($testPasswords as $testPass) {
                    $result = $auth->login($user['username'], $testPass);
                    $status = $result['success'] ? "✅ SUCCESS" : "❌ FAIL";
                    echo "<div>Password '{$testPass}': {$status} - {$result['message']}</div>";
                }
                echo "</div>";
                break; // Test only first user
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Auth class error: {$e->getMessage()}</div>";
    }
    echo "</div>";

    echo "<div class='section'>
            <h2>🔧 6. Manual Login Test</h2>";

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_login'])) {
        $testUsername = $_POST['username'] ?? '';
        $testPassword = $_POST['password'] ?? '';

        echo "<div class='info'><h4>Testing login with:</h4>
                Username: <strong>{$testUsername}</strong><br>
                Password: <strong>{$testPassword}</strong>
              </div>";

        try {
            $auth = new Auth();
            $result = $auth->login($testUsername, $testPassword);

            if ($result['success']) {
                echo "<div class='success'>✅ LOGIN SUCCESS: {$result['message']}</div>";
                echo "<div class='info'>Session data: <div class='code'>" . print_r($_SESSION, true) . "</div></div>";
            } else {
                echo "<div class='error'>❌ LOGIN FAILED: {$result['message']}</div>";

                // Additional debugging
                $stmt = $db->prepare("SELECT password FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$testUsername, $testUsername]);
                $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($dbUser) {
                    $passwordMatch = password_verify($testPassword, $dbUser['password']);
                    echo "<div class='info'>Password verification: " . ($passwordMatch ? "✅ MATCH" : "❌ NO MATCH") . "</div>";
                    echo "<div class='info'>Stored hash: <div class='code'>{$dbUser['password']}</div></div>";
                } else {
                    echo "<div class='error'>❌ User not found in database</div>";
                }
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Login test error: {$e->getMessage()}</div>";
        }
    }

    echo "<div class='test-form'>
            <h4>Manual Login Test:</h4>
            <form method='POST'>
                <input type='text' name='username' placeholder='Username' value='admin' required>
                <input type='password' name='password' placeholder='Password' value='password' required>
                <button type='submit' name='test_login'>🔍 Test Login</button>
            </form>
          </div>";
    echo "</div>";

    echo "<div class='section'>
            <h2>⚙️ 7. System Information</h2>";

    echo "<table>
            <tr><th>Item</th><th>Value</th></tr>
            <tr><td>PHP Version</td><td>" . PHP_VERSION . "</td></tr>
            <tr><td>Session Status</td><td>" . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "</td></tr>
            <tr><td>Session ID</td><td>" . session_id() . "</td></tr>
            <tr><td>Current User</td><td>" . (isLoggedIn() ? 'Logged In (' . getCurrentUser()['username'] . ')' : 'Not Logged In') . "</td></tr>
            <tr><td>APP_ROOT</td><td>" . APP_ROOT . "</td></tr>
            <tr><td>Working Directory</td><td>" . getcwd() . "</td></tr>
            <tr><td>Script Name</td><td>" . $_SERVER['SCRIPT_NAME'] . "</td></tr>
            <tr><td>Request URI</td><td>" . $_SERVER['REQUEST_URI'] . "</td></tr>
          </table>";
    echo "</div>";

    echo "<div class='section'>
            <h2>📝 8. Recommendations</h2>";

    echo "<div class='info'>
            <h4>🔧 Quick Fixes:</h4>
            <ol>
                <li>Reset admin password: <a href='reset-password.php' target='_blank'>Open Reset Password Tool</a></li>
                <li>Check if user 'admin' exists and is active</li>
                <li>Try different passwords: 'password', 'admin123', '123456'</li>
                <li>Check database connection and table structure</li>
                <li>Verify file permissions on includes/ and classes/ directories</li>
            </ol>
          </div>";

    echo "<div class='warning'>
            <h4>⚠️ Common Issues:</h4>
            <ul>
                <li>Wrong password hash in database</li>
                <li>User status is 'inactive'</li>
                <li>Session not starting properly</li>
                <li>Auth class not loaded correctly</li>
                <li>Database connection issues</li>
            </ul>
          </div>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Critical Error: {$e->getMessage()}</div>";
    echo "<div class='code'>{$e->getTraceAsString()}</div>";
}

echo "</div></body></html>";
