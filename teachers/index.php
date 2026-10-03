<?php
/**
 * Faculty & Teacher Directory (Section 12)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('teacher_view');

$search = trim($_GET['search'] ?? '');
$params = [];
$whereClause = "";

if (!empty($search)) {
    $whereClause = "WHERE (t.name LIKE :s OR t.teacher_code LIKE :s OR t.email LIKE :s OR t.phone LIKE :s)";
    $params[':s'] = "%{$search}%";
}

$stmt = $pdo->prepare("
    SELECT t.*, d.name as dept_name, des.title as desig_title,
           (SELECT COUNT(*) FROM class_subjects cs WHERE cs.teacher_id = t.id) as subjects_count
    FROM teachers t
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN designations des ON t.designation_id = des.id
    {$whereClause}
    ORDER BY t.id ASC
");
$stmt->execute($params);
$teachers = $stmt->fetchAll();

$pageTitle = "Teachers Directory";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Teachers</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Academic Faculty Roster</h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('teacher_add')): ?>
            <a href="<?= BASE_PATH ?>/teachers/add.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fas fa-plus me-1"></i> + Add Teacher
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/teachers/index.php" method="GET" class="row g-2">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search teacher by name, code, email..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
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
                        <th>Teacher Name</th>
                        <th>Code</th>
                        <th>Qualification</th>
                        <th>Experience</th>
                        <th>Department</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No teachers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($teachers as $t): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-initials me-2" style="width: 36px; height: 36px; background: linear-gradient(135deg, #10b981, #06b6d4);">
                                            <?= strtoupper(substr($t['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <a href="<?= BASE_PATH ?>/teachers/view.php?id=<?= $t['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= e($t['name']) ?>
                                            </a>
                                            <div class="text-muted small"><?= e($t['gender']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= e($t['teacher_code']) ?></code></td>
                                <td><?= e($t['qualification'] ?: 'Graduate') ?></td>
                                <td><?= $t['experience_years'] ?> Years</td>
                                <td><span class="badge bg-light text-dark border"><?= e($t['dept_name'] ?? 'Academics') ?></span></td>
                                <td>
                                    <div class="small text-muted"><i class="fas fa-phone me-1"></i><?= e($t['phone']) ?></div>
                                    <div class="small text-muted"><i class="fas fa-envelope me-1"></i><?= e($t['email']) ?></div>
                                </td>
                                <td><span class="badge badge-soft-success"><?= ucfirst($t['status']) ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_PATH ?>/teachers/view.php?id=<?= $t['id'] ?>" class="btn btn-outline-info" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (hasPermission('teacher_edit')): ?>
                                            <a href="<?= BASE_PATH ?>/teachers/edit.php?id=<?= $t['id'] ?>" class="btn btn-outline-primary" title="Edit">
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
