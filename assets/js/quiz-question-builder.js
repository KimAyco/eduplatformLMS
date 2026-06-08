(function () {
    var form = document.getElementById('questionForm');
    if (!form) return;

    var typeSelect = document.getElementById('questionType');
    var questionText = document.getElementById('questionText');
    var questionTextGroup = document.getElementById('questionTextGroup');
    var questionTextHint = document.getElementById('questionTextHint');
    var mcqList = document.getElementById('mcqChoicesList');
    var mcqFields = document.getElementById('mcqFields');
    var addMcqBtn = document.getElementById('addMcqChoice');
    var matchingList = document.getElementById('matchingRowsList');
    var addMatchingBtn = document.getElementById('addMatchingRow');
    var fibBuilder = document.getElementById('fillBlankBuilder');
    var fibInsertBlankBtn = document.getElementById('fibInsertBlank');

    var MCQ_MIN = 2;
    var MCQ_MAX = 10;
    var MATCH_MIN = 2;
    var MATCH_MAX = 12;

    function parseJson(el, fallback) {
        if (!el) return fallback;
        try {
            return JSON.parse(el.getAttribute('data-choices') || el.getAttribute('data-pairs') || el.getAttribute('data-segments') || 'null') || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function toggleTypeFields() {
        var type = typeSelect.value;
        document.querySelectorAll('.quiz-type-fields').forEach(function (el) {
            el.style.display = 'none';
        });
        var map = {
            multiple_choice: 'mcqFields',
            true_false: 'tfFields',
            essay: 'essayFields',
            fill_blank: 'fillBlankFields',
            matching: 'matchingFields',
            file_response: 'fileResponseFields'
        };
        var panel = document.getElementById(map[type]);
        if (panel) panel.style.display = 'block';

        if (type === 'fill_blank') {
            questionTextGroup.style.display = 'none';
            questionText.removeAttribute('required');
        } else {
            questionTextGroup.style.display = '';
            questionText.setAttribute('required', 'required');
            questionTextHint.textContent = 'Enter the question prompt students will see.';
        }
    }

    function renderMcqChoices() {
        if (!mcqList || !mcqFields) return;
        var choices = parseJson(mcqFields, ['', '']);
        var correct = parseInt(mcqFields.getAttribute('data-correct') || '0', 10);
        mcqList.innerHTML = '';

        choices.forEach(function (text, index) {
            var row = document.createElement('div');
            row.className = 'quiz-mcq-choice option-row';
            row.innerHTML =
                '<label class="quiz-mcq-correct" title="Correct answer">' +
                    '<input type="radio" name="correct_choice_index" value="' + index + '"' + (index === correct ? ' checked' : '') + '>' +
                    '<span class="sr-only">Correct</span>' +
                '</label>' +
                '<input type="text" name="choices[' + index + '][text]" class="form-control" value="' + escapeAttr(text) + '" placeholder="Choice ' + (index + 1) + '">' +
                '<button type="button" class="btn btn-sm btn-secondary quiz-remove-row" data-action="remove-mcq" aria-label="Remove choice">&times;</button>';
            mcqList.appendChild(row);
        });

        mcqList.querySelectorAll('[data-action="remove-mcq"]').forEach(function (btn) {
            btn.disabled = choices.length <= MCQ_MIN;
        });
    }

    function addMcqChoice() {
        var count = mcqList.querySelectorAll('.quiz-mcq-choice').length;
        if (count >= MCQ_MAX) return;
        var choices = collectMcqChoices();
        choices.push('');
        mcqFields.setAttribute('data-choices', JSON.stringify(choices));
        renderMcqChoices();
    }

    function collectMcqChoices() {
        return Array.from(mcqList.querySelectorAll('.quiz-mcq-choice')).map(function (row) {
            return row.querySelector('input[type="text"]').value;
        });
    }

    function renderMatchingRows() {
        if (!matchingList) return;
        var pairs = parseJson(matchingList, [{ left: '', right: '' }, { left: '', right: '' }]);
        matchingList.innerHTML = '';

        pairs.forEach(function (pair, index) {
            var row = document.createElement('tr');
            row.className = 'quiz-matching-builder-row';
            row.innerHTML =
                '<td><input type="text" name="matching_left[]" class="form-control" value="' + escapeAttr(pair.left || '') + '" placeholder="Prompt ' + (index + 1) + '"></td>' +
                '<td><input type="text" name="matching_right[]" class="form-control" value="' + escapeAttr(pair.right || '') + '" placeholder="Answer ' + (index + 1) + '"></td>' +
                '<td><button type="button" class="btn btn-sm btn-secondary quiz-remove-row" data-action="remove-match" aria-label="Remove pair">&times;</button></td>';
            matchingList.appendChild(row);
        });

        matchingList.querySelectorAll('[data-action="remove-match"]').forEach(function (btn) {
            btn.disabled = pairs.length <= MATCH_MIN;
        });
    }

    function addMatchingRow() {
        var count = matchingList.querySelectorAll('.quiz-matching-builder-row').length;
        if (count >= MATCH_MAX) return;
        var pairs = collectMatchingPairs();
        pairs.push({ left: '', right: '' });
        matchingList.setAttribute('data-pairs', JSON.stringify(pairs));
        renderMatchingRows();
    }

    function collectMatchingPairs() {
        return Array.from(matchingList.querySelectorAll('.quiz-matching-builder-row')).map(function (row) {
            var inputs = row.querySelectorAll('input');
            return { left: inputs[0].value, right: inputs[1].value };
        });
    }

    function parseFibSegments() {
        if (!fibBuilder) return [{ type: 'text', value: '' }];
        try {
            return JSON.parse(fibBuilder.getAttribute('data-segments') || 'null') || [{ type: 'text', value: '' }];
        } catch (e) {
            return [{ type: 'text', value: '' }];
        }
    }

    function autoSizeFibInput(input, minCh, maxCh) {
        input.style.maxWidth = maxCh + 'ch';
        input.style.width = minCh + 'ch';
        input.style.width = Math.max(input.offsetWidth, input.scrollWidth + 6) + 'px';
    }

    function autoSizeFibText(input) {
        autoSizeFibInput(input, 3, 48);
    }

    function autoSizeFibAnswer(input) {
        autoSizeFibInput(input, 5, 48);
    }

    function bindFibInput(input) {
        if (input.classList.contains('quiz-fib-text')) {
            autoSizeFibText(input);
        } else if (input.classList.contains('quiz-fib-answer')) {
            autoSizeFibAnswer(input);
        }
    }

    function getActiveFibTextInput() {
        var active = document.activeElement;
        if (active && active.classList && active.classList.contains('quiz-fib-text') && fibBuilder.contains(active)) {
            return active;
        }
        var segments = fibBuilder.querySelectorAll('.quiz-fib-segment--text .quiz-fib-text');
        return segments.length ? segments[segments.length - 1] : null;
    }

    function ensureFibHasTextSegment() {
        if (!fibBuilder.querySelector('.quiz-fib-segment--text')) {
            fibBuilder.appendChild(createFibSegment({ type: 'text', value: '' }));
        }
    }

    function ensureTrailingTextAfterBlanks() {
        Array.from(fibBuilder.querySelectorAll('.quiz-fib-segment--blank')).forEach(function (blank) {
            var next = blank.nextElementSibling;
            if (!next || !next.classList.contains('quiz-fib-segment--text')) {
                var afterSeg = createFibSegment({ type: 'text', value: '' });
                blank.after(afterSeg);
                bindFibInput(afterSeg.querySelector('.quiz-fib-text'));
            }
        });
    }

    function mergeAdjacentTextSegments() {
        var segments = Array.from(fibBuilder.querySelectorAll('.quiz-fib-segment'));
        segments.forEach(function (segment, index) {
            if (!segment.classList.contains('quiz-fib-segment--text') || index === 0) return;
            var prev = segments[index - 1];
            if (!prev.classList.contains('quiz-fib-segment--text')) return;
            var prevInput = prev.querySelector('.quiz-fib-text');
            var curInput = segment.querySelector('.quiz-fib-text');
            prevInput.value = (prevInput.value + ' ' + curInput.value).replace(/\s+/g, ' ').trim();
            autoSizeFibText(prevInput);
            segment.remove();
        });
    }

    function renderFibSegments() {
        if (!fibBuilder) return;
        var segments = parseFibSegments();
        fibBuilder.innerHTML = '';

        segments.forEach(function (segment) {
            fibBuilder.appendChild(createFibSegment(segment));
        });

        if (!fibBuilder.children.length) {
            fibBuilder.appendChild(createFibSegment({ type: 'text', value: '' }));
        }

        fibBuilder.querySelectorAll('.quiz-fib-text, .quiz-fib-answer').forEach(bindFibInput);
        mergeAdjacentTextSegments();
        ensureFibHasTextSegment();
        ensureTrailingTextAfterBlanks();
    }

    function createFibSegment(segment) {
        var wrap = document.createElement('span');
        wrap.className = 'quiz-fib-segment quiz-fib-segment--' + segment.type;

        if (segment.type === 'blank') {
            wrap.innerHTML =
                '<span class="quiz-fib-blank-wrap">' +
                    '<input type="text" class="form-control quiz-fib-answer" value="' + escapeAttr(segment.answer || '') + '" placeholder="answer" aria-label="Correct answer for blank">' +
                    '<button type="button" class="btn btn-sm btn-secondary quiz-remove-row" data-action="remove-fib" aria-label="Remove blank">&times;</button>' +
                '</span>';
        } else {
            wrap.innerHTML =
                '<input type="text" class="form-control quiz-fib-text" value="' + escapeAttr(segment.value || '') + '" placeholder="Type here…" aria-label="Sentence text">';
        }

        return wrap;
    }

    function collectFibSegments() {
        return Array.from(fibBuilder.querySelectorAll('.quiz-fib-segment')).map(function (seg) {
            if (seg.classList.contains('quiz-fib-segment--blank')) {
                return { type: 'blank', answer: seg.querySelector('.quiz-fib-answer').value };
            }
            return { type: 'text', value: seg.querySelector('.quiz-fib-text').value };
        });
    }

    function insertFibBlankAtCursor() {
        ensureFibHasTextSegment();
        var textInput = getActiveFibTextInput();
        if (!textInput) return;

        var segmentEl = textInput.closest('.quiz-fib-segment--text');
        var cursorPos = typeof textInput.selectionStart === 'number' ? textInput.selectionStart : textInput.value.length;
        var before = textInput.value.slice(0, cursorPos);
        var after = textInput.value.slice(cursorPos);

        textInput.value = before;
        autoSizeFibText(textInput);

        var blankSeg = createFibSegment({ type: 'blank', answer: '' });
        segmentEl.after(blankSeg);

        var afterSeg = createFibSegment({ type: 'text', value: after });
        blankSeg.after(afterSeg);
        bindFibInput(afterSeg.querySelector('.quiz-fib-text'));

        bindFibInput(blankSeg.querySelector('.quiz-fib-answer'));
        ensureTrailingTextAfterBlanks();

        var trailInput = afterSeg.querySelector('.quiz-fib-text');
        trailInput.focus();
        var pos = after.length;
        trailInput.setSelectionRange(pos, pos);
    }

    function removeFibSegment(segment) {
        var isBlank = segment.classList.contains('quiz-fib-segment--blank');
        var prev = segment.previousElementSibling;
        var next = segment.nextElementSibling;

        if (isBlank && prev && prev.classList.contains('quiz-fib-segment--text') && next && next.classList.contains('quiz-fib-segment--text')) {
            var prevInput = prev.querySelector('.quiz-fib-text');
            var nextInput = next.querySelector('.quiz-fib-text');
            prevInput.value = [prevInput.value, nextInput.value].filter(Boolean).join(' ').trim();
            autoSizeFibText(prevInput);
            next.remove();
        }

        segment.remove();
        ensureFibHasTextSegment();
        mergeAdjacentTextSegments();
    }

    function syncFillBlankToForm() {
        var segments = collectFibSegments();
        var parts = [];
        var blankInputs = [];

        segments.forEach(function (segment) {
            if (segment.type === 'blank') {
                parts.push('___');
                blankInputs.push(segment.answer || '');
            } else if (segment.value && segment.value.trim() !== '') {
                parts.push(segment.value);
            }
        });

        questionText.value = parts.join(' ').replace(/\s+/g, ' ').trim();

        form.querySelectorAll('input[name^="blank_answers"]').forEach(function (el) {
            el.remove();
        });

        blankInputs.forEach(function (answer) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'blank_answers[]';
            hidden.value = answer;
            form.appendChild(hidden);
        });
    }

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    if (addMcqBtn) {
        addMcqBtn.addEventListener('click', addMcqChoice);
    }

    if (mcqList) {
        mcqList.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action="remove-mcq"]');
            if (!btn || btn.disabled) return;
            var choices = collectMcqChoices();
            var row = btn.closest('.quiz-mcq-choice');
            var index = Array.from(mcqList.children).indexOf(row);
            choices.splice(index, 1);
            mcqFields.setAttribute('data-choices', JSON.stringify(choices));
            renderMcqChoices();
        });
    }

    if (addMatchingBtn) {
        addMatchingBtn.addEventListener('click', addMatchingRow);
    }

    if (matchingList) {
        matchingList.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action="remove-match"]');
            if (!btn || btn.disabled) return;
            var pairs = collectMatchingPairs();
            var row = btn.closest('.quiz-matching-builder-row');
            var index = Array.from(matchingList.children).indexOf(row);
            pairs.splice(index, 1);
            matchingList.setAttribute('data-pairs', JSON.stringify(pairs));
            renderMatchingRows();
        });
    }

    if (fibInsertBlankBtn) fibInsertBlankBtn.addEventListener('click', insertFibBlankAtCursor);

    if (fibBuilder) {
        fibBuilder.addEventListener('click', function (e) {
            if (e.target === fibBuilder) {
                ensureTrailingTextAfterBlanks();
                var lastText = fibBuilder.querySelector('.quiz-fib-segment--text:last-of-type .quiz-fib-text');
                if (lastText) lastText.focus();
            }
        });

        fibBuilder.addEventListener('input', function (e) {
            if (e.target.classList.contains('quiz-fib-text')) {
                autoSizeFibText(e.target);
            } else if (e.target.classList.contains('quiz-fib-answer')) {
                autoSizeFibAnswer(e.target);
            }
        });

        fibBuilder.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action="remove-fib"]');
            if (!btn) return;
            var segment = btn.closest('.quiz-fib-segment');
            if (fibBuilder.querySelectorAll('.quiz-fib-segment').length <= 1 && segment.classList.contains('quiz-fib-segment--text')) {
                segment.querySelector('input').value = '';
                autoSizeFibText(segment.querySelector('.quiz-fib-text'));
                return;
            }
            removeFibSegment(segment);
        });
    }

    typeSelect.addEventListener('change', toggleTypeFields);

    form.addEventListener('submit', function () {
        if (typeSelect.value === 'fill_blank') {
            syncFillBlankToForm();
        }
    });

    renderMcqChoices();
    renderMatchingRows();
    renderFibSegments();
    toggleTypeFields();
})();
