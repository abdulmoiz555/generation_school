<?php
/**
 * Library Catalog & Circulation (Section 27)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('library_manage');

$categories = $pdo->query("SELECT * FROM book_categories ORDER BY name ASC")->fetchAll();
$authors = $pdo->query("SELECT * FROM authors ORDER BY name ASC")->fetchAll();
$allStudents = $pdo->query("SELECT id, first_name, last_name, admission_no FROM students WHERE status = 'active' ORDER BY first_name ASC")->fetchAll();

// Add Book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_book') {
    $isbn = trim($_POST['isbn'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $authorId = (int)$_POST['author_id'];
    $catId = (int)$_POST['category_id'];
    $pub = trim($_POST['publisher'] ?? '');
    $qty = (int)($_POST['quantity'] ?? 1);
    $shelf = trim($_POST['shelf_no'] ?? '');

    $stmt = $pdo->prepare("
        INSERT INTO books (isbn, title, author_id, category_id, publisher, quantity, available_quantity, shelf_no)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$isbn, $title, $authorId, $catId, $pub, $qty, $qty, $shelf]);
    logAudit('ADD_BOOK', 'Library', (int)$pdo->lastInsertId(), "Added book {$title}");
    setFlashMessage('success', "Book '{$title}' added to library catalog!");
    header("Location: " . BASE_PATH . "/library/books.php");
    exit;
}

// Issue Book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_book') {
    $bId = (int)$_POST['book_id'];
    $sId = (int)$_POST['student_id'];
    $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
    $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO book_issues (book_id, student_id, issue_date, due_date, status, issued_by) VALUES (?, ?, ?, ?, 'Issued', ?)");
    $stmt->execute([$bId, $sId, $issueDate, $dueDate, getCurrentUserId()]);
    $pdo->query("UPDATE books SET available_quantity = available_quantity - 1 WHERE id = {$bId}");
    $pdo->commit();

    logAudit('ISSUE_BOOK', 'Library', $bId, "Issued book to student {$sId}");
    setFlashMessage('success', "Book issued successfully! Due on " . formatDate($dueDate));
    header("Location: " . BASE_PATH . "/library/books.php?tab=issues");
    exit;
}

// Return Book
if (isset($_GET['action']) && $_GET['action'] === 'return_book' && isset($_GET['issue_id'])) {
    $issueId = (int)$_GET['issue_id'];
    $issStmt = $pdo->prepare("SELECT * FROM book_issues WHERE id = ?");
    $issStmt->execute([$issueId]);
    $issue = $issStmt->fetch();

    if ($issue && $issue['status'] !== 'Returned') {
        $pdo->beginTransaction();
        $retDate = date('Y-m-d');
        $upd = $pdo->prepare("UPDATE book_issues SET return_date = ?, status = 'Returned', returned_to = ? WHERE id = ?");
        $upd->execute([$retDate, getCurrentUserId(), $issueId]);
        $pdo->query("UPDATE books SET available_quantity = available_quantity + 1 WHERE id = {$issue['book_id']}");
        $pdo->commit();

        logAudit('RETURN_BOOK', 'Library', $issueId, "Returned book {$issue['book_id']}");
        setFlashMessage('success', "Book successfully checked in and returned to shelf!");
    }
    header("Location: " . BASE_PATH . "/library/books.php?tab=issues");
    exit;
}

// Catalog
$books = $pdo->query("
    SELECT b.*, a.name as author_name, c.name as category_name
    FROM books b
    JOIN authors a ON b.author_id = a.id
    JOIN book_categories c ON b.category_id = c.id
    ORDER BY b.id DESC
")->fetchAll();

// Active Issues
$issues = $pdo->query("
    SELECT bi.*, b.title as book_title, s.first_name, s.last_name, s.admission_no, c.class_name
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.id
    JOIN students s ON bi.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY bi.id DESC
")->fetchAll();

$activeTab = $_GET['tab'] ?? 'catalog';
$pageTitle = "Library Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Library</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Library & Circulation (Section 27)</h3>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#issueBookModal">
            <i class="fas fa-hand-holding me-1"></i> Issue Book
        </button>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addBookModal">
            <i class="fas fa-plus me-1"></i> + New Book
        </button>
    </div>
</div>

<ul class="nav nav-pills mb-4" id="libraryNav">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'catalog' ? 'active' : '' ?> rounded-pill" href="<?= BASE_PATH ?>/library/books.php?tab=catalog">
            <i class="fas fa-book me-1"></i> Books Catalog (<?= count($books) ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'issues' ? 'active' : '' ?> rounded-pill" href="<?= BASE_PATH ?>/library/books.php?tab=issues">
            <i class="fas fa-exchange-alt me-1"></i> Circulation & Issues (<?= count($issues) ?>)
        </a>
    </li>
</ul>

<?php if ($activeTab === 'catalog'): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Book Title</th>
                            <th>ISBN</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Available / Total</th>
                            <th>Shelf #</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $b): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($b['title']) ?></div>
                                    <small class="text-muted"><?= e($b['publisher'] ?: 'Publisher') ?></small>
                                </td>
                                <td><code><?= e($b['isbn'] ?: 'N/A') ?></code></td>
                                <td><?= e($b['author_name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($b['category_name']) ?></span></td>
                                <td>
                                    <span class="badge <?= $b['available_quantity'] > 0 ? 'badge-soft-success' : 'badge-soft-danger' ?> fs-6">
                                        <?= $b['available_quantity'] ?> / <?= $b['quantity'] ?>
                                    </span>
                                </td>
                                <td><code><?= e($b['shelf_no'] ?: '-') ?></code></td>
                                <td class="text-end">
                                    <?php if ($b['available_quantity'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#issueBookModal" onclick="document.getElementById('issueBookSelect').value = '<?= $b['id'] ?>'">
                                            Issue
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-white">All Issued</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Book Title</th>
                            <th>Issued To</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($issues as $iss): 
                            $isOverdue = ($iss['status'] === 'Issued' && strtotime($iss['due_date']) < time());
                        ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($iss['book_title']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($iss['first_name'] . ' ' . $iss['last_name']) ?></div>
                                    <small class="text-muted"><?= e($iss['class_name']) ?> &bull; <?= e($iss['admission_no']) ?></small>
                                </td>
                                <td><?= formatDate($iss['issue_date']) ?></td>
                                <td>
                                    <span class="<?= $isOverdue ? 'text-danger fw-bold' : '' ?>">
                                        <?= formatDate($iss['due_date']) ?>
                                    </span>
                                </td>
                                <td><?= $iss['return_date'] ? formatDate($iss['return_date']) : '-' ?></td>
                                <td>
                                    <?php if ($iss['status'] === 'Returned'): ?>
                                        <span class="badge badge-soft-success">Returned</span>
                                    <?php elseif ($isOverdue): ?>
                                        <span class="badge badge-soft-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-info">On Loan</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($iss['status'] !== 'Returned'): ?>
                                        <a href="<?= BASE_PATH ?>/library/books.php?action=return_book&issue_id=<?= $iss['id'] ?>" class="btn btn-sm btn-success rounded-pill px-3" onclick="return confirm('Return this book to library shelf?')">
                                            <i class="fas fa-check me-1"></i> Return
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Checked In</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Book to Library</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/library/books.php" method="POST">
                <input type="hidden" name="action" value="add_book">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Book Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Book Title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ISBN Number</label>
                        <input type="text" name="isbn" class="form-control" placeholder="978-...">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Author <span class="text-danger">*</span></label>
                            <select name="author_id" class="form-select" required>
                                <?php foreach ($authors as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= e($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Total Quantity</label>
                            <input type="number" name="quantity" class="form-control" value="5" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Shelf Number</label>
                            <input type="text" name="shelf_no" class="form-control" placeholder="Shelf-A1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Publisher</label>
                        <input type="text" name="publisher" class="form-control" placeholder="Publisher Name">
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Issue Book Modal -->
<div class="modal fade" id="issueBookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Issue Book to Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/library/books.php" method="POST">
                <input type="hidden" name="action" value="issue_book">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Book <span class="text-danger">*</span></label>
                        <select name="book_id" id="issueBookSelect" class="form-select" required>
                            <option value="">-- Choose Book --</option>
                            <?php foreach ($books as $b): ?>
                                <?php if ($b['available_quantity'] > 0): ?>
                                    <option value="<?= $b['id'] ?>"><?= e($b['title']) ?> (<?= $b['available_quantity'] ?> in stock)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($allStudents as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= e($st['first_name'] . ' ' . $st['last_name']) ?> (<?= e($st['admission_no']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Due Date (14 Days)</label>
                            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
