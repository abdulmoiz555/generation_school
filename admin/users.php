<?php
/**
 * Users, Roles & Account Security
 * Complete User Management with Role Access Control
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('settings_manage');

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

$error = '';

// Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $uname = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pwd = trim($_POST['password'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 2);
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($uname) || empty($email) || empty($pwd) || empty($fullName)) {
        $error = "Please fill in all mandatory fields (Name, Username, Email, Password).";
    } else {
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$uname, $email, $hash, $roleId, $fullName, $phone, $status]);
            $newId = (int)$pdo->lastInsertId();
            logAudit('ADD_USER', 'Users', $newId, "Created system user {$uname} ({$fullName}) with role ID {$roleId}");
            setFlashMessage('success', "User '{$fullName}' registered successfully with role access!");
            header("Location: " . BASE_PATH . "/admin/users.php");
            exit;
        } catch (Exception $e) {
            $error = "Failed to add user: Username or email is already taken in the system.";
        }
    }
}

// Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $uId = (int)($_POST['user_id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 2);
    $status = $_POST['status'] ?? 'active';
    $newPwd = trim($_POST['password'] ?? '');

    if (empty($fullName) || empty($email) || $uId <= 0) {
        $error = "Please provide valid user details.";
    } else {
        try {
            if (!empty($newPwd)) {
                $hash = password_hash($newPwd, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?, status = ?, password = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $phone, $roleId, $status, $hash, $uId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $phone, $roleId, $status, $uId]);
            }
            logAudit('EDIT_USER', 'Users', $uId, "Updated profile for user ID {$uId} ({$fullName})");
            setFlashMessage('success', "User '{$fullName}' updated successfully!");
            header("Location: " . BASE_PATH . "/admin/users.php");
            exit;
        } catch (Exception $e) {
            $error = "Update failed: Email may already be registered to another user.";
        }
    }
}

// Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_pwd') {
    $uId = (int)$_POST['user_id'];
    $newPwd = trim($_POST['new_password'] ?? 'admin123');
    $hash = password_hash($newPwd, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hash, $uId]);
    logAudit('RESET_PASSWORD', 'Users', $uId, "Reset password for user ID {$uId}");
    setFlashMessage('success', "Password successfully reset!");
    header("Location: " . BASE_PATH . "/admin/users.php");
    exit;
}

// Toggle User Status
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $uId = (int)$_GET['id'];
    $curStatus = $_GET['current'] ?? 'active';
    $nextStatus = ($curStatus === 'active') ? 'suspended' : 'active';

    if ($uId !== (int)getCurrentUserId()) {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$nextStatus, $uId]);
        logAudit('TOGGLE_USER_STATUS', 'Users', $uId, "Changed status of user ID {$uId} to {$nextStatus}");
        setFlashMessage('success', "User status updated to " . ucfirst($nextStatus));
    } else {
        setFlashMessage('error', "You cannot suspend your own active account.");
    }
    header("Location: " . BASE_PATH . "/admin/users.php");
    exit;
}

// Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $uId = (int)$_POST['user_id'];
    if ($uId === (int)getCurrentUserId()) {
        setFlashMessage('error', "You cannot delete your own logged-in account.");
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$uId]);
        logAudit('DELETE_USER', 'Users', $uId, "Deleted user ID {$uId}");
        setFlashMessage('success', "User account deleted successfully.");
    }
    header("Location: " . BASE_PATH . "/admin/users.php");
    exit;
}

// Filters & Search
$roleFilter = (int)($_GET['role_id'] ?? 0);
$searchQ = trim($_GET['q'] ?? '');

$sql = "
    SELECT u.*, r.display_name as role_display, r.name as role_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE 1=1
";
$params = [];

if ($roleFilter > 0) {
    $sql .= " AND u.role_id = ?";
    $params[] = $roleFilter;
}

if (!empty($searchQ)) {
    $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%{$searchQ}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY u.role_id ASC, u.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// User Statistics
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$adminUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role_id IN (1, 2)")->fetchColumn();
$teacherUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 4")->fetchColumn();

$pageTitle = "Users & Access Control";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Settings</li>
                <li class="breadcrumb-item active">Users</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">System Users & Role-Based Access</h3>
        <p class="text-muted small mb-0">Add any user type to the system, configure roles, and manage credentials</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= BASE_PATH ?>/admin/roles.php" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-user-shield me-1"></i> Roles & Responsibilities
        </a>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-1"></i> + Add Any User to System
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 shadow-sm" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Quick Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="rounded-3 p-3 bg-primary-subtle text-primary me-3">
                    <i class="fas fa-users fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Accounts</div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalUsers ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="rounded-3 p-3 bg-success-subtle text-success me-3">
                    <i class="fas fa-user-check fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Active Accounts</div>
                    <div class="fs-4 fw-bold text-success"><?= $activeUsers ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="rounded-3 p-3 bg-danger-subtle text-danger me-3">
                    <i class="fas fa-user-shield fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Administrators</div>
                    <div class="fs-4 fw-bold text-danger"><?= $adminUsers ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="rounded-3 p-3 bg-info-subtle text-info me-3">
                    <i class="fas fa-chalkboard-teacher fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Teachers</div>
                    <div class="fs-4 fw-bold text-info"><?= $teacherUsers ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= BASE_PATH ?>/admin/users.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search by name, username, email, or phone..." value="<?= e($searchQ) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="role_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- All Roles (<?= count($roles) ?> available) --</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $roleFilter == $r['id'] ? 'selected' : '' ?>>
                            <?= e($r['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 flex-grow-1">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <?php if ($roleFilter > 0 || !empty($searchQ)): ?>
                    <a href="<?= BASE_PATH ?>/admin/users.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">System User Directory (<?= count($users) ?> Accounts)</h6>
        <span class="badge bg-light text-dark border">Generation Model School Portal</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User Profile</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Assigned Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-user-slash fs-3 d-block mb-2"></i>
                                No user accounts matching the specified search or filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold me-2 text-white" 
                                             style="width: 36px; height: 36px; background: <?= $u['role_id'] == 1 ? '#ef4444' : ($u['role_id'] == 4 ? '#0ea5e9' : '#3b82f6') ?>;">
                                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;">ID: #<?= $u['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= e($u['username']) ?></code></td>
                                <td><a href="mailto:<?= e($u['email']) ?>" class="text-decoration-none text-muted"><?= e($u['email']) ?></a></td>
                                <td>
                                    <span class="badge <?= $u['role_id'] == 1 ? 'bg-danger-subtle text-danger border border-danger-subtle' : ($u['role_id'] == 4 ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle') ?> px-3 py-1 rounded-pill">
                                        <?= e($u['role_display']) ?>
                                    </span>
                                </td>
                                <td><?= e($u['phone'] ?: '-') ?></td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Active</span>
                                    <?php elseif ($u['status'] === 'suspended'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">Suspended</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill"><?= ucfirst($u['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Edit User Button -->
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                onclick="populateEditUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)"
                                                title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Reset Password -->
                                        <button type="button" class="btn btn-outline-warning text-dark" 
                                                data-bs-toggle="modal" data-bs-target="#resetModal" 
                                                onclick="document.getElementById('resetUserId').value = '<?= $u['id'] ?>'; document.getElementById('resetUserName').textContent = '<?= e($u['full_name']) ?> (<?= e($u['username']) ?>)';"
                                                title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <!-- Toggle Status -->
                                        <?php if ($u['id'] != getCurrentUserId()): ?>
                                            <a href="<?= BASE_PATH ?>/admin/users.php?action=toggle_status&id=<?= $u['id'] ?>&current=<?= $u['status'] ?>" 
                                               class="btn <?= $u['status'] === 'active' ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                               title="<?= $u['status'] === 'active' ? 'Suspend Account' : 'Activate Account' ?>">
                                                <i class="fas <?= $u['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                                            </a>
                                            <!-- Delete User -->
                                            <button type="button" class="btn btn-outline-danger" 
                                                    data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                                    onclick="document.getElementById('deleteUserId').value = '<?= $u['id'] ?>'; document.getElementById('deleteUserName').textContent = '<?= e($u['full_name']) ?>';"
                                                    title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 1. Add User Modal (Enhanced with all features) -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary text-white p-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Add Any User to System</h5>
                        <p class="text-muted small mb-0">Create credentials for teachers, admins, accountants, staff, parents or students</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/admin/users.php" method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Legal Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="newFullName" class="form-control" placeholder="e.g. Prof. Tariq Mahmood" required oninput="autoSuggestUsername(this.value)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-at text-muted"></i></span>
                                <input type="text" name="username" id="newUsername" class="form-control" placeholder="tariq_mahmood" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Official Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="newEmail" class="form-control" placeholder="user@generation.edu.pk" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="+92 300 1234567">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">System Access Role <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= $r['name'] === 'teacher' ? 'selected' : '' ?>>
                                        <?= e($r['display_name']) ?> (<?= e($r['description']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Initial Account Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active (Immediate Access)</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">User Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="password" id="newUserPassword" class="form-control font-monospace" placeholder="Enter password or click Generate" required value="admin123">
                                <button type="button" class="btn btn-outline-secondary" onclick="generateRandomPassword('newUserPassword')">
                                    <i class="fas fa-magic me-1"></i> Generate
                                </button>
                            </div>
                            <div class="form-text small text-muted">Default is set to <code>admin123</code>. You can generate a strong unique password.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-check me-1"></i> Create User Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold mb-0"><i class="fas fa-user-edit text-primary me-2"></i> Edit User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/admin/users.php" method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Legal Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="editFullName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username (Read-Only)</label>
                            <input type="text" id="editUsername" class="form-control bg-light" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Official Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="editEmail" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact Phone Number</label>
                            <input type="text" name="phone" id="editPhone" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">System Access Role <span class="text-danger">*</span></label>
                            <select name="role_id" id="editRoleId" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= e($r['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Account Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">Change Password (Leave blank to keep current)</label>
                            <div class="input-group">
                                <input type="text" name="password" id="editUserPassword" class="form-control font-monospace" placeholder="Leave empty to retain existing password">
                                <button type="button" class="btn btn-outline-secondary" onclick="generateRandomPassword('editUserPassword')">
                                    <i class="fas fa-magic me-1"></i> Generate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-save me-1"></i> Update User Details
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-warning-subtle">
                <h5 class="modal-title fw-bold text-dark mb-0"><i class="fas fa-key text-warning me-2"></i> Reset User Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/admin/users.php" method="POST">
                <input type="hidden" name="action" value="reset_pwd">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-body p-4">
                    <p class="text-muted small">Enter a new secure password for <strong id="resetUserName" class="text-dark">User</strong>:</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password</label>
                        <div class="input-group">
                            <input type="text" name="new_password" id="resetInputPassword" class="form-control font-monospace" value="admin123" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="generateRandomPassword('resetInputPassword')">
                                <i class="fas fa-magic me-1"></i> Generate
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">Confirm Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-danger-subtle">
                <h5 class="modal-title fw-bold text-danger mb-0"><i class="fas fa-trash-alt me-2"></i> Delete User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/admin/users.php" method="POST">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5 class="fw-bold">Are you sure?</h5>
                    <p class="text-muted small mb-0">You are about to permanently delete user <strong id="deleteUserName"></strong>. This action cannot be reversed.</p>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Delete Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function autoSuggestUsername(name) {
    if (!name) return;
    const clean = name.toLowerCase().replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
    const userField = document.getElementById('newUsername');
    if (userField && !userField.value.trim()) {
        userField.value = clean;
    }
}

function generateRandomPassword(targetId) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    let pwd = '';
    for (let i = 0; i < 10; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const target = document.getElementById(targetId);
    if (target) {
        target.value = pwd;
        target.focus();
    }
}

function populateEditUser(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editFullName').value = user.full_name;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editPhone').value = user.phone || '';
    document.getElementById('editRoleId').value = user.role_id;
    document.getElementById('editStatus').value = user.status;
    document.getElementById('editUserPassword').value = '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
