<?php

namespace App\Application\Services;

use App\Models\NavigationMenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class NavigationMenuService
{
    /**
     * Formato compatível com a sidebar (header + links).
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(?User $user = null): array
    {
        $items = NavigationMenuItem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return config('adminlte.menu', []);
        }

        $menu = [];

        foreach ($items as $item) {
            if ($item->required_role && (! $user || ! $user->hasRole($item->required_role))) {
                continue;
            }

            if ($item->isHeader()) {
                $menu[] = ['header' => $item->label];

                continue;
            }

            if (! $item->route || ! Route::has($item->route)) {
                continue;
            }

            $entry = [
                'text' => $item->label,
                'route' => $item->route,
                'icon' => $item->icon ?? 'bi bi-circle',
            ];

            if ($item->required_role) {
                $entry['role'] = $item->required_role;
            }

            $menu[] = $entry;
        }

        return $menu;
    }
}
