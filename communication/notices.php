<?php
/**
 * School Notice Board (Section 34)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// Add Notice (Admin / Super Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_notice' && hasPermission('notices_manage')) {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');
    $aud = $_POST['audience'] ?? 'Everyone';

    if (!empty($title) && !empty($desc)) {
        $stmt = $pdo->prepare("INSERT INTO notices (title, description, date, audience, status, created_by) VALUES (?, ?, ?, ?, 'Active', ?)");
        $stmt->execute([$title, $desc, $date, $aud, getCurrentUserId()]);
        logAudit('ADD_NOTICE', 'Communication', (int)$pdo->lastInsertId(), "Created notice: {$title}");
        setFlashMessage('success', "Notice published on bulletin board!");
    }
    header("Location: " . BASE_PATH . "/communication/notices.php");
    exit;
}

$notices = $pdo->query("
    SELECT n.*, u.full_name as author_name 
    FROM notices n 
    LEFT JOIN users u ON n.created_by = u.id 
    ORDER BY n.date DESC, n.id DESC
")->fetchAll();

$pageTitle = "Notice Board";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Communication</li>
                <li class="breadcrumb-item active">Notice Board</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Official Campus Notice Board (Section 34)</h3>
    </div>
    <?php if (hasPermission('notices_manage')): ?>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
            <i class="fas fa-bullhorn me-1"></i> + Broadcast Notice
        </button>
    <?php endif; ?>
</div>

<div class="row g-4">
    <?php foreach ($notices as $n): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill">
                        <i class="fas fa-users me-1"></i> <?= e($n['audience']) ?>
                    </span>
                    <span class="text-muted small"><i class="fas fa-calendar-alt me-1"></i> <?= formatDate($n['date']) ?></span>
                </div>
                <div class="card-body p-4 d-flex flex-column">
                    <h5 class="fw-bold text-dark mb-2"><?= e($n['title']) ?></h5>
                    <p class="text-muted small flex-grow-1"><?= nl2br(e($n['description'])) ?></p>
                    <div class="pt-3 border-top d-flex justify-content-between align-items-center small text-muted">
                        <span><i class="fas fa-user-edit me-1"></i> <?= e($n['author_name'] ?: 'Administration') ?></span>
                        <span class="badge bg-success-subtle text-success">Active</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="addNoticeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Broadcast Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/communication/notices.php" method="POST">
                <input type="hidden" name="action" value="add_notice">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notice Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Headline / Subject" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Publish Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Target Audience</label>
                            <select name="audience" class="form-select">
                                <option value="Everyone">Everyone (Public)</option>
                                <option value="Students">Students Only</option>
                                <option value="Parents">Parents Only</option>
                                <option value="Teachers">Faculty & Teachers</option>
                                <option value="Staff">Operations Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notice Body Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Detailed notice announcement..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Publish Notice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
