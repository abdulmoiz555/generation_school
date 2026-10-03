<?php
/**
 * Half A4 Dual Slip - School Copy & Parent Copy (Side-by-Side)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$paymentId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT p.*, s.first_name, s.last_name, s.admission_no, s.roll_no,
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

$schoolName = getSetting('school_name', 'Springfield International Academy');
$schoolPhone = getSetting('phone', '+1 555-0199');
$schoolAddress = getSetting('address', '742 Evergreen Terrace, Springfield');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Half A4 Dual Fee Slip - Receipt #<?= e($payment['receipt_no']) ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        }

        .sign-box {
            display: flex;
            justify-content: space-between;
            margin-top: 18px;
            padding-top: 8px;
        }

        .sign-col {
            text-align: center;
            width: 45%;
            border-top: 1px solid #334155;
            padding-top: 3px;
            font-size: 9.5px;
            font-weight: 600;
        }

        /* Print Settings for Half A4 Landscape */
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .dual-slip-container {
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                gap: 8mm !important;
            }
            .slip-half {
                border: 1.5px solid #000 !important;
                page-break-inside: avoid;
            }
            @page {
                size: A4 landscape;
                margin: 6mm;
            }
        }
    </style>
</head>
<body>

<!-- Interactive Header & Format Switcher (Hidden in Print) -->
<div class="container-fluid max-w-1100 mb-3 no-print" style="max-width: 1100px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border">
        <div>
            <span class="badge bg-primary me-2"><i class="fas fa-copy me-1"></i> Half A4 Format</span>
            <span class="fw-bold text-dark fs-6">Receipt #<?= e($payment['receipt_no']) ?></span>
            <span class="text-muted ms-2">(Student: <?= e($payment['first_name'] . ' ' . $payment['last_name']) ?>)</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fas fa-print me-1"></i> Print Half A4 (Dual Copy)
            </button>
            <a href="<?= BASE_PATH ?>/fees/receipt-pos.php?id=<?= $payment['id'] ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                <i class="fas fa-receipt me-1"></i> POS Thermal
            </a>
            <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $payment['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fas fa-file-alt me-1"></i> Standard A4
            </a>
            <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-light btn-sm rounded-pill px-3 border">
                <i class="fas fa-arrow-left me-1"></i> Back to Payments
            </a>
        </div>
    </div>
</div>

<!-- Dual Slip Container -->
<div class="dual-slip-container">

    <?php 
    // Render two slips: 1 = School Copy, 2 = Parent / Student Copy
    $slips = [
        ['type' => 'School / Accounts Copy', 'badge_bg' => '#0f172a'],
        ['type' => 'Parent / Student Copy',  'badge_bg' => '#1e3a8a']
    ];
    
    foreach ($slips as $idx => $sInfo):
    ?>
    <div class="slip-half">
        <div>
            <!-- Slip Header -->
            <div class="slip-header">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="slip-badge" style="background-color: <?= $sInfo['badge_bg'] ?>;"><?= $sInfo['type'] ?></span>
                    <span class="fw-bold text-danger font-monospace" style="font-size: 11px;">#<?= e($payment['receipt_no']) ?></span>
                </div>
                <div class="d-flex align-items-center justify-content-center gap-2 my-1">
                    <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" class="rounded-circle" style="width: 28px; height: 28px; object-fit: cover; border: 1px solid #94a3b8;" onerror="this.style.display='none'">
                    <div class="school-title mb-0"><?= e($schoolName) ?></div>
                </div>
                <div class="text-muted" style="font-size: 9px;"><?= e($schoolAddress) ?> &bull; Tel: <?= e($schoolPhone) ?></div>
            </div>

            <!-- Student & Receipt Metadata -->
            <table class="meta-table mb-2">
                <tr>
                    <td width="20%" class="text-muted">Student:</td>
                    <td width="35%"><strong><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></strong></td>
                    <td width="20%" class="text-muted">Date:</td>
                    <td width="25%"><strong><?= formatDate($payment['payment_date']) ?></strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Adm No:</td>
                    <td><code><?= e($payment['admission_no']) ?></code></td>
                    <td class="text-muted">Session:</td>
                    <td><?= e($payment['session_name'] ?? '2025-2026') ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Class/Sec:</td>
                    <td><strong><?= e($payment['class_name'] . ' - ' . $payment['section_name']) ?></strong> (Roll: <?= e($payment['roll_no'] ?: '-') ?>)</td>
                    <td class="text-muted">Method:</td>
                    <td><span class="badge bg-light text-dark border p-1" style="font-size: 9px;"><?= e($payment['payment_method']) ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">Father:</td>
                    <td colspan="3"><?= e($payment['father_name'] ?: 'N/A') ?></td>
                </tr>
            </table>

            <!-- Fee Items Table -->
            <table class="data-table mb-2">
                <thead>
                    <tr>
                        <th width="30">#</th>
                        <th>Particulars</th>
                        <th>Period</th>
                        <th class="text-end" width="75">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td>1</td>
                            <td>Tuition & Institutional Fees</td>
                            <td>Current</td>
                            <td class="text-end fw-bold"><?= formatCurrency($payment['paid_amount']) ?></td>
                        </tr>
                    <?php else: ?>
                        <?php $sr = 1; foreach ($items as $it): ?>
                            <tr>
                                <td><?= $sr++ ?></td>
                                <td><?= e($it['fee_name']) ?></td>
                                <td><?= e($it['month']) ?></td>
                                <td class="text-end"><?= formatCurrency($it['amount_applied']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <?php if ($payment['discount_amount'] > 0): ?>
                        <tr>
                            <td colspan="3" class="text-end text-success p-1">Discount/Concession:</td>
                            <td class="text-end text-success p-1 fw-bold">-<?= formatCurrency($payment['discount_amount']) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($payment['fine_amount'] > 0): ?>
                        <tr>
                            <td colspan="3" class="text-end text-danger p-1">Late Fine:</td>
                            <td class="text-end text-danger p-1 fw-bold">+<?= formatCurrency($payment['fine_amount']) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr style="background:#f1f5f9;">
                        <th colspan="3" class="text-end" style="font-size: 11px;">Total Amount Paid:</th>
                        <th class="text-end text-success" style="font-size: 12px;"><?= formatCurrency($payment['paid_amount']) ?></th>
                    </tr>
                    <?php if ($payment['balance_remaining'] > 0): ?>
                        <tr>
                            <td colspan="3" class="text-end text-danger p-1">Remaining Balance:</td>
                            <td class="text-end text-danger p-1 fw-bold"><?= formatCurrency($payment['balance_remaining']) ?></td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>

            <?php if (!empty($payment['transaction_ref']) || !empty($payment['remarks'])): ?>
                <div class="text-muted mb-2 font-monospace" style="font-size: 8.5px;">
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
                Computer generated fee slip &bull; Valid without physical stamp.
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

</body>
</html>
