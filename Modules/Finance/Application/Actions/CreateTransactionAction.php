<?php

namespace Modules\Finance\Application\Actions;

use Modules\Categorization\Application\Services\CategorizationService;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Domain\Events\TransactionCreated;
use Modules\Finance\Infrastructure\Models\RecurringItem;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Finance\Infrastructure\Repositories\TransactionRepository;

class CreateTransactionAction
{
    public function __construct(
        protected TransactionRepository $repository,
        protected CategorizationService $categorization,
    ) {}

    public function execute(CreateTransactionData $data): Transaction
    {
        $attributes = [
            'workspace_id' => $data->workspaceId,
            'financial_account_id' => $data->financialAccountId,
            'category_id' => $data->categoryId,
            'company_id' => $data->companyId,
            'project_id' => $data->projectId,
            'operation_id' => $data->operationId,
            'operation_unit_id' => $data->operationUnitId,
            'type' => $data->type,
            'status' => $data->status,
            'amount' => $data->amount,
            'description' => $data->description,
            'transaction_date' => $data->transactionDate,
            'counterparty' => $data->counterparty,
            'funding_source' => $data->fundingSource,
            'payment_method' => $data->paymentMethod,
            'due_date' => $data->dueDate,
            'source' => $data->source,
            'metadata' => $data->metadata,
            'is_recurring' => $data->isRecurring,
            'recurrence_frequency' => $data->recurrenceFrequency?->value,
        ];

        $autoCategorizeSources = ['telegram', 'whatsapp', 'statement_import', 'ocr', 'api'];
        if (
            ! $data->categoryId
            && in_array($data->source, $autoCategorizeSources, true)
            && ($data->counterparty || $data->description)
        ) {
            $suggestion = $this->categorization->suggest(
                $data->workspaceId,
                $data->description,
                $data->counterparty ?? '',
                $data->type,
            );
            if ($suggestion) {
                $attributes['category_id'] = $suggestion['category_id'];
                $attributes['categorization_confidence'] = $suggestion['confidence'];
            }
        }

        if ($data->isRecurring && $data->recurrenceFrequency) {
            $nextDue = $data->recurrenceFrequency->nextDueFrom($data->transactionDate);
            $recurring = RecurringItem::query()->create([
                'workspace_id' => $data->workspaceId,
                'category_id' => $attributes['category_id'] ?? null,
                'company_id' => $data->companyId,
                'type' => $data->type->value,
                'title' => $data->description,
                'amount' => $data->amount,
                'frequency' => $data->recurrenceFrequency->value,
                'day_of_month' => (int) date('j', strtotime($data->transactionDate)),
                'next_due_at' => $nextDue,
                'last_occurrence_at' => $data->transactionDate,
                'is_active' => true,
            ]);
            $attributes['recurring_item_id'] = $recurring->id;
        }

        $transaction = $this->repository->create($attributes);
        event(new TransactionCreated($transaction));

        return $transaction->load(['category', 'company', 'recurringItem']);
    }
}
