<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Alerts\Infrastructure\Models\Alert;
use App\Core\Enums\AlertSeverity;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Infrastructure\Models\CltSalary;
use Modules\Finance\Infrastructure\Models\CltSalaryPeriod;

class CltSalaryService
{
    public function __construct(
        protected CreateTransactionAction $createTransaction,
    ) {}

    public function defaultCategoryId(int $workspaceId): int
    {
        return (int) Category::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => 'salario-clt'],
            ['name' => 'Salário CLT', 'type' => 'income', 'color' => '#157347', 'is_system' => true],
        )->id;
    }

    /**
     * @param  array{company_id: int, gross_amount?: float|null, net_amount: float, payment_day?: int, description_template?: string, category_id?: int|null}  $data
     */
    public function upsert(int $workspaceId, array $data): CltSalary
    {
        $categoryId = $data['category_id'] ?? $this->defaultCategoryId($workspaceId);
        $paymentDay = min(28, max(1, (int) ($data['payment_day'] ?? 5)));

        return CltSalary::query()->updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'company_id' => $data['company_id'],
            ],
            [
                'category_id' => $categoryId,
                'gross_amount' => isset($data['gross_amount']) && $data['gross_amount'] !== ''
                    ? (float) $data['gross_amount']
                    : null,
                'net_amount' => (float) $data['net_amount'],
                'payment_day' => $paymentDay,
                'description_template' => $data['description_template'] ?? 'Salário CLT',
                'is_active' => $data['is_active'] ?? true,
            ],
        );
    }

    public function ensurePendingPeriods(int $workspaceId): int
    {
        $created = 0;
        $month = now()->startOfMonth();

        $salaries = CltSalary::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->with('company')
            ->get();

        foreach ($salaries as $salary) {
            if (! $this->shouldOpenPeriod($salary, $month)) {
                continue;
            }

            $period = CltSalaryPeriod::query()->firstOrCreate(
                [
                    'clt_salary_id' => $salary->id,
                    'reference_month' => $month->toDateString(),
                ],
                [
                    'gross_amount' => $salary->gross_amount,
                    'net_amount' => $salary->net_amount,
                    'status' => CltSalaryPeriod::STATUS_PENDING,
                ],
            );

            if ($period->wasRecentlyCreated) {
                $created++;
            } elseif ($period->isPending()) {
                $period->update([
                    'gross_amount' => $salary->gross_amount,
                    'net_amount' => $salary->net_amount,
                ]);
            }

            if ($period->isPending()) {
                $this->ensureConfirmationAlert($workspaceId, $salary, $period);
            }
        }

        return $created;
    }

    /**
     * @return Collection<int, CltSalaryPeriod>
     */
    public function pendingPeriods(int $workspaceId): Collection
    {
        return CltSalaryPeriod::query()
            ->where('status', CltSalaryPeriod::STATUS_PENDING)
            ->whereHas('cltSalary', fn ($q) => $q->where('workspace_id', $workspaceId)->where('is_active', true))
            ->with(['cltSalary.company', 'cltSalary.category'])
            ->orderBy('reference_month')
            ->get();
    }

    public function suggestPaymentDate(CltSalary $salary, Carbon|string $referenceMonth): string
    {
        $ref = Carbon::parse($referenceMonth)->startOfMonth();
        $paymentDay = min(28, max(1, (int) $salary->payment_day));
        $txDate = $ref->copy()->day(min($paymentDay, $ref->daysInMonth));

        if ($txDate->isFuture()) {
            return now()->toDateString();
        }

        return $txDate->toDateString();
    }

    /**
     * @param  array{net_amount: float, gross_amount?: float|null, transaction_date?: string}  $data
     * @return array{period: CltSalaryPeriod, transaction_id: int}
     */
    public function confirmPeriod(CltSalaryPeriod $period, User $user, array $data): array
    {
        abort_unless($period->isPending(), 422, 'Este período já foi confirmado.');

        $salary = $period->cltSalary()->with('company')->firstOrFail();
        $net = (float) $data['net_amount'];
        if ($net <= 0) {
            throw new \InvalidArgumentException('Informe o valor líquido recebido.');
        }

        $gross = isset($data['gross_amount']) && $data['gross_amount'] !== ''
            ? (float) $data['gross_amount']
            : null;

        $ref = Carbon::parse($period->reference_month);
        $txDate = isset($data['transaction_date']) && $data['transaction_date'] !== ''
            ? Carbon::parse($data['transaction_date'])->toDateString()
            : $this->suggestPaymentDate($salary, $ref);

        $description = trim($salary->description_template.' — '.$salary->company->name.' — '.$ref->translatedFormat('F/Y'));

        $transaction = $this->createTransaction->execute(new CreateTransactionData(
            workspaceId: $salary->workspace_id,
            type: TransactionType::Income,
            amount: $net,
            description: mb_substr($description, 0, 500),
            transactionDate: $txDate,
            categoryId: $salary->category_id ?? $this->defaultCategoryId($salary->workspace_id),
            companyId: $salary->company_id,
            counterparty: $salary->company->name,
            status: TransactionStatus::Confirmed,
            source: 'clt_salary',
            metadata: [
                'clt_salary_id' => $salary->id,
                'clt_salary_period_id' => $period->id,
                'gross_amount' => $gross,
                'net_amount' => $net,
                'reference_month' => $ref->toDateString(),
                'payment_date' => $txDate,
            ],
        ));

        $period->update([
            'gross_amount' => $gross,
            'net_amount' => $net,
            'status' => CltSalaryPeriod::STATUS_CONFIRMED,
            'transaction_id' => $transaction->id,
            'confirmed_at' => now(),
            'confirmed_by' => $user->id,
        ]);

        $salary->update([
            'gross_amount' => $gross ?? $salary->gross_amount,
            'net_amount' => $net,
        ]);

        $this->markAlertsRead($salary->workspace_id, $period->id);

        return ['period' => $period->fresh(), 'transaction_id' => $transaction->id];
    }

    public function skipPeriod(CltSalaryPeriod $period): void
    {
        abort_unless($period->isPending(), 422);

        $period->update(['status' => CltSalaryPeriod::STATUS_SKIPPED]);
        $workspaceId = $period->cltSalary()->value('workspace_id');
        if ($workspaceId) {
            $this->markAlertsRead((int) $workspaceId, $period->id);
        }
    }

    protected function shouldOpenPeriod(CltSalary $salary, Carbon $month): bool
    {
        $today = now();
        if (! $today->isSameMonth($month)) {
            return false;
        }

        $paymentDay = min(28, max(1, (int) $salary->payment_day));
        $openFrom = $month->copy()->day(max(1, $paymentDay - 3));

        return $today->greaterThanOrEqualTo($openFrom);
    }

    protected function ensureConfirmationAlert(int $workspaceId, CltSalary $salary, CltSalaryPeriod $period): void
    {
        $exists = Alert::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'clt_salary_confirmation')
            ->where('is_read', false)
            ->where('context->clt_salary_period_id', $period->id)
            ->exists();

        if ($exists) {
            return;
        }

        $monthLabel = Carbon::parse($period->reference_month)->translatedFormat('F/Y');
        $net = number_format((float) $period->net_amount, 2, ',', '.');

        Alert::query()->create([
            'workspace_id' => $workspaceId,
            'type' => 'clt_salary_confirmation',
            'severity' => AlertSeverity::Info,
            'title' => 'Confirmar salário CLT — '.$salary->company->name,
            'message' => "Referência {$monthLabel}. Valor líquido sugerido: R$ {$net}. Confira faltas/descontos e confirme em Salário CLT.",
            'context' => [
                'clt_salary_id' => $salary->id,
                'clt_salary_period_id' => $period->id,
                'company_id' => $salary->company_id,
            ],
            'triggered_at' => now(),
        ]);
    }

    protected function markAlertsRead(int $workspaceId, int $periodId): void
    {
        Alert::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'clt_salary_confirmation')
            ->where('context->clt_salary_period_id', $periodId)
            ->update(['is_read' => true]);
    }
}
