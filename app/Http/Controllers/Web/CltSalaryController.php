<?php

namespace App\Http\Controllers\Web;

use App\Core\Enums\CompanyType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Services\CltSalaryService;
use Modules\Finance\Infrastructure\Models\CltSalary;
use Modules\Finance\Infrastructure\Models\CltSalaryPeriod;
use Modules\OCR\Application\Services\ReceiptStorageService;

class CltSalaryController extends Controller
{
    public function index(Request $request, CltSalaryService $service): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $pendingPeriods = $service->pendingPeriods($workspaceId);
        $suggestedPayDates = $pendingPeriods->mapWithKeys(fn (CltSalaryPeriod $p) => [
            $p->id => $service->suggestPaymentDate($p->cltSalary, $p->reference_month),
        ]);

        return view('finance.clt-salaries.index', [
            'salaries' => CltSalary::query()
                ->where('workspace_id', $workspaceId)
                ->with(['company', 'category'])
                ->orderBy('id')
                ->get(),
            'pendingPeriods' => $pendingPeriods,
            'suggestedPayDates' => $suggestedPayDates,
            'employers' => Company::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('type', [CompanyType::Employer->value, CompanyType::Payer->value, CompanyType::Client->value])
                ->orderBy('name')
                ->get(),
            'defaultCategoryId' => $service->defaultCategoryId($workspaceId),
        ]);
    }

    public function store(Request $request, CltSalaryService $service): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $validated = $this->validated($request, $workspaceId);

        $service->upsert($workspaceId, $validated);
        $service->ensurePendingPeriods($workspaceId);

        return redirect()
            ->route('clt-salaries.index')
            ->with('success', 'Vínculo CLT salvo. Confirme o valor líquido todo mês após o pagamento.');
    }

    public function update(Request $request, CltSalary $cltSalary, CltSalaryService $service): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($cltSalary->workspace_id === $workspaceId, 404);

        $validated = $this->validated($request, $workspaceId);
        $service->upsert($workspaceId, array_merge($validated, ['company_id' => $cltSalary->company_id]));

        return redirect()
            ->route('clt-salaries.index')
            ->with('success', 'Salário CLT atualizado.');
    }

    public function confirm(
        Request $request,
        CltSalaryPeriod $period,
        CltSalaryService $service,
        ReceiptStorageService $receiptStorage,
    ): RedirectResponse {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $period->load('cltSalary.company');
        abort_unless($period->cltSalary->workspace_id === $workspaceId, 404);

        $validated = $request->validate([
            'net_amount' => 'required|numeric|min:0.01',
            'gross_amount' => 'nullable|numeric|min:0',
            'transaction_date' => 'required|date',
            'receipts' => 'nullable|array|max:5',
            'receipts.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        try {
            $result = $service->confirmPeriod($period, $request->user(), $validated);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['net_amount' => $e->getMessage()])->withInput();
        }

        $receiptCount = 0;
        foreach ($request->file('receipts', []) as $file) {
            $receiptStorage->store(
                $workspaceId,
                $request->user(),
                $file,
                $result['transaction_id'],
                queueOcr: true,
            );
            $receiptCount++;
        }

        $message = 'Salário CLT registrado. Data do pagamento: '
            .\Carbon\Carbon::parse($validated['transaction_date'])->format('d/m/Y').'.';
        if ($receiptCount > 0) {
            $message .= " {$receiptCount} comprovante(s) anexado(s).";
        } else {
            $message .= ' Você pode anexar o holerite ou comprovante na transação.';
        }

        return redirect()
            ->route('transactions.edit', $result['transaction_id'])
            ->with('success', $message);
    }

    public function skip(Request $request, CltSalaryPeriod $period, CltSalaryService $service): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $period->load('cltSalary');
        abort_unless($period->cltSalary->workspace_id === $workspaceId, 404);

        $service->skipPeriod($period);

        return redirect()
            ->route('clt-salaries.index')
            ->with('success', 'Confirmação deste mês ignorada.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, int $workspaceId): array
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'gross_amount' => 'nullable|numeric|min:0',
            'net_amount' => 'required|numeric|min:0.01',
            'payment_day' => 'required|integer|min:1|max:28',
            'description_template' => 'nullable|string|max:120',
            'is_active' => 'sometimes|boolean',
        ]);

        $company = Company::query()->findOrFail($validated['company_id']);
        abort_unless($company->workspace_id === $workspaceId, 404);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
