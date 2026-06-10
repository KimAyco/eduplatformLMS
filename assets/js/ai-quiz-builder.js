(function () {
    const app = document.getElementById('aiQuizBuilderApp');
    const form = document.getElementById('aiQuizBuilderForm');
    if (!app || !form) return;

    const apiUrl = app.dataset.apiUrl;
    const classId = parseInt(app.dataset.classId, 10);
    const csrf = app.dataset.csrf;
    const courseUrl = app.dataset.courseUrl;
    const statusEl = document.getElementById('aiQuizBuilderStatus');
    const progressTitle = document.getElementById('aiProgressTitle');
    const progressDetail = document.getElementById('aiProgressDetail');
    const btn = document.getElementById('aiQuizGenerateBtn');

    const sourceLabels = { lesson: 'Class lessons', topic: 'Topic description', upload: 'Uploaded document' };
    const difficultyLabels = { easy: 'Easy', medium: 'Medium', hard: 'Hard', mixed: 'Mixed' };
    const typeLabels = {};
    form.querySelectorAll('[data-preview-type]').forEach(function (cb) {
        typeLabels[cb.value] = cb.closest('.ai-type-chip').querySelector('span').textContent.trim();
    });

    /* Source cards */
    form.querySelectorAll('.ai-source-card input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            form.querySelectorAll('.ai-source-card').forEach(function (card) {
                card.classList.toggle('is-selected', card.querySelector('input').checked);
            });
            form.querySelectorAll('[data-source-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-source-panel') !== radio.value;
            });
            updatePreview();
        });
    });

    /* Difficulty pills */
    form.querySelectorAll('.ai-difficulty-pill input').forEach(function (radio) {
        radio.addEventListener('change', function () {
            form.querySelectorAll('.ai-difficulty-pill').forEach(function (pill) {
                pill.classList.toggle('is-selected', pill.querySelector('input').checked);
            });
            updatePreview();
        });
    });

    /* Type chips */
    form.querySelectorAll('.ai-type-chip input').forEach(function (cb) {
        cb.addEventListener('change', function () {
            cb.closest('.ai-type-chip').classList.toggle('is-selected', cb.checked);
            updatePreview();
            syncTypesToggleLabel();
        });
    });

    const typesToggle = document.getElementById('aiTypesToggleAll');
    if (typesToggle) {
        typesToggle.addEventListener('click', function () {
            const boxes = form.querySelectorAll('.ai-type-chip input');
            const allOn = Array.from(boxes).every(function (b) { return b.checked; });
            boxes.forEach(function (b) {
                b.checked = !allOn;
                b.closest('.ai-type-chip').classList.toggle('is-selected', b.checked);
            });
            updatePreview();
            syncTypesToggleLabel();
        });
    }

    function syncTypesToggleLabel() {
        if (!typesToggle) return;
        const boxes = form.querySelectorAll('.ai-type-chip input');
        const allOn = Array.from(boxes).every(function (b) { return b.checked; });
        typesToggle.textContent = allOn ? 'Deselect all' : 'Select all';
    }

    /* Question count slider + buttons */
    const countInput = form.querySelector('.ai-count-input');
    const countSlider = form.querySelector('.ai-count-slider');
    if (countInput && countSlider) {
        function syncCount(val) {
            val = Math.max(3, Math.min(30, parseInt(val, 10) || 10));
            countInput.value = val;
            countSlider.value = val;
            updatePreview();
        }
        countSlider.addEventListener('input', function () { syncCount(countSlider.value); });
        countInput.addEventListener('change', function () { syncCount(countInput.value); });
        form.querySelectorAll('[data-count-delta]').forEach(function (b) {
            b.addEventListener('click', function () {
                syncCount(parseInt(countInput.value, 10) + parseInt(b.getAttribute('data-count-delta'), 10));
            });
        });
    }

    /* File upload zone */
    const uploadZone = document.getElementById('aiUploadZone');
    const fileInput = document.getElementById('aiDocumentInput');
    const uploadBrowse = document.getElementById('aiUploadBrowse');
    const uploadFile = document.getElementById('aiUploadFile');
    const uploadFileName = document.getElementById('aiUploadFileName');
    const uploadClear = document.getElementById('aiUploadClear');

    function setUploadFile(file) {
        if (!file || !uploadZone) return;
        uploadZone.classList.add('has-file');
        if (uploadFile) uploadFile.hidden = false;
        if (uploadFileName) uploadFileName.textContent = file.name;
        const dt = new DataTransfer();
        dt.items.add(file);
        if (fileInput) fileInput.files = dt.files;
    }

    function clearUpload() {
        if (!uploadZone) return;
        uploadZone.classList.remove('has-file');
        if (uploadFile) uploadFile.hidden = true;
        if (fileInput) fileInput.value = '';
    }

    if (uploadBrowse && fileInput) {
        uploadBrowse.addEventListener('click', function () { fileInput.click(); });
        fileInput.addEventListener('change', function () {
            if (fileInput.files[0]) setUploadFile(fileInput.files[0]);
        });
    }
    if (uploadClear) uploadClear.addEventListener('click', clearUpload);

    if (uploadZone) {
        ['dragenter', 'dragover'].forEach(function (ev) {
            uploadZone.addEventListener(ev, function (e) {
                e.preventDefault();
                uploadZone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            uploadZone.addEventListener(ev, function (e) {
                e.preventDefault();
                uploadZone.classList.remove('is-dragover');
            });
        });
        uploadZone.addEventListener('drop', function (e) {
            const file = e.dataTransfer.files[0];
            if (file) setUploadFile(file);
        });
    }

    /* Live preview */
    function updatePreview() {
        const title = form.querySelector('[data-preview="title"]');
        const section = form.querySelector('[data-preview="section"]');
        const source = form.querySelector('input[name="source_type"]:checked');
        const difficulty = form.querySelector('input[name="difficulty"]:checked');
        const count = form.querySelector('[data-preview="count"]');
        const types = Array.from(form.querySelectorAll('.ai-type-chip input:checked'))
            .map(function (cb) { return typeLabels[cb.value] || cb.value; });

        setOut('title', (title && title.value.trim()) || '—');
        if (section) {
            const opt = section.options[section.selectedIndex];
            setOut('section', opt && opt.value ? opt.textContent : 'Any / unassigned');
        }
        setOut('source', source ? sourceLabels[source.value] || source.value : '—');
        setOut('count', count ? count.value : '—');
        setOut('difficulty', difficulty ? difficultyLabels[difficulty.value] || difficulty.value : '—');
        setOut('types', types.length ? types.join(', ') : 'None selected');
    }

    function setOut(key, text) {
        const el = form.querySelector('[data-preview-out="' + key + '"]');
        if (el) el.textContent = text;
    }

    form.querySelector('[data-preview="title"]')?.addEventListener('input', updatePreview);
    form.querySelector('[data-preview="section"]')?.addEventListener('change', updatePreview);
    updatePreview();
    syncTypesToggleLabel();

    function showProgress(title, detail) {
        if (statusEl) statusEl.hidden = false;
        if (progressTitle) progressTitle.textContent = title;
        if (progressDetail) progressDetail.textContent = detail || '';
        if (btn) btn.disabled = true;
    }

    function pollJob(jobId) {
        const interval = setInterval(function () {
            fetch(apiUrl + '?action=job_status&id=' + jobId, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok || !data.job) return;
                    const job = data.job;
                    if (job.status === 'pending' || job.status === 'processing') {
                        const pos = job.queue_position ? 'Position #' + job.queue_position + ' in queue.' : 'This may take a minute.';
                        showProgress('Generating questions…', pos);
                        return;
                    }
                    clearInterval(interval);
                    if (job.status === 'completed' && job.result && job.result.quiz_id) {
                        showProgress('Success!', 'Opening quiz editor…');
                        window.location.href = courseUrl + '&quiz=' + job.result.quiz_id;
                        return;
                    }
                    showProgress('Generation failed', job.error || 'Please try again.');
                    if (btn) btn.disabled = false;
                });
        }, 2500);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const types = Array.from(form.querySelectorAll('.ai-type-chip input:checked')).map(function (cb) { return cb.value; });
        if (types.length === 0) {
            showProgress('Select question types', 'Choose at least one question type to continue.');
            if (statusEl) statusEl.hidden = false;
            return;
        }

        const fd = new FormData(form);
        const sourceType = fd.get('source_type');
        if (sourceType === 'topic' && !String(fd.get('topic') || '').trim()) {
            showProgress('Topic required', 'Describe what the quiz should cover.');
            if (statusEl) statusEl.hidden = false;
            return;
        }
        if (sourceType === 'upload' && (!fileInput || !fileInput.files[0])) {
            showProgress('Document required', 'Upload a PDF, DOCX, or TXT file.');
            if (statusEl) statusEl.hidden = false;
            return;
        }

        showProgress('Preparing…', 'Sending request to AI.');

        const payload = {
            class_id: classId,
            title: fd.get('title'),
            section_id: parseInt(fd.get('section_id'), 10) || null,
            item_count: parseInt(fd.get('item_count'), 10) || 10,
            difficulty: fd.get('difficulty'),
            question_types: types,
        };

        if (sourceType === 'topic') {
            payload.context_text = fd.get('topic');
        } else if (sourceType === 'lesson') {
            payload.use_lesson_context = true;
            payload.section_id = parseInt(fd.get('section_id'), 10) || null;
        }

        const body = { job_type: 'generate_exam_quiz', payload: payload, prompt_preview: 'Exam quiz: ' + payload.title };

        function enqueueJob() {
            fetch(apiUrl + '?action=enqueue', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
                body: JSON.stringify(body),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        showProgress('Error', data.error || 'Could not start generation.');
                        if (btn) btn.disabled = false;
                        return;
                    }
                    if (data.job && data.job.status === 'completed' && data.job.result && data.job.result.quiz_id) {
                        showProgress('Success!', 'Opening quiz editor…');
                        window.location.href = courseUrl + '&quiz=' + data.job.result.quiz_id;
                        return;
                    }
                    if (data.job_id) pollJob(data.job_id);
                })
                .catch(function () {
                    showProgress('Network error', 'Check your connection and try again.');
                    if (btn) btn.disabled = false;
                });
        }

        if (sourceType === 'upload' && fileInput && fileInput.files[0]) {
            showProgress('Reading document…', 'Extracting text from your file.');
            const uploadFd = new FormData();
            uploadFd.append('document', fileInput.files[0]);
            fetch(apiUrl + '?action=extract_document', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrf },
                body: uploadFd,
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) throw new Error(data.error || 'Upload failed');
                    payload.context_text = data.text;
                    body.payload = payload;
                    showProgress('Queued for generation…', '');
                    enqueueJob();
                })
                .catch(function (err) {
                    showProgress('Upload failed', err.message || 'Could not read document.');
                    if (btn) btn.disabled = false;
                });
            return;
        }

        enqueueJob();
    });
})();
