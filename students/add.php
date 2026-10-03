<?php
/**
 * Add New Student (Admissions)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_add');

$activeSession = getActiveSession();
$allClasses = getAllClasses();
$allParents = $pdo->query("SELECT id, father_name, mother_name, phone, cnic FROM parents ORDER BY father_name ASC")->fetchAll();
$allSessions = getAllSessions();

$error = '';
$generatedAdmNo = generateAdmissionNumber();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Session security check failed. Please refresh and try again.";
    } else {
        // Collect & sanitize inputs
        $admissionNo = trim($_POST['admission_no'] ?? $generatedAdmNo);
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
        $admissionDate = $_POST['admission_date'] ?? date('Y-m-d');
        $sessionId = (int)($_POST['session_id'] ?? $activeSession['id']);
        $classId = (int)($_POST['class_id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $bloodGroup = trim($_POST['blood_group'] ?? '');
        $previousSchool = trim($_POST['previous_school'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        // If creating new parent on the fly
        if (!$parentId && !empty($_POST['new_father_name'])) {
            $fName = trim($_POST['new_father_name']);
            $fPhone = trim($_POST['new_parent_phone'] ?? '');
            $fCnic = trim($_POST['new_parent_cnic'] ?? '');
            $pStmt = $pdo->prepare("INSERT INTO parents (father_name, phone, cnic, address, city) VALUES (?, ?, ?, ?, ?)");
            $pStmt->execute([$fName, $fPhone, $fCnic, $address, $city]);
            $parentId = (int)$pdo->lastInsertId();
        }

        // Validate essentials
        if (empty($firstName) || empty($lastName) || empty($dob) || empty($classId) || empty($sectionId)) {
            $error = "Please fill in all mandatory fields marked with an asterisk (*).";
        } else {
            // Check uniqueness of admission number
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admission_no = ?");
            $checkStmt->execute([$admissionNo]);
            if ($checkStmt->fetchColumn() > 0) {
                $error = "The Admission Number '{$admissionNo}' is already assigned to another student.";
            } else {
                // Photo upload handling
                $photoName = 'default-student.png';
                if (!empty($_FILES['photo']['name'])) {
                    $uploadRes = handleFileUpload($_FILES['photo'], 'students');
                    if ($uploadRes['success']) {
                        $photoName = $uploadRes['filename'];
                    }
                }

                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO students (
                            admission_no, roll_no, first_name, last_name, gender, dob, 
                            cnic_bform, phone, email, address, city, province, 
                            admission_date, session_id, class_id, section_id, parent_id, 
                            blood_group, previous_school, photo, status
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, 
                            ?, ?, ?, ?, ?, ?, 
                            ?, ?, ?, ?, ?, 
                            ?, ?, ?, 'active'
                        )
                    ");
                    $insertStmt->execute([
                        $admissionNo, $rollNo, $firstName, $lastName, $gender, $dob,
                        $cnicBform, $phone, $email, $address, $city, $province,
                        $admissionDate, $sessionId, $classId, $sectionId, $parentId,
                        $bloodGroup, $previousSchool, $photoName
                    ]);

                    $newStudentId = (int)$pdo->lastInsertId();
                    logAudit('ADD_STUDENT', 'Students', $newStudentId, "Enrolled student {$firstName} {$lastName} ({$admissionNo})");
                    setFlashMessage('success', "Student {$firstName} {$lastName} has been successfully registered!");
                    header("Location: " . BASE_PATH . "/students/view.php?id=" . $newStudentId);
                    exit;
                } catch (Exception $e) {
                    error_log("Failed to add student: " . $e->getMessage());
                    $error = "Something went wrong while saving student data. Please verify inputs.";
                }
            }
        }
    }
}

$pageTitle = "Enroll New Student";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/students/index.php">Students</a></li>
                <li class="breadcrumb-item active">New Admission</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Student Admission Form</h3>
    </div>
    <a href="<?= BASE_PATH ?>/students/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Back to Directory
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="<?= BASE_PATH ?>/students/add.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <!-- 1. Academic & Enrollment Details -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3">
            <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-graduation-cap me-2"></i> 1. Academic & Class Assignment</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Admission Number <span class="text-danger">*</span></label>
                    <input type="text" name="admission_no" class="form-control" value="<?= e($generatedAdmNo) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Roll Number</label>
                    <input type="text" name="roll_no" class="form-control" placeholder="e.g. 101">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Admission Date <span class="text-danger">*</span></label>
                    <input type="date" name="admission_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Academic Session <span class="text-danger">*</span></label>
                    <select name="session_id" class="form-select" required>
                        <?php foreach ($allSessions as $sess): ?>
                            <option value="<?= $sess['id'] ?>" <?= ($sess['is_current'] == 1) ? 'selected' : '' ?>>
                                <?= e($sess['session_name']) ?> <?= ($sess['is_current'] == 1) ? '(Current)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Class <span class="text-danger">*</span></label>
                    <select name="class_id" class="form-select class-select" data-target-section="studentSectionSelect" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach ($allClasses as $cls): ?>
                            <option value="<?= $cls['id'] ?>"><?= e($cls['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Section <span class="text-danger">*</span></label>
                    <select name="section_id" id="studentSectionSelect" class="form-select" required>
                        <option value="">-- Choose Section --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Previous School Attended</label>
                    <input type="text" name="previous_school" class="form-control" placeholder="Name of previous institute">
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Personal Student Details -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3">
            <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-user me-2"></i> 2. Personal Information</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="dob" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">B-Form / National ID</label>
                    <input type="text" name="cnic_bform" class="form-control" placeholder="e.g. 42101-1234567-1">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <option value="">Unknown</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Student Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Student Phone (Optional)</label>
                    <input type="text" name="phone" class="form-control" placeholder="+1 (555) ...">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Student Email (Optional)</label>
                    <input type="email" name="email" class="form-control" placeholder="student@example.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">City & Province</label>
                    <div class="input-group">
                        <input type="text" name="city" class="form-control" placeholder="City">
                        <input type="text" name="province" class="form-control" placeholder="Province">
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Residential Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Full residential street address"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Parent & Guardian Association -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3">
            <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-users me-2"></i> 3. Parent / Guardian Details</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Select Existing Parent Profile</label>
                    <select name="parent_id" class="form-select">
                        <option value="">-- Choose Existing Parent (Or fill details below) --</option>
                        <?php foreach ($allParents as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= e($p['father_name']) ?> (Phone: <?= e($p['phone']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="fas fa-info-circle me-1 text-primary"></i> If this is the student's first family member in school, enter the father/guardian name below to automatically register the parent profile.
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">New Father / Guardian Name</label>
                    <input type="text" name="new_father_name" class="form-control" placeholder="Father or Guardian Full Name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Parent Contact Phone</label>
                    <input type="text" name="new_parent_phone" class="form-control" placeholder="+1 (555) ...">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Parent CNIC / ID</label>
                    <input type="text" name="new_parent_cnic" class="form-control" placeholder="National Identity Card">
                </div>
            </div>
        </div>
    </div>

    <div class="text-end mb-5">
        <a href="<?= BASE_PATH ?>/students/index.php" class="btn btn-light border px-4 py-2 me-2 rounded-pill">Cancel</a>
        <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill fw-semibold shadow-sm">
            <i class="fas fa-save me-2"></i> Save Admission & Register Student
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
