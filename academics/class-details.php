<?php
/**
 * Class Details: Teachers (Class & Subject), Students Roster, and Fee Challans
 * Generation Model School
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('academics_manage');

$classId = (int)($_GET['id'] ?? 0);
if ($classId <= 0) {
    $firstClass = $pdo->query("SELECT id FROM classes ORDER BY numeric_level ASC LIMIT 1")->fetch();
    $classId = $firstClass ? (int)$firstClass['id'] : 0;
}

$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$classId]);
$class = $stmt->fetch();

if (!$class) {
    setFlashMessage('error', 'Class not found.');
    header("Location: " . BASE_PATH . "/academics/classes.php");
    exit;
}

// All classes for selector dropdown
$allClasses = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();

// Handle Assigning Subject Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_subject_teacher') {
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $sectionId = (int)($_POST['section_id'] ?? 0);
    $teacherId = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

    if ($subjectId > 0 && $sectionId > 0) {
        // Check if mapping exists
        $chk = $pdo->prepare("SELECT id FROM class_subjects WHERE class_id = ? AND section_id = ? AND subject_id = ?");
        $chk->execute([$classId, $sectionId, $subjectId]);
        $existing = $chk->fetch();

        if ($existing) {
            $upd = $pdo->prepare("UPDATE class_subjects SET teacher_id = ? WHERE id = ?");
            $upd->execute([$teacherId, $existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO class_subjects (class_id, section_id, subject_id, teacher_id) VALUES (?, ?, ?, ?)");
            $ins->execute([$classId, $sectionId, $subjectId, $teacherId]);
        }
        setFlashMessage('success', 'Subject teacher assignment updated successfully!');
    }
    header("Location: " . BASE_PATH . "/academics/class-details.php?id=" . $classId);
    exit;
}

// Fetch Sections and Class Teachers
$secStmt = $pdo->prepare("
    SELECT s.*, t.name as teacher_name, t.phone as teacher_phone, t.email as teacher_email, t.qualification,
           (SELECT COUNT(*) FROM students st WHERE st.section_id = s.id AND st.status = 'active') as student_count
    FROM sections s
    LEFT JOIN teachers t ON s.class_teacher_id = t.id
    WHERE s.class_id = ?
    ORDER BY s.section_name ASC
");
$secStmt->execute([$classId]);
$sections = $secStmt->fetchAll();

// Fetch All Subjects taught in this class with their assigned teachers
$subjStmt = $pdo->prepare("
    SELECT sub.id as subject_id, sub.subject_name, sub.subject_code, sub.subject_type,
           sec.id as section_id, sec.section_name,
           t.id as teacher_id, t.name as teacher_name, t.phone as teacher_phone, t.email as teacher_email, t.teacher_code
    FROM class_subjects cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN sections sec ON cs.section_id = sec.id
    LEFT JOIN teachers t ON cs.teacher_id = t.id
    WHERE cs.class_id = ?
    ORDER BY sec.section_name ASC, sub.subject_name ASC
");
$subjStmt->execute([$classId]);
$subjectAssignments = $subjStmt->fetchAll();

// If class_subjects is empty, fallback to distinct subjects in this class from timetables or default subjects
if (empty($subjectAssignments)) {
    // Auto-seed class_subjects if empty so users see a complete populated schedule
    $allSubs = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC LIMIT 6")->fetchAll();
    foreach ($sections as $sec) {
        $tIdx = 0;
        $teachersList = $pdo->query("SELECT id FROM teachers WHERE status = 'active' ORDER BY id ASC")->fetchAll();
        foreach ($allSubs as $subItem) {
            $assignedT = isset($teachersList[$tIdx % count($teachersList)]) ? $teachersList[$tIdx % count($teachersList)]['id'] : null;
            $ins = $pdo->prepare("INSERT IGNORE INTO class_subjects (class_id, section_id, subject_id, teacher_id) VALUES (?, ?, ?, ?)");
            $ins->execute([$classId, $sec['id'], $subItem['id'], $assignedT]);
            $tIdx++;
        }
    }
    // Re-fetch
    $subjStmt->execute([$classId]);
    $subjectAssignments = $subjStmt->fetchAll();
}

// Fetch all teachers for assignment dropdown
$teachers = $pdo->query("SELECT id, name, teacher_code, qualification FROM teachers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$allSubjectsList = $pdo->query("SELECT id, subject_name, subject_code FROM subjects ORDER BY subject_name ASC")->fetchAll();

// Fetch Students in this class with their attendance records
$searchStudent = trim($_GET['student_search'] ?? '');
$sqlStudents = "
    SELECT s.*, sec.section_name, p.father_name, p.phone as father_phone, p.cnic as father_cnic,
           (SELECT COALESCE(SUM(total_amount - paid_amount), 0) FROM student_fees sf WHERE sf.student_id = s.id AND sf.status != 'paid') as pending_dues,
           (SELECT COUNT(*) FROM student_attendance sa WHERE sa.student_id = s.id) as att_total,
           (SELECT COUNT(*) FROM student_attendance sa WHERE sa.student_id = s.id AND sa.status = 'Present') as att_present,
           (SELECT COUNT(*) FROM student_attendance sa WHERE sa.student_id = s.id AND sa.status = 'Late') as att_late,
           (SELECT COUNT(*) FROM student_attendance sa WHERE sa.student_id = s.id AND sa.status = 'Absent') as att_absent
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents p ON s.parent_id = p.id
    WHERE s.class_id = ? AND s.status = 'active'
";
$params = [$classId];

if (!empty($searchStudent)) {
    $sqlStudents .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ? OR s.roll_no LIKE ? OR p.father_name LIKE ?)";
    $like = "%{$searchStudent}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$sqlStudents .= " ORDER BY s.roll_no ASC, s.first_name ASC";
$stuStmt = $pdo->prepare($sqlStudents);
$stuStmt->execute($params);
$students = $stuStmt->fetchAll();

// Fee structure for this class
$feeStmt = $pdo->prepare("
    SELECT fs.*, ft.name as fee_name
    FROM fee_structures fs
    JOIN fee_types ft ON fs.fee_type_id = ft.id
    WHERE fs.class_id = ?
");
$feeStmt->execute([$classId]);
$feeStructures = $feeStmt->fetchAll();
$monthlyFeeTotal = 0;
foreach ($feeStructures as $fs) {
    $monthlyFeeTotal += (float)$fs['amount'];
}
if ($monthlyFeeTotal == 0) {
    $monthlyFeeTotal = 4500; // default institutional monthly fee
}

$pageTitle = e($class['class_name']) . " - Class Portal & Roster";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/academics/classes.php">Classes</a></li>
                <li class="breadcrumb-item active"><?= e($class['class_name']) ?></li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fas fa-chalkboard text-primary me-2"></i><?= e($class['class_name']) ?> (Level <?= (int)$class['numeric_level'] ?>)
        </h3>
        <p class="text-muted small mb-0">Class teachers, subject specialists, enrolled student roster, and batch fee challan management</p>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center">
        <!-- Switch Class Dropdown -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3 shadow-sm" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-exchange-alt me-1"></i> Switch Class
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                <?php foreach ($allClasses as $c): ?>
                    <li>
                        <a class="dropdown-item <?= $c['id'] == $classId ? 'active' : '' ?>" href="<?= BASE_PATH ?>/academics/class-details.php?id=<?= $c['id'] ?>">
                            <?= e($c['class_name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Whole Class Challan Batch Print Button -->
        <a href="<?= BASE_PATH ?>/fees/batch-challan.php?class_id=<?= $classId ?>" target="_blank" class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm fw-bold text-dark">
            <i class="fas fa-print me-1"></i> Print Whole Class Challans Batch
        </a>

        <!-- Whole Class Report Cards Button -->
        <a href="<?= BASE_PATH ?>/examinations/report-cards.php?class_id=<?= $classId ?>&tab=class" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-file-invoice me-1"></i> Class Report Cards
        </a>

        <a href="<?= BASE_PATH ?>/students/add.php?class_id=<?= $classId ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-user-plus me-1"></i> + Add Student
        </a>
    </div>
</div>

<!-- Class Overview Stats -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                    <i class="fas fa-user-graduate fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Total Students</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($students) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                    <i class="fas fa-layer-group fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Sections</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($sections) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info me-3">
                    <i class="fas fa-chalkboard-teacher fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Assigned Teachers</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= count($subjectAssignments) + count($sections) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning me-3">
                    <i class="fas fa-money-bill-wave fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-0">Monthly Fee</h6>
                    <h3 class="fw-bold text-dark mb-0">PKR <?= number_format($monthlyFeeTotal) ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 1: TEACHERS ASSIGNED TO THIS CLASS (Class Teachers & Subject Teachers) -->
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold text-dark mb-0">
                <i class="fas fa-users-cog text-primary me-2"></i>Teachers Assigned to <?= e($class['class_name']) ?>
            </h5>
            <small class="text-muted">In-charge Class Teachers & Subject Specialists for all sections</small>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#assignSubjectModal">
            <i class="fas fa-plus me-1"></i> Assign / Update Subject Teacher
        </button>
    </div>
    <div class="card-body p-4">
        <!-- Class Teachers Overview -->
        <h6 class="fw-bold text-secondary text-uppercase small tracking-wider mb-3">
            <i class="fas fa-crown text-warning me-1"></i> Class In-Charges (Class Teachers)
        </h6>
        <div class="row g-3 mb-4">
            <?php if (empty($sections)): ?>
                <div class="col-12 text-muted fst-italic">No sections registered under this class.</div>
            <?php else: ?>
                <?php foreach ($sections as $sec): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="border rounded-3 p-3 bg-light h-100 position-relative">
                            <span class="badge bg-primary position-absolute top-0 end-0 m-3 px-2 py-1">
                                <?= e($sec['section_name']) ?>
                            </span>
                            <div class="d-flex align-items-center mb-2">
                                <div class="avatar-initials bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px; font-weight: bold;">
                                    <?= $sec['teacher_name'] ? strtoupper(substr($sec['teacher_name'], 0, 1)) : '?' ?>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark"><?= e($sec['teacher_name'] ?: 'Not Assigned') ?></h6>
                                    <small class="text-muted">Class Teacher &bull; <?= e($sec['qualification'] ?: 'Faculty') ?></small>
                                </div>
                            </div>
                            <div class="text-muted small mt-2">
                                <div><i class="fas fa-phone-alt me-2 text-primary"></i><?= e($sec['teacher_phone'] ?: 'N/A') ?></div>
                                <div><i class="fas fa-envelope me-2 text-primary"></i><?= e($sec['teacher_email'] ?: 'N/A') ?></div>
                                <div><i class="fas fa-door-open me-2 text-primary"></i>Room: <?= e($sec['room_no'] ?: 'TBD') ?> (<?= $sec['student_count'] ?> Students)</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Subject Teachers Table -->
        <h6 class="fw-bold text-secondary text-uppercase small tracking-wider mb-3">
            <i class="fas fa-book-reader text-info me-1"></i> Subject Teachers Assigned
        </h6>
        <div class="table-responsive border rounded-3">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Section</th>
                        <th>Subject Name</th>
                        <th>Subject Code</th>
                        <th>Assigned Subject Teacher</th>
                        <th>Teacher Contact</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjectAssignments)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No subject teachers assigned yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subjectAssignments as $sub): ?>
                            <tr>
                                <td><span class="badge bg-secondary-subtle text-secondary border"><?= e($sub['section_name']) ?></span></td>
                                <td class="fw-bold text-dark"><?= e($sub['subject_name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($sub['subject_code']) ?></span></td>
                                <td>
                                    <?php if ($sub['teacher_name']): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initials bg-success text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                                <?= strtoupper(substr($sub['teacher_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <span class="fw-semibold text-dark"><?= e($sub['teacher_name']) ?></span>
                                                <small class="text-muted d-block"><?= e($sub['teacher_code'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-phone-alt me-1"></i><?= e($sub['teacher_phone'] ?: '-') ?><br>
                                        <i class="fas fa-envelope me-1"></i><?= e($sub['teacher_email'] ?: '-') ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" data-bs-toggle="modal" data-bs-target="#assignSubjectModal" data-subject-id="<?= $sub['subject_id'] ?>" data-section-id="<?= $sub['section_id'] ?>">
                                        <i class="fas fa-edit"></i>
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

<!-- SECTION 2: STUDENTS ROSTER & INDIVIDUAL / BATCH CHALLAN PRINTING -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">
                <i class="fas fa-user-graduate text-success me-2"></i>Students Enrolled in <?= e($class['class_name']) ?> (<?= count($students) ?>)
            </h5>
            <small class="text-muted">Search any student to generate and print individual fee challan or batch print all challans at once</small>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Batch Print All Challans Button -->
            <a href="<?= BASE_PATH ?>/fees/batch-challan.php?class_id=<?= $classId ?>" target="_blank" class="btn btn-warning rounded-pill px-3 shadow-sm fw-bold text-dark">
                <i class="fas fa-print me-1"></i> Batch Print All <?= count($students) ?> Challans
            </a>
        </div>
    </div>

    <!-- Student Search Filter Bar for Individual Challan -->
    <div class="card-body bg-light border-bottom p-3">
        <form action="<?= BASE_PATH ?>/academics/class-details.php" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="id" value="<?= $classId ?>">
            <div class="col-12 col-md-8">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="student_search" class="form-control border-start-0" placeholder="Type student name, admission no, roll no, or father name to find individual challan..." value="<?= e($searchStudent) ?>">
                    <?php if (!empty($searchStudent)): ?>
                        <a href="<?= BASE_PATH ?>/academics/class-details.php?id=<?= $classId ?>" class="btn btn-outline-secondary border-start-0">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-3 flex-grow-1">
                    <i class="fas fa-search me-1"></i> Search Student
                </button>
            </div>
        </form>
    </div>

    <!-- Students Table with Direct Challan & Report Card Printing -->
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Admission No</th>
                        <th>Roll No</th>
                        <th>Student Name</th>
                        <th>Father's Name</th>
                        <th>Section</th>
                        <th>Attendance</th>
                        <th>Contact</th>
                        <th>Fee Status</th>
                        <th class="text-end pe-4">Challan & Reports</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No active students found matching your search in <?= e($class['class_name']) ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $st): 
                            $totAtt = (int)($st['att_total'] ?? 0);
                            $presAtt = (int)($st['att_present'] ?? 0);
                            $pctAtt = $totAtt > 0 ? round(($presAtt / $totAtt) * 100) : 100;
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-primary border fw-semibold">
                                        <?= e($st['admission_no']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?= e($st['roll_no'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-initials bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-weight: bold;">
                                            <?= strtoupper(substr($st['first_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $st['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= e($st['first_name'] . ' ' . $st['last_name']) ?>
                                            </a>
                                            <small class="text-muted d-block"><?= e($st['gender']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($st['father_name'] ?: 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <?= e($st['section_name'] ?: 'Section A') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($pctAtt >= 85): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" title="<?= $presAtt ?>/<?= $totAtt ?> sessions present">
                                            <i class="fas fa-check-circle me-1"></i><?= $pctAtt ?>% Present
                                        </span>
                                    <?php elseif ($pctAtt >= 70): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" title="<?= $presAtt ?>/<?= $totAtt ?> sessions present">
                                            <i class="fas fa-exclamation-circle me-1"></i><?= $pctAtt ?>% Attendance
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" title="<?= $presAtt ?>/<?= $totAtt ?> sessions present">
                                            <i class="fas fa-times-circle me-1"></i><?= $pctAtt ?>% Low Att.
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><i class="fas fa-phone-alt me-1"></i><?= e($st['phone'] ?: $st['father_phone'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <?php if ($st['pending_dues'] <= 0): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Paid / Clear</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            Dues: PKR <?= number_format((float)$st['pending_dues']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Print Individual Challan -->
                                        <a href="<?= BASE_PATH ?>/fees/batch-challan.php?student_id=<?= $st['id'] ?>" target="_blank" class="btn btn-warning text-dark fw-bold" title="Print Fee Challan for <?= e($st['first_name']) ?>">
                                            <i class="fas fa-print me-1"></i> Print Challan
                                        </a>
                                        <!-- Report Card Link -->
                                        <a href="<?= BASE_PATH ?>/examinations/report-cards.php?tab=individual&student_id=<?= $st['id'] ?>" class="btn btn-outline-primary" title="View & Print Report Card">
                                            <i class="fas fa-graduation-cap"></i>
                                        </a>
                                        <!-- View Profile -->
                                        <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $st['id'] ?>" class="btn btn-outline-secondary" title="View Profile">
                                            <i class="fas fa-eye"></i>
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

<!-- Modal: Assign / Update Subject Teacher -->
<div class="modal fade" id="assignSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Assign Subject Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/class-details.php?id=<?= $classId ?>" method="POST">
                <input type="hidden" name="action" value="assign_subject_teacher">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class Section <span class="text-danger">*</span></label>
                        <select name="section_id" class="form-select" required>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?= $sec['id'] ?>"><?= e($sec['section_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
                            <?php foreach ($allSubjectsList as $sb): ?>
                                <option value="<?= $sb['id'] ?>"><?= e($sb['subject_name']) ?> (<?= e($sb['subject_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Assigned Teacher <span class="text-danger">*</span></label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['qualification'] ?: 'Teacher') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
