<?php
/**
 * Global Navbar Component
 */
?>
<header class="app-navbar">
    <div class="d-flex align-items-center">
        <button type="button" class="btn btn-light border btn-sm me-3" id="sidebarToggle" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="d-none d-md-block">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                <i class="fas fa-calendar-alt me-1"></i> Session: <strong><?= e($activeSession['session_name'] ?? '2025-2026') ?></strong>
            </span>
        </div>
    </div>

    <div class="nav-actions">
        <!-- Dark/Light Mode Switcher -->
        <button class="btn btn-light border btn-sm rounded-circle d-flex align-items-center justify-content-center" id="themeToggleBtn" style="width: 38px; height: 38px;" title="Toggle Dark/Light Mode">
            <i class="fas fa-moon text-secondary"></i>
        </button>

        <!-- Notifications Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light border btn-sm rounded-circle position-relative d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell text-secondary"></i>
                <?php if ($unreadNotifications > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                        <?= $unreadNotifications ?>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 p-2" style="width: 300px;" aria-labelledby="notificationsDropdown">
                <li class="dropdown-header fw-bold text-dark d-flex justify-content-between align-items-center pb-2 border-bottom">
                    <span>Notifications</span>
                    <span class="badge bg-primary rounded-pill"><?= $unreadNotifications ?> New</span>
                </li>
                <li>
                    <a class="dropdown-item py-2 px-2 rounded-2 mt-1" href="<?= BASE_PATH ?>/communication/notifications.php">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success text-white p-2 me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                <i class="fas fa-money-bill-wave fa-xs"></i>
                            </div>
                            <div class="overflow-hidden">
                                <div class="small fw-semibold text-truncate">Fee Payment Received</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Receipt #REC-2026-0001 created</div>
                            </div>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2 px-2 rounded-2" href="<?= BASE_PATH ?>/communication/notices.php">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-info text-white p-2 me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                <i class="fas fa-bullhorn fa-xs"></i>
                            </div>
                            <div class="overflow-hidden">
                                <div class="small fw-semibold text-truncate">Annual Sports Day 2026</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Registrations are now open</div>
                            </div>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item text-center small text-primary fw-medium" href="<?= BASE_PATH ?>/communication/notifications.php">View All Notifications</a></li>
            </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light border-0 p-1 d-flex align-items-center rounded-pill" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="avatar-initials me-2">
                    <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="text-start d-none d-lg-block me-2">
                    <div class="fw-semibold small text-truncate" style="max-width: 140px;"><?= e($currentUser['full_name'] ?? 'User') ?></div>
                    <div class="text-muted" style="font-size: 0.72rem;"><?= e($currentUser['role_display'] ?? 'Staff') ?></div>
                </div>
                <i class="fas fa-chevron-down text-muted small me-1"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 p-2" aria-labelledby="userMenuDropdown">
                <li class="px-3 py-2 border-bottom mb-2">
                    <div class="fw-bold"><?= e($currentUser['full_name'] ?? 'User') ?></div>
                    <div class="small text-muted"><?= e($currentUser['email'] ?? '') ?></div>
                </li>
                <li><a class="dropdown-item rounded-2 py-2" href="<?= BASE_PATH ?>/admin/settings.php"><i class="fas fa-cog text-muted me-2"></i> Settings</a></li>
                <li><a class="dropdown-item rounded-2 py-2" href="<?= BASE_PATH ?>/communication/messages.php"><i class="fas fa-envelope text-muted me-2"></i> Messages</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item rounded-2 py-2 text-danger" href="<?= BASE_PATH ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</header>
