(function () {
    'use strict';

    var dataEl = document.getElementById('resourcesPreviewData');
    if (!dataEl) return;

    var items;
    try {
        items = JSON.parse(dataEl.textContent || '[]');
    } catch (e) {
        return;
    }

    function hideFallback(mount) {
        var wrap = mount.closest('.resources-card-preview');
        if (wrap) {
            wrap.classList.add('has-thumb');
            var fb = wrap.querySelector('.resources-card-preview-fallback');
            if (fb) fb.style.display = 'none';
        }
    }

    function deckElementToKonva(el) {
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
            var node = new Konva.Image(Object.assign({}, common, {
                width: el.width || 200, height: el.height || 200, image: img
            }));
            img.src = el.src;
            return { node: node, image: img };
        }
        return { node: null };
    }

    function renderDeckPreview(mount, deck) {
        if (typeof Konva === 'undefined' || !deck || !deck.slides || !deck.slides.length) return;

        var slide = deck.slides[0];
        var canvasW = (deck.canvas && deck.canvas.width) || 1920;
        var canvasH = (deck.canvas && deck.canvas.height) || 1080;
        var boxW = mount.clientWidth || 320;
        var boxH = mount.clientHeight || Math.round(boxW * 9 / 16);
        var scale = Math.min(boxW / canvasW, boxH / canvasH);

        var stage = new Konva.Stage({ container: mount, width: boxW, height: boxH });
        var layer = new Konva.Layer();
        stage.add(layer);

        var group = new Konva.Group({
            x: (boxW - canvasW * scale) / 2,
            y: (boxH - canvasH * scale) / 2,
            scaleX: scale,
            scaleY: scale
        });

        if (slide.background && slide.background.type === 'image' && slide.background.value) {
            var bgImg = new Image();
            bgImg.crossOrigin = 'anonymous';
            bgImg.onload = function () {
                group.add(new Konva.Image({ x: 0, y: 0, width: canvasW, height: canvasH, image: bgImg }));
                layer.batchDraw();
                hideFallback(mount);
            };
            bgImg.onerror = function () {
                group.add(new Konva.Rect({ width: canvasW, height: canvasH, fill: '#ffffff' }));
                layer.batchDraw();
                hideFallback(mount);
            };
            bgImg.src = slide.background.value;
        } else {
            group.add(new Konva.Rect({
                width: canvasW,
                height: canvasH,
                fill: (slide.background && slide.background.value) || '#ffffff'
            }));
        }

        var pending = 0;
        (slide.elements || []).forEach(function (el) {
            var built = deckElementToKonva(el);
            var node = built && built.node ? built.node : built;
            if (!node) return;
            if (built.image) {
                pending++;
                built.image.onload = built.image.onerror = function () {
                    pending--;
                    if (pending <= 0) {
                        layer.batchDraw();
                        hideFallback(mount);
                    }
                };
            }
            group.add(node);
        });

        layer.add(group);
        layer.batchDraw();
        if (pending === 0) hideFallback(mount);
    }

    function renderDocPreview(mount, html) {
        if (!html || html === '<p></p>') return;
        var inner = document.createElement('div');
        inner.className = 'resources-doc-preview-inner';
        inner.innerHTML = html;
        mount.appendChild(inner);
        hideFallback(mount);
    }

    function whenSized(mount, cb) {
        function tick() {
            if (mount.clientWidth > 0 && mount.clientHeight > 0) {
                cb();
            } else {
                requestAnimationFrame(tick);
            }
        }
        tick();
    }

    function boot() {
        items.forEach(function (item) {
            var preview = document.querySelector('.resources-card-preview[data-resource-id="' + item.id + '"] .resources-card-preview-mount');
            if (!preview) return;

            whenSized(preview, function () {
                if (item.type === 'deck' && item.deck) {
                    renderDeckPreview(preview, item.deck);
                } else if (item.type === 'doc' && item.html) {
                    renderDocPreview(preview, item.html);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        requestAnimationFrame(boot);
    }
})();
