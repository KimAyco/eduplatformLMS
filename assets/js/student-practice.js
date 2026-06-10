(function () {
    const app = document.getElementById('studentPracticeApp');
    if (!app) return;

    const apiUrl = app.dataset.apiUrl;
    const classId = app.dataset.classId;
    const quizUrl = app.dataset.quizUrl || 'quiz-take.php';
    const dialog = document.getElementById('practiceConfigDialog');
    const form = document.getElementById('practiceConfigForm');
    const titleEl = document.getElementById('practiceConfigTitle');
    const subtitleEl = document.getElementById('practiceConfigSubtitle');
    const countInput = document.getElementById('practiceItemCount');
    const countSlider = document.getElementById('practiceItemSlider');
    const typeChips = document.getElementById('practiceTypeChips');
    const typesToggle = document.getElementById('practiceTypesToggleAll');

    let pendingBtn = null;

    function statusElFor(btn) {
        const scope = btn.getAttribute('data-practice-scope') || 'lesson';
        const sid = btn.getAttribute('data-section-id') || '0';
        const key = scope === 'course' ? 'course' : (scope + (sid !== '0' ? '-' + sid : ''));
        return app.querySelector('[data-practice-status="' + key + '"]');
    }

    function syncCount(value) {
        const n = Math.max(3, Math.min(30, parseInt(value, 10) || 10));
        if (countInput) countInput.value = n;
        if (countSlider) countSlider.value = n;
    }

    function updateTypeChipStates() {
        if (!typeChips) return;
        typeChips.querySelectorAll('.ai-type-chip').forEach(function (chip) {
            const input = chip.querySelector('input[type="checkbox"]');
            chip.classList.toggle('is-selected', input && input.checked);
        });
    }

    function readConfig() {
        const types = [];
        if (typeChips) {
            typeChips.querySelectorAll('input[name="question_types[]"]:checked').forEach(function (el) {
                types.push(el.value);
            });
        }
        return {
            item_count: parseInt(countInput ? countInput.value : '10', 10) || 10,
            question_types: types,
        };
    }

    function openConfigModal(btn) {
        if (!dialog || !form) {
            startPractice(btn, readConfig());
            return;
        }
        pendingBtn = btn;
        const label = btn.getAttribute('data-practice-label') || 'Practice quiz';
        if (titleEl) titleEl.textContent = 'Configure practice';
        if (subtitleEl) subtitleEl.textContent = label;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    }

    function closeConfigModal() {
        if (dialog && typeof dialog.close === 'function') {
            dialog.close();
        }
        pendingBtn = null;
    }

    function pollJob(jobId, statusEl, btn, config) {
        const interval = setInterval(function () {
            fetch(apiUrl + '?action=job_status&id=' + jobId, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok || !data.job) return;
                    const job = data.job;
                    if (job.status === 'pending' || job.status === 'processing') {
                        const pos = job.queue_position ? ' (queue #' + job.queue_position + ')' : '';
                        statusEl.textContent = 'Generating practice questions' + pos + '…';
                        statusEl.hidden = false;
                        return;
                    }
                    clearInterval(interval);
                    if (job.status === 'completed') {
                        startPractice(btn, config, true);
                        return;
                    }
                    statusEl.textContent = job.error || 'Could not generate practice quiz.';
                    statusEl.hidden = false;
                    statusEl.classList.add('practice-status--error');
                    btn.disabled = false;
                })
                .catch(function () {});
        }, 2500);
    }

    function startPractice(btn, config, fromJob) {
        const scope = btn.getAttribute('data-practice-scope') || 'lesson';
        const sectionId = btn.getAttribute('data-section-id') || '0';
        const statusEl = statusElFor(btn);

        btn.disabled = true;
        if (statusEl) {
            statusEl.textContent = fromJob ? 'Building your quiz…' : 'Preparing practice quiz…';
            statusEl.hidden = false;
            statusEl.classList.remove('practice-status--error');
        }

        const payload = {
            class_id: classId,
            scope: scope,
            section_id: sectionId,
            item_count: config.item_count,
            question_types: config.question_types,
        };

        fetch(apiUrl + '?action=practice_start', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    if (statusEl) {
                        statusEl.textContent = data.error || 'Something went wrong.';
                        statusEl.classList.add('practice-status--error');
                    }
                    btn.disabled = false;
                    return;
                }
                if (data.status === 'ready' && data.url) {
                    window.location.href = data.url;
                    return;
                }
                if (data.job_id) {
                    pollJob(data.job_id, statusEl, btn, config);
                }
            })
            .catch(function () {
                if (statusEl) {
                    statusEl.textContent = 'Network error. Try again.';
                    statusEl.classList.add('practice-status--error');
                }
                btn.disabled = false;
            });
    }

    if (countInput && countSlider) {
        countInput.addEventListener('input', function () { syncCount(countInput.value); });
        countSlider.addEventListener('input', function () { syncCount(countSlider.value); });
    }

    app.querySelectorAll('[data-practice-count-delta]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const delta = parseInt(btn.getAttribute('data-practice-count-delta'), 10) || 0;
            syncCount((parseInt(countInput.value, 10) || 10) + delta);
        });
    });

    if (typeChips) {
        typeChips.addEventListener('change', updateTypeChipStates);
    }

    if (typesToggle && typeChips) {
        typesToggle.addEventListener('click', function () {
            const boxes = typeChips.querySelectorAll('input[type="checkbox"]');
            const allChecked = Array.prototype.every.call(boxes, function (b) { return b.checked; });
            boxes.forEach(function (b) { b.checked = !allChecked; });
            typesToggle.textContent = allChecked ? 'Select all' : 'Clear all';
            updateTypeChipStates();
        });
    }

    document.querySelectorAll('[data-close-practice-config]').forEach(function (el) {
        el.addEventListener('click', closeConfigModal);
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const config = readConfig();
            if (!config.question_types.length) {
                alert('Select at least one question type.');
                return;
            }
            const btn = pendingBtn;
            closeConfigModal();
            if (btn) {
                startPractice(btn, config);
            }
        });
    }

    app.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-start-practice]');
        if (!btn) return;
        e.preventDefault();
        openConfigModal(btn);
    });
})();
