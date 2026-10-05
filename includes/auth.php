<?php
// -------------------------------------------------------------
// DoneDay - session + authentication guards (hardened)
// -------------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    // Secure flag only when HTTPS is actually in use, so plain-HTTP
    // XAMPP development keeps working unchanged.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'httponly' => true,   // JavaScript cannot read the session cookie
        'samesite' => 'Lax',  // CSRF mitigation at the cookie level
        'secure'   => $https, // Secure on production HTTPS, off on local XAMPP
    ]);
    session_start();
}

/**
 * Baseline HTTP security headers for every page and API response.
 * The CSP is deliberately simple: 'unsafe-inline' is required because
 * DoneDay ships small inline scripts (theme bootstrap, DONEDAY config).
 * There are no external sources at all - the app is fully self-hosted.
 */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; "
     . "script-src 'self' 'unsafe-inline'; "
     . "style-src 'self' 'unsafe-inline'; "
     . "img-src 'self' data:; connect-src 'self'; "
     . "base-uri 'self'; form-action 'self'");

/** Return the logged-in user row, or null. */
function current_user(PDO $pdo) {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/** Stop the request if the visitor is not logged in. */
function require_login(PDO $pdo) {
    if (!current_user($pdo)) {
        header('Location: login.php');
        exit;
    }
}
