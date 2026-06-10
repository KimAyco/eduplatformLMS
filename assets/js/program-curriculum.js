(function () {
    'use strict';

    var root = document.querySelector('[data-program-curriculum]');
    if (!root) return;

    var isPreview = root.classList.contains('is-preview-mode');
    var isEdit = root.classList.contains('is-edit-mode');

    var storageKey = 'programCurriculum:' + window.location.pathname + window.location.search;
    var saved = {};
    try {
        saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
    } catch (e) {
        saved = {};
    }

    function persist() {
        sessionStorage.setItem(storageKey, JSON.stringify(saved));
    }

    /* Level tabs */
    var levelTabs = root.querySelectorAll('[data-level-tab]');
    var levelPanels = root.querySelectorAll('[data-level-panel]');

    function activateLevel(levelId) {
        levelTabs.forEach(function (tab) {
            var active = tab.getAttribute('data-level-tab') === levelId;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        levelPanels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-level-panel') !== levelId;
        });
        saved.activeLevel = levelId;
        persist();

        if (isPreview) {
            var panel = root.querySelector('[data-level-panel="' + levelId + '"]');
            if (panel) {
                panel.querySelectorAll('[data-program-term]').forEach(function (term) {
                    term.classList.add('is-open');
                    var toggle = term.querySelector('[data-term-toggle]');
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                });
            }
        }
    }

    levelTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateLevel(tab.getAttribute('data-level-tab'));
        });
    });

    var initialLevel = saved.activeLevel;
    if (initialLevel && root.querySelector('[data-level-panel="' + initialLevel + '"]')) {
        activateLevel(initialLevel);
    } else if (levelTabs.length) {
        activateLevel(levelTabs[0].getAttribute('data-level-tab'));
    }

    /* Term accordions (edit mode only) */
    root.querySelectorAll('[data-program-term]').forEach(function (term) {
        var id = term.getAttribute('data-program-term');
        var btn = term.querySelector('[data-term-toggle]');
        if (!btn) return;

        if (!isPreview && saved['term:' + id] !== undefined) {
            term.classList.toggle('is-open', saved['term:' + id]);
            btn.setAttribute('aria-expanded', saved['term:' + id] ? 'true' : 'false');
        }

        if (!isPreview) {
            btn.addEventListener('click', function () {
                term.classList.toggle('is-open');
                var isOpen = term.classList.contains('is-open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                saved['term:' + id] = isOpen;
                persist();
            });
        }
    });

    function openFromHash() {
        var hash = (window.location.hash || '').replace('#', '');
        if (!hash) return;

        if (hash.indexOf('term-') === 0) {
            var termEl = document.getElementById(hash);
            if (!termEl) return;
            var panel = termEl.closest('[data-level-panel]');
            if (panel) {
                activateLevel(panel.getAttribute('data-level-panel'));
            }
            termEl.classList.add('is-open');
            var toggle = termEl.querySelector('[data-term-toggle]');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
            saved['term:' + hash.replace('term-', '')] = true;
            persist();
            termEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        if (hash.indexOf('level-') === 0 && root.querySelector('[data-level-panel="' + hash + '"]')) {
            activateLevel(hash);
        }
    }

    openFromHash();
    window.addEventListener('hashchange', openFromHash);

    root.querySelectorAll('[data-rename-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('[data-rename-wrap]');
            if (!wrap) return;
            wrap.classList.toggle('is-editing');
            var input = wrap.querySelector('input[type="text"]');
            if (wrap.classList.contains('is-editing') && input) {
                input.focus();
                input.select();
            }
        });
    });

    root.querySelectorAll('[data-rename-cancel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('[data-rename-wrap]');
            if (wrap) wrap.classList.remove('is-editing');
        });
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-rename-wrap]') || e.target.closest('[data-rename-toggle]')) return;
        root.querySelectorAll('[data-rename-wrap].is-editing').forEach(function (wrap) {
            wrap.classList.remove('is-editing');
        });
    });

    /* Subject catalog picker (edit mode) */
    if (isEdit) {
        root.querySelectorAll('[data-subject-picker]').forEach(function (picker) {
            var search = picker.querySelector('[data-subject-search]');
            var grid = picker.querySelector('[data-subject-grid]');
            var countEl = picker.querySelector('[data-subject-count]');
            var rows = picker.querySelectorAll('[data-subject-row]');
            var checkboxes = picker.querySelectorAll('.program-subject-row-check');
            var activeFilter = 'all';

            function syncRows() {
                rows.forEach(function (row) {
                    var cb = row.querySelector('.program-subject-row-check');
                    if (cb) row.classList.toggle('is-selected', cb.checked);
                });
                if (countEl) {
                    var n = 0;
                    checkboxes.forEach(function (cb) { if (cb.checked) n++; });
                    countEl.textContent = String(n);
                }
                applyFilters();
            }

            function applyFilters() {
                var q = search ? search.value.trim().toLowerCase() : '';
                rows.forEach(function (row) {
                    var cb = row.querySelector('.program-subject-row-check');
                    var label = row.getAttribute('data-subject-row') || '';
                    var matchesSearch = q === '' || label.indexOf(q) !== -1;
                    var matchesFilter = activeFilter === 'all'
                        || (activeFilter === 'selected' && cb && cb.checked)
                        || (activeFilter === 'unselected' && cb && !cb.checked);
                    row.classList.toggle('is-hidden', !matchesSearch || !matchesFilter);
                });
            }

            checkboxes.forEach(function (cb) {
                cb.addEventListener('change', syncRows);
            });

            rows.forEach(function (row) {
                row.addEventListener('click', function (e) {
                    if (e.target === row.querySelector('.program-subject-row-check')) return;
                    var cb = row.querySelector('.program-subject-row-check');
                    if (!cb) return;
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            if (search) {
                search.addEventListener('input', applyFilters);
            }

            picker.querySelectorAll('[data-subject-filter]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activeFilter = btn.getAttribute('data-subject-filter') || 'all';
                    picker.querySelectorAll('[data-subject-filter]').forEach(function (b) {
                        b.classList.toggle('is-active', b === btn);
                    });
                    applyFilters();
                });
            });

            picker.querySelectorAll('[data-subject-select-visible]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    rows.forEach(function (row) {
                        if (row.classList.contains('is-hidden')) return;
                        var cb = row.querySelector('.program-subject-row-check');
                        if (cb) cb.checked = true;
                    });
                    syncRows();
                });
            });

            picker.querySelectorAll('[data-subject-clear]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    checkboxes.forEach(function (cb) { cb.checked = false; });
                    syncRows();
                });
            });

            syncRows();
        });
    }

    if (isEdit) {
        root.querySelectorAll('[data-add-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-add-toggle');
                var target = targetId ? document.getElementById(targetId) : null;
                if (!target) return;
                var open = !target.hidden;
                target.hidden = open;
                btn.setAttribute('aria-expanded', open ? 'false' : 'true');
                if (!open) {
                    var input = target.querySelector('input[type="text"]');
                    if (input) input.focus();
                }
            });
        });
    }

    if (isPreview) {
        root.querySelectorAll('[data-program-term]').forEach(function (term) {
            term.classList.add('is-open');
            var toggle = term.querySelector('[data-term-toggle]');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        });
    }
})();
