(function () {
    var dropdown = document.getElementById('notifDropdown');
    var listEl = document.getElementById('notifList');
    var emptyEl = document.getElementById('notifEmpty');
    var badge = document.getElementById('notifBadge');
    var markAllBtn = document.getElementById('notifMarkAll');
    if (!dropdown || !listEl) return;

    var apiMeta = document.querySelector('meta[name="notifications-api"]');
    var apiUrl = apiMeta ? apiMeta.getAttribute('content') : '';
    if (!apiUrl) return;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function updateBadge(count) {
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
    }

    function renderList(items) {
        listEl.innerHTML = '';
        if (!items.length) {
            if (emptyEl) emptyEl.hidden = false;
            return;
        }
        if (emptyEl) emptyEl.hidden = true;
        items.forEach(function (item) {
            var a = document.createElement('a');
            a.href = item.url;
            a.className = 'notif-item' + (item.is_read ? '' : ' notif-item--unread');
            a.innerHTML = '<strong>' + escapeHtml(item.title) + '</strong>' +
                (item.priority !== 'normal' ? '<span class="notif-item-priority notif-item-priority--' + item.priority + '">' + escapeHtml(item.priority_label) + '</span>' : '') +
                '<p>' + escapeHtml(item.preview) + '</p>' +
                '<time>' + escapeHtml(formatTime(item.created_at)) + '</time>';
            listEl.appendChild(a);
        });
    }

    function escapeHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function formatTime(iso) {
        if (!iso) return '';
        var d = new Date(iso.replace(' ', 'T') + 'Z');
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleString();
    }

    function refreshUnread() {
        fetch(apiUrl + '?action=unread_count', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) updateBadge(data.unread_count || 0);
            })
            .catch(function () {});
    }

    function refreshList() {
        fetch(apiUrl + '?action=list&limit=12', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                renderList(data.notifications || []);
            })
            .catch(function () {});
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            fetch(apiUrl + '?action=read_all', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken(),
                },
            })
                .then(function () {
                    refreshUnread();
                    refreshList();
                })
                .catch(function () {});
        });
    }

    var btn = document.getElementById('notifBtn');
    if (btn) {
        btn.addEventListener('click', function () {
            refreshList();
        });
    }

    refreshUnread();
    refreshList();
    window.setInterval(refreshUnread, 30000);
})();
