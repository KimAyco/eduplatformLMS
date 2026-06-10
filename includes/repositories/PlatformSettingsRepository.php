<?php

class PlatformSettingsRepository
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            self::loadAll();
        }
        if (!array_key_exists($key, self::$cache)) {
            return $default;
        }
        return self::castValue(self::$cache[$key], $default);
    }

    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        db()->prepare('INSERT INTO platform_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)')
            ->execute([$key, $stored, $userId]);

        if (self::$cache !== null) {
            self::$cache[$key] = $stored;
        }
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::loadAll();
        }
        $out = [];
        foreach (self::$cache as $key => $value) {
            $out[$key] = self::castValue($value, null);
        }
        return $out;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    private static function loadAll(): void
    {
        self::$cache = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM platform_settings')->fetchAll();
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
            if ($rows === []) {
                self::seedDefaults();
            }
        } catch (PDOException) {
            // Table may not exist before migration.
        }
    }

    private static function seedDefaults(): void
    {
        $defaults = [
            'ai_enabled' => '1',
            'groq_rate_limit_per_minute' => '3',
            'groq_model' => 'llama-3.3-70b-versatile',
        ];
        foreach ($defaults as $key => $value) {
            db()->prepare('INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES (?, ?)')
                ->execute([$key, $value]);
            self::$cache[$key] = $value;
        }
    }

    private static function castValue(mixed $value, mixed $default): mixed
    {
        if (!is_string($value)) {
            return $value ?? $default;
        }
        if ($value === '1' || $value === '0') {
            if (is_bool($default)) {
                return $value === '1';
            }
        }
        if (is_int($default) && ctype_digit($value)) {
            return (int) $value;
        }
        if (is_float($default) && is_numeric($value)) {
            return (float) $value;
        }
        return $value;
    }
}

function platformSetting(string $key, mixed $default = null): mixed
{
    return PlatformSettingsRepository::get($key, $default);
}

function aiIsEnabled(): bool
{
    return (bool) platformSetting('ai_enabled', true);
}

function groqRateLimitPerMinute(): int
{
    return max(1, (int) platformSetting('groq_rate_limit_per_minute', 3));
}

function groqModel(): string
{
    return (string) platformSetting('groq_model', env('GROQ_MODEL', 'llama-3.3-70b-versatile'));
}
