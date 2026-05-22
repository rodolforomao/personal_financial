<?php

namespace Modules\Finance\Application\DTOs;

use App\Core\Enums\RecurrenceFrequency;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Spatie\LaravelData\Data;

class CreateTransactionData extends Data
{
    public function __construct(
        public int $workspaceId,
        public TransactionType $type,
        public float $amount,
        public string $description,
        public string $transactionDate,
        public ?int $financialAccountId = null,
        public ?int $categoryId = null,
        public ?int $companyId = null,
        public ?int $projectId = null,
        public TransactionStatus $status = TransactionStatus::Pending,
        public ?string $counterparty = null,
        public ?string $dueDate = null,
        public string $source = 'manual',
        public array $metadata = [],
        public bool $isRecurring = false,
        public ?RecurrenceFrequency $recurrenceFrequency = null,
    ) {}
}
