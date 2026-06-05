<?php
/**
 * School subdomain sign-in — form POST target on the main LMS domain.
 * Session is created here so users are not bounced to login.php afterward.
 */
require_once __DIR__ . '/includes/bootstrap.php';

function subdomainAuthReturnUrlAllowed(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '') {
        return false;
    }

    $allowedHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($allowedHost === '') {
        return false;
    }

    if ($host === $allowedHost) {
        return true;
    }

    $suffix = '.' . $allowedHost;
    return str_ends_with($host, $suffix);
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

if (isLoggedIn()) {
    redirectByRole();
}

$schoolCode = normalizeSchoolCode($_POST['school_code'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$returnUrl = trim($_POST['return_url'] ?? '');

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
redirectByRole();
