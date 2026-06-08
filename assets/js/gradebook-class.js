(function () {
    'use strict';

    var root = document.querySelector('[data-gradebook-tabs]');
    if (!root) return;

    var tabs = root.querySelectorAll('[data-gradebook-tab]');
    var panels = root.querySelectorAll('[data-gradebook-panel]');

    function activate(tabId) {
        tabs.forEach(function (tab) {
            var active = tab.getAttribute('data-gradebook-tab') === tabId;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-gradebook-panel') === tabId);
        });
        if (window.location.hash !== '#' + tabId) {
            history.replaceState(null, '', '#' + tabId);
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activate(tab.getAttribute('data-gradebook-tab'));
        });
    });

    var hash = (window.location.hash || '').replace('#', '');
    if (hash && root.querySelector('[data-gradebook-panel="' + hash + '"]')) {
        activate(hash);
    }

    var search = root.querySelector('[data-gradebook-search]');
    if (search) {
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            root.querySelectorAll('[data-gradebook-row]').forEach(function (row) {
                var text = row.getAttribute('data-gradebook-row') || '';
                row.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    root.querySelectorAll('.gb-cell-edit .gb-manual-input').forEach(function (input) {
        var initial = input.value;
        var wrap = input.closest('.gb-cell-edit');
        if (!wrap) return;

        input.addEventListener('input', function () {
            var changed = input.value.trim() !== initial.trim();
            wrap.classList.toggle('gb-cell-edit--dirty', changed);

            var pending = wrap.querySelector('.gb-cell-badge--pending');
            var saved = wrap.querySelector('.gb-cell-badge--override');
            if (changed && !saved) {
                if (!pending) {
                    pending = document.createElement('span');
                    pending.className = 'gb-cell-badge gb-cell-badge--pending';
                    pending.title = 'Unsaved change';
                    pending.innerHTML = '<i class="fa-solid fa-pen" aria-hidden="true"></i> Unsaved';
                    wrap.appendChild(pending);
                }
            } else if (pending) {
                pending.remove();
            }
        });
    });
})();
