<?php
/**
 * HR Payroll Management
 * Generates monthly staff payroll, calculates allowances, deductions, net salary, and generates printable payslips
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('hr_manage');

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$selectedMonthYear = sprintf("%04d-%02d", $year, $month);

// Handle Generate Payroll Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_payroll') {
    $payMonth = $_POST['pay_month'] ?? date('m');
    $payYear = $_POST['pay_year'] ?? date('Y');
    $mYear = sprintf("%04d-%02d", $payYear, $payMonth);
    
    // Fetch all active staff
    $staffList = $pdo->query("SELECT * FROM staff WHERE status = 'active'")->fetchAll();
    $generatedCount = 0;

    foreach ($staffList as $s) {
        $basic = (float)($s['salary'] ?? 3000);
        $allowances = round($basic * 0.15, 2); // default 15% allowance
        $deductions = round($basic * 0.05, 2); // default 5% tax/provident
        $net = $basic + $allowances - $deductions;
        $payslipNo = 'PAY-' . date('Ym') . '-' . str_pad($s['id'], 4, '0', STR_PAD_LEFT);

        // Check if payroll already exists for this staff and month
        $checkStmt = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND month_year = ?");
        $checkStmt->execute([$s['id'], $mYear]);
        if (!$checkStmt->fetch()) {
            $insStmt = $pdo->prepare("
                INSERT INTO payroll (employee_id, month_year, basic_salary, allowances, deductions, net_salary, payment_date, payment_method, status, payslip_no, generated_by)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'Bank Transfer', 'Pending', ?, ?)
            ");
            $insStmt->execute([$s['id'], $mYear, $basic, $allowances, $deductions, $net, $payslipNo, $_SESSION['user_id'] ?? 1]);
            $generatedCount++;
        }
    }

    logAudit('GENERATE_PAYROLL', 'Payroll', 0, "Generated {$generatedCount} payroll entries for {$mYear}");
    setFlashMessage('success', "Payroll generated successfully for {$generatedCount} staff members!");
    header("Location: " . BASE_PATH . "/hr/payroll.php?month={$payMonth}&year={$payYear}");
    exit;
}

// Handle Mark as Paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_salary') {
    $payrollId = (int)$_POST['payroll_id'];
    $method = $_POST['payment_method'] ?? 'Bank Transfer';

    $stmt = $pdo->prepare("
        UPDATE payroll 
        SET status = 'Paid', payment_method = ?, payment_date = CURDATE()
        WHERE id = ?
    ");
    $stmt->execute([$method, $payrollId]);
    logAudit('PAY_SALARY', 'Payroll', $payrollId, "Marked salary paid for payroll ID {$payrollId}");
    setFlashMessage('success', "Salary payment recorded successfully!");
    header("Location: " . BASE_PATH . "/hr/payroll.php?month={$month}&year={$year}");
    exit;
}

// Fetch payroll records for selected month and year
$stmt = $pdo->prepare("
    SELECT p.*, s.staff_code, s.name as staff_name, s.phone, d.name as dept_name, des.title as designation_title
    FROM payroll p
    JOIN staff s ON p.employee_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN designations des ON s.designation_id = des.id
    WHERE p.month_year = ?
    ORDER BY s.name ASC
");
$stmt->execute([$selectedMonthYear]);
$payrolls = $stmt->fetchAll();

// Statistics
$totalPayrollAmount = 0;
$paidAmount = 0;
$unpaidAmount = 0;
foreach ($payrolls as $pr) {
    $totalPayrollAmount += (float)$pr['net_salary'];
    if ($pr['status'] === 'Paid') {
        $paidAmount += (float)$pr['net_salary'];
    } else {
        $unpaidAmount += (float)$pr['net_salary'];
    }
}

$pageTitle = "HR Staff Payroll";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/hr/employees.php">HR & Staff</a></li>
                <li class="breadcrumb-item active">Payroll</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-file-invoice-dollar text-primary me-2"></i> Monthly Staff Payroll</h3>
        <p class="text-muted small mb-0">Generate monthly salaries, track disbursement status, and print employee payslips</p>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" onsubmit="return confirm('Generate payroll for all active staff for this month?');">
            <input type="hidden" name="action" value="generate_payroll">
            <input type="hidden" name="pay_month" value="<?= $month ?>">
            <input type="hidden" name="pay_year" value="<?= $year ?>">
            <button type="submit" class="btn btn-primary rounded-pill px-3 shadow-sm">
                <i class="fas fa-calculator me-1"></i> Generate Payroll
            </button>
        </form>
        <a href="<?= BASE_PATH ?>/hr/employees.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fas fa-users me-1"></i> Employee Directory
        </a>
    </div>
</div>

<!-- Month Selector & KPI Cards -->
<div class="row g-3 mb-4 no-print">
    <div class="col-12 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
            <label class="small text-muted fw-bold mb-2">Select Pay Period</label>
            <form method="GET" class="d-flex gap-2">
                <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= sprintf('%02d', $m) ?>" <?= (int)$month === $m ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= (int)$year === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="col-4 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary-subtle text-primary h-100">
            <div class="small fw-semibold text-uppercase">Total Commitment</div>
            <div class="fs-4 fw-bold mt-1"><?= formatCurrency($totalPayrollAmount) ?></div>
            <div class="small opacity-75 mt-1"><?= count($payrolls) ?> Employees</div>
        </div>
    </div>
    <div class="col-4 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-success-subtle text-success h-100">
            <div class="small fw-semibold text-uppercase">Disbursed (Paid)</div>
            <div class="fs-4 fw-bold mt-1"><?= formatCurrency($paidAmount) ?></div>
            <div class="small opacity-75 mt-1">Processed Payments</div>
        </div>
    </div>
    <div class="col-4 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-danger-subtle text-danger h-100">
            <div class="small fw-semibold text-uppercase">Pending Disbursal</div>
            <div class="fs-4 fw-bold mt-1"><?= formatCurrency($unpaidAmount) ?></div>
            <div class="small opacity-75 mt-1">Awaiting Payout</div>
        </div>
    </div>
</div>

<!-- Payroll Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">
            Payroll Sheet for <?= date('F Y', mktime(0, 0, 0, (int)$month, 10, (int)$year)) ?>
        </h6>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 no-print">
            <i class="fas fa-print me-1"></i> Print Sheet
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
            <thead class="table-light">
                <tr>
                    <th>Slip #</th>
                    <th>Staff Name & Designation</th>
                    <th>Department</th>
                    <th>Basic</th>
                    <th>Allowances</th>
                    <th>Deductions</th>
                    <th class="table-primary text-end">Net Pay</th>
                    <th class="text-center">Status</th>
                    <th class="text-end no-print">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payrolls)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fas fa-money-check-alt fa-3x opacity-25 mb-3 d-block"></i>
                            <p class="mb-2">No payroll generated for <?= date('F Y', mktime(0, 0, 0, (int)$month, 10, (int)$year)) ?> yet.</p>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="generate_payroll">
                                <input type="hidden" name="pay_month" value="<?= $month ?>">
                                <input type="hidden" name="pay_year" value="<?= $year ?>">
                                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">
                                    <i class="fas fa-magic me-1"></i> Auto Generate Now
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payrolls as $p): ?>
                    <tr>
                        <td class="fw-bold text-primary font-monospace"><?= e($p['payslip_no']) ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($p['staff_name']) ?></div>
                            <small class="text-muted"><?= e($p['staff_code']) ?> &bull; <?= e($p['designation_title'] ?: 'Staff') ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= e($p['dept_name'] ?: 'Academics') ?></span></td>
                        <td><?= formatCurrency($p['basic_salary']) ?></td>
                        <td class="text-success">+<?= formatCurrency($p['allowances']) ?></td>
                        <td class="text-danger">-<?= formatCurrency($p['deductions']) ?></td>
                        <td class="table-primary fw-bold text-end text-primary"><?= formatCurrency($p['net_salary']) ?></td>
                        <td class="text-center">
                            <?php if ($p['status'] === 'Paid'): ?>
                                <span class="badge bg-success-subtle text-success px-2 py-1"><i class="fas fa-check-circle me-1"></i> Paid</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning px-2 py-1"><i class="fas fa-clock me-1"></i> Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end no-print">
                            <div class="btn-group btn-group-sm">
                                <?php if ($p['status'] !== 'Paid'): ?>
                                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#payModal<?= $p['id'] ?>" title="Record Payment">
                                        <i class="fas fa-hand-holding-usd"></i> Pay
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-secondary" onclick="printPayslip(<?= htmlspecialchars(json_encode($p)) ?>)" title="Print Payslip">
                                    <i class="fas fa-receipt"></i> Slip
                                </button>
                            </div>

                            <!-- Pay Modal -->
                            <div class="modal fade text-start" id="payModal<?= $p['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <form method="POST" class="modal-content rounded-4 border-0 shadow">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold">Disburse Salary</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="pay_salary">
                                            <input type="hidden" name="payroll_id" value="<?= $p['id'] ?>">
                                            <p class="text-muted small">Paying <?= e($p['staff_name']) ?> (<?= formatCurrency($p['net_salary']) ?>)</p>
                                            
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold">Payment Method</label>
                                                <select name="payment_method" class="form-select">
                                                    <option value="Bank Transfer">Bank Transfer</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Cheque">Cheque</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success rounded-pill px-4">Confirm Payment</button>
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

<!-- Modal Payslip Viewer / Printable Dialog -->
<div class="modal fade" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow p-4" id="payslipPrintArea">
            <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
                <div>
                    <h4 class="fw-bold text-primary mb-1"><?= e(getSetting('school_name', 'EduManage International Academy')) ?></h4>
                    <div class="text-muted small"><?= e(getSetting('school_address', '100 Academic Way, Education City')) ?></div>
                    <div class="fw-bold text-dark mt-2">SALARY PAYSLIP</div>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary px-3 py-2 fs-6" id="slipPeriod">Month Year</span>
                    <div class="text-muted small mt-2">Payslip Generated: <?= date('d M Y') ?></div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <div class="small text-muted">Employee Name:</div>
                    <div class="fw-bold" id="slipName">-</div>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Staff Code / Designation:</div>
                    <div class="fw-bold" id="slipEmpId">-</div>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Department:</div>
                    <div class="fw-bold" id="slipDept">-</div>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Payment Status:</div>
                    <div class="fw-bold text-success" id="slipStatus">-</div>
                </div>
            </div>

            <table class="table table-bordered mb-4">
                <thead class="table-light">
                    <tr>
                        <th>Earnings (Allowances)</th>
                        <th class="text-end">Amount</th>
                        <th>Deductions</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic Salary</td>
                        <td class="text-end" id="slipBasic">-</td>
                        <td>Tax & Provident</td>
                        <td class="text-end text-danger" id="slipDeduction">-</td>
                    </tr>
                    <tr>
                        <td>Allowances</td>
                        <td class="text-end text-success" id="slipAllowance">-</td>
                        <td>Other Deductions</td>
                        <td class="text-end text-danger">$0.00</td>
                    </tr>
                    <tr class="table-primary fw-bold">
                        <td>Total Earnings</td>
                        <td class="text-end" id="slipTotalEarn">-</td>
                        <td>Net Payable Salary</td>
                        <td class="text-end text-primary" id="slipNet">-</td>
                    </tr>
                </tbody>
            </table>

            <div class="d-flex justify-content-between align-items-end mt-5 pt-3 border-top">
                <div class="text-center" style="width: 200px;">
                    <div class="border-top border-dark pt-1 small fw-bold">Employee Signature</div>
                </div>
                <div class="text-center" style="width: 200px;">
                    <div class="border-top border-dark pt-1 small fw-bold">Finance / Bursar</div>
                </div>
            </div>

            <div class="text-end mt-4 no-print">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 ms-2" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print Payslip
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function printPayslip(data) {
    document.getElementById('slipPeriod').innerText = '<?= date('F', mktime(0, 0, 0, (int)$month, 10)) ?> <?= $year ?>';
    document.getElementById('slipName').innerText = data.staff_name;
    document.getElementById('slipEmpId').innerText = data.staff_code + ' / ' + (data.designation_title || 'Staff');
    document.getElementById('slipDept').innerText = data.dept_name || 'General';
    document.getElementById('slipStatus').innerText = data.status.toUpperCase() + (data.payment_method ? ' via ' + data.payment_method : '');
    
    document.getElementById('slipBasic').innerText = '$' + parseFloat(data.basic_salary).toFixed(2);
    document.getElementById('slipAllowance').innerText = '$' + parseFloat(data.allowances).toFixed(2);
    document.getElementById('slipDeduction').innerText = '$' + parseFloat(data.deductions).toFixed(2);
    document.getElementById('slipTotalEarn').innerText = '$' + (parseFloat(data.basic_salary) + parseFloat(data.allowances)).toFixed(2);
    document.getElementById('slipNet').innerText = '$' + parseFloat(data.net_salary).toFixed(2);

    const modal = new bootstrap.Modal(document.getElementById('payslipModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
