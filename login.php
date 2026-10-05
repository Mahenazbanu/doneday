<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$email = '';

// ---- Simple brute-force throttle -------------------------------------
// After 5 failed attempts the account-in-session is locked for 15 minutes.
// Session-based throttling is a pragmatic baseline for shared PHP hosting
// (no cron, no Redis). Its limits are documented in SECURITY.md.
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_SECONDS', 900);

$lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (time() < $lockedUntil) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } elseif (!csrf_verify()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([strtolower($email)]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Success: clear the throttle, rotate the session id.
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            session_regenerate_id(true); // prevent session fixation
            $_SESSION['user_id'] = (int)$user['id'];
            rollover_yesterday_tasks($pdo, (int)$user['id']);
            header('Location: dashboard.php');
            exit;
        }

        // Failure: count it, lock after the threshold. Always the same
        // generic message so the endpoint cannot enumerate accounts.
        $attempts = (int)($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts;
        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
            $_SESSION['login_attempts'] = 0;
        }
        $error = 'Incorrect email or password.';
    }
}

$page_title = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card">
    <h1>Welcome back</h1>
    <p class="muted">Good to see you. Let's look at today.</p>

    <?php if (isset($_GET['registered'])): ?>
        <p class="notice success">Account created! You can log in now.</p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="notice error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="login.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <p class="auth-switch">New to DoneDay? <a href="register.php">Create an account</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
