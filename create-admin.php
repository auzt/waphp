<?php

/**
 * ===============================================================================
 * CREATE ADMIN USER SCRIPT
 * ===============================================================================
 * Script untuk membuat user admin dengan password yang pasti benar
 * Akses: http://localhost:8080/wanew/create-admin.php
 * ===============================================================================
 */

define('APP_ROOT', __DIR__);

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create Admin User - WhatsApp Monitor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #bee5eb; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; margin: 5px 0; white-space: pre-wrap; }
        button { background: #007bff; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        button:hover { background: #0056b3; }
        .danger { background: #dc3545; }
        .danger:hover { background: #c82333; }
        h1 { color: #007bff; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>👑 Create Admin User - WhatsApp Monitor</h1>";

try {
    // Load bootstrap
    require_once APP_ROOT . '/includes/bootstrap.php';

    // Get database connection
    $db = Database::getInstance()->getConnection();

    echo "<div class='success'>✅ Database connection successful</div>";

    // Check existing users
    echo "<h2>📋 Existing Users:</h2>";
    $stmt = $db->query("SELECT id, username, email, full_name, role, status, created_at FROM users ORDER BY id");
    $existingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($existingUsers) > 0) {
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

        foreach ($existingUsers as $user) {
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
        echo "<div class='info'>ℹ️ No users found in database</div>";
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_admin'])) {

            echo "<h2>🔄 Creating Admin User...</h2>";

            // Check if admin already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = 'admin' OR email = 'admin@localhost'");
            $stmt->execute();
            $existingAdmin = $stmt->fetch();

            if ($existingAdmin) {
                echo "<div class='info'>ℹ️ Admin user already exists. Updating password...</div>";

                // Update existing admin
                $newPassword = 'password';
                $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);

                $stmt = $db->prepare("
                    UPDATE users 
                    SET password = ?, 
                        status = 'active',
                        role = 'admin',
                        updated_at = NOW()
                    WHERE username = 'admin' OR email = 'admin@localhost'
                ");
                $stmt->execute([$hashedPassword]);

                echo "<div class='success'>✅ Admin user updated successfully!</div>";
            } else {
                echo "<div class='info'>ℹ️ Creating new admin user...</div>";

                // Create new admin user
                $newPassword = 'password';
                $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);

                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password, full_name, role, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                $stmt->execute([
                    'admin',
                    'admin@localhost',
                    $hashedPassword,
                    'System Administrator',
                    'admin',
                    'active'
                ]);

                echo "<div class='success'>✅ Admin user created successfully!</div>";
            }

            // Verify the password works
            echo "<h3>🔍 Password Verification Test:</h3>";
            $testPassword = 'password';
            $verifyResult = password_verify($testPassword, $hashedPassword);

            if ($verifyResult) {
                echo "<div class='success'>✅ Password verification successful!</div>";
                echo "<div class='info'>
                        <strong>Login Credentials:</strong><br>
                        Username: <code>admin</code><br>
                        Password: <code>password</code>
                      </div>";
            } else {
                echo "<div class='error'>❌ Password verification failed!</div>";
            }

            // Test login
            echo "<h3>🧪 Login Test:</h3>";
            try {
                $auth = new Auth();
                $loginResult = $auth->login('admin', 'password');

                if ($loginResult['success']) {
                    echo "<div class='success'>✅ Login test successful: {$loginResult['message']}</div>";

                    // Show session data
                    echo "<div class='info'>
                            <strong>Session Data:</strong>
                            <div class='code'>" . print_r($_SESSION, true) . "</div>
                          </div>";

                    // Logout for clean state
                    $auth->logout();
                    echo "<div class='info'>ℹ️ Logged out for clean state</div>";
                } else {
                    echo "<div class='error'>❌ Login test failed: {$loginResult['message']}</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Login test error: {$e->getMessage()}</div>";
            }
        } elseif (isset($_POST['delete_all'])) {

            echo "<h2>🗑️ Deleting All Users...</h2>";

            $stmt = $db->prepare("DELETE FROM users");
            $stmt->execute();
            $deletedCount = $stmt->rowCount();

            echo "<div class='success'>✅ Deleted {$deletedCount} users</div>";
        }
    }

    echo "<h2>⚙️ Actions:</h2>";

    echo "<form method='POST' style='display: inline-block;'>
            <button type='submit' name='create_admin'>
                👑 Create/Update Admin User (password: 'password')
            </button>
          </form>";

    echo "<form method='POST' style='display: inline-block;' onsubmit='return confirm(\"Are you sure you want to delete ALL users?\")'>
            <button type='submit' name='delete_all' class='danger'>
                🗑️ Delete All Users
            </button>
          </form>";

    echo "<div class='info'>
            <h3>📝 What this script does:</h3>
            <ol>
                <li>Checks if admin user exists</li>
                <li>Creates new admin user or updates existing one</li>
                <li>Sets password to 'password' with proper Argon2ID hashing</li>
                <li>Ensures user status is 'active' and role is 'admin'</li>
                <li>Tests password verification</li>
                <li>Tests actual login process</li>
            </ol>
            
            <h3>🔐 After running this script:</h3>
            <ul>
                <li>Username: <strong>admin</strong></li>
                <li>Password: <strong>password</strong></li>
                <li>You can login at: <a href='pages/auth/login.php'>pages/auth/login.php</a></li>
            </ul>
          </div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: {$e->getMessage()}</div>";
    echo "<div class='code'>" . $e->getTraceAsString() . "</div>";
}

echo "</div></body></html>";
