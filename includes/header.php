<?php
// Expects: $page_title (string). Needs $pdo (included below) for theme lookup.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
if (empty($page_title)) { $page_title = 'DoneDay'; }

// Resolve the user's theme: system (default), light, or dark.
$theme = 'system';
if (!empty($_SESSION['user_id'])) {
    $s = get_user_settings($pdo, (int)$_SESSION['user_id']);
    $theme = valid_theme($s['theme'] ?? 'system');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> · DoneDay</title>
    <!-- Theme bootstrap: resolves "system" to light/dark BEFORE first paint,
         then follows OS changes live. No reload required. -->
    <script>
        (function () {
            var root = document.documentElement;
            function apply() {
                if (root.dataset.theme === 'system') {
                    root.dataset.theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
            }
            apply();
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', apply);
        })();
    </script>
    <link rel="stylesheet" href="assets/css/style.css?v=20261003c">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="logo" href="dashboard.php">Done<span>Day</span></a>
        <p class="header-date"><?= e(date('l, j F Y')) ?></p>
        <nav class="site-nav" aria-label="Main">
            <a href="dashboard.php"<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' aria-current="page"' : '' ?>>Today</a>
            <a href="history.php"<?= basename($_SERVER['PHP_SELF']) === 'history.php' ? ' aria-current="page"' : '' ?>>History</a>
            <a href="settings.php" aria-label="Settings">⚙</a>
            <a href="logout.php" class="nav-logout">Log out</a>
        </nav>
    </div>
</header>
<main class="wrap" id="main">
