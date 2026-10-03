<?php
/**
 * Add New Teacher (Section 12)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('teacher_add');

$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$designations = $pdo->query("SELECT * FROM designations ORDER BY title ASC")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Session validation failed.";
    } else {
        $code = trim($_POST['teacher_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $gender = $_POST['gender'] ?? 'Male';
        $dob = $_POST['dob'] ?? '';
        $cnic = trim($_POST['cnic'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $experience = (int)($_POST['experience_years'] ?? 0);
        $joiningDate = $_POST['joining_date'] ?? date('Y-m-d');
        $deptId = (int)($_POST['department_id'] ?? 1);
        $desigId = (int)($_POST['designation_id'] ?? 1);
        $salary = (float)($_POST['salary'] ?? 0);
        $address = trim($_POST['address'] ?? '');

        if (empty($code) || empty($name) || empty($phone) || empty($email)) {
            $error = "Please fill in all mandatory fields.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO teachers (teacher_code, name, gender, dob, cnic, phone, email, qualification, experience_years, joining_date, department_id, designation_id, salary, address, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$code, $name, $gender, $dob, $cnic, $phone, $email, $qualification, $experience, $joiningDate, $deptId, $desigId, $salary, $address]);
                $newId = (int)$pdo->lastInsertId();
                logAudit('ADD_TEACHER', 'Teachers', $newId, "Added teacher {$name} ({$code})");
                setFlashMessage('success', "Teacher '{$name}' registered successfully!");
                header("Location: " . BASE_PATH . "/teachers/view.php?id=" . $newId);
                exit;
            } catch (Exception $e) {
                error_log("Teacher add error: " . $e->getMessage());
                $error = "Failed to add teacher. The email or code may already exist.";
            }
        }
    }
}

// Generate teacher code
$tMax = (int)$pdo->query("SELECT MAX(id) FROM teachers")->fetchColumn() + 1;
$suggestedCode = sprintf("TCH-%04d", 1000 + $tMax);

$pageTitle = "Add Teacher";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/teachers/index.php">Teachers</a></li>
                <li class="breadcrumb-item active">New Teacher</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Register New Teacher</h3>
    </div>
    <a href="<?= BASE_PATH ?>/teachers/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Back to Roster
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
        <form action="<?= BASE_PATH ?>/teachers/add.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Teacher Code <span class="text-danger">*</span></label>
                    <input type="text" name="teacher_code" class="form-control" value="<?= e($suggestedCode) ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Dr. / Mr. / Ms. Full Name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="dob" class="form-control" value="1990-01-01" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">CNIC / National ID</label>
                    <input type="text" name="cnic" class="form-control" placeholder="42101-1234567-1">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Phone Contact <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" placeholder="+1 (555) ..." required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="teacher@school.edu" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Qualification</label>
                    <input type="text" name="qualification" class="form-control" placeholder="e.g. M.Sc. Mathematics, B.Ed">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Experience (Years)</label>
                    <input type="number" name="experience_years" class="form-control" value="5">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Department</label>
                    <select name="department_id" class="form-select">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Designation</label>
                    <select name="designation_id" class="form-select">
                        <?php foreach ($designations as $des): ?>
                            <option value="<?= $des['id'] ?>"><?= e($des['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Monthly Salary ($)</label>
                    <input type="number" step="0.01" name="salary" class="form-control" placeholder="4500.00">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Residential street address">
                </div>
            </div>

            <div class="text-end mt-4 pt-3 border-top">
                <a href="<?= BASE_PATH ?>/teachers/index.php" class="btn btn-light border px-4 rounded-pill">Cancel</a>
                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> Register Teacher
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
