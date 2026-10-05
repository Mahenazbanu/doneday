<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login($pdo);

$user = current_user($pdo);
$stmt = $pdo->prepare(
    "SELECT * FROM tasks
     WHERE user_id = ? AND status = 'completed'
     ORDER BY completed_at DESC
     LIMIT 200"
);
$stmt->execute([$user['id']]);
$completed = $stmt->fetchAll();

$page_title = 'Completed';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <h1>Completed</h1>
    <p class="muted">Proof of what you've already done. No analytics, no pressure.</p>

    <?php if (!$completed): ?>
        <div class="empty-state">
            <p>Nothing here yet — and that's okay.</p>
            <p class="muted">Finished tasks will rest here.</p>
        </div>
    <?php else: ?>
        <ul class="task-list">
            <?php foreach ($completed as $task): ?>
                <li class="task task-completed" data-task-id="<?= (int)$task['id'] ?>">
                    <div class="task-body">
                        <span class="task-title"><?= e($task['title']) ?></span>
                        <span class="task-meta muted small">
                            done <?= e($task['completed_at'] ? date('j M Y', strtotime($task['completed_at'])) : '') ?>
                            <?php if (!empty($task['category'])): ?> · <?= e($task['category']) ?><?php endif; ?>
                        </span>
                    </div>
                    <div class="task-actions">
                        <button class="btn-icon btn-restore" aria-label="Restore to Later">↺</button>
                        <button class="btn-icon btn-delete" aria-label="Delete permanently">×</button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div class="quote-popup" id="quote-popup" role="status" aria-live="polite" hidden>
    <button class="quote-close" id="quote-close" aria-label="Dismiss">×</button>
    <p id="quote-text"></p>
</div>
<script>
    window.DONEDAY = {
        csrf: <?= json_encode(csrf_token()) ?>,
        motivationEnabled: <?= !empty(get_user_settings($pdo, (int)$user['id'])['motivation_enabled']) ? 'true' : 'false' ?>
    };
</script>
<script src="assets/js/quotes.js?v=20261003c"></script>
<script src="assets/js/app.js?v=20261003c"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
