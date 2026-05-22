<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Infrastructure\Models\Transaction;

class StatementImportService
{
    public function __construct(protected CreateTransactionAction $createTransaction) {}

    public function importOfx(int $workspaceId, string $filePath): int
    {
        $content = file_get_contents($filePath);
        $count = 0;

        if (preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/s', $content, $matches)) {
            foreach ($matches[1] as $block) {
                $amount = $this->extractTag($block, 'TRNAMT');
                $date = $this->extractTag($block, 'DTPOSTED');
                $memo = $this->extractTag($block, 'MEMO') ?? $this->extractTag($block, 'NAME') ?? 'Importação OFX';

                if ($amount === null) {
                    continue;
                }

                $type = (float) $amount >= 0 ? TransactionType::Income : TransactionType::Expense;

                $this->createTransaction->execute(new CreateTransactionData(
                    workspaceId: $workspaceId,
                    type: $type,
                    amount: abs((float) $amount),
                    description: $memo,
                    transactionDate: $this->parseOfxDate($date),
                    status: TransactionStatus::Pending,
                    source: 'ofx',
                    counterparty: $this->extractTag($block, 'NAME'),
                ));

                $count++;
            }
        }

        return $count;
    }

    public function importCsv(int $workspaceId, string $filePath, array $mapping): int
    {
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            $amount = (float) ($data[$mapping['amount']] ?? 0);
            $type = $amount >= 0 ? TransactionType::Income : TransactionType::Expense;

            $this->createTransaction->execute(new CreateTransactionData(
                workspaceId: $workspaceId,
                type: $type,
                amount: abs($amount),
                description: $data[$mapping['description']] ?? 'Importação CSV',
                transactionDate: $data[$mapping['date']] ?? now()->toDateString(),
                status: TransactionStatus::Pending,
                source: 'csv',
                counterparty: $data[$mapping['counterparty']] ?? null,
            ));

            $count++;
        }

        fclose($handle);

        return $count;
    }

    protected function extractTag(string $block, string $tag): ?string
    {
        return preg_match("/<{$tag}>([^<]+)/", $block, $m) ? trim($m[1]) : null;
    }

    protected function parseOfxDate(?string $date): string
    {
        if (! $date || strlen($date) < 8) {
            return now()->toDateString();
        }

        return substr($date, 0, 4).'-'.substr($date, 4, 2).'-'.substr($date, 6, 2);
    }
}
