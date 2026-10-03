<?php
/**
 * System Notifications Center (Section 36)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = getCurrentUserId();

// Mark all as read
if (isset($_GET['action']) && $_GET['action'] === 'read_all') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    setFlashMessage('success', "All notifications marked as read!");
    header("Location: " . BASE_PATH . "/communication/notifications.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$pageTitle = "Notifications Center";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Communication</li>
                <li class="breadcrumb-item active">Notifications</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">System Notifications & Alerts (Section 36)</h3>
    </div>
    <a href="<?= BASE_PATH ?>/communication/notifications.php?action=read_all" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
        <i class="fas fa-check-double me-1"></i> Mark All as Read
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-bell-slash fs-1 d-block mb-3 text-secondary"></i>
                    No alerts or notifications at this time.
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="list-group-item p-3 <?= $n['is_read'] ? '' : 'bg-light' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-<?= $n['type'] === 'success' ? 'success' : ($n['type'] === 'warning' ? 'warning' : 'primary') ?> me-3 p-2 rounded-circle">
                                    <i class="fas fa-bell fa-sm"></i>
                                </span>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark"><?= e($n['title']) ?></h6>
                                    <p class="text-muted small mb-0"><?= e($n['message']) ?></p>
                                </div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block"><?= formatDate($n['created_at'], 'M d, H:i') ?></small>
                                <?php if (!empty($n['link'])): ?>
                                    <a href="<?= BASE_PATH ?>/<?= e($n['link']) ?>" class="btn btn-xs btn-outline-primary mt-1">View Detail</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
