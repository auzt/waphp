<?php

/**
 * Path Helper Functions
 * 
 * Helper functions untuk menangani path yang benar
 * di berbagai environment (localhost, subdirectory, dll)
 */

/**
 * Get base URL for the application
 */
function getBaseUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = $_SERVER['SCRIPT_NAME'];

    // Extract directory path from script name
    $path = dirname($scriptName);

    // Remove trailing slash except for root
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    return $protocol . '://' . $host . $path;
}

/**
 * Get application base path (for redirects)
 */
function getBasePath()
{
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = dirname($scriptName);

    // For subdirectory installations
    if ($path !== '/') {
        return rtrim($path, '/');
    }

    return '';
}

/**
 * Create URL relative to application root
 */
function url($path = '')
{
    $basePath = getBasePath();
    $path = ltrim($path, '/');

    if (empty($path)) {
        return $basePath ?: '/';
    }

    return $basePath . '/' . $path;
}

/**
 * Redirect to a path relative to application root
 */
function redirectTo($path)
{
    $url = url($path);
    header('Location: ' . $url);
    exit;
}

/**
 * Get asset URL
 */
function asset($path)
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Check if we're in root directory or subdirectory
 */
function isSubdirectoryInstall()
{
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return $path !== '/';
}

/**
 * Get current page relative path
 */
function getCurrentPagePath()
{
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $basePath = getBasePath();

    if ($basePath) {
        return str_replace($basePath, '', $scriptName);
    }

    return $scriptName;
}

/**
 * Get relative path from current location to target
 */
function getRelativePath($target)
{
    $current = dirname($_SERVER['SCRIPT_NAME']);
    $basePath = getBasePath();

    // Calculate how many levels deep we are
    $currentRelative = str_replace($basePath, '', $current);
    $levels = substr_count(trim($currentRelative, '/'), '/');

    if ($levels === 0) {
        // We're at root level
        return ltrim($target, '/');
    } else {
        // We need to go up some levels
        return str_repeat('../', $levels) . ltrim($target, '/');
    }
}
