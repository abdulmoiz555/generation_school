<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();

// Start fresh session to pass logout confirmation flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
setFlashMessage('success', 'You have been successfully logged out.');

$loginUrl = (defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '') . '/login.php';
if (!headers_sent()) {
    header("Location: " . $loginUrl);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($loginUrl) ?>">
    <title>Logging Out | Springfield Academy</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        try {
            window.location.replace(<?= json_encode($loginUrl) ?>);
        } catch (e) {
            window.location.href = <?= json_encode($loginUrl) ?>;
        }
    </script>
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="card shadow-sm border-0 rounded-4 text-center p-4" style="max-width: 420px; width: 100%;">
        <div class="mb-3">
            <span class="spinner-border text-primary" role="status"></span>
        </div>
        <h5 class="fw-bold text-dark mb-1">Logging Out...</h5>
        <p class="text-muted small mb-3">Returning to the login screen safely.</p>
        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-sign-in-alt me-2"></i> Go to Login
        </a>
    </div>
</body>
</html>
<?php exit; ?>
