<?php
/**
 * Application Entry Point
 * Redirects to dashboard if logged in, or login screen if unauthenticated
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . BASE_PATH . "/dashboard.php");
} else {
    header("Location: " . BASE_PATH . "/login.php");
}
exit;
