<?php
/**
 * Transport Management (Section 28)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('transport_manage');

$vehicles = $pdo->query("SELECT v.*, d.name as driver_name, d.phone as driver_phone FROM vehicles v LEFT JOIN drivers d ON v.driver_id = d.id ORDER BY v.id ASC")->fetchAll();
$routes = $pdo->query("SELECT r.*, v.vehicle_no, v.vehicle_type FROM transport_routes r LEFT JOIN vehicles v ON r.vehicle_id = v.id ORDER BY r.id ASC")->fetchAll();
$drivers = $pdo->query("SELECT * FROM drivers ORDER BY id ASC")->fetchAll();

// Add Route
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_route') {
    $name = trim($_POST['route_name'] ?? '');
    $pickup = trim($_POST['pickup_point'] ?? '');
    $drop = trim($_POST['drop_point'] ?? '');
    $fee = (float)$_POST['monthly_fee'];
    $vehId = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;

    $stmt = $pdo->prepare("INSERT INTO transport_routes (route_name, pickup_point, drop_point, monthly_fee, vehicle_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $pickup, $drop, $fee, $vehId]);
    logAudit('ADD_ROUTE', 'Transport', (int)$pdo->lastInsertId(), "Created transport route {$name}");
    setFlashMessage('success', "Transport route '{$name}' created!");
    header("Location: " . BASE_PATH . "/transport/routes.php");
    exit;
}

$pageTitle = "Transport Fleet & Routes";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Transport</li>
                <li class="breadcrumb-item active">Fleet & Routes</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Transport Fleet & Routes (Section 28)</h3>
    </div>
    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addRouteModal">
        <i class="fas fa-plus me-1"></i> + New Route
    </button>
</div>

<div class="row g-4 mb-4">
    <!-- Routes List -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold text-primary mb-0"><i class="fas fa-route me-2"></i> Active Transit Routes</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Route Name</th>
                                <th>Pickup / Destination</th>
                                <th>Monthly Charge</th>
                                <th>Vehicle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routes as $r): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= e($r['route_name']) ?></td>
                                    <td class="small">
                                        <div><i class="fas fa-map-marker-alt text-success me-1"></i> <?= e($r['pickup_point']) ?></div>
                                        <div class="text-muted"><i class="fas fa-flag-checkered text-danger me-1"></i> <?= e($r['drop_point']) ?></div>
                                    </td>
                                    <td class="fw-bold text-success"><?= formatCurrency($r['monthly_fee']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($r['vehicle_no'] ?: 'Unassigned') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Vehicles List -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-bus me-2 text-info"></i> School Vehicles & Drivers</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vehicle #</th>
                                <th>Type</th>
                                <th>Seats</th>
                                <th>Driver</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicles as $v): ?>
                                <tr>
                                    <td class="fw-bold"><code><?= e($v['vehicle_no']) ?></code></td>
                                    <td><?= e($v['vehicle_type']) ?></td>
                                    <td><?= $v['capacity'] ?></td>
                                    <td>
                                        <div><?= e($v['driver_name'] ?: 'None') ?></div>
                                        <small class="text-muted"><?= e($v['driver_phone'] ?: '') ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addRouteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Transport Route</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/transport/routes.php" method="POST">
                <input type="hidden" name="action" value="add_route">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Route Name <span class="text-danger">*</span></label>
                        <input type="text" name="route_name" class="form-control" placeholder="e.g. Route 3: East Ridge Shuttle" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pickup Area <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_point" class="form-control" placeholder="Start / Pickup location" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Drop Area <span class="text-danger">*</span></label>
                        <input type="text" name="drop_point" class="form-control" value="Springfield Academy Campus" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Monthly Fare ($)</label>
                            <input type="number" step="0.01" name="monthly_fee" class="form-control" value="60.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Assign Vehicle</label>
                            <select name="vehicle_id" class="form-select">
                                <option value="">-- Choose Vehicle --</option>
                                <?php foreach ($vehicles as $veh): ?>
                                    <option value="<?= $veh['id'] ?>"><?= e($veh['vehicle_no']) ?> (<?= e($veh['vehicle_type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
