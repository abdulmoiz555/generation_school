<?php
/**
 * 3-Part School Fee Challan (Bank Copy, School Copy, Student Copy)
 * With Generation Model School watermark, bank details, and fee breakdown
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$studentId = (int)($_GET['student_id'] ?? 0);
$searchQ = trim($_GET['q'] ?? '');

if ($studentId <= 0 && !empty($searchQ)) {
    // Search student by admission_no, roll_no, or name
    $findStmt = $pdo->prepare("SELECT id FROM students WHERE admission_no = ? OR roll_no = ? OR first_name LIKE ? OR last_name LIKE ? LIMIT 1");
    $findStmt->execute([$searchQ, $searchQ, "%{$searchQ}%", "%{$searchQ}%"]);
    $found = $findStmt->fetch();
    if ($found) {
        $studentId = (int)$found['id'];
    }
}

// Fallback to first student if none specified
if ($studentId <= 0) {
    $firstSt = $pdo->query("SELECT id FROM students WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetch();
    $studentId = $firstSt ? (int)$firstSt['id'] : 1;
}

$student = getStudent($studentId);
if (!$student) {
    die("Student record not found.");
}

$activeSession = getActiveSession();
$challanNo = 'CHL-' . date('Ym') . '-' . str_pad($student['id'], 4, '0', STR_PAD_LEFT);
$issueDate = date('d-M-Y');
$dueDate = date('10-M-Y', strtotime('+1 month'));

// Standard class fee breakdown
$tuition = 3500.00;
$examFee = 1500.00;
$labFee = 1000.00;
$sportsFee = 800.00;

// Fetch class fee structures if configured
$fsStmt = $pdo->prepare("SELECT fs.*, ft.code, ft.name as fee_name FROM fee_structures fs JOIN fee_types ft ON fs.fee_type_id = ft.id WHERE fs.class_id = ?");
$fsStmt->execute([$student['class_id']]);
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
$discountPct = floatval($student['scholarship_pct'] ?? 0);
$discountAmount = round(($grossTotal * ($discountPct / 100)), 2);
$netTotal = max(0, $grossTotal - $discountAmount);
$lateSurcharge = 200.00;
$afterDueDateTotal = $netTotal + $lateSurcharge;

$schoolName = getSetting('school_name', 'Generation Model School');
$schoolPhone = getSetting('phone', '+92 (51) 555-0199');
$schoolAddress = getSetting('address', 'Main Campus, Educational Zone, Islamabad');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Challan #<?= $challanNo ?> - <?= e($student['first_name'] . ' ' . $student['last_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg">
    <style>
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 10px;
        }

        .challan-page {
            max-width: 1180px;
            margin: 0 auto;
            background: #fff;
            padding: 12px;
            border-radius: 8px;
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
            padding: 10px;
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
            letter-spacing: 0.5px;
        }

        .table-mini {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-bottom: 6px;
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
            font-size: 9.5px;
            margin-bottom: 6px;
        }

        .meta-list td {
            padding: 1px 3px;
            vertical-align: top;
        }

        .bank-box {
            background: #f8fafc;
            border: 1px dashed #64748b;
            padding: 4px 6px;
            border-radius: 4px;
            font-size: 8.5px;
            margin-bottom: 6px;
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
                margin: 6mm;
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
                <span class="badge bg-success px-3 py-1"><i class="fas fa-file-invoice-dollar me-1"></i> Fee Challan</span>
                <span class="fw-bold fs-6 font-monospace"><?= $challanNo ?></span>
            </div>
            <div class="text-muted small mt-1">
                Student: <strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong> &bull; Adm No: <code><?= e($student['admission_no']) ?></code> &bull; Class: <strong><?= e($student['class_name'] . ' - ' . $student['section_name']) ?></strong>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Search for another student challan -->
            <form method="GET" action="<?= BASE_PATH ?>/fees/challan.php" class="d-flex gap-1">
                <input type="text" name="q" class="form-control form-control-sm rounded-pill" placeholder="Adm No / Name / Roll No..." style="width: 180px;">
                <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="fas fa-search"></i></button>
            </form>

            <button type="button" onclick="window.print()" class="btn btn-success px-4 py-2 rounded-pill shadow fw-bold">
                <i class="fas fa-print me-2"></i> Print Challan (Ctrl + P)
            </button>
            <a href="<?= BASE_PATH ?>/fees/challan-batch.php?class_id=<?= $student['class_id'] ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3 py-2">
                <i class="fas fa-copy me-1"></i> Whole Class Batch Challans
            </a>
            <a href="<?= BASE_PATH ?>/academics/class-view.php?id=<?= $student['class_id'] ?>" class="btn btn-light btn-sm rounded-pill px-3 py-2 border">
                <i class="fas fa-arrow-left me-1"></i> Back to Class
            </a>
        </div>
    </div>
</div>

<div class="challan-page">
    <div class="challan-grid">
        <?php 
        $copies = [
            ['title' => 'Bank Copy', 'badge' => '#0f172a', 'note' => 'To be retained by Bank'],
            ['title' => 'School / Accounts Copy', 'badge' => '#1e3a8a', 'note' => 'Submit to Accounts Office'],
            ['title' => 'Student / Parent Copy', 'badge' => '#065f46', 'note' => 'Student retention copy']
        ];
        foreach ($copies as $idx => $copy):
        ?>
        <div class="challan-slip">
            <!-- Institutional Watermark -->
            <div class="challan-watermark">
                <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Watermark" onerror="this.style.display='none'">
            </div>

            <div class="challan-content">
                <!-- Header -->
                <div class="text-center pb-2 mb-2 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="slip-header-badge" style="background-color: <?= $copy['badge'] ?>;"><?= $copy['title'] ?></span>
                        <span class="fw-bold font-monospace text-danger" style="font-size: 9.5px;"><?= $challanNo ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-center gap-1 my-1">
                        <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" class="rounded-circle" style="width: 26px; height: 26px; object-fit: cover;" onerror="this.style.display='none'">
                        <strong class="text-uppercase" style="font-size: 11px;"><?= e($schoolName) ?></strong>
                    </div>
                    <div class="text-muted" style="font-size: 8px;"><?= e($schoolAddress) ?> &bull; Tel: <?= e($schoolPhone) ?></div>
                </div>

                <!-- Bank Info -->
                <div class="bank-box">
                    <strong>Habib Bank Limited (HBL) &bull; A/C #: 0192-8849102-01</strong><br>
                    <span>Branch: Super Market Islamabad &bull; Title: Generation Model School</span>
                </div>

                <!-- Student & Dates Meta -->
                <table class="meta-list">
                    <tr>
                        <td width="28%" class="text-muted">Student Name:</td>
                        <td width="72%"><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Father Name:</td>
                        <td><?= e($student['father_name'] ?: 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Adm No & Roll:</td>
                        <td><code><?= e($student['admission_no']) ?></code> &bull; Roll: <strong><?= e($student['roll_no'] ?: '-') ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Class & Section:</td>
                        <td><strong><?= e($student['class_name'] . ' - ' . $student['section_name']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Student Type:</td>
                        <td><?= e($student['student_type'] ?? 'Coeducation') ?> <?= !empty($student['scholarship_title']) ? '('.e($student['scholarship_title']).')' : '' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Issue & Due Date:</td>
                        <td>Issue: <?= $issueDate ?> &bull; <strong class="text-danger">Due: <?= $dueDate ?></strong></td>
                    </tr>
                </table>

                <!-- Fee Particulars Table -->
                <table class="table-mini">
                    <thead>
                        <tr>
                            <th>Particulars</th>
                            <th class="text-end" width="35%">Amount (PKR)</th>
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
                            <td>Concession (<?= e($student['scholarship_category'] ?: 'Scholarship') ?> <?= number_format($discountPct, 0) ?>%)</td>
                            <td class="text-end text-success">- <?= number_format($discountAmount, 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="fw-bold bg-light">
                            <td>Total Payable (Within Due Date)</td>
                            <td class="text-end text-primary fs-6"><?= number_format($netTotal, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Late Fee Surcharge (After Due Date)</td>
                            <td class="text-end text-muted"><?= number_format($lateSurcharge, 2) ?></td>
                        </tr>
                        <tr class="fw-bold">
                            <td>Payable After Due Date</td>
                            <td class="text-end text-danger"><?= number_format($afterDueDateTotal, 2) ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="text-muted" style="font-size: 7.5px;">
                    * Payments accepted at any designated bank branch or online bank transfer.<br>
                    * <?= $copy['note'] ?>.
                </div>
            </div>

            <!-- Signatures -->
            <div class="d-flex justify-content-between pt-3 mt-2 border-top text-center" style="font-size: 8.5px;">
                <div style="width: 45%; border-top: 1px dashed #64748b; padding-top: 2px;">
                    Bank Cashier / Officer Stamp
                </div>
                <div style="width: 45%; border-top: 1px dashed #64748b; padding-top: 2px;">
                    Accounts Officer Signature
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

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
