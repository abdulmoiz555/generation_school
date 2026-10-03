<?php
/**
 * Core Application Helper Functions
 */
require_once __DIR__ . '/../config/config.php';

/**
 * Get active academic session
 */
function getActiveSession() {
    global $pdo;
    static $activeSession = null;
    if ($activeSession !== null) {
        return $activeSession;
    }
    
    $stmt = $pdo->query("SELECT * FROM academic_sessions WHERE is_current = 1 LIMIT 1");
    $activeSession = $stmt->fetch();
    if (!$activeSession) {
        // Fallback to first available session
        $stmt = $pdo->query("SELECT * FROM academic_sessions ORDER BY id DESC LIMIT 1");
        $activeSession = $stmt->fetch();
    }
    return $activeSession;
}

/**
 * Get student details with class, section, parent, and session info
 */
function getStudent($studentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT s.*, 
               c.class_name, 
               sec.section_name,
               p.father_name, p.mother_name, p.guardian_name, p.phone as parent_phone, p.email as parent_email,
               sess.session_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents p ON s.parent_id = p.id
        LEFT JOIN academic_sessions sess ON s.session_id = sess.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetch();
}

/**
 * Get class by ID
 */
function getClass($classId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? LIMIT 1");
    $stmt->execute([$classId]);
    return $stmt->fetch();
}

/**
 * Get section by ID
 */
function getSection($sectionId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ? LIMIT 1");
    $stmt->execute([$sectionId]);
    return $stmt->fetch();
}

/**
 * Get all classes
 */
function getAllClasses() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC, class_name ASC");
    return $stmt->fetchAll();
}

/**
 * Get sections for a given class or all sections
 */
function getSections($classId = null) {
    global $pdo;
    if ($classId) {
        $stmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? ORDER BY section_name ASC");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }
    $stmt = $pdo->query("SELECT s.*, c.class_name FROM sections s JOIN classes c ON s.class_id = c.id ORDER BY c.numeric_level, s.section_name");
    return $stmt->fetchAll();
}

/**
 * Get all academic sessions
 */
function getAllSessions() {
    global $pdo;
    return $pdo->query("SELECT * FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
}

/**
 * Calculate total pending fee balance for a student
 */
function getFeeBalance($studentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(balance), 0) as total_balance 
        FROM student_fees 
        WHERE student_id = ? AND status IN ('Pending', 'Partial', 'Overdue')
    ");
    $stmt->execute([$studentId]);
    $res = $stmt->fetch();
    return (float)($res['total_balance'] ?? 0);
}

/**
 * Generate unique fee receipt number
 */
function generateReceiptNumber() {
    global $pdo;
    $year = date('Y');
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM fee_payments");
    $maxId = (int)($stmt->fetch()['max_id'] ?? 0) + 1;
    return sprintf("REC-%s-%04d", $year, $maxId);
}

/**
 * Generate unique admission number
 */
function generateAdmissionNumber() {
    global $pdo;
    $year = date('Y');
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM students");
    $maxId = (int)($stmt->fetch()['max_id'] ?? 0) + 1;
    return sprintf("ADM-%s-%03d", $year, $maxId);
}

/**
 * Calculate grade based on percentage from grades table
 */
function calculateGrade($percentage) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT grade_name, remarks 
        FROM grades 
        WHERE ? >= min_percentage AND ? <= max_percentage 
        ORDER BY min_percentage DESC 
        LIMIT 1
    ");
    $stmt->execute([$percentage, $percentage]);
    $grade = $stmt->fetch();
    return $grade ? $grade['grade_name'] : ($percentage >= 50 ? 'D' : 'F');
}

/**
 * Flash message helper functions
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}

/**
 * Get count of unread notifications for a user
 */
function getUnreadNotificationsCount($userId) {
    global $pdo;
    if (!$userId) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Get recent notices for current audience
 */
function getRecentNotices($audience = 'Everyone', $limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM notices 
        WHERE status = 'Active' AND (audience = 'Everyone' OR audience = ?)
        ORDER BY date DESC, id DESC 
        LIMIT ?
    ");
    $stmt->bindValue(1, $audience);
    $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Safe File Upload Handler
 */
function handleFileUpload($file, $targetSubDir = 'students', $allowedExts = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error code: ' . ($file['error'] ?? 'unknown')];
    }
    
    $maxSize = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed limit of 5MB.'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        return ['success' => false, 'error' => 'Invalid file format. Allowed formats: ' . implode(', ', $allowedExts)];
    }
    
    // Disallow dangerous files
    if (in_array($ext, ['php', 'phtml', 'php3', 'php4', 'php5', 'sh', 'exe', 'bat', 'cmd'])) {
        return ['success' => false, 'error' => 'Security check failed. Invalid file extension.'];
    }
    
    $uploadDir = __DIR__ . '/../uploads/' . trim($targetSubDir, '/') . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = uniqid('file_', true) . '.' . $ext;
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $fileName, 'filepath' => 'uploads/' . trim($targetSubDir, '/') . '/' . $fileName];
    }
    
    return ['success' => false, 'error' => 'Failed to save uploaded file. Check folder permissions.'];
}
