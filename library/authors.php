<?php
/**
 * Library Authors
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('library_manage');

// Add Author
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_author') {
    $name = trim($_POST['name'] ?? '');
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO authors (name) VALUES (?)");
        $stmt->execute([$name]);
        setFlashMessage('success', "Author created!");
    }
    header("Location: " . BASE_PATH . "/library/authors.php");
    exit;
}

$authors = $pdo->query("SELECT * FROM authors ORDER BY name ASC")->fetchAll();

$pageTitle = "Book Authors";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/library/books.php">Library</a></li>
                <li class="breadcrumb-item active">Authors</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Book Authors</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/library/books.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Books Catalog</a>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addAuthorModal">
            + New Author
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Author Name</th>
                        <th>Books in Collection</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($authors as $a): 
                        $bCount = (int)$pdo->query("SELECT COUNT(*) FROM books WHERE author_id = {$a['id']}")->fetchColumn();
                    ?>
                        <tr>
                            <td><strong class="text-dark"><?= e($a['name']) ?></strong></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $bCount ?> Titles</span></td>
                            <td><?= formatDate($a['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addAuthorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Book Author</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/library/authors.php" method="POST">
                <input type="hidden" name="action" value="add_author">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Author Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Isaac Asimov" required>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Author</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
