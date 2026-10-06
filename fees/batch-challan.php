<?php
/**
 * Whole Class Batch Fee Challan & Individual Challan Printable Generator
 * Generation Model School
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_view');

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
$monthYear = $_GET['month'] ?? date('F Y');

$students = [];
$classInfo = null;

if ($studentId) {
    // Single Student Challan
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, c.numeric_level, sec.section_name, p.father_name, p.phone as father_phone, p.cnic as father_cnic
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents p ON s.parent_id = p.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
    $st = $stmt->fetch();
    if ($st) {
        $students[] = $st;
        $classId = (int)$st['class_id'];
    }
} elseif ($classId) {
    // Whole Class Batch Challans
    $sql = "
        SELECT s.*, c.class_name, c.numeric_level, sec.section_name, p.father_name, p.phone as father_phone, p.cnic as father_cnic
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents p ON s.parent_id = p.id
        WHERE s.class_id = ? AND s.status = 'active'
    ";
    $params = [$classId];
    if ($sectionId) {
        $sql .= " AND s.section_id = ?";
        $params[] = $sectionId;
    }
    $sql .= " ORDER BY s.roll_no ASC, s.first_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
}

if ($classId) {
    $cStmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $cStmt->execute([$classId]);
    $classInfo = $cStmt->fetch();
}

// Fetch fee structures for this class
$feeItems = [];
if ($classId) {
    $fsStmt = $pdo->prepare("
        SELECT fs.*, ft.name as fee_name
        FROM fee_structures fs
        JOIN fee_types ft ON fs.fee_type_id = ft.id
        WHERE fs.class_id = ?
    ");
    $fsStmt->execute([$classId]);
    $feeItems = $fsStmt->fetchAll();
}

// Default fee items if not explicitly set
if (empty($feeItems)) {
    $feeItems = [
        ['fee_name' => 'Tuition Fee (' . $monthYear . ')', 'amount' => 3800],
        ['fee_name' => 'Examination & Test Fund', 'amount' => 500],
        ['fee_name' => 'Computer & Science Lab', 'amount' => 400],
        ['fee_name' => 'Utility & Generator Charges', 'amount' => 300],
    ];
}

$schoolName = getSetting('school_name', 'Generation Model School');
$schoolAddress = getSetting('school_address', 'Main Campus, Model Town, Lahore');
$schoolPhone = getSetting('school_phone', '+92 42 35889900');
$schoolEmail = getSetting('school_email', 'info@generation.edu.pk');
$bankName = getSetting('bank_name', 'Habib Bank Limited (HBL)');
$bankAccount = getSetting('bank_account', 'Generation Model School - A/C 0145-79012345-01');
$dueDate = date('10-M-Y', strtotime('+10 days'));
$issueDate = date('d-M-Y');
$lateFeeFine = 300;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Challans Batch - <?= htmlspecialchars($classInfo['class_name'] ?? 'Students') ?> | <?= htmlspecialchars($schoolName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #0f172a;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        
        .no-print-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #1e293b;
            color: white;
            padding: 12px 24px;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .challan-page {
            width: 100%;
            max-width: 1100px;
            margin: 80px auto 40px auto;
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            page-break-after: always;
        }

        .challan-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        .challan-slip {
            border: 1.5px dashed #64748b;
            border-radius: 6px;
            padding: 14px;
            background: #ffffff;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 580px;
        }

        .challan-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            opacity: 0.05;
            pointer-events: none;
            width: 180px;
            text-align: center;
        }

        .challan-watermark img {
            width: 100%;
            filter: grayscale(100%);
        }

        .slip-header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .slip-title {
            font-weight: 800;
            font-size: 13px;
            text-transform: uppercase;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .slip-copy-badge {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 4px;
            margin-top: 4px;
        }

        .info-table, .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 10px;
        }

        .info-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .info-label {
            font-weight: 600;
            color: #475569;
            width: 35%;
        }

        .info-val {
            font-weight: 700;
            color: #0f172a;
        }

        .fee-table th {
            background: #f8fafc;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 4px 6px;
            text-align: left;
            font-weight: 700;
            color: #334155;
            font-size: 9.5px;
        }

        .fee-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #f1f5f9;
        }

        .fee-table .amount-col {
            text-align: right;
            font-weight: 600;
        }

        .total-row {
            background: #f8fafc;
            border-top: 1.5px solid #0f172a;
            border-bottom: 1.5px solid #0f172a;
            font-weight: 800;
            font-size: 11px;
        }

        .bank-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px;
            margin-top: 6px;
            font-size: 9px;
            line-height: 1.3;
        }

        .signature-box {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 15px;
        }

        .sig-line {
            width: 45%;
            border-top: 1px dashed #64748b;
            text-align: center;
            font-size: 9px;
            font-weight: 600;
            color: #475569;
        }

        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            .no-print-toolbar {
                display: none !important;
            }
            .challan-page {
                margin: 0 !important;
                padding: 10px !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
            .challan-slip {
                border: 1px solid #000000 !important;
            }
            @page {
                size: A4 landscape;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>

<!-- Floating Action Bar (Hidden when printing) -->
<div class="no-print-toolbar">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= BASE_PATH ?>/academics/class-details.php?id=<?= $classId ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i> Return to Class Details
        </a>
        <span class="fw-bold">
            <i class="fas fa-file-invoice-dollar text-warning me-2"></i>
            <?= htmlspecialchars($classInfo['class_name'] ?? 'Class') ?> Challans Batch &bull; <?= count($students) ?> Students
        </span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <!-- Month Selector Form with All 12 Months -->
        <form method="GET" class="d-flex align-items-center gap-1 m-0">
            <?php if ($classId): ?><input type="hidden" name="class_id" value="<?= $classId ?>"><?php endif; ?>
            <?php if ($studentId): ?><input type="hidden" name="student_id" value="<?= $studentId ?>"><?php endif; ?>
            <?php if ($sectionId): ?><input type="hidden" name="section_id" value="<?= $sectionId ?>"><?php endif; ?>
            <select name="month" class="form-select form-select-sm rounded-pill bg-dark text-white border-secondary" style="width: 170px;" onchange="this.form.submit()">
                <?php 
                $allMonthsList = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                $curYear = date('Y');
                foreach ($allMonthsList as $mName): 
                    $val = $mName . ' ' . $curYear;
                    $isSelected = ($monthYear === $val || (isset($_GET['month']) && $_GET['month'] === $val));
                ?>
                    <option value="<?= $val ?>" <?= $isSelected ? 'selected' : '' ?>>
                        <?= $val ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <button onclick="window.print()" class="btn btn-warning btn-sm rounded-pill px-4 fw-bold text-dark shadow-sm">
            <i class="fas fa-print me-1"></i> Print All <?= count($students) ?> Challans
        </button>
    </div>
</div>

<?php if (empty($students)): ?>
    <div class="challan-page text-center py-5">
        <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
        <h4>No active students found in this class</h4>
        <p class="text-muted">Please ensure students are admitted into this class or check your filter criteria.</p>
        <a href="<?= BASE_PATH ?>/academics/class-details.php?id=<?= $classId ?>" class="btn btn-primary rounded-pill px-4">Go to Class Details</a>
    </div>
<?php else: ?>
    <?php foreach ($students as $st): 
        $challanNo = "GMS-" . date('y') . "-" . str_pad($st['id'], 4, '0', STR_PAD_LEFT);
        
        // Calculate Total
        $subtotal = 0;
        foreach ($feeItems as $item) {
            $subtotal += (float)$item['amount'];
        }
        $totalAfterDue = $subtotal + $lateFeeFine;

        $copies = [
            ['title' => 'Bank Copy', 'badge_class' => 'bg-secondary'],
            ['title' => 'School / Office Copy', 'badge_class' => 'bg-primary'],
            ['title' => 'Student / Parent Copy', 'badge_class' => 'bg-dark'],
        ];
    ?>
        <div class="challan-page">
            <div class="challan-grid">
                <?php foreach ($copies as $copy): ?>
                    <div class="challan-slip">
                        <!-- Watermark -->
                        <div class="challan-watermark">
                            <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" onerror="this.style.display='none'">
                        </div>

                        <div>
                            <!-- Header -->
                            <div class="slip-header">
                                <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                    <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" style="width: 24px; height: 24px; border-radius: 50%;" onerror="this.style.display='none'">
                                    <div class="slip-title"><?= htmlspecialchars($schoolName) ?></div>
                                </div>
                                <div class="text-muted" style="font-size: 8.5px;"><?= htmlspecialchars($schoolAddress) ?></div>
                                <span class="slip-copy-badge <?= $copy['badge_class'] ?>"><?= $copy['title'] ?></span>
                            </div>

                            <!-- Metadata -->
                            <table class="info-table">
                                <tr>
                                    <td class="info-label">Challan No:</td>
                                    <td class="info-val text-danger fw-bold"><?= $challanNo ?></td>
                                    <td class="info-label text-end">Date:</td>
                                    <td class="info-val text-end"><?= $issueDate ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Admission No:</td>
                                    <td class="info-val"><?= htmlspecialchars($st['admission_no']) ?></td>
                                    <td class="info-label text-end">Roll No:</td>
                                    <td class="info-val text-end"><?= htmlspecialchars($st['roll_no'] ?: '-') ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Student Name:</td>
                                    <td class="info-val" colspan="3"><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Father's Name:</td>
                                    <td class="info-val" colspan="3"><?= htmlspecialchars($st['father_name'] ?: 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Class & Section:</td>
                                    <td class="info-val" colspan="3">
                                        <?= htmlspecialchars($st['class_name'] ?? 'Class') ?> - <?= htmlspecialchars($st['section_name'] ?? 'A') ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="info-label">Fee Month:</td>
                                    <td class="info-val" colspan="3" style="color:#0284c7;"><?= htmlspecialchars($monthYear) ?></td>
                                </tr>
                            </table>

                            <!-- Fee Particulars Table -->
                            <table class="fee-table">
                                <thead>
                                    <tr>
                                        <th>Fee Particulars</th>
                                        <th class="amount-col">Amount (PKR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($feeItems as $fi): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($fi['fee_name']) ?></td>
                                            <td class="amount-col"><?= number_format((float)$fi['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td>Total Payable within Due Date</td>
                                        <td class="amount-col">PKR <?= number_format($subtotal) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger fw-semibold">Late Fee Fine after Due Date</td>
                                        <td class="amount-col text-danger fw-semibold"><?= number_format($lateFeeFine) ?></td>
                                    </tr>
                                    <tr class="fw-bold" style="background:#fef2f2;">
                                        <td class="text-danger">Total Payable after Due Date</td>
                                        <td class="amount-col text-danger">PKR <?= number_format($totalAfterDue) ?></td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Bank Deposit Details -->
                            <div class="bank-box">
                                <div><strong>Bank:</strong> <?= htmlspecialchars($bankName) ?></div>
                                <div><strong>Account:</strong> <?= htmlspecialchars($bankAccount) ?></div>
                                <div class="text-danger"><strong>Due Date:</strong> <?= $dueDate ?></div>
                            </div>
                        </div>

                        <!-- Signatures -->
                        <div>
                            <div class="signature-box">
                                <div class="sig-line">Bank Cashier / Stamp</div>
                                <div class="sig-line">Authorized Officer</div>
                            </div>
                            <div class="text-center text-muted mt-2" style="font-size: 8px;">
                                Fees deposited once is non-refundable & non-transferable.
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
    // Auto prompt print if param ?print=1
    if (new URLSearchParams(window.location.search).get('print') === '1') {
        window.addEventListener('load', () => window.print());
    }
</script>

</body>
</html>
