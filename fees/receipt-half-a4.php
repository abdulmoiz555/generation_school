<?php
/**
 * Half A4 Dual Slip - School Copy & Parent Copy (Side-by-Side)
 * With Generation Model School Watermark & Robust Printing Support
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$paymentId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT p.*, s.first_name, s.last_name, s.admission_no, s.roll_no, s.student_type,
           c.class_name, sec.section_name, pr.father_name,
           u.full_name as receiver_name, sess.session_name
    FROM fee_payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents pr ON s.parent_id = pr.id
    LEFT JOIN users u ON p.received_by = u.id
    LEFT JOIN academic_sessions sess ON p.session_id = sess.id
    WHERE p.id = ?
");
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlashMessage('error', 'Payment record not found.');
    header("Location: " . BASE_PATH . "/fees/payments.php");
    exit;
}

// Payment item breakdown
$itemStmt = $pdo->prepare("
    SELECT fpi.*, sf.month, ft.name as fee_name
    FROM fee_payment_items fpi
    JOIN student_fees sf ON fpi.student_fee_id = sf.id
    JOIN fee_types ft ON sf.fee_type_id = ft.id
    WHERE fpi.payment_id = ?
");
$itemStmt->execute([$paymentId]);
$items = $itemStmt->fetchAll();

$schoolName = getSetting('school_name', 'Generation Model School');
$schoolPhone = getSetting('phone', '+1 (800) 555-0199');
$schoolAddress = getSetting('address', 'Main Campus, Educational Zone, Islamabad');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Slip #<?= e($payment['receipt_no']) ?> | <?= e($schoolName) ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg">
    <style>
        body {
            background-color: #f1f5f9;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 15px;
        }

        /* Half A4 Dual Grid Layout */
        .dual-slip-container {
            display: flex;
            flex-direction: row;
            gap: 12px;
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .slip-half {
            flex: 1;
            border: 1.5px solid #334155;
            border-radius: 6px;
            padding: 14px;
            background: #fff;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        /* Enhanced School Logo Watermark on both school and parent copies */
        .slip-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 220px;
            height: 220px;
            opacity: 0.14;
            pointer-events: none;
            z-index: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .slip-watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            filter: grayscale(15%);
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .slip-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .slip-badge {
            display: inline-block;
            background: #0f172a;
            color: #fff;
            font-weight: 700;
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .slip-header {
            text-align: center;
            border-bottom: 1.5px dashed #64748b;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .school-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            color: #0f172a;
        }

        .meta-table, .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }

        .meta-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
        }

        .data-table th {
            background-color: #f8fafc;
            font-weight: 700;
        }

        .divider-cut {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            border-left: 2px dashed #94a3b8;
            margin: 0 4px;
            position: relative;
        }

        .divider-icon {
            background: #fff;
            padding: 4px 0;
            color: #64748b;
            font-size: 12px;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
        }

        .sign-box {
            display: flex;
            justify-content: space-between;
            margin-top: 14px;
            padding-top: 6px;
        }

        .sign-col {
            text-align: center;
            font-size: 9.5px;
            font-weight: 600;
            border-top: 1px solid #94a3b8;
            padding-top: 3px;
            width: 42%;
        }

        /* Print Settings for standard A4 landscape or half-cut */
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

            .dual-slip-container {
                box-shadow: none !important;
                padding: 4px !important;
                max-width: 100% !important;
                gap: 8px !important;
                background: transparent !important;
            }

            .slip-half {
                border: 1.5px solid #000 !important;
                padding: 10px !important;
                page-break-inside: avoid;
            }

            .slip-watermark {
                opacity: 0.10 !important;
                display: flex !important;
                visibility: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .data-table th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .divider-cut {
                border-left: 1.5px dashed #000 !important;
            }

            @page {
                size: A4 landscape;
                margin: 6mm;
            }
        }
    </style>
</head>
<body>

<!-- Interactive Header & Action Toolbar (Hidden in Print) -->
<div class="container-fluid max-w-1100 mb-3 no-print" style="max-width: 1100px;">
    <!-- Active Notice if Autoprint triggered -->
    <div id="autoprintBanner" class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 rounded-3 mb-2 shadow-sm border-0" style="display: none !important;">
        <div>
            <i class="fas fa-spinner fa-spin me-2"></i> <strong>Preparing Print Slip:</strong> Your print dialog should open automatically. If not, press <strong>Print Slip Now</strong> below or press <kbd>Ctrl + P</kbd>.
        </div>
        <button type="button" class="btn-close" onclick="document.getElementById('autoprintBanner').remove()"></button>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-3 rounded-4 shadow-sm border gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success px-3 py-1"><i class="fas fa-copy me-1"></i> Half A4 Dual Slip</span>
                <span class="fw-bold text-dark fs-5 font-monospace">Slip #<?= e($payment['receipt_no']) ?></span>
            </div>
            <div class="text-muted small mt-1">
                Student: <strong><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></strong> &bull; Class: <strong><?= e($payment['class_name'] . ' - ' . $payment['section_name']) ?></strong> &bull; Paid: <strong class="text-success"><?= formatCurrency($payment['paid_amount']) ?></strong>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Huge Primary Print Button -->
            <button type="button" onclick="triggerPrint()" class="btn btn-success px-4 py-2 rounded-pill shadow fw-bold fs-6">
                <i class="fas fa-print me-2"></i> Print Slip Now (Ctrl + P)
            </button>
            <button type="button" onclick="printViaFrame()" class="btn btn-primary px-3 py-2 rounded-pill shadow-sm fw-semibold">
                <i class="fas fa-file-pdf me-1"></i> Print / Save PDF
            </button>
            <a href="<?= BASE_PATH ?>/fees/receipt-pos.php?id=<?= $payment['id'] ?>" class="btn btn-dark btn-sm rounded-pill px-3 py-2">
                <i class="fas fa-receipt me-1"></i> POS 80mm
            </a>
            <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $payment['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2">
                <i class="fas fa-file-invoice me-1"></i> Full A4
            </a>
            <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-light btn-sm rounded-pill px-3 py-2 border">
                <i class="fas fa-arrow-left me-1"></i> All Slips
            </a>
        </div>
    </div>
</div>

<!-- Dual Slip Container -->
<div class="dual-slip-container">

    <?php 
    // Render two slips side-by-side: 1 = School Copy, 2 = Parent / Student Copy
    $slips = [
        ['type' => 'School / Accounts Copy', 'badge_bg' => '#0f172a'],
        ['type' => 'Parent / Student Copy',  'badge_bg' => '#1e3a8a']
    ];
    
    foreach ($slips as $idx => $sInfo):
    ?>
    <div class="slip-half">
        <!-- School Logo Watermark in Both Sections -->
        <div class="slip-watermark">
            <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Watermark" onerror="this.style.display='none'">
        </div>

        <div class="slip-inner">
            <div>
                <!-- Slip Header -->
                <div class="slip-header">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="slip-badge" style="background-color: <?= $sInfo['badge_bg'] ?>;"><?= $sInfo['type'] ?></span>
                        <span class="fw-bold text-danger font-monospace" style="font-size: 11px;">#<?= e($payment['receipt_no']) ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-center gap-2 my-1">
                        <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover; border: 1px solid #94a3b8;" onerror="this.style.display='none'">
                        <div class="school-title mb-0"><?= e($schoolName) ?></div>
                    </div>
                    <div class="text-muted" style="font-size: 9px;"><?= e($schoolAddress) ?> &bull; Tel: <?= e($schoolPhone) ?></div>
                </div>

                <!-- Student & Receipt Metadata -->
                <table class="meta-table mb-2">
                    <tr>
                        <td width="20%" class="text-muted">Student:</td>
                        <td width="30%"><strong><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></strong></td>
                        <td width="20%" class="text-muted">Date:</td>
                        <td width="30%"><strong><?= formatDate($payment['payment_date']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Adm No:</td>
                        <td><code><?= e($payment['admission_no']) ?></code></td>
                        <td class="text-muted">Session:</td>
                        <td><?= e($payment['session_name'] ?: '2026-2027') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Class & Sec:</td>
                        <td><strong><?= e($payment['class_name'] . ' - ' . $payment['section_name']) ?></strong></td>
                        <td class="text-muted">Roll No:</td>
                        <td><?= e($payment['roll_no'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Father Name:</td>
                        <td><?= e($payment['father_name'] ?: 'N/A') ?></td>
                        <td class="text-muted">Pay Method:</td>
                        <td><span class="badge bg-light text-dark border p-1" style="font-size: 8px;"><?= e($payment['payment_method']) ?></span></td>
                    </tr>
                </table>

                <!-- Fee Particulars Table -->
                <table class="data-table mb-2">
                    <thead>
                        <tr>
                            <th width="8%">#</th>
                            <th>Fee Particulars</th>
                            <th width="24%">Month</th>
                            <th width="25%" class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $k => $it): ?>
                                <tr>
                                    <td><?= $k + 1 ?></td>
                                    <td><?= e($it['fee_name']) ?></td>
                                    <td><?= e($it['month'] ?: date('F Y')) ?></td>
                                    <td class="text-end fw-semibold"><?= formatCurrency($it['amount_applied']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td>1</td>
                                <td>Tuition & Institutional Fee</td>
                                <td><?= date('F Y') ?></td>
                                <td class="text-end fw-semibold"><?= formatCurrency($payment['paid_amount']) ?></td>
                            </tr>
                        <?php endif; ?>

                        <!-- Subtotal -->
                        <tr>
                            <td colspan="3" class="text-end text-muted">Base Total:</td>
                            <td class="text-end"><?= formatCurrency($payment['total_amount'] ?: $payment['paid_amount']) ?></td>
                        </tr>

                        <!-- Scholarship / Concession (if applied) -->
                        <?php if ((float)$payment['discount_amount'] > 0 || !empty($payment['concession_type'])): ?>
                            <tr>
                                <td colspan="3" class="text-end text-success fw-semibold">
                                    <i class="fas fa-gift me-1"></i> Concession (<?= e($payment['concession_type'] ?: 'Scholarship') ?>):
                                </td>
                                <td class="text-end text-success fw-bold">
                                    - <?= formatCurrency($payment['discount_amount']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <!-- Fine / Late Fee -->
                        <?php if ((float)$payment['fine_amount'] > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end text-danger fw-semibold">Late Surcharge:</td>
                                <td class="text-end text-danger fw-bold">+ <?= formatCurrency($payment['fine_amount']) ?></td>
                            </tr>
                        <?php endif; ?>

                        <!-- Net Paid Amount -->
                        <tr style="background-color: #f8fafc;">
                            <th colspan="3" class="text-end text-success" style="font-size: 11.5px;">TOTAL PAID (PKR):</th>
                            <th class="text-end text-success fw-bold" style="font-size: 12px;"><?= formatCurrency($payment['paid_amount']) ?></th>
                        </tr>

                        <?php if ((float)$payment['balance_remaining'] > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end text-danger fw-semibold">Remaining Arrears:</td>
                                <td class="text-end text-danger fw-bold"><?= formatCurrency($payment['balance_remaining']) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($payment['transaction_ref']) || !empty($payment['remarks'])): ?>
                    <div class="p-1 px-2 bg-light border rounded text-muted mb-2" style="font-size: 8.5px;">
                        <?= !empty($payment['transaction_ref']) ? 'Ref: ' . e($payment['transaction_ref']) . ' | ' : '' ?>
                        <?= !empty($payment['remarks']) ? 'Note: ' . e($payment['remarks']) : '' ?>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <!-- Signatures Section -->
                <div class="sign-box">
                    <div class="sign-col">
                        Received By: <?= e($payment['receiver_name'] ?: 'Cashier') ?><br>
                        <span class="text-muted fw-normal" style="font-size: 8px;">Authorized Officer</span>
                    </div>
                    <div class="sign-col">
                        Parent / Student<br>
                        <span class="text-muted fw-normal" style="font-size: 8px;">Signature</span>
                    </div>
                </div>

                <div class="text-center text-muted mt-2" style="font-size: 7.5px;">
                    Generation Model School &bull; Computer generated fee slip &bull; Valid without physical stamp.
                </div>
            </div>
        </div>
    </div>

    <?php if ($idx === 0): ?>
        <div class="divider-cut no-print">
            <span class="divider-icon"><i class="fas fa-cut"></i></span>
        </div>
    <?php endif; ?>

    <?php endforeach; ?>

</div>

<script>
function triggerPrint() {
    window.focus();
    try {
        window.print();
    } catch (e) {
        console.warn('Direct print error, falling back to frame:', e);
        printViaFrame();
    }
}

function printViaFrame() {
    window.focus();
    // In case browser or parent window has print handler
    try {
        if (window.parent && window.parent !== window && window.parent.print) {
            window.parent.focus();
            window.parent.print();
            return;
        }
    } catch (err) {
        // cross-origin parent fallback
    }
    window.print();
}

// Global keyboard shortcut: Ctrl+P or Cmd+P
window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        triggerPrint();
    }
});

// Auto-trigger print with safe delay if requested
window.addEventListener('load', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('autoprint') === '1' || params.get('print') === '1') {
        const banner = document.getElementById('autoprintBanner');
        if (banner) banner.style.setProperty('display', 'flex', 'important');
        setTimeout(() => {
            triggerPrint();
        }, 400);
    }
});
</script>

</body>
</html>
