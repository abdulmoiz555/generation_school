<?php
/**
 * System Database Backup & Export Utility
 * Allows downloading MySQL dump and reviewing table sizes and health
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('admin_settings');

// Handle Export Download Action
if (isset($_GET['action']) && $_GET['action'] === 'download_sql') {
    $filename = "school_management_backup_" . date('Y-m-d_H-i-s') . ".sql";
    
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "-- ========================================================\n";
    echo "-- EduManage School Management System Database Dump\n";
    echo "-- Exported on: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: school_management\n";
    echo "-- ========================================================\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $createStmt['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            echo "INSERT INTO `$table` VALUES \n";
            $rowInserts = [];
            foreach ($rows as $row) {
                $values = array_map(function($v) use ($pdo) {
                    if ($v === null) return "NULL";
                    return $pdo->quote($v);
                }, array_values($row));
                $rowInserts[] = "(" . implode(", ", $values) . ")";
            }
            echo implode(",\n", $rowInserts) . ";\n\n";
        }
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    logAudit('DATABASE_BACKUP', 'System', 0, "Downloaded database backup SQL: {$filename}");
    exit;
}

// Fetch database stats
$tableStats = $pdo->query("
    SELECT 
        table_name AS `Table`, 
        table_rows AS `Rows`,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS `Size_MB`
    FROM information_schema.TABLES 
    WHERE table_schema = DATABASE()
    ORDER BY (data_length + index_length) DESC
")->fetchAll();

$totalRows = 0;
$totalSize = 0;
foreach ($tableStats as $ts) {
    $totalRows += (int)$ts['Rows'];
    $totalSize += (float)$ts['Size_MB'];
}

$pageTitle = "Database Backup & Health";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/admin/settings.php">Admin</a></li>
                <li class="breadcrumb-item active">Database Backup</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-database text-primary me-2"></i> Database Backup & Export</h3>
        <p class="text-muted small mb-0">Generate full SQL backups with table schemas and seed datasets</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/admin/backup.php?action=download_sql" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-download me-2"></i> Download Full SQL Backup
        </a>
    </div>
</div>

<!-- Overview KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary-subtle text-primary h-100">
            <div class="small fw-semibold text-uppercase">Total Database Tables</div>
            <div class="fs-2 fw-bold mt-1"><?= count($tableStats) ?> Tables</div>
            <div class="small opacity-75 mt-1">InnoDB Engine &bull; UTF8mb4</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-success-subtle text-success h-100">
            <div class="small fw-semibold text-uppercase">Total Estimated Records</div>
            <div class="fs-2 fw-bold mt-1"><?= number_format($totalRows) ?> Rows</div>
            <div class="small opacity-75 mt-1">Across all relational tables</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-info-subtle text-info h-100">
            <div class="small fw-semibold text-uppercase">Database Size on Disk</div>
            <div class="fs-2 fw-bold mt-1"><?= $totalSize ?> MB</div>
            <div class="small opacity-75 mt-1">Data + Index Length</div>
        </div>
    </div>
</div>

<!-- Database Tables Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">Database Structure Details</h6>
        <span class="badge bg-light text-secondary border">Database: school_management</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Table Name</th>
                    <th>Estimated Rows</th>
                    <th>Storage Size (MB)</th>
                    <th class="text-end">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($tableStats as $tbl): ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td class="fw-bold font-monospace text-primary"><?= e($tbl['Table']) ?></td>
                    <td><?= number_format($tbl['Rows']) ?></td>
                    <td><?= $tbl['Size_MB'] ?> MB</td>
                    <td class="text-end">
                        <span class="badge bg-success-subtle text-success px-2 py-1"><i class="fas fa-check-circle me-1"></i> Healthy</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
