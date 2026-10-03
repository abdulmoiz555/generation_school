<?php
/**
 * General Staff Directory
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('hr_manage');

$stmt = $pdo->query("
    SELECT s.*, d.name as dept_name, des.title as desig_title
    FROM staff s
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN designations des ON s.designation_id = des.id
    ORDER BY s.id ASC
");
$staffMembers = $stmt->fetchAll();

$pageTitle = "Staff Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Staff</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Non-Teaching Staff & Personnel</h3>
    </div>
    <a href="<?= BASE_PATH ?>/staff/add.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
        <i class="fas fa-plus me-1"></i> + Add Staff Member
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Staff Name</th>
                        <th>Code</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Contact</th>
                        <th>Joining Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffMembers as $sm): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($sm['name']) ?></div>
                                <small class="text-muted"><?= e($sm['email'] ?: 'No email') ?></small>
                            </td>
                            <td><code><?= e($sm['staff_code']) ?></code></td>
                            <td><span class="badge bg-light text-dark border"><?= e($sm['dept_name'] ?? 'Operations') ?></span></td>
                            <td><strong><?= e($sm['desig_title'] ?? 'Staff') ?></strong></td>
                            <td><i class="fas fa-phone me-1 text-muted small"></i><?= e($sm['phone']) ?></td>
                            <td><?= formatDate($sm['joining_date']) ?></td>
                            <td><span class="badge badge-soft-success"><?= ucfirst($sm['status']) ?></span></td>
                            <td class="text-end">
                                <a href="<?= BASE_PATH ?>/staff/view.php?id=<?= $sm['id'] ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
