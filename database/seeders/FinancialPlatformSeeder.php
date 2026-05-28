<?php

namespace Database\Seeders;

use App\Core\Enums\CompanyType;
use App\Models\SubscriptionProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Core\Infrastructure\Models\FeatureFlag;
use Modules\Core\Infrastructure\Models\Workspace;
use App\Services\RbacBootstrap;
use Modules\Operations\Infrastructure\Models\Operation;
use Spatie\Permission\Models\Role;

class FinancialPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'principal'],
            ['name' => 'Workspace Principal', 'currency' => 'BRL']
        );

        $defaultProfile = SubscriptionProfile::query()->firstOrCreate(
            ['slug' => 'mensal'],
            [
                'name' => 'Mensal',
                'monthly_price_cents' => 2000,
                'description' => 'Acesso padrão ao Financial IQ.',
                'is_active' => true,
            ],
        );

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@financial.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'is_platform_internal' => true,
                'subscription_profile_id' => $defaultProfile->id,
                'access_status' => User::ACCESS_MANUAL_RELEASE,
            ]
        );
        $user->forceFill([
            'is_platform_internal' => true,
            'subscription_profile_id' => $defaultProfile->id,
            'access_status' => User::ACCESS_MANUAL_RELEASE,
            'access_approved_at' => now(),
        ])->save();

        $workspace->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

        RbacBootstrap::sync();
        $user->syncRoles(['SUPER_ADMIN', 'admin']);

        $categories = [
            ['name' => 'IA & APIs', 'slug' => 'ia', 'type' => 'expense', 'color' => '#6f42c1'],
            ['name' => 'Ferramentas SaaS', 'slug' => 'ferramentas', 'type' => 'expense', 'color' => '#0d6efd'],
            ['name' => 'Streaming', 'slug' => 'streaming', 'type' => 'expense', 'color' => '#dc3545'],
            ['name' => 'Infraestrutura', 'slug' => 'infraestrutura', 'type' => 'expense', 'color' => '#fd7e14'],
            ['name' => 'Cloud', 'slug' => 'cloud', 'type' => 'expense', 'color' => '#20c997'],
            ['name' => 'Compras', 'slug' => 'compras', 'type' => 'expense', 'color' => '#ffc107'],
            ['name' => 'Transporte', 'slug' => 'transporte', 'type' => 'expense', 'color' => '#17a2b8'],
            ['name' => 'Viagem & hospedagem', 'slug' => 'viagem', 'type' => 'expense', 'color' => '#6f42c1'],
            ['name' => 'Alimentação', 'slug' => 'alimentacao', 'type' => 'expense', 'color' => '#e67e22'],
            ['name' => 'Saúde', 'slug' => 'saude', 'type' => 'expense', 'color' => '#e74c3c'],
            ['name' => 'Contas recorrentes', 'slug' => 'contas-recorrentes', 'type' => 'expense', 'color' => '#7f8c8d'],
            ['name' => 'Combustível', 'slug' => 'combustivel', 'type' => 'expense', 'color' => '#f39c12'],
            ['name' => 'Utilidades', 'slug' => 'utilidades', 'type' => 'expense', 'color' => '#3498db'],
            ['name' => 'Marketing', 'slug' => 'marketing', 'type' => 'expense', 'color' => '#e83e8c'],
            ['name' => 'Impostos', 'slug' => 'impostos', 'type' => 'expense', 'color' => '#343a40'],
            ['name' => 'Salários & Pró-labore', 'slug' => 'salarios', 'type' => 'expense', 'color' => '#198754'],
            ['name' => 'Serviços terceiros', 'slug' => 'servicos', 'type' => 'expense', 'color' => '#6610f2'],
            ['name' => 'Receitas clientes', 'slug' => 'receitas-clientes', 'type' => 'income', 'color' => '#198754'],
            ['name' => 'Dividendos / Sócio', 'slug' => 'dividendos', 'type' => 'income', 'color' => '#0dcaf0'],
            ['name' => 'Outras receitas', 'slug' => 'receitas', 'type' => 'income', 'color' => '#28a745'],
            ['name' => 'Aluguel - Airbnb', 'slug' => 'aluguel-airbnb', 'type' => 'income', 'color' => '#ff5a5f'],
            ['name' => 'Salário CLT', 'slug' => 'salario-clt', 'type' => 'income', 'color' => '#157347'],
        ];

        foreach ($categories as $cat) {
            $category = Category::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'slug' => $cat['slug']],
                [...$cat, 'workspace_id' => $workspace->id, 'is_system' => true]
            );

            foreach ((array) config('financial.default_categorization_patterns', []) as $pattern => $slug) {
                if ($slug !== $cat['slug']) {
                    continue;
                }
                CategorizationRule::query()->firstOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'pattern' => $pattern,
                    ],
                    [
                        'name' => ucfirst($pattern),
                        'category_id' => $category->id,
                        'match_type' => 'contains',
                        'priority' => 100,
                    ]
                );
            }
        }

        $featuredRules = [
            ['name' => 'Evento B3', 'pattern' => 'evento b3', 'slug' => 'dividendos', 'transaction_type' => 'income', 'priority' => 10],
            ['name' => 'Airbnb', 'pattern' => 'airbnb', 'slug' => 'aluguel-airbnb', 'transaction_type' => 'income', 'priority' => 12],
            ['name' => 'Uber Eats', 'pattern' => 'uber eats', 'slug' => 'alimentacao', 'transaction_type' => 'expense', 'priority' => 15],
            ['name' => 'Uber', 'pattern' => 'uber', 'slug' => 'transporte', 'transaction_type' => 'expense', 'priority' => 25],
        ];

        foreach ($featuredRules as $rule) {
            $category = Category::query()
                ->where('workspace_id', $workspace->id)
                ->where('slug', $rule['slug'])
                ->first();

            if (! $category) {
                continue;
            }

            CategorizationRule::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'pattern' => $rule['pattern'],
                ],
                [
                    'name' => $rule['name'],
                    'category_id' => $category->id,
                    'match_type' => 'contains',
                    'transaction_type' => $rule['transaction_type'],
                    'priority' => $rule['priority'],
                    'is_active' => true,
                ]
            );
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

        $oliveiras = Company::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Residencial Oliveiras'],
            ['type' => CompanyType::Own, 'status' => 'active'],
        );
        if ($oliveiras->type !== CompanyType::Own) {
            $oliveiras->update(['type' => CompanyType::Own]);
        }

        Operation::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'slug' => 'residencial-oliveiras'],
            [
                'company_id' => $oliveiras->id,
                'name' => 'Residencial Oliveiras',
                'description' => 'Airbnb, aluguel por temporada',
                'exclude_from_main_dashboard' => true,
            ],
        );
    }
}
