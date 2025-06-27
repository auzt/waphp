<?php

/**
 * Test Path Helper Functions
 * 
 * File untuk testing path helper functions
 * Akses: http://localhost:8080/wanew/test-paths.php
 */

define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/includes/path-helper.php';

?>
<!DOCTYPE html>
<html>

<head>
    <title>Path Helper Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f8f9fa;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #bee5eb;
        }

        .code {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
            font-family: monospace;
            margin: 5px 0;
        }

        h1 {
            color: #007bff;
        }

        h3 {
            color: #28a745;
            margin-top: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #dee2e6;
            text-align: left;
        }

        th {
            background: #f8f9fa;
        }

        .success {
            color: #28a745;
            font-weight: bold;
        }

        .test-btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Path Helper Test - WhatsApp Monitor</h1>

        <div class="info">
            <strong>Current URL:</strong> <?= $_SERVER['REQUEST_URI'] ?><br>
            <strong>Script Name:</strong> <?= $_SERVER['SCRIPT_NAME'] ?><br>
            <strong>HTTP Host:</strong> <?= $_SERVER['HTTP_HOST'] ?><br>
            <strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?>
        </div>

        <h3>Path Helper Results:</h3>
        <table>
            <tr>
                <th>Function</th>
                <th>Result</th>
                <th>Description</th>
            </tr>
            <tr>
                <td><code>getBaseUrl()</code></td>
                <td class="code"><?= getBaseUrl() ?></td>
                <td>Full base URL of the application</td>
            </tr>
            <tr>
                <td><code>getBasePath()</code></td>
                <td class="code"><?= getBasePath() ?></td>
                <td>Base path for redirects</td>
            </tr>
            <tr>
                <td><code>url('')</code></td>
                <td class="code"><?= url('') ?></td>
                <td>Application root URL</td>
            </tr>
            <tr>
                <td><code>url('pages/auth/login.php')</code></td>
                <td class="code"><?= url('pages/auth/login.php') ?></td>
                <td>Login page URL</td>
            </tr>
            <tr>
                <td><code>url('pages/dashboard/index.php')</code></td>
                <td class="code"><?= url('pages/dashboard/index.php') ?></td>
                <td>Dashboard URL</td>
            </tr>
            <tr>
                <td><code>asset('css/style.css')</code></td>
                <td class="code"><?= asset('css/style.css') ?></td>
                <td>Asset URL example</td>
            </tr>
            <tr>
                <td><code>isSubdirectoryInstall()</code></td>
                <td class="code"><?= isSubdirectoryInstall() ? 'true' : 'false' ?></td>
                <td>Is installed in subdirectory?</td>
            </tr>
            <tr>
                <td><code>getCurrentPagePath()</code></td>
                <td class="code"><?= getCurrentPagePath() ?></td>
                <td>Current page relative path</td>
            </tr>
        </table>

        <h3>Test Navigation:</h3>
        <a href="<?= url('') ?>" class="test-btn">Home (index.php)</a>
        <a href="<?= url('pages/auth/login.php') ?>" class="test-btn">Login Page</a>
        <a href="<?= url('api/health') ?>" class="test-btn">API Health</a>
        <a href="<?= url('check-functions.php') ?>" class="test-btn">Function Check</a>

        <h3>Server Variables:</h3>
        <table>
            <tr>
                <th>Variable</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>$_SERVER['REQUEST_URI']</td>
                <td class="code"><?= $_SERVER['REQUEST_URI'] ?></td>
            </tr>
            <tr>
                <td>$_SERVER['SCRIPT_NAME']</td>
                <td class="code"><?= $_SERVER['SCRIPT_NAME'] ?></td>
            </tr>
            <tr>
                <td>$_SERVER['HTTP_HOST']</td>
                <td class="code"><?= $_SERVER['HTTP_HOST'] ?></td>
            </tr>
            <tr>
                <td>$_SERVER['SERVER_PORT']</td>
                <td class="code"><?= $_SERVER['SERVER_PORT'] ?></td>
            </tr>
            <tr>
                <td>$_SERVER['DOCUMENT_ROOT']</td>
                <td class="code"><?= $_SERVER['DOCUMENT_ROOT'] ?></td>
            </tr>
            <tr>
                <td>$_SERVER['PHP_SELF']</td>
                <td class="code"><?= $_SERVER['PHP_SELF'] ?></td>
            </tr>
            <tr>
                <td>dirname($_SERVER['SCRIPT_NAME'])</td>
                <td class="code"><?= dirname($_SERVER['SCRIPT_NAME']) ?></td>
            </tr>
            <tr>
                <td>basename(dirname($_SERVER['SCRIPT_NAME']))</td>
                <td class="code"><?= basename(dirname($_SERVER['SCRIPT_NAME'])) ?></td>
            </tr>
        </table>

        <div class="info">
            <strong>✅ Expected Results for XAMPP subdirectory installation:</strong><br>
            • Base Path should be: <code>/wanew</code><br>
            • Login URL should be: <code>/wanew/pages/auth/login.php</code><br>
            • Is Subdirectory should be: <code>true</code><br>
            • Navigation links should work correctly
        </div>
    </div>
</body>

</html>