<?php

/**
 * ===============================================================================
 * PASSWORD RESET SCRIPT
 * ===============================================================================
 * Script untuk reset password user tanpa GUI
 * Jalankan: php reset-password.php
 * Atau akses via browser: http://localhost:8080/wanew/reset-password.php
 * ===============================================================================
 */

define('APP_ROOT', __DIR__);

// Load required files
require_once APP_ROOT . '/includes/bootstrap.php';

// Cek apakah dijalankan via CLI atau browser
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    // Jika via browser, tampilkan form
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Password Reset - WhatsApp Monitor</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f8f9fa; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .form-group { margin: 20px 0; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
            button { background: #007bff; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
            button:hover { background: #0056b3; }
            .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb; }
            .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f5c6cb; }
            .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #bee5eb; }
            .users-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .users-table th, .users-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
            .users-table th { background: #f8f9fa; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🔐 Password Reset - WhatsApp Monitor</h1>";
}

try {
    // Koneksi database
    $db = Database::getInstance()->getConnection();

    // Handle form submission atau CLI arguments
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $isCLI) {

        if ($isCLI) {
            // Mode CLI
            echo "=== PASSWORD RESET SCRIPT ===\n";
            echo "WhatsApp Monitor - Password Reset\n";
            echo "================================\n\n";

            // Tampilkan daftar user
            echo "Daftar User:\n";
            $stmt = $db->query("SELECT id, username, email, full_name, role, status FROM users ORDER BY id");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as $user) {
                echo sprintf(
                    "ID: %d | Username: %s | Email: %s | Name: %s | Role: %s | Status: %s\n",
                    $user['id'],
                    $user['username'],
                    $user['email'],
                    $user['full_name'],
                    $user['role'],
                    $user['status']
                );
            }

            echo "\n";

            // Input dari CLI
            echo "Masukkan Username atau Email: ";
            $handle = fopen("php://stdin", "r");
            $identifier = trim(fgets($handle));

            echo "Masukkan Password Baru: ";
            $newPassword = trim(fgets($handle));

            fclose($handle);
        } else {
            // Mode Browser
            $identifier = trim($_POST['identifier'] ?? '');
            $newPassword = trim($_POST['password'] ?? '');
        }

        if (empty($identifier) || empty($newPassword)) {
            $error = "Username/Email dan password baru harus diisi!";
        } else {
            // Reset password
            $result = resetUserPassword($db, $identifier, $newPassword);

            if ($isCLI) {
                if ($result['success']) {
                    echo "\n✅ SUCCESS: " . $result['message'] . "\n";
                } else {
                    echo "\n❌ ERROR: " . $result['message'] . "\n";
                }
            } else {
                if ($result['success']) {
                    echo "<div class='success'>✅ " . htmlspecialchars($result['message']) . "</div>";
                } else {
                    echo "<div class='error'>❌ " . htmlspecialchars($result['message']) . "</div>";
                }
            }
        }
    }

    if (!$isCLI) {
        // Tampilkan form dan daftar user untuk browser

        // Tampilkan daftar user
        echo "<div class='info'>
                <h3>📋 Daftar User yang Tersedia:</h3>
                <table class='users-table'>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Nama Lengkap</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>";

        $stmt = $db->query("SELECT id, username, email, full_name, role, status FROM users ORDER BY id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $user) {
            echo "<tr>
                    <td>{$user['id']}</td>
                    <td>{$user['username']}</td>
                    <td>{$user['email']}</td>
                    <td>{$user['full_name']}</td>
                    <td><span style='background: #007bff; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px;'>{$user['role']}</span></td>
                    <td><span style='background: " . ($user['status'] === 'active' ? '#28a745' : '#dc3545') . "; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px;'>{$user['status']}</span></td>
                  </tr>";
        }

        echo "</table></div>";

        // Form reset password
        echo "<form method='POST'>
                <div class='form-group'>
                    <label for='identifier'>Username atau Email:</label>
                    <input type='text' id='identifier' name='identifier' placeholder='Masukkan username atau email' required>
                </div>
                
                <div class='form-group'>
                    <label for='password'>Password Baru:</label>
                    <input type='password' id='password' name='password' placeholder='Masukkan password baru' required>
                </div>
                
                <div class='form-group'>
                    <button type='submit'>🔄 Reset Password</button>
                </div>
              </form>";

        // Quick reset buttons untuk user default
        echo "<div class='info'>
                <h3>🚀 Quick Reset (Default Users):</h3>
                <form method='POST' style='display: inline-block; margin-right: 10px;'>
                    <input type='hidden' name='identifier' value='admin'>
                    <input type='hidden' name='password' value='password'>
                    <button type='submit' style='background: #28a745;'>Reset Admin → password</button>
                </form>
                
                <form method='POST' style='display: inline-block;'>
                    <input type='hidden' name='identifier' value='admin'>
                    <input type='hidden' name='password' value='admin123'>
                    <button type='submit' style='background: #ffc107; color: black;'>Reset Admin → admin123</button>
                </form>
              </div>";

        echo "<div class='info'>
                <h3>📝 Informasi:</h3>
                <ul>
                    <li>Script ini untuk reset password user tanpa melalui GUI</li>
                    <li>Anda bisa menggunakan username atau email untuk identifikasi user</li>
                    <li>Password akan di-hash menggunakan Argon2ID untuk keamanan</li>
                    <li>Setelah reset, user bisa login dengan password baru</li>
                </ul>
              </div>";

        echo "</div></body></html>";
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();

    if ($isCLI) {
        echo "❌ ERROR: " . $error . "\n";
    } else {
        echo "<div class='error'>❌ " . htmlspecialchars($error) . "</div>";
        echo "</div></body></html>";
    }
}

/**
 * Function untuk reset password user
 */
function resetUserPassword($db, $identifier, $newPassword)
{
    try {
        // Cari user berdasarkan username atau email
        $stmt = $db->prepare("
            SELECT id, username, email, full_name 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'success' => false,
                'message' => "User dengan username/email '{$identifier}' tidak ditemukan!"
            ];
        }

        // Validasi password
        if (strlen($newPassword) < 6) {
            return [
                'success' => false,
                'message' => "Password minimal 6 karakter!"
            ];
        }

        // Hash password baru
        $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);

        // Update password di database
        $stmt = $db->prepare("
            UPDATE users 
            SET password = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $user['id']]);

        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, data, ip_address, created_at) 
                VALUES (?, 'password_reset', ?, ?, NOW())
            ");
            $stmt->execute([
                $user['id'],
                json_encode(['reset_method' => 'script', 'identifier' => $identifier]),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Exception $e) {
            // Ignore logging error
        }

        return [
            'success' => true,
            'message' => "Password untuk user '{$user['username']}' ({$user['full_name']}) berhasil direset!"
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Error: " . $e->getMessage()
        ];
    }
}

if ($isCLI) {
    echo "\nScript selesai.\n";
}
