<?php
/**
 * Edit Parent Profile
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_edit');

$parentId = (int)($_GET['id'] ?? 0);
$pStmt = $pdo->prepare("SELECT * FROM parents WHERE id = ?");
$pStmt->execute([$parentId]);
$parent = $pStmt->fetch();

if (!$parent) {
    setFlashMessage('error', 'Parent profile not found.');
    header("Location: " . BASE_PATH . "/parents/index.php");
    exit;
}

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
                UPDATE parents SET 
                    father_name = ?, mother_name = ?, guardian_name = ?, cnic = ?,
                    phone = ?, alternate_phone = ?, email = ?, occupation = ?,
                    address = ?, city = ?
                WHERE id = ?
            ");
            $stmt->execute([$fatherName, $motherName, $guardianName, $cnic, $phone, $altPhone, $email, $occupation, $address, $city, $parentId]);
            logAudit('EDIT_PARENT', 'Parents', $parentId, "Updated parent details for {$fatherName}");
            setFlashMessage('success', "Parent profile updated successfully!");
            header("Location: " . BASE_PATH . "/parents/view.php?id=" . $parentId);
            exit;
        }
    }
}

$pageTitle = "Edit Parent - " . $parent['father_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/parents/index.php">Parents</a></li>
                <li class="breadcrumb-item active">Edit Profile</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Edit Parent: <?= e($parent['father_name']) ?></h3>
    </div>
    <a href="<?= BASE_PATH ?>/parents/view.php?id=<?= $parent['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
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
        <form action="<?= BASE_PATH ?>/parents/edit.php?id=<?= $parent['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Father / Primary Guardian Name <span class="text-danger">*</span></label>
                    <input type="text" name="father_name" class="form-control" value="<?= e($parent['father_name']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Mother's Name</label>
                    <input type="text" name="mother_name" class="form-control" value="<?= e($parent['mother_name']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Guardian Name</label>
                    <input type="text" name="guardian_name" class="form-control" value="<?= e($parent['guardian_name']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Primary Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" value="<?= e($parent['phone']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Alternate Phone</label>
                    <input type="text" name="alternate_phone" class="form-control" value="<?= e($parent['alternate_phone']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">CNIC / ID Number</label>
                    <input type="text" name="cnic" class="form-control" value="<?= e($parent['cnic']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= e($parent['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Occupation / Profession</label>
                    <input type="text" name="occupation" class="form-control" value="<?= e($parent['occupation']) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold small">Residential Address</label>
                    <input type="text" name="address" class="form-control" value="<?= e($parent['address']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">City</label>
                    <input type="text" name="city" class="form-control" value="<?= e($parent['city']) ?>">
                </div>
            </div>

            <div class="text-end mt-4 pt-3 border-top">
                <a href="<?= BASE_PATH ?>/parents/view.php?id=<?= $parent['id'] ?>" class="btn btn-light border px-4 rounded-pill">Cancel</a>
                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
