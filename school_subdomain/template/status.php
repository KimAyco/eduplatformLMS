<?php
/**
 * Deploy diagnostic — open in browser, then delete this file.
 */
require_once __DIR__ . '/init.php';

header('Content-Type: text/plain; charset=utf-8');

$lines = [
    'School subdomain portal status',
    '==============================',
    '',
    'Portal version: portal-auth-v3',
    'LMS root: ' . subdomainLmsRoot(),
    'LMS URL: ' . lmsUrl(),
    'Form action: ' . lmsUrl('subdomain-auth.php'),
    '',
];

$school = resolveSubdomainSchool();
if ($school === null) {
    $lines[] = 'School: NOT FOUND (check school_code in config.php)';
} else {
    $lines[] = 'School: ' . $school['name'] . ' (' . ($school['school_code'] ?? '') . ')';
    $lines[] = 'School status: ' . ($school['status'] ?? 'unknown');
}

try {
    db()->query('SELECT 1');
    $lines[] = 'Database: OK';
} catch (Throwable $e) {
    $lines[] = 'Database: FAILED — ' . $e->getMessage();
}

try {
    $fields = portalAuthFields(normalizeSchoolCode((string) subdomainConfig('school_code', 'TEST')));
    $lines[] = 'Portal signature: OK (ts=' . $fields['portal_ts'] . ')';
} catch (Throwable $e) {
    $lines[] = 'Portal signature: FAILED — ' . $e->getMessage();
}

$authUrl = lmsUrl('subdomain-auth.php');
$headers = @get_headers($authUrl, true);
$lines[] = 'subdomain-auth.php reachable: ' . ($headers !== false ? 'yes (HTTP redirect on GET is normal)' : 'no — upload subdomain-auth.php to main site');

$lines[] = '';
$lines[] = 'Delete status.php after confirming everything looks correct.';

echo implode("\n", $lines);
