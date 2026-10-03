<?php
/**
 * Marks Entry AJAX & Processing Endpoint
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !hasPermission('marks_entry')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$examId = (int)($_POST['exam_id'] ?? 0);
$examSubjectId = (int)($_POST['exam_subject_id'] ?? 0);
$marksObtainedArr = $_POST['marks_obtained'] ?? [];
$maxMarks = (float)($_POST['max_marks'] ?? 100);
$remarksArr = $_POST['remarks'] ?? [];
$userId = getCurrentUserId();

if (!$examId || !$examSubjectId || empty($marksObtainedArr)) {
    echo json_encode(['success' => false, 'message' => 'Missing exam or subject information']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO marks (exam_id, exam_subject_id, student_id, marks_obtained, max_marks, percentage, grade, is_passed, remarks, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            marks_obtained = VALUES(marks_obtained),
            max_marks = VALUES(max_marks),
            percentage = VALUES(percentage),
            grade = VALUES(grade),
            is_passed = VALUES(is_passed),
            remarks = VALUES(remarks),
            updated_at = CURRENT_TIMESTAMP
    ");

    $count = 0;
    foreach ($marksObtainedArr as $studentId => $obtained) {
        $studentId = (int)$studentId;
        $obtained = floatval($obtained);
        if ($obtained < 0) $obtained = 0;
        if ($obtained > $maxMarks) $obtained = $maxMarks;

        $percentage = ($maxMarks > 0) ? round(($obtained / $maxMarks) * 100, 2) : 0;
        $grade = calculateGrade($percentage);
        $isPassed = ($percentage >= 40) ? 1 : 0;
        $remark = trim($remarksArr[$studentId] ?? '');

        $stmt->execute([
            $examId,
            $examSubjectId,
            $studentId,
            $obtained,
            $maxMarks,
            $percentage,
            $grade,
            $isPassed,
            $remark,
            $userId
        ]);
        $count++;
    }

    $pdo->commit();
    logAudit('SAVE_MARKS', 'Examinations', $examSubjectId, "Saved marks for {$count} students");
    echo json_encode(['success' => true, 'message' => "Marks recorded for {$count} students successfully!"]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Marks save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error while saving marks']);
}
exit;
