<?php
/**
 * POS Thermal Receipt (80mm / 58mm Thermal Printer Slip)
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
    <title>POS Receipt - #<?= e($payment['receipt_no']) ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            color: #000;
            font-family: 'Courier New', Courier, monospace, system-ui;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }

        /* 80mm POS Thermal Width (approx 300px) */
        .pos-receipt-wrapper {
            max-width: 320px;
            margin: 0 auto;
            background: #fff;
            padding: 18px 14px;
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #cbd5e1;
        }

        .pos-header {
            text-align: center;
            margin-bottom: 8px;
        }

        .pos-school-name {
            font-size: 14px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .pos-divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .pos-double-divider {
            border-top: 2px solid #000;
            margin: 8px 0;
        }

        .pos-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .pos-table td, .pos-table th {
            padding: 3px 0;
            vertical-align: top;
        }

        .pos-barcode {
            text-align: center;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
            font-weight: bold;
            font-size: 15px;
            margin: 10px 0 4px;
        }

        /* Print Settings for 80mm Thermal Rolls */
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .pos-receipt-wrapper {
                box-shadow: none !important;
                border: none !important;
                padding: 4mm !important;
                max-width: 100% !important;
                width: 78mm !important;
                margin: 0 auto !important;
            }
            @page {
                size: 80mm auto;
                margin: 0mm;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Toolbar (Hidden in Print) -->
<div class="container mb-3 no-print" style="max-width: 500px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-2 px-3 rounded-3 shadow-sm border gap-2">
        <span class="badge bg-dark fs-7"><i class="fas fa-print me-1"></i> POS 80mm Thermal</span>
        <div class="d-flex flex-wrap gap-1">
            <button type="button" onclick="triggerPrint()" class="btn btn-dark btn-sm rounded-pill px-3 shadow-sm fw-bold">
                <i class="fas fa-print me-1"></i> Print POS
            </button>
            <a href="<?= BASE_PATH ?>/fees/receipt-pos.php?id=<?= $payment['id'] ?>&autoprint=1" target="_blank" class="btn btn-primary btn-sm rounded-pill px-2 fw-semibold">
                <i class="fas fa-external-link-alt"></i> New Tab
            </a>
            <a href="<?= BASE_PATH ?>/fees/receipt-half-a4.php?id=<?= $payment['id'] ?>" class="btn btn-success btn-sm rounded-pill px-2">
                <i class="fas fa-copy me-1"></i> Half A4
            </a>
            <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $payment['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-2">
                Full A4
            </a>
            <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-light btn-sm rounded-pill px-2 border">
                All Slips
            </a>
        </div>
    </div>
</div>

<!-- POS Receipt Body -->
<div class="pos-receipt-wrapper">
    
    <div class="pos-header">
        <div class="text-center mb-1">
            <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" class="rounded-circle" style="width: 42px; height: 42px; object-fit: cover; border: 1px solid #000;" onerror="this.style.display='none'">
        </div>
        <div class="pos-school-name"><?= e($schoolName) ?></div>
        <div style="font-size: 10px;"><?= e($schoolAddress) ?></div>
        <div style="font-size: 10px;">Tel: <?= e($schoolPhone) ?></div>
        <div class="fw-bold mt-1" style="font-size: 11px;">*** OFFICIAL FEE RECEIPT ***</div>
    </div>

    <div class="pos-divider"></div>

    <table class="pos-table">
        <tr>
            <td>Receipt No:</td>
            <td class="text-end fw-bold">#<?= e($payment['receipt_no']) ?></td>
        </tr>
        <tr>
            <td>Date & Time:</td>
            <td class="text-end"><?= date('d-M-Y H:i', strtotime($payment['created_at'] ?? $payment['payment_date'])) ?></td>
        </tr>
        <tr>
            <td>Session:</td>
            <td class="text-end"><?= e($payment['session_name'] ?? '2025-2026') ?></td>
        </tr>
        <tr>
            <td>Cashier:</td>
            <td class="text-end"><?= e($payment['receiver_name'] ?: 'Cashier') ?></td>
        </tr>
    </table>

    <div class="pos-divider"></div>

    <!-- Student Details -->
    <table class="pos-table">
        <tr>
            <td>Student:</td>
            <td class="text-end fw-bold"><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></td>
        </tr>
        <tr>
            <td>Admission No:</td>
            <td class="text-end"><?= e($payment['admission_no']) ?></td>
        </tr>
        <tr>
            <td>Class & Sec:</td>
            <td class="text-end"><?= e($payment['class_name'] . ' - ' . $payment['section_name']) ?></td>
        </tr>
        <tr>
            <td>Father:</td>
            <td class="text-end"><?= e($payment['father_name'] ?: 'N/A') ?></td>
        </tr>
    </table>

    <div class="pos-divider"></div>

    <!-- Line Items -->
    <table class="pos-table">
        <thead>
            <tr style="border-bottom: 1px dashed #000;">
                <th class="text-start">Particulars</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td>School Tuition & Fees</td>
                    <td class="text-end"><?= formatCurrency($payment['paid_amount']) ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div><?= e($it['fee_name']) ?></div>
                            <small class="text-muted"><?= e($it['month']) ?></small>
                        </td>
                        <td class="text-end fw-bold"><?= formatCurrency($it['amount_applied']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pos-divider"></div>

    <!-- Financial Totals -->
    <table class="pos-table">
        <tr>
            <td>Gross Subtotal:</td>
            <td class="text-end"><?= formatCurrency($payment['total_amount'] + $payment['discount_amount'] - $payment['fine_amount']) ?></td>
        </tr>
        <?php if ($payment['discount_amount'] > 0): ?>
            <tr>
                <td>Discount / Concession:</td>
                <td class="text-end">-<?= formatCurrency($payment['discount_amount']) ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($payment['fine_amount'] > 0): ?>
            <tr>
                <td>Late Fine:</td>
                <td class="text-end">+<?= formatCurrency($payment['fine_amount']) ?></td>
            </tr>
        <?php endif; ?>
        <tr class="fw-bold" style="font-size: 13px;">
            <td>NET PAID:</td>
            <td class="text-end"><?= formatCurrency($payment['paid_amount']) ?></td>
        </tr>
        <tr>
            <td>Payment Method:</td>
            <td class="text-end"><?= e($payment['payment_method']) ?></td>
        </tr>
        <?php if ($payment['balance_remaining'] > 0): ?>
            <tr class="fw-bold">
                <td>Balance Due:</td>
                <td class="text-end"><?= formatCurrency($payment['balance_remaining']) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($payment['transaction_ref'])): ?>
        <div style="font-size: 10px; margin-top: 4px;">
            Ref #: <?= e($payment['transaction_ref']) ?>
        </div>
    <?php endif; ?>

    <div class="pos-double-divider"></div>

    <!-- Verification & Footer -->
    <div class="pos-barcode">
        ||| | ||||| || |||||| | |||
    </div>
    <div class="text-center font-monospace" style="font-size: 9px; letter-spacing: 1px;">
        *<?= e($payment['receipt_no']) ?>*
    </div>

    <div class="text-center mt-2" style="font-size: 10px;">
        Thank you for your payment!<br>
        Please keep this receipt for records.<br>
        <span style="font-size: 8.5px;">Powered by EduManage Portal</span>
    </div>

</div>

<script>
function triggerPrint() {
    window.focus();
    try {
        window.print();
    } catch (e) {
        console.warn('POS print error:', e);
        try {
            if (window.parent && window.parent !== window && window.parent.print) {
                window.parent.focus();
                window.parent.print();
            }
        } catch(ex) {}
    }
}

window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        triggerPrint();
    }
});

window.addEventListener('load', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('autoprint') === '1' || params.get('print') === '1') {
        setTimeout(triggerPrint, 400);
    }
});
</script>

</body>
</html>
