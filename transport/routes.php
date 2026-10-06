<?php
/**
 * School Transport Management
 * Routes, Vehicles, Drivers, and Student Transport Allocations
 * Generation Model School
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('transport_manage');

// Handle Add Route
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_route') {
    $name = trim($_POST['route_name'] ?? '');
    $pickup = trim($_POST['pickup_point'] ?? '');
    $drop = trim($_POST['drop_point'] ?? 'Generation Model School Campus');
    $fee = (float)($_POST['monthly_fee'] ?? 0);
    $vehId = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;

    if (!empty($name) && !empty($pickup)) {
        $stmt = $pdo->prepare("INSERT INTO transport_routes (route_name, pickup_point, drop_point, monthly_fee, vehicle_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $pickup, $drop, $fee, $vehId]);
        logAudit('ADD_ROUTE', 'Transport', (int)$pdo->lastInsertId(), "Created transport route {$name}");
        setFlashMessage('success', "Transport route '{$name}' created!");
    }
    header("Location: " . BASE_PATH . "/transport/routes.php");
    exit;
}

// Handle Add Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_vehicle') {
    $vehNo = trim($_POST['vehicle_no'] ?? '');
    $regNo = trim($_POST['registration_no'] ?? '');
    $vehType = trim($_POST['vehicle_type'] ?? 'School Van');
    $capacity = (int)($_POST['capacity'] ?? 20);
    $driverId = !empty($_POST['driver_id']) ? (int)$_POST['driver_id'] : null;

    if (!empty($vehNo)) {
        $stmt = $pdo->prepare("INSERT INTO vehicles (vehicle_no, registration_no, vehicle_type, capacity, driver_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$vehNo, $regNo, $vehType, $capacity, $driverId]);
        setFlashMessage('success', "Vehicle '{$vehNo}' registered successfully!");
    }
    header("Location: " . BASE_PATH . "/transport/routes.php");
    exit;
}

// Handle Assign Student to Transport
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_student') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $routeId = (int)($_POST['route_id'] ?? 0);
    $activeSessionId = (int)($pdo->query("SELECT id FROM academic_sessions WHERE is_current = 1 LIMIT 1")->fetchColumn() ?: 1);

    if ($studentId > 0 && $routeId > 0) {
        $stmt = $pdo->prepare("INSERT INTO student_transport (student_id, route_id, session_id, start_date, status) VALUES (?, ?, ?, CURDATE(), 'active')");
        $stmt->execute([$studentId, $routeId, $activeSessionId]);
        setFlashMessage('success', "Student registered for school transport route!");
    }
    header("Location: " . BASE_PATH . "/transport/routes.php");
    exit;
}

// Ensure default drivers and vehicles exist if empty
$vCount = (int)$pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
if ($vCount === 0) {
    // Seed drivers
    $dStmt = $pdo->prepare("INSERT INTO drivers (name, phone, license_no, license_expiry, status) VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 2 YEAR), 'active')");
    $dStmt->execute(['Muhammad Aslam', '0300-1234567', 'LHR-COMM-9921']);
    $dId1 = (int)$pdo->lastInsertId();
    $dStmt->execute(['Rashid Mehmood', '0321-9876543', 'LHR-COMM-4412']);
    $dId2 = (int)$pdo->lastInsertId();

    // Seed vehicles
    $vStmt = $pdo->prepare("INSERT INTO vehicles (vehicle_no, registration_no, vehicle_type, capacity, driver_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $vStmt->execute(['VAN-01', 'LEA-2024-1102', 'Toyota HiAce (Coaster)', 22, $dId1]);
    $vId1 = (int)$pdo->lastInsertId();
    $vStmt->execute(['BUS-02', 'LEB-2023-8841', 'Hino Medium Bus', 45, $dId2]);
    $vId2 = (int)$pdo->lastInsertId();

    // Seed routes
    $rStmt = $pdo->prepare("INSERT INTO transport_routes (route_name, pickup_point, drop_point, monthly_fee, vehicle_id) VALUES (?, ?, ?, ?, ?)");
    $rStmt->execute(['Route 1: Gulberg & Model Town Loop', 'Main Market, Gulberg III & C-Block Model Town', 'Generation Model School Campus', 3500, $vId1]);
    $rStmt->execute(['Route 2: Johar Town & Faisal Town Express', 'PIA Road, Shaukat Khanum Chowk & Akbar Chowk', 'Generation Model School Campus', 4000, $vId2]);
}

$vehicles = $pdo->query("SELECT v.*, d.name as driver_name, d.phone as driver_phone FROM vehicles v LEFT JOIN drivers d ON v.driver_id = d.id ORDER BY v.id ASC")->fetchAll();
$routes = $pdo->query("
    SELECT r.*, v.vehicle_no, v.vehicle_type,
           (SELECT COUNT(*) FROM student_transport st WHERE st.route_id = r.id AND st.status = 'active') as student_count
    FROM transport_routes r 
    LEFT JOIN vehicles v ON r.vehicle_id = v.id 
    ORDER BY r.id ASC
")->fetchAll();
$drivers = $pdo->query("SELECT * FROM drivers ORDER BY id ASC")->fetchAll();

// Enrolled transport students
$transportStudents = $pdo->query("
    SELECT st.*, s.first_name, s.last_name, s.admission_no, s.roll_no, c.class_name, sec.section_name,
           r.route_name, r.monthly_fee, v.vehicle_no, p.phone as father_phone
    FROM student_transport st
    JOIN students s ON st.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents p ON s.parent_id = p.id
    JOIN transport_routes r ON st.route_id = r.id
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    WHERE st.status = 'active'
    ORDER BY s.first_name ASC
")->fetchAll();

$allActiveStudents = $pdo->query("SELECT id, first_name, last_name, admission_no, roll_no FROM students WHERE status = 'active' ORDER BY first_name ASC LIMIT 60")->fetchAll();

$pageTitle = "School Transport Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Operations</li>
                <li class="breadcrumb-item active">Transport</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fas fa-bus text-primary me-2"></i>School Transport Management
        </h3>
        <p class="text-muted small mb-0">Manage institutional fleet, pickup routes, drivers, and student bus pass allocations</p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#assignStudentModal">
            <i class="fas fa-user-plus me-1"></i> Register Student to Route
        </button>
        <button type="button" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
            <i class="fas fa-truck-pickup me-1"></i> + New Vehicle
        </button>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addRouteModal">
            <i class="fas fa-route me-1"></i> + New Route
        </button>
    </div>
</div>

<!-- Transport Overview Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                    <i class="fas fa-route fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Active Routes</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($routes) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                    <i class="fas fa-bus-alt fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Fleet Vehicles</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($vehicles) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning me-3">
                    <i class="fas fa-id-card fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Licensed Drivers</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($drivers) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info me-3">
                    <i class="fas fa-user-graduate fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Transport Users</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($transportStudents) ?> Students</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Routes List -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-route me-2 text-primary"></i> School Pickup & Drop Routes</h6>
                <span class="badge bg-primary rounded-pill"><?= count($routes) ?> Active</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Route Name</th>
                                <th>Pickup / Destination</th>
                                <th>Monthly Fee</th>
                                <th>Assigned Vehicle</th>
                                <th class="text-end pe-3">Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routes as $r): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= e($r['route_name']) ?></td>
                                    <td class="small">
                                        <div><i class="fas fa-map-marker-alt text-success me-1"></i> <?= e($r['pickup_point']) ?></div>
                                        <div class="text-muted"><i class="fas fa-school text-primary me-1"></i> <?= e($r['drop_point']) ?></div>
                                    </td>
                                    <td class="fw-bold text-success">PKR <?= number_format((float)$r['monthly_fee']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="fas fa-bus me-1 text-primary"></i><?= e($r['vehicle_no'] ?: 'Unassigned') ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle fw-bold"><?= $r['student_count'] ?></span>
                                    </td>
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
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-bus me-2 text-success"></i> Fleet Vehicles & Drivers</h6>
                <span class="badge bg-success rounded-pill"><?= count($vehicles) ?> Vehicles</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vehicle #</th>
                                <th>Type</th>
                                <th>Seats</th>
                                <th>Driver & Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicles as $v): ?>
                                <tr>
                                    <td class="fw-bold">
                                        <span class="badge bg-light text-primary border font-monospace"><?= e($v['vehicle_no']) ?></span>
                                        <div class="small text-muted"><?= e($v['registration_no']) ?></div>
                                    </td>
                                    <td class="small"><?= e($v['vehicle_type']) ?></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary border"><?= $v['capacity'] ?> Seats</span></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($v['driver_name'] ?: 'None') ?></div>
                                        <small class="text-muted"><i class="fas fa-phone-alt me-1"></i><?= e($v['driver_phone'] ?: '-') ?></small>
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

<!-- Student Transport Allocations Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold text-dark mb-0">
                <i class="fas fa-user-check text-info me-2"></i>Students Enrolled in School Transport (<?= count($transportStudents) ?>)
            </h5>
            <small class="text-muted">Active students using institutional transit services</small>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Admission #</th>
                        <th>Student Name</th>
                        <th>Class & Section</th>
                        <th>Assigned Route</th>
                        <th>Assigned Vehicle</th>
                        <th>Monthly Transport Fee</th>
                        <th>Emergency Phone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transportStudents)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No students currently assigned to school transport.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transportStudents as $ts): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><?= e($ts['admission_no']) ?></span></td>
                                <td class="fw-bold text-dark"><?= e($ts['first_name'] . ' ' . $ts['last_name']) ?></td>
                                <td><span class="badge bg-primary-subtle text-primary border"><?= e($ts['class_name'] ?? 'Class') ?> - <?= e($ts['section_name'] ?? 'A') ?></span></td>
                                <td><i class="fas fa-route text-success me-1"></i><?= e($ts['route_name']) ?></td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= e($ts['vehicle_no'] ?: 'Bus') ?></span></td>
                                <td class="fw-bold text-success">PKR <?= number_format((float)$ts['monthly_fee']) ?></td>
                                <td class="small text-muted"><i class="fas fa-phone-alt me-1"></i><?= e($ts['father_phone'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Route -->
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
                        <input type="text" name="route_name" class="form-control" placeholder="e.g. Route 3: Wapda Town & Valencia Express" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pickup Points / Areas <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_point" class="form-control" placeholder="e.g. Valencia Gate 1, Wapda Town Roundabout" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Destination / Drop Point <span class="text-danger">*</span></label>
                        <input type="text" name="drop_point" class="form-control" value="Generation Model School Campus" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Monthly Fare (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="monthly_fee" class="form-control" value="3500.00" required>
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
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Create Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Vehicle -->
<div class="modal fade" id="addVehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Register School Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/transport/routes.php" method="POST">
                <input type="hidden" name="action" value="add_vehicle">
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Vehicle Code/ID <span class="text-danger">*</span></label>
                            <input type="text" name="vehicle_no" class="form-control" placeholder="e.g. BUS-03" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Number Plate Registration</label>
                            <input type="text" name="registration_no" class="form-control" placeholder="e.g. LEC-2025-5021">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Vehicle Type</label>
                            <select name="vehicle_type" class="form-select">
                                <option value="School Bus">School Bus</option>
                                <option value="Toyota HiAce (Van)">Toyota HiAce (Van)</option>
                                <option value="Coaster">Coaster</option>
                                <option value="Carry Daba">Suzuki Carry</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Passenger Capacity</label>
                            <input type="number" name="capacity" class="form-control" value="25">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Designated Driver</label>
                        <select name="driver_id" class="form-select">
                            <option value="">-- Select Driver --</option>
                            <?php foreach ($drivers as $dr): ?>
                                <option value="<?= $dr['id'] ?>"><?= e($dr['name']) ?> (<?= e($dr['phone']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Register Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Assign Student to Transport -->
<div class="modal fade" id="assignStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Register Student for Transport</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/transport/routes.php" method="POST">
                <input type="hidden" name="action" value="assign_student">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <?php foreach ($allActiveStudents as $st): ?>
                                <option value="<?= $st['id'] ?>">
                                    <?= e($st['first_name'] . ' ' . $st['last_name']) ?> (<?= e($st['admission_no']) ?> &bull; Roll: <?= e($st['roll_no'] ?: '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Transport Route <span class="text-danger">*</span></label>
                        <select name="route_id" class="form-select" required>
                            <?php foreach ($routes as $rt): ?>
                                <option value="<?= $rt['id'] ?>">
                                    <?= e($rt['route_name']) ?> &bull; PKR <?= number_format($rt['monthly_fee']) ?>/month
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4">Enroll in Transport</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
