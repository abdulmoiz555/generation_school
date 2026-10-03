<?php
/**
 * Role-Based Access Control and Permission Checking
 */
require_once __DIR__ . '/auth.php';

// Cache permissions for session
if (!isset($_SESSION['user_permissions']) && isLoggedIn()) {
    refreshUserPermissions();
}

/**
 * Reload permissions for the current user from database
 */
function refreshUserPermissions() {
    global $pdo;
    $roleId = $_SESSION['role_id'] ?? null;
    if (!$roleId) {
        $_SESSION['user_permissions'] = [];
        return;
    }
    
    // Super Admin has all permissions
    if ($roleId == 1) {
        $stmt = $pdo->query("SELECT code FROM permissions");
        $_SESSION['user_permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT p.code 
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
    ");
    $stmt->execute([$roleId]);
    $_SESSION['user_permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Check if the currently logged-in user possesses a permission code
 */
function hasPermission($permissionCode) {
    if (!isLoggedIn()) return false;
    
    // Super Admin has unrestricted access
    if (($_SESSION['role_id'] ?? 0) == 1) {
        return true;
    }
    
    if (!isset($_SESSION['user_permissions'])) {
        refreshUserPermissions();
    }
    
    return in_array($permissionCode, $_SESSION['user_permissions'] ?? []);
}

/**
 * Check if current user has any of the listed roles
 */
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    $currentRole = $_SESSION['role_name'] ?? '';
    
    if (is_array($roles)) {
        return in_array($currentRole, $roles);
    }
    return $currentRole === $roles;
}

/**
 * Enforce permission on a page
 */
function requirePermission($permissionCode) {
    requireLogin();
    
    if (!hasPermission($permissionCode)) {
        http_response_code(403);
        $title = "Access Denied";
        require_once __DIR__ . '/header.php';
        echo '<div class="container-fluid py-5">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">
                    <div class="card border-0 shadow-sm p-5 rounded-4">
                        <div class="display-1 text-danger mb-3"><i class="fas fa-shield-alt"></i></div>
                        <h2 class="fw-bold text-dark">Access Denied (403)</h2>
                        <p class="text-muted fs-5">You do not have the required permission (<code>' . e($permissionCode) . '</code>) to access this page.</p>
                        <div class="mt-4">
                            <a href="' . (defined('BASE_PATH') ? BASE_PATH : '') . '/dashboard.php" class="btn btn-primary px-4 py-2 rounded-pill"><i class="fas fa-home me-2"></i>Return to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        require_once __DIR__ . '/footer.php';
        exit;
    }
}

// Role Helper Functions
function isSuperAdmin() { return hasRole('superadmin'); }
function isAdmin() { return hasRole(['superadmin', 'admin']); }
function isAccountant() { return hasRole(['superadmin', 'accountant']); }
function isTeacher() { return hasRole(['superadmin', 'teacher']); }
function isReceptionist() { return hasRole(['superadmin', 'receptionist']); }
function isLibrarian() { return hasRole(['superadmin', 'librarian']); }
function isParent() { return hasRole('parent'); }
function isStudent() { return hasRole('student'); }
