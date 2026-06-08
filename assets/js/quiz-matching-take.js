(function () {
    'use strict';

    function initMatchingConnect(wrap) {
        var board = wrap.querySelector('.quiz-matching-board');
        var svg = wrap.querySelector('.quiz-matching-lines');
        if (!board || !svg) return;

        var selectedLeft = null;

        function hiddenInputs() {
            return Array.from(wrap.querySelectorAll('.quiz-matching-inputs input[type="hidden"]'));
        }

        function readMap() {
            var map = {};
            hiddenInputs().forEach(function (input) {
                var match = input.name.match(/_(\d+)$/);
                if (!match) return;
                var leftIdx = match[1];
                map[leftIdx] = input.value === '' ? null : input.value;
            });
            return map;
        }

        function writeMap(map) {
            hiddenInputs().forEach(function (input) {
                var match = input.name.match(/_(\d+)$/);
                if (!match) return;
                var leftIdx = match[1];
                input.value = map[leftIdx] != null && map[leftIdx] !== '' ? String(map[leftIdx]) : '';
            });
        }

        function findLeftForRight(rightIdx) {
            var map = readMap();
            var key = Object.keys(map).find(function (leftIdx) {
                return String(map[leftIdx]) === String(rightIdx);
            });
            return key != null ? key : null;
        }

        function clearSelection() {
            selectedLeft = null;
            wrap.querySelectorAll('.quiz-matching-item.is-selected').forEach(function (el) {
                el.classList.remove('is-selected');
            });
        }

        function syncVisualState() {
            var map = readMap();
            wrap.querySelectorAll('.quiz-matching-item').forEach(function (el) {
                el.classList.remove('is-connected');
            });
            Object.keys(map).forEach(function (leftIdx) {
                var rightIdx = map[leftIdx];
                if (rightIdx == null || rightIdx === '') return;
                var leftEl = wrap.querySelector('[data-side="left"][data-index="' + leftIdx + '"]');
                var rightEl = wrap.querySelector('[data-side="right"][data-index="' + rightIdx + '"]');
                if (leftEl) leftEl.classList.add('is-connected');
                if (rightEl) rightEl.classList.add('is-connected');
            });
            drawLines();
        }

        function anchorPoint(el, side) {
            var boardRect = board.getBoundingClientRect();
            var rect = el.getBoundingClientRect();
            var x = side === 'left'
                ? rect.right - boardRect.left
                : rect.left - boardRect.left;
            var y = rect.top - boardRect.top + rect.height / 2;
            return { x: x, y: y };
        }

        function drawLines() {
            var map = readMap();
            var width = board.offsetWidth;
            var height = board.offsetHeight;
            svg.setAttribute('width', String(width));
            svg.setAttribute('height', String(height));
            svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
            svg.innerHTML = '';

            Object.keys(map).forEach(function (leftIdx) {
                var rightIdx = map[leftIdx];
                if (rightIdx == null || rightIdx === '') return;

                var leftEl = wrap.querySelector('[data-side="left"][data-index="' + leftIdx + '"]');
                var rightEl = wrap.querySelector('[data-side="right"][data-index="' + rightIdx + '"]');
                if (!leftEl || !rightEl) return;

                var start = anchorPoint(leftEl, 'left');
                var end = anchorPoint(rightEl, 'right');
                var midX = (start.x + end.x) / 2;
                var d = 'M ' + start.x + ' ' + start.y + ' C ' + midX + ' ' + start.y + ', ' + midX + ' ' + end.y + ', ' + end.x + ' ' + end.y;

                var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('class', 'quiz-matching-line');
                path.setAttribute('d', d);
                svg.appendChild(path);
            });
        }

        function connect(leftIdx, rightIdx) {
            var map = readMap();
            Object.keys(map).forEach(function (key) {
                if (String(map[key]) === String(rightIdx)) {
                    map[key] = null;
                }
            });
            map[leftIdx] = String(rightIdx);
            writeMap(map);
            clearSelection();
            syncVisualState();
        }

        function disconnectLeft(leftIdx) {
            var map = readMap();
            map[leftIdx] = null;
            writeMap(map);
            clearSelection();
            syncVisualState();
        }

        function disconnectRight(rightIdx) {
            var leftIdx = findLeftForRight(rightIdx);
            if (leftIdx != null) {
                disconnectLeft(leftIdx);
            }
        }

        wrap.addEventListener('click', function (e) {
            var item = e.target.closest('.quiz-matching-item');
            if (!item || !wrap.contains(item)) return;
            e.preventDefault();

            var side = item.getAttribute('data-side');
            var index = item.getAttribute('data-index');

            if (side === 'left') {
                if (selectedLeft === index) {
                    disconnectLeft(index);
                    return;
                }
                clearSelection();
                selectedLeft = index;
                item.classList.add('is-selected');
                return;
            }

            if (side === 'right') {
                if (selectedLeft != null) {
                    connect(selectedLeft, index);
                    return;
                }
                if (findLeftForRight(index) != null) {
                    disconnectRight(index);
                }
            }
        });

        window.addEventListener('resize', drawLines);
        syncVisualState();
    }

    document.querySelectorAll('.quiz-matching-connect').forEach(initMatchingConnect);
})();
