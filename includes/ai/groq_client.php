<?php

/**
 * @param list<array{role: string, content: string}> $messages
 * @return array{content: string, raw: array<string, mixed>}
 */
function groqChatCompletion(array $messages, ?int $keyIndex = null, array $options = []): array
{
    if ($keyIndex === null) {
        $keyIndex = groqAcquireKeyIndex();
    }

    $apiKey = groqKeyAt($keyIndex);
    if ($apiKey === null) {
        throw new RuntimeException('Groq API key not found.');
    }

    if (!groqKeyHasSlot($keyIndex)) {
        $keyIndex = groqAcquireKeyIndex();
        $apiKey = groqKeyAt($keyIndex);
    }

    $payload = [
        'model' => $options['model'] ?? groqModel(),
        'messages' => $messages,
        'temperature' => $options['temperature'] ?? 0.4,
        'max_tokens' => $options['max_tokens'] ?? 4096,
    ];

    if (!empty($options['response_format'])) {
        $payload['response_format'] = $options['response_format'];
    }

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 120,
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    groqRecordKeyUsage($keyIndex);

    if ($body === false) {
        throw new RuntimeException('Groq request failed: ' . $curlError);
    }

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($body, true) ?? [];

    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
        throw new RuntimeException('Groq API error: ' . $msg);
    }

    $content = (string) ($decoded['choices'][0]['message']['content'] ?? '');
    return ['content' => $content, 'raw' => $decoded, 'key_index' => $keyIndex];
}

/**
 * @return array<string, mixed>
 */
function groqJsonCompletion(array $messages, ?int $keyIndex = null, array $options = []): array
{
    $options['response_format'] = ['type' => 'json_object'];
    $result = groqChatCompletion($messages, $keyIndex, $options);
    $content = trim($result['content']);

    if (str_starts_with($content, '```')) {
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Groq returned invalid JSON.');
    }

    return $decoded;
}
