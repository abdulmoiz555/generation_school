<?php
/**
 * Student ID Card Generator
 * Printable ID cards with school branding, student photo, QR code placeholder, barcode, and emergency contact
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_view');

$selectedStudentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$selectedSectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$sections = [];
if ($selectedClassId) {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? ORDER BY section_name ASC");
    $stmt->execute([$selectedClassId]);
    $sections = $stmt->fetchAll();
}

$students = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, sec.section_name, p.father_name, p.phone as father_phone, p.alternate_phone
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents p ON s.parent_id = p.id
        WHERE s.id = ?
    ");
    $stmt->execute([$selectedStudentId]);
    $students = $stmt->fetchAll();
} elseif ($selectedClassId) {
    $query = "
        SELECT s.*, c.class_name, sec.section_name, p.father_name, p.phone as father_phone, p.alternate_phone
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents p ON s.parent_id = p.id
        WHERE s.class_id = ? AND s.status = 'active'
    ";
    $params = [$selectedClassId];
    if ($selectedSectionId) {
        $query .= " AND s.section_id = ?";
        $params[] = $selectedSectionId;
    }
    $query .= " ORDER BY s.roll_no ASC, s.first_name ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} else {
    // Default: fetch first 6 students for preview if none selected
    $students = $pdo->query("
        SELECT s.*, c.class_name, sec.section_name, p.father_name, p.phone as father_phone, p.alternate_phone
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents p ON s.parent_id = p.id
        WHERE s.status = 'active'
        ORDER BY s.id ASC
        LIMIT 6
    ")->fetchAll();
}

$schoolName = getSetting('school_name', 'EduManage International Academy');
$schoolPhone = getSetting('school_phone', '+1 (555) 019-2834');
$schoolAddress = getSetting('school_address', '100 Academic Way, Education City');
$activeSession = getActiveSession();

$pageTitle = "Student ID Card Generator";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.id-card-container {
    width: 320px;
    height: 480px;
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    position: relative;
    border: 1px solid #e2e8f0;
    display: inline-block;
    vertical-align: top;
    margin: 12px;
}
.id-card-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    color: #fff;
    padding: 16px 12px;
    text-align: center;
    position: relative;
}
.id-card-header::after {
    content: '';
    position: absolute;
    bottom: -12px;
    left: 0;
    right: 0;
    height: 24px;
    background: #fff;
    border-top-left-radius: 50% 12px;
    border-top-right-radius: 50% 12px;
}
.id-card-photo-wrapper {
    width: 95px;
    height: 95px;
    margin: -10px auto 10px;
    border-radius: 50%;
    border: 4px solid #fff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    overflow: hidden;
    background: #f1f5f9;
    position: relative;
    z-index: 2;
}
.id-card-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.id-card-body {
    padding: 0 20px 10px;
    text-align: center;
}
.id-card-student-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 2px;
}
.id-card-badge {
    display: inline-block;
    padding: 2px 10px;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 9999px;
    background: #eff6ff;
    color: #2563eb;
    margin-bottom: 12px;
}
.id-card-table {
    width: 100%;
    font-size: 0.8rem;
    text-align: left;
    margin-bottom: 10px;
}
.id-card-table td {
    padding: 3px 0;
}
.id-card-table td.label {
    color: #64748b;
    font-weight: 500;
    width: 42%;
}
.id-card-table td.value {
    color: #0f172a;
    font-weight: 600;
}
.id-card-barcode {
    height: 30px;
    background: repeating-linear-gradient(
        90deg,
        #1e293b,
        #1e293b 2px,
        transparent 2px,
        transparent 4px,
        #1e293b 4px,
        #1e293b 7px,
        transparent 7px,
        transparent 9px
    );
    margin: 8px 30px;
    border-radius: 2px;
}
.id-card-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 8px 16px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.7rem;
    color: #64748b;
}

@media print {
    body {
        background: #fff !important;
        margin: 0;
        padding: 0;
    }
    .no-print, .app-sidebar, .app-navbar, .app-footer, .breadcrumb, nav {
        display: none !important;
    }
    .main-content, .content-wrapper, .container-fluid {
        padding: 0 !important;
        margin: 0 !important;
    }
    .id-card-container {
        box-shadow: none !important;
        border: 1px solid #cbd5e1 !important;
        page-break-inside: avoid;
        margin: 10px;
    }
}
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/students/index.php">Students</a></li>
                <li class="breadcrumb-item active">ID Cards</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-id-card text-primary me-2"></i> Student ID Card Generator</h3>
        <p class="text-muted small mb-0">Generate and print high-resolution laminated student identity cards</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-print me-2"></i> Print ID Cards
        </button>
        <a href="<?= BASE_PATH ?>/students/index.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Students
        </a>
    </div>
</div>

<!-- Filter card -->
<div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <label class="small text-muted mb-1 fw-bold">Select Class</label>
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Classes (Preview) --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedClassId == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($sections)): ?>
            <div class="col-md-4">
                <label class="small text-muted mb-1 fw-bold">Select Section</label>
                <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Sections --</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selectedSectionId == $s['id'] ? 'selected' : '' ?>>
                            Section <?= e($s['section_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-4 d-flex align-items-end">
                <a href="<?= BASE_PATH ?>/students/id-card.php" class="btn btn-light btn-sm text-secondary">
                    <i class="fas fa-undo me-1"></i> Reset Filters
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Cards Grid -->
<div class="text-center print-area">
    <?php if (empty($students)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
            <div class="text-muted mb-3"><i class="fas fa-user-slash fa-3x text-secondary opacity-50"></i></div>
            <h5 class="fw-bold">No Students Found</h5>
            <p class="text-muted small">Please select a different class or section to generate identity cards.</p>
        </div>
    <?php else: ?>
        <div class="d-flex flex-wrap justify-content-center">
            <?php foreach ($students as $stu): 
                $photoPath = !empty($stu['photo']) && file_exists(__DIR__ . '/../uploads/students/' . $stu['photo'])
                    ? BASE_PATH . '/uploads/students/' . $stu['photo']
                    : 'https://ui-avatars.com/api/?name=' . urlencode($stu['first_name'] . ' ' . $stu['last_name']) . '&background=2563eb&color=fff&size=200';
            ?>
            <div class="id-card-container">
                <!-- Header -->
                <div class="id-card-header">
                    <div class="fw-bold text-uppercase tracking-wider" style="font-size: 0.82rem; letter-spacing: 0.5px;"><?= e($schoolName) ?></div>
                    <div style="font-size: 0.65rem; opacity: 0.85;"><?= e($schoolAddress) ?></div>
                    <div class="badge bg-white text-primary mt-1 px-2 py-0" style="font-size: 0.62rem;">STUDENT IDENTITY CARD</div>
                </div>

                <!-- Photo -->
                <div class="id-card-photo-wrapper">
                    <img src="<?= $photoPath ?>" alt="Student Photo" class="id-card-photo">
                </div>

                <!-- Body -->
                <div class="id-card-body">
                    <div class="id-card-student-name"><?= e($stu['first_name'] . ' ' . $stu['last_name']) ?></div>
                    <div class="id-card-badge">Session <?= e($activeSession['session_name'] ?? '2026-2027') ?></div>

                    <table class="id-card-table">
                        <tr>
                            <td class="label">Admission No:</td>
                            <td class="value text-primary"><?= e($stu['admission_no']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Class & Sec:</td>
                            <td class="value"><?= e($stu['class_name'] ?? 'N/A') ?> - <?= e($stu['section_name'] ?? 'A') ?></td>
                        </tr>
                        <tr>
                            <td class="label">Roll No:</td>
                            <td class="value"><?= e($stu['roll_no'] ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <td class="label">Blood Group:</td>
                            <td class="value"><span class="badge bg-danger-subtle text-danger px-2"><?= e($stu['blood_group'] ?: 'O+') ?></span></td>
                        </tr>
                        <tr>
                            <td class="label">Emergency Ph:</td>
                            <td class="value"><?= e($stu['father_phone'] ?: $stu['alternate_phone'] ?: $stu['phone'] ?: $schoolPhone) ?></td>
                        </tr>
                    </table>

                    <!-- Barcode simulation -->
                    <div class="id-card-barcode" title="<?= e($stu['admission_no']) ?>"></div>
                    <div class="text-muted" style="font-size: 0.65rem; letter-spacing: 2px; margin-top: -4px;">
                        *<?= e($stu['admission_no']) ?>*
                    </div>
                </div>

                <!-- Footer -->
                <div class="id-card-footer">
                    <div><i class="fas fa-phone-alt me-1"></i> <?= e($schoolPhone) ?></div>
                    <div class="text-end">
                        <div class="fw-bold" style="font-size: 0.65rem; border-top: 1px dotted #94a3b8; padding-top: 1px; min-width: 60px;">Authorized Sign</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
