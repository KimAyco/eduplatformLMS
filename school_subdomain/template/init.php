<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit(
        "School subdomain is not configured.\n\n"
        . "Copy config.php.example to config.php in this folder and set lms_url and school_code.\n"
    );
}

/** @var array<string, mixed> $subdomainConfig */
$subdomainConfig = require $configFile;

foreach (['lms_url'] as $requiredKey) {
    if (empty($subdomainConfig[$requiredKey])) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit("config.php: \"{$requiredKey}\" is required.\n");
    }
}

$hasCode = trim((string) ($subdomainConfig['school_code'] ?? '')) !== '';
$hasSlug = trim((string) ($subdomainConfig['school_slug'] ?? '')) !== '';
if (!$hasCode && !$hasSlug) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("config.php: set either \"school_code\" or \"school_slug\".\n");
}

function subdomainIsPlaceholderLmsRoot(string $path): bool
{
    return $path === ''
        || str_contains($path, '...')
        || stripos($path, 'USERNAME') !== false
        || stripos($path, 'yoursite.com') !== false;
}

function subdomainLmsRootCandidates(string $docRoot, string $lmsUrl): array
{
    $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
    $host = parse_url($lmsUrl, PHP_URL_HOST) ?: '';
    $candidates = [];

    if ($host !== '' && preg_match('#^(.+/domains)/[^/]+/public_html$#', $docRoot, $matches)) {
        $candidates[] = $matches[1] . '/' . $host . '/public_html';
        $candidates[] = $matches[1] . '/' . $host . '/public_html/LMS';
    }

    if ($host !== '' && preg_match('#^(.+/public_html)/[^/]+$#', $docRoot, $matches)) {
        $candidates[] = $matches[1];
        $candidates[] = $matches[1] . '/LMS';
    }

    return array_values(array_unique($candidates));
}

function subdomainResolveLmsRoot(string $configured, string $lmsUrl): ?string
{
    $configured = rtrim(str_replace('\\', '/', trim($configured)), '/');

    if ($configured !== '' && !in_array(strtolower($configured), ['auto', 'detect'], true) && !subdomainIsPlaceholderLmsRoot($configured)) {
        $resolved = realpath($configured);
        if ($resolved && is_file($resolved . '/includes/subdomain_bootstrap.php')) {
            return str_replace('\\', '/', $resolved);
        }
    }

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
    if (!$docRoot) {
        return null;
    }

    foreach (subdomainLmsRootCandidates(str_replace('\\', '/', $docRoot), $lmsUrl) as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved && is_file($resolved . '/includes/subdomain_bootstrap.php')) {
            return str_replace('\\', '/', $resolved);
        }
    }

    return null;
}

$configuredRoot = (string) ($subdomainConfig['lms_root'] ?? 'auto');
$lmsUrl = (string) $subdomainConfig['lms_url'];
$lmsRoot = subdomainResolveLmsRoot($configuredRoot, $lmsUrl);

if ($lmsRoot === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) ?: '(unknown)';
    $tried = subdomainLmsRootCandidates(str_replace('\\', '/', (string) $docRoot), $lmsUrl);
    $lines = [
        'Cannot load LMS bootstrap.',
        '',
        'Your lms_root is wrong or still a placeholder.',
        'Configured lms_root: ' . ($configuredRoot === '' ? '(empty)' : $configuredRoot),
        'Subdomain document root: ' . $docRoot,
        '',
        'Fix config.php — use one of these:',
        "  'lms_root' => 'auto',",
        '  (recommended — auto-finds the main site on Hostinger)',
        '',
        'Or set the real absolute path, for example:',
        '  /home/u353641241/domains/kimayco.site/public_html',
        '',
        'Paths checked automatically:',
    ];

    if ($tried === []) {
        $lines[] = '  (none — server layout not recognized)';
    } else {
        foreach ($tried as $path) {
            $exists = is_dir($path) ? 'folder exists' : 'not found';
            $bootstrap = is_file($path . '/includes/subdomain_bootstrap.php') ? 'bootstrap OK' : 'no bootstrap';
            $lines[] = "  - {$path} ({$exists}, {$bootstrap})";
        }
    }

    $lines[] = '';
    $lines[] = 'Tip: upload probe.php from the template folder, open it in the browser, then delete it.';

    exit(implode("\n", $lines));
}

$bootstrapFile = $lmsRoot . '/includes/subdomain_bootstrap.php';

require_once $bootstrapFile;

function subdomainConfig(string $key, mixed $default = null): mixed
{
    global $subdomainConfig;
    return $subdomainConfig[$key] ?? $default;
}

function subdomainLmsRoot(): string
{
    global $lmsRoot;
    return $lmsRoot;
}

function lmsUrl(string $path = ''): string
{
    $base = rtrim((string) subdomainConfig('lms_url'), '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function redirectToLms(string $path): never
{
    header('Location: ' . lmsUrl($path));
    exit;
}

function resolveSubdomainSchool(): ?array
{
    $code = trim((string) subdomainConfig('school_code', ''));
    if ($code !== '') {
        return resolveLoginSchool(null, 0, normalizeSchoolCode($code));
    }

    $slug = trim((string) subdomainConfig('school_slug', ''));
    if ($slug !== '') {
        return resolveLoginSchool($slug, 0, null);
    }

    return null;
}

function subdomainPortalTitle(array $school): string
{
    $custom = trim((string) subdomainConfig('portal_title', ''));
    return $custom !== '' ? $custom : $school['name'];
}

function subdomainPrimaryColor(): string
{
    $color = trim((string) subdomainConfig('primary_color', '#0f6cbf'));
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#0f6cbf';
}
