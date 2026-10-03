<?php
/**
 * Users, Roles & Account Security (Section 6 & 7)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('settings_manage');

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

// Add User
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $uname = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pwd = trim($_POST['password'] ?? '');
    $roleId = (int)$_POST['role_id'];
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($uname) || empty($email) || empty($pwd) || empty($fullName)) {
        $error = "Please fill in all mandatory fields.";
    } else {
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$uname, $email, $hash, $roleId, $fullName, $phone]);
            logAudit('ADD_USER', 'Users', (int)$pdo->lastInsertId(), "Created system user {$uname}");
            setFlashMessage('success', "User '{$fullName}' registered successfully!");
            header("Location: " . BASE_PATH . "/admin/users.php");
            exit;
        } catch (Exception $e) {
            $error = "Username or email is already taken.";
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

// Fetch all system users
$users = $pdo->query("
    SELECT u.*, r.display_name as role_display
    FROM users u
    JOIN roles r ON u.role_id = r.id
    ORDER BY u.role_id ASC, u.id ASC
")->fetchAll();

$pageTitle = "Users & Roles Management";
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
    </div>
    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus me-1"></i> + Create System User
    </button>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Assigned Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-initials me-2" style="width: 34px; height: 34px;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <span class="fw-bold text-dark"><?= e($u['full_name']) ?></span>
                                </div>
                            </td>
                            <td><code><?= e($u['username']) ?></code></td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill">
                                    <?= e($u['role_display']) ?>
                                </span>
                            </td>
                            <td><?= e($u['phone'] ?: '-') ?></td>
                            <td><span class="badge badge-soft-success"><?= ucfirst($u['status']) ?></span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#resetModal" onclick="document.getElementById('resetUserId').value = '<?= $u['id'] ?>'; document.getElementById('resetUserName').textContent = '<?= e($u['full_name']) ?>';">
                                    <i class="fas fa-key me-1"></i> Reset Pwd
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Create System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/admin/users.php" method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="User Full Name" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="username" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= e($r['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="user@school.edu" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Initial Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+1 (555) ...">
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Reset User Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/admin/users.php" method="POST">
                <input type="hidden" name="action" value="reset_pwd">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-body p-4">
                    <p class="text-muted small">Enter a new secure password for <strong id="resetUserName" class="text-dark">User</strong>:</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" value="admin123" required>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark">Confirm Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
