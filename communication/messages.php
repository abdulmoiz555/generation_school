<?php
/**
 * Internal Messaging System (Section 35)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = getCurrentUserId();
$allUsers = $pdo->query("SELECT id, full_name, username, role_id FROM users WHERE id != {$userId} AND status = 'active' ORDER BY full_name ASC")->fetchAll();

// Send Message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $receiverId = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($receiverId && !empty($subject) && !empty($body)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body, is_read) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$userId, $receiverId, $subject, $body]);
        setFlashMessage('success', "Message sent successfully!");
    }
    header("Location: " . BASE_PATH . "/communication/messages.php");
    exit;
}

// Fetch Received and Sent Messages
$inboxStmt = $pdo->prepare("
    SELECT m.*, u.full_name as sender_name, u.avatar 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.receiver_id = ? 
    ORDER BY m.created_at DESC
");
$inboxStmt->execute([$userId]);
$inbox = $inboxStmt->fetchAll();

$sentStmt = $pdo->prepare("
    SELECT m.*, u.full_name as receiver_name 
    FROM messages m 
    JOIN users u ON m.receiver_id = u.id 
    WHERE m.sender_id = ? 
    ORDER BY m.created_at DESC
");
$sentStmt->execute([$userId]);
$sent = $sentStmt->fetchAll();

$pageTitle = "Internal Messaging";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Communication</li>
                <li class="breadcrumb-item active">Messages</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Internal Messaging System</h3>
    </div>
    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#composeModal">
        <i class="fas fa-paper-plane me-1"></i> Compose Message
    </button>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold text-primary mb-0"><i class="fas fa-inbox me-2"></i> Inbox (<?= count($inbox) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($inbox)): ?>
                        <div class="text-center py-4 text-muted">Your inbox is empty.</div>
                    <?php else: ?>
                        <?php foreach ($inbox as $m): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark"><?= e($m['sender_name']) ?></strong>
                                    <small class="text-muted"><?= formatDate($m['created_at'], 'M d, H:i') ?></small>
                                </div>
                                <h6 class="fw-semibold text-primary mb-1"><?= e($m['subject']) ?></h6>
                                <p class="text-muted small mb-0"><?= nl2br(e($m['body'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-paper-plane me-2 text-secondary"></i> Sent Messages (<?= count($sent) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($sent as $s): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted">To: <strong><?= e($s['receiver_name']) ?></strong></span>
                                <small class="text-muted"><?= formatDate($s['created_at'], 'M d') ?></small>
                            </div>
                            <div class="fw-semibold small text-dark"><?= e($s['subject']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">New Direct Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/communication/messages.php" method="POST">
                <input type="hidden" name="action" value="send_message">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recipient <span class="text-danger">*</span></label>
                        <select name="receiver_id" class="form-select" required>
                            <option value="">-- Choose User --</option>
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" placeholder="Message Subject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Message <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control" rows="4" placeholder="Write your message here..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
