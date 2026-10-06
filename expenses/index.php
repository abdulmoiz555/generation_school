<?php
/**
 * Institutional Expenses & Expenditure Management
 * Generation Model School
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('finance_manage');

// Ensure default categories exist if empty
$catCount = (int)$pdo->query("SELECT COUNT(*) FROM expense_categories")->fetchColumn();
if ($catCount === 0) {
    $defaultCats = [
        ['Electricity & Utilities', 'WAPDA electricity, gas, water and generator diesel bills'],
        ['Campus Maintenance & Repair', 'Building upkeep, plumbing, paint, and electrical repairs'],
        ['Stationary & Printing', 'Printing of exams, fee challans, office files, and supplies'],
        ['Staff Welfare & Refreshments', 'Tea, refreshments, staff meetings, and employee welfare'],
        ['Sports & Extracurricular', 'Sports equipment, grounds upkeep, annual day events'],
        ['Software, IT & Lab Expenses', 'Computer hardware, internet line, lab chemicals, software'],
        ['Marketing & Advertising', 'Admissions banners, flyers, social media ads, prospectus'],
        ['Miscellaneous Office Expenses', 'General daily campus expenditures']
    ];
    $cStmt = $pdo->prepare("INSERT INTO expense_categories (name, description) VALUES (?, ?)");
    foreach ($defaultCats as $dc) {
        $cStmt->execute($dc);
    }
}

// Add Expense Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $title = trim($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!empty($title) && $categoryId > 0 && $amount > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO expenses (category_id, title, description, amount, expense_date, payment_method, reference_no, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$categoryId, $title, $description, $amount, $expenseDate, $paymentMethod, $referenceNo, $_SESSION['user_id'] ?? 1]);
        logAudit('ADD_EXPENSE', 'Finance', (int)$pdo->lastInsertId(), "Recorded expense: {$title} (PKR {$amount})");
        setFlashMessage('success', "Expense '{$title}' of PKR " . number_format($amount) . " recorded successfully!");
    }
    header("Location: " . BASE_PATH . "/expenses/index.php");
    exit;
}

// Add Category Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $catName = trim($_POST['category_name'] ?? '');
    $catDesc = trim($_POST['category_description'] ?? '');
    if (!empty($catName)) {
        $stmt = $pdo->prepare("INSERT INTO expense_categories (name, description) VALUES (?, ?)");
        $stmt->execute([$catName, $catDesc]);
        setFlashMessage('success', "Expense Category '{$catName}' added!");
    }
    header("Location: " . BASE_PATH . "/expenses/index.php");
    exit;
}

// Fetch Categories
$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY name ASC")->fetchAll();

// Filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$catFilter = isset($_GET['category_id']) && !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$methodFilter = $_GET['payment_method'] ?? '';

$sql = "
    SELECT e.*, ec.name as category_name, u.full_name as recorder_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.expense_date BETWEEN ? AND ?
";
$params = [$startDate, $endDate];

if ($catFilter) {
    $sql .= " AND e.category_id = ?";
    $params[] = $catFilter;
}
if (!empty($methodFilter)) {
    $sql .= " AND e.payment_method = ?";
    $params[] = $methodFilter;
}

$sql .= " ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Statistics
$totalAmount = array_sum(array_column($expenses, 'amount'));
$todayDate = date('Y-m-d');
$todayAmount = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = '{$todayDate}'")->fetchColumn();
$thisMonthStart = date('Y-m-01');
$monthAmount = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date >= '{$thisMonthStart}'")->fetchColumn();

// If expenses table has fewer than 3 records, seed realistic institutional expense entries
if (count($expenses) === 0 && (int)$pdo->query("SELECT COUNT(*) FROM expenses")->fetchColumn() === 0) {
    $firstCat = $categories[0]['id'] ?? 1;
    $secondCat = $categories[1]['id'] ?? 1;
    $thirdCat = $categories[2]['id'] ?? 1;
    $fourthCat = $categories[3]['id'] ?? 1;

    $sample = [
        [$firstCat, 'WAPDA Electricity Bill - Main Campus', 'Monthly campus utility connection', 34500, date('Y-m-05'), 'Bank', 'VCH-WAP-01'],
        [$secondCat, 'Generator Diesel Refill & Service', '50 Liters diesel and filter change', 14200, date('Y-m-08'), 'Cash', 'VCH-DSL-02'],
        [$thirdCat, 'Exam Sheets & Answer Books Printing', '5,000 serialized examination sheets', 22000, date('Y-m-12'), 'Cheque', 'VCH-PRN-03'],
        [$fourthCat, 'Faculty Refreshments & Tea Fund', 'Monthly staff tea and biscuits supply', 6500, date('Y-m-14'), 'Cash', 'VCH-TEA-04'],
        [$firstCat, 'High-Speed Optical Fiber Internet', 'Monthly institutional broadband subscription', 5500, date('Y-m-15'), 'Online', 'VCH-NET-05'],
    ];
    $insExp = $pdo->prepare("INSERT INTO expenses (category_id, title, description, amount, expense_date, payment_method, reference_no, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($sample as $smp) {
        $insExp->execute($smp);
    }
    // Re-query
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();
    $totalAmount = array_sum(array_column($expenses, 'amount'));
    $monthAmount = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date >= '{$thisMonthStart}'")->fetchColumn();
}

$pageTitle = "Institutional Expenses & Accounts";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Expenses</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fas fa-file-invoice-dollar text-danger me-2"></i>Institutional Expenses Management
        </h3>
        <p class="text-muted small mb-0">Track campus utility bills, maintenance, stationary purchase, events, and generate printable expenditure reports</p>
    </div>

    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-print me-1"></i> Print Report
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-folder-plus me-1"></i> + New Category
        </button>
        <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fas fa-plus-circle me-1"></i> + Record Expense
        </button>
    </div>
</div>

<!-- Stats Widgets -->
<div class="row g-3 mb-4 no-print">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger me-3">
                    <i class="fas fa-calendar-alt fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">This Month Expenses</h6>
                    <h3 class="fw-bold text-dark mb-0">PKR <?= number_format($monthAmount) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning me-3">
                    <i class="fas fa-clock fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Today's Expenses</h6>
                    <h3 class="fw-bold text-dark mb-0">PKR <?= number_format($todayAmount) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                    <i class="fas fa-filter fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Filtered Total</h6>
                    <h3 class="fw-bold text-danger mb-0">PKR <?= number_format($totalAmount) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                    <i class="fas fa-list-ol fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Total Vouchers</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($expenses) ?> Entries</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Form -->
<div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/expenses/index.php" method="GET" class="row g-2 align-items-center">
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Category</label>
                <select name="category_id" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Payment Mode</label>
                <select name="payment_method" class="form-select">
                    <option value="">All Methods</option>
                    <option value="Cash" <?= $methodFilter === 'Cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="Bank" <?= $methodFilter === 'Bank' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="Cheque" <?= $methodFilter === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                    <option value="Online" <?= $methodFilter === 'Online' ? 'selected' : '' ?>>Online / Card</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary rounded-3 w-100 py-2">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Expenses Table & Printable Sheet -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold text-dark mb-0">Expense Ledger & Vouchers</h5>
            <small class="text-muted">Showing expenditures from <?= date('d-M-Y', strtotime($startDate)) ?> to <?= date('d-M-Y', strtotime($endDate)) ?></small>
        </div>
        <div class="text-end">
            <span class="fs-6 fw-bold text-danger">Total: PKR <?= number_format($totalAmount) ?></span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Voucher / Ref #</th>
                        <th>Expense Title</th>
                        <th>Category</th>
                        <th>Payment Method</th>
                        <th>Recorded By</th>
                        <th class="text-end pe-4">Amount (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No expense records found for the selected date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td class="text-muted fw-semibold"><?= date('d-M-Y', strtotime($exp['expense_date'])) ?></td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= e($exp['reference_no'] ?: 'VCH-'.$exp['id']) ?></span></td>
                                <td>
                                    <strong class="text-dark d-block"><?= e($exp['title']) ?></strong>
                                    <?php if (!empty($exp['description'])): ?>
                                        <small class="text-muted"><?= e($exp['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($exp['category_name']) ?></span></td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="fas fa-money-check-alt me-1"></i><?= e($exp['payment_method']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= e($exp['recorder_name'] ?: 'Finance Office') ?></td>
                                <td class="text-end pe-4 fw-bold text-danger fs-6">
                                    PKR <?= number_format((float)$exp['amount']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="6" class="fw-bold text-uppercase ps-4">TOTAL INSTITUTIONAL EXPENDITURE</td>
                        <td class="text-end pe-4 fw-bold text-danger fs-5">PKR <?= number_format($totalAmount) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Record New Expense -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-danger"><i class="fas fa-plus-circle me-2"></i>Record New Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/expenses/index.php" method="POST">
                <input type="hidden" name="action" value="add_expense">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Expense Title / Payee <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Generator Fuel Refill or Science Lab Glassware" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="e.g. 15000" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="Cash">Cash Payment</option>
                                <option value="Bank">Bank Transfer</option>
                                <option value="Cheque">Bank Cheque</option>
                                <option value="Online">Online Banking / Card</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Voucher / Receipt Reference #</label>
                        <input type="text" name="reference_no" class="form-control" value="VCH-<?= date('ym') ?>-<?= rand(100, 999) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Expense Description & Notes</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Provide any details, invoice number, or vendor specifics"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Create Expense Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/expenses/index.php" method="POST">
                <input type="hidden" name="action" value="add_category">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" class="form-control" placeholder="e.g. Science Fair Expenses" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <input type="text" name="category_description" class="form-control" placeholder="Short description of items in this category">
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
