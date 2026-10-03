<?php
/**
 * Student Demographics & Statistical Reports (Section 37)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('reports_view');

// Stats
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status != 'inactive'")->fetchColumn();
$maleCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE gender = 'Male' AND status != 'inactive'")->fetchColumn();
$femaleCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE gender = 'Female' AND status != 'inactive'")->fetchColumn();

// Class Breakdown
$classStats = $pdo->query("
    SELECT c.class_name, 
           COUNT(s.id) as total_students,
           SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
           SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as female_count
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id AND s.status != 'inactive'
    GROUP BY c.id, c.class_name, c.numeric_level
    ORDER BY c.numeric_level ASC
")->fetchAll();

$pageTitle = "Student Reports";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Reports</li>
                <li class="breadcrumb-item active">Students</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Student Enrollment Analytics & Demographics</h3>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
        <i class="fas fa-print me-1"></i> Print Report
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Total Active Enrollment</div>
                <div class="stat-number text-primary"><?= number_format($totalStudents) ?></div>
            </div>
            <div class="stat-icon primary"><i class="fas fa-user-graduate"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Male Students</div>
                <div class="stat-number text-info"><?= $maleCount ?></div>
                <div class="text-muted small mt-1"><?= $totalStudents > 0 ? round(($maleCount / $totalStudents) * 100) : 0 ?>% of total</div>
            </div>
            <div class="stat-icon info"><i class="fas fa-male"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Female Students</div>
                <div class="stat-number text-danger"><?= $femaleCount ?></div>
                <div class="text-muted small mt-1"><?= $totalStudents > 0 ? round(($femaleCount / $totalStudents) * 100) : 0 ?>% of total</div>
            </div>
            <div class="stat-icon danger"><i class="fas fa-female"></i></div>
        </div>
    </div>
</div>

<div class="printable-area">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent py-3">
            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-table me-2"></i> Class-wise Enrollment Breakdown</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">Class Grade</th>
                            <th>Total Students</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th>Percentage of School</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classStats as $cs): 
                            $share = ($totalStudents > 0) ? round(($cs['total_students'] / $totalStudents) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td class="text-start fw-bold text-dark"><?= e($cs['class_name']) ?></td>
                                <td class="fw-bold text-primary fs-6"><?= $cs['total_students'] ?></td>
                                <td><?= $cs['male_count'] ?></td>
                                <td><?= $cs['female_count'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 100px;">
                                            <div class="progress-bar bg-primary" style="width: <?= $share ?>%;"></div>
                                        </div>
                                        <span class="small fw-semibold"><?= $share ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
