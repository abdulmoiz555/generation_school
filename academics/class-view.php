<?php
/**
 * Class Details Dashboard
 * Shows details of all students and teachers assigned (Class Teacher & Subject Teachers),
 * with batch fee challan printing and individual student challan generation.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$classId = (int)($_GET['id'] ?? 1);
$cls = getClass($classId);

if (!$cls) {
    setFlashMessage('error', 'Class not found.');
    header("Location: " . BASE_PATH . "/academics/classes.php");
    exit;
}

$allTeachers = $pdo->query("SELECT id, name, teacher_code, phone, email FROM teachers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$activeSession = getActiveSession();

// Handle Assign Class Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_class_teacher') {
    $teacherId = !empty($_POST['class_teacher_id']) ? (int)$_POST['class_teacher_id'] : null;
    $stmt = $pdo->prepare("UPDATE classes SET class_teacher_id = ? WHERE id = ?");
    $stmt->execute([$teacherId, $classId]);
    // Also update default sections
    $stmt2 = $pdo->prepare("UPDATE sections SET class_teacher_id = ? WHERE class_id = ? AND (class_teacher_id IS NULL OR class_teacher_id = 0)");
    $stmt2->execute([$teacherId, $classId]);
    logAudit('ASSIGN_CLASS_TEACHER', 'Academics', $classId, "Assigned teacher ID {$teacherId} as class teacher for class {$classId}");
    setFlashMessage('success', "Class Teacher assignment updated successfully!");
    header("Location: " . BASE_PATH . "/academics/class-view.php?id=" . $classId);
    exit;
}

// Handle Assign Subject Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_subject_teacher') {
    $subjectId = (int)$_POST['subject_id'];
    $teacherId = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    
    // Check if mapping exists
    $chk = $pdo->prepare("SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ? LIMIT 1");
    $chk->execute([$classId, $subjectId]);
    $mapping = $chk->fetch();
    
    if ($mapping) {
        $stmt = $pdo->prepare("UPDATE class_subjects SET teacher_id = ? WHERE class_id = ? AND subject_id = ?");
        $stmt->execute([$teacherId, $classId, $subjectId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO class_subjects (class_id, section_id, subject_id, teacher_id) VALUES (?, 1, ?, ?)");
        $stmt->execute([$classId, $subjectId, $teacherId]);
    }
    setFlashMessage('success', "Subject teacher assignment saved!");
    header("Location: " . BASE_PATH . "/academics/class-view.php?id=" . $classId);
    exit;
}

// Fetch Assigned Class Teacher
$classTeacher = null;
if (!empty($cls['class_teacher_id'])) {
    $tStmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $tStmt->execute([$cls['class_teacher_id']]);
    $classTeacher = $tStmt->fetch();
}

// Fetch Sections
$secStmt = $pdo->prepare("
    SELECT s.*, t.name as section_teacher_name 
    FROM sections s 
    LEFT JOIN teachers t ON s.class_teacher_id = t.id 
    WHERE s.class_id = ? 
    ORDER BY s.section_name ASC
");
$secStmt->execute([$classId]);
$sections = $secStmt->fetchAll();

// Fetch Subjects & Assigned Subject Teachers
$subStmt = $pdo->prepare("
    SELECT s.*, t.id as teacher_id, t.name as teacher_name, t.teacher_code, t.phone as teacher_phone
    FROM subjects s
    LEFT JOIN class_subjects cs ON cs.subject_id = s.id AND cs.class_id = ?
    LEFT JOIN teachers t ON cs.teacher_id = t.id
    WHERE s.class_id = ?
    ORDER BY s.subject_name ASC
");
$subStmt->execute([$classId, $classId]);
$subjects = $subStmt->fetchAll();

// Fetch All Enrolled Students in this Class
$stStmt = $pdo->prepare("
    SELECT s.*, sec.section_name, pr.father_name, pr.phone as parent_phone,
           sc.title as scholarship_title, sc.discount_percentage as scholarship_pct,
           (SELECT COALESCE(SUM(balance), 0) FROM student_fees sf WHERE sf.student_id = s.id AND sf.status IN ('Pending','Partial','Overdue')) as fee_due
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents pr ON s.parent_id = pr.id
    LEFT JOIN scholarships sc ON s.scholarship_id = sc.id
    WHERE s.class_id = ? AND s.status = 'active'
    ORDER BY s.roll_no ASC, s.first_name ASC
");
$stStmt->execute([$classId]);
$students = $stStmt->fetchAll();

$pageTitle = "Class Details - " . $cls['class_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/academics/classes.php">Classes</a></li>
                <li class="breadcrumb-item active"><?= e($cls['class_name']) ?></li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-2">
            <h3 class="fw-bold text-dark mb-0"><?= e($cls['class_name']) ?></h3>
            <span class="badge bg-primary px-3 py-1 rounded-pill">Level <?= $cls['numeric_level'] ?></span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                <?= count($students) ?> Students Enrolled
            </span>
        </div>
    </div>
    
    <!-- Action Buttons for Challan & Report Card Printing -->
    <div class="d-flex flex-wrap gap-2">
        <!-- Whole Class Batch Challans -->
        <a href="<?= BASE_PATH ?>/fees/challan-batch.php?class_id=<?= $cls['id'] ?>" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm fw-bold">
            <i class="fas fa-print me-1"></i> Print Whole Class Batch Challans (<?= count($students) ?>)
        </a>
        <!-- Individual Challan Search -->
        <button type="button" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#searchChallanModal">
            <i class="fas fa-search me-1"></i> Print Individual Challan
        </button>
        <!-- Whole Class Report Card -->
        <a href="<?= BASE_PATH ?>/examinations/tabulation.php?class_id=<?= $cls['id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-file-alt me-1"></i> Class Report Sheet (Tabulation)
        </a>
        <a href="<?= BASE_PATH ?>/academics/classes.php" class="btn btn-light btn-sm rounded-pill px-3 border">
            <i class="fas fa-arrow-left me-1"></i> All Classes
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- 1. Assigned Teachers Panel -->
    <div class="col-12 col-lg-5">
        <!-- Class Teacher Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-chalkboard-teacher text-primary me-2"></i> Assigned Class Teacher
                </h6>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0" data-bs-toggle="modal" data-bs-target="#assignClassTeacherModal">
                    <i class="fas fa-user-edit me-1"></i> Change
                </button>
            </div>
            <div class="card-body p-4">
                <?php if ($classTeacher): ?>
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4 me-3" style="width: 58px; height: 58px;">
                            <?= strtoupper(substr($classTeacher['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><?= e($classTeacher['name']) ?></h5>
                            <div class="text-muted small">Code: <code><?= e($classTeacher['teacher_code']) ?></code> &bull; <?= e($classTeacher['qualification'] ?: 'Teacher') ?></div>
                            <div class="text-muted small"><i class="fas fa-phone me-1"></i> <?= e($classTeacher['phone']) ?> &bull; <i class="fas fa-envelope ms-1 me-1"></i> <?= e($classTeacher['email']) ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-user-slash fs-2 text-muted mb-2 d-block"></i>
                        <span class="text-muted small">No class teacher has been assigned yet.</span>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#assignClassTeacherModal">
                                Assign Class Teacher
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subject Teachers Card -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-book-reader text-success me-2"></i> Subject Teachers Assigned (<?= count($subjects) ?> Subjects)
                </h6>
                <a href="<?= BASE_PATH ?>/academics/subjects.php?class_id=<?= $cls['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0">
                    + Manage
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Assigned Teacher</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjects)): ?>
                                <tr><td colspan="3" class="text-center py-3 text-muted">No subjects configured for this class.</td></tr>
                            <?php else: ?>
                                <?php foreach ($subjects as $sub): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($sub['subject_name']) ?></div>
                                            <code><?= e($sub['subject_code']) ?></code>
                                        </td>
                                        <td>
                                            <?php if (!empty($sub['teacher_name'])): ?>
                                                <span class="fw-bold text-primary"><?= e($sub['teacher_name']) ?></span>
                                                <div class="text-muted" style="font-size: 0.72rem;"><?= e($sub['teacher_phone']) ?></div>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-dark border">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1"
                                                    data-bs-toggle="modal" data-bs-target="#assignSubjectTeacherModal"
                                                    onclick="populateAssignSubject(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['subject_name'], ENT_QUOTES) ?>', '<?= $sub['teacher_id'] ?? '' ?>')">
                                                Assign
                                            </button>
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

    <!-- 2. Enrolled Students in this Class -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fas fa-users text-primary me-2"></i> All Students in <?= e($cls['class_name']) ?> (<?= count($students) ?>)
                    </h6>
                    <small class="text-muted">Batch fee challans and individual reports</small>
                </div>
                <div class="input-group input-group-sm" style="max-width: 200px;">
                    <input type="text" id="studentTableFilter" class="form-control" placeholder="Search student...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 580px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="classStudentsTable">
                        <thead class="table-light small">
                            <tr>
                                <th>Roll #</th>
                                <th>Student Details</th>
                                <th>Type & Quota</th>
                                <th>Parent Contact</th>
                                <th>Fee Due</th>
                                <th class="text-end">Challan / Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No students enrolled in this class yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $st): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border font-monospace"><?= e($st['roll_no'] ?: '-') ?></span></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($st['first_name'] . ' ' . $st['last_name']) ?></div>
                                            <code class="small"><?= e($st['admission_no']) ?></code> &bull; <span class="text-muted small"><?= e($st['gender']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-dark border"><?= e($st['student_type'] ?? 'Coeducation') ?></span>
                                            <?php if (!empty($st['scholarship_title'])): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle d-block mt-1" style="font-size: 0.7rem;">
                                                    <?= e($st['scholarship_title']) ?> (<?= number_format($st['scholarship_pct'], 0) ?>%)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= e($st['father_name'] ?: 'N/A') ?></div>
                                            <div class="text-muted small"><?= e($st['parent_phone'] ?: $st['phone']) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($st['fee_due'] > 0): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= formatCurrency($st['fee_due']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Clear</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <!-- Print Individual Challan -->
                                                <a href="<?= BASE_PATH ?>/fees/challan.php?student_id=<?= $st['id'] ?>" class="btn btn-outline-success" title="Print Fee Challan">
                                                    <i class="fas fa-file-invoice-dollar"></i> Challan
                                                </a>
                                                <!-- Individual Report Card -->
                                                <a href="<?= BASE_PATH ?>/examinations/results.php?student_id=<?= $st['id'] ?>" class="btn btn-outline-primary" title="Student Report Card">
                                                    <i class="fas fa-graduation-cap"></i> Report
                                                </a>
                                            </div>
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

<!-- Modal 1: Assign Class Teacher -->
<div class="modal fade" id="assignClassTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold mb-0">Assign Class Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/class-view.php?id=<?= $classId ?>" method="POST">
                <input type="hidden" name="action" value="assign_class_teacher">
                <div class="modal-body p-4">
                    <p class="text-muted small">Select the designated Class Teacher for <strong><?= e($cls['class_name']) ?></strong>. The class teacher will have full access to enter exam numbers, marks, and view report cards for this class.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Choose Teacher <span class="text-danger">*</span></label>
                        <select name="class_teacher_id" class="form-select" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($allTeachers as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($cls['class_teacher_id'] == $t['id']) ? 'selected' : '' ?>>
                                    <?= e($t['name']) ?> (<?= e($t['teacher_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Assign Subject Teacher -->
<div class="modal fade" id="assignSubjectTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold mb-0">Assign Subject Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/class-view.php?id=<?= $classId ?>" method="POST">
                <input type="hidden" name="action" value="assign_subject_teacher">
                <input type="hidden" name="subject_id" id="assignSubjectId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject</label>
                        <input type="text" id="assignSubjectName" class="form-control bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Assign Teacher <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="assignTeacherSelect" class="form-select" required>
                            <option value="">-- Choose Subject Teacher --</option>
                            <?php foreach ($allTeachers as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    <?= e($t['name']) ?> (<?= e($t['teacher_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Assign Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Search Individual Challan -->
<div class="modal fade" id="searchChallanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom bg-success-subtle">
                <h5 class="modal-title fw-bold text-dark mb-0"><i class="fas fa-search text-success me-2"></i> Find & Print Individual Challan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <label class="form-label small fw-semibold">Search by Student Name, Admission No, or Roll No:</label>
                <div class="input-group mb-3">
                    <span class="input-group-text bg-light"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" id="challanStudentQuery" class="form-control" placeholder="e.g. Alexander or ADM-2025-001..." autocomplete="off">
                </div>
                <div id="challanResultsList" class="list-group" style="max-height: 250px; overflow-y: auto;">
                    <div class="text-muted small text-center py-3">Type at least 1 character to search student challan</div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function populateAssignSubject(subId, subName, currentTeacherId) {
    document.getElementById('assignSubjectId').value = subId;
    document.getElementById('assignSubjectName').value = subName;
    document.getElementById('assignTeacherSelect').value = currentTeacherId || '';
}

// Student Table Filter
const filterInput = document.getElementById('studentTableFilter');
if (filterInput) {
    filterInput.addEventListener('input', () => {
        const val = filterInput.value.toLowerCase();
        const rows = document.querySelectorAll('#classStudentsTable tbody tr');
        rows.forEach(r => {
            r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
        });
    });
}

// Live Search for Individual Challan
const challanQuery = document.getElementById('challanStudentQuery');
const challanResults = document.getElementById('challanResultsList');
if (challanQuery) {
    let debounce;
    challanQuery.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            const q = challanQuery.value.trim();
            if (q.length < 1) {
                challanResults.innerHTML = '<div class="text-muted small text-center py-3">Type to search student challan</div>';
                return;
            }
            fetch(`<?= BASE_PATH ?>/api/student-search.php?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.students.length > 0) {
                        let html = '';
                        data.students.forEach(st => {
                            html += `
                                <a href="<?= BASE_PATH ?>/fees/challan.php?student_id=${st.id}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                                    <div>
                                        <strong>${st.first_name} ${st.last_name}</strong>
                                        <div class="text-muted small">${st.class_name || ''} - ${st.section_name || ''} &bull; Adm: <code>${st.admission_no}</code></div>
                                    </div>
                                    <span class="btn btn-sm btn-success rounded-pill px-3">
                                        <i class="fas fa-print me-1"></i> Print Challan
                                    </span>
                                </a>
                            `;
                        });
                        challanResults.innerHTML = html;
                    } else {
                        challanResults.innerHTML = '<div class="text-muted small text-center py-3">No matching student found</div>';
                    }
                });
        }, 200);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
