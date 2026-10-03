<?php
/**
 * AJAX Attendance Save Endpoint
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !hasPermission('attendance_student')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$date = $_POST['attendance_date'] ?? date('Y-m-d');
$classId = (int)($_POST['class_id'] ?? 0);
$sectionId = (int)($_POST['section_id'] ?? 0);
$attendanceData = $_POST['attendance'] ?? []; // student_id => 'Present'|'Absent'|'Late'|'Leave'
$remarksData = $_POST['remarks'] ?? [];
$activeSession = getActiveSession();
$sessionId = $activeSession['id'] ?? 1;

if (empty($attendanceData)) {
    echo json_encode(['success' => false, 'message' => 'No attendance data received']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO student_attendance (session_id, student_id, class_id, section_id, attendance_date, status, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)
    ");

    $count = 0;
    foreach ($attendanceData as $studentId => $status) {
        $studentId = (int)$studentId;
        $status = in_array($status, ['Present', 'Absent', 'Late', 'Leave']) ? $status : 'Present';
        $remark = trim($remarksData[$studentId] ?? '');

        $stmt->execute([
            $sessionId,
            $studentId,
            $classId,
            $sectionId,
            $date,
            $status,
            $remark
        ]);
        $count++;
    }

    $pdo->commit();
    logAudit('SAVE_ATTENDANCE', 'Attendance', $classId, "Saved attendance for {$count} students on {$date}");
    echo json_encode(['success' => true, 'message' => "Attendance saved for {$count} students successfully!"]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Attendance save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error while saving attendance']);
}
exit;
