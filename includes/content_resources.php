<?php

function canManageContentResources(): bool
{
    $user = currentUser();
    return $user && in_array($user['role'] ?? '', ['teacher', 'school_admin'], true);
}

function requireContentResourceAccess(): void
{
    requireLogin();
    requireSchoolActive();
    if (!canManageContentResources()) {
        http_response_code(403);
        die('Access denied.');
    }
}

function contentResourceTypeLabel(string $type): string
{
    return match (normalizeContentResourceType($type)) {
        'doc' => 'Document',
        default => 'Slide deck',
    };
}

function normalizeContentResourceType(string $type): string
{
    $type = strtolower(trim($type));
    return in_array($type, ['deck', 'doc'], true) ? $type : 'deck';
}

function contentResourceMaterialType(string $resourceType): string
{
    return normalizeContentResourceType($resourceType) === 'doc' ? 'doc' : 'deck';
}

function contentResourceEditorUrl(int $id, string $resourceType): string
{
    $type = normalizeContentResourceType($resourceType);
    $page = $type === 'doc' ? 'resource-doc-editor.php' : 'resource-deck-editor.php';
    return url($page . '?id=' . $id);
}

function contentResourceListUrl(string $role): string
{
    return url(($role === 'school_admin' ? 'school' : 'teacher') . '/resources.php');
}

function defaultDeckContent(): string
{
    return json_encode([
        'version' => 1,
        'canvas' => ['width' => 1920, 'height' => 1080],
        'slides' => [
            [
                'id' => 'slide_' . bin2hex(random_bytes(4)),
                'background' => ['type' => 'color', 'value' => '#ffffff'],
                'elements' => [],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * @return array<string, mixed>
 */
function validateDeckContent(string $json): array
{
    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Invalid deck data.');
    }

    $slides = $data['slides'] ?? [];
    if (!is_array($slides) || $slides === []) {
        throw new InvalidArgumentException('Deck must contain at least one slide.');
    }
    if (count($slides) > 50) {
        throw new InvalidArgumentException('Deck cannot exceed 50 slides.');
    }

    $allowedTypes = ['text', 'rect', 'circle', 'ellipse', 'triangle', 'hexagon', 'star', 'arrow', 'line', 'image'];
    $sanitizedSlides = [];

    foreach ($slides as $slide) {
        if (!is_array($slide)) {
            continue;
        }
        $elements = [];
        foreach (($slide['elements'] ?? []) as $el) {
            if (!is_array($el) || empty($el['type']) || !in_array($el['type'], $allowedTypes, true)) {
                continue;
            }
            if (count($elements) >= 100) {
                break;
            }
            $el = sanitizeDeckElement($el);
            $elements[] = $el;
        }

        $bg = $slide['background'] ?? ['type' => 'color', 'value' => '#ffffff'];
        if (!is_array($bg)) {
            $bg = ['type' => 'color', 'value' => '#ffffff'];
        }
        $bgType = ($bg['type'] ?? 'color') === 'image' ? 'image' : 'color';
        $bgValue = (string) ($bg['value'] ?? '#ffffff');
        if ($bgType === 'image') {
            $bgValue = sanitizeDeckAssetUrl($bgValue);
        } elseif (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $bgValue)) {
            $bgValue = '#ffffff';
        }

        $sanitizedSlides[] = [
            'id' => (string) ($slide['id'] ?? 'slide_' . bin2hex(random_bytes(4))),
            'background' => ['type' => $bgType, 'value' => $bgValue],
            'elements' => $elements,
        ];
    }

    if ($sanitizedSlides === []) {
        throw new InvalidArgumentException('Deck must contain at least one valid slide.');
    }

    $canvas = $data['canvas'] ?? ['width' => 1920, 'height' => 1080];
    $width = max(800, min(3840, (int) ($canvas['width'] ?? 1920)));
    $height = max(450, min(2160, (int) ($canvas['height'] ?? 1080)));

    return [
        'version' => 1,
        'canvas' => ['width' => $width, 'height' => $height],
        'slides' => $sanitizedSlides,
    ];
}

/**
 * @param array<string, mixed> $el
 * @return array<string, mixed>
 */
function sanitizeDeckElement(array $el): array
{
    $base = [
        'id' => (string) ($el['id'] ?? 'el_' . bin2hex(random_bytes(4))),
        'type' => (string) $el['type'],
        'x' => (float) ($el['x'] ?? 0),
        'y' => (float) ($el['y'] ?? 0),
        'rotation' => (float) ($el['rotation'] ?? 0),
        'opacity' => max(0, min(1, (float) ($el['opacity'] ?? 1))),
        'locked' => !empty($el['locked']),
    ];

    return match ($el['type']) {
        'text' => array_merge($base, [
            'width' => max(40, (float) ($el['width'] ?? 400)),
            'text' => mb_substr((string) ($el['text'] ?? ''), 0, 5000),
            'fontSize' => max(8, min(200, (float) ($el['fontSize'] ?? 32))),
            'fontFamily' => sanitizeDeckFont((string) ($el['fontFamily'] ?? 'Inter')),
            'fill' => sanitizeDeckColor((string) ($el['fill'] ?? '#111827')),
            'align' => in_array($el['align'] ?? 'left', ['left', 'center', 'right'], true) ? $el['align'] : 'left',
            'fontStyle' => in_array($el['fontStyle'] ?? 'normal', ['normal', 'bold', 'italic', 'bold italic'], true) ? $el['fontStyle'] : 'normal',
        ]),
        'rect' => array_merge($base, [
            'width' => max(1, (float) ($el['width'] ?? 200)),
            'height' => max(1, (float) ($el['height'] ?? 100)),
            'fill' => sanitizeDeckColor((string) ($el['fill'] ?? '#2563eb')),
            'stroke' => sanitizeDeckColor((string) ($el['stroke'] ?? '#1e40af')),
            'strokeWidth' => max(0, min(40, (float) ($el['strokeWidth'] ?? 0))),
            'cornerRadius' => max(0, (float) ($el['cornerRadius'] ?? 0)),
        ]),
        'circle' => array_merge($base, [
            'radius' => max(1, (float) ($el['radius'] ?? 50)),
            'fill' => sanitizeDeckColor((string) ($el['fill'] ?? '#2563eb')),
            'stroke' => sanitizeDeckColor((string) ($el['stroke'] ?? '#1e40af')),
            'strokeWidth' => max(0, min(40, (float) ($el['strokeWidth'] ?? 0))),
        ]),
        'ellipse' => array_merge($base, [
            'radiusX' => max(1, (float) ($el['radiusX'] ?? 120)),
            'radiusY' => max(1, (float) ($el['radiusY'] ?? 80)),
            'fill' => sanitizeDeckColor((string) ($el['fill'] ?? '#2563eb')),
            'stroke' => sanitizeDeckColor((string) ($el['stroke'] ?? '#1e40af')),
            'strokeWidth' => max(0, min(40, (float) ($el['strokeWidth'] ?? 0))),
        ]),
        'triangle', 'hexagon' => array_merge($base, [
            'radius' => max(1, (float) ($el['radius'] ?? 80)),
            'sides' => $el['type'] === 'triangle' ? 3 : 6,
            'fill' => sanitizeDeckColor((string) ($el['fill'] ?? '#2563eb')),
            'stroke' => sanitizeDeckColor((string) ($el['stroke'] ?? '#1e40af')),
            'strokeWidth' => max(0, min(40, (float) ($el['strokeWidth'] ?? 0))),
        ]),
        'star' => array_merge($base, [
            'outerRadius' => max(1, (float) ($el['outerRadius'] ?? 80)),
            'innerRadius' => max(1, (float) ($el['innerRadius'] ?? 40)),
            'numPoints' => max(3, min(12, (int) ($el['numPoints'] ?? 5))),
            'fill' => sanitizeDeckColor((string) ($el['fill'] ?? '#f59e0b')),
            'stroke' => sanitizeDeckColor((string) ($el['stroke'] ?? '#b45309')),
            'strokeWidth' => max(0, min(40, (float) ($el['strokeWidth'] ?? 0))),
        ]),
        'arrow' => array_merge($base, [
            'points' => sanitizeDeckPoints($el['points'] ?? [0, 0, 280, 0]),
            'stroke' => sanitizeDeckColor((string) ($el['stroke'] ?? '#111827')),
            'fill' => sanitizeDeckColor((string) ($el['fill'] ?? '#111827')),
            'strokeWidth' => max(1, (float) ($el['strokeWidth'] ?? 6)),
            'pointerLength' => max(8, (float) ($el['pointerLength'] ?? 24)),
            'pointerWidth' => max(8, (float) ($el['pointerWidth'] ?? 24)),
        ]),
        'line' => array_merge($base, [
            'points' => sanitizeDeckPoints($el['points'] ?? [0, 0, 200, 0]),
            'stroke' => sanitizeDeckColor((string) ($el['stroke'] ?? '#111827')),
            'strokeWidth' => max(1, (float) ($el['strokeWidth'] ?? 4)),
        ]),
        'image' => array_merge($base, [
            'width' => max(10, (float) ($el['width'] ?? 200)),
            'height' => max(10, (float) ($el['height'] ?? 200)),
            'src' => sanitizeDeckAssetUrl((string) ($el['src'] ?? '')),
        ]),
        default => $base,
    };
}

function sanitizeDeckColor(string $color): string
{
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        return $color;
    }
    return '#111827';
}

function sanitizeDeckFont(string $font): string
{
    $allowed = ['Inter', 'Arial', 'Georgia', 'Times New Roman', 'Courier New', 'Verdana', 'Tahoma'];
    return in_array($font, $allowed, true) ? $font : 'Inter';
}

/**
 * @param mixed $points
 * @return list<float>
 */
function sanitizeDeckPoints(mixed $points): array
{
    if (!is_array($points)) {
        return [0, 0, 200, 0];
    }
    $out = [];
    foreach (array_slice($points, 0, 20) as $p) {
        $out[] = (float) $p;
    }
    return count($out) >= 4 ? $out : [0, 0, 200, 0];
}

function sanitizeDeckAssetUrl(string $src): string
{
    $src = trim($src);
    if ($src === '') {
        return '';
    }

    $schoolId = schoolId();
    $prefix = $schoolId . '/resources/';

    if (str_contains($src, 'download.php')) {
        if (preg_match('/file=([^&]+)/', $src, $m)) {
            $path = urldecode($m[1]);
            if (str_starts_with($path, $prefix)) {
                return downloadUrl($path, 'resource_asset');
            }
        }
        return '';
    }

    if (str_starts_with($src, $prefix)) {
        return downloadUrl($src, 'resource_asset');
    }

    return '';
}

/**
 * @param array<string, mixed> $resource
 */
function canAccessContentResource(array $resource): bool
{
    $user = currentUser();
    if (!$user || (int) ($resource['school_id'] ?? 0) !== schoolId()) {
        return false;
    }
    if (($user['role'] ?? '') === 'school_admin') {
        return true;
    }
    return ($user['role'] ?? '') === 'teacher' && (int) ($resource['created_by'] ?? 0) === (int) $user['id'];
}

function contentResourceJsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function deckTemplates(): array
{
    return [
        'title' => [
            'label' => 'Title slide',
            'slides' => [[
                'background' => ['type' => 'color', 'value' => '#1e3a5f'],
                'elements' => [
                    ['type' => 'text', 'x' => 120, 'y' => 380, 'width' => 1680, 'text' => 'Presentation Title', 'fontSize' => 72, 'fill' => '#ffffff', 'align' => 'center', 'fontStyle' => 'bold'],
                    ['type' => 'text', 'x' => 120, 'y' => 520, 'width' => 1680, 'text' => 'Subtitle goes here', 'fontSize' => 36, 'fill' => '#94a3b8', 'align' => 'center'],
                ],
            ]],
        ],
        'bullets' => [
            'label' => 'Bullet points',
            'slides' => [[
                'background' => ['type' => 'color', 'value' => '#ffffff'],
                'elements' => [
                    ['type' => 'text', 'x' => 100, 'y' => 80, 'width' => 1720, 'text' => 'Key Points', 'fontSize' => 48, 'fill' => '#111827', 'fontStyle' => 'bold'],
                    ['type' => 'text', 'x' => 120, 'y' => 220, 'width' => 1680, 'text' => "• First point\n• Second point\n• Third point", 'fontSize' => 32, 'fill' => '#374151'],
                ],
            ]],
        ],
        'image_text' => [
            'label' => 'Image + text',
            'slides' => [[
                'background' => ['type' => 'color', 'value' => '#f8fafc'],
                'elements' => [
                    ['type' => 'rect', 'x' => 80, 'y' => 120, 'width' => 800, 'height' => 840, 'fill' => '#e2e8f0', 'cornerRadius' => 12],
                    ['type' => 'text', 'x' => 960, 'y' => 200, 'width' => 880, 'text' => 'Section Title', 'fontSize' => 44, 'fill' => '#111827', 'fontStyle' => 'bold'],
                    ['type' => 'text', 'x' => 960, 'y' => 320, 'width' => 880, 'text' => 'Add your description here. Explain the concept with supporting details.', 'fontSize' => 28, 'fill' => '#4b5563'],
                ],
            ]],
        ],
        'quote' => [
            'label' => 'Quote',
            'slides' => [[
                'background' => ['type' => 'color', 'value' => '#0f172a'],
                'elements' => [
                    ['type' => 'text', 'x' => 200, 'y' => 360, 'width' => 1520, 'text' => '"A great quote inspires learning."', 'fontSize' => 52, 'fill' => '#f8fafc', 'align' => 'center', 'fontStyle' => 'italic'],
                    ['type' => 'text', 'x' => 200, 'y' => 560, 'width' => 1520, 'text' => '— Author Name', 'fontSize' => 28, 'fill' => '#94a3b8', 'align' => 'center'],
                ],
            ]],
        ],
        'section' => [
            'label' => 'Section divider',
            'slides' => [[
                'background' => ['type' => 'color', 'value' => '#2563eb'],
                'elements' => [
                    ['type' => 'text', 'x' => 120, 'y' => 440, 'width' => 1680, 'text' => 'Section Title', 'fontSize' => 64, 'fill' => '#ffffff', 'align' => 'center', 'fontStyle' => 'bold'],
                ],
            ]],
        ],
    ];
}
