<?php
/**
 * HR Employees & Payroll (Section 30 & 31)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('hr_manage');

// Handle Payroll Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_payroll') {
    $empId = (int)$_POST['employee_id'];
    $monthYear = $_POST['month_year'];
    $basic = (float)$_POST['basic_salary'];
    $allow = (float)$_POST['allowances'];
    $bonus = (float)$_POST['bonus'];
    $deduct = (float)$_POST['deductions'];
    $net = max(0, $basic + $allow + $bonus - $deduct);
    $payMethod = $_POST['payment_method'] ?? 'Bank Transfer';

    $payslipNo = "SLIP-" . date('Ym') . "-" . sprintf("%03d", $empId);

    $stmt = $pdo->prepare("
        INSERT INTO payroll (employee_id, month_year, basic_salary, allowances, bonus, deductions, net_salary, payment_date, payment_method, status, payslip_no, generated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'Paid', ?, ?)
    ");
    $stmt->execute([$empId, $monthYear, $basic, $allow, $bonus, $deduct, $net, $payMethod, $payslipNo, getCurrentUserId()]);
    logAudit('GENERATE_PAYROLL', 'HR', (int)$pdo->lastInsertId(), "Generated payroll {$payslipNo} for employee {$empId}");
    setFlashMessage('success', "Payroll generated! Payslip #{$payslipNo}");
    header("Location: " . BASE_PATH . "/hr/employees.php?tab=payroll");
    exit;
}

$employees = $pdo->query("
    SELECT e.*, d.name as dept_name, des.title as desig_title
    FROM employees e
    JOIN departments d ON e.department_id = d.id
    JOIN designations des ON e.designation_id = des.id
    ORDER BY e.id ASC
")->fetchAll();

$payrollHistory = $pdo->query("
    SELECT p.*, e.name as emp_name, e.employee_code, d.name as dept_name
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    JOIN departments d ON e.department_id = d.id
    ORDER BY p.id DESC
")->fetchAll();

$activeTab = $_GET['tab'] ?? 'employees';
$pageTitle = "HR & Payroll Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">HR & Payroll</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Human Resources & Payroll (Section 30 - 32)</h3>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#generatePayrollModal">
            <i class="fas fa-file-invoice-dollar me-1"></i> Generate Payroll
        </button>
    </div>
</div>

<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'employees' ? 'active' : '' ?> rounded-pill" href="<?= BASE_PATH ?>/hr/employees.php?tab=employees">
            <i class="fas fa-users me-1"></i> Employees List (<?= count($employees) ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'payroll' ? 'active' : '' ?> rounded-pill" href="<?= BASE_PATH ?>/hr/employees.php?tab=payroll">
            <i class="fas fa-receipt me-1"></i> Payroll & Payslips (<?= count($payrollHistory) ?>)
        </a>
    </li>
</ul>

<?php if ($activeTab === 'employees'): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Code</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Basic Salary</th>
                            <th>Contact</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($emp['name']) ?></td>
                                <td><code><?= e($emp['employee_code']) ?></code></td>
                                <td><span class="badge bg-light text-dark border"><?= e($emp['dept_name']) ?></span></td>
                                <td><?= e($emp['desig_title']) ?></td>
                                <td class="fw-bold text-success"><?= formatCurrency($emp['basic_salary']) ?></td>
                                <td><?= e($emp['phone']) ?></td>
                                <td><span class="badge badge-soft-success"><?= ucfirst($emp['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Payslip #</th>
                            <th>Employee</th>
                            <th>Billing Month</th>
                            <th>Basic</th>
                            <th>Allowances</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                            <th>Payment Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payrollHistory as $pr): ?>
                            <tr>
                                <td><strong class="text-primary"><?= e($pr['payslip_no']) ?></strong></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($pr['emp_name']) ?></div>
                                    <small class="text-muted"><?= e($pr['dept_name']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($pr['month_year']) ?></span></td>
                                <td><?= formatCurrency($pr['basic_salary']) ?></td>
                                <td class="text-success">+<?= formatCurrency($pr['allowances'] + $pr['bonus']) ?></td>
                                <td class="text-danger">-<?= formatCurrency($pr['deductions']) ?></td>
                                <td class="fw-bold text-success fs-6"><?= formatCurrency($pr['net_salary']) ?></td>
                                <td><?= formatDate($pr['payment_date']) ?></td>
                                <td><span class="badge badge-soft-success">Paid</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Payroll Modal -->
<div class="modal fade" id="generatePayrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Process Employee Salary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/hr/employees.php" method="POST">
                <input type="hidden" name="action" value="generate_payroll">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" id="payrollEmpSelect" class="form-select" required>
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['id'] ?>" data-basic="<?= $e['basic_salary'] ?>">
                                    <?= e($e['name']) ?> (<?= e($e['employee_code']) ?>) - Basic: <?= formatCurrency($e['basic_salary']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Month & Year <span class="text-danger">*</span></label>
                        <input type="text" name="month_year" class="form-control" value="<?= date('F Y') ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Basic Salary ($)</label>
                            <input type="number" step="0.01" name="basic_salary" id="payrollBasic" class="form-control" value="3500.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Allowances ($)</label>
                            <input type="number" step="0.01" name="allowances" class="form-control" value="500.00">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Bonus / Overtime ($)</label>
                            <input type="number" step="0.01" name="bonus" class="form-control" value="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Deductions / Taxes ($)</label>
                            <input type="number" step="0.01" name="deductions" class="form-control" value="150.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Disbursement Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Issue Payslip</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('payrollEmpSelect')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const b = opt.getAttribute('data-basic');
    if (b) {
        document.getElementById('payrollBasic').value = b;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
