<?php
/**
 * Edit Teacher Profile
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('teacher_edit');

$teacherId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    setFlashMessage('error', 'Teacher not found.');
    header("Location: " . BASE_PATH . "/teachers/index.php");
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
        $dob = $_POST['dob'] ?? '';
        $cnic = trim($_POST['cnic'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $experience = (int)($_POST['experience_years'] ?? 0);
        $deptId = (int)($_POST['department_id'] ?? 1);
        $desigId = (int)($_POST['designation_id'] ?? 1);
        $salary = (float)($_POST['salary'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($phone) || empty($email)) {
            $error = "Name, Phone, and Email are required.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE teachers SET 
                        name = ?, gender = ?, dob = ?, cnic = ?, phone = ?, email = ?,
                        qualification = ?, experience_years = ?, department_id = ?, designation_id = ?,
                        salary = ?, address = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $gender, $dob, $cnic, $phone, $email, $qualification, $experience, $deptId, $desigId, $salary, $address, $status, $teacherId]);
                logAudit('EDIT_TEACHER', 'Teachers', $teacherId, "Updated teacher {$name}");
                setFlashMessage('success', "Teacher profile updated successfully!");
                header("Location: " . BASE_PATH . "/teachers/view.php?id=" . $teacherId);
                exit;
            } catch (Exception $e) {
                error_log("Teacher edit error: " . $e->getMessage());
                $error = "Failed to update profile. Email may conflict.";
            }
        }
    }
}

$pageTitle = "Edit Teacher - " . $teacher['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/teachers/index.php">Teachers</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Edit Teacher: <?= e($teacher['name']) ?></h3>
    </div>
    <a href="<?= BASE_PATH ?>/teachers/view.php?id=<?= $teacher['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
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
        <form action="<?= BASE_PATH ?>/teachers/edit.php?id=<?= $teacher['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Teacher Code</label>
                    <input type="text" class="form-control bg-light" value="<?= e($teacher['teacher_code']) ?>" readonly>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($teacher['name']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="Male" <?= $teacher['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $teacher['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $teacher['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date of Birth</label>
                    <input type="date" name="dob" class="form-control" value="<?= e($teacher['dob']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">CNIC / ID</label>
                    <input type="text" name="cnic" class="form-control" value="<?= e($teacher['cnic']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" value="<?= e($teacher['phone']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= e($teacher['email']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Qualification</label>
                    <input type="text" name="qualification" class="form-control" value="<?= e($teacher['qualification']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Experience (Yrs)</label>
                    <input type="number" name="experience_years" class="form-control" value="<?= $teacher['experience_years'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Department</label>
                    <select name="department_id" class="form-select">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $teacher['department_id'] == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Designation</label>
                    <select name="designation_id" class="form-select">
                        <?php foreach ($designations as $des): ?>
                            <option value="<?= $des['id'] ?>" <?= $teacher['designation_id'] == $des['id'] ? 'selected' : '' ?>><?= e($des['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Monthly Salary ($)</label>
                    <input type="number" step="0.01" name="salary" class="form-control" value="<?= $teacher['salary'] ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $teacher['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $teacher['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="resigned" <?= $teacher['status'] === 'resigned' ? 'selected' : '' ?>>Resigned</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= e($teacher['address']) ?>">
                </div>
            </div>

            <div class="text-end mt-4 pt-3 border-top">
                <a href="<?= BASE_PATH ?>/teachers/view.php?id=<?= $teacher['id'] ?>" class="btn btn-light border px-4 rounded-pill">Cancel</a>
                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
