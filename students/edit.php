<?php
/**
 * Edit Student Profile
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_edit');

$studentId = (int)($_GET['id'] ?? 0);
$student = getStudent($studentId);

if (!$student) {
    setFlashMessage('error', 'Student not found.');
    header("Location: " . BASE_PATH . "/students/index.php");
    exit;
}

$allClasses = getAllClasses();
$allSections = getSections($student['class_id']);
$allParents = $pdo->query("SELECT id, father_name, phone FROM parents ORDER BY father_name ASC")->fetchAll();
$allSessions = getAllSessions();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Session security check failed. Please refresh.";
    } else {
        $rollNo = trim($_POST['roll_no'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $gender = $_POST['gender'] ?? 'Male';
        $dob = $_POST['dob'] ?? '';
        $cnicBform = trim($_POST['cnic_bform'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $classId = (int)($_POST['class_id'] ?? $student['class_id']);
        $sectionId = (int)($_POST['section_id'] ?? $student['section_id']);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $bloodGroup = trim($_POST['blood_group'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($firstName) || empty($lastName) || empty($dob) || empty($classId) || empty($sectionId)) {
            $error = "Please fill in all mandatory fields.";
        } else {
            $photoName = $student['photo'];
            if (!empty($_FILES['photo']['name'])) {
                $uploadRes = handleFileUpload($_FILES['photo'], 'students');
                if ($uploadRes['success']) {
                    $photoName = $uploadRes['filename'];
                }
            }

            try {
                $updateStmt = $pdo->prepare("
                    UPDATE students SET
                        roll_no = ?, first_name = ?, last_name = ?, gender = ?, dob = ?,
                        cnic_bform = ?, phone = ?, email = ?, address = ?, city = ?, province = ?,
                        class_id = ?, section_id = ?, parent_id = ?, blood_group = ?,
                        photo = ?, status = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $rollNo, $firstName, $lastName, $gender, $dob,
                    $cnicBform, $phone, $email, $address, $city, $province,
                    $classId, $sectionId, $parentId, $bloodGroup,
                    $photoName, $status, $studentId
                ]);

                logAudit('EDIT_STUDENT', 'Students', $studentId, "Updated profile for {$firstName} {$lastName}");
                setFlashMessage('success', "Student record updated successfully!");
                header("Location: " . BASE_PATH . "/students/view.php?id=" . $studentId);
                exit;
            } catch (Exception $e) {
                error_log("Failed to update student: " . $e->getMessage());
                $error = "Database error while updating student profile.";
            }
        }
    }
}

$pageTitle = "Edit Student - " . $student['first_name'] . ' ' . $student['last_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/students/index.php">Students</a></li>
                <li class="breadcrumb-item active">Edit Profile</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Edit Student: <?= e($student['first_name'] . ' ' . $student['last_name']) ?></h3>
    </div>
    <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $student['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Back to Profile
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="<?= BASE_PATH ?>/students/edit.php?id=<?= $student['id'] ?>" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3">
            <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-user-edit me-2"></i> Update Student Details</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Admission Number</label>
                    <input type="text" class="form-control bg-light" value="<?= e($student['admission_no']) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Roll Number</label>
                    <input type="text" name="roll_no" class="form-control" value="<?= e($student['roll_no']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" value="<?= e($student['first_name']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" value="<?= e($student['last_name']) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Class <span class="text-danger">*</span></label>
                    <select name="class_id" class="form-select class-select" data-target-section="editSectionSelect" required>
                        <?php foreach ($allClasses as $cls): ?>
                            <option value="<?= $cls['id'] ?>" <?= $student['class_id'] == $cls['id'] ? 'selected' : '' ?>>
                                <?= e($cls['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Section <span class="text-danger">*</span></label>
                    <select name="section_id" id="editSectionSelect" class="form-select" required>
                        <?php foreach ($allSections as $sec): ?>
                            <option value="<?= $sec['id'] ?>" <?= $student['section_id'] == $sec['id'] ? 'selected' : '' ?>>
                                <?= e($sec['section_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="Male" <?= $student['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $student['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $student['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="dob" class="form-control" value="<?= e($student['dob']) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <option value="">Unknown</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= $student['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Enrollment Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $student['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $student['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="promoted" <?= $student['status'] === 'promoted' ? 'selected' : '' ?>>Promoted</option>
                        <option value="graduated" <?= $student['status'] === 'graduated' ? 'selected' : '' ?>>Graduated</option>
                        <option value="transferred" <?= $student['status'] === 'transferred' ? 'selected' : '' ?>>Transferred</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Parent / Guardian</label>
                    <select name="parent_id" class="form-select">
                        <option value="">-- No Parent Assigned --</option>
                        <?php foreach ($allParents as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $student['parent_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['father_name']) ?> (<?= e($p['phone']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Update Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($student['phone']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($student['email']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">B-Form / ID</label>
                    <input type="text" name="cnic_bform" class="form-control" value="<?= e($student['cnic_bform']) ?>">
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-semibold small">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= e($student['address']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">City</label>
                    <input type="text" name="city" class="form-control" value="<?= e($student['city']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Province</label>
                    <input type="text" name="province" class="form-control" value="<?= e($student['province']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="text-end mb-5">
        <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $student['id'] ?>" class="btn btn-light border px-4 rounded-pill">Cancel</a>
        <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
            <i class="fas fa-save me-2"></i> Update Changes
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
