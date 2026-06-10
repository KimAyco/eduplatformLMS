(function () {
    'use strict';

    var app = document.getElementById('deckEditorApp');
    if (!app || typeof Konva === 'undefined') return;

    var resourceId = parseInt(app.dataset.resourceId, 10);
    var apiUrl = app.dataset.apiUrl;
    var csrf = app.dataset.csrf;
    var saveStatus = document.getElementById('deckSaveStatus');

    var CANVAS_W = 1920;
    var CANVAS_H = 1080;
    var deck = { version: 1, canvas: { width: CANVAS_W, height: CANVAS_H }, slides: [] };
    var slideIndex = 0;
    var selectedIds = [];
    var currentTool = 'select';
    var undoStack = [];
    var redoStack = [];
    var saveTimer = null;
    var dirty = false;
    var templates = {};

    var stage, layer, transformer;
    var thumbStages = [];
    var stageScale = 1;

    function setSelectTool() {
        currentTool = 'select';
        document.querySelectorAll('.deck-tool-btn[data-tool]').forEach(function (b) {
            b.classList.toggle('active', b.dataset.tool === 'select');
        });
        refreshNodeDragState();
    }

    function refreshNodeDragState() {
        if (!layer) return;
        layer.getChildren().forEach(function (n) {
            if (!n.id() || n.getClassName() === 'Transformer' || n.hasName('slide-bg')) return;
            var slide = currentSlide();
            var el = slide && slide.elements.find(function (e) { return e.id === n.id(); });
            n.draggable(currentTool === 'select' && !(el && el.locked));
        });
    }

    function uid(prefix) {
        return prefix + '_' + Math.random().toString(36).slice(2, 10);
    }

    function defaultSlide() {
        return { id: uid('slide'), background: { type: 'color', value: '#ffffff' }, elements: [] };
    }

    function apiGet(action, params) {
        var q = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch(apiUrl + '?' + q.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    function apiPost(action, body) {
        return fetch(apiUrl + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }

    function apiUpload(formData) {
        formData.append('action', 'upload_asset');
        return fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf },
            body: formData
        }).then(function (r) { return r.json(); });
    }

    function apiUploadThumb(blob) {
        var fd = new FormData();
        fd.append('action', 'upload_thumbnail');
        fd.append('resource_id', String(resourceId));
        fd.append('file', blob, 'thumb.png');
        return fetch(apiUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf }, body: fd }).then(function (r) { return r.json(); });
    }

    function setSaveStatus(text, state) {
        if (!saveStatus) return;
        saveStatus.textContent = text;
        saveStatus.className = 'deck-save-status' + (state ? ' is-' + state : '');
    }

    function pushUndo() {
        undoStack.push(JSON.stringify(deck));
        if (undoStack.length > 40) undoStack.shift();
        redoStack = [];
    }

    function applyDeckSnapshot(json) {
        try {
            deck = JSON.parse(json);
            CANVAS_W = deck.canvas.width || 1920;
            CANVAS_H = deck.canvas.height || 1080;
            if (slideIndex >= deck.slides.length) slideIndex = deck.slides.length - 1;
            renderSlideRail();
            renderCanvas();
            markDirty();
        } catch (e) {}
    }

    function markDirty() {
        dirty = true;
        setSaveStatus('Unsaved changes', 'dirty');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveDeck, 3000);
    }

    function saveDeck() {
        if (!dirty) return;
        setSaveStatus('Saving…', 'saving');
        var title = document.getElementById('deckTitleInput').value.trim() || 'Untitled presentation';
        apiPost('save', { id: resourceId, title: title, content: deck }).then(function (res) {
            if (!res.ok) {
                setSaveStatus('Save failed', 'error');
                return;
            }
            dirty = false;
            setSaveStatus('Saved', 'saved');
            captureThumbnail();
        }).catch(function () {
            setSaveStatus('Save failed', 'error');
        });
    }

    function dataUrlToBlob(dataUrl) {
        var parts = dataUrl.split(',');
        if (parts.length < 2) return null;
        var mime = (parts[0].match(/:(.*?);/) || [])[1] || 'image/png';
        var binary = atob(parts[1]);
        var len = binary.length;
        var bytes = new Uint8Array(len);
        for (var i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i);
        return new Blob([bytes], { type: mime });
    }

    function captureThumbnail() {
        if (!layer) return;
        try {
            var dataUrl = layer.toDataURL({ pixelRatio: 0.25, mimeType: 'image/png' });
            var blob = dataUrlToBlob(dataUrl);
            if (blob) apiUploadThumb(blob);
        } catch (e) {
            try {
                var fallback = stage && stage.toDataURL({ pixelRatio: 0.2, mimeType: 'image/png' });
                var fbBlob = fallback ? dataUrlToBlob(fallback) : null;
                if (fbBlob) apiUploadThumb(fbBlob);
            } catch (err) {}
        }
    }

    function currentSlide() {
        return deck.slides[slideIndex];
    }

    function attachNodeEvents(node) {
        node.on('mousedown touchstart', function (e) {
            if (currentTool !== 'select') return;
            e.cancelBubble = true;
            selectNode(node.id());
        });
        node.on('dragstart', function () {
            selectNode(node.id());
        });
        node.on('dragend transformend', function () {
            normalizeNodeScale(node);
            syncSlideFromLayer();
            markDirty();
            updatePropertiesPanel();
            renderSlideRail();
        });
        node.on('dblclick dbltap', function () {
            if (node.getAttr('elementType') === 'text') {
                editTextNode(node);
            }
        });
    }

    function normalizeNodeScale(node) {
        var type = node.getAttr('elementType');
        if (type === 'rect' || type === 'image') {
            node.width(Math.max(20, node.width() * node.scaleX()));
            node.height(Math.max(20, node.height() * node.scaleY()));
            node.scale({ x: 1, y: 1 });
        } else if (type === 'circle') {
            node.radius(Math.max(5, node.radius() * node.scaleX()));
            node.scale({ x: 1, y: 1 });
        } else if (type === 'ellipse') {
            node.radiusX(Math.max(5, node.radiusX() * node.scaleX()));
            node.radiusY(Math.max(5, node.radiusY() * node.scaleY()));
            node.scale({ x: 1, y: 1 });
        } else if (type === 'triangle' || type === 'hexagon') {
            node.radius(Math.max(5, node.radius() * node.scaleX()));
            node.scale({ x: 1, y: 1 });
        } else if (type === 'star') {
            node.outerRadius(Math.max(5, node.outerRadius() * node.scaleX()));
            node.innerRadius(Math.max(3, node.innerRadius() * node.scaleX()));
            node.scale({ x: 1, y: 1 });
        }
    }

    function editTextNode(node) {
        var container = document.getElementById('deckStageContainer');
        if (!container || !stage) return;
        var textarea = document.createElement('textarea');
        var rect = container.getBoundingClientRect();
        var absPos = node.getAbsolutePosition();
        var scale = stage.scaleX() || 1;
        textarea.value = node.text();
        textarea.className = 'deck-text-edit';
        textarea.style.left = (rect.left + absPos.x * scale) + 'px';
        textarea.style.top = (rect.top + absPos.y * scale) + 'px';
        textarea.style.width = Math.max(120, node.width() * scale) + 'px';
        textarea.style.height = Math.max(40, node.height() * scale) + 'px';
        textarea.style.fontSize = (node.fontSize() * scale) + 'px';
        textarea.style.fontFamily = node.fontFamily();
        textarea.style.color = node.fill();
        textarea.style.textAlign = node.align();
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        function commit() {
            pushUndo();
            node.text(textarea.value);
            syncSlideFromLayer();
            markDirty();
            renderSlideRail();
            textarea.remove();
            layer.batchDraw();
        }

        textarea.addEventListener('blur', commit);
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                textarea.remove();
                return;
            }
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                textarea.blur();
            }
        });
    }

    function elementToKonva(el, interactive) {
        var node;
        var common = {
            id: el.id,
            x: el.x || 0,
            y: el.y || 0,
            rotation: el.rotation || 0,
            opacity: el.opacity != null ? el.opacity : 1,
            draggable: interactive && !el.locked
        };

        if (el.type === 'text') {
            node = new Konva.Text(Object.assign({}, common, {
                text: el.text || '',
                width: el.width || 400,
                fontSize: el.fontSize || 32,
                fontFamily: el.fontFamily || 'Inter',
                fill: el.fill || '#111827',
                align: el.align || 'left',
                fontStyle: el.fontStyle || 'normal'
            }));
        } else if (el.type === 'rect') {
            node = new Konva.Rect(Object.assign({}, common, {
                width: el.width || 200,
                height: el.height || 100,
                fill: el.fill || '#2563eb',
                stroke: el.strokeWidth ? (el.stroke || '#1e40af') : undefined,
                strokeWidth: el.strokeWidth || 0,
                cornerRadius: el.cornerRadius || 0
            }));
        } else if (el.type === 'circle') {
            node = new Konva.Circle(Object.assign({}, common, {
                radius: el.radius || 50,
                fill: el.fill || '#2563eb',
                stroke: el.strokeWidth ? (el.stroke || '#1e40af') : undefined,
                strokeWidth: el.strokeWidth || 0
            }));
        } else if (el.type === 'ellipse') {
            node = new Konva.Ellipse(Object.assign({}, common, {
                radiusX: el.radiusX || 120,
                radiusY: el.radiusY || 80,
                fill: el.fill || '#2563eb',
                stroke: el.strokeWidth ? (el.stroke || '#1e40af') : undefined,
                strokeWidth: el.strokeWidth || 0
            }));
        } else if (el.type === 'triangle' || el.type === 'hexagon') {
            node = new Konva.RegularPolygon(Object.assign({}, common, {
                sides: el.type === 'triangle' ? 3 : 6,
                radius: el.radius || 80,
                fill: el.fill || '#2563eb',
                stroke: el.strokeWidth ? (el.stroke || '#1e40af') : undefined,
                strokeWidth: el.strokeWidth || 0
            }));
        } else if (el.type === 'star') {
            node = new Konva.Star(Object.assign({}, common, {
                numPoints: el.numPoints || 5,
                innerRadius: el.innerRadius || 40,
                outerRadius: el.outerRadius || 80,
                fill: el.fill || '#f59e0b',
                stroke: el.strokeWidth ? (el.stroke || '#b45309') : undefined,
                strokeWidth: el.strokeWidth || 0
            }));
        } else if (el.type === 'arrow') {
            node = new Konva.Arrow(Object.assign({}, common, {
                points: el.points || [0, 0, 280, 0],
                stroke: el.stroke || '#111827',
                fill: el.fill || el.stroke || '#111827',
                strokeWidth: el.strokeWidth || 6,
                pointerLength: el.pointerLength || 24,
                pointerWidth: el.pointerWidth || 24,
                hitStrokeWidth: 24
            }));
        } else if (el.type === 'line') {
            node = new Konva.Line(Object.assign({}, common, {
                points: el.points || [0, 0, 200, 0],
                stroke: el.stroke || '#111827',
                strokeWidth: el.strokeWidth || 4,
                hitStrokeWidth: 24,
                lineCap: 'round',
                lineJoin: 'round'
            }));
        } else if (el.type === 'image' && el.src) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            node = new Konva.Image(Object.assign({}, common, {
                width: el.width || 200,
                height: el.height || 200,
                image: img
            }));
            img.src = el.src;
            img.onload = function () { if (layer) layer.batchDraw(); };
        }

        if (node) {
            node.setAttr('elementType', el.type);
            if (interactive) attachNodeEvents(node);
        }
        return node;
    }

    function konvaToElement(node) {
        var type = node.getAttr('elementType') || 'rect';
        var base = {
            id: node.id(),
            type: type,
            x: node.x(),
            y: node.y(),
            rotation: node.rotation(),
            opacity: node.opacity()
        };
        if (type === 'text') {
            return Object.assign(base, {
                width: node.width(),
                text: node.text(),
                fontSize: node.fontSize(),
                fontFamily: node.fontFamily(),
                fill: node.fill(),
                align: node.align(),
                fontStyle: node.fontStyle()
            });
        }
        if (type === 'rect') {
            return Object.assign(base, {
                width: node.width() * node.scaleX(),
                height: node.height() * node.scaleY(),
                fill: node.fill(),
                stroke: node.stroke(),
                strokeWidth: node.strokeWidth(),
                cornerRadius: node.cornerRadius()
            });
        }
        if (type === 'circle') {
            return Object.assign(base, {
                radius: node.radius() * node.scaleX(),
                fill: node.fill(),
                stroke: node.stroke(),
                strokeWidth: node.strokeWidth()
            });
        }
        if (type === 'ellipse') {
            return Object.assign(base, {
                radiusX: node.radiusX() * node.scaleX(),
                radiusY: node.radiusY() * node.scaleY(),
                fill: node.fill(),
                stroke: node.stroke(),
                strokeWidth: node.strokeWidth()
            });
        }
        if (type === 'triangle' || type === 'hexagon') {
            return Object.assign(base, {
                type: type,
                radius: node.radius() * node.scaleX(),
                fill: node.fill(),
                stroke: node.stroke(),
                strokeWidth: node.strokeWidth()
            });
        }
        if (type === 'star') {
            return Object.assign(base, {
                outerRadius: node.outerRadius() * node.scaleX(),
                innerRadius: node.innerRadius() * node.scaleX(),
                numPoints: node.numPoints(),
                fill: node.fill(),
                stroke: node.stroke(),
                strokeWidth: node.strokeWidth()
            });
        }
        if (type === 'arrow') {
            return Object.assign(base, {
                points: node.points(),
                stroke: node.stroke(),
                fill: node.fill(),
                strokeWidth: node.strokeWidth(),
                pointerLength: node.pointerLength(),
                pointerWidth: node.pointerWidth()
            });
        }
        if (type === 'line') {
            return Object.assign(base, {
                points: node.points(),
                stroke: node.stroke(),
                strokeWidth: node.strokeWidth()
            });
        }
        if (type === 'image') {
            var slide = currentSlide();
            var existing = slide.elements.find(function (e) { return e.id === node.id(); });
            return Object.assign(base, {
                width: node.width() * node.scaleX(),
                height: node.height() * node.scaleY(),
                src: existing ? existing.src : ''
            });
        }
        return base;
    }

    function syncSlideFromLayer() {
        var slide = currentSlide();
        if (!slide || !layer) return;
        var bg = layer.findOne('.slide-bg');
        if (bg && bg.getClassName() === 'Rect') {
            slide.background = { type: 'color', value: bg.fill() };
        }
        var elements = [];
        layer.getChildren(function (n) {
            if (n.getClassName() === 'Transformer' || n.hasName('slide-bg')) return false;
            if (n.id()) elements.push(konvaToElement(n));
            return false;
        });
        slide.elements = elements;
    }

    function fitStage() {
        var wrap = document.getElementById('deckCanvasWrap');
        if (!wrap || !stage) return;
        var pad = 40;
        var availW = wrap.clientWidth - pad;
        var availH = wrap.clientHeight - pad;
        var scale = Math.min(availW / CANVAS_W, availH / CANVAS_H, 1);
        stageScale = scale;
        stage.width(CANVAS_W * scale);
        stage.height(CANVAS_H * scale);
        stage.scale({ x: scale, y: scale });
        stage.batchDraw();
    }

    function renderCanvas() {
        var container = document.getElementById('deckStageContainer');
        if (!container) return;
        container.innerHTML = '';
        thumbStages.forEach(function (s) { s.destroy(); });
        thumbStages = [];

        var slide = currentSlide();
        if (!slide) return;

        stage = new Konva.Stage({ container: container, width: CANVAS_W, height: CANVAS_H });
        layer = new Konva.Layer();
        stage.add(layer);

        if (slide.background.type === 'image' && slide.background.value) {
            var bgImg = new Image();
            bgImg.crossOrigin = 'anonymous';
            bgImg.onload = function () {
                var bgNode = new Konva.Image({ x: 0, y: 0, width: CANVAS_W, height: CANVAS_H, image: bgImg, listening: false, name: 'slide-bg' });
                layer.add(bgNode);
                bgNode.moveToBottom();
                layer.batchDraw();
            };
            bgImg.src = slide.background.value;
        } else {
            layer.add(new Konva.Rect({
                x: 0, y: 0, width: CANVAS_W, height: CANVAS_H,
                fill: slide.background.value || '#ffffff',
                listening: false, name: 'slide-bg'
            }));
        }

        slide.elements.forEach(function (el) {
            var node = elementToKonva(el, true);
            if (node) layer.add(node);
        });

        transformer = new Konva.Transformer({
            rotateEnabled: true,
            anchorSize: 10,
            anchorCornerRadius: 4,
            padding: 6,
            borderStroke: '#3b82f6',
            anchorStroke: '#3b82f6',
            anchorFill: '#ffffff',
            enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'middle-left', 'middle-right', 'top-center', 'bottom-center'],
            boundBoxFunc: function (oldBox, newBox) {
                if (newBox.width < 16 || newBox.height < 16) return oldBox;
                return newBox;
            }
        });
        layer.add(transformer);

        transformer.on('transformend', function () {
            transformer.nodes().forEach(normalizeNodeScale);
            syncSlideFromLayer();
            markDirty();
            updatePropertiesPanel();
            renderSlideRail();
        });

        stage.on('click tap', function (e) {
            if (e.target === stage || e.target.hasName('slide-bg')) {
                selectNode(null);
            }
        });

        selectedIds = [];
        setSelectTool();
        fitStage();
        updatePropertiesPanel();
        updateLayersList();
        document.getElementById('deckBgColor').value = slide.background.type === 'color' ? (slide.background.value || '#ffffff') : '#ffffff';
    }

    function selectNode(id) {
        selectedIds = id ? [id] : [];
        if (!transformer || !layer) return;
        if (!id) {
            transformer.nodes([]);
        } else {
            var node = layer.findOne('#' + id);
            if (node) transformer.nodes([node]);
        }
        layer.batchDraw();
        updatePropertiesPanel();
        updateLayersList();
    }

    function renderSlideRail() {
        var rail = document.getElementById('deckSlideThumbs');
        if (!rail) return;
        rail.innerHTML = '';

        deck.slides.forEach(function (slide, idx) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'deck-slide-thumb' + (idx === slideIndex ? ' is-active' : '');
            item.dataset.index = String(idx);

            var thumbBox = document.createElement('div');
            thumbBox.className = 'deck-slide-thumb-canvas';
            thumbBox.id = 'thumb-' + slide.id;
            item.appendChild(thumbBox);

            var label = document.createElement('span');
            label.className = 'deck-slide-thumb-num';
            label.textContent = String(idx + 1);
            item.appendChild(label);

            var actions = document.createElement('div');
            actions.className = 'deck-slide-thumb-actions';
            if (deck.slides.length > 1) {
                var delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'deck-slide-del';
                delBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
                delBtn.title = 'Delete slide';
                delBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    pushUndo();
                    deck.slides.splice(idx, 1);
                    if (slideIndex >= deck.slides.length) slideIndex = deck.slides.length - 1;
                    renderSlideRail();
                    renderCanvas();
                    markDirty();
                });
                actions.appendChild(delBtn);
            }
            var dupBtn = document.createElement('button');
            dupBtn.type = 'button';
            dupBtn.className = 'deck-slide-dup';
            dupBtn.innerHTML = '<i class="fa-solid fa-copy"></i>';
            dupBtn.title = 'Duplicate slide';
            dupBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                pushUndo();
                var copy = JSON.parse(JSON.stringify(slide));
                copy.id = uid('slide');
                copy.elements = copy.elements.map(function (el) {
                    var c = Object.assign({}, el);
                    c.id = uid('el');
                    return c;
                });
                deck.slides.splice(idx + 1, 0, copy);
                slideIndex = idx + 1;
                renderSlideRail();
                renderCanvas();
                markDirty();
            });
            actions.appendChild(dupBtn);
            item.appendChild(actions);

            item.addEventListener('click', function () {
                syncSlideFromLayer();
                slideIndex = idx;
                renderSlideRail();
                renderCanvas();
            });

            rail.appendChild(item);

            requestAnimationFrame(function () {
                renderThumbPreview(slide, thumbBox);
            });
        });
    }

    function renderThumbPreview(slide, container) {
        var tw = 160;
        var th = 90;
        var scale = tw / CANVAS_W;
        var s = new Konva.Stage({ container: container, width: tw, height: th });
        var l = new Konva.Layer();
        s.add(l);
        thumbStages.push(s);

        if (slide.background.type === 'image' && slide.background.value) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                l.add(new Konva.Image({ x: 0, y: 0, width: CANVAS_W, height: CANVAS_H, scaleX: scale, scaleY: scale, image: img }));
                l.batchDraw();
            };
            img.src = slide.background.value;
        } else {
            l.add(new Konva.Rect({ x: 0, y: 0, width: tw, height: th, fill: slide.background.value || '#fff' }));
        }

        slide.elements.forEach(function (el) {
            var n = elementToKonva(el, false);
            if (n) {
                n.scale({ x: scale, y: scale });
                n.draggable(false);
                n.listening(false);
                l.add(n);
            }
        });
        l.batchDraw();
    }

    function addElement(el) {
        pushUndo();
        currentSlide().elements.push(el);
        renderCanvas();
        selectNode(el.id);
        markDirty();
        renderSlideRail();
    }

    function addTextBox() {
        addElement({
            id: uid('el'), type: 'text', x: 200, y: 200, width: 600,
            text: 'Double-click to edit', fontSize: 40, fontFamily: 'Inter', fill: '#111827', align: 'left'
        });
        setSelectTool();
    }

    function addShape(type) {
        var cx = 560;
        var cy = 420;
        var el;
        if (type === 'rect') {
            el = { id: uid('el'), type: 'rect', x: cx, y: cy, width: 320, height: 180, fill: '#2563eb', stroke: '#1e40af', strokeWidth: 2 };
        } else if (type === 'rounded_rect') {
            el = { id: uid('el'), type: 'rect', x: cx, y: cy, width: 320, height: 180, fill: '#7c3aed', stroke: '#5b21b6', strokeWidth: 2, cornerRadius: 24 };
        } else if (type === 'circle') {
            el = { id: uid('el'), type: 'circle', x: cx + 90, y: cy + 90, radius: 90, fill: '#2563eb', stroke: '#1e40af', strokeWidth: 2 };
        } else if (type === 'ellipse') {
            el = { id: uid('el'), type: 'ellipse', x: cx + 140, y: cy + 90, radiusX: 140, radiusY: 90, fill: '#0ea5e9', stroke: '#0369a1', strokeWidth: 2 };
        } else if (type === 'triangle') {
            el = { id: uid('el'), type: 'triangle', x: cx + 80, y: cy + 90, radius: 100, fill: '#10b981', stroke: '#047857', strokeWidth: 2 };
        } else if (type === 'hexagon') {
            el = { id: uid('el'), type: 'hexagon', x: cx + 90, y: cy + 90, radius: 100, fill: '#8b5cf6', stroke: '#6d28d9', strokeWidth: 2 };
        } else if (type === 'star') {
            el = { id: uid('el'), type: 'star', x: cx + 90, y: cy + 90, outerRadius: 100, innerRadius: 48, numPoints: 5, fill: '#f59e0b', stroke: '#b45309', strokeWidth: 2 };
        } else if (type === 'arrow') {
            el = { id: uid('el'), type: 'arrow', x: cx, y: cy + 90, points: [0, 0, 360, 0], stroke: '#111827', fill: '#111827', strokeWidth: 8, pointerLength: 28, pointerWidth: 28 };
        } else if (type === 'line') {
            el = { id: uid('el'), type: 'line', x: cx, y: cy + 90, points: [0, 0, 400, 0], stroke: '#111827', strokeWidth: 6 };
        } else {
            return;
        }
        addElement(el);
        setSelectTool();
    }

    function uploadImage(file, callback) {
        var fd = new FormData();
        fd.append('resource_id', String(resourceId));
        fd.append('file', file);
        apiUpload(fd).then(function (res) {
            if (res.ok && res.url) callback(res.url);
        });
    }

    function updatePropertiesPanel() {
        var elPanel = document.getElementById('deckPropsElement');
        if (!elPanel) return;
        if (!selectedIds.length || !layer) {
            elPanel.hidden = true;
            return;
        }
        var node = layer.findOne('#' + selectedIds[0]);
        if (!node) { elPanel.hidden = true; return; }
        elPanel.hidden = false;
        var type = node.getAttr('elementType');
        document.getElementById('deckPropText').parentElement.style.display = type === 'text' ? '' : 'none';
        document.getElementById('deckPropFontSize').parentElement.style.display = type === 'text' ? '' : 'none';
        document.getElementById('deckPropFont').parentElement.style.display = type === 'text' ? '' : 'none';
        document.getElementById('deckPropAlign').parentElement.style.display = type === 'text' ? '' : 'none';
        if (type === 'text') {
            document.getElementById('deckPropText').value = node.text();
            document.getElementById('deckPropFontSize').value = node.fontSize();
            document.getElementById('deckPropFont').value = node.fontFamily();
            document.getElementById('deckPropAlign').value = node.align();
            document.getElementById('deckPropFill').value = rgbToHex(node.fill());
        } else if (type === 'line' || type === 'arrow') {
            document.getElementById('deckPropFill').value = rgbToHex(node.stroke());
        } else if (node.fill && node.fill()) {
            document.getElementById('deckPropFill').value = rgbToHex(node.fill());
        }

        var strokeWrap = document.getElementById('deckPropStrokeWrap');
        var radiusWrap = document.getElementById('deckPropRadiusWrap');
        var showStroke = ['line', 'arrow', 'rect', 'circle', 'ellipse', 'triangle', 'hexagon', 'star'].indexOf(type) >= 0;
        var showRadius = type === 'rect';
        if (strokeWrap) strokeWrap.style.display = showStroke ? '' : 'none';
        if (radiusWrap) radiusWrap.style.display = showRadius ? '' : 'none';
        if (showStroke && document.getElementById('deckPropStrokeWidth')) {
            document.getElementById('deckPropStrokeWidth').value = node.strokeWidth ? node.strokeWidth() : 0;
        }
        if (showRadius && document.getElementById('deckPropCornerRadius')) {
            document.getElementById('deckPropCornerRadius').value = node.cornerRadius ? node.cornerRadius() : 0;
        }
    }

    function rgbToHex(color) {
        if (!color) return '#111827';
        if (color.indexOf('#') === 0) return color;
        var m = color.match(/\d+/g);
        if (!m || m.length < 3) return '#111827';
        return '#' + m.slice(0, 3).map(function (x) { return ('0' + parseInt(x, 10).toString(16)).slice(-2); }).join('');
    }

    function updateLayersList() {
        var list = document.getElementById('deckLayersList');
        if (!list) return;
        list.innerHTML = '';
        var slide = currentSlide();
        if (!slide) return;
        slide.elements.slice().reverse().forEach(function (el) {
            var li = document.createElement('li');
            li.className = selectedIds.indexOf(el.id) >= 0 ? 'is-active' : '';
            li.textContent = el.type + (el.text ? ': ' + el.text.slice(0, 24) : '');
            li.addEventListener('click', function () { selectNode(el.id); });
            list.appendChild(li);
        });
    }

    function applyTemplate(key) {
        var tpl = templates[key];
        if (!tpl || !tpl.slides || !tpl.slides[0]) return;
        pushUndo();
        var raw = tpl.slides[0];
        var slide = {
            id: uid('slide'),
            background: raw.background || { type: 'color', value: '#ffffff' },
            elements: (raw.elements || []).map(function (el) {
                var c = Object.assign({}, el);
                c.id = uid('el');
                return c;
            })
        };
        deck.slides.splice(slideIndex + 1, 0, slide);
        slideIndex++;
        renderSlideRail();
        renderCanvas();
        markDirty();
    }

    function initToolbar() {
        document.querySelectorAll('.deck-tool-btn[data-tool]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentTool = btn.dataset.tool;
                document.querySelectorAll('.deck-tool-btn[data-tool]').forEach(function (b) { b.classList.toggle('active', b === btn); });
                var shapesPanel = document.getElementById('deckShapesPanel');
                if (shapesPanel) shapesPanel.hidden = true;
                if (currentTool === 'text') {
                    addTextBox();
                    setSelectTool();
                } else if (currentTool === 'image') {
                    document.getElementById('deckImageInput').click();
                    setSelectTool();
                } else {
                    refreshNodeDragState();
                }
            });
        });

        var shapesToggle = document.getElementById('deckShapesToggle');
        var shapesPanel = document.getElementById('deckShapesPanel');
        if (shapesToggle && shapesPanel) {
            shapesToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                shapesPanel.hidden = !shapesPanel.hidden;
            });
            document.addEventListener('click', function (e) {
                if (!shapesPanel.hidden && !shapesPanel.contains(e.target) && e.target !== shapesToggle) {
                    shapesPanel.hidden = true;
                }
            });
            shapesPanel.querySelectorAll('.deck-shape-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    addShape(btn.dataset.shape);
                    shapesPanel.hidden = true;
                });
            });
        }

        document.getElementById('deckAddSlideBtn').addEventListener('click', function () {
            pushUndo();
            deck.slides.splice(slideIndex + 1, 0, defaultSlide());
            slideIndex++;
            renderSlideRail();
            renderCanvas();
            markDirty();
        });

        document.getElementById('deckUndoBtn').addEventListener('click', function () {
            if (!undoStack.length) return;
            redoStack.push(JSON.stringify(deck));
            applyDeckSnapshot(undoStack.pop());
        });

        document.getElementById('deckRedoBtn').addEventListener('click', function () {
            if (!redoStack.length) return;
            undoStack.push(JSON.stringify(deck));
            applyDeckSnapshot(redoStack.pop());
        });

        document.getElementById('deckImageInput').addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (!file) return;
            uploadImage(file, function (url) {
                addElement({ id: uid('el'), type: 'image', x: 400, y: 250, width: 500, height: 400, src: url });
                setSelectTool();
            });
            e.target.value = '';
        });

        document.getElementById('deckBgColor').addEventListener('input', function (e) {
            currentSlide().background = { type: 'color', value: e.target.value };
            renderCanvas();
            markDirty();
            renderSlideRail();
        });

        document.getElementById('deckBgImageBtn').addEventListener('click', function () {
            document.getElementById('deckBgImageInput').click();
        });

        document.getElementById('deckBgImageInput').addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (!file) return;
            uploadImage(file, function (url) {
                pushUndo();
                currentSlide().background = { type: 'image', value: url };
                renderCanvas();
                markDirty();
                renderSlideRail();
            });
            e.target.value = '';
        });

        function applyPropChange() {
            if (!selectedIds.length || !layer) return;
            var node = layer.findOne('#' + selectedIds[0]);
            if (!node) return;
            var type = node.getAttr('elementType');
            var color = document.getElementById('deckPropFill').value;
            if (type === 'text') {
                node.text(document.getElementById('deckPropText').value);
                node.fontSize(parseFloat(document.getElementById('deckPropFontSize').value) || 32);
                node.fontFamily(document.getElementById('deckPropFont').value);
                node.align(document.getElementById('deckPropAlign').value);
                node.fill(color);
            } else if (type === 'line' || type === 'arrow') {
                node.stroke(color);
                if (type === 'arrow') node.fill(color);
            } else if (node.fill) {
                node.fill(color);
            }
            var sw = parseFloat(document.getElementById('deckPropStrokeWidth').value) || 0;
            if (node.strokeWidth) {
                node.strokeWidth(sw);
                if (sw > 0 && !node.stroke()) {
                    node.stroke(type === 'line' || type === 'arrow' ? color : '#1e40af');
                }
            }
            if (type === 'rect' && node.cornerRadius) {
                node.cornerRadius(parseFloat(document.getElementById('deckPropCornerRadius').value) || 0);
            }
            syncSlideFromLayer();
            layer.batchDraw();
            markDirty();
            renderSlideRail();
        }

        ['deckPropText', 'deckPropFontSize', 'deckPropFont', 'deckPropAlign', 'deckPropFill', 'deckPropStrokeWidth', 'deckPropCornerRadius'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', applyPropChange);
        });

        document.getElementById('deckBringFront').addEventListener('click', function () {
            if (!selectedIds.length) return;
            var node = layer.findOne('#' + selectedIds[0]);
            if (node) { node.moveToTop(); transformer.moveToTop(); syncSlideFromLayer(); markDirty(); renderSlideRail(); }
        });

        document.getElementById('deckSendBack').addEventListener('click', function () {
            if (!selectedIds.length) return;
            var node = layer.findOne('#' + selectedIds[0]);
            var bg = layer.findOne('.slide-bg');
            if (node) { node.moveDown(); if (bg) bg.moveToBottom(); syncSlideFromLayer(); markDirty(); renderSlideRail(); }
        });

        document.getElementById('deckDeleteEl').addEventListener('click', function () {
            if (!selectedIds.length) return;
            pushUndo();
            currentSlide().elements = currentSlide().elements.filter(function (e) { return e.id !== selectedIds[0]; });
            selectedIds = [];
            renderCanvas();
            markDirty();
            renderSlideRail();
        });

        document.getElementById('deckTitleInput').addEventListener('input', markDirty);

        document.getElementById('deckTemplateSelect').addEventListener('change', function (e) {
            if (e.target.value) {
                applyTemplate(e.target.value);
                e.target.value = '';
            }
        });

        window.addEventListener('resize', fitStage);

        document.addEventListener('keydown', function (e) {
            if (e.target.matches('input, textarea, select')) return;
            if (e.key === 'Delete' || e.key === 'Backspace') {
                if (selectedIds.length) {
                    e.preventDefault();
                    document.getElementById('deckDeleteEl').click();
                }
            }
            if (e.key === 'v' || e.key === 'V') { setSelectTool(); }
            if (e.key === 't' || e.key === 'T') { addTextBox(); setSelectTool(); }
            if (e.ctrlKey && e.key === 'z') { e.preventDefault(); document.getElementById('deckUndoBtn').click(); }
            if (e.ctrlKey && e.key === 'y') { e.preventDefault(); document.getElementById('deckRedoBtn').click(); }
            if (selectedIds.length && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(e.key) >= 0) {
                e.preventDefault();
                var node = layer.findOne('#' + selectedIds[0]);
                if (!node) return;
                var step = e.shiftKey ? 10 : 1;
                if (e.key === 'ArrowUp') node.y(node.y() - step);
                if (e.key === 'ArrowDown') node.y(node.y() + step);
                if (e.key === 'ArrowLeft') node.x(node.x() - step);
                if (e.key === 'ArrowRight') node.x(node.x() + step);
                syncSlideFromLayer();
                layer.batchDraw();
                markDirty();
            }
        });
    }

    function initPresenter() {
        var dialog = document.getElementById('deckPresenter');
        var inner = document.getElementById('deckPresenterInner');
        var presentIdx = 0;
        var pStage, pLayer;

        function showPresentSlide(idx) {
            presentIdx = idx;
            inner.innerHTML = '';
            if (pStage) pStage.destroy();
            var slide = deck.slides[presentIdx];
            if (!slide) return;

            var wrapW = inner.clientWidth;
            var wrapH = inner.clientHeight;
            var scale = Math.min(wrapW / CANVAS_W, wrapH / CANVAS_H);

            pStage = new Konva.Stage({ container: inner, width: wrapW, height: wrapH });
            pLayer = new Konva.Layer();
            pStage.add(pLayer);

            var group = new Konva.Group({ x: (wrapW - CANVAS_W * scale) / 2, y: (wrapH - CANVAS_H * scale) / 2, scaleX: scale, scaleY: scale });

            if (slide.background.type === 'image' && slide.background.value) {
                var img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = function () {
                    group.add(new Konva.Image({ width: CANVAS_W, height: CANVAS_H, image: img }));
                    pLayer.batchDraw();
                };
                img.src = slide.background.value;
            } else {
                group.add(new Konva.Rect({ width: CANVAS_W, height: CANVAS_H, fill: slide.background.value || '#fff' }));
            }

            slide.elements.forEach(function (el) {
                var n = elementToKonva(el, false);
                if (n) { n.draggable(false); n.listening(false); group.add(n); }
            });

            pLayer.add(group);
            pLayer.batchDraw();
            document.getElementById('deckPresentCounter').textContent = (presentIdx + 1) + ' / ' + deck.slides.length;
        }

        document.getElementById('deckPresentBtn').addEventListener('click', function () {
            syncSlideFromLayer();
            dialog.showModal();
            if (dialog.requestFullscreen) dialog.requestFullscreen().catch(function () {});
            showPresentSlide(slideIndex);
        });

        document.getElementById('deckPresentPrev').addEventListener('click', function () {
            if (presentIdx > 0) showPresentSlide(presentIdx - 1);
        });

        document.getElementById('deckPresentNext').addEventListener('click', function () {
            if (presentIdx < deck.slides.length - 1) showPresentSlide(presentIdx + 1);
        });

        document.getElementById('deckPresentClose').addEventListener('click', function () {
            if (document.fullscreenElement) document.exitFullscreen().catch(function () {});
            dialog.close();
        });

        dialog.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight' || e.key === ' ') { e.preventDefault(); document.getElementById('deckPresentNext').click(); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); document.getElementById('deckPresentPrev').click(); }
            if (e.key === 'Escape') document.getElementById('deckPresentClose').click();
        });
    }

    function loadTemplates() {
        apiGet('templates').then(function (res) {
            if (!res.ok) return;
            templates = res.templates || {};
            var sel = document.getElementById('deckTemplateSelect');
            Object.keys(templates).forEach(function (key) {
                var opt = document.createElement('option');
                opt.value = key;
                opt.textContent = templates[key].label || key;
                sel.appendChild(opt);
            });
        });
    }

    function init() {
        initToolbar();
        initPresenter();
        loadTemplates();

        apiGet('get', { id: resourceId }).then(function (res) {
            if (!res.ok || !res.resource) return;
            deck = res.resource.content;
            if (!deck.slides || !deck.slides.length) {
                deck.slides = [defaultSlide()];
            }
            CANVAS_W = deck.canvas.width || 1920;
            CANVAS_H = deck.canvas.height || 1080;
            renderSlideRail();
            renderCanvas();
        });

        window.addEventListener('beforeunload', function (e) {
            if (dirty) {
                saveDeck();
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    init();
})();
