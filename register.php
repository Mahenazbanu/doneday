<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';

    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors[] = 'Please enter your name (max 100 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        // Duplicate email?
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([strtolower($old['email'])]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$old['name'], strtolower($old['email']), $hash]);
            $newId = (int)$pdo->lastInsertId();
            // Default settings row (daily limit 5, motivation on, system theme).
            $pdo->prepare('INSERT INTO settings (user_id) VALUES (?)')->execute([$newId]);
            $pdo->commit();
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Registration failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong while creating your account. Please try again.';
        }
    }
}

$page_title = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card">
    <h1>Create your account</h1>
    <p class="muted">Start focusing on what matters today.</p>

    <?php foreach ($errors as $err): ?>
        <p class="notice error"><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post" action="register.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" required maxlength="100" autocomplete="name">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email">

        <label for="password">Password <span class="muted">(min. 8 characters)</span></label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">

        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">

        <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>
    <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
