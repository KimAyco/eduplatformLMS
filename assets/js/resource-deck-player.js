(function () {
    'use strict';

    var container = document.getElementById('deckPlayer');
    if (!container || typeof Konva === 'undefined') return;

    var deckData;
    try {
        deckData = JSON.parse(container.dataset.deck || '{}');
    } catch (e) {
        return;
    }

    var slides = deckData.slides || [];
    if (!slides.length) return;

    var CANVAS_W = (deckData.canvas && deckData.canvas.width) || 1920;
    var CANVAS_H = (deckData.canvas && deckData.canvas.height) || 1080;
    var idx = 0;
    var stage, layer;

    function elementToKonva(el) {
        var common = { x: el.x || 0, y: el.y || 0, rotation: el.rotation || 0, opacity: el.opacity != null ? el.opacity : 1 };
        if (el.type === 'text') {
            return new Konva.Text(Object.assign({}, common, {
                text: el.text || '', width: el.width || 400, fontSize: el.fontSize || 32,
                fontFamily: el.fontFamily || 'Inter', fill: el.fill || '#111827',
                align: el.align || 'left', fontStyle: el.fontStyle || 'normal'
            }));
        }
        if (el.type === 'rect') {
            return new Konva.Rect(Object.assign({}, common, {
                width: el.width || 200, height: el.height || 100, fill: el.fill || '#2563eb',
                stroke: el.strokeWidth ? (el.stroke || '#1e40af') : undefined,
                strokeWidth: el.strokeWidth || 0, cornerRadius: el.cornerRadius || 0
            }));
        }
        if (el.type === 'circle') {
            return new Konva.Circle(Object.assign({}, common, {
                radius: el.radius || 50, fill: el.fill || '#2563eb',
                stroke: el.strokeWidth ? (el.stroke || '#1e40af') : undefined,
                strokeWidth: el.strokeWidth || 0
            }));
        }
        if (el.type === 'ellipse') {
            return new Konva.Ellipse(Object.assign({}, common, {
                radiusX: el.radiusX || 120, radiusY: el.radiusY || 80, fill: el.fill || '#2563eb',
                stroke: el.strokeWidth ? (el.stroke || '#1e40af') : undefined,
                strokeWidth: el.strokeWidth || 0
            }));
        }
        if (el.type === 'triangle' || el.type === 'hexagon') {
            return new Konva.RegularPolygon(Object.assign({}, common, {
                sides: el.type === 'triangle' ? 3 : 6, radius: el.radius || 80, fill: el.fill || '#2563eb',
                stroke: el.strokeWidth ? (el.stroke || '#1e40af') : undefined,
                strokeWidth: el.strokeWidth || 0
            }));
        }
        if (el.type === 'star') {
            return new Konva.Star(Object.assign({}, common, {
                numPoints: el.numPoints || 5, innerRadius: el.innerRadius || 40, outerRadius: el.outerRadius || 80,
                fill: el.fill || '#f59e0b',
                stroke: el.strokeWidth ? (el.stroke || '#b45309') : undefined,
                strokeWidth: el.strokeWidth || 0
            }));
        }
        if (el.type === 'arrow') {
            return new Konva.Arrow(Object.assign({}, common, {
                points: el.points || [0, 0, 280, 0], stroke: el.stroke || '#111827',
                fill: el.fill || el.stroke || '#111827', strokeWidth: el.strokeWidth || 6,
                pointerLength: el.pointerLength || 24, pointerWidth: el.pointerWidth || 24
            }));
        }
        if (el.type === 'line') {
            return new Konva.Line(Object.assign({}, common, {
                points: el.points || [0, 0, 200, 0], stroke: el.stroke || '#111827',
                strokeWidth: el.strokeWidth || 4, lineCap: 'round', lineJoin: 'round'
            }));
        }
        if (el.type === 'image' && el.src) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            var node = new Konva.Image(Object.assign({}, common, { width: el.width || 200, height: el.height || 200, image: img }));
            img.src = el.src;
            img.onload = function () { layer.batchDraw(); };
            return node;
        }
        return null;
    }

    function fitStage() {
        var wrap = container.querySelector('.deck-player-canvas');
        if (!wrap || !stage) return;
        var pad = 16;
        var scale = Math.min((wrap.clientWidth - pad) / CANVAS_W, (wrap.clientHeight - pad) / CANVAS_H, 1);
        stage.width(CANVAS_W * scale);
        stage.height(CANVAS_H * scale);
        stage.scale({ x: scale, y: scale });
        stage.batchDraw();
    }

    function renderSlide(i) {
        idx = i;
        var mount = container.querySelector('.deck-player-canvas');
        if (!mount) return;
        mount.innerHTML = '';

        var slide = slides[idx];
        stage = new Konva.Stage({ container: mount, width: CANVAS_W, height: CANVAS_H });
        layer = new Konva.Layer();
        stage.add(layer);

        if (slide.background && slide.background.type === 'image' && slide.background.value) {
            var bgImg = new Image();
            bgImg.crossOrigin = 'anonymous';
            bgImg.onload = function () {
                layer.add(new Konva.Image({ x: 0, y: 0, width: CANVAS_W, height: CANVAS_H, image: bgImg }));
                layer.batchDraw();
            };
            bgImg.src = slide.background.value;
        } else {
            layer.add(new Konva.Rect({
                x: 0, y: 0, width: CANVAS_W, height: CANVAS_H,
                fill: (slide.background && slide.background.value) || '#ffffff'
            }));
        }

        (slide.elements || []).forEach(function (el) {
            var n = elementToKonva(el);
            if (n) layer.add(n);
        });

        layer.batchDraw();
        fitStage();

        var counter = container.querySelector('.deck-player-counter');
        if (counter) counter.textContent = (idx + 1) + ' / ' + slides.length;

        var prevBtn = container.querySelector('[data-deck-prev]');
        var nextBtn = container.querySelector('[data-deck-next]');
        if (prevBtn) prevBtn.disabled = idx <= 0;
        if (nextBtn) nextBtn.disabled = idx >= slides.length - 1;
    }

    container.querySelector('[data-deck-prev]') && container.querySelector('[data-deck-prev]').addEventListener('click', function () {
        if (idx > 0) renderSlide(idx - 1);
    });

    container.querySelector('[data-deck-next]') && container.querySelector('[data-deck-next]').addEventListener('click', function () {
        if (idx < slides.length - 1) renderSlide(idx + 1);
    });

    container.querySelector('[data-deck-fullscreen]') && container.querySelector('[data-deck-fullscreen]').addEventListener('click', function () {
        var el = container;
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(function () {});
        } else if (el.requestFullscreen) {
            el.requestFullscreen().catch(function () {});
        }
    });

    container.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') container.querySelector('[data-deck-next]').click();
        if (e.key === 'ArrowLeft') container.querySelector('[data-deck-prev]').click();
    });

    window.addEventListener('resize', fitStage);
    renderSlide(0);
})();
