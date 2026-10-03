<?php
/**
 * System Audit & Activity Logs
 * Tracks user logins, record mutations, and critical actions across modules
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('admin_settings');

$filterModule = $_GET['module'] ?? '';
$filterAction = $_GET['action_type'] ?? '';
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT a.*, u.username, u.email, r.name as role_name
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE 1=1
";
$params = [];

if ($filterModule) {
    $query .= " AND a.module = ?";
    $params[] = $filterModule;
}
if ($filterAction) {
    $query .= " AND a.action = ?";
    $params[] = $filterAction;
}
if ($search) {
    $query .= " AND (a.description LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY a.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct modules for filter
$modules = $pdo->query("SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL AND module != '' ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "System Audit Logs";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/admin/settings.php">Admin</a></li>
                <li class="breadcrumb-item active">Audit Logs</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-history text-primary me-2"></i> Audit & Activity Logs</h3>
        <p class="text-muted small mb-0">Immutable tracking of user authentication, state mutations, and system actions</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/admin/backup.php" class="btn btn-outline-primary rounded-pill px-3">
            <i class="fas fa-database me-1"></i> Database Backup
        </a>
        <a href="<?= BASE_PATH ?>/admin/settings.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fas fa-cog me-1"></i> System Settings
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by description or user..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="module" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Modules --</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= e($m) ?>" <?= $filterModule === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-dark btn-sm"><i class="fas fa-filter me-1"></i> Filter</button>
                <a href="<?= BASE_PATH ?>/admin/audit-logs.php" class="btn btn-light btn-sm text-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light">
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fas fa-shield-alt fa-3x opacity-25 mb-3 d-block"></i>
                            No audit logs matching criteria found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted small text-nowrap">
                            <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($log['username'] ?? 'System') ?></div>
                            <small class="text-muted"><?= e($log['role_name'] ?? 'Automated') ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= e($log['module'] ?: 'General') ?></span></td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <?= e($log['action']) ?>
                            </span>
                        </td>
                        <td class="text-secondary"><?= e($log['description'] ?: '-') ?></td>
                        <td class="text-muted small"><code><?= e($log['ip_address'] ?: '127.0.0.1') ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
