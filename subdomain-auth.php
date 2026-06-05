<?php
/**
 * School subdomain sign-in — form POST target on the main LMS domain.
 * Session is created here so users are not bounced to login.php afterward.
 */
require_once __DIR__ . '/includes/bootstrap.php';

function subdomainAuthReturnUrlAllowed(string $url): bool
{
    return portalAuthAllowedHost(strtolower((string) parse_url($url, PHP_URL_HOST)));
}

function subdomainAuthRedirectBack(string $returnUrl, string $message): never
{
    if ($returnUrl !== '' && subdomainAuthReturnUrlAllowed($returnUrl)) {
        $separator = str_contains($returnUrl, '?') ? '&' : '?';
        header('Location: ' . $returnUrl . $separator . 'login_error=' . rawurlencode($message));
        exit;
    }

    flash('error', $message);
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

$returnUrl = trim($_POST['return_url'] ?? '');

if (!verifyPortalAuthRequest()) {
    subdomainAuthRedirectBack($returnUrl, 'Sign-in must start from your school portal.');
}

$schoolCode = normalizeSchoolCode($_POST['school_code'] ?? '');
$portalTs = (int) ($_POST['portal_ts'] ?? 0);
$portalSig = trim($_POST['portal_sig'] ?? '');

if (!verifyPortalAuthSignature($schoolCode, $portalTs, $portalSig)) {
    if ($portalTs <= 0 || $portalSig === '') {
        subdomainAuthRedirectBack($returnUrl, 'Portal sign-in is outdated. Refresh the page and try again.');
    }
    subdomainAuthRedirectBack($returnUrl, 'Your sign-in request expired. Refresh the page and try again.');
}

if (isLoggedIn()) {
    redirectByRole();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$school = $schoolCode !== '' ? resolveLoginSchool(null, 0, $schoolCode) : null;

if ($schoolCode === '') {
    subdomainAuthRedirectBack($returnUrl, 'School code is required.');
}

if ($school === null) {
    subdomainAuthRedirectBack($returnUrl, 'Invalid school code. Please check the code and try again.');
}

if ($school['status'] !== 'active') {
    subdomainAuthRedirectBack($returnUrl, 'This school is not available for login yet.');
}

if ($email === '' || $password === '') {
    subdomainAuthRedirectBack($returnUrl, 'Email and password are required.');
}

if (!checkLoginRateLimit($email)) {
    subdomainAuthRedirectBack($returnUrl, loginLockedMessage());
}

$result = authenticate($email, $password, 'school', (int) $school['id']);

if ($result === null) {
    recordLoginAttempt($email, false);
    subdomainAuthRedirectBack($returnUrl, 'Invalid email or password.');
}

if (isset($result['error']) && $result['error'] === 'school_inactive') {
    subdomainAuthRedirectBack($returnUrl, 'Your school account is not active.');
}

recordLoginAttempt($email, true);
loginUser($result);
session_write_close();
redirectByRole();
