
<?php
/**
 * One-time diagnostic — open in browser, copy the correct lms_root, then DELETE this file.
 */
header('Content-Type: text/plain; charset=utf-8');

$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
$lmsUrl = 'https://kimayco.site';

if (is_file(__DIR__ . '/config.php')) {
    $cfg = require __DIR__ . '/config.php';
    if (!empty($cfg['lms_url'])) {
        $lmsUrl = $cfg['lms_url'];
    }
}

echo "=== EduPlatform subdomain probe ===\n\n";
echo "Subdomain document root:\n  {$docRoot}\n\n";
echo "Main LMS URL from config:\n  {$lmsUrl}\n\n";

$host = parse_url($lmsUrl, PHP_URL_HOST) ?: 'kimayco.site';
$doc = str_replace('\\', '/', (string) $docRoot);
$candidates = [];

if (preg_match('#^(.+/domains)/[^/]+/public_html$#', $doc, $m)) {
    $candidates[] = $m[1] . '/' . $host . '/public_html';
    $candidates[] = $m[1] . '/' . $host . '/public_html/LMS';
}

echo "Candidate LMS paths:\n";
$found = null;
foreach ($candidates as $path) {
    $bootstrap = $path . '/includes/bootstrap.php';
    $ok = is_file($bootstrap);
    echo '  ' . ($ok ? '[OK]' : '[--]') . ' ' . $path . "\n";
    if ($ok && $found === null) {
        $found = $path;
    }
}

echo "\n";
if ($found !== null) {
    echo "Use in config.php:\n\n";
    echo "  'lms_root' => 'auto',\n";
    echo "  // or explicitly:\n";
    echo "  'lms_root' => '" . $found . "',\n";
} else {
    echo "No LMS found automatically.\n";
    echo "In Hostinger File Manager, open your MAIN site folder (kimayco.site/public_html)\n";
    echo "and confirm includes/bootstrap.php exists there.\n";
    echo "Copy that full path into config.php as lms_root.\n";
}

echo "\nDelete probe.php after use.\n";
