<?php

namespace App\Core\Support;

class MarkdownJsonParser
{
    /**
     * @return array<string, mixed>
     */
    public static function decode(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) {
            $decoded = json_decode($m[1], true);

            return is_array($decoded) ? $decoded : [];
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
