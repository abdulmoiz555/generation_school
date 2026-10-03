<?php
/**
 * Global Footer Component
 */
?>
<?php if (isLoggedIn()): ?>
        </main>
        <footer class="app-footer text-center text-md-start d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div>
                &copy; <?= date('Y') ?> <strong><?= e(getSetting('school_name', 'EduManage School Management System')) ?></strong>. All rights reserved.
            </div>
            <div class="text-muted small mt-2 mt-md-0">
                <span>Registration No: <?= e(getSetting('registration_no', 'EDU-REG-2024-9843')) ?></span> | 
                <span>Version 3.2 (PHP 8 & MySQL)</span>
            </div>
        </footer>
    </div>
</div>
<?php else: ?>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap 5 Bundle JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Core Vanilla JS App -->
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>

<?php if (isset($extraScripts) && is_array($extraScripts)): ?>
    <?php foreach ($extraScripts as $script): ?>
        <script src="<?= BASE_PATH ?>/assets/js/<?= e($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
