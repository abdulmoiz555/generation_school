<?php
/**
 * Classes & Sections Management (Section 13)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('academics_manage');

$allTeachers = $pdo->query("SELECT id, name, teacher_code FROM teachers WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Add Class Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_class') {
    $className = trim($_POST['class_name'] ?? '');
    $numericLevel = (int)($_POST['numeric_level'] ?? 1);
    if (!empty($className)) {
        $stmt = $pdo->prepare("INSERT INTO classes (class_name, numeric_level) VALUES (?, ?)");
        $stmt->execute([$className, $numericLevel]);
        logAudit('ADD_CLASS', 'Academics', (int)$pdo->lastInsertId(), "Created class {$className}");
        setFlashMessage('success', "Class '{$className}' added successfully!");
    }
    header("Location: " . BASE_PATH . "/academics/classes.php");
    exit;
}

// Add Section Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_section') {
    $classId = (int)($_POST['class_id'] ?? 0);
    $sectionName = trim($_POST['section_name'] ?? '');
    $classTeacherId = !empty($_POST['class_teacher_id']) ? (int)$_POST['class_teacher_id'] : null;
    $roomNo = trim($_POST['room_no'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 40);

    if ($classId && !empty($sectionName)) {
        $stmt = $pdo->prepare("INSERT INTO sections (class_id, section_name, class_teacher_id, room_no, capacity) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$classId, $sectionName, $classTeacherId, $roomNo, $capacity]);
        logAudit('ADD_SECTION', 'Academics', (int)$pdo->lastInsertId(), "Created section {$sectionName}");
        setFlashMessage('success', "Section '{$sectionName}' created successfully!");
    }
    header("Location: " . BASE_PATH . "/academics/classes.php");
    exit;
}

$classes = getAllClasses();

$pageTitle = "Classes & Sections Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Academics</li>
                <li class="breadcrumb-item active">Classes & Sections</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Class & Section Administration</h3>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
            <i class="fas fa-plus me-1"></i> + New Class
        </button>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
            <i class="fas fa-layer-group me-1"></i> + New Section
        </button>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($classes as $cls): 
        // Fetch sections and student count
        $secStmt = $pdo->prepare("
            SELECT s.*, t.name as teacher_name,
                   (SELECT COUNT(*) FROM students st WHERE st.section_id = s.id AND st.status = 'active') as student_count
            FROM sections s
            LEFT JOIN teachers t ON s.class_teacher_id = t.id
            WHERE s.class_id = ?
            ORDER BY s.section_name ASC
        ");
        $secStmt->execute([$cls['id']]);
        $sections = $secStmt->fetchAll();

        $totalInClass = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = {$cls['id']} AND status = 'active'")->fetchColumn();
    ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><?= e($cls['class_name']) ?></h5>
                        <small class="text-muted">Level <?= $cls['numeric_level'] ?></small>
                    </div>
                    <span class="badge bg-primary rounded-pill px-3 py-2">
                        <?= $totalInClass ?> Students
                    </span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (empty($sections)): ?>
                            <li class="list-group-item text-center text-muted py-3">No sections created yet.</li>
                        <?php else: ?>
                            <?php foreach ($sections as $sec): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                    <div>
                                        <span class="fw-semibold text-dark"><?= e($sec['section_name']) ?></span>
                                        <div class="text-muted small">
                                            <i class="fas fa-chalkboard-teacher me-1"></i> <?= e($sec['teacher_name'] ?: 'No Teacher') ?> &bull; 
                                            <i class="fas fa-door-open me-1"></i> <?= e($sec['room_no'] ?: 'Room TBD') ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-light text-dark border"><?= $sec['student_count'] ?> / <?= $sec['capacity'] ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Academic Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/classes.php" method="POST">
                <input type="hidden" name="action" value="add_class">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class Name <span class="text-danger">*</span></label>
                        <input type="text" name="class_name" class="form-control" placeholder="e.g. Grade 6 or Kindergarten" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Numeric Level <span class="text-danger">*</span></label>
                        <input type="number" name="numeric_level" class="form-control" placeholder="e.g. 6" required>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Class Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/classes.php" method="POST">
                <input type="hidden" name="action" value="add_section">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class <span class="text-danger">*</span></label>
                        <select name="class_id" class="form-select" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Section Name <span class="text-danger">*</span></label>
                        <input type="text" name="section_name" class="form-control" placeholder="e.g. Section C" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class Teacher</label>
                        <select name="class_teacher_id" class="form-select">
                            <option value="">-- Assign Class Teacher --</option>
                            <?php foreach ($allTeachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['teacher_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Room Number</label>
                            <input type="text" name="room_no" class="form-control" placeholder="e.g. Room 303">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Capacity</label>
                            <input type="number" name="capacity" class="form-control" value="40">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
