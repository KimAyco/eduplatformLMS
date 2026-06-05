<?php

function ensureSubdomainLoginTokenTable(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    db()->exec("CREATE TABLE IF NOT EXISTS subdomain_login_tokens (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        school_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_subdomain_login_token_hash (token_hash),
        KEY idx_subdomain_login_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ready = true;
}

function createSubdomainLoginToken(int $userId, int $schoolId, int $ttlSeconds = 120): string
{
    ensureSubdomainLoginTokenTable();

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

    db()->prepare('INSERT INTO subdomain_login_tokens (user_id, school_id, token_hash, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $schoolId, $hash, $expiresAt]);

    return $token;
}

function parseSubdomainLoginToken(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    ensureSubdomainLoginTokenTable();

    $hash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT user_id, school_id FROM subdomain_login_tokens
        WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
        LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    db()->prepare('UPDATE subdomain_login_tokens SET used_at = NOW() WHERE token_hash = ?')
        ->execute([$hash]);

    return [
        'user_id' => (int) $row['user_id'],
        'school_id' => (int) $row['school_id'],
    ];
}

function subdomainLoginBridgeReady(): bool
{
    try {
        ensureSubdomainLoginTokenTable();
        return true;
    } catch (Throwable) {
        return false;
    }
}
