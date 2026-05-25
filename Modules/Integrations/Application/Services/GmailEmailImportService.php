<?php

namespace Modules\Integrations\Application\Services;

use App\Core\Enums\TransactionStatus;
use Illuminate\Support\Facades\Http;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Integrations\Infrastructure\Models\IntegrationConnection;

class GmailEmailImportService
{
    public function __construct(
        protected GmailOAuthService $oauth,
        protected GmailEmailTransactionParser $parser,
        protected CreateTransactionAction $createTransaction,
    ) {}

    /**
     * @return array{created: int, skipped: int, scanned: int, error?: string}
     */
    public function syncWorkspace(int $workspaceId, int $limit = 25): array
    {
        $connections = $this->oauth->connectionsForWorkspace($workspaceId)
            ->filter(fn (IntegrationConnection $connection): bool => $connection->status === 'active' || $connection->status === 'error');

        if ($connections->isEmpty()) {
            return ['created' => 0, 'skipped' => 0, 'scanned' => 0, 'error' => 'Nenhum Gmail conectado neste workspace.'];
        }

        $total = ['created' => 0, 'skipped' => 0, 'scanned' => 0];
        $errors = [];
        foreach ($connections as $connection) {
            $result = $this->syncConnection($connection, $limit);
            $total['created'] += $result['created'];
            $total['skipped'] += $result['skipped'];
            $total['scanned'] += $result['scanned'];
            if (! empty($result['error'])) {
                $errors[] = $result['error'];
            }
        }

        if ($errors !== [] && $total['scanned'] === 0) {
            $total['error'] = implode('; ', $errors);
        }

        return $total;
    }

    /**
     * @return array{created: int, skipped: int, scanned: int, error?: string}
     */
    public function syncUser(int $workspaceId, int $userId, int $limit = 25): array
    {
        $connection = $this->oauth->connection($workspaceId, $userId);
        if (! $connection || ! in_array($connection->status, ['active', 'error'], true)) {
            return ['created' => 0, 'skipped' => 0, 'scanned' => 0, 'error' => 'Seu Gmail não está conectado.'];
        }

        return $this->syncConnection($connection, $limit);
    }

    /**
     * @return array{created: int, skipped: int, scanned: int, error?: string}
     */
    public function syncConnection(IntegrationConnection $connection, int $limit = 25): array
    {
        $workspaceId = (int) $connection->workspace_id;
        $userId = (int) $connection->user_id;

        try {
            $token = $this->oauth->accessToken($connection);
            $messages = $this->listMessages($token, $limit);
            $created = 0;
            $skipped = 0;

            foreach ($messages as $summary) {
                $id = (string) ($summary['id'] ?? '');
                if ($id === '' || $this->alreadyProcessed($workspaceId, $id, $connection)) {
                    $skipped++;
                    continue;
                }

                $message = $this->getMessage($token, $id);
                $parsed = $this->parser->parse($message);
                if (! $parsed) {
                    $this->rememberProcessed($connection, $id);
                    $skipped++;
                    continue;
                }

                $this->createTransaction->execute(new CreateTransactionData(
                    workspaceId: $workspaceId,
                    type: $parsed['type'],
                    amount: $parsed['amount'],
                    description: $parsed['description'],
                    transactionDate: $parsed['transaction_date'],
                    status: TransactionStatus::Confirmed,
                    counterparty: $parsed['counterparty'],
                    paymentMethod: $parsed['payment_method'],
                    source: 'gmail',
                    metadata: [
                        'gmail_user_id' => $userId,
                        'gmail_email' => $connection->settings['email'] ?? $connection->credentials['email'] ?? null,
                        'gmail_message_id' => $id,
                        'gmail_subject' => $parsed['subject'],
                        'gmail_from' => $parsed['from'],
                        'gmail_snippet' => $message['snippet'] ?? null,
                        'raw_text' => $parsed['raw_text'],
                    ],
                ));

                $this->rememberProcessed($connection, $id);
                $created++;
            }

            $connection->update([
                'last_sync_at' => now(),
                'last_error' => null,
                'status' => 'active',
            ]);

            return ['created' => $created, 'skipped' => $skipped, 'scanned' => count($messages)];
        } catch (\Throwable $e) {
            $connection->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            return ['created' => 0, 'skipped' => 0, 'scanned' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listMessages(string $token, int $limit): array
    {
        $response = Http::withToken($token)
            ->timeout(30)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', [
                'maxResults' => max(1, min(100, $limit)),
                'q' => (string) config('financial.integrations.gmail.search_query'),
            ])
            ->throw()
            ->json();

        return is_array($response['messages'] ?? null) ? $response['messages'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getMessage(string $token, string $id): array
    {
        return Http::withToken($token)
            ->timeout(30)
            ->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$id}", [
                'format' => 'full',
            ])
            ->throw()
            ->json();
    }

    protected function alreadyProcessed(int $workspaceId, string $messageId, IntegrationConnection $connection): bool
    {
        $processed = $connection->settings['processed_message_ids'] ?? [];
        if (is_array($processed) && in_array($messageId, $processed, true)) {
            return true;
        }

        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('source', 'gmail')
            ->where('metadata->gmail_user_id', (int) $connection->user_id)
            ->where('metadata->gmail_message_id', $messageId)
            ->exists();
    }

    protected function rememberProcessed(IntegrationConnection $connection, string $messageId): void
    {
        $settings = $connection->settings ?? [];
        $processed = is_array($settings['processed_message_ids'] ?? null)
            ? $settings['processed_message_ids']
            : [];
        array_unshift($processed, $messageId);
        $settings['processed_message_ids'] = array_values(array_unique(array_slice($processed, 0, 500)));
        $connection->update(['settings' => $settings]);
    }
}
