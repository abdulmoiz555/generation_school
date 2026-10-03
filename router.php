<?php
/**
 * PHP Built-in Server Router
 * Handles clean URLs, static assets, and subfolder routing for both / and /school-management/
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Strip /school-management prefix if accessed that way
if (strpos($uri, '/school-management') === 0) {
    $uri = substr($uri, strlen('/school-management'));
    if ($uri === '' || $uri === false) {
        $uri = '/';
    }
}

$filePath = __DIR__ . $uri;

// If it's a directory, look for index.php or index.html
if (is_dir($filePath)) {
    if (file_exists(rtrim($filePath, '/') . '/index.php')) {
        require rtrim($filePath, '/') . '/index.php';
        return true;
    }
}

// If file exists and is not a PHP file, let the webserver serve it directly
if (file_exists($filePath) && !is_dir($filePath)) {
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    if ($ext !== 'php') {
        return false; // serve as static asset
    } else {
        require $filePath;
        return true;
    }
}

// Fallback to index.php
require __DIR__ . '/index.php';
return true;
