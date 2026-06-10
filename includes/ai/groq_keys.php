<?php

/**
 * @return list<string>
 */
function groqApiKeys(): array
{
    static $keys = null;
    if ($keys !== null) {
        return $keys;
    }

    $keys = [];

    $combined = env('GROQ_API_KEYS', '');
    if (is_string($combined) && trim($combined) !== '') {
        foreach (explode(',', $combined) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $keys[] = $part;
            }
        }
    }

    if ($keys === []) {
        for ($i = 1; $i <= 10; $i++) {
            $legacy = env($i . 'GROQ_API_KEY', '');
            if (is_string($legacy) && trim($legacy) !== '') {
                $keys[] = trim(preg_replace('/\s+#.*$/', '', $legacy) ?? $legacy);
            }
        }
    }

    $single = env('GROQ_API_KEY', '');
    if ($keys === [] && is_string($single) && trim($single) !== '') {
        $keys[] = trim($single);
    }

    return $keys;
}

function groqKeyCount(): int
{
    return count(groqApiKeys());
}

function groqKeyAt(int $index): ?string
{
    $keys = groqApiKeys();
    return $keys[$index] ?? null;
}

function groqNextKeyIndex(): int
{
    static $cursor = 0;
    $count = groqKeyCount();
    if ($count === 0) {
        return 0;
    }
    $index = $cursor % $count;
    $cursor++;
    return $index;
}
