<?php
/**
 * DoneDay - single, beginner-friendly task API.
 * One file handles every task action via $_POST['action'],
 * so there is no duplicated bootstrap/CSRF/ownership code.
 * Always answers with JSON: {"success": bool, "message": string, ...}
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$user = current_user($pdo);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expired. Refresh and try again.']);
    exit;
}

$userId = (int)$user['id'];
$action = $_POST['action'] ?? '';

/** Load one task, but ONLY if it belongs to this user. */
function owned_task(PDO $pdo, $userId, $taskId) {
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$taskId, $userId]);
    $task = $stmt->fetch();
    if (!$task) {
        json_fail('That task could not be found.');
    }
    return $task;
}

/** Today's persistent stats, merged into every mutation response. */
function today_stats_payload(PDO $pdo, $userId) {
    $s = today_stats($pdo, $userId);
    return ['today_total' => $s['total'], 'today_done' => $s['done']];
}

/** Format a task row for JSON output. */
function task_json(array $t) {
    return [
        'id'          => (int)$t['id'],
        'title'       => $t['title'],
        'description' => $t['description'],
        'priority'    => $t['priority'],
        'status'      => $t['status'],
        'category'    => $t['category'],
        'due_date'    => $t['due_date'],
    ];
}

/** List of today's active tasks (used by the "full plate" dialog). */
function today_options(PDO $pdo, $userId) {
    $stmt = $pdo->prepare(
        "SELECT id, title FROM tasks
         WHERE user_id = ? AND status = 'today' AND task_date = CURDATE()
         ORDER BY created_at ASC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

switch ($action) {

    // -------------------------------------------------- CREATE
    case 'create': {
        $title = trim($_POST['title'] ?? '');
        $force = !empty($_POST['force']);

        if ($title === '' || mb_strlen($title) > 200) {
            json_fail('Please enter a task title (max 200 characters).');
        }

        $settings = get_user_settings($pdo, $userId);
        $limit    = (int)$settings['daily_task_limit'];

        if (!$force && today_task_count($pdo, $userId) >= $limit) {
            echo json_encode([
                'success' => false,
                'code'    => 'limit_reached',
                'message' => 'Daily limit reached.',
                'today'   => today_options($pdo, $userId),
            ]);
            exit;
        }

        $desc     = trim($_POST['description'] ?? '') ?: null;
        $priority = in_array($_POST['priority'] ?? '', ['low', 'normal', 'high'], true)
                    ? $_POST['priority'] : 'normal';
        $category = trim($_POST['category'] ?? '') ?: null;
        $due      = $_POST['due_date'] ?? null;
        $due      = ($due && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) ? $due : null;
        $status   = $_POST['status'] ?? 'today';
        $status   = ($status === 'later') ? 'later' : 'today';
        $taskDate = $status === 'today' ? date('Y-m-d') : null;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO tasks (user_id, title, description, priority, category, due_date, status, task_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $title, $desc, $priority, $category, $due, $status, $taskDate]);
            $task = owned_task($pdo, $userId, (int)$pdo->lastInsertId());
            json_ok([
                'message' => 'Task created.',
                'task'    => task_json($task),
                'today_count' => today_task_count($pdo, $userId),
            ] + today_stats_payload($pdo, $userId));
        } catch (PDOException $e) {
            error_log('Task create failed: ' . $e->getMessage());
            json_fail('Something went wrong while saving your task. Please try again.');
        }
    }

    // -------------------------------------------------- UPDATE
    case 'update': {
        $task = owned_task($pdo, $userId, (int)($_POST['id'] ?? 0));
        $title = trim($_POST['title'] ?? '');
        if ($title === '' || mb_strlen($title) > 200) {
            json_fail('Please enter a task title (max 200 characters).');
        }
        $desc     = trim($_POST['description'] ?? '') ?: null;
        $priority = in_array($_POST['priority'] ?? '', ['low', 'normal', 'high'], true)
                    ? $_POST['priority'] : $task['priority'];
        $category = trim($_POST['category'] ?? '') ?: null;
        $due      = $_POST['due_date'] ?? null;
        $due      = ($due && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) ? $due : null;

        try {
            $stmt = $pdo->prepare(
                'UPDATE tasks SET title = ?, description = ?, priority = ?, category = ?, due_date = ?, updated_at = NOW()
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$title, $desc, $priority, $category, $due, $task['id'], $userId]);
            json_ok(['message' => 'Task updated.', 'task' => task_json(owned_task($pdo, $userId, (int)$task['id']))]);
        } catch (PDOException $e) {
            error_log('Task update failed: ' . $e->getMessage());
            json_fail('Something went wrong while updating your task. Please try again.');
        }
    }

    // -------------------------------------------------- DELETE
    case 'delete': {
        $task = owned_task($pdo, $userId, (int)($_POST['id'] ?? 0));
        try {
            $pdo->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?')->execute([$task['id'], $userId]);
            json_ok([
                'message' => 'Task deleted.',
                'today_count' => today_task_count($pdo, $userId),
            ] + today_stats_payload($pdo, $userId));
        } catch (PDOException $e) {
            error_log('Task delete failed: ' . $e->getMessage());
            json_fail('Could not delete that task. Please try again.');
        }
    }

    // -------------------------------------------------- COMPLETE
    case 'complete': {
        $task = owned_task($pdo, $userId, (int)($_POST['id'] ?? 0));
        try {
            $pdo->prepare(
                "UPDATE tasks SET status = 'completed', completed_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND user_id = ?"
            )->execute([$task['id'], $userId]);

            $remaining = today_task_count($pdo, $userId);
            json_ok([
                'message'     => 'Task completed.',
                'today_count' => $remaining,
                'all_done'    => ($remaining === 0 && $task['status'] === 'today'),
            ] + today_stats_payload($pdo, $userId));
        } catch (PDOException $e) {
            error_log('Task complete failed: ' . $e->getMessage());
            json_fail('Could not complete that task. Please try again.');
        }
    }

    // -------------------------------------------------- RESTORE
    case 'restore': {
        $task = owned_task($pdo, $userId, (int)($_POST['id'] ?? 0));
        try {
            // Restored tasks land in Later, never straight into Today.
            $pdo->prepare(
                "UPDATE tasks SET status = 'later', task_date = NULL, completed_at = NULL, updated_at = NOW()
                 WHERE id = ? AND user_id = ?"
            )->execute([$task['id'], $userId]);
            json_ok(['message' => 'Task restored to Later.'] + today_stats_payload($pdo, $userId));
        } catch (PDOException $e) {
            error_log('Task restore failed: ' . $e->getMessage());
            json_fail('Could not restore that task. Please try again.');
        }
    }

    // -------------------------------------------------- MOVE (today <-> later)
    case 'move': {
        $task   = owned_task($pdo, $userId, (int)($_POST['id'] ?? 0));
        $to     = $_POST['to'] ?? '';
        $force  = !empty($_POST['force']);

        if (!in_array($to, ['today', 'later'], true)) {
            json_fail('Invalid destination.');
        }

        // Enforce the daily limit when moving INTO Today.
        if ($to === 'today' && $task['status'] !== 'today' && !$force) {
            $settings = get_user_settings($pdo, $userId);
            if (today_task_count($pdo, $userId) >= (int)$settings['daily_task_limit']) {
                echo json_encode([
                    'success' => false,
                    'code'    => 'limit_reached',
                    'message' => 'Daily limit reached.',
                    'today'   => today_options($pdo, $userId),
                    'pending_task_id' => (int)$task['id'],
                ]);
                exit;
            }
        }

        try {
            $taskDate = $to === 'today' ? date('Y-m-d') : null;
            $pdo->prepare(
                'UPDATE tasks SET status = ?, task_date = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
            )->execute([$to, $taskDate, $task['id'], $userId]);
            $t = owned_task($pdo, $userId, (int)$task['id']);
            json_ok([
                'message'     => $to === 'today' ? 'Moved to Today.' : 'Moved to Later. It\'ll be there when you need it.',
                'task'        => task_json($t),
                'today_count' => today_task_count($pdo, $userId),
            ] + today_stats_payload($pdo, $userId));
        } catch (PDOException $e) {
            error_log('Task move failed: ' . $e->getMessage());
            json_fail('Could not move that task. Please try again.');
        }
    }

    // -------------------------------------------------- SAVE SETTINGS
    // Auto-save endpoint used by the Settings page (no form submit, no reload).
    case 'save_settings': {
        $current  = get_user_settings($pdo, $userId);
        $limit    = (int)($_POST['daily_task_limit'] ?? $current['daily_task_limit']);
        if ($limit < 1 || $limit > 20) {
            json_fail('Daily limit must be between 1 and 20.');
        }
        $theme      = valid_theme($_POST['theme'] ?? ($current['theme'] ?? 'system'));
        $motivation = !empty($_POST['motivation_enabled']) ? 1 : 0;
        try {
            ensure_theme_column($pdo); // upgrade DBs created before themes existed
            $stmt = $pdo->prepare(
                'UPDATE settings SET daily_task_limit = ?, motivation_enabled = ?, theme = ?, updated_at = NOW()
                 WHERE user_id = ?'
            );
            $stmt->execute([$limit, $motivation, $theme, $userId]);
            json_ok(['message' => 'Settings saved.']);
        } catch (PDOException $ex) {
            error_log('Settings save failed: ' . $ex->getMessage());
            json_fail('Something went wrong while saving your settings. Please try again.');
        }
    }

    default:
        json_fail('Unknown action.');
}
