<?php
/**
 * Student Document Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_view');

$studentId = (int)($_GET['student_id'] ?? 0);
$student = getStudent($studentId);

if (!$student) {
    setFlashMessage('error', 'Student not found.');
    header("Location: " . BASE_PATH . "/students/index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('student_edit')) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Session validation failed.";
    } else {
        $title = trim($_POST['title'] ?? '');
        if (empty($title) || empty($_FILES['document']['name'])) {
            $error = "Please provide both document title and file.";
        } else {
            $uploadRes = handleFileUpload($_FILES['document'], 'documents', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);
            if ($uploadRes['success']) {
                $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                $stmt = $pdo->prepare("INSERT INTO student_documents (student_id, title, file_path, file_type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$studentId, $title, $uploadRes['filepath'], $ext]);
                logAudit('UPLOAD_DOCUMENT', 'Students', $studentId, "Uploaded document '{$title}'");
                setFlashMessage('success', "Document '{$title}' uploaded successfully!");
                header("Location: " . BASE_PATH . "/students/documents.php?student_id=" . $studentId);
                exit;
            } else {
                $error = $uploadRes['error'];
            }
        }
    }
}

// Fetch documents
$docStmt = $pdo->prepare("SELECT * FROM student_documents WHERE student_id = ? ORDER BY created_at DESC");
$docStmt->execute([$studentId]);
$documents = $docStmt->fetchAll();

$pageTitle = "Documents - " . $student['first_name'] . ' ' . $student['last_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/students/index.php">Students</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/students/view.php?id=<?= $student['id'] ?>"><?= e($student['first_name']) ?></a></li>
                <li class="breadcrumb-item active">Documents</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Documents Repository: <?= e($student['first_name'] . ' ' . $student['last_name']) ?></h3>
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

<div class="row g-4">
    <!-- Upload Form -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold text-primary mb-0"><i class="fas fa-file-upload me-2"></i> Upload New Document</h6>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_PATH ?>/students/documents.php?student_id=<?= $student['id'] ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Document Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Birth Certificate, Previous School Leaving Certificate" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Select File (PDF, PNG, JPG) <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" required>
                        <div class="form-text small">Maximum file size: 5MB.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 shadow-sm">
                        <i class="fas fa-cloud-upload-alt me-2"></i> Upload Document
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Document List -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-folder-open me-2 text-secondary"></i> Existing Documents (<?= count($documents) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Format</th>
                                <th>Date</th>
                                <th class="text-end">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No documents on file for this student.</td></tr>
                            <?php else: ?>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td class="fw-semibold">
                                            <i class="fas fa-file-pdf text-danger me-2"></i>
                                            <?= e($doc['title']) ?>
                                        </td>
                                        <td><span class="badge bg-light text-dark border text-uppercase"><?= e($doc['file_type']) ?></span></td>
                                        <td><?= formatDate($doc['created_at']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_PATH ?>/<?= e($doc['file_path']) ?>" class="btn btn-sm btn-outline-primary" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
