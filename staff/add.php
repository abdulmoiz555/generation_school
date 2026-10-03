<?php
/**
 * Add Staff Member
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('hr_manage');

$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$designations = $pdo->query("SELECT * FROM designations ORDER BY title ASC")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Session validation failed.";
    } else {
        $code = trim($_POST['staff_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $gender = $_POST['gender'] ?? 'Male';
        $dob = $_POST['dob'] ?? '1990-01-01';
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $deptId = (int)($_POST['department_id'] ?? 2);
        $desigId = (int)($_POST['designation_id'] ?? 4);
        $salary = (float)($_POST['salary'] ?? 0);
        $joiningDate = $_POST['joining_date'] ?? date('Y-m-d');
        $address = trim($_POST['address'] ?? '');

        if (empty($code) || empty($name) || empty($phone)) {
            $error = "Staff Code, Name, and Phone are required.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO staff (staff_code, name, gender, dob, phone, email, department_id, designation_id, salary, joining_date, address, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$code, $name, $gender, $dob, $phone, $email, $deptId, $desigId, $salary, $joiningDate, $address]);
            $newId = (int)$pdo->lastInsertId();
            logAudit('ADD_STAFF', 'Staff', $newId, "Added staff {$name} ({$code})");
            setFlashMessage('success', "Staff member '{$name}' created!");
            header("Location: " . BASE_PATH . "/staff/index.php");
            exit;
        }
    }
}

$sMax = (int)$pdo->query("SELECT MAX(id) FROM staff")->fetchColumn() + 1;
$suggestedCode = sprintf("STF-%04d", 2000 + $sMax);

$pageTitle = "Add Staff";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/staff/index.php">Staff</a></li>
                <li class="breadcrumb-item active">Add Member</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Register Staff Member</h3>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form action="<?= BASE_PATH ?>/staff/add.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Staff Code <span class="text-danger">*</span></label>
                    <input type="text" name="staff_code" class="form-control" value="<?= e($suggestedCode) ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date of Birth</label>
                    <input type="date" name="dob" class="form-control" value="1992-05-10">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" placeholder="+1 (555) ..." required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="staff@school.edu">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Monthly Salary ($)</label>
                    <input type="number" step="0.01" name="salary" class="form-control" placeholder="3200.00">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Department</label>
                    <select name="department_id" class="form-select">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Designation</label>
                    <select name="designation_id" class="form-select">
                        <?php foreach ($designations as $des): ?>
                            <option value="<?= $des['id'] ?>"><?= e($des['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Full residential street address"></textarea>
                </div>
            </div>

            <div class="text-end mt-4 pt-3 border-top">
                <a href="<?= BASE_PATH ?>/staff/index.php" class="btn btn-light border px-4 rounded-pill">Cancel</a>
                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> Save Staff Member
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
