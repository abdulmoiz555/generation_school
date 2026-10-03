<?php
/**
 * Global Sidebar Navigation Component
 */
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';

function isNavActive($pathPart) {
    global $currentUri, $currentScript;
    return (strpos($currentUri, $pathPart) !== false || strpos($currentScript, $pathPart) !== false);
}
?>
<aside class="app-sidebar">
    <a href="<?= BASE_PATH ?>/dashboard.php" class="sidebar-brand d-flex align-items-center gap-2">
        <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" class="rounded-circle shadow-sm" style="width: 32px; height: 32px; object-fit: cover; border: 1.5px solid rgba(255,255,255,0.2);" onerror="this.style.display='none'">
        <span class="text-truncate" style="max-width: 175px;" title="<?= e(getSetting('school_name', 'Generation Model School')) ?>"><?= e(getSetting('school_name', 'Generation Model School')) ?></span>
    </a>

    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/dashboard.php" class="sidebar-link <?= (isNavActive('dashboard.php') || $currentScript == BASE_PATH . '/index.php') ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <?php if (hasRole(['superadmin', 'admin', 'receptionist'])): ?>
        <li class="sidebar-heading">Admissions & Students</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/students/index.php" class="sidebar-link <?= (isNavActive('/students/') && !isNavActive('id-card.php')) ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/students/id-card.php" class="sidebar-link <?= isNavActive('id-card.php') ? 'active' : '' ?>">
                <i class="fas fa-id-card"></i>
                <span>Student ID Cards</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/parents/index.php" class="sidebar-link <?= isNavActive('/parents/') ? 'active' : '' ?>">
                <i class="fas fa-user-friends"></i>
                <span>Parents</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasRole(['superadmin', 'admin', 'teacher'])): ?>
        <li class="sidebar-heading">Academics</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/academics/classes.php" class="sidebar-link <?= isNavActive('classes.php') ? 'active' : '' ?>">
                <i class="fas fa-chalkboard"></i>
                <span>Classes & Sections</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/academics/subjects.php" class="sidebar-link <?= isNavActive('subjects.php') ? 'active' : '' ?>">
                <i class="fas fa-book-open"></i>
                <span>Subjects</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/academics/timetable.php" class="sidebar-link <?= isNavActive('timetable.php') ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Timetable</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/academics/sessions.php" class="sidebar-link <?= isNavActive('sessions.php') ? 'active' : '' ?>">
                <i class="fas fa-history"></i>
                <span>Academic Sessions</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasRole(['superadmin', 'admin', 'teacher', 'receptionist'])): ?>
        <li class="sidebar-heading">Attendance</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/attendance/students.php" class="sidebar-link <?= isNavActive('attendance/students.php') ? 'active' : '' ?>">
                <i class="fas fa-user-check"></i>
                <span>Student Attendance</span>
            </a>
        </li>
        <?php if (hasRole(['superadmin', 'admin'])): ?>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/attendance/teachers.php" class="sidebar-link <?= isNavActive('attendance/teachers.php') ? 'active' : '' ?>">
                <i class="fas fa-clipboard-user"></i>
                <span>Teacher Attendance</span>
            </a>
        </li>
        <?php endif; ?>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/attendance/reports.php" class="sidebar-link <?= isNavActive('attendance/reports.php') ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span>Attendance Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasRole(['superadmin', 'admin', 'accountant', 'receptionist'])): ?>
        <li class="sidebar-heading">Fee & Finance</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/fees/collect.php" class="sidebar-link <?= isNavActive('fees/collect.php') ? 'active' : '' ?>">
                <i class="fas fa-cash-register"></i>
                <span>Collect Fee</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/fees/payments.php" class="sidebar-link <?= isNavActive('fees/payments.php') ? 'active' : '' ?>">
                <i class="fas fa-receipt"></i>
                <span>Fee Receipts & Slips</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/fees/fee-structure.php" class="sidebar-link <?= isNavActive('fees/fee-structure.php') ? 'active' : '' ?>">
                <i class="fas fa-tags"></i>
                <span>Fee Structures</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/fees/arrears.php" class="sidebar-link <?= isNavActive('fees/arrears.php') ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Unpaid & Arrears</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/fees/reports.php" class="sidebar-link <?= isNavActive('fees/reports.php') ? 'active' : '' ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Financial Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasRole(['superadmin', 'admin', 'teacher'])): ?>
        <li class="sidebar-heading">Examinations</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/examinations/exams.php" class="sidebar-link <?= isNavActive('examinations/exams.php') ? 'active' : '' ?>">
                <i class="fas fa-edit"></i>
                <span>Exams & Dates</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/examinations/marks.php" class="sidebar-link <?= isNavActive('examinations/marks.php') ? 'active' : '' ?>">
                <i class="fas fa-pen-nib"></i>
                <span>Marks Entry</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/examinations/results.php" class="sidebar-link <?= isNavActive('examinations/results.php') ? 'active' : '' ?>">
                <i class="fas fa-award"></i>
                <span>Result Cards</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/examinations/tabulation.php" class="sidebar-link <?= isNavActive('examinations/tabulation.php') ? 'active' : '' ?>">
                <i class="fas fa-table"></i>
                <span>Tabulation Sheet</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/examinations/grades.php" class="sidebar-link <?= isNavActive('examinations/grades.php') ? 'active' : '' ?>">
                <i class="fas fa-sliders-h"></i>
                <span>Grading System</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasRole(['superadmin', 'admin'])): ?>
        <li class="sidebar-heading">Staff & HR</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/teachers/index.php" class="sidebar-link <?= isNavActive('/teachers/') ? 'active' : '' ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Teachers</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/staff/index.php" class="sidebar-link <?= isNavActive('/staff/') ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i>
                <span>General Staff</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/hr/payroll.php" class="sidebar-link <?= isNavActive('payroll.php') ? 'active' : '' ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Staff Payroll</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/hr/leaves.php" class="sidebar-link <?= isNavActive('leaves.php') ? 'active' : '' ?>">
                <i class="fas fa-calendar-times"></i>
                <span>Leave Applications</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasRole(['superadmin', 'admin', 'librarian'])): ?>
        <li class="sidebar-heading">Library</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/library/books.php" class="sidebar-link <?= isNavActive('/library/') ? 'active' : '' ?>">
                <i class="fas fa-book"></i>
                <span>Books & Issues</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasRole(['superadmin', 'admin'])): ?>
        <li class="sidebar-heading">Operations</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/transport/routes.php" class="sidebar-link <?= isNavActive('/transport/') ? 'active' : '' ?>">
                <i class="fas fa-bus"></i>
                <span>Transport</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/inventory/items.php" class="sidebar-link <?= isNavActive('/inventory/') ? 'active' : '' ?>">
                <i class="fas fa-boxes"></i>
                <span>Inventory</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="sidebar-heading">Communication</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/communication/notices.php" class="sidebar-link <?= isNavActive('notices.php') ? 'active' : '' ?>">
                <i class="fas fa-bullhorn"></i>
                <span>Notice Board</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/communication/messages.php" class="sidebar-link <?= isNavActive('messages.php') ? 'active' : '' ?>">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>
        </li>

        <?php if (hasRole(['superadmin', 'admin', 'accountant'])): ?>
        <li class="sidebar-heading">Reports & Analytics</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/reports/index.php" class="sidebar-link <?= isNavActive('/reports/') ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i>
                <span>Reports Center</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasRole(['superadmin', 'admin'])): ?>
        <li class="sidebar-heading">System Settings</li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/admin/settings.php" class="sidebar-link <?= isNavActive('admin/settings.php') ? 'active' : '' ?>">
                <i class="fas fa-cogs"></i>
                <span>School Settings</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/admin/users.php" class="sidebar-link <?= isNavActive('admin/users.php') ? 'active' : '' ?>">
                <i class="fas fa-user-shield"></i>
                <span>Users & Roles</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/admin/audit-logs.php" class="sidebar-link <?= isNavActive('admin/audit-logs.php') ? 'active' : '' ?>">
                <i class="fas fa-history"></i>
                <span>Audit Logs</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= BASE_PATH ?>/admin/backup.php" class="sidebar-link <?= isNavActive('admin/backup.php') ? 'active' : '' ?>">
                <i class="fas fa-database"></i>
                <span>Database Backup</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</aside>
