<?php
/**
 * Global Header Component
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/functions.php';

$pageTitle = isset($pageTitle) ? $pageTitle . " | " . getSetting('school_name', 'EduManage') : getSetting('school_name', 'EduManage');
$activeSession = getActiveSession();
$currentUser = getCurrentUser();
$unreadNotifications = $currentUser ? getUnreadNotificationsCount($currentUser['id']) : 0;
$flashSuccess = getFlashMessage('success');
$flashError = getFlashMessage('error');
$flashWarning = getFlashMessage('warning');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Font Awesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/print.css">

    <script>
        window.APP_BASE_PATH = "<?= BASE_PATH ?>";
    </script>
</head>
<body>
<div class="app-wrapper">
<?php if (isLoggedIn()): ?>
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="app-content">
        <?php require_once __DIR__ . '/navbar.php'; ?>
        <main class="page-container">
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0 mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?= e($flashSuccess) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0 mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= e($flashError) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($flashWarning): ?>
                <div class="alert alert-warning alert-dismissible fade show rounded-3 shadow-sm border-0 mb-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?= e($flashWarning) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
<?php else: ?>
    <div class="w-100 min-vh-100 d-flex flex-column">
<?php endif; ?>
