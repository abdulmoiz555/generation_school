<?php
/**
 * Add New Parent
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_add');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Session validation failed.";
    } else {
        $fatherName = trim($_POST['father_name'] ?? '');
        $motherName = trim($_POST['mother_name'] ?? '');
        $guardianName = trim($_POST['guardian_name'] ?? '');
        $cnic = trim($_POST['cnic'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $altPhone = trim($_POST['alternate_phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');

        if (empty($fatherName) || empty($phone)) {
            $error = "Father/Guardian Name and Phone number are required.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO parents (father_name, mother_name, guardian_name, cnic, phone, alternate_phone, email, occupation, address, city)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fatherName, $motherName, $guardianName, $cnic, $phone, $altPhone, $email, $occupation, $address, $city]);
            $newId = (int)$pdo->lastInsertId();
            logAudit('ADD_PARENT', 'Parents', $newId, "Added parent profile for {$fatherName}");
            setFlashMessage('success', "Parent profile created successfully!");
            header("Location: " . BASE_PATH . "/parents/view.php?id=" . $newId);
            exit;
        }
    }
}

$pageTitle = "Add New Parent";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/parents/index.php">Parents</a></li>
                <li class="breadcrumb-item active">Add Parent</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Register Parent Profile</h3>
    </div>
    <a href="<?= BASE_PATH ?>/parents/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Back to List
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
        <form action="<?= BASE_PATH ?>/parents/add.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Father / Primary Guardian Name <span class="text-danger">*</span></label>
                    <input type="text" name="father_name" class="form-control" placeholder="Full Name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Mother's Name</label>
                    <input type="text" name="mother_name" class="form-control" placeholder="Mother Name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Guardian Name (If applicable)</label>
                    <input type="text" name="guardian_name" class="form-control" placeholder="Guardian Name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Primary Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" placeholder="+1 (555) ..." required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Alternate Phone</label>
                    <input type="text" name="alternate_phone" class="form-control" placeholder="Secondary Phone">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">CNIC / ID Number</label>
                    <input type="text" name="cnic" class="form-control" placeholder="e.g. 42101-1234567-1">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="parent@example.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Occupation / Profession</label>
                    <input type="text" name="occupation" class="form-control" placeholder="e.g. Engineer, Doctor, Business">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold small">Residential Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Full residential street address">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">City</label>
                    <input type="text" name="city" class="form-control" placeholder="Springfield">
                </div>
            </div>

            <div class="text-end mt-4 pt-3 border-top">
                <a href="<?= BASE_PATH ?>/parents/index.php" class="btn btn-light border px-4 rounded-pill">Cancel</a>
                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> Save Parent
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
