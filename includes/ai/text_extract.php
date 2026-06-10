<?php

function textExtractFromMaterial(array $material): string
{
    $parts = [];
    if (!empty($material['title'])) {
        $parts[] = 'Title: ' . $material['title'];
    }
    if (!empty($material['body'])) {
        $parts[] = $material['body'];
    }
    if (!empty($material['description'])) {
        $parts[] = $material['description'];
    }

    $type = (string) ($material['type'] ?? 'file');

    if ($type === 'doc' && !empty($material['content'])) {
        $parts[] = textExtractFromHtml((string) $material['content']);
    } elseif ($type === 'deck' && !empty($material['content'])) {
        $parts[] = textExtractFromDeckJson((string) $material['content']);
    } elseif (!empty($material['file_path'])) {
        $parts[] = textExtractFromFilePath((string) $material['file_path'], (string) ($material['original_name'] ?? ''));
    }

    return trim(implode("\n\n", array_filter($parts, static fn ($p) => trim($p) !== '')));
}

function textExtractFromLibraryResource(array $resource): string
{
    $parts = [];
    if (!empty($resource['title'])) {
        $parts[] = 'Title: ' . $resource['title'];
    }
    if (!empty($resource['description'])) {
        $parts[] = $resource['description'];
    }
    if (!empty($resource['body'])) {
        $parts[] = $resource['body'];
    }

    $type = (string) ($resource['type'] ?? 'file');
    if ($type === 'doc' && !empty($resource['content'])) {
        $parts[] = textExtractFromHtml((string) $resource['content']);
    } elseif ($type === 'deck' && !empty($resource['content'])) {
        $parts[] = textExtractFromDeckJson((string) $resource['content']);
    } elseif (!empty($resource['file_path'])) {
        $parts[] = textExtractFromFilePath((string) $resource['file_path'], (string) ($resource['original_name'] ?? ''));
    }

    return trim(implode("\n\n", array_filter($parts, static fn ($p) => trim($p) !== '')));
}

function textExtractFromHtml(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    return trim($text);
}

function textExtractFromDeckJson(string $json): string
{
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return '';
    }
    $slides = $data['slides'] ?? [];
    $lines = [];
    foreach ($slides as $slide) {
        if (!is_array($slide)) {
            continue;
        }
        foreach ($slide['elements'] ?? [] as $el) {
            if (!is_array($el)) {
                continue;
            }
            if (($el['type'] ?? '') === 'text' && !empty($el['text'])) {
                $lines[] = trim((string) $el['text']);
            }
        }
    }
    return trim(implode("\n", $lines));
}

function textExtractFromFilePath(string $relativePath, string $originalName = ''): string
{
    $full = uploadFullPath($relativePath);
    if (!is_file($full)) {
        return '';
    }

    $ext = strtolower(pathinfo($originalName ?: $relativePath, PATHINFO_EXTENSION));

    return match ($ext) {
        'txt' => trim((string) file_get_contents($full)),
        'docx' => textExtractFromDocx($full),
        'pdf' => textExtractFromPdf($full),
        default => '',
    };
}

function textExtractFromDocx(string $fullPath): string
{
    if (!class_exists('ZipArchive')) {
        return '';
    }
    $zip = new ZipArchive();
    if ($zip->open($fullPath) !== true) {
        return '';
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        return '';
    }
    $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml) ?? $xml;
    $xml = preg_replace('/<w:br[^>]*\/>/', "\n", $xml) ?? $xml;
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

function textExtractFromPdf(string $fullPath): string
{
    if (function_exists('shell_exec')) {
        $escaped = escapeshellarg($fullPath);
        foreach (['pdftotext', 'C:\\Program Files\\xpdf\\pdftotext.exe'] as $bin) {
            $cmd = strpos($bin, ' ') !== false ? "\"$bin\" -layout $escaped -" : "$bin -layout $escaped -";
            $out = @shell_exec($cmd);
            if (is_string($out) && trim($out) !== '') {
                return trim($out);
            }
        }
    }
    return '';
}

function textContentHash(string $text): string
{
    return hash('sha256', $text);
}

function textTruncateForContext(string $text, int $maxChars = 24000): string
{
    if (mb_strlen($text) <= $maxChars) {
        return $text;
    }
    return mb_substr($text, 0, $maxChars) . "\n\n[... truncated ...]";
}

function uploadFullPath(string $relativePath): string
{
    $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : (APP_ROOT . '/uploads');
    return rtrim($uploadDir, '/\\') . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}
