<?php
/**
 * Student Search & Cascading Dropdown API
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'search';

if ($action === 'get_sections') {
    $classId = (int)($_GET['class_id'] ?? 0);
    $sections = getSections($classId);
    echo json_encode(['success' => true, 'sections' => $sections]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode(['success' => true, 'students' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.id, s.admission_no, s.roll_no, s.first_name, s.last_name, s.gender,
           c.class_name, sec.section_name, p.father_name, p.phone as parent_phone
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents p ON s.parent_id = p.id
    WHERE s.status = 'active' AND (
        s.first_name LIKE ? OR 
        s.last_name LIKE ? OR 
        s.admission_no LIKE ? OR 
        s.roll_no LIKE ? OR
        p.father_name LIKE ?
    )
    ORDER BY s.first_name ASC
    LIMIT 15
");

$searchParam = "%{$q}%";
$stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
$students = $stmt->fetchAll();

// Append fee balance for each student
foreach ($students as &$st) {
    $st['fee_balance'] = getFeeBalance($st['id']);
}

echo json_encode(['success' => true, 'students' => $students]);
exit;
