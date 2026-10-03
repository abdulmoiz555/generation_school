<?php
/**
 * Authentication - Login Screen
 * Generation Model School Portal
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already authenticated
if (isLoggedIn()) {
    header("Location: " . BASE_PATH . "/dashboard.php");
    exit;
}

$error = '';
$loginInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Invalid session token. Please refresh and try again.";
    } else {
        $loginInput = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if (empty($loginInput) || empty($password)) {
            $error = "Please enter both username/email and password.";
        } else {
            $res = loginUser($loginInput, $password, $remember);
            if ($res['success']) {
                header("Location: " . BASE_PATH . "/dashboard.php");
                exit;
            } else {
                $error = $res['message'];
            }
        }
    }
}

$pageTitle = "Login to Portal";
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-100 min-vh-100 d-flex flex-column justify-content-center align-items-center py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-5">
                
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle shadow-lg mb-3 p-1" style="width: 76px; height: 76px; overflow: hidden; border: 2px solid #3b82f6;">
                        <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Generation Model School" class="w-100 h-100 rounded-circle" style="object-fit: cover;" onerror="this.outerHTML='<i class=\'fas fa-graduation-cap fs-2 text-primary\'></i>'">
                    </div>
                    <h3 class="fw-bold text-white mb-1"><?= e(getSetting('school_name', 'Generation Model School')) ?></h3>
                    <p class="text-light text-opacity-75 small"><?= e(getSetting('school_motto', 'Excellence in Education, Character in Leadership')) ?></p>
                </div>

                <div class="card shadow-2xl border-0 rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4 text-center">
                        <h4 class="fw-bold text-dark mb-1">Sign In</h4>
                        <p class="text-muted small">Access your institutional portal securely</p>
                    </div>

                    <div class="card-body p-4 pt-2">
                        <?php if ($flash = getFlashMessage('success')): ?>
                            <div class="alert alert-success d-flex align-items-center py-2 px-3 small rounded-3 mb-3">
                                <i class="fas fa-check-circle me-2"></i>
                                <div><?= e($flash) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($flashErr = getFlashMessage('error')): ?>
                            <div class="alert alert-danger d-flex align-items-center py-2 px-3 small rounded-3 mb-3">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <div><?= e($flashErr) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger d-flex align-items-center py-2 px-3 small rounded-3 mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <div><?= e($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <form id="loginForm" action="<?= BASE_PATH ?>/login.php" method="POST" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                            <div class="mb-3">
                                <label for="loginInput" class="form-label small fw-semibold text-secondary">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-user"></i></span>
                                    <input type="text" name="login" id="loginInput" class="form-control border-start-0 ps-0" placeholder="e.g. username" value="<?= e($loginInput) ?>" required autofocus>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="passwordInput" class="form-label small fw-semibold text-secondary mb-0">Password</label>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" id="passwordInput" class="form-control border-start-0 border-end-0 ps-0" placeholder="••••••••••••••••" required>
                                    <button class="btn btn-light border border-start-0 text-muted" type="button" id="togglePasswordBtn" title="Toggle password visibility">
                                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                                    <label class="form-check-label small text-muted" for="rememberMe">
                                        Remember me
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold shadow-sm">
                                <i class="fas fa-sign-in-alt me-2"></i> Sign In
                            </button>
                        </form>

                    </div>
                </div>

                <div class="text-center mt-4 text-light text-opacity-75 small">
                    &copy; <?= date('Y') ?> <?= e(getSetting('school_name', 'Generation Model School')) ?>. All rights reserved.
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// Show/Hide password toggle
const toggleBtn = document.getElementById('togglePasswordBtn');
const passwordInput = document.getElementById('passwordInput');
const toggleIcon = document.getElementById('togglePasswordIcon');

if (toggleBtn && passwordInput && toggleIcon) {
    toggleBtn.addEventListener('click', () => {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
