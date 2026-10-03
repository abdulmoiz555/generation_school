<?php
/**
 * Book Issue Redirect
 */
require_once __DIR__ . '/../config/config.php';
header("Location: " . BASE_PATH . "/library/books.php?tab=issues");
exit;
