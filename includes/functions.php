<?php
// -------------------------------------------------------------
// DoneDay - small shared helper functions
// -------------------------------------------------------------

/** Escape output to prevent XSS. Always echo user data through this. */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/** Send a consistent JSON response and stop. */
function json_ok($data = []) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true] + $data);
    exit;
}

function json_fail($message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message] + $extra);
    exit;
}

/** Create or return the CSRF token for this session. */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Check the CSRF token sent with a form / fetch request. */
function csrf_verify() {
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && hash_equals($_SESSION['csrf'] ?? '', $sent);
}

/** Fetch a user's settings row, creating defaults on first use. */
function get_user_settings(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('SELECT * FROM settings WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        $pdo->prepare('INSERT INTO settings (user_id) VALUES (?)')->execute([$userId]);
        $row = ['user_id' => $userId, 'daily_task_limit' => 5, 'motivation_enabled' => 1, 'theme' => 'system'];
    }
    return $row;
}


/**
 * Self-healing migration: databases created from the original
 * doneday.sql predate the theme setting. Add the column if missing.
 * Safe to call on every save; it checks information_schema first.
 */
function ensure_theme_column(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'theme'"
    );
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $pdo->exec(
            "ALTER TABLE settings
             ADD COLUMN theme VARCHAR(10) NOT NULL DEFAULT 'system'
             AFTER motivation_enabled"
        );
    }
    $done = true;
}

/** Valid theme values; anything else falls back to 'system'. */
function valid_theme($theme) {
    return in_array($theme, ['system', 'light', 'dark'], true) ? $theme : 'system';
}

/** How many tasks are currently active in "Today"? */
function today_task_count(PDO $pdo, $userId) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tasks
         WHERE user_id = ? AND status = 'today' AND task_date = CURDATE()"
    );
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Daily reset behaviour:
 * Any task still sitting in "Today" from a previous calendar day is
 * gently moved to "Later". Nothing is deleted, nothing is shamed -
 * the user simply starts each day with a fresh, honest Today list.
 * Idempotent: running it twice changes nothing the second time.
 */
function rollover_yesterday_tasks(PDO $pdo, $userId) {
    $stmt = $pdo->prepare(
        "UPDATE tasks
         SET status = 'later', updated_at = NOW()
         WHERE user_id = ? AND status = 'today' AND task_date < CURDATE()"
    );
    $stmt->execute([$userId]);
}

/**
 * Authoritative "today" statistics for the progress bar.
 * done  = tasks completed today
 * total = done + tasks still active in Today
 * Completed tasks keep counting toward today's total even after they
 * move to the Completed list, so 5 created / 3 done reads 3/5 (60%).
 */
function today_stats(PDO $pdo, $userId) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tasks
         WHERE user_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()"
    );
    $stmt->execute([$userId]);
    $done = (int)$stmt->fetchColumn();
    return ['done' => $done, 'total' => $done + today_task_count($pdo, $userId)];
}
