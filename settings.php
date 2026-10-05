<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login($pdo);

$user = current_user($pdo);
$settings = get_user_settings($pdo, (int)$user['id']);

// ------------------------------------------------------------------
// POST = auto-save endpoint (called by fetch whenever a control changes).
// Always answers JSON: {"success":true,"message":"Saved"} or
// {"success":false,"message":"..."} - never a 500, never a new tab.
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Session expired. Refresh and try again.']);
        exit;
    }

    $limit = (int)($_POST['daily_task_limit'] ?? 5);
    if ($limit < 1 || $limit > 20) {
        echo json_encode(['success' => false, 'message' => 'Daily limit must be between 1 and 20.']);
        exit;
    }
    $motivation = isset($_POST['motivation_enabled']) ? 1 : 0;
    $theme = valid_theme($_POST['theme'] ?? 'system');

    try {
        ensure_theme_column($pdo); // upgrade DBs created before themes existed
        $stmt = $pdo->prepare(
            'UPDATE settings SET daily_task_limit = ?, motivation_enabled = ?, theme = ?, updated_at = NOW() WHERE user_id = ?'
        );
        $stmt->execute([$limit, $motivation, $theme, $user['id']]);
        echo json_encode(['success' => true, 'message' => 'Settings saved.']);
    } catch (PDOException $ex) {
        error_log('Settings save failed: ' . $ex->getMessage());
        echo json_encode(['success' => false, 'message' => 'Something went wrong while saving. Please try again.']);
    }
    exit;
}

$page_title = 'Settings';
require __DIR__ . '/includes/header.php';
?>
<section class="panel settings-panel">
    <h1>Settings</h1>
    <p class="muted small">Changes save automatically — no button needed.</p>
    <p class="save-status" id="save-status" role="status" aria-live="polite"></p>

    <form id="settings-form" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <fieldset>
            <legend>Daily task limit</legend>
            <p class="muted small">How many active tasks Today holds. Fewer is calmer.</p>
            <div class="limit-choices" role="radiogroup">
                <?php foreach ([3, 5, 7] as $n): ?>
                    <label class="choice">
                        <input type="radio" name="daily_task_limit" value="<?= $n ?>"
                               <?= (int)$settings['daily_task_limit'] === $n ? 'checked' : '' ?>>
                        <span><?= $n ?></span>
                    </label>
                <?php endforeach; ?>
                <label class="choice choice-custom">
                    <input type="radio" name="daily_task_limit" id="limit-custom-radio" value="custom"
                                   <?= in_array((int)$settings['daily_task_limit'], [3,5,7], true) ? '' : 'checked' ?>>
                    <span>Custom</span>
                    <input type="number" id="limit-custom-value" name="custom_limit"
                           min="1" max="20" value="<?= (int)$settings['daily_task_limit'] ?>"
                           aria-label="Custom limit">
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Appearance</legend>
            <p class="muted small">Applies instantly and saves automatically.</p>
            <div class="limit-choices" role="radiogroup">
                <?php foreach (['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label): ?>
                    <label class="choice">
                        <input type="radio" name="theme" value="<?= e($value) ?>"
                               <?= ($settings['theme'] ?? 'system') === $value ? 'checked' : '' ?>>
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Motivational messages</legend>
            <label class="check-row">
                <input type="checkbox" name="motivation_enabled" value="1"
                       <?= !empty($settings['motivation_enabled']) ? 'checked' : '' ?>>
                Show gentle, context-aware messages
            </label>
        </fieldset>
    </form>

    <section class="account-box">
        <h2>Account</h2>
        <p><strong><?= e($user['name']) ?></strong><br>
           <span class="muted"><?= e($user['email']) ?></span><br>
           <span class="muted small">Member since <?= e(date('j M Y', strtotime($user['created_at']))) ?></span></p>
        <a class="btn btn-ghost" href="logout.php">Log out</a>
    </section>
</section>
<script>
    // Theme: preview instantly on the whole page (System follows the OS live).
    (function () {
        const root = document.documentElement;
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const apply = value => {
            root.dataset.theme = value === 'system' ? (media.matches ? 'dark' : 'light') : value;
        };
        document.querySelectorAll('input[name="theme"]').forEach(r =>
            r.addEventListener('change', () => apply(r.value)));
        media.addEventListener('change', () => {
            const selected = document.querySelector('input[name="theme"]:checked');
            if (selected && selected.value === 'system') apply('system');
        });
    })();

    // Auto-save: any change inside the form POSTs to settings.php and
    // shows an inline Saved / error status. No button, no reload.
    (function () {
        const form = document.getElementById('settings-form');
        const status = document.getElementById('save-status');
        const customRadio = document.getElementById('limit-custom-radio');
        const customValue = document.getElementById('limit-custom-value');
        const csrf = form.querySelector('input[name="csrf"]').value;
        let timer = null;
        let saveTimer = null;

        // Custom limit: typing a number selects the Custom pill.
        customValue.addEventListener('input', () => {
            if (customValue.value !== '') {
                customRadio.value = customValue.value;
                customRadio.checked = true;
                save();
            }
        });

        form.addEventListener('change', save);
        form.addEventListener('submit', e => e.preventDefault()); // Enter key must not navigate

        function currentLimit() {
            if (customRadio.checked) return customValue.value;
            return form.querySelector('input[name="daily_task_limit"]:checked')?.value ?? '5';
        }

        function save() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(doSave, 250); // one request per burst of changes
        }

        function doSave() {
            const body = new FormData();
            body.append('csrf', csrf);
            body.append('daily_task_limit', currentLimit());
            body.append('theme', form.querySelector('input[name="theme"]:checked')?.value ?? 'system');
            if (form.querySelector('input[name="motivation_enabled"]').checked) {
                body.append('motivation_enabled', '1');
            }

            status.textContent = 'Saving…';
            status.className = 'save-status saving';

            fetch('settings.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    status.textContent = data.success ? 'Saved ✓' : (data.message || 'Could not save.');
                    status.className = 'save-status ' + (data.success ? 'saved' : 'error');
                })
                .catch(() => {
                    status.textContent = 'Could not save. Check your connection and try again.';
                    status.className = 'save-status error';
                });

            clearTimeout(timer);
            timer = setTimeout(() => { status.textContent = ''; }, 3000);
        }
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
