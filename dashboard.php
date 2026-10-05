<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login($pdo);

$user = current_user($pdo);
rollover_yesterday_tasks($pdo, (int)$user['id']);
$settings = get_user_settings($pdo, (int)$user['id']);

// Today's tasks
$stmt = $pdo->prepare(
    "SELECT * FROM tasks
     WHERE user_id = ? AND status = 'today' AND task_date = CURDATE()
     ORDER BY FIELD(priority, 'high', 'normal', 'low'), created_at ASC"
);
$stmt->execute([$user['id']]);
$todayTasks = $stmt->fetchAll();

// Later tasks (not today, not completed)
$stmt = $pdo->prepare(
    "SELECT * FROM tasks
     WHERE user_id = ? AND status = 'later'
     ORDER BY FIELD(priority, 'high', 'normal', 'low'), created_at ASC"
);
$stmt->execute([$user['id']]);
$laterTasks = $stmt->fetchAll();

$doneToday  = count(array_filter($todayTasks, fn($t) => $t['status'] === 'completed'));
$activeToday = count($todayTasks);
$limit      = (int)$settings['daily_task_limit'];

$page_title = 'Today';
require __DIR__ . '/includes/header.php';
?>
<section class="panel today-panel">
    <div class="today-head">
        <h1>Today</h1>
        <p class="muted" id="today-summary" aria-live="polite">
            <?php if ($activeToday === 0): ?>
                Your day is clear.
            <?php else: ?>
                <?= (int)$doneToday ?> / <?= (int)$activeToday ?> completed
            <?php endif; ?>
        </p>
    </div>

    <div class="progress" role="progressbar" aria-label="Today's progress"
         aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="progress-wrap">
        <div class="progress-fill" id="progress-fill"></div>
    </div>

    <ul class="task-list" id="today-list">
        <?php if (!$todayTasks): ?>
            <li class="empty-state" id="today-empty">
                <p>Nothing planned yet.</p>
                <p class="muted">A short, honest list beats a long, stressful one.</p>
            </li>
        <?php else: ?>
            <?php foreach ($todayTasks as $task):
                require __DIR__ . '/includes/task_item.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>

    <form id="quick-add" class="quick-add" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label class="visually-hidden" for="quick-title">Task title</label>
        <input type="text" id="quick-title" name="title" maxlength="200"
               placeholder="Type a task and press Enter…" required>
        <button type="submit" class="btn btn-primary">Add task</button>
    </form>
    <p class="muted small">Fastest flow: type, Enter, done. Details can wait.</p>
</section>

<details class="panel later-panel" id="later-panel">
    <summary>Later <span class="count-badge" id="later-count"><?= count($laterTasks) ?></span></summary>
    <p class="muted small">Putting a task here is a plan, not a failure.</p>
    <ul class="task-list" id="later-list">
        <?php if (!$laterTasks): ?>
            <li class="empty-state" id="later-empty">
                <p>Nothing waiting in the wings.</p>
            </li>
        <?php else: ?>
            <?php foreach ($laterTasks as $task):
                require __DIR__ . '/includes/task_item.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</details>

<!-- Task edit/create modal -->
<div class="modal-backdrop" id="task-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="task-modal-title">
        <h2 id="task-modal-title">Task</h2>
        <form id="task-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="task-id">
            <label for="task-title">Title</label>
            <input type="text" id="task-title" name="title" maxlength="200" required>

            <label for="task-desc">Description <span class="muted">(optional)</span></label>
            <textarea id="task-desc" name="description" rows="3" maxlength="2000"></textarea>

            <div class="form-row">
                <div>
                    <label for="task-priority">Priority</label>
                    <select id="task-priority" name="priority">
                        <option value="low">Low</option>
                        <option value="normal" selected>Normal</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div>
                    <label for="task-due">Due date <span class="muted">(optional)</span></label>
                    <input type="date" id="task-due" name="due_date">
                </div>
            </div>

            <label for="task-category">Category <span class="muted">(optional)</span></label>
            <input type="text" id="task-category" name="category" maxlength="50" placeholder="e.g. Uni, Home, Health">

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save task</button>
            </div>
        </form>
    </div>
</div>

<!-- Friendly limit modal -->
<div class="modal-backdrop" id="limit-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="limit-title">
        <h2 id="limit-title">That's a full plate</h2>
        <p>You've already got <?= (int)$limit ?> things on today's plate.
           Want to move one to <strong>Later</strong>?</p>
        <form id="limit-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="pending_title" id="limit-pending-title">
            <label for="limit-move-select">Move to Later:</label>
            <select id="limit-move-select" name="move_task_id"></select>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" name="choice" value="move" class="btn btn-primary">Move &amp; add</button>
                <button type="submit" name="choice" value="anyway" class="btn btn-ghost">Add anyway</button>
            </div>
        </form>
    </div>
</div>

<!-- Motivational popup -->
<div class="quote-popup" id="quote-popup" role="status" aria-live="polite" hidden>
    <button class="quote-close" id="quote-close" aria-label="Dismiss">×</button>
    <p id="quote-text"></p>
</div>

<script>
    window.DONEDAY = {
        csrf: <?= json_encode(csrf_token()) ?>,
        limit: <?= (int)$limit ?>,
        motivationEnabled: <?= !empty($settings['motivation_enabled']) ? 'true' : 'false' ?>
    };
</script>
<script src="assets/js/quotes.js?v=20261003c"></script>
<script src="assets/js/app.js?v=20261003c"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
