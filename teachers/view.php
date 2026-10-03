<?php
/**
 * Teacher Profile & Assigned Load (Section 12)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('teacher_view');

$teacherId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT t.*, d.name as dept_name, des.title as desig_title
    FROM teachers t
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN designations des ON t.designation_id = des.id
    WHERE t.id = ?
");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    setFlashMessage('error', 'Teacher not found.');
    header("Location: " . BASE_PATH . "/teachers/index.php");
    exit;
}

// Assigned subjects
$subStmt = $pdo->prepare("
    SELECT s.*, c.class_name
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.teacher_id = ?
");
$subStmt->execute([$teacherId]);
$subjects = $subStmt->fetchAll();

// Class Teacher of Section?
$ctStmt = $pdo->prepare("
    SELECT sec.*, c.class_name 
    FROM sections sec 
    JOIN classes c ON sec.class_id = c.id 
    WHERE sec.class_teacher_id = ?
");
$ctStmt->execute([$teacherId]);
$classTeacherSections = $ctStmt->fetchAll();

$pageTitle = "Teacher Profile - " . $teacher['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/teachers/index.php">Teachers</a></li>
                <li class="breadcrumb-item active"><?= e($teacher['name']) ?></li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Teacher Profile</h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('teacher_edit')): ?>
            <a href="<?= BASE_PATH ?>/teachers/edit.php?id=<?= $teacher['id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fas fa-edit me-1"></i> Edit Profile
            </a>
        <?php endif; ?>
        <a href="<?= BASE_PATH ?>/teachers/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            Back
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
            <div class="avatar-initials mx-auto mb-3" style="width: 90px; height: 90px; font-size: 2.2rem; background: linear-gradient(135deg, #10b981, #06b6d4);">
                <?= strtoupper(substr($teacher['name'], 0, 1)) ?>
            </div>
            <h4 class="fw-bold text-dark mb-1"><?= e($teacher['name']) ?></h4>
            <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-3">
                <?= e($teacher['teacher_code']) ?>
            </div>

            <div class="border-top pt-3 text-start small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Department:</span>
                    <strong><?= e($teacher['dept_name'] ?? 'Academics') ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Designation:</span>
                    <strong><?= e($teacher['desig_title'] ?? 'Teacher') ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Qualification:</span>
                    <span><?= e($teacher['qualification']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Experience:</span>
                    <span><?= $teacher['experience_years'] ?> Years</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Joining Date:</span>
                    <span><?= formatDate($teacher['joining_date']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Phone:</span>
                    <strong><?= e($teacher['phone']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Email:</span>
                    <span><?= e($teacher['email']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <!-- Assigned Subjects & Classes -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold text-primary mb-0"><i class="fas fa-book me-2"></i> Teaching Load & Assigned Subjects</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Code</th>
                                <th>Class</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjects)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No course subjects currently assigned.</td></tr>
                            <?php else: ?>
                                <?php foreach ($subjects as $sub): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= e($sub['subject_name']) ?></td>
                                        <td><code><?= e($sub['subject_code']) ?></code></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($sub['class_name']) ?></span></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary border"><?= e($sub['subject_type']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Class Teacher Role -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-chalkboard-teacher me-2 text-success"></i> Class Teacher Assignments</h6>
            </div>
            <div class="card-body p-3">
                <?php if (empty($classTeacherSections)): ?>
                    <p class="text-muted small mb-0">Not currently assigned as Class Teacher for any section.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($classTeacherSections as $cts): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                                <i class="fas fa-check-circle me-1"></i> Class Teacher: <?= e($cts['class_name'] . ' - ' . $cts['section_name']) ?> (<?= e($cts['room_no'] ?: 'Room TBD') ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
