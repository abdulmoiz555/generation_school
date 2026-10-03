<?php
/**
 * Academic Sessions Management (Section 50)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('settings_manage');

// Set Active Session
if (isset($_GET['action']) && $_GET['action'] === 'activate' && isset($_GET['id'])) {
    $actId = (int)$_GET['id'];
    $pdo->query("UPDATE academic_sessions SET is_current = 0");
    $stmt = $pdo->prepare("UPDATE academic_sessions SET is_current = 1, status = 'active' WHERE id = ?");
    $stmt->execute([$actId]);
    $pdo->query("UPDATE school_settings SET key_value = '{$actId}' WHERE key_name = 'active_session_id'");
    logAudit('ACTIVATE_SESSION', 'Academics', $actId, "Set session {$actId} as active");
    setFlashMessage('success', "Active academic session updated!");
    header("Location: " . BASE_PATH . "/academics/sessions.php");
    exit;
}

// Add New Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_session') {
    $name = trim($_POST['session_name'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    if (!empty($name) && !empty($startDate) && !empty($endDate)) {
        $stmt = $pdo->prepare("INSERT INTO academic_sessions (session_name, start_date, end_date, is_current, status) VALUES (?, ?, ?, 0, 'active')");
        $stmt->execute([$name, $startDate, $endDate]);
        logAudit('ADD_SESSION', 'Academics', (int)$pdo->lastInsertId(), "Created academic session {$name}");
        setFlashMessage('success', "Academic session '{$name}' created successfully!");
    }
    header("Location: " . BASE_PATH . "/academics/sessions.php");
    exit;
}

$sessions = getAllSessions();

$pageTitle = "Academic Sessions";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Academics</li>
                <li class="breadcrumb-item active">Sessions</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Academic Sessions</h3>
    </div>
    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSessionModal">
        <i class="fas fa-plus me-1"></i> + New Session
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Session Title</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Current Active</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= e($s['session_name']) ?></td>
                            <td><?= formatDate($s['start_date']) ?></td>
                            <td><?= formatDate($s['end_date']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= ucfirst($s['status']) ?></span></td>
                            <td>
                                <?php if ($s['is_current'] == 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                                        <i class="fas fa-check-circle me-1"></i> Active Session
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($s['is_current'] != 1): ?>
                                    <a href="<?= BASE_PATH ?>/academics/sessions.php?action=activate&id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Switch current academic session to <?= e($s['session_name']) ?>?')">
                                        Set as Active
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Session Modal -->
<div class="modal fade" id="addSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Academic Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/sessions.php" method="POST">
                <input type="hidden" name="action" value="add_session">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Session Name <span class="text-danger">*</span></label>
                        <input type="text" name="session_name" class="form-control" placeholder="e.g. 2026-2027" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
