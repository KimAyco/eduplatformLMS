(function () {
    var form = document.getElementById('announcementForm');
    if (!form) return;

    var rulesEl = document.getElementById('audienceRules');
    var addBtn = document.getElementById('addAudienceRule');
    var estimateEl = document.getElementById('recipientEstimate');
    var estimateCount = document.getElementById('recipientEstimateCount');
    var apiUrl = form.dataset.api || '';
    var audience = {};
    var targetOptions = [];

    try {
        audience = JSON.parse(form.dataset.audience || '{}');
        targetOptions = JSON.parse(form.dataset.targetOptions || '[]');
    } catch (e) {
        audience = {};
        targetOptions = [];
    }

    var estimateTimer = null;

    function optionNeedsId(type) {
        for (var i = 0; i < targetOptions.length; i++) {
            if (targetOptions[i].type === type) return targetOptions[i].needs_id;
        }
        return false;
    }

    function buildIdSelect(needsId, selectedId, index) {
        if (!needsId) {
            return '<input type="hidden" name="targets[' + index + '][target_id]" value="">';
        }
        var list = audience[needsId + 's'] || audience[needsId] || [];
        if (needsId === 'class_group') list = audience.class_groups || [];
        if (needsId === 'program_level') list = audience.program_levels || [];
        var html = '<select name="targets[' + index + '][target_id]" class="form-control audience-target-id" required>';
        html += '<option value="">Select…</option>';
        list.forEach(function (item) {
            html += '<option value="' + item.id + '"' + (String(item.id) === String(selectedId) ? ' selected' : '') + '>' + escapeHtml(item.name) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function buildUserPicker(index, selectedId) {
        return '<div class="audience-user-picker">' +
            '<input type="hidden" name="targets[' + index + '][target_id]" class="audience-user-id" value="' + (selectedId || '') + '">' +
            '<input type="search" class="form-control audience-user-search" placeholder="Search by name or email…" autocomplete="off">' +
            '<div class="audience-user-results" hidden></div>' +
            '<div class="audience-user-selected text-muted" hidden></div>' +
            '</div>';
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function buildRuleRow(data, index) {
        data = data || {};
        var type = data.target_type || 'all_students';
        var targetId = data.target_id || '';
        var needsId = optionNeedsId(type);

        var html = '<div class="audience-rule" data-index="' + index + '">';
        html += '<div class="audience-rule__type">';
        html += '<label>Audience</label>';
        html += '<select name="targets[' + index + '][target_type]" class="form-control audience-type-select">';
        targetOptions.forEach(function (opt) {
            html += '<option value="' + opt.type + '"' + (opt.type === type ? ' selected' : '') + '>' + escapeHtml(opt.label) + '</option>';
        });
        html += '</select></div>';

        html += '<div class="audience-rule__id">';
        html += '<label>Target</label>';
        html += '<div class="audience-rule__id-field">';
        if (needsId === 'user') {
            html += buildUserPicker(index, targetId);
        } else if (needsId) {
            html += buildIdSelect(needsId, targetId, index);
        } else {
            html += '<span class="text-muted audience-no-target">All matching users</span>';
            html += '<input type="hidden" name="targets[' + index + '][target_id]" value="">';
        }
        html += '</div></div>';

        html += '<button type="button" class="btn btn-outline btn-sm audience-remove" aria-label="Remove rule"><i class="fa-solid fa-trash"></i></button>';
        html += '</div>';
        return html;
    }

    function getRuleCount() {
        return rulesEl.querySelectorAll('.audience-rule').length;
    }

    function addRule(data) {
        var index = getRuleCount();
        rulesEl.insertAdjacentHTML('beforeend', buildRuleRow(data, index));
        bindRule(rulesEl.lastElementChild);
        scheduleEstimate();
    }

    function reindexRules() {
        rulesEl.querySelectorAll('.audience-rule').forEach(function (row, index) {
            row.dataset.index = String(index);
            row.querySelectorAll('[name^="targets["]').forEach(function (el) {
                el.name = el.name.replace(/targets\[\d+\]/, 'targets[' + index + ']');
            });
        });
    }

    function onTypeChange(select) {
        var row = select.closest('.audience-rule');
        var index = row.dataset.index;
        var type = select.value;
        var needsId = optionNeedsId(type);
        var field = row.querySelector('.audience-rule__id-field');
        if (!field) return;

        if (needsId === 'user') {
            field.innerHTML = buildUserPicker(index, '');
        } else if (needsId) {
            field.innerHTML = buildIdSelect(needsId, '', index);
        } else {
            field.innerHTML = '<span class="text-muted audience-no-target">All matching users</span><input type="hidden" name="targets[' + index + '][target_id]" value="">';
        }
        if (needsId === 'user') {
            bindUserPicker(field.querySelector('.audience-user-picker'));
        }
        scheduleEstimate();
    }

    function bindUserPicker(picker) {
        if (!picker || picker.dataset.bound) return;
        picker.dataset.bound = '1';
        var search = picker.querySelector('.audience-user-search');
        var results = picker.querySelector('.audience-user-results');
        var hidden = picker.querySelector('.audience-user-id');
        var selected = picker.querySelector('.audience-user-selected');
        var timer = null;

        search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var q = search.value.trim();
                if (q.length < 1) {
                    results.hidden = true;
                    return;
                }
                fetch(apiUrl + '?action=search_users&q=' + encodeURIComponent(q), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.ok) return;
                        results.innerHTML = '';
                        (data.users || []).forEach(function (u) {
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'audience-user-option';
                            btn.textContent = u.name + ' (' + u.role_label + ') — ' + u.email;
                            btn.addEventListener('click', function () {
                                hidden.value = u.id;
                                selected.textContent = 'Selected: ' + u.name;
                                selected.hidden = false;
                                results.hidden = true;
                                search.value = '';
                                scheduleEstimate();
                            });
                            results.appendChild(btn);
                        });
                        results.hidden = (data.users || []).length === 0;
                    })
                    .catch(function () {});
            }, 250);
        });
    }

    function bindRule(row) {
        var typeSelect = row.querySelector('.audience-type-select');
        if (typeSelect) {
            typeSelect.addEventListener('change', function () { onTypeChange(typeSelect); });
        }
        var remove = row.querySelector('.audience-remove');
        if (remove) {
            remove.addEventListener('click', function () {
                if (getRuleCount() <= 1) return;
                row.remove();
                reindexRules();
                scheduleEstimate();
            });
        }
        row.querySelectorAll('.audience-target-id').forEach(function (el) {
            el.addEventListener('change', scheduleEstimate);
        });
        var picker = row.querySelector('.audience-user-picker');
        if (picker) bindUserPicker(picker);
    }

    function collectTargets() {
        var targets = [];
        rulesEl.querySelectorAll('.audience-rule').forEach(function (row) {
            var type = row.querySelector('.audience-type-select');
            var idEl = row.querySelector('[name$="[target_id]"]');
            if (!type) return;
            targets.push({
                target_type: type.value,
                target_id: idEl && idEl.value ? parseInt(idEl.value, 10) : null,
            });
        });
        return targets;
    }

    function scheduleEstimate() {
        clearTimeout(estimateTimer);
        estimateTimer = setTimeout(refreshEstimate, 400);
    }

    function refreshEstimate() {
        if (!apiUrl) return;
        var targets = collectTargets();
        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch(apiUrl + '?action=estimate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf ? csrf.getAttribute('content') : '',
            },
            body: JSON.stringify({ targets: targets }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                estimateCount.textContent = String(data.recipient_count || 0);
                estimateEl.hidden = false;
            })
            .catch(function () {});
    }

    var initial = [];
    try {
        initial = JSON.parse(rulesEl.dataset.initial || '[]');
    } catch (e) {
        initial = [];
    }
    if (!initial.length) initial = [{ target_type: 'all_students', target_id: null }];
    initial.forEach(addRule);

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            addRule({ target_type: 'all_students', target_id: null });
        });
    }

    refreshEstimate();
})();
