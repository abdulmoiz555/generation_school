<?php
/**
 * Create New System User
 * Generation Model School
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('settings_manage');

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$error = '';
$success = '';

$fullName = '';
$username = '';
$email = '';
$phone = '';
$roleId = 4; // Default to Teacher
$status = 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 4);
    $status = $_POST['status'] ?? 'active';
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields (Full Name, Username, Email, and Password).";
    } elseif ($password !== $confirmPassword) {
        $error = "The password and confirmation password do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if username or email already exists
        $checkStmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $checkStmt->execute([$username, $email]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ($existing['username'] === $username) {
                $error = "Username '{$username}' is already taken. Please choose another username.";
            } else {
                $error = "Email '{$email}' is already registered with an existing account.";
            }
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role_id, full_name, phone, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$username, $email, $passwordHash, $roleId, $fullName, $phone, $status]);
                $newUserId = (int)$pdo->lastInsertId();

                logAudit('CREATE_USER', 'Users', $newUserId, "Created new user account '{$username}' with role ID {$roleId}");
                setFlashMessage('success', "New user '{$fullName}' ({$username}) has been successfully created and granted system access!");
                header("Location: " . BASE_PATH . "/admin/users.php");
                exit;
            } catch (Exception $e) {
                $error = "Failed to register new user. Error: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Create New User";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/admin/users.php">Users & Roles</a></li>
                <li class="breadcrumb-item active">Create New User</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fas fa-user-plus text-primary me-2"></i>Create New System User
        </h3>
        <p class="text-muted small mb-0">Provision login credentials and assign role-based access for staff, teachers, accountants, or administrators</p>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/admin/users.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to All Users
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Main Registration Form -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold text-dark mb-0">User Account Information</h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_PATH ?>/admin/user-add.php" method="POST" autocomplete="off" id="createUserForm">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Full Legal Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="fullNameInput" class="form-control" placeholder="e.g. Prof. Tariq Mahmood" value="<?= e($fullName) ?>" required autofocus oninput="generateUsernameSuggestion(this.value)">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Username (Unique ID) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-at text-muted"></i></span>
                                <input type="text" name="username" id="usernameInput" class="form-control" placeholder="tariq_mahmood" value="<?= e($username) ?>" required>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Official Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="tariq@generation.edu.pk" value="<?= e($email) ?>" required>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Contact Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-phone-alt text-muted"></i></span>
                                <input type="text" name="phone" class="form-control" placeholder="+92 300 1234567" value="<?= e($phone) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-secondary">System Access Role <span class="text-danger">*</span></label>
                            <select name="role_id" id="roleSelector" class="form-select" required onchange="updateRolePreview(this)">
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" data-desc="<?= e($r['description']) ?>" data-name="<?= e($r['name']) ?>" <?= $roleId == $r['id'] ? 'selected' : '' ?>>
                                        <?= e($r['display_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Account Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (Immediate System Access)</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive (Pending Activation)</option>
                                <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>

                        <!-- Password Section with Strong Password Generator -->
                        <div class="col-12 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-semibold text-secondary mb-0">Password & Security <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1" onclick="generateStrongPassword()">
                                    <i class="fas fa-magic me-1"></i> Generate Strong Password
                                </button>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small text-muted">New Password</label>
                                    <div class="input-group">
                                        <input type="text" name="password" id="passwordField" class="form-control font-monospace" placeholder="Enter or generate strong password" required>
                                        <button class="btn btn-light border text-muted" type="button" onclick="copyPasswordToClipboard()" title="Copy to clipboard">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="form-text small text-muted">Recommended: At least 10+ characters with uppercase, numbers, and symbols.</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small text-muted">Confirm Password</label>
                                    <input type="text" name="confirm_password" id="confirmPasswordField" class="form-control font-monospace" placeholder="Repeat same password" required>
                                    <div id="passwordMatchText" class="form-text small"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 pt-4 border-top d-flex justify-content-end gap-2">
                            <a href="<?= BASE_PATH ?>/admin/users.php" class="btn btn-light rounded-pill px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                <i class="fas fa-user-check me-2"></i> Register & Grant Access
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Role Information & Guidance Card -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h6 class="fw-bold text-dark mb-3">
                <i class="fas fa-shield-alt text-primary me-2"></i>Role Privileges Overview
            </h6>
            <div id="roleInfoBox" class="p-3 bg-light rounded-3 border mb-3">
                <h6 id="roleTitle" class="fw-bold text-primary mb-1">Teacher</h6>
                <p id="roleDescription" class="text-muted small mb-0">Marks entry, classroom attendance, student performance assessment, and exam reports.</p>
            </div>
            
            <h6 class="fw-bold text-dark small text-uppercase tracking-wider mb-2">Available System Roles:</h6>
            <ul class="list-unstyled small text-muted mb-0">
                <li class="mb-2"><strong class="text-dark">Super Admin:</strong> Unrestricted access to entire system, backup, settings, and users.</li>
                <li class="mb-2"><strong class="text-dark">School Admin:</strong> Admissions, academics, classes, exams, timetable, and operations.</li>
                <li class="mb-2"><strong class="text-dark">Teacher:</strong> Attendance, mark entries, student profile viewing, and report cards.</li>
                <li class="mb-2"><strong class="text-dark">Accountant:</strong> Fee collection, challan issuance, expenses, and payroll records.</li>
                <li class="mb-2"><strong class="text-dark">Receptionist:</strong> Student admissions, parent inquiries, and front-desk visitor management.</li>
                <li class="mb-2"><strong class="text-dark">Librarian:</strong> Book catalog, issues, returns, and library inventory.</li>
            </ul>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold text-dark mb-2">
                <i class="fas fa-lightbulb text-warning me-2"></i>Security Best Practices
            </h6>
            <p class="text-muted small mb-0">
                Always ensure official staff emails are provided. Once registered, users can change their passwords upon logging in, or administrators can reset passwords anytime from the Users & Roles console.
            </p>
        </div>
    </div>
</div>

<script>
// Auto-suggest username from full name
function generateUsernameSuggestion(fullName) {
    const usernameInput = document.getElementById('usernameInput');
    if (!usernameInput.value || usernameInput.dataset.manual !== 'true') {
        const clean = fullName.toLowerCase().replace(/[^a-z0-9\s]/g, '').trim().replace(/\s+/g, '_');
        usernameInput.value = clean;
    }
}

document.getElementById('usernameInput').addEventListener('input', function() {
    this.dataset.manual = 'true';
});

// Strong Password Generator
function generateStrongPassword() {
    const charsUpper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    const charsLower = "abcdefghijkmnopqrstuvwxyz";
    const charsNum = "23456789";
    const charsSpecial = "@#$%&*!";
    
    let pass = "";
    pass += charsUpper.charAt(Math.floor(Math.random() * charsUpper.length));
    pass += charsLower.charAt(Math.floor(Math.random() * charsLower.length));
    pass += charsNum.charAt(Math.floor(Math.random() * charsNum.length));
    pass += charsSpecial.charAt(Math.floor(Math.random() * charsSpecial.length));

    const allChars = charsUpper + charsLower + charsNum + charsSpecial;
    for (let i = 0; i < 10; i++) {
        pass += allChars.charAt(Math.floor(Math.random() * allChars.length));
    }

    // Shuffle
    pass = pass.split('').sort(() => 0.5 - Math.random()).join('') + "2026#";

    document.getElementById('passwordField').value = pass;
    document.getElementById('confirmPasswordField').value = pass;
    checkPasswordMatch();
}

function copyPasswordToClipboard() {
    const passField = document.getElementById('passwordField');
    if (passField.value) {
        navigator.clipboard.writeText(passField.value);
        alert('Password copied to clipboard: ' + passField.value);
    }
}

function checkPasswordMatch() {
    const p1 = document.getElementById('passwordField').value;
    const p2 = document.getElementById('confirmPasswordField').value;
    const matchText = document.getElementById('passwordMatchText');
    
    if (!p2) {
        matchText.textContent = '';
    } else if (p1 === p2) {
        matchText.textContent = '✓ Passwords match perfectly.';
        matchText.className = 'form-text small text-success';
    } else {
        matchText.textContent = '✗ Passwords do not match.';
        matchText.className = 'form-text small text-danger';
    }
}

document.getElementById('passwordField').addEventListener('input', checkPasswordMatch);
document.getElementById('confirmPasswordField').addEventListener('input', checkPasswordMatch);

function updateRolePreview(selectElem) {
    const opt = selectElem.options[selectElem.selectedIndex];
    document.getElementById('roleTitle').textContent = opt.text;
    document.getElementById('roleDescription').textContent = opt.getAttribute('data-desc') || 'Full privileges associated with this role.';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
