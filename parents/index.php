<?php
/**
 * Parents Directory & Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_view');

$search = trim($_GET['search'] ?? '');
$params = [];
$whereClause = "";

if (!empty($search)) {
    $whereClause = "WHERE father_name LIKE :s OR mother_name LIKE :s OR phone LIKE :s OR cnic LIKE :s";
    $params[':s'] = "%{$search}%";
}

$stmt = $pdo->prepare("SELECT * FROM parents {$whereClause} ORDER BY id DESC");
$stmt->execute($params);
$parents = $stmt->fetchAll();

$pageTitle = "Parents Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Parents</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Parents Directory</h3>
    </div>
    <?php if (hasPermission('student_add')): ?>
        <a href="<?= BASE_PATH ?>/parents/add.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-plus me-1"></i> Add Parent
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/parents/index.php" method="GET" class="row g-2">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search father name, phone, CNIC..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Father / Guardian Name</th>
                        <th>Mother Name</th>
                        <th>CNIC / ID</th>
                        <th>Phone</th>
                        <th>Associated Children</th>
                        <th>City</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parents)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No parent records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($parents as $p): 
                            // Fetch children
                            $chStmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, c.class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.parent_id = ?");
                            $chStmt->execute([$p['id']]);
                            $children = $chStmt->fetchAll();
                        ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_PATH ?>/parents/view.php?id=<?= $p['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                        <?= e($p['father_name']) ?>
                                    </a>
                                </td>
                                <td><?= e($p['mother_name'] ?: '-') ?></td>
                                <td><code><?= e($p['cnic'] ?: '-') ?></code></td>
                                <td><i class="fas fa-phone-alt me-1 text-muted small"></i><?= e($p['phone']) ?></td>
                                <td>
                                    <?php if (empty($children)): ?>
                                        <span class="text-muted small">None linked</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($children as $ch): ?>
                                                <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $ch['id'] ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none">
                                                    <?= e($ch['first_name'] . ' (' . ($ch['class_name'] ?? 'Class') . ')') ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($p['city'] ?: '-') ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_PATH ?>/parents/view.php?id=<?= $p['id'] ?>" class="btn btn-outline-info" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (hasPermission('student_edit')): ?>
                                            <a href="<?= BASE_PATH ?>/parents/edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
