(function () {
    const app = document.getElementById('aiMonitorApp');
    if (!app) return;

    const apiUrl = app.dataset.apiUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function statusBadgeClass(status) {
        return 'badge badge-' + status;
    }

    function renderQueue(rows) {
        const tbody = app.querySelector('[data-ai-queue-body]');
        if (!tbody) return;
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-muted">No AI requests yet.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (row) {
            const cancel = ['pending', 'processing'].indexOf(row.status) >= 0
                ? '<button type="button" class="btn btn-sm btn-danger" data-cancel-job="' + row.id + '">Cancel</button>'
                : '';
            const key = row.assigned_key_index !== null && row.assigned_key_index !== undefined
                ? '#' + (parseInt(row.assigned_key_index, 10) + 1) : '—';
            const pos = row.queue_position ? ' (#' + row.queue_position + ' in queue)' : '';
            return '<tr data-job-id="' + row.id + '">' +
                '<td>#' + row.id + '</td>' +
                '<td><code>' + escapeHtml(row.job_type) + '</code></td>' +
                '<td><span class="' + statusBadgeClass(row.status) + '">' + escapeHtml(row.status) + pos + '</span></td>' +
                '<td class="ai-prompt-cell">' + escapeHtml(row.prompt_preview || '—') + '</td>' +
                '<td>' + key + '</td>' +
                '<td>' + escapeHtml(row.requested_by || '—') + '</td>' +
                '<td>' + escapeHtml(row.created_at || '') + '</td>' +
                '<td>' + cancel + '</td></tr>';
        }).join('');
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function updateStats(counts) {
        ['pending', 'processing', 'completed'].forEach(function (k) {
            const el = app.querySelector('[data-ai-stat="' + k + '"]');
            if (el && counts[k] !== undefined) el.textContent = counts[k];
        });
    }

    function poll() {
        fetch(apiUrl + '?action=queue_snapshot', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                updateStats(data.counts || {});
                renderQueue(data.queue || []);
            })
            .catch(function () {});

        fetch(apiUrl + '?action=key_stats', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.keys) return;
                data.keys.forEach(function (ks) {
                    const el = app.querySelector('[data-key-used="' + ks.index + '"]');
                    if (el) el.textContent = ks.used + ' / ' + ks.limit + ' requests';
                    const cards = app.querySelectorAll('.ai-key-card');
                    const card = cards[ks.index];
                    if (card) {
                        const fill = card.querySelector('.storage-bar-fill');
                        if (fill) fill.style.width = Math.min(100, Math.round((ks.used / ks.limit) * 100)) + '%';
                    }
                });
            })
            .catch(function () {});
    }

    app.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-cancel-job]');
        if (!btn) return;
        const id = btn.getAttribute('data-cancel-job');
        fetch(apiUrl + '?action=cancel_job', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
            body: JSON.stringify({ job_id: parseInt(id, 10) }),
        }).then(function () { poll(); });
    });

    poll();
    setInterval(poll, 3000);
})();
