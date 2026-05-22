<?php

namespace App\Core\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LogFileReader
{
    /**
     * @return list<array{path: string, name: string, size: int, modified: int}>
     */
    public function discoverFiles(): array
    {
        $dir = storage_path('logs');
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (File::files($dir) as $file) {
            $name = $file->getFilename();
            if (! str_ends_with($name, '.log')) {
                continue;
            }
            $files[] = [
                'path' => $file->getPathname(),
                'name' => $name,
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
            ];
        }

        usort($files, fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function parseRecent(
        ?string $fileName = null,
        int $maxEntries = 150,
        ?string $level = null,
        ?string $search = null,
        ?int $workspaceId = null,
    ): Collection {
        $files = $this->discoverFiles();
        if ($files === []) {
            return collect();
        }

        $target = $fileName
            ? collect($files)->firstWhere('name', $fileName)
            : $files[0];

        if (! $target || ! is_readable($target['path'])) {
            return collect();
        }

        $content = File::get($target['path']);
        $entries = collect();

        if (preg_match_all(
            '/^\[([^\]]+)\]\s*(.*?)(?=^\[|\z)/ms',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $parsed = $this->parseBlock(trim($match[1]), trim($match[2]), $target['name']);

                if ($level && strtoupper($parsed['level']) !== strtoupper($level)) {
                    continue;
                }

                if ($search && ! Str::contains(Str::lower($parsed['text_search']), Str::lower($search))) {
                    continue;
                }

                if ($workspaceId && ! $this->matchesWorkspace($parsed, $workspaceId)) {
                    continue;
                }

                $entries->push($parsed);
            }
        }

        return $entries
            ->sortByDesc(fn ($e) => $e['datetime']->timestamp)
            ->take($maxEntries)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseBlock(string $timestamp, string $body, string $fileName): array
    {
        $level = 'INFO';
        $channel = 'app';
        $message = $body;

        if (preg_match('/^([^\s]+)\.([A-Z]+):\s*(.*)$/s', $body, $m)) {
            $channel = $m[1];
            $level = $m[2];
            $message = trim($m[3]);
        }

        $context = null;
        if (preg_match('/\s(\{.*\})\s*$/s', $message, $jsonMatch)) {
            $decoded = json_decode($jsonMatch[1], true);
            if (is_array($decoded)) {
                $context = $decoded;
                $message = trim(str_replace($jsonMatch[1], '', $message));
            }
        }

        try {
            $datetime = Carbon::parse($timestamp);
        } catch (\Throwable) {
            $datetime = now();
        }

        return [
            'source' => 'log',
            'file' => $fileName,
            'datetime' => $datetime,
            'channel' => $channel,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'raw' => "[{$timestamp}] {$body}",
            'text_search' => $message.' '.json_encode($context ?? []),
        ];
    }

    protected function matchesWorkspace(array $entry, int $workspaceId): bool
    {
        $haystack = $entry['text_search'] ?? '';

        return str_contains($haystack, (string) $workspaceId)
            || str_contains($haystack, '"workspace_id":'.$workspaceId)
            || str_contains($haystack, '"workspace_id":"'.$workspaceId.'"');
    }
}
