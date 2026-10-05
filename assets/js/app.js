/**
 * DoneDay - front-end behaviour (vanilla JS, Fetch API).
 * Handles task interactions, modals, progress, and gentle quote popups.
 */
(function () {
    'use strict';

    const CSRF = window.DONEDAY.csrf;
    const LIMIT = window.DONEDAY.limit || 5;
    const MOTIVATION = window.DONEDAY.motivationEnabled !== false;

    const todayList   = document.getElementById('today-list');
    const laterList   = document.getElementById('later-list');
    const isHistory   = !todayList && !!document.querySelector('.task-completed');

    /* ------------------------------ helpers */

    async function api(action, data) {
        const body = new FormData();
        body.append('action', action);
        body.append('csrf', CSRF);
        Object.entries(data || {}).forEach(([k, v]) => body.append(k, v));
        const res = await fetch('api/tasks.php', { method: 'POST', body });
        if (!res.ok) throw new Error('Network error');
        return res.json();
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    /* ------------------------------ quote popup */

    const popup = document.getElementById('quote-popup');
    const quoteText = document.getElementById('quote-text');
    let quoteTimer = null;

    function maybeQuote(category, chance = 1) {
        if (!MOTIVATION || !popup || Math.random() > chance) return;
        const q = window.pickQuote(category);
        if (!q) return;
        quoteText.textContent = q;
        popup.hidden = false;
        popup.classList.add('show');
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(hideQuote, 5000);
    }
    function hideQuote() {
        popup.classList.remove('show');
        popup.hidden = true;
    }
    const closeBtn = document.getElementById('quote-close');
    if (closeBtn) closeBtn.addEventListener('click', hideQuote);

    /* ------------------------------ progress + summary */

    function refreshProgress() {
        if (!todayList) return;
        const items = todayList.querySelectorAll('.task:not(.done)');
        const done  = todayList.querySelectorAll('.task.done').length;
        const total = items.length + done;
        const fill = document.getElementById('progress-fill');
        const wrap = document.getElementById('progress-wrap');
        const summary = document.getElementById('today-summary');
        if (fill) fill.style.width = total ? Math.round((done / total) * 100) + '%' : '0%';
        if (wrap) wrap.setAttribute('aria-valuenow', total ? Math.round((done / total) * 100) : 0);
        if (summary) {
            summary.textContent = total === 0
                ? 'Your day is clear.'
                : (done === total ? "You're done for today." : done + ' / ' + total + ' completed');
        }
        const empty = document.getElementById('today-empty');
        if (empty) empty.style.display = total === 0 ? '' : 'none';
    }

    /* ------------------------------ task rendering */

    function taskNode(task) {
        const li = document.createElement('li');
        li.className = 'task prio-' + (task.priority || 'normal');
        li.dataset.taskId = task.id;
        li.dataset.status = task.status;
        if (task.status === 'later') li.classList.add('task-later');

        const check = document.createElement('input');
        check.type = 'checkbox';
        check.className = 'task-check';
        check.setAttribute('aria-label', 'Mark as completed: ' + task.title);
        if (task.status !== 'today') check.disabled = true;

        const body = document.createElement('div');
        body.className = 'task-body';
        let meta = '';
        if (task.category) meta += '<span class="tag">' + escapeHtml(task.category) + '</span>';
        if (task.due_date) meta += '<span class="tag tag-due">due ' + escapeHtml(task.due_date) + '</span>';
        body.innerHTML =
            '<span class="task-title">' + escapeHtml(task.title) + '</span>' +
            (task.description ? '<span class="task-desc">' + escapeHtml(task.description.slice(0, 90)) + '</span>' : '') +
            '<span class="task-meta">' + meta + '</span>';

        const actions = document.createElement('div');
        actions.className = 'task-actions';
        const isToday = task.status === 'today';
        actions.innerHTML =
            '<button class="btn-icon btn-edit" aria-label="Edit task">✎</button>' +
            '<button class="btn-icon btn-move" data-move-to="' + (isToday ? 'later' : 'today') + '" aria-label="' +
                (isToday ? 'Move to Later' : 'Move to Today') + '">' + (isToday ? '→' : '←') + '</button>' +
            '<button class="btn-icon btn-delete" aria-label="Delete task">×</button>';

        li.append(check, body, actions);
        return li;
    }

    function removeEmptyState(list) {
        const empty = list && list.querySelector('.empty-state');
        if (empty) empty.remove();
    }

    function addToList(task) {
        const list = task.status === 'today' ? todayList : laterList;
        if (!list) return;
        removeEmptyState(list);
        list.prepend(taskNode(task));
        if (task.status === 'later') updateLaterCount(1);
        refreshProgress();
    }

    function updateLaterCount(delta) {
        const badge = document.getElementById('later-count');
        if (!badge) return;
        badge.textContent = Math.max(0, parseInt(badge.textContent || '0', 10) + delta);
    }

    function rebind(li, task) {
        li.dataset.status = task.status;
        li.classList.toggle('task-later', task.status === 'later');
        li.classList.remove('done');
        // keep the priority indicator in sync with the edited value
        li.classList.remove('prio-low', 'prio-normal', 'prio-high');
        li.classList.add('prio-' + (task.priority || 'normal'));
        const check = li.querySelector('.task-check');
        check.disabled = task.status !== 'today';
        const moveBtn = li.querySelector('.btn-move');
        if (moveBtn) {
            const toToday = task.status !== 'today';
            moveBtn.dataset.moveTo = toToday ? 'today' : 'later';
            moveBtn.textContent = toToday ? '←' : '→';
            moveBtn.setAttribute('aria-label', toToday ? 'Move to Today' : 'Move to Later');
        }
        const title = li.querySelector('.task-title');
        if (title) title.textContent = task.title;
        const desc = li.querySelector('.task-desc');
        if (task.description) {
            if (desc) desc.textContent = task.description.slice(0, 90);
            else {
                const d = document.createElement('span');
                d.className = 'task-desc';
                d.textContent = task.description.slice(0, 90);
                title.after(d);
            }
        } else if (desc) desc.remove();
        refreshProgress();
    }

    /* ------------------------------ modals */

    const taskModal = document.getElementById('task-modal');
    const taskForm  = document.getElementById('task-form');
    const limitModal = document.getElementById('limit-modal');
    const limitForm  = document.getElementById('limit-form');
    const limitSelect = document.getElementById('limit-move-select');

    function openModal(m) { m.hidden = false; requestAnimationFrame(() => m.classList.add('open')); }
    function closeModal(m) { m.classList.remove('open'); m.hidden = true; }
    document.querySelectorAll('[data-close-modal]').forEach(b =>
        b.addEventListener('click', () => {
            const m = b.closest('.modal-backdrop');
            if (m) closeModal(m);
        }));
    document.querySelectorAll('.modal-backdrop').forEach(m =>
        m.addEventListener('click', e => { if (e.target === m) closeModal(m); }));
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.open, .modal-backdrop:not([hidden])')
            .forEach(m => closeModal(m));
    });

    function openTaskModal(task) {
        if (!taskModal) return;
        document.getElementById('task-modal-title').textContent = task ? 'Edit task' : 'New task';
        document.getElementById('task-id').value = task ? task.id : '';
        document.getElementById('task-title').value = task ? task.title : '';
        document.getElementById('task-desc').value = task ? (task.description || '') : '';
        document.getElementById('task-priority').value = task ? (task.priority || 'normal') : 'normal';
        document.getElementById('task-due').value = task && task.due_date ? task.due_date : '';
        document.getElementById('task-category').value = task && task.category ? task.category : '';
        openModal(taskModal);
        document.getElementById('task-title').focus();
    }

    function fillLimitModal(todayTasks, pendingTitle) {
        limitSelect.innerHTML = '';
        todayTasks.forEach(t => {
            const o = document.createElement('option');
            o.value = t.id;
            o.textContent = t.title;
            limitSelect.appendChild(o);
        });
        document.getElementById('limit-pending-title').value = pendingTitle || '';
        maybeQuote('limit');
        openModal(limitModal);
    }

    /* ------------------------------ actions */

    // Complete / un-complete
    document.addEventListener('change', async e => {
        if (!e.target.classList.contains('task-check')) return;
        const li = e.target.closest('.task');
        const id = li.dataset.taskId;
        try {
            if (e.target.checked) {
                e.target.disabled = true; // prevent double-submit while the request runs
                const r = await api('complete', { id });
                if (!r.success) { e.target.disabled = false; throw new Error(r.message); }
                li.classList.add('done');
                refreshProgress();           // bar moves instantly
                setTimeout(() => {
                    li.remove();
                    refreshProgress();       // recount after the row leaves
                }, 450);
                if (r.all_done) maybeQuote('alldone');
                else maybeQuote('completed'); // always: this is the reward moment
            }
        } catch (err) {
            e.target.checked = false;
            alert('Something went wrong. Please try again.');
        }
    });

    // Edit / move / delete buttons (delegated)
    document.addEventListener('click', async e => {
        const li = e.target.closest('.task');
        if (!li) return;
        const id = li.dataset.taskId;

        if (e.target.closest('.btn-edit')) {
            openTaskModal({
                id,
                title: li.querySelector('.task-title').textContent,
                description: li.querySelector('.task-desc') ? li.querySelector('.task-desc').textContent : '',
                priority: (li.className.match(/prio-(low|normal|high)/) || [,'normal'])[1],
                due_date: (li.querySelector('.tag-due') || {textContent:''}).textContent.replace('due ', '').trim() || null,
                category: (li.querySelector('.tag:not(.tag-due)') || {textContent:''}).textContent.trim() || null,
            });
        }

        if (e.target.closest('.btn-delete')) {
            if (!confirm('Delete this task? This cannot be undone.')) return;
            try {
                const r = await api('delete', { id });
                if (!r.success) throw new Error(r.message);
                const wasLater = li.dataset.status === 'later';
                li.remove();
                if (wasLater) updateLaterCount(-1);
                refreshProgress();
            } catch (err) { alert('Could not delete that task. Please try again.'); }
        }

        if (e.target.closest('.btn-restore')) {
            try {
                const r = await api('restore', { id });
                if (!r.success) throw new Error(r.message);
                li.remove();
                maybeQuote('created', 0.5);
            } catch (err) { alert('Could not restore that task. Please try again.'); }
        }

        const moveBtn = e.target.closest('.btn-move');
        if (moveBtn) {
            const to = moveBtn.dataset.moveTo;
            try {
                const r = await api('move', { id, to });
                if (!r.success && r.code === 'limit_reached') {
                    fillLimitModal(r.today, null);
                    limitForm.dataset.pendingTaskId = r.pending_task_id || '';
                    limitForm.dataset.mode = 'move';
                    return;
                }
                if (!r.success) throw new Error(r.message);
                li.remove();
                if (to === 'later') {
                    updateLaterCount(1);
                    if (laterList) { removeEmptyState(laterList); laterList.appendChild(taskNode(r.task)); }
                } else {
                    updateLaterCount(-1);
                    removeEmptyState(todayList);
                    todayList.appendChild(taskNode(r.task));
                }
                refreshProgress();
                if (to === 'later') maybeQuote('encourage', 0.4);
            } catch (err) { alert('Could not move that task. Please try again.'); }
        }
    });

    // Quick add
    const quickAdd = document.getElementById('quick-add');
    if (quickAdd) {
        quickAdd.addEventListener('submit', async e => {
            e.preventDefault();
            const input = document.getElementById('quick-title');
            const title = input.value.trim();
            if (!title) return;
            try {
                const r = await api('create', { title });
                if (!r.success && r.code === 'limit_reached') {
                    fillLimitModal(r.today, title);
                    limitForm.dataset.mode = 'create';
                    return;
                }
                if (!r.success) throw new Error(r.message);
                input.value = '';
                addToList(r.task);
                maybeQuote('created', 0.35);
            } catch (err) {
                alert('Something went wrong while saving your task. Please try again.');
            }
        });
    }

    // Limit modal submit: move a task to Later then add/move, or add anyway
    if (limitForm) {
        limitForm.addEventListener('submit', async e => {
            e.preventDefault();
            const choice = e.submitter ? e.submitter.value : 'move';
            const mode = limitForm.dataset.mode;
            try {
                if (choice === 'move') {
                    await api('move', { id: limitSelect.value, to: 'later' });
                }
                if (mode === 'create') {
                    const r = await api('create', { title: document.getElementById('limit-pending-title').value, force: 1 });
                    if (!r.success) throw new Error(r.message);
                    addToList(r.task);
                } else {
                    const id = limitForm.dataset.pendingTaskId;
                    const r = await api('move', { id, to: 'today', force: 1 });
                    if (!r.success) throw new Error(r.message);
                    location.reload(); // simplest correct refresh after a swap
                    return;
                }
                closeModal(limitModal);
            } catch (err) {
                alert('Something went wrong. Please try again.');
            }
        });
    }

    // Task modal submit (create from modal or edit)
    if (taskForm) {
        taskForm.addEventListener('submit', async e => {
            e.preventDefault();
            const id = document.getElementById('task-id').value;
            const data = {
                title: document.getElementById('task-title').value,
                description: document.getElementById('task-desc').value,
                priority: document.getElementById('task-priority').value,
                due_date: document.getElementById('task-due').value,
                category: document.getElementById('task-category').value,
            };
            try {
                if (id) {
                    const r = await api('update', Object.assign({ id }, data));
                    if (!r.success) throw new Error(r.message);
                    const li = document.querySelector('.task[data-task-id="' + id + '"]');
                    if (li) rebind(li, r.task);
                } else {
                    const r = await api('create', data);
                    if (!r.success && r.code === 'limit_reached') {
                        closeModal(taskModal);
                        fillLimitModal(r.today, data.title);
                        limitForm.dataset.mode = 'create';
                        return;
                    }
                    if (!r.success) throw new Error(r.message);
                    addToList(r.task);
                    maybeQuote('created', 0.35);
                }
                closeModal(taskModal);
            } catch (err) {
                alert('Something went wrong while saving your task. Please try again.');
            }
        });
    }

    // Empty-day encouragement
    if (todayList && !todayList.querySelector('.task') && !isHistory) {
        maybeQuote('empty', 0.5);
    }

    refreshProgress();
})();
