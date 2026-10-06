<?php
/**
 * School Stationary & Uniform Store Management
 * Generation Model School
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('inventory_manage');

// Handle adding new stationary item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_stationary') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $itemType = trim($_POST['item_type'] ?? 'Stationary');
    $unit = trim($_POST['unit'] ?? 'Piece');
    $currentStock = (int)($_POST['current_stock'] ?? 0);
    $minStock = (int)($_POST['min_stock'] ?? 10);
    $costPrice = (float)($_POST['purchase_price'] ?? 0);
    $salePrice = (float)($_POST['sale_price'] ?? 0);

    // Ensure Stationary category exists
    $catStmt = $pdo->query("SELECT id FROM inventory_categories WHERE name LIKE '%Station%' LIMIT 1");
    $cat = $catStmt->fetch();
    if ($cat) {
        $catId = (int)$cat['id'];
    } else {
        $pdo->query("INSERT INTO inventory_categories (name, description) VALUES ('School Stationary & Books', 'Textbooks, notebooks, uniforms, and student supplies')");
        $catId = (int)$pdo->lastInsertId();
    }

    if (!empty($name) && !empty($code)) {
        $stmt = $pdo->prepare("
            INSERT INTO inventory_items (name, code, category_id, unit, current_stock, min_stock, purchase_price, location)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $code, $catId, $unit, $currentStock, $minStock, $costPrice, $itemType]);
        setFlashMessage('success', "Stationary item '{$name}' added to inventory!");
    }
    header("Location: " . BASE_PATH . "/inventory/stationary.php");
    exit;
}

// Handle issuing/selling stationary to student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sell_stationary') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $studentId = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
    $qty = (int)($_POST['quantity'] ?? 1);
    $ref = trim($_POST['reference'] ?? 'Counter Sale');
    $notes = trim($_POST['notes'] ?? '');

    if ($itemId > 0 && $qty > 0) {
        // Check current stock
        $chk = $pdo->prepare("SELECT current_stock, name FROM inventory_items WHERE id = ?");
        $chk->execute([$itemId]);
        $it = $chk->fetch();

        if ($it && (int)$it['current_stock'] >= $qty) {
            $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?")->execute([$qty, $itemId]);
            $ins = $pdo->prepare("
                INSERT INTO stock_transactions (item_id, transaction_type, quantity, reference, notes, created_by)
                VALUES (?, 'Out', ?, ?, ?, ?)
            ");
            $ins->execute([$itemId, $qty, $ref, $notes, $_SESSION['user_id'] ?? 1]);
            setFlashMessage('success', "Issued {$qty}x {$it['name']} successfully!");
        } else {
            setFlashMessage('error', "Insufficient stock available for {$it['name']}!");
        }
    }
    header("Location: " . BASE_PATH . "/inventory/stationary.php");
    exit;
}

// Ensure initial stationary seed items exist if table is fresh
$countItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn();
if ($countItems === 0) {
    // Insert category
    $pdo->query("INSERT INTO inventory_categories (name, description) VALUES ('Stationary & Uniforms', 'School syllabus, notebooks, and uniforms')");
    $catId = (int)$pdo->lastInsertId();

    $seed = [
        ['Oxford English Book 1-5', 'ST-ENG-01', $catId, 'Set', 120, 20, 1400, 'Books'],
        ['Urdu Qawaid & Insha', 'ST-URD-01', $catId, 'Piece', 200, 30, 450, 'Books'],
        ['Standard School Notebook (Four Lines)', 'ST-NOTE-4L', $catId, 'Piece', 500, 50, 120, 'Notebooks'],
        ['Standard School Notebook (Math Grid)', 'ST-NOTE-SQ', $catId, 'Piece', 450, 50, 120, 'Notebooks'],
        ['School Diary & Academic Planner', 'ST-DRY-26', $catId, 'Piece', 350, 40, 250, 'Supplies'],
        ['Complete Uniform Boys (Shirt + Trouser)', 'UNIF-B-01', $catId, 'Suit', 85, 15, 2200, 'Uniform'],
        ['Complete Uniform Girls (Sash + Frock)', 'UNIF-G-01', $catId, 'Suit', 90, 15, 2300, 'Uniform'],
        ['Geometry Box & Mathematical Instruments', 'ST-GEOM-01', $catId, 'Box', 150, 25, 380, 'Supplies'],
        ['School Tie & Embroidered Badge Set', 'ST-TIE-01', $catId, 'Set', 220, 30, 250, 'Uniform'],
        ['Art & Drawing Sketch Book (A3)', 'ST-ART-01', $catId, 'Piece', 180, 25, 200, 'Supplies']
    ];
    $stmt = $pdo->prepare("INSERT INTO inventory_items (name, code, category_id, unit, current_stock, min_stock, purchase_price, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($seed as $s) {
        $stmt->execute($s);
    }
}

// Fetch stationary items
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM inventory_items WHERE 1=1";
$params = [];
if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR code LIKE ? OR location LIKE ?)";
    $like = "%{$search}%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY location ASC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Fetch students for sale modal
$allStudents = $pdo->query("SELECT id, first_name, last_name, admission_no, roll_no FROM students WHERE status = 'active' ORDER BY first_name ASC LIMIT 50")->fetchAll();

// Fetch recent sales/issues
$recentSales = $pdo->query("
    SELECT t.*, i.name as item_name, i.code as item_code, i.purchase_price
    FROM stock_transactions t
    JOIN inventory_items i ON t.item_id = i.id
    WHERE t.transaction_type = 'Out'
    ORDER BY t.created_at DESC LIMIT 15
")->fetchAll();

$pageTitle = "School Stationary & Uniforms";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Inventory</li>
                <li class="breadcrumb-item active">School Stationary</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fas fa-pencil-ruler text-primary me-2"></i>School Stationary & Uniform Store
        </h3>
        <p class="text-muted small mb-0">Manage institutional textbooks, student notebooks, school uniforms, diaries, and issue records</p>
    </div>

    <div class="d-flex gap-2">
        <button type="button" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#sellStationaryModal">
            <i class="fas fa-shopping-cart me-1"></i> Issue / Sell to Student
        </button>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addStationaryModal">
            <i class="fas fa-plus me-1"></i> + New Stationary Item
        </button>
    </div>
</div>

<!-- Stationary Overview Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                    <i class="fas fa-boxes fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Total Items</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($items) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">In Stock Units</h6>
                    <?php 
                        $totalStock = array_sum(array_column($items, 'current_stock'));
                    ?>
                    <h3 class="fw-bold text-dark mb-0"><?= number_format($totalStock) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning me-3">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Low Stock Alert</h6>
                    <?php 
                        $lowStockCount = 0;
                        foreach ($items as $it) {
                            if ((int)$it['current_stock'] <= (int)$it['min_stock']) $lowStockCount++;
                        }
                    ?>
                    <h3 class="fw-bold text-dark mb-0"><?= $lowStockCount ?> Items</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info me-3">
                    <i class="fas fa-receipt fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Recent Issues</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($recentSales) ?> Transactions</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search Filter -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/inventory/stationary.php" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search stationary by item name, item code (e.g. ST-NOTE), or category..." value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= BASE_PATH ?>/inventory/stationary.php" class="btn btn-outline-secondary border-start-0">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary rounded-3 w-100">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stationary Items Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h5 class="fw-bold text-dark mb-0">Stationary & Uniform Stock Inventory</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th>Type / Category</th>
                        <th>Unit Price (PKR)</th>
                        <th>Available Stock</th>
                        <th>Stock Status</th>
                        <th class="text-end pe-4">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No stationary items found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $it): 
                            $isLow = (int)$it['current_stock'] <= (int)$it['min_stock'];
                            $isOut = (int)$it['current_stock'] <= 0;
                        ?>
                            <tr>
                                <td><span class="badge bg-light text-primary border font-monospace"><?= e($it['code']) ?></span></td>
                                <td class="fw-bold text-dark"><?= e($it['name']) ?></td>
                                <td><span class="badge bg-secondary-subtle text-secondary border"><?= e($it['location'] ?: 'Stationary') ?></span></td>
                                <td class="fw-bold text-dark">PKR <?= number_format((float)$it['purchase_price']) ?></td>
                                <td>
                                    <strong class="fs-6 <?= $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-success') ?>">
                                        <?= number_format((int)$it['current_stock']) ?>
                                    </strong>
                                    <small class="text-muted"> <?= e($it['unit']) ?></small>
                                </td>
                                <td>
                                    <?php if ($isOut): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Out of Stock</span>
                                    <?php elseif ($isLow): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Low Stock (Min: <?= $it['min_stock'] ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#sellStationaryModal" data-item-id="<?= $it['id'] ?>">
                                        <i class="fas fa-shopping-cart me-1"></i> Issue
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Issue / Sales Ledger -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h5 class="fw-bold text-dark mb-0">Recent Stationary Issues & Counter Dispatches</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Item</th>
                        <th>Quantity Issued</th>
                        <th>Reference / Student</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentSales)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No dispatches recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td class="text-muted"><?= date('d-M-Y H:i', strtotime($sale['created_at'])) ?></td>
                                <td class="fw-semibold text-dark"><?= e($sale['item_name']) ?> (<?= e($sale['item_code']) ?>)</td>
                                <td><span class="badge bg-primary rounded-pill px-3"><?= $sale['quantity'] ?> units</span></td>
                                <td><strong class="text-dark"><?= e($sale['reference'] ?: 'Counter Sale') ?></strong></td>
                                <td class="text-muted small"><?= e($sale['notes'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add New Stationary Item -->
<div class="modal fade" id="addStationaryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Stationary / Uniform Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/inventory/stationary.php" method="POST">
                <input type="hidden" name="action" value="add_stationary">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Item Title <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Mathematics Notebook or Summer Uniform Set" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Item Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. ST-MAT-01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Category / Type</label>
                            <select name="item_type" class="form-select">
                                <option value="Books">Textbooks</option>
                                <option value="Notebooks">Notebooks / Copies</option>
                                <option value="Uniform">Uniform & Badges</option>
                                <option value="Supplies">Stationary Supplies</option>
                                <option value="Art">Art & Crafts</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Price (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="purchase_price" class="form-control" placeholder="e.g. 250" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Unit of Measure</label>
                            <input type="text" name="unit" class="form-control" value="Piece">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Initial Stock Quantity</label>
                            <input type="number" name="current_stock" class="form-control" value="50">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Low Stock Alert Level</label>
                            <input type="number" name="min_stock" class="form-control" value="10">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Add to Store</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Issue / Sell Stationary to Student -->
<div class="modal fade" id="sellStationaryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Issue / Sell Stationary to Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/inventory/stationary.php" method="POST">
                <input type="hidden" name="action" value="sell_stationary">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Stationary Item <span class="text-danger">*</span></label>
                        <select name="item_id" class="form-select" required>
                            <?php foreach ($items as $it): ?>
                                <option value="<?= $it['id'] ?>">
                                    <?= e($it['name']) ?> (Stock: <?= $it['current_stock'] ?>) &bull; PKR <?= number_format($it['purchase_price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Student (Optional)</label>
                        <select name="student_id" class="form-select">
                            <option value="">-- General Counter Sale --</option>
                            <?php foreach ($allStudents as $st): ?>
                                <option value="<?= $st['id'] ?>">
                                    <?= e($st['first_name'] . ' ' . $st['last_name']) ?> (<?= e($st['admission_no']) ?> &bull; Roll: <?= e($st['roll_no'] ?: '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Quantity to Issue <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Receipt / Reference No</label>
                            <input type="text" name="reference" class="form-control" value="INV-<?= date('md') ?>-<?= rand(100, 999) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Remarks / Payment Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Paid in Cash / Billed to Student Account">
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4">Confirm Dispatch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
