<?php
/**
 * Fee Calculation Dynamic API
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$studentId = (int)($_POST['student_id'] ?? $_GET['student_id'] ?? 0);
$baseAmount = (float)($_POST['amount'] ?? 0);
$discountType = $_POST['discount_type'] ?? 'Fixed';
$discountVal = (float)($_POST['discount_value'] ?? 0);
$fine = (float)($_POST['fine'] ?? 0);
$paid = (float)($_POST['paid'] ?? 0);

$discountAmount = 0.00;
if ($discountType === 'Percentage') {
    $discountAmount = round(($baseAmount * $discountVal) / 100, 2);
} else {
    $discountAmount = min($baseAmount, $discountVal);
}

$totalPayable = max(0, $baseAmount - $discountAmount + $fine);
$balance = max(0, $totalPayable - $paid);

$previousBalance = 0.00;
if ($studentId > 0) {
    $previousBalance = getFeeBalance($studentId);
}

echo json_encode([
    'success' => true,
    'base_amount' => $baseAmount,
    'discount_amount' => $discountAmount,
    'fine_amount' => $fine,
    'total_payable' => $totalPayable,
    'paid_amount' => $paid,
    'balance_remaining' => $balance,
    'previous_arrears' => $previousBalance,
    'grand_total_with_arrears' => $previousBalance + $totalPayable
]);
exit;
