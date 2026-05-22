<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Modules\Core\Infrastructure\Models\FeatureFlag;
use Modules\Core\Infrastructure\Models\Workspace;
use Spatie\Permission\Models\Role;

class FinancialPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'principal'],
            ['name' => 'Workspace Principal', 'currency' => 'BRL']
        );

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@financial.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'is_platform_internal' => true,
            ]
        );
        $user->forceFill(['is_platform_internal' => true])->save();

        $workspace->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

        Role::findOrCreate('admin');
        Role::findOrCreate('finance_manager');
        Role::findOrCreate('viewer');
        $user->assignRole('admin');

        $categories = [
            ['name' => 'IA & APIs', 'slug' => 'ia', 'type' => 'expense', 'color' => '#6f42c1'],
            ['name' => 'Ferramentas SaaS', 'slug' => 'ferramentas', 'type' => 'expense', 'color' => '#0d6efd'],
            ['name' => 'Streaming', 'slug' => 'streaming', 'type' => 'expense', 'color' => '#dc3545'],
            ['name' => 'Infraestrutura', 'slug' => 'infraestrutura', 'type' => 'expense', 'color' => '#fd7e14'],
            ['name' => 'Cloud', 'slug' => 'cloud', 'type' => 'expense', 'color' => '#20c997'],
            ['name' => 'Compras', 'slug' => 'compras', 'type' => 'expense', 'color' => '#ffc107'],
            ['name' => 'Marketing', 'slug' => 'marketing', 'type' => 'expense', 'color' => '#e83e8c'],
            ['name' => 'Impostos', 'slug' => 'impostos', 'type' => 'expense', 'color' => '#343a40'],
            ['name' => 'Salários & Pró-labore', 'slug' => 'salarios', 'type' => 'expense', 'color' => '#198754'],
            ['name' => 'Serviços terceiros', 'slug' => 'servicos', 'type' => 'expense', 'color' => '#6610f2'],
            ['name' => 'Receitas clientes', 'slug' => 'receitas-clientes', 'type' => 'income', 'color' => '#198754'],
            ['name' => 'Dividendos / Sócio', 'slug' => 'dividendos', 'type' => 'income', 'color' => '#0dcaf0'],
            ['name' => 'Outras receitas', 'slug' => 'receitas', 'type' => 'income', 'color' => '#28a745'],
        ];

        foreach ($categories as $cat) {
            $category = Category::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'slug' => $cat['slug']],
                [...$cat, 'workspace_id' => $workspace->id, 'is_system' => true]
            );

            foreach (config('financial.default_categorization_patterns', []) as $pattern => $slug) {
                if ($slug !== $cat['slug']) {
                    continue;
                }
                CategorizationRule::query()->firstOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'pattern' => $pattern,
                    ],
                    [
                        'category_id' => $category->id,
                        'match_type' => 'contains',
                        'priority' => 10,
                    ]
                );
            }
        }

        $flags = [
            'ai_financial_analysis',
            'ai_categorization',
            'ai_observability',
            'ocr_vision',
            'open_finance',
        ];

        foreach ($flags as $flag) {
            FeatureFlag::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'key' => $flag],
                ['enabled' => in_array($flag, ['ai_financial_analysis', 'ai_categorization'])]
            );
        }
    }
}
