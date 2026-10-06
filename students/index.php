<?php
/**
 * Student Directory & Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_view');

// Handle Student Delete (Soft Delete)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (hasPermission('student_delete')) {
        $deleteId = (int)$_GET['id'];
        $stmt = $pdo->prepare("UPDATE students SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$deleteId]);
        logAudit('DELETE_STUDENT', 'Students', $deleteId, 'Set student status to inactive');
        setFlashMessage('success', 'Student record has been deactivated successfully.');
    } else {
        setFlashMessage('error', 'You do not have permission to delete student records.');
    }
    header("Location: " . BASE_PATH . "/students/index.php");
    exit;
}

// Search and Filter Parameters
$search = trim($_GET['search'] ?? '');
$classFilter = (int)($_GET['class_id'] ?? 0);
$sectionFilter = (int)($_GET['section_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? 'active');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build Query
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.admission_no LIKE :search OR s.roll_no LIKE :search OR p.father_name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($classFilter > 0) {
    $whereConditions[] = "s.class_id = :class_id";
    $params[':class_id'] = $classFilter;
}

if ($sectionFilter > 0) {
    $whereConditions[] = "s.section_id = :section_id";
    $params[':section_id'] = $sectionFilter;
}

if (!empty($statusFilter) && $statusFilter !== 'all') {
    $whereConditions[] = "s.status = :status";
    $params[':status'] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Count Total Records
$countSql = "
    SELECT COUNT(*) 
    FROM students s
    LEFT JOIN parents p ON s.parent_id = p.id
    {$whereClause}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch Paginated Records
$sql = "
    SELECT s.*, c.class_name, sec.section_name, p.father_name, p.phone as parent_phone
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents p ON s.parent_id = p.id
    {$whereClause}
    ORDER BY s.id DESC
    LIMIT {$limit} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$allClasses = getAllClasses();
$allSections = $classFilter > 0 ? getSections($classFilter) : [];

$pageTitle = "Student Management";
$extraScripts = ['students.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Students</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Student Directory</h3>
    </div>
    
    <div class="d-flex flex-wrap gap-2">
        <?php if (hasPermission('student_promote')): ?>
            <a href="<?= BASE_PATH ?>/students/promote.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fas fa-graduation-cap me-1"></i> Promote Students
            </a>
        <?php endif; ?>
        <?php if (hasPermission('student_add')): ?>
            <a href="<?= BASE_PATH ?>/students/add.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fas fa-user-plus me-1"></i> + New Admission
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/students/index.php" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" id="studentSearchInput" class="form-control border-start-0" placeholder="Search name, roll no, father name..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-6 col-md-2">
                <select name="class_id" class="form-select class-select" data-target-section="filterSectionId">
                    <option value="">All Classes</option>
                    <?php foreach ($allClasses as $cls): ?>
                        <option value="<?= $cls['id'] ?>" <?= $classFilter == $cls['id'] ? 'selected' : '' ?>>
                            <?= e($cls['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="section_id" id="filterSectionId" class="form-select">
                    <option value="">All Sections</option>
                    <?php foreach ($allSections as $sec): ?>
                        <option value="<?= $sec['id'] ?>" <?= $sectionFilter == $sec['id'] ? 'selected' : '' ?>>
                            <?= e($sec['section_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="status" class="form-select">
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="promoted" <?= $statusFilter === 'promoted' ? 'selected' : '' ?>>Promoted</option>
                </select>
            </div>

            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filter</button>
                <a href="<?= BASE_PATH ?>/students/index.php" class="btn btn-light border"><i class="fas fa-undo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Student List Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-bold">Total Students Found: <span class="badge bg-primary rounded-pill"><?= $totalRecords ?></span></span>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print List</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Adm No</th>
                        <th>Student Name</th>
                        <th>Class & Sec</th>
                        <th>Roll No</th>
                        <th>Father Name</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody">
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-user-slash fs-1 d-block mb-3 text-secondary"></i>
                                No student records found matching the criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $st): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-primary border fw-semibold">
                                        <?= e($st['admission_no']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-initials me-2" style="width: 36px; height: 36px;">
                                            <?= strtoupper(substr($st['first_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $st['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= e($st['first_name'] . ' ' . $st['last_name']) ?>
                                            </a>
                                            <div class="text-muted small"><?= e($st['gender']) ?> &bull; <?= e($st['blood_group'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?= e($st['class_name'] ?? 'Unassigned') ?>
                                    </span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                        <?= e($st['section_name'] ?? '-') ?>
                                    </span>
                                </td>
                                <td><?= e($st['roll_no'] ?: '-') ?></td>
                                <td><?= e($st['father_name'] ?: 'Not Specified') ?></td>
                                <td>
                                    <span class="small text-muted"><i class="fas fa-phone-alt me-1"></i><?= e($st['phone'] ?: $st['parent_phone'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <?php if ($st['status'] === 'active'): ?>
                                        <span class="badge badge-soft-success">Active</span>
                                    <?php elseif ($st['status'] === 'promoted'): ?>
                                        <span class="badge badge-soft-info">Promoted</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-danger"><?= ucfirst($st['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $st['id'] ?>" class="btn btn-outline-info" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= BASE_PATH ?>/fees/batch-challan.php?student_id=<?= $st['id'] ?>" class="btn btn-outline-warning" title="Print Fee Challan">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                        <?php if (hasPermission('student_edit')): ?>
                                            <a href="<?= BASE_PATH ?>/students/edit.php?id=<?= $st['id'] ?>" class="btn btn-outline-primary" title="Edit Student">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (hasPermission('student_delete') && $st['status'] === 'active'): ?>
                                            <a href="<?= BASE_PATH ?>/students/index.php?action=delete&id=<?= $st['id'] ?>" class="btn btn-outline-danger btn-delete-confirm" data-confirm-message="Are you sure you want to deactivate student <?= e($st['first_name'] . ' ' . $st['last_name']) ?>?" title="Deactivate">
                                                <i class="fas fa-trash-alt"></i>
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
    
    <!-- Server Side Pagination Section 39 -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center py-3">
            <span class="small text-muted">Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= $totalRecords ?> records)</span>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&class_id=<?= $classFilter ?>&section_id=<?= $sectionFilter ?>&status=<?= $statusFilter ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&class_id=<?= $classFilter ?>&section_id=<?= $sectionFilter ?>&status=<?= $statusFilter ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&class_id=<?= $classFilter ?>&section_id=<?= $sectionFilter ?>&status=<?= $statusFilter ?>">Next</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
