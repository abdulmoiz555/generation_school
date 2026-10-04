<?php
/**
 * Scholarships & Fee Concessions Management
 * Admin configuration for Need-based, Orphan, Siblings, and custom concessions
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_manage');

$error = '';

// Add New Scholarship / Concession
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_scholarship') {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'Need-based';
    $percentage = floatval($_POST['discount_percentage'] ?? 0);
    $desc = trim($_POST['description'] ?? '');

    if (empty($title) || $percentage < 0 || $percentage > 100) {
        $error = "Please enter a valid title and percentage between 0% and 100%.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO scholarships (title, category, discount_percentage, description, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$title, $category, $percentage, $desc]);
        logAudit('ADD_SCHOLARSHIP', 'Fees', (int)$pdo->lastInsertId(), "Created scholarship '{$title}' ({$percentage}%)");
        setFlashMessage('success', "Scholarship '{$title}' added with {$percentage}% fee concession!");
        header("Location: " . BASE_PATH . "/fees/scholarships.php");
        exit;
    }
}

// Edit Scholarship / Percentage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_scholarship') {
    $scId = (int)($_POST['scholarship_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'Need-based';
    $percentage = floatval($_POST['discount_percentage'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($title) || $percentage < 0 || $percentage > 100 || $scId <= 0) {
        $error = "Please enter a valid title and percentage between 0% and 100%.";
    } else {
        $stmt = $pdo->prepare("UPDATE scholarships SET title = ?, category = ?, discount_percentage = ?, description = ?, status = ? WHERE id = ?");
        $stmt->execute([$title, $category, $percentage, $desc, $status, $scId]);
        logAudit('EDIT_SCHOLARSHIP', 'Fees', $scId, "Updated scholarship ID {$scId} percentage to {$percentage}%");
        setFlashMessage('success', "Scholarship '{$title}' updated to {$percentage}% fee waiver!");
        header("Location: " . BASE_PATH . "/fees/scholarships.php");
        exit;
    }
}

// Toggle Status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $scId = (int)$_GET['id'];
    $cur = $_GET['current'] ?? 'active';
    $next = ($cur === 'active') ? 'inactive' : 'active';
    $stmt = $pdo->prepare("UPDATE scholarships SET status = ? WHERE id = ?");
    $stmt->execute([$next, $scId]);
    setFlashMessage('success', "Scholarship status changed to " . ucfirst($next));
    header("Location: " . BASE_PATH . "/fees/scholarships.php");
    exit;
}

// Delete Scholarship
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_scholarship') {
    $scId = (int)$_POST['scholarship_id'];
    $stmt = $pdo->prepare("DELETE FROM scholarships WHERE id = ?");
    $stmt->execute([$scId]);
    logAudit('DELETE_SCHOLARSHIP', 'Fees', $scId, "Deleted scholarship ID {$scId}");
    setFlashMessage('success', "Scholarship category removed.");
    header("Location: " . BASE_PATH . "/fees/scholarships.php");
    exit;
}

// Fetch all scholarships
$scholarships = $pdo->query("SELECT * FROM scholarships ORDER BY category ASC, id ASC")->fetchAll();

$pageTitle = "Scholarships & Fee Concessions";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Fees</li>
                <li class="breadcrumb-item active">Scholarships & Concessions</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Institutional Scholarships & Concessions</h3>
        <p class="text-muted small mb-0">Configure discount percentages for Need-Based, Orphan, Siblings, and Merit quotas</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/fees/collect.php" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-cash-register me-1"></i> Fee Collection Counter
        </a>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addScholarshipModal">
            <i class="fas fa-plus me-1"></i> + New Scholarship Quota
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 shadow-sm" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Quota Cards Highlight -->
<div class="row g-3 mb-4">
    <?php
    $categories = [
        'Orphan' => ['icon' => 'fa-hands-holding-child', 'color' => 'danger', 'desc' => 'Welfare waivers for orphan students'],
        'Need-based' => ['icon' => 'fa-hand-holding-dollar', 'color' => 'primary', 'desc' => 'Financial aid for deserving families'],
        'Siblings' => ['icon' => 'fa-people-roof', 'color' => 'success', 'desc' => 'Kinship discount for 2nd & 3rd children'],
        'Merit' => ['icon' => 'fa-award', 'color' => 'warning', 'desc' => 'Top position holders in academic exams'],
    ];
    foreach ($categories as $catName => $meta):
        $found = null;
        foreach ($scholarships as $sc) {
            if ($sc['category'] === $catName) {
                $found = $sc;
                break;
            }
        }
        $pct = $found ? $found['discount_percentage'] . '%' : 'Not Set';
    ?>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-<?= $meta['color'] ?> border-4">
            <div class="d-flex align-items-center mb-2">
                <div class="rounded-circle p-2 bg-<?= $meta['color'] ?>-subtle text-<?= $meta['color'] ?> me-2">
                    <i class="fas <?= $meta['icon'] ?>"></i>
                </div>
                <div class="fw-bold text-dark"><?= $catName ?> Quota</div>
            </div>
            <div class="fs-4 fw-bold text-<?= $meta['color'] ?> mb-1"><?= $pct ?> Waiver</div>
            <p class="text-muted small mb-0"><?= $meta['desc'] ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Scholarships Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-percentage text-primary me-2"></i> All Active Fee Concessions & Scholarships</h6>
        <span class="badge bg-light text-dark border">Admin Percentage Control</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Concession Program</th>
                        <th>Category</th>
                        <th>Fee Discount %</th>
                        <th>Criteria / Description</th>
                        <th>Status</th>
                        <th class="text-end">Admin Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scholarships)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No scholarships configured. Click "+ New Scholarship Quota" to add one.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($scholarships as $s): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($s['title']) ?></div>
                                    <div class="text-muted small">ID: #<?= $s['id'] ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3">
                                        <?= e($s['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fs-6 fw-bold text-success font-monospace">
                                        <?= number_format($s['discount_percentage'], 1) ?>%
                                    </span>
                                    <span class="text-muted small d-block">Fee Deduction</span>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= e($s['description'] ?: 'Official institutional fee waiver policy.') ?></span>
                                </td>
                                <td>
                                    <?php if ($s['status'] === 'active'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Edit Percentage Button -->
                                        <button type="button" class="btn btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#editScholarshipModal"
                                                onclick="populateEditScholarship(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)"
                                                title="Edit Percentage">
                                            <i class="fas fa-edit me-1"></i> Edit %
                                        </button>
                                        <!-- Toggle Status -->
                                        <a href="<?= BASE_PATH ?>/fees/scholarships.php?action=toggle&id=<?= $s['id'] ?>&current=<?= $s['status'] ?>"
                                           class="btn <?= $s['status'] === 'active' ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                           title="<?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                            <i class="fas <?= $s['status'] === 'active' ? 'fa-pause' : 'fa-play' ?>"></i>
                                        </a>
                                        <!-- Delete -->
                                        <button type="button" class="btn btn-outline-danger"
                                                data-bs-toggle="modal" data-bs-target="#deleteScholarshipModal"
                                                onclick="document.getElementById('deleteScholarshipId').value = '<?= $s['id'] ?>'; document.getElementById('deleteScholarshipTitle').textContent = '<?= e($s['title']) ?>';"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

<!-- Add Modal -->
<div class="modal fade" id="addScholarshipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold mb-0"><i class="fas fa-award text-primary me-2"></i> Add Scholarship / Concession</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/fees/scholarships.php" method="POST">
                <input type="hidden" name="action" value="add_scholarship">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Scholarship Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Deserving Orphan Student Assistance" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="Orphan">Orphan</option>
                                <option value="Need-based" selected>Need-based</option>
                                <option value="Siblings">Siblings</option>
                                <option value="Merit">Merit</option>
                                <option value="Staff-Child">Staff-Child</option>
                                <option value="Special">Special / Discretionary</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Discount Percentage (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" max="100" name="discount_percentage" class="form-control" placeholder="50" required>
                                <span class="input-group-text bg-light fw-bold">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Policy Description & Eligibility</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="State eligibility criteria (e.g. income certificate required, only applies to tuition fee)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Save Scholarship</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Percentage Modal -->
<div class="modal fade" id="editScholarshipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold mb-0"><i class="fas fa-edit text-primary me-2"></i> Edit Concession Percentage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/fees/scholarships.php" method="POST">
                <input type="hidden" name="action" value="edit_scholarship">
                <input type="hidden" name="scholarship_id" id="editScId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Program Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="editScTitle" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category" id="editScCategory" class="form-select" required>
                                <option value="Orphan">Orphan</option>
                                <option value="Need-based">Need-based</option>
                                <option value="Siblings">Siblings</option>
                                <option value="Merit">Merit</option>
                                <option value="Staff-Child">Staff-Child</option>
                                <option value="Special">Special / Discretionary</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Fee Discount % (Admin Editable) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" max="100" name="discount_percentage" id="editScPct" class="form-control font-monospace fw-bold text-success" required>
                                <span class="input-group-text bg-light fw-bold">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Account Status</label>
                        <select name="status" id="editScStatus" class="form-select">
                            <option value="active">Active (Available for collection)</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" id="editScDesc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Update Percentage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteScholarshipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-danger-subtle">
                <h5 class="modal-title fw-bold text-danger mb-0">Delete Scholarship</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/fees/scholarships.php" method="POST">
                <input type="hidden" name="action" value="delete_scholarship">
                <input type="hidden" name="scholarship_id" id="deleteScholarshipId">
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5 class="fw-bold">Confirm Deletion</h5>
                    <p class="text-muted small mb-0">Are you sure you want to delete <strong id="deleteScholarshipTitle"></strong>?</p>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function populateEditScholarship(sc) {
    document.getElementById('editScId').value = sc.id;
    document.getElementById('editScTitle').value = sc.title;
    document.getElementById('editScCategory').value = sc.category;
    document.getElementById('editScPct').value = sc.discount_percentage;
    document.getElementById('editScStatus').value = sc.status;
    document.getElementById('editScDesc').value = sc.description || '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
