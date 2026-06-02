<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Modules\Finance\Application\Services\TransactionIndexFilterService;
use Symfony\Component\HttpFoundation\Response;

class PersistFilters
{
    const TTL_MINUTES = 30;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $routeName  = $request->route()?->getName();
        $filterKeys = $this->filterKeysFor($routeName);

        if (empty($filterKeys)) {
            return $next($request);
        }

        $sessionKey   = "persisted_filters.{$routeName}";
        $timestampKey = "persisted_filters_ts.{$routeName}";

        // Limpeza explícita: usuário clicou em "Limpar filtros"
        if ($request->has('clear')) {
            session()->forget([$sessionKey, $timestampKey]);
            return redirect()->to($request->url());
        }

        $active = collect($request->only($filterKeys))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        if (! empty($active)) {
            // Filtros presentes → persiste e atualiza o timestamp
            session([
                $sessionKey   => $active,
                $timestampKey => now()->timestamp,
            ]);
        } else {
            // Sem filtros na URL → tenta restaurar da session
            $stored = session($sessionKey, []);
            $ts     = session($timestampKey, 0);

            if (
                ! empty($stored)
                && $ts > 0
                && Carbon::createFromTimestamp($ts)->diffInMinutes(now()) < self::TTL_MINUTES
            ) {
                return redirect()->to($request->url() . '?' . http_build_query($stored));
            }
        }

        return $next($request);
    }

    private function filterKeysFor(?string $route): array
    {
        return match ($route) {
            'alerts.index'       => ['severity', 'status'],
            'transactions.index' => TransactionIndexFilterService::QUERY_KEYS,
            'reports.index'      => [...TransactionIndexFilterService::QUERY_KEYS, 'view'],
            default              => [],
        };
    }
}
