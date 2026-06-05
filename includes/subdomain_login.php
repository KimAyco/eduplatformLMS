<?php

function subdomainLoginSecret(): string
{
    $secret = trim((string) env('SUBDOMAIN_LOGIN_SECRET', ''));
    if ($secret !== '') {
        return $secret;
    }

    $dbPass = (string) env('DB_PASS', '');
    $dbName = (string) env('DB_NAME', '');
    if ($dbPass === '' || $dbName === '') {
        return '';
    }

    return hash('sha256', APP_NAME . '|subdomain-bridge|' . $dbName . '|' . $dbPass);
}

function createSubdomainLoginToken(int $userId, int $schoolId, int $ttlSeconds = 120): string
{
    $secret = subdomainLoginSecret();
    if ($secret === '') {
        throw new RuntimeException('Subdomain login bridge is not configured.');
    }

    $payload = $userId . '|' . $schoolId . '|' . (time() + $ttlSeconds);
    $signature = hash_hmac('sha256', $payload, $secret);

    return rtrim(strtr(base64_encode($payload . '.' . $signature), '+/', '-_'), '=');
}

function parseSubdomainLoginToken(string $token): ?array
{
    $secret = subdomainLoginSecret();
    if ($secret === '' || $token === '') {
        return null;
    }

    $decoded = base64_decode(strtr($token, '-_', '+/'), true);
    if ($decoded === false || !str_contains($decoded, '.')) {
        return null;
    }

    [$payload, $signature] = explode('.', $decoded, 2);
    if (!hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
        return null;
    }

    $parts = explode('|', $payload);
    if (count($parts) !== 3) {
        return null;
    }

    [$userId, $schoolId, $expiresAt] = $parts;
    if ((int) $expiresAt < time()) {
        return null;
    }

    return [
        'user_id' => (int) $userId,
        'school_id' => (int) $schoolId,
    ];
}
