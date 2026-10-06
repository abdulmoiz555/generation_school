<?php
/**
 * Authentication - Login Screen
 * Generation Model School Institutional Portal
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

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
        $error = "Invalid session security token. Please refresh and try again.";
    } else {
        $loginInput = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if (empty($loginInput) || empty($password)) {
            $error = "Please enter your username/email and password.";
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

$pageTitle = "Login to Portal | Generation Model School";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body, html {
            height: 100%;
            margin: 0;
            background-color: #f1f5f9;
        }

        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            position: relative;
        }

        .auth-wrapper::before {
            content: "";
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background-image: radial-gradient(rgba(59, 130, 246, 0.15) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
            padding: 2.5rem 2.25rem;
            position: relative;
            z-index: 1;
        }

        .brand-logo-wrap {
            width: 76px;
            height: 76px;
            margin: 0 auto 1.25rem;
            border-radius: 50%;
            background: #ffffff;
            padding: 4px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            border: 2px solid #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .brand-logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .login-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.25rem;
            text-align: center;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            font-size: 0.85rem;
            color: #64748b;
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .form-floating-custom {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .form-label-custom {
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.4rem;
            display: block;
        }

        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-custom .input-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 0.95rem;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-custom {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.92rem;
            color: #0f172a;
            background: #f8fafc;
            transition: all 0.2s ease;
        }

        .input-custom:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }

        .input-custom:focus + .input-icon,
        .input-custom:focus ~ .input-icon {
            color: #3b82f6;
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .password-toggle-btn:hover {
            color: #334155;
        }

        .btn-signin {
            width: 100%;
            padding: 0.85rem 1.25rem;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.96rem;
            letter-spacing: 0.2px;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.35);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-signin:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            box-shadow: 0 14px 20px -3px rgba(37, 99, 235, 0.45);
            transform: translateY(-1px);
            color: #ffffff;
        }

        .btn-signin:active {
            transform: translateY(0);
        }

        .quick-role-chip {
            padding: 0.35rem 0.65rem;
            font-size: 0.72rem;
            font-weight: 600;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .quick-role-chip:hover {
            background: #e2e8f0;
            color: #0f172a;
            border-color: #cbd5e1;
        }

        .role-selector-box {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px dashed #e2e8f0;
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="login-card">
        <!-- Logo -->
        <div class="brand-logo-wrap">
            <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Generation Model School" onerror="this.outerHTML='<i class=\'fas fa-graduation-cap fs-2 text-primary\'></i>'">
        </div>

        <h2 class="login-title">Sign In</h2>
        <p class="login-subtitle">Generation Model School Institutional Portal</p>

        <?php if ($flash = getFlashMessage('success')): ?>
            <div class="alert alert-success d-flex align-items-center py-2 px-3 small rounded-3 mb-3 border-0">
                <i class="fas fa-check-circle me-2"></i>
                <div><?= htmlspecialchars($flash) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($flashErr = getFlashMessage('error')): ?>
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 small rounded-3 mb-3 border-0">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div><?= htmlspecialchars($flashErr) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 small rounded-3 mb-3 border-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form id="loginForm" action="<?= BASE_PATH ?>/login.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <!-- Username / Email Input -->
            <div class="form-floating-custom">
                <label for="loginInput" class="form-label-custom">Username or Email</label>
                <div class="input-group-custom">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="login" id="loginInput" class="input-custom" placeholder="Enter username or email" value="<?= htmlspecialchars($loginInput) ?>" required autofocus>
                </div>
            </div>

            <!-- Password Input -->
            <div class="form-floating-custom mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="passwordInput" class="form-label-custom">Password</label>
                </div>
                <div class="input-group-custom">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="passwordInput" class="input-custom" placeholder="Enter your secure password" required>
                    <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="Toggle password visibility">
                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Remember Me -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                    <label class="form-check-label small text-secondary" for="rememberMe">
                        Remember this device
                    </label>
                </div>
            </div>

            <!-- Sign In Submit Button (Strictly No Sign-in with Google) -->
            <button type="submit" class="btn-signin" id="submitLoginBtn">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>

        <!-- Quick Institutional Role Switcher (Populates username only, NO passwords displayed) -->
        <div class="role-selector-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-secondary" style="font-size: 0.76rem;">
                    <i class="fas fa-user-shield text-primary me-1"></i> Quick Sign-In by Role:
                </span>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <button type="button" class="quick-role-chip" data-user="admin" title="Login as School Administrator">
                    <i class="fas fa-user-tie text-primary"></i> Admin
                </button>
                <button type="button" class="quick-role-chip" data-user="superadmin" title="Login as Principal">
                    <i class="fas fa-crown text-warning"></i> Principal
                </button>
                <button type="button" class="quick-role-chip" data-user="teacher" title="Login as Teaching Faculty">
                    <i class="fas fa-chalkboard-teacher text-success"></i> Teacher
                </button>
                <button type="button" class="quick-role-chip" data-user="accountant" title="Login as Finance Accountant">
                    <i class="fas fa-cash-register text-info"></i> Accountant
                </button>
                <button type="button" class="quick-role-chip" data-user="receptionist" title="Login as Front Reception">
                    <i class="fas fa-concierge-bell text-secondary"></i> Reception
                </button>
                <button type="button" class="quick-role-chip" data-user="librarian" title="Login as Librarian">
                    <i class="fas fa-book-reader text-dark"></i> Librarian
                </button>
            </div>
        </div>

    </div>
</div>

<script>
// Show / Hide Password toggle
const toggleBtn = document.getElementById('togglePasswordBtn');
const passwordInput = document.getElementById('passwordInput');
const toggleIcon = document.getElementById('togglePasswordIcon');
const loginInput = document.getElementById('loginInput');

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

// Quick fill username only (passwords never exposed on screen)
document.querySelectorAll('.quick-role-chip').forEach(chip => {
    chip.addEventListener('click', () => {
        const username = chip.getAttribute('data-user');
        if (loginInput && username) {
            loginInput.value = username;
            if (passwordInput) {
                passwordInput.value = '';
                passwordInput.focus();
            }
        }
    });
});
</script>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
