<?php
/**
 * Roles & Permission Matrix (Section 7)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('settings_manage');

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$permissions = $pdo->query("SELECT * FROM permissions ORDER BY module ASC, code ASC")->fetchAll();

$rolePermMap = [];
$stmt = $pdo->query("SELECT role_id, permission_id FROM role_permissions");
while ($r = $stmt->fetch()) {
    $rolePermMap[$r['role_id']][] = $r['permission_id'];
}

$pageTitle = "Role Permissions Matrix";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Settings</li>
                <li class="breadcrumb-item active">Roles & Access</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Role-Based Access Control (RBAC Matrix)</h3>
    </div>
    <a href="<?= BASE_PATH ?>/admin/users.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
        <i class="fas fa-users me-1"></i> User Accounts
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent py-3">
        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-shield-alt me-2"></i> System Security Roles & Responsibilities</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Role Title</th>
                        <th>Identifier</th>
                        <th>Scope of Access</th>
                        <th>Assigned Users</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $r): 
                        $uCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role_id = {$r['id']}")->fetchColumn();
                    ?>
                        <tr>
                            <td><strong class="text-dark"><?= e($r['display_name']) ?></strong></td>
                            <td><code><?= e($r['name']) ?></code></td>
                            <td class="text-muted small"><?= e($r['description']) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $uCount ?> Accounts</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent py-3">
        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-key me-2 text-warning"></i> Module Permissions Grid</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 small text-center">
                <thead class="table-light">
                    <tr>
                        <th class="text-start">Module / Permission Code</th>
                        <?php foreach ($roles as $r): ?>
                            <th><?= e($r['display_name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissions as $p): ?>
                        <tr>
                            <td class="text-start">
                                <span class="badge bg-light text-dark border me-1"><?= ucfirst($p['module']) ?></span>
                                <code><?= e($p['code']) ?></code>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= e($p['description']) ?></div>
                            </td>
                            <?php foreach ($roles as $r): 
                                $has = ($r['id'] == 1) || in_array($p['id'], $rolePermMap[$r['id']] ?? []);
                            ?>
                                <td>
                                    <?php if ($has): ?>
                                        <i class="fas fa-check-circle text-success fs-6"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times text-muted opacity-25"></i>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
