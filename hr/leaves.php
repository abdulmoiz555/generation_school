<?php
/**
 * HR Staff Leave Management
 * Staff leave applications, review, approval and rejection workflow
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('hr_manage');

// Handle Leave Status Update (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_leave') {
    $leaveId = (int)$_POST['leave_id'];
    $status = $_POST['status']; // Approved, Rejected

    $stmt = $pdo->prepare("UPDATE employee_leaves SET status = ?, approved_by = ? WHERE id = ?");
    $stmt->execute([$status, $_SESSION['user_id'] ?? 1, $leaveId]);
    logAudit('UPDATE_LEAVE', 'Leaves', $leaveId, "Updated leave status to {$status}");
    setFlashMessage('success', "Leave application status updated to " . ucfirst($status) . "!");
    header("Location: " . BASE_PATH . "/hr/leaves.php");
    exit;
}

// Handle New Leave Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_leave') {
    $staffId = (int)$_POST['staff_id'];
    $leaveTypeId = (int)$_POST['leave_type_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reason = trim($_POST['reason'] ?? '');
    
    $d1 = new DateTime($startDate);
    $d2 = new DateTime($endDate);
    $days = max(1, $d2->diff($d1)->days + 1);

    $stmt = $pdo->prepare("
        INSERT INTO employee_leaves (employee_id, leave_type_id, start_date, end_date, total_days, reason, status)
        VALUES (?, ?, ?, ?, ?, ?, 'Pending')
    ");
    $stmt->execute([$staffId, $leaveTypeId, $startDate, $endDate, $days, $reason]);
    logAudit('APPLY_LEAVE', 'Leaves', (int)$pdo->lastInsertId(), "Applied leave for staff ID {$staffId}");
    setFlashMessage('success', "Leave application submitted successfully!");
    header("Location: " . BASE_PATH . "/hr/leaves.php");
    exit;
}

// Fetch all leaves with staff details
$leaves = $pdo->query("
    SELECT l.*, s.staff_code, s.name as staff_name, lt.name as leave_type_name, d.name as dept_name
    FROM employee_leaves l
    JOIN staff s ON l.employee_id = s.id
    LEFT JOIN leave_types lt ON l.leave_type_id = lt.id
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY l.created_at DESC
")->fetchAll();

$staffMembers = $pdo->query("SELECT id, staff_code, name FROM staff WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();

$pageTitle = "Staff Leave Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/hr/employees.php">HR & Staff</a></li>
                <li class="breadcrumb-item active">Leaves</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-calendar-check text-primary me-2"></i> Staff Leave Management</h3>
        <p class="text-muted small mb-0">Review leave applications, manage balances, and approve or decline requests</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
            <i class="fas fa-plus me-1"></i> Apply Leave
        </button>
        <a href="<?= BASE_PATH ?>/hr/payroll.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fas fa-file-invoice-dollar me-1"></i> Payroll
        </a>
    </div>
</div>

<!-- Leave Requests Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">Leave Applications</h6>
        <span class="badge bg-light text-secondary border"><?= count($leaves) ?> Records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Staff Member</th>
                    <th>Leave Type</th>
                    <th>Duration & Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fas fa-mug-hot fa-3x opacity-25 mb-3 d-block"></i>
                            No leave applications submitted yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaves as $lv): 
                        $statusClass = match(strtolower($lv['status'])) {
                            'approved' => 'bg-success-subtle text-success',
                            'rejected' => 'bg-danger-subtle text-danger',
                            default => 'bg-warning-subtle text-warning'
                        };
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?= e($lv['staff_name']) ?></div>
                            <small class="text-muted"><?= e($lv['staff_code']) ?> &bull; <?= e($lv['dept_name'] ?: 'Staff') ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= e($lv['leave_type_name'] ?: 'General') ?></span></td>
                        <td>
                            <div class="small fw-semibold"><?= e($lv['start_date']) ?> to <?= e($lv['end_date']) ?></div>
                            <span class="badge bg-info-subtle text-info px-2"><?= $lv['total_days'] ?> Days</span>
                        </td>
                        <td class="small text-muted" style="max-width: 250px;"><?= e($lv['reason'] ?: 'Not specified') ?></td>
                        <td>
                            <span class="badge <?= $statusClass ?> px-2 py-1 text-uppercase" style="font-size: 0.75rem;">
                                <?= e($lv['status']) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if (strtolower($lv['status']) === 'pending'): ?>
                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2 me-1" data-bs-toggle="modal" data-bs-target="#actionModal<?= $lv['id'] ?>">
                                    <i class="fas fa-check"></i> Review
                                </button>
                            <?php else: ?>
                                <span class="small text-muted">Processed</span>
                            <?php endif; ?>

                            <!-- Action Modal -->
                            <div class="modal fade text-start" id="actionModal<?= $lv['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <form method="POST" class="modal-content rounded-4 border-0 shadow">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold">Review Leave Request</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="update_leave">
                                            <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold">Decision</label>
                                                <select name="status" class="form-select">
                                                    <option value="Approved">Approve Request</option>
                                                    <option value="Rejected">Reject Request</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4">Save Decision</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Apply Leave Modal -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Apply for Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="apply_leave">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Employee</label>
                    <select name="staff_id" class="form-select" required>
                        <option value="">-- Select Staff --</option>
                        <?php foreach ($staffMembers as $sm): ?>
                            <option value="<?= $sm['id'] ?>"><?= e($sm['name']) ?> (<?= e($sm['staff_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Leave Type</label>
                    <select name="leave_type_id" class="form-select" required>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?= $lt['id'] ?>"><?= e($lt['name']) ?> (<?= $lt['days_allowed'] ?> days/yr)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">End Date</label>
                        <input type="date" name="end_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Reason</label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Brief explanation for leave request..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4">Submit Application</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
