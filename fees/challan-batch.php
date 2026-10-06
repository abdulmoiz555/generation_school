<?php
/**
 * Whole Class Batch Fee Challan Printing
 * Renders 3-part bank/school/student challans for all students in a class with automatic page-breaks
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$classId = (int)($_GET['class_id'] ?? 1);
$cls = getClass($classId);

if (!$cls) {
    die("Class not found.");
}

$sectionId = (int)($_GET['section_id'] ?? 0);
$allClasses = getAllClasses();
$sections = getSections($classId);

// Fetch all active students in class
$sql = "
    SELECT s.*, sec.section_name, pr.father_name,
           sc.title as scholarship_title, sc.discount_percentage as scholarship_pct, sc.category as scholarship_category
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents pr ON s.parent_id = pr.id
    LEFT JOIN scholarships sc ON s.scholarship_id = sc.id
    WHERE s.class_id = ? AND s.status = 'active'
";
$params = [$classId];
if ($sectionId > 0) {
    $sql .= " AND s.section_id = ?";
    $params[] = $sectionId;
}
$sql .= " ORDER BY s.roll_no ASC, s.first_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Standard class fee breakdown
$tuition = 3500.00;
$examFee = 1500.00;
$labFee = 1000.00;
$sportsFee = 800.00;

// Fetch class fee structures
$fsStmt = $pdo->prepare("SELECT fs.*, ft.code FROM fee_structures fs JOIN fee_types ft ON fs.fee_type_id = ft.id WHERE fs.class_id = ?");
$fsStmt->execute([$classId]);
$classStructures = $fsStmt->fetchAll();
if (!empty($classStructures)) {
    foreach ($classStructures as $cs) {
        if ($cs['code'] === 'TUI-MON') $tuition = (float)$cs['amount'];
        if ($cs['code'] === 'EXAM-FEE') $examFee = (float)$cs['amount'];
        if ($cs['code'] === 'LAB-FEE') $labFee = (float)$cs['amount'];
        if ($cs['code'] === 'SPT-FEE') $sportsFee = (float)$cs['amount'];
    }
}
$grossTotal = $tuition + $examFee + $labFee + $sportsFee;
$lateSurcharge = 200.00;

$schoolName = getSetting('school_name', 'Generation Model School');
$schoolPhone = getSetting('phone', '+92 (51) 555-0199');
$schoolAddress = getSetting('address', 'Main Campus, Educational Zone, Islamabad');
$issueDate = date('d-M-Y');
$dueDate = date('10-M-Y', strtotime('+1 month'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Fee Challans - <?= e($cls['class_name']) ?> (<?= count($students) ?> Students)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg">
    <style>
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 9.5px;
            margin: 0;
            padding: 10px;
        }

        .challan-page {
            max-width: 1180px;
            margin: 0 auto 20px auto;
            background: #fff;
            padding: 10px;
            border-radius: 6px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .challan-grid {
            display: flex;
            flex-direction: row;
            gap: 10px;
        }

        .challan-slip {
            flex: 1;
            border: 1.5px solid #1e293b;
            border-radius: 4px;
            padding: 8px 10px;
            position: relative;
            background: #fff;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .challan-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 170px;
            height: 170px;
            opacity: 0.13;
            pointer-events: none;
            z-index: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .challan-watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            filter: grayscale(15%);
        }

        .challan-content {
            position: relative;
            z-index: 1;
        }

        .slip-header-badge {
            background: #0f172a;
            color: #fff;
            font-weight: 800;
            font-size: 8.5px;
            padding: 2px 8px;
            border-radius: 3px;
            text-transform: uppercase;
        }

        .table-mini {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 4px;
        }

        .table-mini td, .table-mini th {
            padding: 2px 4px;
            border: 1px solid #cbd5e1;
        }

        .table-mini th {
            background: #f1f5f9;
            font-weight: 700;
        }

        .meta-list {
            width: 100%;
            font-size: 9px;
            margin-bottom: 4px;
        }

        .meta-list td {
            padding: 1px 2px;
            vertical-align: top;
        }

        .bank-box {
            background: #f8fafc;
            border: 1px dashed #64748b;
            padding: 3px 5px;
            border-radius: 4px;
            font-size: 8px;
            margin-bottom: 4px;
        }

        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print {
                display: none !important;
            }
            .challan-page {
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                margin-bottom: 0 !important;
                page-break-after: always;
            }
            .challan-slip {
                border: 1.5px solid #000 !important;
                page-break-inside: avoid;
            }
            .challan-watermark {
                display: flex !important;
                opacity: 0.13 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            @page {
                size: A4 landscape;
                margin: 5mm;
            }
        }
    </style>
</head>
<body>

<!-- Header Actions Toolbar (Hidden on Print) -->
<div class="container-fluid mb-3 no-print" style="max-width: 1180px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-3 rounded-4 shadow-sm border gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success px-3 py-1"><i class="fas fa-layer-group me-1"></i> Batch Fee Challan Printing</span>
                <span class="fw-bold fs-6"><?= e($cls['class_name']) ?></span>
                <span class="badge bg-primary rounded-pill"><?= count($students) ?> Students Total</span>
            </div>
            <div class="text-muted small mt-1">
                Print whole class fee challans at once &bull; Each page contains Bank, School, and Student copies.
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Filter by Class / Section -->
            <form method="GET" action="<?= BASE_PATH ?>/fees/challan-batch.php" class="d-flex gap-1">
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= e($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($sections)): ?>
                <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All Sections</option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?= $sec['id'] ?>" <?= $sectionId == $sec['id'] ? 'selected' : '' ?>><?= e($sec['section_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </form>

            <button type="button" onclick="window.print()" class="btn btn-success px-4 py-2 rounded-pill shadow fw-bold">
                <i class="fas fa-print me-2"></i> Print All <?= count($students) ?> Challans (Ctrl + P)
            </button>
            <a href="<?= BASE_PATH ?>/academics/class-view.php?id=<?= $classId ?>" class="btn btn-light btn-sm rounded-pill px-3 py-2 border">
                <i class="fas fa-arrow-left me-1"></i> Class Details
            </a>
        </div>
    </div>
</div>

<?php if (empty($students)): ?>
    <div class="container text-center py-5 no-print">
        <i class="fas fa-users-slash fs-1 text-muted mb-3 d-block"></i>
        <h5>No active students found in <?= e($cls['class_name']) ?></h5>
        <a href="<?= BASE_PATH ?>/academics/class-view.php?id=<?= $classId ?>" class="btn btn-primary rounded-pill px-4 mt-2">Back to Class</a>
    </div>
<?php else: ?>
    <?php foreach ($students as $sIdx => $student): 
        $challanNo = 'CHL-' . date('Ym') . '-' . str_pad($student['id'], 4, '0', STR_PAD_LEFT);
        $discountPct = floatval($student['scholarship_pct'] ?? 0);
        $discountAmount = round(($grossTotal * ($discountPct / 100)), 2);
        $netTotal = max(0, $grossTotal - $discountAmount);
        $afterDueDateTotal = $netTotal + $lateSurcharge;
    ?>
    <div class="challan-page">
        <div class="challan-grid">
            <?php 
            $copies = [
                ['title' => 'Bank Copy', 'badge' => '#0f172a', 'note' => 'Retained by Bank'],
                ['title' => 'School / Accounts Copy', 'badge' => '#1e3a8a', 'note' => 'Submit to Accounts'],
                ['title' => 'Student / Parent Copy', 'badge' => '#065f46', 'note' => 'Student Record']
            ];
            foreach ($copies as $copy):
            ?>
            <div class="challan-slip">
                <div class="challan-watermark">
                    <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Watermark" onerror="this.style.display='none'">
                </div>

                <div class="challan-content">
                    <div class="text-center pb-1 mb-1 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="slip-header-badge" style="background-color: <?= $copy['badge'] ?>;"><?= $copy['title'] ?></span>
                            <span class="fw-bold font-monospace text-danger" style="font-size: 9px;"><?= $challanNo ?></span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center gap-1 my-1">
                            <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" class="rounded-circle" style="width: 24px; height: 24px; object-fit: cover;" onerror="this.style.display='none'">
                            <strong class="text-uppercase" style="font-size: 10.5px;"><?= e($schoolName) ?></strong>
                        </div>
                        <div class="text-muted" style="font-size: 7.5px;"><?= e($schoolAddress) ?> &bull; Tel: <?= e($schoolPhone) ?></div>
                    </div>

                    <div class="bank-box">
                        <strong>HBL A/C #: 0192-8849102-01 &bull; Generation Model School</strong>
                    </div>

                    <table class="meta-list">
                        <tr>
                            <td width="30%" class="text-muted">Student:</td>
                            <td width="70%"><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Father:</td>
                            <td><?= e($student['father_name'] ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Adm & Roll:</td>
                            <td><code><?= e($student['admission_no']) ?></code> &bull; Roll: <strong><?= e($student['roll_no'] ?: '-') ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Class & Sec:</td>
                            <td><strong><?= e($cls['class_name'] . ' - ' . ($student['section_name'] ?: 'A')) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Type / Quota:</td>
                            <td><?= e($student['student_type'] ?? 'Coeducation') ?> <?= !empty($student['scholarship_title']) ? '('.e($student['scholarship_title']).')' : '' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Due Date:</td>
                            <td><strong class="text-danger"><?= $dueDate ?></strong> (Issue: <?= $issueDate ?>)</td>
                        </tr>
                    </table>

                    <table class="table-mini">
                        <thead>
                            <tr>
                                <th>Particulars</th>
                                <th class="text-end" width="35%">PKR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Monthly Tuition Fee</td>
                                <td class="text-end"><?= number_format($tuition, 2) ?></td>
                            </tr>
                            <tr>
                                <td>Examination Assessment Fee</td>
                                <td class="text-end"><?= number_format($examFee, 2) ?></td>
                            </tr>
                            <tr>
                                <td>Computer & Science Lab Fee</td>
                                <td class="text-end"><?= number_format($labFee, 2) ?></td>
                            </tr>
                            <tr>
                                <td>Sports & Extracurricular Fee</td>
                                <td class="text-end"><?= number_format($sportsFee, 2) ?></td>
                            </tr>
                            <?php if ($discountAmount > 0): ?>
                            <tr class="table-success">
                                <td>Concession (<?= number_format($discountPct, 0) ?>%)</td>
                                <td class="text-end text-success">- <?= number_format($discountAmount, 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="fw-bold bg-light">
                                <td>Payable Within Due Date</td>
                                <td class="text-end text-primary"><?= number_format($netTotal, 2) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Late Surcharge</td>
                                <td class="text-end text-muted"><?= number_format($lateSurcharge, 2) ?></td>
                            </tr>
                            <tr class="fw-bold">
                                <td>After Due Date</td>
                                <td class="text-end text-danger"><?= number_format($afterDueDateTotal, 2) ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="text-muted" style="font-size: 7px;">
                        * Valid at any bank branch. <?= $copy['note'] ?>.
                    </div>
                </div>

                <div class="d-flex justify-content-between pt-2 mt-1 border-top text-center" style="font-size: 8px;">
                    <div style="width: 45%; border-top: 1px dashed #64748b; padding-top: 2px;">
                        Bank Officer Stamp
                    </div>
                    <div style="width: 45%; border-top: 1px dashed #64748b; padding-top: 2px;">
                        Accounts Officer
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
});
</script>

</body>
</html>
