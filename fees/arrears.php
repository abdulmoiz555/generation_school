<?php
/**
 * Fee Arrears & Defaulters Report (Section 21)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_manage');

$allClasses = getAllClasses();
$classFilter = (int)($_GET['class_id'] ?? 0);
$monthFilter = trim($_GET['month'] ?? '');

$conditions = ["sf.status IN ('Pending', 'Partial', 'Overdue')"];
$params = [];

if ($classFilter > 0) {
    $conditions[] = "s.class_id = :class_id";
    $params[':class_id'] = $classFilter;
}
if (!empty($monthFilter)) {
    $conditions[] = "sf.month LIKE :m";
    $params[':m'] = "%{$monthFilter}%";
}

$where = "WHERE " . implode(" AND ", $conditions);

$stmt = $pdo->prepare("
    SELECT sf.*, s.first_name, s.last_name, s.admission_no, s.roll_no,
           c.class_name, sec.section_name, p.father_name, p.phone as parent_phone,
           ft.name as fee_name
    FROM student_fees sf
    JOIN students s ON sf.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents p ON s.parent_id = p.id
    JOIN fee_types ft ON sf.fee_type_id = ft.id
    {$where}
    ORDER BY sf.due_date ASC, s.first_name ASC
");
$stmt->execute($params);
$arrears = $stmt->fetchAll();

$totalArrears = array_sum(array_column($arrears, 'balance'));

$pageTitle = "Fee Arrears & Defaulters";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Fees</li>
                <li class="breadcrumb-item active">Arrears</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Fee Arrears & Outstanding Dues</h3>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-print me-1"></i> Print Defaulters List
        </button>
        <a href="<?= BASE_PATH ?>/fees/collect.php" class="btn btn-success btn-sm rounded-pill px-3">
            <i class="fas fa-cash-register me-1"></i> Collect Fee
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/fees/arrears.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Class Filter</label>
                <select name="class_id" class="form-select">
                    <option value="">All Classes</option>
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Billing Month Keyword</label>
                <input type="text" name="month" class="form-control" placeholder="e.g. September or October" value="<?= e($monthFilter) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter Arrears</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Total Outstanding Deficit: <span class="text-danger fs-5"><?= formatCurrency($totalArrears) ?></span></span>
        <span class="badge bg-danger rounded-pill"><?= count($arrears) ?> Invoices Unpaid</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Class & Sec</th>
                        <th>Fee Particulars</th>
                        <th>Month</th>
                        <th>Billed Amount</th>
                        <th>Paid</th>
                        <th>Remaining Due</th>
                        <th>Due Date</th>
                        <th class="text-end no-print">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($arrears)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No pending fee arrears found. All students are up to date!</td></tr>
                    <?php else: ?>
                        <?php foreach ($arrears as $a): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></div>
                                    <small class="text-muted">Adm: <?= e($a['admission_no']) ?> &bull; Phone: <?= e($a['parent_phone'] ?: '-') ?></small>
                                </td>
                                <td><?= e($a['class_name'] . ' - ' . $a['section_name']) ?></td>
                                <td><?= e($a['fee_name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($a['month']) ?></span></td>
                                <td><?= formatCurrency($a['amount']) ?></td>
                                <td class="text-success"><?= formatCurrency($a['paid_amount']) ?></td>
                                <td class="fw-bold text-danger fs-6"><?= formatCurrency($a['balance']) ?></td>
                                <td>
                                    <span class="badge <?= strtotime($a['due_date']) < time() ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                        <?= formatDate($a['due_date']) ?>
                                    </span>
                                </td>
                                <td class="text-end no-print">
                                    <a href="<?= BASE_PATH ?>/fees/collect.php?student_id=<?= $a['student_id'] ?>" class="btn btn-sm btn-success rounded-pill px-3">
                                        Collect
                                    </a>
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
