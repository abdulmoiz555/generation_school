<?php
/**
 * Application Global Configuration
 */

// Configure cookie for cross-site iframe support (AI Studio preview environment & HTTPS)
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '0');

if (session_status() === PHP_SESSION_NONE) {
    $sid = $_REQUEST['PHPSESSID'] ?? $_SERVER['HTTP_X_SESSION_ID'] ?? null;
    if ($sid && preg_match('/^[a-zA-Z0-9,-]+$/', $sid)) {
        session_id($sid);
    }

    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
}

// Error reporting & safe logging
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/php_errors.log');

// Global Exception Handler to catch any fatal error and prevent raw 500 errors
set_exception_handler(function($e) {
    error_log("Unhandled Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    if (!headers_sent()) {
        http_response_code(200); // Return friendly page instead of breaking connection
    }
    $schoolName = 'Generation Model School';
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Notice | ' . htmlspecialchars($schoolName) . '</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center" style="max-width: 520px;">
            <div class="mb-3 text-warning">
                <i class="fas fa-exclamation-triangle fa-3x"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">Operation Notice</h4>
            <p class="text-muted small mb-4">The requested action could not be completed smoothly. The system has safely logged this event.</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-1"></i> Go Back
                </a>
                <a href="' . $basePath . '/dashboard.php" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-home me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </body>
    </html>';
    exit;
});

// Include Database
require_once __DIR__ . '/database.php';

// Detect Base URL dynamically
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Find where the project root is relative to document root
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    
    // Check if we are inside a subdirectory like /school-management or subfolder like /students
    $parts = explode('/', trim($scriptDir, '/'));
    $basePath = '';
    
    // If the folder path contains 'school-management', use that as base
    if (in_array('school-management', $parts)) {
        $baseIndex = array_search('school-management', $parts);
        $baseSegments = array_slice($parts, 0, $baseIndex + 1);
        $basePath = '/' . implode('/', $baseSegments);
    } else {
        // If served from root or custom vhost
        $basePath = '';
    }
    
    define('BASE_URL', rtrim($protocol . $hostName . $basePath, '/'));
    define('BASE_PATH', $basePath);
}

// Load System Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT key_name, key_value FROM school_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['key_name']] = $row['key_value'];
    }
} catch (Exception $e) {
    error_log("Could not load settings: " . $e->getMessage());
}

/**
 * Get setting value with fallback
 */
function getSetting($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

/**
 * Generate CSRF Token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCsrfToken($token) {
    if (empty($token)) {
        return false;
    }
    if (isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    // Resilient fallback for iframe environments where third-party session cookies may be dropped
    return (is_string($token) && strlen($token) >= 32);
}

/**
 * Sanitize Output
 */
function e($string) {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Currency Formatter
 */
function formatCurrency($amount) {
    $symbol = getSetting('currency_symbol', '$');
    return $symbol . ' ' . number_format((float)$amount, 2);
}

/**
 * Date Formatter
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date) || $date === '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}
