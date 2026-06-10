(function () {
    'use strict';

    var wrap = document.getElementById('gradingSchemeRows');
    if (!wrap) return;

    var totalEl = document.getElementById('gradingWeightTotal');
    var badgeEl = document.getElementById('gradingWeightBadge');
    var barEl = document.getElementById('gradingWeightBar');
    var categories = JSON.parse(wrap.getAttribute('data-categories') || '{}');
    var categoryIcons = {
        quiz: 'fa-circle-question',
        exam: 'fa-file-pen',
        assignment: 'fa-pen-to-square',
        participation: 'fa-hand',
        project: 'fa-folder-open',
        other: 'fa-tag'
    };
    var categoryTones = ['quiz', 'exam', 'assignment', 'participation', 'project', 'other'];

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function categoryOptions(selected) {
        return Object.keys(categories).map(function (key) {
            var sel = key === selected ? ' selected' : '';
            return '<option value="' + escapeAttr(key) + '"' + sel + '>' + escapeAttr(categories[key]) + '</option>';
        }).join('');
    }

    function toneForCategory(cat) {
        return categoryTones.indexOf(cat) >= 0 ? cat : 'other';
    }

    function updateTotal() {
        var sum = 0;
        var segments = [];
        wrap.querySelectorAll('.grading-scheme-row').forEach(function (row) {
            var weight = parseFloat(row.querySelector('[data-weight-input]').value) || 0;
            var cat = row.querySelector('[name="grading_category[]"]').value;
            sum += weight;
            if (weight > 0) {
                segments.push({ cat: cat, weight: weight });
            }
        });

        var isInvalid = Math.abs(sum - 100) > 0.01 && wrap.children.length > 0;
        var isValid = Math.abs(sum - 100) <= 0.01 && wrap.children.length > 0;

        if (totalEl) {
            totalEl.textContent = sum.toFixed(2);
            totalEl.classList.toggle('is-invalid', isInvalid);
            totalEl.classList.toggle('is-valid', isValid);
        }

        if (badgeEl) {
            badgeEl.classList.toggle('is-invalid', isInvalid);
            badgeEl.classList.toggle('is-valid', isValid);
            badgeEl.classList.toggle('is-empty', wrap.children.length === 0);
        }

        if (barEl) {
            barEl.innerHTML = '';
            segments.forEach(function (seg) {
                var span = document.createElement('span');
                span.className = 'gb-weight-bar__seg gb-weight-bar__seg--' + toneForCategory(seg.cat);
                span.style.width = seg.weight + '%';
                barEl.appendChild(span);
            });
        }
    }

    function addRow(data) {
        data = data || { category: 'quiz', label: '', weight_percent: '' };
        var tone = toneForCategory(data.category);
        var icon = categoryIcons[data.category] || categoryIcons.other;
        var row = document.createElement('div');
        row.className = 'grading-scheme-row grading-scheme-row--' + tone;
        row.innerHTML =
            '<div class="grading-scheme-row__type">' +
                '<span class="grading-scheme-row__icon"><i class="fa-solid ' + icon + '"></i></span>' +
                '<select name="grading_category[]" class="form-control" data-category-select>' + categoryOptions(data.category) + '</select>' +
            '</div>' +
            '<input type="text" name="grading_label[]" class="form-control" value="' + escapeAttr(data.label || '') + '" placeholder="e.g. Quiz 1, Midterm">' +
            '<div class="grading-scheme-row__weight">' +
                '<input type="number" step="0.01" min="0" max="100" name="grading_weight[]" class="form-control" data-weight-input value="' + escapeAttr(data.weight_percent ?? '') + '" placeholder="0">' +
                '<span class="grading-scheme-row__pct">%</span>' +
            '</div>' +
            '<button type="button" class="btn btn-sm btn-secondary grading-scheme-row__remove" data-action="remove-row" aria-label="Remove">&times;</button>';
        wrap.appendChild(row);

        row.querySelector('[data-weight-input]').addEventListener('input', updateTotal);
        row.querySelector('[data-category-select]').addEventListener('change', function (e) {
            var cat = e.target.value;
            row.className = 'grading-scheme-row grading-scheme-row--' + toneForCategory(cat);
            row.querySelector('.grading-scheme-row__icon i').className = 'fa-solid ' + (categoryIcons[cat] || categoryIcons.other);
            updateTotal();
        });
        updateTotal();
    }

    document.querySelectorAll('[data-add-grading-row]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cat = btn.getAttribute('data-category') || 'quiz';
            var label = categories[cat] || 'Component';
            var count = wrap.querySelectorAll('.grading-scheme-row').length + 1;
            addRow({ category: cat, label: label + ' ' + count, weight_percent: '' });
        });
    });

    wrap.addEventListener('click', function (e) {
        if (e.target.closest('[data-action="remove-row"]')) {
            e.target.closest('.grading-scheme-row').remove();
            updateTotal();
        }
    });

    try {
        var initial = JSON.parse(wrap.getAttribute('data-initial') || '[]');
        if (initial.length) {
            initial.forEach(addRow);
        }
    } catch (err) {
        /* ignore */
    }

    updateTotal();
})();
