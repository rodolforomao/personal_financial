<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class WebPhpAccessGuard
{
    public function __construct(
        protected ?string $appRoot = null,
        protected ?string $publicRoot = null,
    ) {
        $this->appRoot = rtrim(str_replace('\\', '/', $this->appRoot ?? base_path()), '/');
        $this->publicRoot = rtrim(str_replace('\\', '/', realpath($this->publicRoot ?? public_path()) ?: ''), '/');
    }

    /**
     * @return list<array{pool: string, domain: string, open_basedir: string, missing_paths: list<string>}>
     */
    public function diagnose(): array
    {
        $issues = [];

        foreach ($this->linkedDomainPools() as $entry) {
            $missing = $this->missingRequiredPaths($entry['open_basedir']);
            if ($missing !== []) {
                $issues[] = [
                    'pool' => $entry['pool'],
                    'domain' => $entry['domain'],
                    'open_basedir' => $entry['open_basedir'],
                    'missing_paths' => $missing,
                ];
            }
        }

        return $issues;
    }

    public function needsFix(): bool
    {
        return $this->diagnose() !== [];
    }

    /**
     * @return array{fixed: list<string>, skipped: list<string>, errors: list<string>, reloaded: bool}
     */
    public function applyFix(): array
    {
        $result = [
            'fixed' => [],
            'skipped' => [],
            'errors' => [],
            'reloaded' => false,
        ];

        foreach ($this->linkedDomainPools() as $entry) {
            $pool = $entry['pool'];
            if ($this->missingRequiredPaths($entry['open_basedir']) === []) {
                $result['skipped'][] = $pool;

                continue;
            }

            if (! is_readable($pool) || ! is_writable($pool)) {
                $result['errors'][] = "Sem permissão para alterar {$pool} (execute como root).";

                continue;
            }

            $content = file_get_contents($pool);
            if (! is_string($content) || $content === '') {
                $result['errors'][] = "Pool inválido ou vazio: {$pool}";

                continue;
            }

            $patched = $this->patchPoolContent($content, $this->appRoot);
            if ($patched === $content) {
                $result['errors'][] = "Não foi possível ajustar open_basedir em {$pool}";

                continue;
            }

            if (file_put_contents($pool, $patched) === false) {
                $result['errors'][] = "Falha ao gravar {$pool}";

                continue;
            }

            $result['fixed'][] = $pool;
        }

        if ($result['fixed'] !== []) {
            $result['reloaded'] = $this->reloadPhpFpm($result['fixed']);
        }

        return $result;
    }

    public function reloadPhpFpm(array $poolFiles = []): bool
    {
        $versions = [];

        foreach ($poolFiles as $pool) {
            if (preg_match('#/etc/php/([0-9.]+)/fpm/#', $pool, $matches)) {
                $versions[$matches[1]] = true;
            }
        }

        if ($versions === []) {
            $versions[PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION] = true;
        }

        $ok = true;
        foreach (array_keys($versions) as $version) {
            $service = "php{$version}-fpm";
            $reload = Process::run(['systemctl', 'reload', $service]);
            if (! $reload->successful()) {
                $ok = false;
            }
        }

        return $ok;
    }

    public function probeHealthUrl(?string $url = null): ?int
    {
        $target = rtrim($url ?? (string) config('app.url'), '/').'/up';

        try {
            return Http::timeout(10)->get($target)->status();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    protected function requiredPaths(): array
    {
        return array_values(array_unique(array_filter([
            $this->appRoot,
            rtrim(str_replace('\\', '/', storage_path()), '/'),
        ])));
    }

    /**
     * @return list<string>
     */
    protected function missingRequiredPaths(string $openBasedir): array
    {
        $missing = [];

        foreach ($this->requiredPaths() as $path) {
            if ($path === '') {
                continue;
            }

            if (! self::pathIsAllowed($path, $openBasedir)) {
                $missing[] = $path;
            }
        }

        return $missing;
    }

    /**
     * @return list<array{pool: string, domain: string, open_basedir: string}>
     */
    protected function linkedDomainPools(): array
    {
        if ($this->publicRoot === '') {
            return [];
        }

        $entries = [];

        foreach (glob('/home/*/web/*/public_html') ?: [] as $publicHtml) {
            $resolved = realpath($publicHtml);
            if ($resolved !== $this->publicRoot) {
                continue;
            }

            $domain = basename(dirname($publicHtml));
            foreach ($this->poolFilesForDomain($domain) as $pool) {
                $openBasedir = $this->readOpenBasedirFromPool($pool);
                if ($openBasedir === null) {
                    continue;
                }

                $entries[] = [
                    'pool' => $pool,
                    'domain' => $domain,
                    'open_basedir' => $openBasedir,
                ];
            }
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    protected function poolFilesForDomain(string $domain): array
    {
        $files = [];

        foreach (glob('/etc/php/*/fpm/pool.d/'.$domain.'.conf') ?: [] as $pool) {
            $files[] = $pool;
        }

        return $files;
    }

    protected function readOpenBasedirFromPool(string $pool): ?string
    {
        $content = @file_get_contents($pool);
        if (! is_string($content)) {
            return null;
        }

        if (! preg_match('/php_admin_value\[open_basedir\]\s*=\s*(.+)$/m', $content, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    protected function patchPoolContent(string $content, string $appRoot): string
    {
        if (str_contains($content, $appRoot)) {
            return $content;
        }

        $needle = 'public_shtml:';
        if (str_contains($content, $needle)) {
            return str_replace($needle, $needle.$appRoot.':', $content);
        }

        return preg_replace(
            '/(php_admin_value\[open_basedir\]\s*=\s*.+?)(\/home\/[^:\s]+\/tmp)/',
            '$1'.$appRoot.':$2',
            $content,
            1,
        ) ?? $content;
    }

    public static function pathIsAllowed(string $path, string $openBasedir): bool
    {
        if ($openBasedir === '') {
            return true;
        }

        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        foreach (explode(PATH_SEPARATOR, $openBasedir) as $allowed) {
            $allowed = rtrim(str_replace('\\', '/', trim($allowed)), '/');
            if ($allowed === '') {
                continue;
            }

            if ($normalized === $allowed || str_starts_with($normalized, $allowed.'/')) {
                return true;
            }
        }

        return false;
    }
}
