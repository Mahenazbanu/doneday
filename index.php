<?php
// Entry point: send people to the dashboard, or to login.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
if (current_user($pdo)) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
