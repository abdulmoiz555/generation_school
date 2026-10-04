<?php
/**
 * School Settings & Configuration (Section 49)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('settings_manage');

$allSessions = getAllSessions();

// Handle Add Academic Session from Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_session') {
    $sessName = trim($_POST['session_name'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $makeActive = isset($_POST['make_active']) ? 1 : 0;

    if (!empty($sessName) && !empty($startDate) && !empty($endDate)) {
        if ($makeActive) {
            $pdo->query("UPDATE academic_sessions SET is_current = 0");
        }
        $stmt = $pdo->prepare("INSERT INTO academic_sessions (session_name, start_date, end_date, is_current, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$sessName, $startDate, $endDate, $makeActive]);
        $newSessId = (int)$pdo->lastInsertId();
        if ($makeActive) {
            $pdo->query("UPDATE school_settings SET key_value = '{$newSessId}' WHERE key_name = 'active_session_id'");
        }
        logAudit('ADD_SESSION', 'Settings', $newSessId, "Created academic session {$sessName}");
        setFlashMessage('success', "New Academic Session '{$sessName}' created successfully!");
    } else {
        setFlashMessage('error', "Please fill in all session details.");
    }
    header("Location: " . BASE_PATH . "/admin/settings.php");
    exit;
}

// Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'add_session')) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('error', 'Session validation failed.');
    } else {
        $allowedKeys = [
            'school_name', 'school_motto', 'registration_no', 'principal_name',
            'phone', 'email', 'website', 'address', 'city', 'province', 'country',
            'currency', 'currency_symbol', 'active_session_id', 'receipt_footer_note',
            'default_fee_exam', 'default_fee_computer_lab', 'default_fee_sports_extracurricular',
            'default_fee_tuition', 'default_fee_admission'
        ];

        $stmt = $pdo->prepare("INSERT INTO school_settings (key_name, key_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        foreach ($allowedKeys as $k) {
            if (isset($_POST[$k])) {
                $stmt->execute([$k, trim($_POST[$k])]);
            }
        }

        // If requested, synchronize standard rates with class fee structures
        if (isset($_POST['sync_fee_structures']) && $_POST['sync_fee_structures'] == '1') {
            $curSessId = (int)($_POST['active_session_id'] ?? 1);
            $classes = $pdo->query("SELECT id FROM classes")->fetchAll();
            $rates = [
                3 => floatval($_POST['default_fee_exam'] ?? 1500),                  // Examination Fee
                4 => floatval($_POST['default_fee_computer_lab'] ?? 1000),          // Computer & Lab Fee
                7 => floatval($_POST['default_fee_sports_extracurricular'] ?? 800), // Sports & Extracurricular Fee
                1 => floatval($_POST['default_fee_tuition'] ?? 3500)                // Tuition Fee
            ];

            foreach ($classes as $c) {
                foreach ($rates as $typeId => $amt) {
                    $chk = $pdo->prepare("SELECT id FROM fee_structures WHERE session_id = ? AND class_id = ? AND fee_type_id = ?");
                    $chk->execute([$curSessId, $c['id'], $typeId]);
                    if ($chk->fetch()) {
                        $upd = $pdo->prepare("UPDATE fee_structures SET amount = ? WHERE session_id = ? AND class_id = ? AND fee_type_id = ?");
                        $upd->execute([$amt, $curSessId, $c['id'], $typeId]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO fee_structures (session_id, class_id, fee_type_id, amount) VALUES (?, ?, ?, ?)");
                        $ins->execute([$curSessId, $c['id'], $typeId, $amt]);
                    }
                }
            }
        }

        logAudit('UPDATE_SETTINGS', 'Settings', null, 'Updated school master configuration & fee structure');
        setFlashMessage('success', 'School settings & institutional fee structure saved and applied system-wide!');
        header("Location: " . BASE_PATH . "/admin/settings.php");
        exit;
    }
}

// Reload fresh settings
$settings = [];
$stmt = $pdo->query("SELECT key_name, key_value FROM school_settings");
while ($r = $stmt->fetch()) {
    $settings[$r['key_name']] = $r['key_value'];
}

$pageTitle = "School Global Settings";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Settings</li>
                <li class="breadcrumb-item active">School Profile</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Institution Profile & Global Settings (Section 49)</h3>
    </div>
</div>

<form action="<?= BASE_PATH ?>/admin/settings.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <!-- 1. Basic Institution Identity -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3">
            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-university me-2"></i> 1. Institutional Identity</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-12 mb-2">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 border">
                        <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" class="rounded-circle shadow-sm" style="width: 64px; height: 64px; object-fit: cover; border: 2px solid #3b82f6;">
                        <div>
                            <div class="fw-bold text-dark fs-6"><?= e($settings['school_name'] ?? 'Generation Model School') ?></div>
                            <div class="small text-muted">Official Institutional Crest & Logo Active</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">School / Institute Name <span class="text-danger">*</span></label>
                    <input type="text" name="school_name" class="form-control" value="<?= e($settings['school_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">School Motto / Tagline</label>
                    <input type="text" name="school_motto" class="form-control" value="<?= e($settings['school_motto'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Registration / Affiliation Number</label>
                    <input type="text" name="registration_no" class="form-control" value="<?= e($settings['registration_no'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Principal / Head of School</label>
                    <input type="text" name="principal_name" class="form-control" value="<?= e($settings['principal_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">Active Academic Session</label>
                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill" data-bs-toggle="modal" data-bs-target="#newSessionModal">
                            <i class="fas fa-plus me-1"></i> + New Session
                        </button>
                    </div>
                    <select name="active_session_id" class="form-select">
                        <?php foreach ($allSessions as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($settings['active_session_id'] ?? 1) == $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['session_name']) ?> <?= ($s['is_current'] == 1) ? '(Current)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Contact & Geographic Location -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3">
            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-map-marker-alt me-2"></i> 2. Contact & Location</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Official Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" value="<?= e($settings['phone'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Official Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= e($settings['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Website Portal URL</label>
                    <input type="text" name="website" class="form-control" value="<?= e($settings['website'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Campus Address</label>
                    <input type="text" name="address" class="form-control" value="<?= e($settings['address'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">City</label>
                    <input type="text" name="city" class="form-control" value="<?= e($settings['city'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Province / State</label>
                    <input type="text" name="province" class="form-control" value="<?= e($settings['province'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Country</label>
                    <input type="text" name="country" class="form-control" value="<?= e($settings['country'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Financial & Receipts Customization -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3">
            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-receipt me-2"></i> 3. Currency & Fee Receipts</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Currency Code</label>
                    <input type="text" name="currency" class="form-control" value="<?= e($settings['currency'] ?? 'USD') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Currency Symbol</label>
                    <input type="text" name="currency_symbol" class="form-control" value="<?= e($settings['currency_symbol'] ?? '$') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Receipt Printed Footer Note</label>
                    <input type="text" name="receipt_footer_note" class="form-control" value="<?= e($settings['receipt_footer_note'] ?? 'This is a computer generated official fee receipt.') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="text-end mb-5">
        <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill fw-semibold shadow-sm">
            <i class="fas fa-save me-2"></i> Save School Settings
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
