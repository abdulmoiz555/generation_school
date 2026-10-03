<?php
/**
 * Library Circulation & Overdue Reports (Section 27)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('library_manage');

$overdueBooks = $pdo->query("
    SELECT bi.*, b.title, b.isbn, s.first_name, s.last_name, s.admission_no, c.class_name, p.phone as parent_phone
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.id
    JOIN students s ON bi.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN parents p ON s.parent_id = p.id
    WHERE bi.status = 'Issued' AND bi.due_date < CURDATE()
    ORDER BY bi.due_date ASC
")->fetchAll();

$pageTitle = "Library Overdue Reports";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/library/books.php">Library</a></li>
                <li class="breadcrumb-item active">Overdue Report</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Library Overdue Loans & Fine Log</h3>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-print me-1"></i> Print Report
        </button>
        <a href="<?= BASE_PATH ?>/library/books.php" class="btn btn-primary btn-sm rounded-pill px-3">
            Books Catalog
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Overdue Library Books: <span class="badge bg-danger rounded-pill"><?= count($overdueBooks) ?> Unreturned</span></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Book Title</th>
                        <th>Student Borrower</th>
                        <th>Class</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($overdueBooks)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No overdue books at this time.</td></tr>
                    <?php else: ?>
                        <?php foreach ($overdueBooks as $ob): 
                            $daysLate = max(1, floor((time() - strtotime($ob['due_date'])) / (60 * 60 * 24)));
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($ob['title']) ?></div>
                                    <small class="text-muted">ISBN: <?= e($ob['isbn']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($ob['first_name'] . ' ' . $ob['last_name']) ?></div>
                                    <small class="text-muted">Adm: <?= e($ob['admission_no']) ?> &bull; Phone: <?= e($ob['parent_phone'] ?: '-') ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($ob['class_name']) ?></span></td>
                                <td><?= formatDate($ob['issue_date']) ?></td>
                                <td class="text-danger fw-bold"><?= formatDate($ob['due_date']) ?></td>
                                <td><span class="badge bg-danger"><?= $daysLate ?> Days Late</span></td>
                                <td class="text-end">
                                    <a href="<?= BASE_PATH ?>/library/books.php?action=return_book&issue_id=<?= $ob['id'] ?>" class="btn btn-sm btn-success rounded-pill px-3">
                                        Check In
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
