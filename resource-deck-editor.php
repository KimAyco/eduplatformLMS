<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireContentResourceAccess();

$id = (int) ($_GET['id'] ?? 0);
$resource = ContentResourceRepository::get($id, schoolId());

if (!$resource || $resource['resource_type'] !== 'deck' || !canAccessContentResource($resource)) {
    flash('error', 'Slide deck not found.');
    redirect(contentResourceListUrl(currentUser()['role'] ?? 'teacher'));
}

$user = currentUser();
$role = $user['role'] ?? 'teacher';
$listUrl = contentResourceListUrl($role);

$pageTitle = 'Edit — ' . $resource['title'];
$editorShell = true;
$pageScripts = ['assets/js/resource-deck-editor.js'];

require __DIR__ . '/includes/layout/dashboard_header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/konva@9.3.6/konva.min.js"></script>

<div class="resource-editor-shell deck-editor-shell" id="deckEditorApp"
    data-resource-id="<?= (int) $resource['id'] ?>"
    data-api-url="<?= e(url('api/content-resources.php')) ?>"
    data-csrf="<?= e(csrfToken()) ?>"
    data-list-url="<?= e($listUrl) ?>"
    data-title="<?= e($resource['title']) ?>">

    <header class="deck-editor-topbar">
        <div class="deck-editor-topbar-left">
            <a href="<?= e($listUrl) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Resources</a>
            <input type="text" class="deck-title-input" id="deckTitleInput" value="<?= e($resource['title']) ?>" maxlength="255" aria-label="Presentation title">
            <span class="deck-save-status" id="deckSaveStatus">Saved</span>
        </div>
        <div class="deck-editor-topbar-right">
            <button type="button" class="btn btn-secondary btn-sm" id="deckPresentBtn"><i class="fa-solid fa-play"></i> Present</button>
        </div>
    </header>

    <div class="deck-editor-layout">
        <aside class="deck-slide-rail" id="deckSlideRail">
            <div class="deck-slide-rail-head">
                <span>Slides</span>
                <button type="button" class="btn btn-sm btn-secondary" id="deckAddSlideBtn" title="Add slide"><i class="fa-solid fa-plus"></i></button>
            </div>
            <div class="deck-slide-thumbs" id="deckSlideThumbs"></div>
        </aside>

        <main class="deck-canvas-area">
            <div class="deck-toolbar" id="deckToolbar">
                <button type="button" class="deck-tool-btn active" data-tool="select" title="Select (V)"><i class="fa-solid fa-arrow-pointer"></i></button>
                <button type="button" class="deck-tool-btn" data-tool="text" title="Text box (T)"><i class="fa-solid fa-font"></i></button>
                <div class="deck-shapes-menu">
                    <button type="button" class="deck-tool-btn" id="deckShapesToggle" title="Shapes"><i class="fa-solid fa-shapes"></i></button>
                    <div class="deck-shapes-panel" id="deckShapesPanel" hidden>
                        <button type="button" class="deck-shape-btn" data-shape="rect" title="Rectangle"><i class="fa-regular fa-square"></i></button>
                        <button type="button" class="deck-shape-btn" data-shape="rounded_rect" title="Rounded rectangle"><i class="fa-regular fa-square-full"></i></button>
                        <button type="button" class="deck-shape-btn" data-shape="circle" title="Circle"><i class="fa-regular fa-circle"></i></button>
                        <button type="button" class="deck-shape-btn" data-shape="ellipse" title="Ellipse"><i class="fa-solid fa-circle"></i></button>
                        <button type="button" class="deck-shape-btn" data-shape="triangle" title="Triangle"><i class="fa-solid fa-play fa-rotate-270"></i></button>
                        <button type="button" class="deck-shape-btn" data-shape="hexagon" title="Hexagon"><i class="fa-solid fa-draw-polygon"></i></button>
                        <button type="button" class="deck-shape-btn" data-shape="star" title="Star"><i class="fa-solid fa-star"></i></button>
                        <button type="button" class="deck-shape-btn" data-shape="arrow" title="Arrow"><i class="fa-solid fa-arrow-right-long"></i></button>
                        <button type="button" class="deck-shape-btn" data-shape="line" title="Line"><i class="fa-solid fa-minus"></i></button>
                    </div>
                </div>
                <button type="button" class="deck-tool-btn" data-tool="image" title="Image"><i class="fa-solid fa-image"></i></button>
                <span class="deck-toolbar-sep"></span>
                <button type="button" class="deck-tool-btn" id="deckUndoBtn" title="Undo (Ctrl+Z)"><i class="fa-solid fa-rotate-left"></i></button>
                <button type="button" class="deck-tool-btn" id="deckRedoBtn" title="Redo (Ctrl+Y)"><i class="fa-solid fa-rotate-right"></i></button>
                <span class="deck-toolbar-sep"></span>
                <select id="deckTemplateSelect" class="deck-template-select" title="Insert template slide">
                    <option value="">+ Template slide</option>
                </select>
                <input type="file" id="deckImageInput" accept="image/*" hidden>
            </div>
            <div class="deck-canvas-wrap" id="deckCanvasWrap">
                <div id="deckStageContainer"></div>
            </div>
        </main>

        <aside class="deck-properties" id="deckProperties">
            <h3>Properties</h3>
            <div id="deckPropsSlide" class="deck-props-section">
                <h4>Slide</h4>
                <label>Background
                    <input type="color" id="deckBgColor" value="#ffffff">
                </label>
                <button type="button" class="btn btn-sm btn-secondary btn-block" id="deckBgImageBtn">Background image</button>
                <input type="file" id="deckBgImageInput" accept="image/*" hidden>
            </div>
            <div id="deckPropsElement" class="deck-props-section" hidden>
                <h4>Element</h4>
                <label>Text
                    <textarea id="deckPropText" class="form-control" rows="3"></textarea>
                </label>
                <label>Font size
                    <input type="number" id="deckPropFontSize" class="form-control" min="8" max="200" value="32">
                </label>
                <label>Font
                    <select id="deckPropFont" class="form-control">
                        <option value="Inter">Inter</option>
                        <option value="Arial">Arial</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Verdana">Verdana</option>
                    </select>
                </label>
                <label>Color
                    <input type="color" id="deckPropFill" value="#111827">
                </label>
                <label id="deckPropStrokeWrap">Stroke width
                    <input type="number" id="deckPropStrokeWidth" class="form-control" min="0" max="40" value="0">
                </label>
                <label id="deckPropRadiusWrap">Corner radius
                    <input type="number" id="deckPropCornerRadius" class="form-control" min="0" max="200" value="0">
                </label>
                <label>Align
                    <select id="deckPropAlign" class="form-control">
                        <option value="left">Left</option>
                        <option value="center">Center</option>
                        <option value="right">Right</option>
                    </select>
                </label>
                <div class="deck-layer-actions">
                    <button type="button" class="btn btn-sm btn-secondary" id="deckBringFront"><i class="fa-solid fa-arrow-up"></i></button>
                    <button type="button" class="btn btn-sm btn-secondary" id="deckSendBack"><i class="fa-solid fa-arrow-down"></i></button>
                    <button type="button" class="btn btn-sm btn-secondary" id="deckDeleteEl"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
            <div id="deckPropsLayers" class="deck-props-section">
                <h4>Layers</h4>
                <ul class="deck-layers-list" id="deckLayersList"></ul>
            </div>
        </aside>
    </div>
</div>

<dialog class="deck-presenter" id="deckPresenter">
    <div class="deck-presenter-inner" id="deckPresenterInner"></div>
    <div class="deck-presenter-controls">
        <button type="button" id="deckPresentPrev"><i class="fa-solid fa-chevron-left"></i></button>
        <span id="deckPresentCounter">1 / 1</span>
        <button type="button" id="deckPresentNext"><i class="fa-solid fa-chevron-right"></i></button>
        <button type="button" id="deckPresentClose"><i class="fa-solid fa-xmark"></i></button>
    </div>
</dialog>

<?php require __DIR__ . '/includes/layout/dashboard_footer.php'; ?>
