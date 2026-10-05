<?php
/** Reusable task row. Expects $task (array). */
$isToday = ($task['status'] === 'today');
$prio = $task['priority'] ?? 'normal';
?>
<li class="task prio-<?= e($prio) ?> <?= $isToday ? '' : 'task-later' ?>"
    data-task-id="<?= (int)$task['id'] ?>" data-status="<?= e($task['status']) ?>">
    <input type="checkbox" class="task-check" <?= $isToday ? '' : 'disabled' ?>
           aria-label="Mark as completed: <?= e($task['title']) ?>">
    <div class="task-body">
        <span class="task-title"><?= e($task['title']) ?></span>
        <?php if (!empty($task['description'])): ?>
            <span class="task-desc"><?= e(mb_strimwidth($task['description'], 0, 90, '…')) ?></span>
        <?php endif; ?>
        <span class="task-meta">
            <?php if (!empty($task['category'])): ?><span class="tag"><?= e($task['category']) ?></span><?php endif; ?>
            <?php if (!empty($task['due_date'])): ?><span class="tag tag-due">due <?= e($task['due_date']) ?></span><?php endif; ?>
        </span>
    </div>
    <div class="task-actions">
        <button class="btn-icon btn-edit" aria-label="Edit task">✎</button>
        <button class="btn-icon btn-move" data-move-to="<?= $isToday ? 'later' : 'today' ?>"
                aria-label="<?= $isToday ? 'Move to Later' : 'Move to Today' ?>">
            <?= $isToday ? '→' : '←' ?>
        </button>
        <button class="btn-icon btn-delete" aria-label="Delete task">×</button>
    </div>
</li>
