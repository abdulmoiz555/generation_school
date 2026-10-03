<?php
/**
 * Inventory Stock & Supplies (Section 29)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('inventory_manage');

$categories = $pdo->query("SELECT * FROM inventory_categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY company_name ASC")->fetchAll();

// Add Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $catId = (int)$_POST['category_id'];
    $supId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $unit = trim($_POST['unit'] ?? 'Units');
    $stock = (int)$_POST['current_stock'];
    $minStock = (int)$_POST['min_stock'];
    $price = (float)$_POST['purchase_price'];
    $loc = trim($_POST['location'] ?? '');

    $stmt = $pdo->prepare("
        INSERT INTO inventory_items (name, code, category_id, supplier_id, unit, current_stock, min_stock, purchase_price, location)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $code, $catId, $supId, $unit, $stock, $minStock, $price, $loc]);
    logAudit('ADD_INVENTORY', 'Inventory', (int)$pdo->lastInsertId(), "Added inventory item {$name}");
    setFlashMessage('success', "Item '{$name}' created in inventory!");
    header("Location: " . BASE_PATH . "/inventory/items.php");
    exit;
}

$items = $pdo->query("
    SELECT i.*, c.name as category_name, s.company_name
    FROM inventory_items i
    JOIN inventory_categories c ON i.category_id = c.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    ORDER BY i.id ASC
")->fetchAll();

$pageTitle = "Inventory Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Inventory</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Campus Inventory & Stock (Section 29)</h3>
    </div>
    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
        <i class="fas fa-plus me-1"></i> + New Inventory Item
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item Name</th>
                        <th>Item Code</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Unit</th>
                        <th>Stock Available</th>
                        <th>Unit Price</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): 
                        $isLow = ($it['current_stock'] <= $it['min_stock']);
                    ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= e($it['name']) ?></td>
                            <td><code><?= e($it['code']) ?></code></td>
                            <td><span class="badge bg-light text-dark border"><?= e($it['category_name']) ?></span></td>
                            <td><?= e($it['company_name'] ?: 'Local Vendor') ?></td>
                            <td><?= e($it['unit']) ?></td>
                            <td>
                                <strong class="<?= $isLow ? 'text-danger fs-6' : 'text-success' ?>">
                                    <?= $it['current_stock'] ?>
                                </strong>
                                <small class="text-muted">(Min: <?= $it['min_stock'] ?>)</small>
                            </td>
                            <td><?= formatCurrency($it['purchase_price']) ?></td>
                            <td><span class="small text-muted"><?= e($it['location'] ?: 'Store') ?></span></td>
                            <td>
                                <?php if ($isLow): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Low Stock
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        In Stock
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/inventory/items.php" method="POST">
                <input type="hidden" name="action" value="add_item">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Science Lab Microscopes" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Item Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. LAB-MIC-01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Unit</label>
                            <input type="text" name="unit" class="form-control" value="Units" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Supplier</label>
                            <select name="supplier_id" class="form-select">
                                <option value="">-- Vendor --</option>
                                <?php foreach ($suppliers as $sp): ?>
                                    <option value="<?= $sp['id'] ?>"><?= e($sp['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Initial Stock</label>
                            <input type="number" name="current_stock" class="form-control" value="10" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Min Stock Alert</label>
                            <input type="number" name="min_stock" class="form-control" value="3" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Unit Price ($)</label>
                            <input type="number" step="0.01" name="purchase_price" class="form-control" value="25.00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Storage Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Room 204 or Main Warehouse">
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
