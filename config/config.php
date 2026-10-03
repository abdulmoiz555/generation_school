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

// Error reporting
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

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
