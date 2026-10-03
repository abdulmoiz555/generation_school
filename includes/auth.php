<?php
/**
 * Authentication and Session Management
 */
require_once __DIR__ . '/../config/config.php';

/**
 * Check if a user is currently logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Enforce login on protected pages
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = "Please log in to access this page.";
        $redirectUrl = (defined('BASE_PATH') ? BASE_PATH : '') . '/login.php';
        header("Location: " . $redirectUrl);
        exit;
    }
}

/**
 * Get current logged in user record
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) {
        return null;
    }
    
    if (isset($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, r.display_name as role_display
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        unset($user['password']); // Never hold password in session
        $_SESSION['user_data'] = $user;
        return $user;
    }
    
    // User deleted or suspended
    logoutUser();
    return null;
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role name
 */
function getCurrentUserRole() {
    return $_SESSION['role_name'] ?? 'guest';
}

/**
 * Attempt login with username or email and password
 */
function loginUser($login, $password, $remember = false) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, r.display_name as role_display
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE (u.username = ? OR u.email = ?)
        LIMIT 1
    ");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account is currently ' . $user['status'] . '. Please contact administration.'];
        }
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['role_display'] = $user['role_display'];
        $_SESSION['avatar'] = $user['avatar'];
        
        unset($user['password']);
        $_SESSION['user_data'] = $user;
        
        // Handle Remember Me token
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$tokenHash, $user['id']]);
            setcookie('edumanage_remember', $user['id'] . ':' . $token, time() + (86400 * 30), "/");
        }
        
        // Audit log
        logAudit('LOGIN', 'Authentication', $user['id'], 'User logged in successfully');
        
        return ['success' => true, 'user' => $user];
    }
    
    return ['success' => false, 'message' => 'Invalid username/email or password.'];
}

/**
 * Log out user and destroy session
 */
function logoutUser() {
    if (isLoggedIn()) {
        logAudit('LOGOUT', 'Authentication', getCurrentUserId(), 'User logged out');
    }
    
    // Clear remember cookie
    if (isset($_COOKIE['edumanage_remember'])) {
        setcookie('edumanage_remember', '', time() - 3600, "/");
    }
    
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Record action in audit log
 */
function logAudit($action, $module, $record_id = null, $details = '') {
    global $pdo;
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $module, $record_id, $details, $ip]);
    } catch (Exception $e) {
        // Silently ignore audit log failures so primary action isn't aborted
        error_log("Audit log failed: " . $e->getMessage());
    }
}
