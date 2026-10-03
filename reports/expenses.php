<?php
/**
 * Operating Expense Ledger & Management (Section 22)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_manage');

$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY name ASC")->fetchAll();

// Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $catId = (int)$_POST['category_id'];
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $amt = (float)$_POST['amount'];
    $date = $_POST['expense_date'] ?? date('Y-m-d');
    $method = $_POST['payment_method'] ?? 'Cash';
    $ref = trim($_POST['reference_no'] ?? '');

    if ($catId && !empty($title) && $amt > 0) {
        $stmt = $pdo->prepare("INSERT INTO expenses (category_id, title, description, amount, expense_date, payment_method, reference_no, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$catId, $title, $desc, $amt, $date, $method, $ref, getCurrentUserId()]);
        logAudit('ADD_EXPENSE', 'Expenses', (int)$pdo->lastInsertId(), "Recorded expense: {$title} ({$amt})");
        setFlashMessage('success', "Expense voucher recorded successfully!");
    }
    header("Location: " . BASE_PATH . "/reports/expenses.php");
    exit;
}

$expenses = $pdo->query("
    SELECT ex.*, c.name as category_name, u.full_name as author_name
    FROM expenses ex
    JOIN expense_categories c ON ex.category_id = c.id
    LEFT JOIN users u ON ex.created_by = u.id
    ORDER BY ex.expense_date DESC
")->fetchAll();

$totalExpenses = array_sum(array_column($expenses, 'amount'));

$pageTitle = "Expense Ledger";
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
        <h3 class="fw-bold text-dark mb-0">Campus Operating Expenses (Section 22)</h3>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-print me-1"></i> Print Statement
        </button>
        <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fas fa-plus me-1"></i> + Record Expense
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Total Operating Expenditure: <span class="text-danger fs-5"><?= formatCurrency($totalExpenses) ?></span></span>
        <span class="badge bg-danger rounded-pill"><?= count($expenses) ?> Entries</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Expense Particulars</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Reference #</th>
                        <th>Entered By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $ex): ?>
                        <tr>
                            <td><?= formatDate($ex['expense_date']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($ex['category_name']) ?></span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($ex['title']) ?></div>
                                <small class="text-muted"><?= e($ex['description'] ?: '-') ?></small>
                            </td>
                            <td class="fw-bold text-danger fs-6"><?= formatCurrency($ex['amount']) ?></td>
                            <td><span class="badge bg-secondary-subtle text-secondary border"><?= e($ex['payment_method']) ?></span></td>
                            <td><code><?= e($ex['reference_no'] ?: '-') ?></code></td>
                            <td><span class="small text-muted"><?= e($ex['author_name'] ?: 'Staff') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Record Expense Voucher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/reports/expenses.php" method="POST">
                <input type="hidden" name="action" value="add_expense">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Expense Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Generator Fuel or Science Lab Consumables" required>
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
                            <label class="form-label small fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="150.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank Transfer</option>
                                <option value="Online">Online</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reference # (Voucher / Bill #)</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="INV-2026-99">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description / Notes</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Itemized expense notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
