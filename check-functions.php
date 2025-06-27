<?php

/**
 * Debug file untuk memeriksa fungsi yang sudah didefinisikan
 * Jalankan file ini untuk melihat fungsi mana yang duplikat
 */

define('APP_ROOT', __DIR__);

echo "<h1>Function Conflict Checker</h1>";
echo "<p>Checking for duplicate function definitions...</p>";

// Track functions before including files
$functionsBefore = get_defined_functions()['user'];

echo "<h2>Functions before including any files: " . count($functionsBefore) . "</h2>";

// Include functions.php
echo "<h3>Including functions.php...</h3>";
try {
    require_once APP_ROOT . '/includes/functions.php';
    $functionsAfterInclude = get_defined_functions()['user'];
    $newFunctions = array_diff($functionsAfterInclude, $functionsBefore);
    echo "<p>New functions added: " . count($newFunctions) . "</p>";
    echo "<ul>";
    foreach ($newFunctions as $func) {
        echo "<li>$func</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color: red'>Error including functions.php: " . $e->getMessage() . "</p>";
}

// Include session.php
echo "<h3>Including session.php...</h3>";
try {
    $functionsBeforeSession = get_defined_functions()['user'];
    require_once APP_ROOT . '/includes/session.php';
    $functionsAfterSession = get_defined_functions()['user'];
    $newSessionFunctions = array_diff($functionsAfterSession, $functionsBeforeSession);
    echo "<p>New functions added by session.php: " . count($newSessionFunctions) . "</p>";
    echo "<ul>";
    foreach ($newSessionFunctions as $func) {
        echo "<li>$func</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color: red'>Error including session.php: " . $e->getMessage() . "</p>";
    echo "<p>Error details: " . $e->getFile() . " on line " . $e->getLine() . "</p>";
}

// Check for specific problematic functions
$problematicFunctions = ['getClientIp', 'getUserAgent', 'isLoggedIn', 'getCurrentUser'];
echo "<h3>Checking specific functions:</h3>";
foreach ($problematicFunctions as $func) {
    if (function_exists($func)) {
        $reflection = new ReflectionFunction($func);
        echo "<p><strong>$func</strong>: defined in " . $reflection->getFileName() . " on line " . $reflection->getStartLine() . "</p>";
    } else {
        echo "<p><strong>$func</strong>: not defined</p>";
    }
}

echo "<h3>All user-defined functions:</h3>";
$allFunctions = get_defined_functions()['user'];
sort($allFunctions);
echo "<ol>";
foreach ($allFunctions as $func) {
    try {
        $reflection = new ReflectionFunction($func);
        echo "<li><strong>$func</strong> - " . basename($reflection->getFileName()) . ":" . $reflection->getStartLine() . "</li>";
    } catch (Exception $e) {
        echo "<li><strong>$func</strong> - Error getting info</li>";
    }
}
echo "</ol>";

echo "<h3>System Info:</h3>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Current working directory: " . getcwd() . "</p>";
echo "<p>APP_ROOT: " . APP_ROOT . "</p>";
