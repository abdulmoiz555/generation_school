<?php
/**
 * Edit Staff Member
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('hr_manage');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    setFlashMessage('error', 'Staff member not found.');
    header("Location: " . BASE_PATH . "/staff/index.php");
    exit;
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$designations = $pdo->query("SELECT * FROM designations ORDER BY title ASC")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Session validation failed.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $gender = $_POST['gender'] ?? 'Male';
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $deptId = (int)($_POST['department_id'] ?? 2);
        $desigId = (int)($_POST['designation_id'] ?? 4);
        $salary = (float)($_POST['salary'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($phone)) {
            $error = "Name and Phone are required.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE staff SET 
                    name = ?, gender = ?, phone = ?, email = ?,
                    department_id = ?, designation_id = ?, salary = ?,
                    address = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $gender, $phone, $email, $deptId, $desigId, $salary, $address, $status, $id]);
            logAudit('EDIT_STAFF', 'Staff', $id, "Updated staff details for {$name}");
            setFlashMessage('success', "Staff member updated successfully!");
            header("Location: " . BASE_PATH . "/staff/view.php?id=" . $id);
            exit;
        }
    }
}

$pageTitle = "Edit Staff - " . $staff['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/staff/index.php">Staff</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Edit Staff: <?= e($staff['name']) ?></h3>
    </div>
    <a href="<?= BASE_PATH ?>/staff/view.php?id=<?= $staff['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Back to Profile
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form action="<?= BASE_PATH ?>/staff/edit.php?id=<?= $staff['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Staff Code</label>
                    <input type="text" class="form-control bg-light" value="<?= e($staff['staff_code']) ?>" readonly>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($staff['name']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="Male" <?= $staff['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $staff['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $staff['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" value="<?= e($staff['phone']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($staff['email']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Monthly Salary ($)</label>
                    <input type="number" step="0.01" name="salary" class="form-control" value="<?= $staff['salary'] ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Department</label>
                    <select name="department_id" class="form-select">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $staff['department_id'] == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Designation</label>
                    <select name="designation_id" class="form-select">
                        <?php foreach ($designations as $des): ?>
                            <option value="<?= $des['id'] ?>" <?= $staff['designation_id'] == $des['id'] ? 'selected' : '' ?>><?= e($des['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $staff['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $staff['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="resigned" <?= $staff['status'] === 'resigned' ? 'selected' : '' ?>>Resigned</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= e($staff['address']) ?>">
                </div>
            </div>

            <div class="text-end mt-4 pt-3 border-top">
                <a href="<?= BASE_PATH ?>/staff/view.php?id=<?= $staff['id'] ?>" class="btn btn-light border px-4 rounded-pill">Cancel</a>
                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> Update Staff Member
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
